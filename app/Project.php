<?php

namespace App;

use Nicolaslopezj\Searchable\SearchableTrait;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
  use SearchableTrait;
    protected $searchable = [
        'columns' => [
        ],
    ];
    public function pois()
    {
        return $this->hasMany('App\Poi');
    }

}
