<?php

namespace App;

use Nicolaslopezj\Searchable\SearchableTrait;
use Illuminate\Database\Eloquent\Model;
class UsersLog extends Model
{
  use SearchableTrait;
    protected $table = 'usersLog';
    public function users()
    {
        return $this->belongsTo('App\User');
    }
}
