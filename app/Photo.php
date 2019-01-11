<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Photo extends Model
{
    protected $table = 'photos';
    public function poi()
    {
        return $this->belongsTo('App\Poi');
    }

    public function photopostions()
    {
        return $this->hasMany('App\PhotoPostion');
    }
}
