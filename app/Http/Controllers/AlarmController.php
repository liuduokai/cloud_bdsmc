<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Alarm;
use App\AlarmsDevice;
use App\AlarmsCamera;
use App\AlarmsSensor;
use App\Sensor;
use App\Camera;
use App\Device;
use App\Registration;
use App\Maintenance;
include_once 'addUserLog.php';
class AlarmController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
          'addReg',
          'deviceAlarms',
          'sensorAlarms',
        ]]);

        DB::connection()->enableQueryLog();
    }

    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\Guard
     */
    public function guard()
    {
        return Auth::guard();
    }
    public function countAlarms()
    {
      $count = Alarm::where('state',0)->count();
      return response()->json(['cnt'=>$count]);
    }
    public function updateAlarm($id)
    {
      $alarm = Alarm::findOrFail(intval($id));
      $alarm->state = 1;
      $alarm->save();
      addUserLog('updateAlarm',$this->guard()->user()->id,3);
      return response()->json(['message' => 'updated_ok']);
    }

    public function sensorAlarmsById(Request $request)
    {
      $this->validate($request, [
            'id' => 'required',
            'pn' => 'required',  //page_num
            'ps' => 'required',  //page_size
      ]);

      $alarms = DB::table('alarmsSensor')
            ->join('sensors', 'sensors.id', '=', 'alarmsSensor.sensor_id')
            ->select('alarmsSensor.*', 
              'sensors.name as name',
              'sensors.up1',
              'sensors.down1',
              'sensors.up2',
              'sensors.down2')
            ->where('sensors.device_id',$request->id)
            ->skip($request->ps * $request->pn)
            ->take($request->ps)
            ->get();

      return response()->json($alarms);
    }

    public function deviceAlarmsById(Request $request)
    {
      $this->validate($request, [
            'id' => 'required',
            'pn' => 'required',  //page_num
            'ps' => 'required',  //page_size
      ]);

      $alarmsDevice = AlarmsDevice::where('device_id',$request->id)
      ->skip($request->ps * $request->pn)
      ->take($request->ps)
      ->get();

      return response()->json($alarmsDevice);
    }

    public function cameraAlarmsById(Request $request)
    {
      $this->validate($request, [
            'id' => 'required',
            'pn' => 'required',  //page_num
            'ps' => 'required',  //page_size
      ]);

      $alarmsCamera = AlarmsCamera::where('camera_id',$request->id)
      ->skip($request->ps * $request->pn)
      ->take($request->ps)
      ->get();

      return response()->json($alarmsCamera);
    }

    public function alarmsDevice(Request $request)
    {
      $this->validate($request, [
            'pn' => 'required',  //page_num
            'ps' => 'required',  //page_size
      ]);

      /* if($this->guard()->user()->type == 1){
        $pois=$this->guard()->user()->project->pois;
      }else{
        $pois=$this->guard()->user()->pois;
      }

      $poiIds = [];
      foreach ($pois as $poi) {
        array_push($poiIds, $poi->id);
      }

      $alarms = DB::table('pois')
            ->join('devices', 'devices.poi_id', '=', 'pois.id')
            ->join('alarmsDevice', 'devices.id', '=', 'alarmsDevice.device_id')
            ->select('alarmsDevice.*', 'devices.name as name', 'pois.name as poi_name', 'pois.location as poi_location')
            ->whereIn('pois.id',$poiIds)
            ->skip($request->ps * $request->pn)
            ->take($request->ps)
            ->get();

      return response()->json($alarms); */
      if($request->has('id2')){
        $alarms = DB::table('pois')
        ->join('devices', 'devices.poi_id', '=', 'pois.id')
        ->join('alarmsDevice', 'devices.id', '=', 'alarmsDevice.device_id')
        ->select('alarmsDevice.*',
         'devices.name as name',
         'devices.id as id',
            'devices.mac as mac',
         'pois.name as poi_name',
         'pois.location as poi_location')
        ->where('devices.id2',$request->id2)
        ->orderBy('alarmsDevice.time', 'desc')
        ->skip($request->ps * $request->pn)
        ->take($request->ps)
        ->get();

        $totalCount = DB::table('pois')
        ->join('devices', 'devices.poi_id', '=', 'pois.id')
        ->join('alarmsDevice', 'devices.id', '=', 'alarmsDevice.device_id')
        ->select('alarmsDevice.*',
         'devices.name as name', 
         'pois.name as poi_name',
         'pois.location as poi_location')
        ->where('devices.id2',$request->id2)
        ->count();

       $retalarm=['totalCount'=>$totalCount,'alarms'=>$alarms];

       return response()->json($retalarm);
      }elseif($request->has('id')){
        $alarms = DB::table('pois')
        ->join('devices', 'devices.poi_id', '=', 'pois.id')
        ->join('alarmsDevice', 'devices.id', '=', 'alarmsDevice.device_id')
        ->select('alarmsDevice.*',
         'devices.name as name',
         'devices.id as id',
            'devices.mac as mac',
         'pois.name as poi_name',
         'pois.location as poi_location')
        ->where('devices.id',$request->id)
        ->orderBy('alarmsDevice.time', 'desc')
        ->skip($request->ps * $request->pn)
        ->take($request->ps)
        ->get();

        $totalCount = DB::table('pois')
        ->join('devices', 'devices.poi_id', '=', 'pois.id')
        ->join('alarmsDevice', 'devices.id', '=', 'alarmsDevice.device_id')
        ->select('alarmsDevice.*',
         'devices.name as name', 
         'pois.name as poi_name',
         'pois.location as poi_location')
        ->where('devices.id',$request->id)
        ->count();

       $retalarm=['totalCount'=>$totalCount,'alarms'=>$alarms];

       return response()->json($retalarm);
      }elseif($request->has('lvl')){
        $alarms = DB::table('pois')
        ->join('devices', 'devices.poi_id', '=', 'pois.id')
        ->join('alarmsDevice', 'devices.id', '=', 'alarmsDevice.device_id')
        ->select('alarmsDevice.*',
         'devices.name as name',
         'devices.id as id',
            'devices.mac as mac',
         'pois.name as poi_name',
         'pois.location as poi_location')
        ->where('alarmsDevice.type',$request->lvl)
        ->orderBy('alarmsDevice.time', 'desc')
        ->skip($request->ps * $request->pn)
        ->take($request->ps)
        ->get();

        $totalCount = DB::table('pois')
        ->join('devices', 'devices.poi_id', '=', 'pois.id')
        ->join('alarmsDevice', 'devices.id', '=', 'alarmsDevice.device_id')
        ->select('alarmsDevice.*',
         'devices.name as name', 
         'pois.name as poi_name',
         'pois.location as poi_location')
         ->where('alarmsDevice.type',$request->lvl)
        ->count(); 

        $retalarm=['totalCount'=>$totalCount,'alarms'=>$alarms];

        return response()->json($retalarm);

      }elseif($request->has('starttime') || $request->has('endtime')){
        if($request->has('starttime') && $request->has('endtime')){             //若只输入了开始时间或结束时间，自动将开始时间补全为1970年1月1日（UTC/GMT的午夜）
          $start = date_create($request->starttime);                            //或将结束时间补全为当前时间
          $end = date_create($request->endtime);
        }elseif($request->has('endtime')){
          $start = date_create('1970-01-01 00:00:00');
          $end = date_create($request->endtime);
        }else{
          $start = date_create($request->starttime);
          $end = date_create();
        }
       
        if($start > $end)                                                       //若输入的开始时间大于结束时间
          return 'wrong time';
  
        $alarms = DB::table('pois')
        ->join('devices', 'devices.poi_id', '=', 'pois.id')
        ->join('alarmsDevice', 'devices.id', '=', 'alarmsDevice.device_id')
        ->select('alarmsDevice.*',
         'devices.name as name',
            'devices.id as id',
            'devices.mac as mac',
         'pois.name as poi_name',
         'pois.location as poi_location')
         ->where([
         ['alarmsDevice.time','>',$start],
         ['alarmsDevice.time','<',$end],
         ])
         ->orderBy('alarmsDevice.time', 'desc')
         ->skip($request->ps * $request->pn)
         ->take($request->ps)
         ->get();

        $totalCount = DB::table('pois')
            ->join('devices', 'devices.poi_id', '=', 'pois.id')
            ->join('alarmsDevice', 'devices.id', '=', 'alarmsDevice.device_id')
            ->select('alarmsDevice.*',
             'devices.name as name', 
             'pois.name as poi_name', 
             'pois.location as poi_location')
              ->where([
                ['alarmsDevice.time','>',$start],
                ['alarmsDevice.time','<',$end],
              ])
              ->count();

        $retalarm=['totalCount'=>$totalCount,'alarms'=>$alarms];
        return response()->json($retalarm);


      }else{
        if($this->guard()->user()->type == 1){
          $pois=$this->guard()->user()->project->pois;
        }else{
          $pois=$this->guard()->user()->pois;
        }
  
        $poiIds = [];
        foreach ($pois as $poi) {
          array_push($poiIds, $poi->id);
        }
  
        $alarms = DB::table('pois')
              ->join('devices', 'devices.poi_id', '=', 'pois.id')
              ->join('alarmsDevice', 'devices.id', '=', 'alarmsDevice.device_id')
              ->select('alarmsDevice.*', 'devices.id as id','devices.mac as mac','devices.name as name', 'pois.name as poi_name', 'pois.location as poi_location')
              ->whereIn('pois.id',$poiIds)
              ->skip($request->ps * $request->pn)
              ->take($request->ps)
              ->get();

         $totalCount = DB::table('pois')
              ->join('devices', 'devices.poi_id', '=', 'pois.id')
              ->join('alarmsDevice', 'devices.id', '=', 'alarmsDevice.device_id')
              ->select('alarmsDevice.*', 'devices.name as name', 'pois.name as poi_name', 'pois.location as poi_location')
              ->whereIn('pois.id',$poiIds)
              ->count();
  
          $retalarm=['totalCount'=>$totalCount,'alarms'=>$alarms];
          return response()->json($retalarm);
      }

    }

    public function alarmsSensor(Request $request)  
    {                        
      $this->validate($request, [
        'pn' => 'required',  //page_num
        'ps' => 'required',  //page_size
      ]);
      if($request->has('id2')){                          //根据id查询，需要参数id
        
        $alarms = DB::table('pois')
        ->join('devices', 'devices.poi_id', '=', 'pois.id')
        ->join('sensors', 'devices.id', '=', 'sensors.device_id')
        ->join('alarmsSensor', 'sensors.id', '=', 'alarmsSensor.sensor_id')
        ->select(
         'alarmsSensor.*',
         'devices.name as name',
            'devices.id as id',
            'devices.mac as mac',
         'sensors.name as sensor_name',
         'pois.name as poi_name', 
         'pois.location as poi_location',
         'sensors.up1',
         'sensors.down1',
         'sensors.up2',
         'sensors.down2')
        ->where('devices.id2',$request->id2)
        ->orderBy('alarmsSensor.time', 'desc')
        ->skip($request->ps * $request->pn)
        ->take($request->ps)
        ->get();

        $totalCount = DB::table('pois')
        ->join('devices', 'devices.poi_id', '=', 'pois.id')
        ->join('sensors', 'devices.id', '=', 'sensors.device_id')
        ->join('alarmsSensor', 'sensors.id', '=', 'alarmsSensor.sensor_id')
        ->select(
         'alarmsSensor.*',
         'devices.name as name', 
         'sensors.name as sensor_name',
         'pois.name as poi_name', 
         'pois.location as poi_location',
         'sensors.up1',
         'sensors.down1',
         'sensors.up2',
         'sensors.down2')
        ->where('devices.id2',$request->id2)
        ->count();

       $retalarm=['totalCount'=>$totalCount,'alarms'=>$alarms];

       return response()->json($retalarm);
      }

      if($request->has('id')){                          //根据id查询，需要参数id
        
        $alarms = DB::table('pois')
        ->join('devices', 'devices.poi_id', '=', 'pois.id')
        ->join('sensors', 'devices.id', '=', 'sensors.device_id')
        ->join('alarmsSensor', 'sensors.id', '=', 'alarmsSensor.sensor_id')
        ->select(
         'alarmsSensor.*',
         'devices.name as name',
            'devices.id as id',
            'devices.mac as mac',
         'sensors.name as sensor_name',
         'pois.name as poi_name', 
         'pois.location as poi_location',
         'sensors.up1',
         'sensors.down1',
         'sensors.up2',
         'sensors.down2')
        ->where('sensors.device_id',$request->id)
        ->orderBy('alarmsSensor.time', 'desc')
        ->skip($request->ps * $request->pn)
        ->take($request->ps)
        ->get();

        $totalCount = DB::table('pois')
        ->join('devices', 'devices.poi_id', '=', 'pois.id')
        ->join('sensors', 'devices.id', '=', 'sensors.device_id')
        ->join('alarmsSensor', 'sensors.id', '=', 'alarmsSensor.sensor_id')
        ->select(
         'alarmsSensor.*',
         'devices.name as name', 
         'sensors.name as sensor_name',
         'pois.name as poi_name', 
         'pois.location as poi_location',
         'sensors.up1',
         'sensors.down1',
         'sensors.up2',
         'sensors.down2')
        ->where('sensors.device_id',$request->id)
        ->count();

       $retalarm=['totalCount'=>$totalCount,'alarms'=>$alarms];

       return response()->json($retalarm);
      }elseif($request->has('lvl')){                                    //根据报警等级查询，需要参数lvl

        $alarms = DB::table('pois')
        ->join('devices', 'devices.poi_id', '=', 'pois.id')
        ->join('sensors', 'devices.id', '=', 'sensors.device_id')
        ->join('alarmsSensor', 'sensors.id', '=', 'alarmsSensor.sensor_id')
        ->select(
         'alarmsSensor.*',
         'devices.name as name',
            'devices.id as id',
            'devices.mac as mac',
         'sensors.name as sensor_name',
         'pois.name as poi_name', 
         'pois.location as poi_location',
         'sensors.up1',
         'sensors.down1',
         'sensors.up2',
         'sensors.down2')
        ->where('alarmsSensor.type',$request->lvl)
        ->orderBy('alarmsSensor.time', 'desc')
        ->skip($request->ps * $request->pn)
        ->take($request->ps)
        ->get();

        $totalCount = DB::table('pois')
        ->join('devices', 'devices.poi_id', '=', 'pois.id')
        ->join('sensors', 'devices.id', '=', 'sensors.device_id')
        ->join('alarmsSensor', 'sensors.id', '=', 'alarmsSensor.sensor_id')
        ->select(
         'alarmsSensor.*',
         'devices.name as name', 
         'sensors.name as sensor_name',
         'pois.name as poi_name', 
         'pois.location as poi_location',
         'sensors.up1',
         'sensors.down1',
         'sensors.up2',
         'sensors.down2')
        ->where('alarmsSensor.type',$request->lvl)
        ->count(); 

        $retalarm=['totalCount'=>$totalCount,'alarms'=>$alarms];

        return response()->json($retalarm);
      }elseif($request->has('starttime') || $request->has('endtime')){          //根据时间查询，需要参数starttime or endtime
        
        if($request->has('starttime') && $request->has('endtime')){             //若只输入了开始时间或结束时间，自动将开始时间补全为1970年1月1日（UTC/GMT的午夜）
          $start = date_create($request->starttime);                            //或将结束时间补全为当前时间
          $end = date_create($request->endtime);
        }elseif($request->has('endtime')){
          $start = date_create('1970-01-01 00:00:00');
          $end = date_create($request->endtime);
        }else{
          $start = date_create($request->starttime);
          $end = date_create();
        }
       
        if($start > $end)                                                       //若输入的开始时间大于结束时间
          return 'wrong time';
  
        $alarms = DB::table('pois')
          ->join('devices', 'devices.poi_id', '=', 'pois.id')
          ->join('sensors', 'devices.id', '=', 'sensors.device_id')
          ->join('alarmsSensor', 'sensors.id', '=', 'alarmsSensor.sensor_id')
          ->select(
          'alarmsSensor.*',
          'devices.name as name',
              'devices.id as id',
              'devices.mac as mac',
          'sensors.name as sensor_name',
          'pois.name as poi_name', 
          'pois.location as poi_location',
          'sensors.up1',
          'sensors.down1',
          'sensors.up2',
          'sensors.down2')
         ->where([
         ['alarmsSensor.time','>',$start],
         ['alarmsSensor.time','<',$end],
         ])
         ->orderBy('alarmsSensor.time', 'desc')
         ->skip($request->ps * $request->pn)
         ->take($request->ps)
         ->get();

        $totalCount = DB::table('pois')
            ->join('devices', 'devices.poi_id', '=', 'pois.id')
            ->join('sensors', 'devices.id', '=', 'sensors.device_id')
            ->join('alarmsSensor', 'sensors.id', '=', 'alarmsSensor.sensor_id')
            ->select(
             'alarmsSensor.*',
             'devices.name as name', 
             'sensors.name as sensor_name',
             'pois.name as poi_name', 
             'pois.location as poi_location',
             'sensors.up1',
             'sensors.down1',
             'sensors.up2',
             'sensors.down2')
              ->where([
                ['alarmsSensor.time','>',$start],
                ['alarmsSensor.time','<',$end],
              ])
              ->count();

        $retalarm=['totalCount'=>$totalCount,'alarms'=>$alarms];
        return response()->json($retalarm);

      }elseif($request->has('poiid')){                                    //根据监测点查询，需要参数poiid
      
          $alarms = DB::table('pois')
          ->join('devices', 'devices.poi_id', '=', 'pois.id')
          ->join('sensors', 'devices.id', '=', 'sensors.device_id')
          ->join('alarmsSensor', 'sensors.id', '=', 'alarmsSensor.sensor_id')
          ->select(
           'alarmsSensor.*',
           'devices.name as name',
              'devices.id as id',
              'devices.mac as mac',
           'sensors.name as sensor_name',
           'pois.name as poi_name', 
           'pois.location as poi_location',
           'sensors.up1',
           'sensors.down1',
           'sensors.up2',
           'sensors.down2')
          ->where('pois.id',$request->poiid)
          ->orderBy('alarmsSensor.time', 'desc')
          ->skip($request->ps * $request->pn)
          ->take($request->ps)
          ->get();
  
         $totalCount = DB::table('pois')
         ->join('devices', 'devices.poi_id', '=', 'pois.id')
         ->join('sensors', 'devices.id', '=', 'sensors.device_id')
         ->join('alarmsSensor', 'sensors.id', '=', 'alarmsSensor.sensor_id')
         ->select(
          'alarmsSensor.*',
          'devices.name as name', 
          'sensors.name as sensor_name',
          'pois.name as poi_name', 
          'pois.location as poi_location',
          'sensors.up1',
          'sensors.down1',
          'sensors.up2',
          'sensors.down2')
         ->where('pois.id',$request->poiid)
         ->count();

        $retalarm=['totalCount'=>$totalCount,'alarms'=>$alarms];
        return response()->json($retalarm);
        
      }else{                                                        //若未传入查询参数
        if($this->guard()->user()->type == 1){
          $pois=$this->guard()->user()->project->pois;
        }else{
         $pois=$this->guard()->user()->pois;
        }

        $poiIds = [];
        foreach ($pois as $poi) {
          array_push($poiIds, $poi->id);
        }

        $alarms = DB::table('pois')
            ->join('devices', 'devices.poi_id', '=', 'pois.id')
            ->join('sensors', 'devices.id', '=', 'sensors.device_id')
            ->join('alarmsSensor', 'sensors.id', '=', 'alarmsSensor.sensor_id')
            ->select(
             'alarmsSensor.*',
             'devices.name as name',
                'devices.id as id',
                'devices.mac as mac',
             'sensors.name as sensor_name',
             'pois.name as poi_name', 
             'pois.location as poi_location',
             'sensors.up1',
             'sensors.down1',
             'sensors.up2',
             'sensors.down2')
            ->whereIn('poi_id',$poiIds)
            ->orderBy('alarmsSensor.time', 'desc')
            ->skip($request->ps * $request->pn)
            ->take($request->ps)
            ->get();
         
        $totalCount = DB::table('pois')
        ->join('devices', 'devices.poi_id', '=', 'pois.id')
        ->join('sensors', 'devices.id', '=', 'sensors.device_id')
        ->join('alarmsSensor', 'sensors.id', '=', 'alarmsSensor.sensor_id')
        ->select(
         'alarmsSensor.*',
         'devices.name as name', 
         'sensors.name as sensor_name',
         'pois.name as poi_name', 
         'pois.location as poi_location',
         'sensors.up1',
         'sensors.down1',
         'sensors.up2',
         'sensors.down2')
        ->whereIn('poi_id',$poiIds)
         ->count();

        $retalarm=['totalCount'=>$totalCount,'alarms'=>$alarms];
        return response()->json($retalarm);
        
        return response()->json($alarms);
      }
    }

    public function alarmsCamera(Request $request)
    {

      $this->validate($request, [
        'pn' => 'required',  //page_num
        'ps' => 'required',  //page_size
       ]);
       if($request->has('id2')){
        $alarms = DB::table('pois')
        ->join('cameras', 'cameras.poi_id', '=', 'pois.id')
        ->join('alarmsCamera', 'cameras.id', '=', 'alarmsCamera.camera_id')
        ->select('alarmsCamera.*', 
        'cameras.name as name',
        'pois.name as poi_name', 
        'pois.location as poi_location')
        ->where('cameras.id2',$request->id2)
        ->orderBy('alarmsCamera.time', 'desc')
        ->skip($request->ps * $request->pn)
        ->take($request->ps)
        ->get();

        $totalCount = DB::table('pois')
        ->join('cameras', 'cameras.poi_id', '=', 'pois.id')
        ->join('alarmsCamera', 'cameras.id', '=', 'alarmsCamera.camera_id')
        ->select('alarmsCamera.*', 
        'cameras.name as name',
        'pois.name as poi_name', 
        'pois.location as poi_location')
        ->where('cameras.id2',$request->id2)
        ->count();

      $retalarm=['totalCount'=>$totalCount,'alarms'=>$alarms];

      return response()->json($retalarm);
      }elseif($request->has('id')){
        $alarms = DB::table('pois')
        ->join('cameras', 'cameras.poi_id', '=', 'pois.id')
        ->join('alarmsCamera', 'cameras.id', '=', 'alarmsCamera.camera_id')
        ->select('alarmsCamera.*', 
        'cameras.name as name',
        'pois.name as poi_name', 
        'pois.location as poi_location')
        ->where('alarmsCamera.camera_id',$request->id)
        ->orderBy('alarmsCamera.time', 'desc')
        ->skip($request->ps * $request->pn)
        ->take($request->ps)
        ->get();

        $totalCount = DB::table('pois')
        ->join('cameras', 'cameras.poi_id', '=', 'pois.id')
        ->join('alarmsCamera', 'cameras.id', '=', 'alarmsCamera.camera_id')
        ->select('alarmsCamera.*', 
        'cameras.name as name',
        'pois.name as poi_name', 
        'pois.location as poi_location')
        ->where('alarmsCamera.camera_id',$request->id)
        ->count();

      $retalarm=['totalCount'=>$totalCount,'alarms'=>$alarms];

      return response()->json($retalarm);
      }elseif($request->has('lvl')){
        $alarms = DB::table('pois')
        ->join('cameras', 'cameras.poi_id', '=', 'pois.id')
        ->join('alarmsCamera', 'cameras.id', '=', 'alarmsCamera.camera_id')
        ->select('alarmsCamera.*', 
        'cameras.name as name',
        'pois.name as poi_name', 
        'pois.location as poi_location')
        ->where('alarmsCamera.type',$request->lvl)
        ->orderBy('alarmsCamera.time', 'desc')
        ->skip($request->ps * $request->pn)
        ->take($request->ps)
        ->get();

        $totalCount = DB::table('pois')
        ->join('cameras', 'cameras.poi_id', '=', 'pois.id')
        ->join('alarmsCamera', 'cameras.id', '=', 'alarmsCamera.camera_id')
        ->select('alarmsCamera.*', 
        'cameras.name as name',
        'pois.name as poi_name', 
        'pois.location as poi_location')
        ->where('alarmsCamera.type',$request->lvl)
        ->count(); 

        $retalarm=['totalCount'=>$totalCount,'alarms'=>$alarms];

        return response()->json($retalarm);

      }elseif($request->has('starttime') || $request->has('endtime')){
        if($request->has('starttime') && $request->has('endtime')){             //若只输入了开始时间或结束时间，自动将开始时间补全为1970年1月1日（UTC/GMT的午夜）
          $start = date_create($request->starttime);                            //或将结束时间补全为当前时间
          $end = date_create($request->endtime);
        }elseif($request->has('endtime')){
          $start = date_create('1970-01-01 00:00:00');
          $end = date_create($request->endtime);
        }else{
          $start = date_create($request->starttime);
          $end = date_create();
        }
      
        if($start > $end)                                                       //若输入的开始时间大于结束时间
          return 'wrong time';

        $alarms = DB::table('pois')
        ->join('cameras', 'cameras.poi_id', '=', 'pois.id')
        ->join('alarmsCamera', 'cameras.id', '=', 'alarmsCamera.camera_id')
        ->select('alarmsCamera.*', 
        'cameras.name as name',
        'pois.name as poi_name', 
        'pois.location as poi_location')
        ->where([
        ['alarmsCamera.time','>',$start],
        ['alarmsCamera.time','<',$end],
        ])
        ->orderBy('alarmsCamera.time', 'desc')
        ->skip($request->ps * $request->pn)
        ->take($request->ps)
        ->get();

        $totalCount = DB::table('pois')
        ->join('cameras', 'cameras.poi_id', '=', 'pois.id')
        ->join('alarmsCamera', 'cameras.id', '=', 'alarmsCamera.camera_id')
        ->select('alarmsCamera.*', 
        'cameras.name as name',
        'pois.name as poi_name', 
        'pois.location as poi_location')
        ->where([
        ['alarmsCamera.time','>',$start],
        ['alarmsCamera.time','<',$end],
        ])
        ->count();

        $retalarm=['totalCount'=>$totalCount,'alarms'=>$alarms];
        return response()->json($retalarm);

      }else{  
          if($this->guard()->user()->type == 1){
            $pois=$this->guard()->user()->project->pois;
          }else{
            $pois=$this->guard()->user()->pois;
          }

          $poiIds = [];
          foreach ($pois as $poi) {
            array_push($poiIds, $poi->id);
          }

          $alarms = DB::table('pois')
             ->join('cameras', 'cameras.poi_id', '=', 'pois.id')
             ->join('alarmsCamera', 'cameras.id', '=', 'alarmsCamera.camera_id')
             ->select('alarmsCamera.*', 'cameras.name as name','pois.name as poi_name', 'pois.location as poi_location')
             ->whereIn('poi_id',$poiIds)
             ->orderBy('alarmsCamera.time', 'desc')
             ->skip($request->ps * $request->pn)
             ->take($request->ps)
             ->get();

          $totalCount = DB::table('pois')
          ->join('cameras', 'cameras.poi_id', '=', 'pois.id')
          ->join('alarmsCamera', 'cameras.id', '=', 'alarmsCamera.camera_id')
          ->select('alarmsCamera.*', 'cameras.name as name','pois.name as poi_name', 'pois.location as poi_location')
          ->whereIn('poi_id',$poiIds)
          ->orderBy('alarmsCamera.time', 'desc')
          ->count();

        $retalarm=['totalCount'=>$totalCount,'alarms'=>$alarms];
        return response()->json($retalarm);

          return response()->json($alarms);
        }
    }
/*
    public function listAlarms(Request $request)
    {
      if($request->has('id'))
      {
        $alarms = Alarm::where('sensor_id',$request->id)->get();
      }else{
      if($this->guard()->user()->type == 1){
        //$alarms = Alarm::with('sensor')->where('state',0)->get();
        $pois=$this->guard()->user()->project->pois;
      }else{
        $pois=$this->guard()->user()->pois;
      }
      $alarms = [];
      $alarms1 = [];
      $alarms2 = [];

      foreach ($pois as $poi) {
          $devices = $poi->devices;
          foreach ($devices as $device) {
              if($request->has('all')){
                  $alarms_tmp = $device->alarms()->orderBy('time', 'desc')->get();
                } else {
                  $alarms_tmp = $device->alarms()->where('state',0)->orderBy('time', 'desc')->get();
                }
                foreach ($alarms_tmp as $alarm) {
                  $alarm['sensor'] = $device;
                  $alarm['poi'] = $alarm->device->poi;
                  if($alarm['state']==0){
                    array_push($alarms1, $alarm);
                  } else {
                    array_push($alarms2, $alarm);
                  }
                }
              
              
              
              
            $sensors = $device->sensors;
              foreach ($sensors as $sensor) {
                if($request->has('all')){
                  $alarms_tmp = $sensor->alarms()->orderBy('time', 'desc')->get();
                } else {
                  $alarms_tmp = $sensor->alarms()->where('state',0)->orderBy('time', 'desc')->get();
                }
                foreach ($alarms_tmp as $alarm) {
                  $alarm['sensor'] = $sensor;
                  $alarm['poi'] = $alarm->sensor->device->poi;
                  if($alarm['state']==0){
                    array_push($alarms1, $alarm);
                  } else {
                    array_push($alarms2, $alarm);
                  }
                }
              }
          }

          $cameras = $poi->cameras;
          foreach ($cameras as $camera) {
            if($request->has('all')){
                  $alarms_tmp2 = $camera->alarms()->orderBy('time', 'desc')->get();
                } else {
                  $alarms_tmp2 = $camera->alarms()->where('state',0)->orderBy('time', 'desc')->get();
                }
                foreach ($alarms_tmp2 as $alarm) {
                  $alarm['sensor'] = $camera;
                  $alarm['poi'] = $alarm->camera->poi;
                  if($alarm['state']==0){
                    array_push($alarms1, $alarm);
                  } else {
                    array_push($alarms2, $alarm);
                  }
                }
          }
      }
      $alarms = array_merge($alarms1,$alarms2);
    }
      //$alarms = Alarm::with('sensor')->find($alarmsIds);


      return response()->json($alarms);
    }
*/
    public function alarm(Request $request,$id)
    {
      return Alarm::findOrFail($id);
    }


    //registrations
    //
    public function addReg(Request $request)
    {
        $this->validate($request, [
            'user_id' => 'required',
            'device_id' => 'required',
        ]);

        $reg = new Registration;
        $reg->user_id = $request->user_id;
        $reg->device_id = $request->device_id;

        $reg->save();
        addUserLog('addReg',$this->guard()->user()->id,1);
        return response()->json(['message' => 'add_ok']);
    }

    public function listRegs(Request $request)
    {
      if($request->has('id'))
      {
        $regs = Registration::with('user')->where('device_id',$request->id)->get();
      }
      else{
        $regs = Registration::with('device')->get();
      }

      return response()->json($regs);
    }

    //Maintenances
    //
    public function listMaintenances(Request $request)
    {
      if($request->has('id'))
      {
        $maintenances = Maintenance::with('user')->where('device_id',$request->id)->get();
      }
      else{
        $maintenances = Maintenance::with('device')->get();
      }

      return response()->json($maintenances);
    }


    public function addMaintenance(Request $request,$id)
    {
        $this->validate($request, [
            'content' => 'required',
        ]);

        $reg = new Maintenance;
        $reg->content = $request->content;
        $reg->user_id = $this->guard()->user()->id;
        $reg->device_id = $id;

        $reg->save();
        addUserLog('addMaintenance',$this->guard()->user()->id,1);
        return response()->json(Maintenance::with('user')->find($reg->id));
    }

    public function handleDeviceAlarm(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|exists:alarmsDevice',
            'conclusion' => 'required',
        ]);

        $alarm = AlarmsDevice::find($request->id);
        $alarm->conclusion = $request->conclusion;
        $alarm->handleUser = $this->guard()->user()->name;
        $alarm->handleTime = date('Y-m-d H:m:s');
        $alarm->state = 1;
        $alarm->save();
        addUserLog('handleDeviceAlarm',$this->guard()->user()->id,3);
        return response()->json($alarm);
    }
    public function handleSensorAlarm(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|exists:alarmsSensor',
            'conclusion' => 'required',
        ]);

        $alarm = AlarmsSensor::find($request->id);
        $alarm->conclusion = $request->conclusion;
        $alarm->handleUser = $this->guard()->user()->name;
        $alarm->handleTime = date('Y-m-d H:m:s');
        $alarm->state = 1;
        $alarm->save();
        addUserLog('handleSensorAlarm',$this->guard()->user()->id,3);
        return response()->json($alarm);
    }
    public function handleCameraAlarm(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|exists:alarmsCamera',
            'conclusion' => 'required',
        ]);

        $alarm = AlarmsCamera::find($request->id);
        $alarm->conclusion = $request->conclusion;
        $alarm->handleUser = $this->guard()->user()->name;
        $alarm->handleTime = date('Y-m-d H:m:s');
        $alarm->state = 1;
        $alarm->save();
        addUserLog('handleCameraAlarm',$this->guard()->user()->id,3);
        return response()->json($alarm);
    }

  }
