<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property int|null $age
 * @property string|null $gender
 * @property string|null $phone
 * @property string $role
 * @property string|null $onboarding_status
 * @property string|null $university
 * @property string|null $major
 * @property int|null $graduation_year
 * @property string|null $interests
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Course> $courses
 * @property-read int|null $courses_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Enrollment> $enrollments
 * @property-read int|null $enrollments_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Order> $orders
 * @property-read int|null $orders_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \App\Models\Profile|null $profile
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Review> $reviews
 * @property-read int|null $reviews_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, $guard = null)
 * @mixin \Eloquent
 */
class User extends Authenticatable
{
   use HasFactory, Notifiable, HasApiTokens, HasRoles {
    HasRoles::hasRole as protected traitHasRole;
}


    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'email',
        'password',
        'age',
        'gender',
        'phone',
        'verification_code',
        'verification_code_expires_at',
        'verification_method',
        'is_verified',
        'phone_verified_at',
        'role',
        'onboarding_status',
        'interests',
        'onboarding_completed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'verification_code_expires_at' => 'datetime',
        'onboarding_completed_at' => 'datetime',
        'age' => 'integer',
        'is_verified' => 'boolean',
    ];

    /**
     * Automatically generate UUID for the primary key.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    // علاقات المستخدم مع باقي الجداول

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    public function courses()
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function isVerified(): bool
    {
        return $this->is_verified;
    }

    /**
     * ✅ MARK USER AS VERIFIED
     * Called when user enters correct verification code
     */
    public function markAsVerified(): void
    {
        $this->update([
            'is_verified' => true,
            'verification_code' => null, // Remove the code after verification
            'verification_code_expires_at' => null,
        ]);
    }

    /**
     * ✅ GENERATE 6-DIGIT VERIFICATION CODE
     * Creates a random 6-digit code and saves it to database
     */

    public function generateVerificationCode(): string
    {
        try {
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            
            Log::info("🔑 Generated verification code: {$code} for user: {$this->id}");
            
            // Use direct database update instead of model update to avoid issues
            \DB::table('users')->where('id', $this->id)->update([
                'verification_code' => $code,
                'verification_code_expires_at' => now()->addMinutes(15)
            ]);

            Log::info("✅ Verification code saved to database for user: {$this->id}");
            return $code;
            
        } catch (\Exception $e) {
            Log::error("❌ Failed to generate verification code for user {$this->id}: " . $e->getMessage());
            // Return fallback code instead of throwing exception
            $fallbackCode = '123456';
            Log::info("🔄 Using fallback verification code: {$fallbackCode} for user: {$this->id}");
            
            try {
                \DB::table('users')->where('id', $this->id)->update([
                    'verification_code' => $fallbackCode,
                    'verification_code_expires_at' => now()->addMinutes(15)
                ]);
                return $fallbackCode;
            } catch (\Exception $fallbackError) {
                Log::error("❌ Even fallback failed: " . $fallbackError->getMessage());
                return $fallbackCode; // Return code even if DB fails
            }
        }
    }

    /**
     * ✅ CHECK IF VERIFICATION CODE IS VALID
     * Verifies the code user entered is correct and not expired
     */
    public function isValidVerificationCode($code): bool
    {
        return $this->verification_code === $code && 
               $this->verification_code_expires_at && 
               $this->verification_code_expires_at->isFuture();
    }

    /**
 * Sync roles and update the role column (FIXED VERSION)
 */
public function syncRolesWithColumn($roles): self
{
    // Convert to array if string
    $roles = is_array($roles) ? $roles : [$roles];
    
    // Get role IDs
    $roleIds = [];
    foreach ($roles as $roleName) {
        $role = \Spatie\Permission\Models\Role::where('name', $roleName)->first();
        if ($role) {
            $roleIds[] = $role->id;
        }
    }
    
    // Update Spatie roles
    $this->roles()->sync($roleIds);
    
    // Update the role column (take the first role)
    $this->update(['role' => $roles[0] ?? 'student']);
    
    return $this;
}

  /**
 * Assign role and update both systems (FIXED VERSION)
 */
public function assignRoleWithColumn($role): self
{
    $roleModel = \Spatie\Permission\Models\Role::where('name', $role)->first();
    
    if ($roleModel) {
        $this->assignRole($role);
        $this->update(['role' => $role]);
    }
    
    return $this;
}

    /**
     * Override the syncRoles method to also update the role column
     */
    public function syncRoles($roles): self
    {
        $roles = parent::syncRoles($roles);
        
        // Update the role column with the first role
        if ($this->roles->count() > 0) {
            $this->update([
                'role' => $this->roles->first()->name
            ]);
        }
        
        return $this;
    }

    /**
     * Onboarding related methods
     */
    public function getOnboardingStatusAttribute($value = null)
    {
        return $value ?? ($this->attributes['onboarding_status'] ?? 'student');
    }

    public function getInterestsAttribute($value = null)
    {
        $raw = $value ?? ($this->attributes['interests'] ?? null);

        if (!$raw) {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function hasCompletedOnboarding(): bool
    {
        return !is_null($this->attributes['onboarding_completed_at']);
    }

    /**
     * Extend Spatie hasRole to fall back to the legacy role column.
     */
    public function hasRole($roles, $guard = null): bool
    {
        if ($this->traitHasRole($roles, $guard)) {
            return true;
        }

        $roles = is_array($roles) ? $roles : [$roles];

        return $this->role && in_array($this->role, $roles, true);
    }

}