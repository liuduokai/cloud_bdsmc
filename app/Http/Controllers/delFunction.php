<?php

use App\Alarm;
use App\AlarmsSensor;
use App\Camera;
use App\Device;
use App\Displacementsensor1;
use App\Photo;
use App\Project;
use App\Sensor;
use App\Poi;


/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2018/12/18
 * Time: 15:38
 */
function _delSensor($id){
    $alarms = Alarm::where('sensor_id',$id)
        ->get();
    foreach ($alarms as $alarm){
        $alarm->delete();
    }

    $alarmsSensors = AlarmsSensor::where('sensor_id',$id)
        ->get();
    foreach ($alarmsSensors as $alarmsSensor){
        $alarmsSensor->delete();
    }

    $displacementsensors = Displacementsensor1::where('device_id',$id)
        ->get();
    foreach ($displacementsensors as $displacementsensor){
        $displacementsensor->delete();
    }

    $sensor = Sensor::findOrFail(intval($id));
    $sensor->delete();
}

function _delDevice($id){
    $device = Device::findOrFail(intval($id));

    $sensors = Sensor::where('device_id',$id)->get();
    foreach ($sensors as $sensor){
        $sensor_id = $sensor->id;
        _delSensor($sensor_id);
    }

    DB::table('crack_device_info')->where('device_table_id','=',$id)->delete();
    DB::table('device_tests_copy')->where('device_table_id','=',$id)->delete();
    DB::table('device_tests')->where('device_table_id','=',$id)->delete();
    DB::table('online')->where('device_id','=',$id)->delete();
    DB::table('alarmsDevice')->where('device_id','=',$id)->delete();
    DB::table('photopositions')->where('device_id','=',$id)->delete();
    DB::table('qianxun')->where('device_id','=',$id)->delete();
    DB::table('gnss_device_info')->where('device_table_id','=',$id)->delete();
    DB::table('registrations')->where('device_id','=',$id)->delete();
    DB::table('alarms')->where('device_id','=',$id)->delete();

    $device->delete();
}

function _delPoi($id){
    $poi = Poi::findOrFail(intval($id));

    $devices = Device::where('poi_id','=',$id)->get();


    foreach ($devices as $device){
        $device_id = $device->id;
        _delDevice($device_id);

    }

    DB::table('poiInfo')->where('poi_id','=',$id)->delete();

    $cameras = DB::table('cameras')->where('poi_id','=',$id)->get();
    foreach ($cameras as $camera){
        $cam_id = $camera->id;
        _delCamera($cam_id);
    }

    $photos = DB::table('photos')->where('poi_id','=',$id)->get();
    foreach ($photos as $photo){
        $pho_id = $photo->id;
        _delPoiPhoto($pho_id);
    }

    $poi->delete();
}

function _delProject($id){

    $project = Project::findOrFail(intval($id));
    $pois = Poi::where('project_id','=',$id)->get();
    foreach ($pois as $pois){
        $poi_id = $pois->id;
        _delPoi($poi_id);
    }

    $project->delete();
}

function _delCamera($id){

    $cam = Camera::findOrFail(intval($id));
    DB::table('alarmsCamera')->where('camera_id','=',$id)->delete();
    $cam->delete();
}

function _delPoiPhoto($id){
    $photo = Photo::findOrFail(intval($id));
    DB::table('photopositions')->where('photo_id','=',$id)->delete();
    $photo->delete();
}

