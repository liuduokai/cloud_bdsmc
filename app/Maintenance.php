<?php

namespace App;

use Nicolaslopezj\Searchable\SearchableTrait;
use Illuminate\Database\Eloquent\Model;

class Maintenance extends Model
{
  use SearchableTrait;
    protected $searchable = [
        'columns' => [
        ],
    ];
    public function device()
    {
        return $this->belongsTo('App\Device');
    }

    public function user()
    {
        return $this->belongsTo('App\User');
    }
}
