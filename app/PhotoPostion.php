<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhotoPostion extends Model
{
  use SoftDeletes;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'photopositions';

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

    public function photo()
    {
        return $this->belongsTo('App\Photo');
    }
    public function device()
    {
        return $this->belongsTo('App\Device');
    }
}
