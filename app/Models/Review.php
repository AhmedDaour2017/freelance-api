<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'project_id',
        'reviewer_id',
        'user_id',
        'rating',
        'comment',
    ];

    
    public function averageRating() {
    return $this->reviews()->avg('rating') ?: 0;
}

    public function reviewsCount() {
        return $this->reviews()->count();
    }

    public function reviews() {
        return $this->hasMany(Review::class, 'user_id');
    }
}
