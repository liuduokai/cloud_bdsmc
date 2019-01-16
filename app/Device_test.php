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

class Device_test extends Model
{
    use SearchableTrait;
    protected $searchable = [
        'columns' => [
            'device_tests.device_hex_id' =>10,
        ],
    ];
}