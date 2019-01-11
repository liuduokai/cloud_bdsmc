<?php
namespace App;

use Nicolaslopezj\Searchable\SearchableTrait;
use Illuminate\Database\Eloquent\Model;

class QianXun extends Model
{
    use SearchableTrait;
    protected $table = 'qianxun';
}

