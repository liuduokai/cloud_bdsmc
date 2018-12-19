<?php
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2018/8/14
 * Time: 13:51
 */

namespace App;
use Nicolaslopezj\Searchable\SearchableTrait;
use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;

class PoiInfo extends Model
{
    // use SoftDeletes;
    use SearchableTrait;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'poiInfo';

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    /*protected $dates = ['deleted_at'];

    protected $hidden = [
        'deleted_at',
    ];*/
    public function poi()
    {
        return $this->belongsTo('App\Poi');
    }
}