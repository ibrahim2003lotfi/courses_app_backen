<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $user_id
 * @property string $status
 * @property array<array-key, mixed>|null $documents
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstructorApplication newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstructorApplication newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstructorApplication query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstructorApplication whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstructorApplication whereDocuments($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstructorApplication whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstructorApplication whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstructorApplication whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstructorApplication whereUserId($value)
 * @mixin \Eloquent
 */
class InstructorApplication extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['user_id', 'status', 'documents'];

    protected $casts = [
        'documents' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
