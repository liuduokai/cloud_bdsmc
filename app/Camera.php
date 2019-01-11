<?php

namespace App;

use Nicolaslopezj\Searchable\SearchableTrait;
use Illuminate\Database\Eloquent\Model;

class Camera extends Model
{
  use SearchableTrait;
    protected $table = 'cameras';
    protected $searchable = [
        'columns' => [
            'pois.name' => 10,
            'pois.location' => 10,
            'pois.props' => 9,
        ],
    ];

    public function poi()
    {
        return $this->belongsTo('App\Poi');
    }

    public function alarms()
    {
        return $this->hasMany('App\Alarm');
    }
}
