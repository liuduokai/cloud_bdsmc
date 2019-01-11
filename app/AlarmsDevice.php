<?php

namespace App;

use Nicolaslopezj\Searchable\SearchableTrait;
use Illuminate\Database\Eloquent\Model;

class AlarmsDevice extends Model
{
  use SearchableTrait;
    protected $table = 'alarmsDevice';
    protected $searchable = [
        'columns' => [
            'alarms.content' => 10,
        ],
    ];
    public function device()
    {
        return $this->belongsTo('App\Device');
    }
}
