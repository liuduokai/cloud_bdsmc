<?php

namespace App;

use Nicolaslopezj\Searchable\SearchableTrait;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
  use SearchableTrait;
    protected $table = 'devices';
    protected $searchable = [
        'columns' => [
            'devices.mac' =>10,
        ],
    ];
    public function poi()
    {
        return $this->belongsTo('App\Poi','poi_id','id');
    }
    public function sensors()
    {
        return $this->hasMany('App\Sensor');
    }
    public function alarms()
    {
        return $this->hasMany('App\Alarm');
    }
}
