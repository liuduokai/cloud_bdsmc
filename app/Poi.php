<?php

namespace App;

use Nicolaslopezj\Searchable\SearchableTrait;
use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;

class Poi extends Model
{
  // use SoftDeletes;
  use SearchableTrait;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pois';

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    /*protected $dates = ['deleted_at'];

    protected $hidden = [
        'deleted_at',
    ];*/
    /**
     * Searchable rules.
     *
     * @var array
     */
    protected $searchable = [
        /**
         * Columns and their priority in search results.
         * Columns with higher values are more important.
         * Columns with equal values have equal importance.
         *
         * @var array
         */
        'columns' => [
            'pois.name' => 10,
            'pois.location' => 10,
            'pois.props' => 9,
            // 'users.email' => 5,
            // 'posts.title' => 2,
            // 'posts.body' => 1,
        ],
        // 'joins' => [
        //     'posts' => ['users.id','posts.user_id'],
        // ],
    ];

    public function user()
    {
        return $this->belongsTo('App\User');
    }
    public function devices()
    {
        return $this->hasMany('App\Device');
    }
    public function cameras()
    {
        return $this->hasMany('App\Camera');
    }

    public function photos()
    {
        return $this->hasMany('App\Photo');
    }
}
