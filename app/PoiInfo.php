<?php
namespace App;
use Nicolaslopezj\Searchable\SearchableTrait;
use Illuminate\Database\Eloquent\Model;
class PoiInfo extends Model
{
    use SearchableTrait;
    protected $table = 'poiInfo';
    public function poi()
    {
        return $this->belongsTo('App\Poi');
    }
}