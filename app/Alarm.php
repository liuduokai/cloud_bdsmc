<?php

namespace App;

use Nicolaslopezj\Searchable\SearchableTrait;
use Illuminate\Database\Eloquent\Model;

class Alarm extends Model
{
  use SearchableTrait;
    protected $table = 'alarms';
    protected $searchable = [
        'columns' => [
            'alarms.content' => 10,
        ],
    ];
    public function sensor()
    {
        return $this->belongsTo('App\Sensor');
    }
    public function device()
    {
        return $this->belongsTo('App\Device');
    }
    public function camera()
    {
        return $this->belongsTo('App\Camera');
    }
}
