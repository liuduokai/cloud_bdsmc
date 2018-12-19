<?php
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2018/10/30
 * Time: 10:51
 */

namespace App;

use Nicolaslopezj\Searchable\SearchableTrait;
use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;

class Device_test extends Model
{
    //use SoftDeletes;
    use SearchableTrait;

    /*protected $dates = ['deleted_at'];
    protected $hidden = [
        'deleted_at',
    ];*/

}