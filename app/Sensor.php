<?php

namespace App;

use Nicolaslopezj\Searchable\SearchableTrait;
use Illuminate\Database\Eloquent\Model;

class Sensor extends Model
{
  use SearchableTrait;
    protected $table = 'sensors';
    protected $searchable = [
        'columns' => [
            'pois.name' => 10,
            'pois.location' => 10,
            'pois.props' => 9,
        ],
    ];

    public function device()
    {
        return $this->belongsTo('App\Device');
    }
    public function alarms()
    {
        return $this->hasMany('App\Alarm');
    }
}
