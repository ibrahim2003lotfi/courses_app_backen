<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class InstructorApplication extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    
    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'education_level',
        'department',
        'specialization',
        'years_of_experience',
        'experience_description',
        'linkedin_url',
        'portfolio_url',
        'certificates',
        'agreed_to_terms',
        'terms_agreed_at',
        'status',
        'documents',
        'additional_info',
        'reviewed_at',
        'reviewed_by',
        'review_notes',
    ];

    protected $casts = [
        'documents' => 'array',
        'additional_info' => 'array',
        'certificates' => 'array',
        'reviewed_at' => 'datetime',
        'terms_agreed_at' => 'datetime',
        'agreed_to_terms' => 'boolean',
        'years_of_experience' => 'integer',
    ];

    protected $appends = ['status_label', 'status_color'];

    protected static function boot(): void
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    // ==================== RELATIONSHIPS ====================

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ==================== ACCESSORS ====================

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'قيد المراجعة',
            self::STATUS_APPROVED => 'مقبول',
            self::STATUS_REJECTED => 'مرفوض',
            default => 'غير معروف',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            default => 'secondary',
        };
    }

    // ==================== SCOPES ====================

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    // ==================== HELPER METHODS ====================

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function approve(User $reviewer, ?string $notes = null): bool
    {
        return $this->update([
            'status' => self::STATUS_APPROVED,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
            'review_notes' => $notes,
        ]);
    }

    public function reject(User $reviewer, string $reason): bool
    {
        return $this->update([
            'status' => self::STATUS_REJECTED,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
            'review_notes' => $reason,
        ]);
    }
}