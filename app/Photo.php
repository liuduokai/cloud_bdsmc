<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Photo extends Model
{
  use SoftDeletes;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'photos';

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    protected $hidden = [
        'deleted_at',
    ];
    /**
     * Searchable rules.
     *
     * @var array
     */

    public function poi()
    {
        return $this->belongsTo('App\Poi');
    }

    public function photopostions()
    {
        return $this->hasMany('App\PhotoPostion');
    }
}
