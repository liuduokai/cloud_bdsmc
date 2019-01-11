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
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
          'addReg',
          'deviceAlarms',
          'sensorAlarms',
        ]]);

        DB::connection()->enableQueryLog();
    }
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

      $sql_where = array();
      if($request->has('mac')){
          $sql_part_mac = ['devices.mac','=',$request->mac];
          array_push($sql_where,$sql_part_mac);
      }

      if($request->has('type')){
          $sql_part_lvl = ['alarmsDevice.type','=',$request->type];
          array_push($sql_where,$sql_part_lvl);
      }

      if($request->has('id')){
          $sql_part_id = ['devices.id','=',$request->id];
          array_push($sql_where,$sql_part_id);
      }

      if($request->has('starttime') ||$request->has('endtime') ){

          if($request->has('starttime') && $request->has('endtime')){
              $start = date_create($request->starttime);
              $end = date_create($request->endtime);
          }elseif($request->has('endtime')){
              $start = date_create('1970-01-01 00:00:00');
              $end = date_create($request->endtime);
          }else{
              $start = date_create($request->starttime);
              $end = date_create();
          }


          $sql_part_time_s = ['alarmsDevice.time','>',$start];
          $sql_part_time_e =['alarmsDevice.time','<',$end];

          array_push($sql_where,$sql_part_time_s);
          array_push($sql_where,$sql_part_time_e);

      }
        $user_type = $this->guard()->user()->type;
        $project_id = $this->guard()->user()->project->id;
        if($user_type == 1){

        }else{
            $sql_part_project = ['pois.project_id','=',$project_id];
            array_push($sql_where,$sql_part_project);
        }

        $result = DB::table('alarmsDevice')
            ->join('devices', 'devices.id', '=', 'alarmsDevice.device_id')
            ->join('pois','pois.id','=','devices.poi_id')
            ->select(
                'alarmsDevice.*',
                'devices.id as id',
                'devices.mac as mac',
                'devices.name as name',
                'pois.name as poi_name',
                'pois.location as poi_location'
            )
            ->where($sql_where)
            ->orderBy('alarmsDevice.time', 'desc')
            ->skip($request->ps * $request->pn)
            ->take($request->ps)
            ->get();
        $count =  DB::table('alarmsDevice')
            ->join('devices', 'devices.id', '=', 'alarmsDevice.device_id')
            ->join('pois','pois.id','=','devices.poi_id')
            ->select(
                'alarmsDevice.*',
                'devices.id as id',
                'devices.mac as mac',
                'devices.name as name',
                'pois.name as poi_name',
                'pois.location as poi_location'
            )
            ->where($sql_where)
            ->count();
        return response()->json(['count'=>$count,'result'=>$result]);

    }

    public function alarmsSensor(Request $request)  
    {
        $this->validate($request, [
            'pn' => 'required',  //page_num
            'ps' => 'required',  //page_size
        ]);


        $sql_where = array();

        if ($request->has('mac')) {
            $sql_part_mac = ['devices.mac', '=', $request->mac];
            array_push($sql_where, $sql_part_mac);
        }

        if ($request->has('type')) {
            $sql_part_lvl = ['alarmsSensor.type', '=', $request->type];
            array_push($sql_where, $sql_part_lvl);
        }

        if($request->has('id')){
            $sql_part_id = ['devices.id','=',$request->id];
            array_push($sql_where,$sql_part_id);
        }

        if ($request->has('starttime') || $request->has('endtime')) {

            if ($request->has('starttime') && $request->has('endtime')) {
                $start = date_create($request->starttime);
                $end = date_create($request->endtime);
            } elseif ($request->has('endtime')) {
                $start = date_create('1970-01-01 00:00:00');
                $end = date_create($request->endtime);
            } else {
                $start = date_create($request->starttime);
                $end = date_create();
            }


            $sql_part_time_s = ['alarmsSensor.time', '>', $start];
            $sql_part_time_e = ['alarmsSensor.time', '<', $end];

            array_push($sql_where, $sql_part_time_s);
            array_push($sql_where, $sql_part_time_e);

        }
        $user_type = $this->guard()->user()->type;
        $project_id = $this->guard()->user()->project->id;
        if ($user_type == 1) {

        } else {
            $sql_part_project = ['pois.project_id', '=', $project_id];
            array_push($sql_where, $sql_part_project);
        }

        $result = DB::table('alarmsSensor')
            ->join('sensors', 'sensors.id', '=', 'alarmsSensor.sensor_id')
            ->join('devices', 'devices.id', '=', 'sensors.device_id')
            ->join('pois', 'pois.id', '=', 'devices.poi_id')
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
                'sensors.down2'
            )
            ->where($sql_where)
            ->orderBy('alarmsSensor.time', 'desc')
            ->skip($request->ps * $request->pn)
            ->take($request->ps)
            ->get();
        $count = DB::table('alarmsSensor')
            ->join('sensors', 'sensors.id', '=', 'alarmsSensor.sensor_id')
            ->join('devices', 'devices.id', '=', 'sensors.device_id')
            ->join('pois', 'pois.id', '=', 'devices.poi_id')
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
                'sensors.down2'
            )
            ->where($sql_where)
            ->count();
        return response()->json(['count' => $count, 'result' => $result]);



    }

    public function alarmsCamera(Request $request)
    {
        $this->validate($request, [
            'pn' => 'required',  //page_num
            'ps' => 'required',  //page_size
        ]);


        $sql_where = array();

        if ($request->has('mac')) {
            $sql_part_mac = ['cameras.uid', '=', $request->mac];
            array_push($sql_where, $sql_part_mac);
        }

        if ($request->has('type')) {
            $sql_part_lvl = ['alarmsCamera.type', '=', $request->type];
            array_push($sql_where, $sql_part_lvl);
        }

        if ($request->has('starttime') || $request->has('endtime')) {

            if ($request->has('starttime') && $request->has('endtime')) {
                $start = date_create($request->starttime);
                $end = date_create($request->endtime);
            } elseif ($request->has('endtime')) {
                $start = date_create('1970-01-01 00:00:00');
                $end = date_create($request->endtime);
            } else {
                $start = date_create($request->starttime);
                $end = date_create();
            }


            $sql_part_time_s = ['alarmsCamera.time', '>', $start];
            $sql_part_time_e = ['alarmsCamera.time', '<', $end];

            array_push($sql_where, $sql_part_time_s);
            array_push($sql_where, $sql_part_time_e);

        }
        $user_type = $this->guard()->user()->type;
        $project_id = $this->guard()->user()->project->id;
        if ($user_type == 1) {

        } else {
            $sql_part_project = ['pois.project_id', '=', $project_id];
            array_push($sql_where, $sql_part_project);
        }
        $result = DB::table('alarmsCamera')
            ->join('cameras', 'cameras.id', '=', 'alarmsCamera.camera_id')
            ->join('pois', 'pois.id', '=', 'cameras.poi_id')
            ->select(
                'alarmsCamera.*',
                'cameras.name as name',
                'cameras.id as id',
                'cameras.uid as uid',
                'pois.name as poi_name',
                'pois.location as poi_location'
            )
            ->where($sql_where)
            ->orderBy('alarmsCamera.time', 'desc')
            ->skip($request->ps * $request->pn)
            ->take($request->ps)
            ->get();
        $count = DB::table('alarmsCamera')
            ->join('cameras', 'cameras.id', '=', 'alarmsCamera.camera_id')
            ->join('pois', 'pois.id', '=', 'cameras.poi_id')
            ->select(
                'alarmsCamera.*',
                'cameras.name as name',
                'cameras.id as id',
                'cameras.uid as uid',
                'pois.name as poi_name',
                'pois.location as poi_location'
            )
            ->where($sql_where)
            ->orderBy('alarmsCamera.time', 'desc')
            ->count();
        return response()->json(['count' => $count, 'result' => $result]);


    }

    public function alarm(Request $request,$id)
    {
      return Alarm::findOrFail($id);
    }

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
