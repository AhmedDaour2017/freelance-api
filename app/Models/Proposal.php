<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proposal extends Model
{
    
    protected $fillable = [
        'price',
        'description',
        'message',
        'project_id',
        'freelancer_id',
        'delivery_days',
        'status'
    ];


    
    public function project()
    {
    return $this->belongsTo(Project::class);
    }

    public function freelancer()
    {
        return $this->belongsTo(User::class, 'freelancer_id');
    }   

}
