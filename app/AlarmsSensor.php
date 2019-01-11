<?php

namespace App;

use Nicolaslopezj\Searchable\SearchableTrait;
use Illuminate\Database\Eloquent\Model;

class AlarmsSensor extends Model
{
  use SearchableTrait;
    protected $table = 'alarmsSensor';
    protected $searchable = [
        'columns' => [
            'alarms.content' => 10,
        ],
    ];
    public function sensor()
    {
        return $this->belongsTo('App\Sensor');
    }
}
