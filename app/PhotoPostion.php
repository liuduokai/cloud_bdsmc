<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PhotoPostion extends Model
{
    protected $table = 'photopositions';
    public function photo()
    {
        return $this->belongsTo('App\Photo');
    }
    public function device()
    {
        return $this->belongsTo('App\Device');
    }
}
