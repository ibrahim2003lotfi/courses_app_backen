<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $instructor_id
 * @property string|null $category_id
 * @property string $title
 * @property string $slug
 * @property string $description
 * @property string $price
 * @property string $level
 * @property int $total_students
 * @property string|null $rating
 * @property int|null $total_ratings
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property-read \App\Models\Category|null $category
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Enrollment> $enrollments
 * @property-read int|null $enrollments_count
 * @property-read \App\Models\User $instructor
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Order> $orders
 * @property-read int|null $orders_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Review> $reviews
 * @property-read int|null $reviews_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Section> $sections
 * @property-read int|null $sections_count
 * @method static \Database\Factories\CourseFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereInstructorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereTotalStudents($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Course extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'instructor_id', 'category_id', 'title', 'slug',
        'description', 'price', 'level', 'total_students', 'rating', 'total_ratings'
    ];

    protected $casts = [
        'rating' => 'decimal:2',
        'total_ratings' => 'integer',
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

    // علاقات
    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function sections()
    {
        return $this->hasMany(Section::class);
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

    /**
     * Check if user can rate this course (has purchased it)
     */
    public function canUserRate($userId): bool
    {
        return $this->enrollments()
            ->where('user_id', $userId)
            ->whereNull('refunded_at')
            ->exists();
    }

    /**
     * Get user's rating for this course
     */
    public function getUserRating($userId)
    {
        return $this->reviews()
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Calculate and update course rating
     */
    public function updateRating(): void
    {
        $ratingStats = $this->reviews()
            ->selectRaw('AVG(rating) as average_rating, COUNT(*) as total_ratings')
            ->first();

        $this->update([
            'rating' => $ratingStats->average_rating ?? null,
            'total_ratings' => $ratingStats->total_ratings ?? 0,
        ]);
    }

    /**
     * Get rating distribution (count of each star rating)
     */
    public function getRatingDistribution(): array
    {
        $stats = $this->reviews()
            ->selectRaw('
                COUNT(*) as total_reviews,
                COUNT(CASE WHEN rating = 5 THEN 1 END) as five_star,
                COUNT(CASE WHEN rating = 4 THEN 1 END) as four_star,
                COUNT(CASE WHEN rating = 3 THEN 1 END) as three_star,
                COUNT(CASE WHEN rating = 2 THEN 1 END) as two_star,
                COUNT(CASE WHEN rating = 1 THEN 1 END) as one_star
            ')
            ->first();

        return [
            'total_reviews' => $stats->total_reviews ?? 0,
            'five_star' => $stats->five_star ?? 0,
            'four_star' => $stats->four_star ?? 0,
            'three_star' => $stats->three_star ?? 0,
            'two_star' => $stats->two_star ?? 0,
            'one_star' => $stats->one_star ?? 0,
        ];
    }

    /**
     * Get average rating formatted (e.g., 4.5)
     */
    public function getFormattedRating(): ?string
    {
        return $this->rating ? number_format($this->rating, 1) : null;
    }

    /**
     * Get star rating percentage for display
     */
    public function getStarRatingPercentage($star): float
    {
        $total = $this->total_ratings;
        if ($total === 0) return 0;

        $distribution = $this->getRatingDistribution();
        $count = $distribution[$star . '_star'] ?? 0;

        return ($count / $total) * 100;
    }

    /**
     * Get simplified rating info for API responses
     */
    public function getRatingInfo(): array
    {
        return [
            'average_rating' => $this->getFormattedRating(),
            'total_ratings' => $this->total_ratings,
            'rating_distribution' => $this->getRatingDistribution(),
        ];
    }
}