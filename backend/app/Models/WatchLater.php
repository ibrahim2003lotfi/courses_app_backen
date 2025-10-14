<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $user_id
 * @property string $lesson_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Lesson $lesson
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WatchLater newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WatchLater newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WatchLater query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WatchLater whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WatchLater whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WatchLater whereLessonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WatchLater whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WatchLater whereUserId($value)
 * @mixin \Eloquent
 */
class WatchLater extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['user_id', 'lesson_id'];

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

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
}
