<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayoutController extends Controller
{
    public function index(Request $request)
    {
        $query = Payout::with('instructor');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('instructor_id')) {
            $query->where('instructor_id', $request->input('instructor_id'));
        }

        $payouts = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($payouts);
    }

    public function pendingPayouts()
    {
        // Calculate pending payouts for each instructor
        $instructorEarnings = DB::table('orders')
            ->join('courses', 'orders.course_id', '=', 'courses.id')
            ->select(
                'courses.instructor_id',
                DB::raw('SUM(orders.amount) as total_earnings'),
                DB::raw('SUM(orders.amount * 0.2) as platform_fee'), // 20% platform fee
                DB::raw('SUM(orders.amount * 0.8) as instructor_earnings'), // 80% to instructor
                DB::raw('COUNT(orders.id) as order_count')
            )
            ->where('orders.status', 'succeeded')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('payouts')
                    ->whereColumn('payouts.instructor_id', 'courses.instructor_id')
                    ->where('payouts.status', 'completed');
            })
            ->groupBy('courses.instructor_id')
            ->having('total_earnings', '>', 0)
            ->get();

        // Get instructor details
        $instructorIds = $instructorEarnings->pluck('instructor_id');
        $instructors = User::whereIn('id', $instructorIds)->get()->keyBy('id');

        $pendingPayouts = $instructorEarnings->map(function ($earning) use ($instructors) {
            $instructor = $instructors->get($earning->instructor_id);
            return [
                'instructor' => $instructor,
                'total_earnings' => $earning->total_earnings,
                'platform_fee' => $earning->platform_fee,
                'payout_amount' => $earning->instructor_earnings,
                'order_count' => $earning->order_count,
            ];
        });

        return response()->json([
            'pending_payouts' => $pendingPayouts,
            'total_pending_amount' => $pendingPayouts->sum('payout_amount'),
        ]);
    }

    public function createPayout(Request $request)
    {
        $validated = $request->validate([
            'instructor_id' => 'required|uuid|exists:users,id',
            'amount' => 'required|numeric|min:1',
            'method' => 'required|in:manual,stripe,bank_transfer',
            'notes' => 'nullable|string',
        ]);

        $payout = Payout::create($validated);

        return response()->json([
            'message' => 'Payout created successfully',
            'payout' => $payout->load('instructor'),
        ], 201);
    }

    public function processPayout(Request $request, $id)
    {
        $payout = Payout::findOrFail($id);

        if ($payout->status !== 'pending') {
            return response()->json([
                'message' => 'Payout has already been processed'
            ], 400);
        }

        $validated = $request->validate([
            'payment_details' => 'required|array',
            'notes' => 'nullable|string',
        ]);

        $payout->update([
            'status' => 'completed',
            'processed_at' => now(),
            'payment_details' => $validated['payment_details'],
            'notes' => $validated['notes'],
        ]);

        // TODO: Integrate with actual payment provider (Stripe Connect, etc.)

        return response()->json([
            'message' => 'Payout processed successfully',
            'payout' => $payout->fresh('instructor'),
        ]);
    }

    public function exportPayouts(Request $request)
    {
        $query = Payout::with('instructor');

        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->input('from_date'));
        }
        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->input('to_date'));
        }

        $payouts = $query->get();

        // Format for CSV export
        $export = $payouts->map(function ($payout) {
            return [
                'Instructor' => $payout->instructor->name,
                'Email' => $payout->instructor->email,
                'Amount' => $payout->amount,
                'Status' => $payout->status,
                'Method' => $payout->method,
                'Created' => $payout->created_at->format('Y-m-d H:i:s'),
                'Processed' => $payout->processed_at ? $payout->processed_at->format('Y-m-d H:i:s') : 'N/A',
            ];
        });

        return response()->json($export);
    }
}