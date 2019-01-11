<?php

namespace App;

use Nicolaslopezj\Searchable\SearchableTrait;
use Illuminate\Database\Eloquent\Model;
class AlarmsCamera extends Model
{
  use SearchableTrait;
    protected $table = 'alarmsCamera';
    protected $searchable = [
        'columns' => [
            'alarms.content' => 10,
        ],
    ];
    public function camera()
    {
        return $this->belongsTo('App\Camera');
    }
}
