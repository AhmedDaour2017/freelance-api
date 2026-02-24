<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;
        protected $fillable = [
        'title',
        'description',
        'budget',
        'client_id',
        'deadline',
        'status'
    ];

     protected $dates = ['deleted_at'];


    public function client(){
        return $this->belongsTo(User::class, 'client_id');
    }

    public function proposals()
    {
    return $this->hasMany(Proposal::class);
    }



    protected static function booted() {
        static::deleting(function ($project) {
            // عند حذف المشروع، سيقوم بحذف جميع العروض المرتبطة به "نواعم" أيضاً
            $project->proposals()->delete(); 
        });
    }

}
