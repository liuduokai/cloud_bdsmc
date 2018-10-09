<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Insar extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'insar';


    public function project()
    {
        return $this->belongsTo('App\Project');
    }
    public function datas()
    {
        return $this->hasMany('App\InsarData');
    }
}
