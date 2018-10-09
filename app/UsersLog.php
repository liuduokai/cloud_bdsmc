<?php

namespace App;

use Nicolaslopezj\Searchable\SearchableTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class UsersLog extends Model
{
  use SoftDeletes;
  use SearchableTrait;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'usersLog';

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];
    protected $hidden = [
        'deleted_at',
    ];

    public function users()
    {
        return $this->belongsTo('App\User');
    }
}
