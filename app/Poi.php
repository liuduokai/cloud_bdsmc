<?php

namespace App;

use Nicolaslopezj\Searchable\SearchableTrait;
use Illuminate\Database\Eloquent\Model;

class Poi extends Model
{
  use SearchableTrait;
    protected $table = 'pois';
    protected $searchable = [
        'columns' => [
            'pois.name' => 10,
            'pois.location' => 10,
            'pois.props' => 9,
        ],
    ];

    public function user()
    {
        return $this->belongsTo('App\User');
    }
    public function devices()
    {
        return $this->hasMany('App\Device');
    }
    public function cameras()
    {
        return $this->hasMany('App\Camera');
    }

    public function photos()
    {
        return $this->hasMany('App\Photo');
    }
}
