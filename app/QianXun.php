<?php
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2018/10/9
 * Time: 10:35
 */

namespace App;

use Nicolaslopezj\Searchable\SearchableTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QianXun extends Model
{
    use SoftDeletes;
    use SearchableTrait;
    protected $table = 'qianxun';
}

