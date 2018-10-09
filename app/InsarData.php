<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InsarData extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'insar_data';


    public function insar()
    {
        return $this->belongsTo('App\Insar');
    }
}
