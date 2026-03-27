<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Portfolio extends Model
{
    

    protected $fillable = [
        'user_id', 
        'title', 
        'description', 
        'image', 
        'link'
    ];



    protected $appends = ['image_url'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }


        public function getImageUrlAttribute()
    {
        if ($this->image) {   
        return asset('storage/' . $this->image);
        }
    }

}
