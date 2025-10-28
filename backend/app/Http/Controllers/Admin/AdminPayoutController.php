<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Models\Order;
use App\Models\User;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AdminPayoutController extends Controller
{
    public function index(Request $request)
    {
        $query = Payout::with('instructor');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by instructor
        if ($request->has('instructor_id')) {
            $query->where('instructor_id', $request->input('instructor_id'));
        }

        // Date range filter
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        $payouts = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        // Get summary statistics
        $stats = [
            'total_pending' => Payout::where('status', 'pending')->sum('amount'),
            'total_completed' => Payout::where('status', 'completed')->sum('amount'),
            'pending_count' => Payout::where('status', 'pending')->count(),
            'this_month' => Payout::where('status', 'completed')
                ->whereMonth('processed_at', now()->month)
                ->sum('amount'),
        ];

        // Get instructors for filter dropdown
        $instructors = User::role('instructor')->get(['id', 'name']);

        return Inertia::render('Admin/Payouts/Index', [
            'payouts' => $payouts,
            'stats' => $stats,
            'instructors' => $instructors,
            'filters' => $request->only(['status', 'instructor_id', 'from_date', 'to_date']),
        ]);
    }

    public function pendingPayouts()
    {
        // Calculate pending payouts for each instructor
        $instructorEarnings = DB::table('orders')
            ->join('courses', 'orders.course_id', '=', 'courses.id')
            ->join('users', 'courses.instructor_id', '=', 'users.id')
            ->select(
                'users.id as instructor_id',
                'users.name as instructor_name',
                'users.email as instructor_email',
                DB::raw('COUNT(DISTINCT orders.id) as total_orders'),
                DB::raw('SUM(orders.amount) as total_sales'),
                DB::raw('SUM(orders.amount * 0.2) as platform_fee'), // 20% platform fee
                DB::raw('SUM(orders.amount * 0.8) as instructor_earnings'), // 80% to instructor
                DB::raw('MIN(orders.created_at) as earliest_order'),
                DB::raw('MAX(orders.created_at) as latest_order')
            )
            ->where('orders.status', 'succeeded')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('payouts')
                    ->whereColumn('payouts.instructor_id', 'users.id')
                    ->where('payouts.status', '!=', 'failed')
                    ->whereMonth('payouts.created_at', '=', DB::raw('MONTH(orders.created_at)'))
                    ->whereYear('payouts.created_at', '=', DB::raw('YEAR(orders.created_at)'));
            })
            ->groupBy('users.id', 'users.name', 'users.email')
            ->having('total_sales', '>', 0)
            ->orderBy('instructor_earnings', 'desc')
            ->get();

        // Get course breakdown for each instructor
        $instructorCourses = [];
        foreach ($instructorEarnings as $earning) {
            $courses = Course::where('instructor_id', $earning->instructor_id)
                ->withCount(['orders' => function ($query) {
                    $query->where('status', 'succeeded');
                }])
                ->with(['orders' => function ($query) {
                    $query->where('status', 'succeeded')
                        ->select('course_id', DB::raw('SUM(amount) as revenue'))
                        ->groupBy('course_id');
                }])
                ->get();
            
            $instructorCourses[$earning->instructor_id] = $courses;
        }

        return Inertia::render('Admin/Payouts/Pending', [
            'pendingPayouts' => $instructorEarnings,
            'instructorCourses' => $instructorCourses,
            'totalPendingAmount' => collect($instructorEarnings)->sum('instructor_earnings'),
        ]);
    }

    public function create(Request $request)
    {
        // GET THE AUTHENTICATED USER HERE
        $adminUser = $request->user(); // or Auth::user()
        
        $validated = $request->validate([
            'instructor_id' => 'required|uuid|exists:users,id',
            'amount' => 'required|numeric|min:1',
            'method' => 'required|in:manual,stripe,bank_transfer,paypal',
            'notes' => 'nullable|string|max:500',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date|after_or_equal:period_start',
        ]);

        // Check if instructor has sufficient earnings
        $earnings = $this->calculateInstructorEarnings($validated['instructor_id']);
        
        if ($earnings['available_balance'] < $validated['amount']) {
            return back()->withErrors([
                'amount' => 'Amount exceeds available balance of $' . $earnings['available_balance']
            ]);
        }

        $payout = Payout::create([
            'instructor_id' => $validated['instructor_id'],
            'amount' => $validated['amount'],
            'status' => 'pending',
            'method' => $validated['method'],
            'notes' => $validated['notes'],
            'payment_details' => [
                'period_start' => $validated['period_start'] ?? null,
                'period_end' => $validated['period_end'] ?? null,
                'created_by' => $adminUser ? $adminUser->id : null, // Now it's defined
            ],
        ]);

        return redirect()->route('admin.payouts.show', $payout->id)
            ->with('success', 'Payout created successfully');
    }

    public function show($id)
    {
        $payout = Payout::with(['instructor.profile', 'instructor.courses'])
            ->findOrFail($id);

        // Get related orders for this payout period
        $relatedOrders = [];
        if (isset($payout->payment_details['period_start']) && isset($payout->payment_details['period_end'])) {
            $relatedOrders = Order::with('course')
                ->whereHas('course', function ($query) use ($payout) {
                    $query->where('instructor_id', $payout->instructor_id);
                })
                ->where('status', 'succeeded')
                ->whereBetween('created_at', [
                    $payout->payment_details['period_start'],
                    $payout->payment_details['period_end']
                ])
                ->get();
        }

        return Inertia::render('Admin/Payouts/Show', [
            'payout' => $payout,
            'relatedOrders' => $relatedOrders,
        ]);
    }

    public function process(Request $request, $id)
    {
        $payout = Payout::findOrFail($id);

        if ($payout->status !== 'pending') {
            return back()->with('error', 'Only pending payouts can be processed');
        }

        // GET THE AUTHENTICATED USER HERE
        $adminUser = $request->user(); // or Auth::user()

        $validated = $request->validate([
            'transaction_id' => 'nullable|string|max:255',
            'payment_proof' => 'nullable|file|mimes:pdf,jpg,png|max:2048',
            'notes' => 'nullable|string|max:500',
        ]);

        // Handle payment proof upload
        $proofPath = null;
        if ($request->hasFile('payment_proof')) {
            $proofPath = $request->file('payment_proof')->store('payout-proofs', 's3');
        }

        // Update payout status
        $payout->update([
            'status' => 'processing',
            'payment_details' => array_merge($payout->payment_details ?? [], [
                'transaction_id' => $validated['transaction_id'] ?? null,
                'payment_proof' => $proofPath,
                'processed_by' => $adminUser ? $adminUser->id : null, // Now it's defined
                'processed_at' => now(),
            ]),
            'notes' => $payout->notes . "\n" . ($validated['notes'] ?? ''),
        ]);

        // TODO: Integrate with actual payment provider (Stripe Connect, PayPal, etc.)
        // For now, we'll mark it as completed after "processing"
        $this->completePayoutProcess($payout);

        return redirect()->route('admin.payouts.index')
            ->with('success', 'Payout is being processed');
    }

    public function complete(Request $request, $id)
    {
        $payout = Payout::findOrFail($id);

        if (!in_array($payout->status, ['pending', 'processing'])) {
            return back()->with('error', 'Invalid payout status for completion');
        }

        // GET THE AUTHENTICATED USER HERE
        $adminUser = $request->user(); // or Auth::user()

        $validated = $request->validate([
            'final_amount' => 'required|numeric|min:0',
            'completion_notes' => 'nullable|string|max:500',
        ]);

        $payout->update([
            'status' => 'completed',
            'processed_at' => now(),
            'amount' => $validated['final_amount'], // In case of adjustments
            'payment_details' => array_merge($payout->payment_details ?? [], [
                'completed_by' => $adminUser ? $adminUser->id : null, // Now it's defined
                'completed_at' => now(),
            ]),
            'notes' => $payout->notes . "\n" . ($validated['completion_notes'] ?? ''),
        ]);

        // Send notification to instructor
        // TODO: Implement notification

        return redirect()->route('admin.payouts.index')
            ->with('success', 'Payout marked as completed');
    }

    public function cancel(Request $request, $id)
    {
        $payout = Payout::findOrFail($id);

        if (in_array($payout->status, ['completed', 'failed'])) {
            return back()->with('error', 'Cannot cancel this payout');
        }

        // GET THE AUTHENTICATED USER HERE
        $adminUser = $request->user(); // or Auth::user()

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $payout->update([
            'status' => 'failed',
            'payment_details' => array_merge($payout->payment_details ?? [], [
                'cancelled_by' => $adminUser ? $adminUser->id : null, // Now it's defined
                'cancelled_at' => now(),
                'cancellation_reason' => $validated['reason'],
            ]),
        ]);

        return redirect()->route('admin.payouts.index')
            ->with('success', 'Payout cancelled');
    }

    public function export(Request $request)
    {
        $query = Payout::with('instructor');

        // Apply filters
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $payouts = $query->get();

        // Format for CSV export
        $export = $payouts->map(function ($payout) {
            return [
                'Payout ID' => $payout->id,
                'Instructor' => $payout->instructor->name,
                'Email' => $payout->instructor->email,
                'Amount' => $payout->amount,
                'Status' => $payout->status,
                'Method' => $payout->method,
                'Created' => $payout->created_at->format('Y-m-d H:i:s'),
                'Processed' => $payout->processed_at ? $payout->processed_at->format('Y-m-d H:i:s') : 'N/A',
                'Transaction ID' => $payout->payment_details['transaction_id'] ?? 'N/A',
            ];
        });

        return Inertia::render('Admin/Payouts/Export', [
            'payouts' => $export,
            'filters' => $request->all(),
        ]);
    }

    private function calculateInstructorEarnings($instructorId)
    {
        $totalEarnings = Order::join('courses', 'orders.course_id', '=', 'courses.id')
            ->where('courses.instructor_id', $instructorId)
            ->where('orders.status', 'succeeded')
            ->sum('orders.amount');

        $totalPaidOut = Payout::where('instructor_id', $instructorId)
            ->whereIn('status', ['completed', 'processing'])
            ->sum('amount');

        $platformFee = $totalEarnings * 0.2; // 20% platform fee
        $instructorShare = $totalEarnings * 0.8; // 80% to instructor

        return [
            'total_sales' => $totalEarnings,
            'platform_fee' => $platformFee,
            'instructor_earnings' => $instructorShare,
            'total_paid_out' => $totalPaidOut,
            'available_balance' => $instructorShare - $totalPaidOut,
        ];
    }

    private function completePayoutProcess($payout)
    {
        // This would integrate with actual payment provider
        // For demonstration, we'll just mark as completed after a delay
        
        // In production, this would be handled by a webhook or job
        $payout->update([
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }
}