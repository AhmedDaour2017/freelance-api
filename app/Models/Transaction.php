<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    //
    protected $fillable = [
        'user_id',
        'amount',
        'type',
        'trackable_type',
        'trackable_id',
        'description',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

// علاقة Polymorphic لربط الحركة بمصدرها (سحب، مشروع، شحن)
    public function trackable() {
        return $this->morphTo();
    }
}
