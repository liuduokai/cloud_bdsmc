<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InsarData extends Model
{
    protected $table = 'insar_data';
    public function insar()
    {
        return $this->belongsTo('App\Insar');
    }
}
