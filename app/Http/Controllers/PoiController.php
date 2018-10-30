<?php

namespace App\Http\Controllers;

use http\Env\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use phpDocumentor\Reflection\Types\Null_;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use Illuminate\Support\Facades\Cache;
use App\Events\SmsEvent;
use App\Camera;
use App\Poi;
use App\Device;
use App\Sensor;
use App\Alarm;
use App\Displacementsensor1;
use App\Photo;
use App\PhotoPostion;
use App\Insar;
use App\InsarData;
use App\Device_test;
use \Bluerhinos\phpMQTT;
use Overtrue\Pinyin\Pinyin;
use App\PoiInfo;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;


include_once 'addUserLog.php';

class PoiController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api',
            ['except' => [
                'listPois2',
                'addPoi2',
                'delPoi2',
                'updatePoi2',
                'listDevices2',
                'addDevice2',
                'delDevice2',
                'updateDevice2',
                'listSensors2',
                'addSensor2',
                'delSensor2',
                'updateSensor2',
                'pick',
                'gnss',
                'history',
                'getDevicedata',
                'addImage',
                'delImage',
                'addPos',
                'delPos',
                'insar',
                'insarData',
                'genImage',
                'listPoses',
                'deviceData',
                'test',
                'listPois',
                'deviceData2',
                'acceptNBData',
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

    public function gnss(Request $request)
    {
        $this->validate($request, [
            'type' => 'required|numeric',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'height' => 'required|numeric',
        ]);
        if ($request->type == 1) {
            $this->validate($request, [
                'id' => 'required|numeric|exists:devices,id2',
            ]);
            $device = Device::where('id2', $request->id)->first();
            $device->lat = $request->lat;
            $device->lng = $request->lng;
            $device->altitude = $request->height;
            $device->save();
            addUserLog('gnss_device', $this->guard()->user()->id, 3);
        } else if ($request->type == 2) {
            $this->validate($request, [
                'id' => 'required|numeric|exists:pois,id2',
            ]);
            $poi = Poi::where('id2', $request->id)->first();
            $poi->lat = $request->lat;
            $poi->lng = $request->lng;
            $poi->altitude = $request->height;
            $poi->save();
            addUserLog('gnss_poi', $this->guard()->user()->id, 3);
        }

        return response()->json(['message' => 'update_ok']);
    }

    public function pick(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|numeric|exists:sensors,id2',
            'time' => 'required|date_format:"Y-m-d H:i:s"',
            'value' => 'required|numeric',
        ]);
        $sensor = Sensor::where('id2', $request->id)->first();
        DB::table('displacementsensor1')->insert(
            [
                'device_id' => $sensor->id,
                'displacement' => $request->value,
                'gps_time' => $request->time,
            ]
        );


        $pwdObj = (object)null;
        $pwdObj->type = 2;
        $pwdObj->id = $sensor->id;
        $v = floatval($request->value);
        $a = FALSE;

        if ($v >= $sensor->up1) {
            $pwdObj->value = $request->value;
            $a = TRUE;
            $alarmContent = "超过一级预警上限";
            $alarmType = 1;
        } else if ($v >= $sensor->up2) {
            $pwdObj->value = $request->value;
            $a = TRUE;
            $alarmContent = "超过二级预警上限";
            $alarmType = 2;
        } else if ($v <= $sensor->down1) {
            $pwdObj->value = $request->value;
            $a = TRUE;
            $alarmContent = "超过一级预警下限";
            $alarmType = 1;
        } else if ($v <= $sensor->down2) {
            $pwdObj->value = $request->value;
            $a = TRUE;
            $alarmContent = "超过二级预警下限";
            $alarmType = 2;
        }

        if ($a) {
            $alarmContent = $sensor->name . $alarmContent;
            $alarm = new Alarm;
            $alarm->content = $alarmContent;
            $alarm->type = $alarmType;
            $alarm->sensor_id = $sensor->id;
            $alarm->save();
            addUserLog('pick', $this->guard()->user()->id, 1);
            $pwdObj->content = $alarm->content;
            event(new SmsEvent($pwdObj));

            $server =config('auth.poiPickHost');     // change if necessary
            $port = config('auth.poiPickPort');                     // change if necessary
            $username = config('auth.poiPickUsername');                   // set your username
            $password = config('auth.poiPickPassword');                   // set your password
            $client_id =config('auth.poiPickClient_id'); // make sure this is unique for connecting to sever - you could use uniqid()
            $mqtt = new phpMQTT($server, $port, $client_id);
            if ($mqtt->connect(true, NULL, $username, $password)) {
                $mqtt->publish("u/" . $alarm->sensor->device->poi->user->id, "123 " . date("r"), 0);
                $mqtt->close();
            } else {
                echo "Time out!\n";
            }
        }

        return response()->json(['message' => 'add_ok']);
    }


    public function listPoses(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|numeric',
        ]);

        $photo = Photo::findOrFail($request->id);
        $photopostions = $photo->photopostions;

        foreach ($photopostions as $photopostion) {
            $photopostion["device"] = $photopostion->device;
        }

        $photo["devices"] = $photopostions;

        return response()->json($photo);
    }

    public function listPois(Request $request)
    {
        $pinyin = new Pinyin();

        if ($this->guard()->user()->type == 1)
            $pois =  Poi::all();
        else {
            $pois = Poi::where('project_id', $this->guard()->user()->project_id)->get();
        }

        foreach ($pois as $poi) {
                       $poi["pinyin"] = $pinyin->convert($poi->name)[0][0];


            $photos = $poi->photos;


            foreach ($photos as $photo) {
                $photo["devices"] = $photo->photopostions;
            }
            foreach ($photos as $v){
                if($v->path != null){
                    $test[] = ($v->path != null);
                    $noNull[] = $v;
                }
            }
            if(isset($noNull)) {
                unset($poi["photos"]);
                $poi["photos"] = $noNull;
            }
            $poi["devices2"] = $poi->devices;
            //return response()->json([$poi]);
        }

        return response()->json($pois);

    }

    public function poi(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|numeric',
        ]);
        $poi = Poi::findOrFail($request->id);
        if ($this->guard()->user()->type != 1) {
            if ($poi->user_id != $this->guard()->user()->id)
                return response()->json(['error' => '未经授权的操作'], 401);
        }

        return $poi;
    }


    public function device(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|numeric',
        ]);
        $device = Device::findOrFail($request->id);
        if ($this->guard()->user()->type != 1) {
            if ($device->poi()->user_id != $this->guard()->user()->id)
                return response()->json(['error' => '未经授权的操作'], 401);
        }

        $device["poi"] = $device->poi;

        return $device;
    }

    public function assignPoi(Request $request)
    {
        if ($this->guard()->user()->type != 1)
            return response()->json(['error' => '未经授权的操作'], 401);

        $this->validate($request, [
            //'id' => 'required|numeric',
            'poiId' => 'required|numeric',
        ]);

        $poi = Poi::findOrFail($request->poiId);
        $poi->user_id = $request->id;
        $poi->save();
        addUserLog('assignPoi', $this->guard()->user()->id, 3);
        return response()->json(['message' => 'assign_ok']);
    }

    public function searchPoi(Request $request, $q)
    {
        $pinyin = new Pinyin();
        $project_id = $this->guard()->user()->project_id;
        $sId = $q;

        $devices = Device::search($sId)
            ->where('devices.deleted_at', '=', NULL)
            ->get();
        foreach ($devices as $device) {
            if ($device->poi != null)
                $poiss[$device->poi->id] = $device->poi;
        }
        $count = 1;
        if(isset($poiss)) {
            foreach ($poiss as $poi) {
                //return response()->json(['type'=>gettype($poi),'project_id'=>$poi->project_id,'poi_name'=>$poi->name]);
                if ($poi->project_id === $project_id || $this->guard()->user()->type === 1) { //权限控制，测试版本暂时不加入相应功能
                    $poi_name = $poi->name;

                    $poi["pinyin"] = $pinyin->convert($poi_name)[0][0];
                    $photos = $poi->photos;
                    foreach ($photos as $photo) {
                        $photo["devices"] = $photo->photopostions;
                    }
                    $poi["photos"] = $photos;
                    $poi["devices2"] = $poi->devices;
                    $pois_id[] = $poi;
                    //$count++;
                }
            }
        }

        //根据监测点名搜索
        if ($this->guard()->user()->type == 1)
            //$pois = Poi::where('project_id', $this->guard()->user()->project_id)->get();

            $pois_name = Poi::search(urldecode($q))
                //->where
                ->get();
        else {
            $pois_name = Poi::search(urldecode($q))
                ->where('project_id',$project_id)
                ->get();
        }


        foreach ($pois_name as $poi) {
            $poi["pinyin"] = $pinyin->convert($poi->name)[0][0];


            $photos = $poi->photos;
            foreach ($photos as $photo) {
                $photo["devices"] = $photo->photopostions;
            }

            $poi["photos"] = $photos;
            $poi["devices2"] = $poi->devices;

            //$poi["photos"] = Photo::where('poi_id',$poi->id)->get();
            //$poi["photos"] = \App\Photo::all();
            //$queries    = DB::getQueryLog();
            //$last_query = end($queries);
            //dd($last_query);
        }

        if (isset($pois_id))
            $pois = $pois_id;
        else
            $pois = $pois_name;
        return response()->json($pois);
    }

    public function listDevices(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|numeric',
        ]);

        if ($this->guard()->user()->type != 1) {
            $poi = Poi::findOrFail($request->id);
            if ($poi->project_id != $this->guard()->user()->project_id)
                return response()->json(['error' => '未经授权的操作'], 401);
        }

        $devices = Device::where([['poi_id', $request->id], ['type', '<>', 6]])->get();
        //$devices  =Device::all();
        //$count = Device::where([['poi_id', $request->id],['type','<>',6]])->count();
        //$returns =['devices' =>$devices];
        return response()->json($devices);
    }

    public function listDevices3(Request $request)
    {
        $this->validate($request, [
            'pn' => 'required',  //page_num
            'ps' => 'required'
        ]);
        if ($this->guard()->user()->type == 1)
            $pois = Poi::where('project_id', $this->guard()->user()->project_id)->get();
        else {
            $pois = $this->guard()->user()->pois;
        }
        $poiIds = [];
        foreach ($pois as $poi) {
            array_push($poiIds, $poi->id);
        }
        $pn = $request->pn - 1;
        $result = DB::table('pois')
            ->join('devices', 'devices.poi_id', '=', 'pois.id')
            ->select("devices.*", 'pois.name as pname', 'pois.location as poi_location')
            ->whereIn('pois.id', $poiIds)
            ->skip($request->ps * $pn)
            ->take($request->ps)
            ->get();
        $count = DB::table('pois')
            ->join('devices', 'devices.poi_id', '=', 'pois.id')
            ->whereIn('pois.id', $poiIds)
            ->count();
        $retalarm = ['count' => $count, 'result' => $result];
        return response()->json($retalarm);
    }

    public function listSensors(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|numeric',
        ]);
        $sensors = DB::table('sensors')
            ->select(DB::raw("sensors.*,
                (
	            case name
                when name = '偏东' then 1
		        when name = '偏北' then 2
		        when name = '高程变化' then 3
		        when name = '经度' then 4
		        when name = '纬度' then 5
		        when name = '海拔' then 6
		        when name = '小时雨量' then 7
		        when name = '当天雨量' then 8
		        when name = '裂缝' then 9
		        when name = '裂缝值' then 10
		        when name = '湿度' then 11
		        when name = '温度' then 12
		        when name = '30cm含水量' then 13
		        when name = '30cm温度' then 14
		        when name = '60cm含水量' then 15
		        when name = '60cm温度' then 16
		        when name = '90cm含水量' then 17
		        when name = '90cm温度' then 18
		        when name = '方位角x' then 19
		        when name = '方位角' then 20
		        when name = '俯仰角y' then 21
		        when name = '横滚角z' then 22
		        ELSE 23
	            END
                ) seq"))
            ->where([['device_id', $request->id],
                    ['sensors.deleted_at', '=', NULL],]
                   )
            ->orderBy('seq', 'asc')
            ->get();
        return response()->json($sensors);
    }


    function isDatetime($param = '', $format = 'Y-m-d H:i:s')
    {
        //2017-06-7  =>  not ok
        //2017-06-07  =>  ok
        return date($format, strtotime($param)) === $param;
    }

    public function history(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|numeric|exists:sensors',
            'start' => 'required|date_format:"Y-m-d H:i:s"',
            'end' => 'required|date_format:"Y-m-d H:i:s"',
        ]);

        if (strtotime($request->end) < strtotime($request->start)) {
            return response()->json(["error" => '结束时间应晚于开始时间']);
        }

        $sensor = Sensor::find($request->id);
        $device = $sensor->device;
        $poi = $device->poi;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1',
            $poi->name . '-' . $device->name . '-' . $sensor->name);
        $sheet->setCellValue('B1', '时间');

        $res = [];
        $row = 2;
        DB::table('displacementsensor1')
            ->where([
                ['device_id', '=', $request->id],
                ['gps_time', '>=', $request->start],
                ['gps_time', '<=', $request->end],
            ])
            ->orderBy('gps_time', 'desc')
            ->chunk(100, function ($records) use (&$res, &$row, &$sheet) {
                foreach ($records as $record) {

                    array_push($res, $record);
                    $sheet->setCellValue('A' . $row,
                        $record->displacement);
                    $sheet->setCellValue('B' . $row, $record->gps_time);
                    $row = $row + 1;
                }
            });


        $filename = $poi->name . '-' . $device->name . '-' . $sensor->name . '-' . $request->start . '-' . $request->end . '.xlsx';

        $writer = new Xlsx($spreadsheet);
        $writer->save('file/' . $filename);

        return response()->download('file/' . $filename, $filename, ['Access-Control-Allow-Origin' => '*', 'Access-Control-Expose-Headers' => 'Content-Disposition'])->deleteFileAfterSend(true);;

        return response()->json($res);
    }

    public function GaodeCoord(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|numeric|exists:insar',
        ]);

        $insars = Insar::find($request->id);

        $insars->lng_g = $request->lng_g;
        $insars->lat_g = $request->lat_g;

        $insars->save();
        addUserLog('GaodeCoord', $this->guard()->user()->id, 3);
        $err['msg'] = 'ok';
        return response()->json($err);
    }

    public function insar(Request $request)
    {
        //$projectId = $this->guard()->user()->project_id;

        //if($projectId)
        {
            //$insars = Insar::all();
            //return response()->json($insars);
        }
        if ($this->guard()->user()->type == 3) {
            $colors = DB::table('insar')->select('color')->where('mean_velocity','<',-20)->distinct()->get();

            foreach ($colors as $color) {
                $insars = DB::table('insar')->select('id', 'latitude', 'longitude', 'lng_g', 'lat_g', 'mean_velocity')->where([['color', $color->color],['mean_velocity','<',-20]])->get();
                $color->insars = $insars;

            }


            return response()->json($colors);
        }else{
            $colors = DB::table('insar')->select('color')->distinct()->get();

            foreach ($colors as $color) {
                $insars = DB::table('insar')->select('id', 'latitude', 'longitude', 'lng_g', 'lat_g')->where('color', $color->color)->get();
                $color->insars = $insars;

            }

            return response()->json($colors);

        }

        $err['error'] = 'error';
        return response()->json($err);
    }

    public function insarData(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|numeric|exists:insar',
        ]);

        $insar = Insar::findOrFail($request->id);
        //$projectId = $this->guard()->user()->project_id;

        //if($projectId)
        {
            $insar->data = InsarData::where("insar_id", $request->id)->get();
            return response()->json($insar);
        }

        $err['error'] = 'error';
        return response()->json($err);
    }

    public function genImage(Request $request)
    {
        $this->validate($request, [
            'color' => 'required',
        ]);

        header("Content-type: image/png");
        $color = $request->color;
        $im = imagecreate(12, 12);
        $orange = imagecolorallocatealpha($im, hexdec(substr($color, 2, 2)), hexdec(substr($color, 4, 2)), hexdec(substr($color, 6, 2)), hexdec(substr($color, 0, 2)));//
        imagepng($im);
        imagedestroy($im);
    }

    public function getDevicedata(Request $request)
    {

        $t = microtime();

        $root['request'] = $request->all();

        $w = array();    //sql查询字符串
        $res = [];

        $type = $request->input('type', '');
        if ($type == '1') {
            $device_id = intval($request->input('device_id', 0));
            if ($device_id == 0) {
                $err['error'] = 'device id error';
                return response()->json($err);
            }

            //$sql = DB::table('displacementsensor1');
            //->where('device_id', $device_id);

            $w_device_id = ['device_id', '=', $device_id];
            array_push($w, $w_device_id);

            //Check time
            $start = $request->input('start', '');
            $end = $request->input('end', '');

            $start_stamp = strtotime($start);
            $end_stamp = strtotime($end);    //将时间解析为unix时间戳

            if ($start_stamp && $end_stamp && $end_stamp < $start_stamp) {
                $err['error'] = 'time error';
                return response()->json($err);
            }                               //判断时间正确性

            if ($this->isDatetime($start)) {
                $w_start = ['gps_time', '>=', $start];
                array_push($w, $w_start);
            } else if ($start != '') {
                $err['error'] = 'start time error';
                return response()->json($err);
            }

            if ($this->isDatetime($end)) {
                $w_end = ['gps_time', '<=', $end];
                array_push($w, $w_end);
            } else if ($end != '') {
                $err['error'] = 'end time error';
                return response()->json($err);
            }                           //设置开始结束时间限制
            //验证开始结束时间的格式是否正确
            //$sql = DB::table('displacementsensor1')->where($w)->orderBy('gps_time', 'desc');
            //$sql = $sql->orderBy('device_id', 'desc');
            //dd($q->getBindings());
            if (count($w) == 1 || count($w) == 2) {
                if (intval($request->input('rt', 0)) == 1) {
                    $res = Displacementsensor1::where($w)->orderBy('gps_time', 'desc')->first();
                } else {
                    //$sql = $sql->take(100);
                    //array_push($w,);
                    //$res = Displacementsensor1::where($w)->whereRaw(DB::raw('TIMESTAMPDIFF(MINUTE, gps_time, NOW()) < 60'))->orderBy('gps_time', 'desc')->take(50)->get();
                    $res = Displacementsensor1::where($w)->orderBy('gps_time', 'desc')->groupby('displacementsensor1.device_id','displacementsensor1.gps_time')->take(30)->get();
                }
                $t1 = microtime();
                list($m0, $s0) = explode(" ", $t);
                list($m1, $s1) = explode(" ", $t1);




                $temp_data =999999;
                $l_time = 0;
                $flag =1;
                foreach ($res as $res_decimatio){
                    if($temp_data == 999999){
                        $temp_data = $res_decimatio->displacement;
                        $l_time  = $res_decimatio->gps_time;
                    }else{
                        $flag = 0;
                        $data_change['displacement'] =round($temp_data - $res_decimatio->displacement,2);
                        $data_change['gps_time'] = $l_time;
                        $data_change['device_id'] = $res_decimatio->device_id;
                        $change[] = $data_change;

                        $temp_data = $res_decimatio->displacement;
                        $l_time = $res_decimatio->gps_time;
                    }
                }
                if($flag) {
                    foreach ($res as $res_decimatio) {
                        $temp_data = $res_decimatio->displacement;
                        $l_time = $res_decimatio->gps_time;
                        $data_change['displacement'] = round($res_decimatio->displacement, 2);
                        $data_change['gps_time'] = $l_time;
                        $data_change['device_id'] = $res_decimatio->device_id;
                        $change[] = $data_change;
                    }
                }
                $root["data"] = $res;
                $root["elapsed time"] = (($s1 + $m1 - $s0 - $m0)) . " s";
                if(isset($change))
                    $root['dataChange'] = $change;

                return response()->json($root);
            } else {
                /*
                        //$sql = $sql->take(10000);

                            $id = $request->input('last_id',0);
                  //dd($w);
                            //$total = $sql->count();
                  $total = Displacementsensor1::where($w)->orderBy('gps_time', 'desc')->count();
                  //dd($total);
                  //$queries    = DB::getQueryLog();
                  //$last_query = end($queries);
                  //dd($last_query);

                            $ratio = intval($total/1500*2);
                            if($ratio == 0)
                            {
                                $page_size = 100;

                            }
                            else
                            if($ratio<10)
                            {
                                $page_size = $ratio*100;
                            }
                            else if($ratio<50)
                            {
                                $page_size = $ratio*10;
                            }
                            else if($ratio<1000)
                            {
                                $page_size = $ratio;
                            }

                            else
                            {
                                $err['error'] = 'data too large';
                                return response()->json($err);
                            }


                                $st = microtime();

                                $res_decimation = [];

                                while(count($res_decimation)<20)
                                {
                                    //$sql = Displacementsensor1::where($w)
                      //->orderBy('gps_time', 'desc')
                      //->skip($id*$page_size)
                      //->take($page_size);
                                    //dd($sql->toSql());
                                    $res = Displacementsensor1::where($w)
                      ->orderBy('gps_time', 'desc')
                      ->skip($id*$page_size)
                      ->take($page_size)
                      ->get();

                                //$sql->skip($id*$page_size)->chunk($page_size, function ($rows) use(&$st,&$ratio,&$request,&$total,&$page_size,&$id) {

                                    $st1 = microtime();
                                    list($m0,$s0) = explode(" ",$st);
                                    list($m1,$s1) = explode(" ",$st1);

                                    $root["elapsedtime"] = (($s1+$m1-$s0-$m0))." s";

                                    //$res = $rows;

                                    $cnt_tmp = count($res);
                                    if($cnt_tmp)
                                    {

                                        //$ratio = 26;
                                        $min = null;
                                        $max = null;

                                        if($ratio < 3)
                                        {
                                            $res_decimation = $res;
                                        }
                                        else
                                        {
                                            for($x=0; $x<$cnt_tmp; $x++)
                                            {
                                                if($x%$ratio == 0)
                                                {
                                                    if($x!=0)
                                                    {
                                                        array_push($res_decimation,$min);
                                                        array_push($res_decimation,$max);
                                                    }
                                                    $min = $max = $res[$x];
                                                    continue;
                                                }
                                                //var_dump($min);
                                                if($min->displacement>$res[$x]->displacement)
                                                    $min = $res[$x];
                                                else if($max->displacement<$res[$x]->displacement)
                                                    $max = $res[$x];

                                                if($x == $cnt_tmp-1)
                                                {
                                                    array_push($res_decimation,$min);
                                                    array_push($res_decimation,$max);
                                                }
                                            }
                                        }
                                    }
                                    else
                                    {
                                        break;
                                    }
                                    $id++;
                                }
                  */
                $id = $request->input('last_id', 0);
                $total = Displacementsensor1::where($w)->orderBy('gps_time', 'asc')->count();
                $page_size = 100;
                $st = microtime();


                $res_decimation = Displacementsensor1::where($w)
                    ->groupby('displacementsensor1.device_id','displacementsensor1.gps_time')
                    ->orderBy('gps_time', 'asc')
                    ->skip($id * $page_size)
                    ->take($page_size)
                    ->get();


                $temp_data =999999;
                $l_time = 0;
                $flag_1 = 1;
                foreach ($res_decimation as $res_decimatio){
                    if($temp_data == 999999){
                        $temp_data = $res_decimatio->displacement;
                        $l_time  = $res_decimatio->gps_time;
                    }else{
                        $flag_1 = 0;
                        $data_change['displacement'] =round($temp_data - $res_decimatio->displacement,2);
                        $data_change['gps_time'] = $l_time;
                        $data_change['device_id'] = $res_decimatio->device_id;
                        $change[] = $data_change;

                        $temp_data = $res_decimatio->displacement;
                        $l_time = $res_decimatio->gps_time;
                    }
                }
                if($flag_1) {
                    foreach ($res as $res_decimatio) {
                        $temp_data = $res_decimatio->displacement;
                        $l_time = $res_decimatio->gps_time;
                        $data_change['displacement'] = round($res_decimatio->displacement, 2);
                        $data_change['gps_time'] = $l_time;
                        $data_change['device_id'] = $res_decimatio->device_id;
                        $change[] = $data_change;
                    }
                }


                $st1 = microtime();
                list($m0, $s0) = explode(" ", $st);
                list($m1, $s1) = explode(" ", $st1);

                $id++;

                $root["elapsedtime"] = (($s1 + $m1 - $s0 - $m0)) . " s";

                $root['lastid'] = $request->input('last_id', 0);
                $root['id'] = $id;
                $root['total'] = $total;
                $root['data'] = $res_decimation;
                $root['total_d'] = count($res_decimation);
                //$root['ratio'] = $ratio;
                $root['page_size'] = $page_size;
                if(isset($change))
                    $root['dataChange'] = $change;

                return response()->json($root);
            }

        }
        elseif ($type == '2'){
            $all_datas = Displacementsensor1::selectRaw('id,DATE_FORMAT(gps_time,"%Y-%m-%d") as gps_time,device_id,convert(displacement,decimal(10,1)) as displacement')
                    ->where('device_id', '=', $request->device_id)
                    ->whereRaw("DATE_FORMAT(gps_time,'%H') BETWEEN 7 AND 9")
                    ->orderBy('gps_time', 'desc')
                    ->groupby(DB::raw('DATE_FORMAT(gps_time,"%Y-%m-%d")'))
                    ->take(30)
                    ->get();
            $temp_data =999999;
            $l_time = 0;
            $flag_2 = 1;
            foreach ($all_datas as $all_data){
                if($temp_data == 999999){
                    $temp_data = $all_data->displacement;
                    $l_time  = $all_data->gps_time;
                }else{
                    $flag_2 = 0;
                    $data_change['displacement'] =round($temp_data - $all_data->displacement,2);
                    $data_change['gps_time'] = $l_time;
                    $data_change['device_id'] = $all_data->device_id;
                    $change[] = $data_change;

                    $temp_data = $all_data->displacement;
                    $l_time = $all_data->gps_time;
                }
            }
            if($flag_2) {
                foreach ($res as $res_decimatio) {
                    $temp_data = $res_decimatio->displacement;
                    $l_time = $res_decimatio->gps_time;
                    $data_change['displacement'] = round($res_decimatio->displacement, 2);
                    $data_change['gps_time'] = $l_time;
                    $data_change['device_id'] = $res_decimatio->device_id;
                    $change[] = $data_change;
                }
            }

            if(!isset($change))
                $change = [];
            return response()->json(['data'=>$all_datas,'dataChange'=>$change]);
        } else {
            $err['error'] = 'type error';
            return response()->json($err);
        }
    }

    public function UpdateSensor(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
        ]);

        $sensor = Sensor::findOrFail($request->id);
        if ($request->has('up1')) $sensor->up1 = (float)$request->up1;
        if ($request->has('up2')) $sensor->up2 = (float)$request->up2;
        if ($request->has('down1')) $sensor->down1 = (float)$request->down1;
        if ($request->has('down2')) $sensor->down2 = (float)$request->down2;

        $sensor->save();
        addUserLog('UpdateSensor', $this->guard()->user()->id, 3);
        return response()->json(['message' => 'updated_ok']);

    }


    //for bdmc
    public function listPois2(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
        ]);

        $pois = Poi::where('project_id', $request->id)->get();

        foreach ($pois as $poi) {
            $photos = $poi->photos;
            foreach ($photos as $photo) {
                $photo["devices"] = $photo->photopostions;
            }

            $poi["photos"] = $photos;
        }

        return response()->json($pois);
    }

    public function addPoi2(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'location' => 'required',
            'project_id' => 'required',
            'id2' => 'required|numeric|unique:pois,id2,,,deleted_at,NULL',
        ]);

        $poi = new Poi;

        $poi->project_id = $request->project_id;
        $poi->name = $request->name;
        $poi->location = $request->location;
        $poi->id2 = $request->id2;

        if ($request->has('user_id'))
            $poi->user_id = $request->input('user_id');
        if ($request->has('lng'))
            $poi->lng = $request->input('lng');
        if ($request->has('lat'))
            $poi->lat = $request->input('lat');
        if ($request->has('altitude'))
            $poi->altitude = $request->input('altitude');


        $poi->save();
        addUserLog('addPoi2', $this->guard()->user()->id, 1);
        return response()->json(['message' => 'add_ok']);
    }

    public function updatePoi2(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
        ]);

        $poi = Poi::findOrFail(intval($request->id));
        if ($request->has('id2'))
            $poi->id2 = $request->input('id2');
        if ($request->has('name'))
            $poi->name = $request->input('name');
        if ($request->has('user_id'))
            $poi->user_id = $request->input('user_id');
        if ($request->has('location'))
            $poi->location = $request->input('location');
        if ($request->has('lng'))
            $poi->lng = $request->input('lng');
        if ($request->has('lat'))
            $poi->lat = $request->input('lat');
        if ($request->has('altitude'))
            $poi->altitude = $request->input('altitude');

        $poi->save();
        addUserLog('updatePoi2', $this->guard()->user()->id, 3);
        return response()->json(['message' => 'update_ok']);
    }

    public function delPoi2(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
        ]);

        $poi = Poi::findOrFail(intval($request->id));
        $poi->delete();
        addUserLog('delPoi2', $this->guard()->user()->id, 2);
        return response()->json(['message' => 'del_ok']);
    }


    public function listDevices2(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
        ]);

        $devices = Device::where('poi_id', intval($request->id))->get();
        // $queries    = DB::getQueryLog();
        // $last_query = end($queries);
        // dd($last_query);
        return response()->json($devices);
    }

    public function addDevice2(Request $request)
    {
        $this->validate($request, [
            'poi_id' => 'required',
            'name' => 'required',
            'id2' => 'required|numeric|unique:devices,id2,,,deleted_at,NULL',
        ]);
        /*$id2 = $request->id2;
        $result = DB::table('devices')
            ->where("devices.id2", $id2)
            ->exists();
        if (!$result) {*/
        $id2 = $request->id2;
        $result = DB::table('devices')
            ->where([["devices.id2", $id2],
                ['devices.deleted_at', '=', NULL],
            ])
            ->exists();
        if (!$result) {
        } else {
            return response()->json(['error' => '该id已经存在，无法继续添加']);
        }


        $device_id = $request->id2;
        $device_id = $device_id / 65536;
        $device_id = base_convert($device_id, 10, 16);
        $device_id = sprintf("%012d", $device_id);


        $device = new Device;
        $device->poi_id = $request->poi_id;
        $device->name = $request->name;
        $device->id2 = $request->id2;
        $device->mac =$device_id;


        if ($request->has('lng'))
            $device->lng = $request->input('lng');
        if ($request->has('lat'))
            $device->lat = $request->input('lat');
        if ($request->has('altitude'))
            $device->altitude = $request->input('altitude');
        if ($request->has('dimension'))
            $device->dimension = $request->input('dimension');
        if ($request->has('unit'))
            $device->unit = $request->input('unit');
        if ($request->has('type'))
            $device->type = $request->input('type');


        $device->save();


        $to_getid = DB::table('devices')
            ->select("devices.*")
            ->where([["devices.id2", $id2],
                ['devices.deleted_at', '=', NULL],
            ])
            ->get();

        foreach ($to_getid as $item){
            $return_id = $item->id;
        }

        $type = $request->input('type');
        if ($type == 5) {
            $result = DB::table('devices')
                ->select("devices.*")
                ->where([["devices.id2", $id2],
                    ['devices.deleted_at', '=', NULL],
                ])
                ->get();
            foreach ($result as $rs)
                $id = $rs->id;


            $device_id = $request->id2;
            $device_id = $device_id / 65536;
            $device_id = base_convert($device_id, 10, 16);
            $device_id = sprintf("%012d", $device_id);


            DB::table('qianxun')
                ->insert(['device_id' => $device_id]);


            DB::table('gnss_device_info')
                ->insert(
                    ['device_hex_id' => $device_id,'device_table_id' => $id]
                    );

            //return response()->json(['message' => 'in it']);
        }
        //ddUserLog('addDevice2', $this->guard()->user()->id, 1);

        DB::table('gnss_device_info')->insertGetId([
            'stand_x' => 0,'stand_y' => 0,'stand_z' => 0,'device_hex_id' => $device_id
        ]);

        return response()->json(['message' => 'add_ok','id'=>$return_id]);
        /*}else{
            return response()->json(['error' => '该id已经存在，无法继续添加']);
        }*/
    }

    public function updateDevice2(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
        ]);

        $device = Device::findOrFail(intval($request->id));
        if ($request->has('name'))
            $device->name = $request->input('name');
        if ($request->has('poi_id'))
            $device->poi_id = $request->input('poi_id');
        if ($request->has('lng'))
            $device->lng = $request->input('lng');
        if ($request->has('lat'))
            $device->lat = $request->input('lat');
        if ($request->has('altitude'))
            $device->altitude = $request->input('altitude');
        if ($request->has('dimension'))
            $device->dimension = $request->input('dimension');
        if ($request->has('unit'))
            $device->unit = $request->input('unit');

        $device->save();
        addUserLog('updateDevice2', $this->guard()->user()->id, 3);
        return response()->json(['message' => 'update_ok']);
    }

    public function delDevice2(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
        ]);

        $device = Device::findOrFail(intval($request->id));
        $device->delete();
        addUserLog('delDevice2', $this->guard()->user()->id, 2);
        return response()->json(['message' => 'del_ok']);
    }

    //sensor
    public function listSensors2(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
        ]);

        $sensors = Sensor::where([['device_id', intval($request->id)],['deleted_at', '=', NULL],])->get();
        // $queries    = DB::getQueryLog();
        // $last_query = end($queries);
        // dd($last_query);

        // $sensors = json_decode(json_encode($sensors), true);
        /* foreach ($sensors as $sensor) {
           $sensor->id2 = (string)$sensor->id2;
       } */
        // $id2  =strval($sensors->id2);
        //$sensors->id2 = (string)$id2;
        return response()->json($sensors);
    }

    public function addSensor2(Request $request)
    {
        $this->validate($request, [
            'device_id' => 'required',
            'name' => 'required',
            'id2' => 'required|numeric|unique:sensors,id2,,,deleted_at,NULL',
        ]);


        $id2 = $request->id2;
        $result = DB::table('sensors')
            ->where("sensors.id2", $id2)
            ->exists();
        if (!$result) {
        } else {
            return response()->json(['error' => '该id已经存在，无法继续添加']);
        }
        /* $id2 = $request->id2;
         $result = DB::table('sensors')
             ->where("sensors.id2", $id2)
             ->exists();
         if(!$result) {*/
        $sensor = new Sensor;
        $sensor->device_id = $request->device_id;
        $sensor->name = $request->name;
        $sensor->id2 = $request->id2;

        if ($request->has('unit'))
            $sensor->unit = $request->unit;

        if ($request->has('up1'))
            $sensor->up1 = $request->up1;
        if ($request->has('up2'))
            $sensor->up2 = $request->up2;
        if ($request->has('down1'))
            $sensor->down1 = $request->down1;
        if ($request->has('down2'))
            $sensor->down2 = $request->down2;
        if ($request->has('value'))
            $sensor->value = $request->value;

        $sensor->save();
        //addUserLog('addSensor2', $this->guard()->user()->id, 1);
        return response()->json(['message' => 'add_ok']);
        /*}else{
            return response()->json(['error' => '该id已经存在，无法继续添加']);
        }*/

    }

    public function updateSensor2(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',

        ]);

        $sensor = Sensor::findOrFail(intval($request->id));

        if ($request->has('id2'))
            $sensor->id2 = $request->input('id2');
        if ($request->has('device_id'))
            $sensor->device_id = $request->device_id;
        if ($request->has('up1'))
            $sensor->up1 = $request->up1;
        if ($request->has('up2'))
            $sensor->up2 = $request->up2;
        if ($request->has('down1'))
            $sensor->down1 = $request->down1;
        if ($request->has('down2'))
            $sensor->down2 = $request->down2;
        if ($request->has('value'))
            $sensor->value = $request->value;
        if ($request->has('unit'))
            $sensor->unit = $request->unit;

        $sensor->save();
        addUserLog('updateSensor2', $this->guard()->user()->id, 3);
        return response()->json(['message' => 'update_ok']);
    }

    public function delSensor2(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
        ]);

        $sensor = Sensor::findOrFail(intval($request->id));
        $sensor->delete();
        addUserLog('delSensor2', $this->guard()->user()->id, 2);
        return response()->json(['message' => 'del_ok']);
    }

    // public function poiFromSensor(Request $request)
    // {
    //   $this->validate($request, [
    //       'sensor_id' => 'required',
    //   ]);
    //
    //   $poi = Sensor::find($request->sensor_id)
    //
    //   return response()->json(['message' => 'del_ok']);
    // }
    public function addPos(Request $request)
    {
        $this->validate($request, [
            'photo_id' => 'required',
            'device_id' => 'required',
            'x' => 'required',
            'y' => 'required',
        ]);

        $photoPostion = new PhotoPostion;
        $photoPostion->photo_id = $request->photo_id;
        $photoPostion->device_id = $request->device_id;
        $photoPostion->x = $request->x;
        $photoPostion->y = $request->y;

        $photoPostion->save();
        addUserLog('addPos', $this->guard()->user()->id, 1);
        return response()->json($photoPostion);
    }

    public function delPos(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',

        ]);

        $photoPostion = PhotoPostion::findOrFail(intval($request->id));

        $photoPostion->delete();
        addUserLog('delPos', $this->guard()->user()->id, 2);
        return response()->json(['message' => 'del_ok']);
    }

    public function addImage(Request $request)
    {
        $this->validate($request, [
            'poi_id' => 'required',

        ]);

        if ($request->hasFile('image') && $request->file('image')->isValid()) {

            $filename = uniqid() . "_" . $request->file('image')->getClientOriginalName();
            $path = "file/" . $filename;
            $request->file('image')->move("./file", $filename);

            $photo = new Photo;
            $photo->poi_id = $request->poi_id;
            $photo->path = $path;
            $photo->info_path = NULL;

            $photo->save();
            addUserLog('addImagePath', $this->guard()->user()->id, 1);
            return response()->json($photo);
            //return response()->json(['message' => 'error']);
        } elseif ($request->hasFile('info_image') && $request->file('info_image')->isValid()) {

            $infofilename = uniqid() . "_" . $request->file('info_image')->getClientOriginalName();
            $infopath = "file/" . $infofilename;
            $request->file('info_image')->move("./file", $infofilename);

            $photo = new Photo;
            $photo->poi_id = $request->poi_id;
            $photo->info_path = $infopath;
            $photo->path = NULL;

            $photo->save();
            addUserLog('addImageInfo', $this->guard()->user()->id, 1);
            return response()->json($photo);
        } else {
            return response()->json(['message' => 'error']);
        }
    }

    public function findImage(Request $request)
    {
        $this->validate($request, [
            'poi_id' => 'required',
            'type' => 'required',
        ]);
        $type = $request->type;
        $id = $request->poi_id;
        if ($type == 1) {                                                                                                        //为1时查找路径，2时查找描述
            $result = Photo::where('poi_id', '=', $id)
                ->whereNotNull('path')
                ->select('id', 'path')
                ->get();
            // $result = Photo::all();

        } else {
            $result = Photo::where('poi_id', $id)
                ->whereNotNull('info_path')
                ->select('info_path')
                ->get();
        }
        return response()->json($result);
    }

    public function delImage(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',

        ]);                                                                                                    //del_falg	为1时删除图片路径，为2时删除图片描述

        if ($request->has('del_flag')) {
            $flag = $request->del_flag;
            if ($flag == 1) {
                $photo = Photo::findOrFail(intval($request->id));
                $photo->path = NULL;
                addUserLog('delImagePath', $this->guard()->user()->id, 2);
            } else {
                $photo = Photo::findOrFail(intval($request->id));
                $photo->info_path = NULL;
                addUserLog('delImageInfo', $this->guard()->user()->id, 2);
            }
            $photo->save();
        } else {
            $photo = Photo::findOrFail(intval($request->id));
            $photo->delete();
            addUserLog('delImage', $this->guard()->user()->id, 2);
            return response()->json(['message' => 'del_ok']);
        }
    }

    public function addPoiInfo(Request $request)
    {
        $this->validate($request, [
            'poi_id' => 'required',
            'l_point' => 'required',
            'r_point' => 'required',
        ]);
        if ($request->hasFile('info') && $request->file('info')->isValid()) {
            $filename = uniqid() . "_" . $request->file('info')->getClientOriginalName();
            $path = "file/" . $filename;
            $request->file('info')->move("./file", $filename);

            $info = new PoiInfo();
            $info->poi_id = $request->poi_id;
            $info->path = $path;
            $info->l_point = $request->l_point;
            $info->r_point = $request->r_point;
            $info->save();
            addUserLog('addPoiInfo', $this->guard()->user()->id, 1);
            return response()->json($info);
        }
    }

    public function getPoiInfo(Request $request)
    {
        $this->validate($request, [
            'poi_id' => 'required',
        ]);
        $id = $request->poi_id;
        $result = PoiInfo::where('poi_id', '=', $id)
            ->select('id', 'path', 'l_point', 'r_point')
            ->get();
        return response()->json($result);
    }

    public function delPoiInfo(Request $request)
    {
        $this->validate($request, [
            'poi_id' => 'required',
        ]);
        $info = PoiInfo::where('poi_id', $request->poi_id);
        $info->delete();
        addUserLog('delPoiInfo', $this->guard()->user()->id, 2);
        return response()->json(['message' => 'del_ok']);
    }

    public function updatePoiInfo(Request $request)
    {
        $this->validate($request, [
            'poi_id' => 'required',
            'l_point' => 'required',
            'r_point' => 'required'
        ]);
        if ($request->hasFile('info') && $request->file('info')->isValid()) {
            $filename = uniqid() . "_" . $request->file('info')->getClientOriginalName();
            $path = "file/" . $filename;
            $request->file('info')->move("./file", $filename);

            $info = PoiInfo::where('poi_id', '=', $request->poi_id)->first();
            $info->path = $path;
            $info->l_point = $request->l_point;
            $info->r_point = $request->r_point;
            $info->save();
            addUserLog('updatePoiInfo', $this->guard()->user()->id, 3);

            return response()->json(['info' => $info, 'type' => gettype($info)]);
        }
    }

    public function Photos(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
        ]);

    }

    public function deviceData(Request $request)
    {
        $messages = [
            'email.required' => '请填写用户名',
            'password.required' => '请填写密码',
        ];
        $empty_object=(object)array();
        $ip = $request->getClientIp();
        $this->validate($request, [
            'email' => 'required',
            'password' => 'required',
            'device_id' => 'required',
            'start_time' => 'required',
            'end_time' => 'required',
        ], $messages);

        $device_id = $request->device_id;
        $device_id=base_convert($device_id,16,10);
        $device_id = $device_id*65536;
        $device_id_1s = DB::table('devices')
                        ->join('pois','devices.poi_id','=','pois.id')
                        ->select('pois.*')
                        ->where([
                            ['devices.id2', '=', $device_id],
                            ['devices.deleted_at','=',NULL],
                        ])
                        ->take(1)
                        ->get();

        foreach ($device_id_1s as $device_id_1){
            $pid=$device_id_1->project_id;
        }
        if(!isset($pid))
             return json_encode((object)null);
        //$device_id_1t= gettype($device_id_1);
        //$device_id_1= $device_id_1["project_id"];
        if (Cache::has($request->email)) {
        } else {
            Cache::put($request->email, 1, 5);
        }
        if (Cache::get($request->email) < 4) {
            $credentials = $request->only('email', 'password');
            if($token = $this->guard()->attempt($credentials)){
                $upid = $this->guard()->user()->project_id;
                $type =$this->guard()->user()->type;
                if ($upid === $pid || $type === 1 ) {
                    Cache::forget($request->email);
                    $start = date_create($request->start_time);
                    $end = date_create($request->end_time);

                    $alarms = DB::table('sensors')
                        ->join('devices','devices.id','=','sensors.device_id')
                        ->join('displacementsensor1', 'sensors.id', '=', 'displacementsensor1.device_id')
                        ->select('displacementsensor1.*','sensors.name')
                        ->where([
                            ['devices.id2', '=', $device_id],
                            ['displacementsensor1.gps_time','>',$start],
                            ['displacementsensor1.gps_time','<',$end],
                            ['devices.deleted_at','=',NULL]
                        ])
                        ->groupby('displacementsensor1.gps_time','displacementsensor1.device_id')
                        ->orderBy('displacementsensor1.gps_time','asc')
                        ->get();
                    /*$result=[];
                    $temp=[];
                    $temp_num = 0;
                    foreach ($alarms as $alarm){
                       if($alarm->device_id>$temp_num){
                           $temp_num = $alarm->device_id;
                           $tempdata = $alarm;
                           $temp[]=$tempdata;
                       } else{
                           $temp_num = $alarm->device_id;
                           $result []= $temp;
                           $temp=[];
                           $tempdata = $alarm;
                           $temp[]=$tempdata;
                       }
                    }*/
                    $temp_time = null;
                    foreach ($alarms as $alarm){
                       $result[$alarm->gps_time][]=$alarm;
                    }
                    if(!isset($result)){
                        $result = json_encode((object)null);
                    }
                    return response()->json(['id'=>$request->device_id,'data'=>$result]);
                } else {
                    return response()->json(['$device_id_1s'=>$device_id_1s,'pid'=>$pid,'upid'=>$upid,'error' => '你不属于当前项目', 'email' => $request->email, 'ip' => $ip], 401);
                }
            }else{
                Cache::increment($request->email);
            }
        } else {
            return response()->json(['error' => '输入密码错误次数过多', $request->email, 'wrong' => Cache::get($request->email), 'ip' => $ip], 403);
            // return response()->json(['error' => 'Unauthorized','email' => $request->email,'ip'=>$ip], 401);
        }
        //echo $ip; */
        return response()->json(['error' => '请输入正确的用户名和密码', 'email' => $request->email, 'ip' => $ip], 401);
    }

    public function deviceData2(Request $request){
        $messages = [
            'email.required' => '请填写用户名',
            'password.required' => '请填写密码',
        ];



        $this->validate($request, [
            'email' => 'required',
            'password' => 'required',
            'device_id' => 'required',
            'start_time' => 'required',
            'end_time' => 'required',
        ], $messages);



        $device_id = $request->device_id;
        $device_id=base_convert($device_id,16,10);
        $device_id = $device_id*65536;
        $device_id_1s = DB::table('devices')
            ->join('pois','devices.poi_id','=','pois.id')
            ->select('pois.*')
            ->where([
                ['devices.id2', '=', $device_id],
                ['devices.deleted_at','=',NULL],
            ])
            ->take(1)
            ->get();



        foreach ($device_id_1s as $device_id_1){
            $pid=$device_id_1->project_id;
        }

        //若未登陆过将账户加入缓存
        if (Cache::has($request->email)) {
        } else {
            Cache::put($request->email, 1, 5);
        }

        //错误密码输入超过四次
        if (Cache::get($request->email) < 4) {
            $credentials = $request->only('email', 'password');

            if($token = $this->guard()->attempt($credentials)){


                $upid = $this->guard()->user()->project_id;
                $type =$this->guard()->user()->type;


                if ($upid === $pid || $type === 1 ) {


                    Cache::forget($request->email);
                    $start = date_create($request->start_time);
                    $end = date_create($request->end_time);


                    $datas = DB::table('sensors')
                        ->join('devices','devices.id','=','sensors.device_id')
                        ->join('displacementsensor1', 'sensors.id', '=', 'displacementsensor1.device_id')
                        ->select('displacementsensor1.*','sensors.name')
                        ->where([
                            ['devices.id2', '=', $device_id],
                            ['displacementsensor1.gps_time','>',$start],
                            ['displacementsensor1.gps_time','<',$end],
                            ['devices.deleted_at','=',NULL]
                        ])
                        ->groupby('displacementsensor1.gps_time','displacementsensor1.device_id')
                        ->orderBy('displacementsensor1.gps_time','asc')
                        ->get();


                    foreach ($datas as $data){
                        $result[$data->gps_time][]=$data;
                    }

                    foreach ($result as $resul){

                        $format_data['id'] = 0;
                        $format_data['deviceId'] = $request->device_id;

                        foreach ($resul as $resu){
                            switch ($resu->name){
                                case '经度':
                                    $format_data['gpsTime'] = $resu->gps_time;
                                    $format_data['longitude'] = $resu->displacement;
                                    break;
                                case '纬度':
                                    $format_data['gpsTime'] = $resu->gps_time;
                                    $format_data['latitude'] = $resu->displacement;
                                    break;
                                case '海拔':
                                    $format_data['gpsTime'] = $resu->gps_time;
                                    $format_data['altitude'] = $resu->displacement;
                                    break;
                                case '偏东':
                                    $format_data['gpsTime'] = $resu->gps_time;
                                    $format_data['east'] = $resu->displacement;
                                    break;
                                case '偏北':
                                    $format_data['gpsTime'] = $resu->gps_time;
                                    $format_data['north'] = $resu->displacement;
                                    break;
                                case '高程变化':
                                    $format_data['gpsTime'] = $resu->gps_time;
                                    $format_data['elevationChange'] = $resu->displacement;
                                    break;
                            }
                        }
                        $result_datas[] = $format_data;
                    }

                    if(isset($result)) {
                        return response()->json(['businessName' => '业务名称', 'data' => $result_datas,'serviceType'=>1,'system'=>'1','user'=>$this->guard()->user()->email]);
                    }else{
                        return response()->json(['error' => '该设备此时段不存在数据']);
                    }
                } else {

                    return response()->json(['error' => '你不属于当前项目'], 401);

                }
            }else{

                Cache::increment($request->email);

            }
        } else {

            return response()->json(['error' => '输入密码错误次数过多'], 401);

        }

        return response()->json(['error' => '请输入正确的用户名和密码'], 401);
    }

    public function testDeviceData(Request $request){

        //$empty_object = (object)array();

        $ip = $request->getClientIp();


        $this->validate($request, [
            'device_id' => 'required',
            'start_time' => 'required',
            'end_time' => 'required',
        ]);


        $device_id = $request->device_id;
        $device_id = base_convert($device_id, 16, 10);
        $device_id = $device_id * 65536;


        $device_id_1s = DB::table('devices')
            ->join('pois', 'devices.poi_id', '=', 'pois.id')
            ->select('pois.*')
            ->where([
                ['devices.id2', '=', $device_id],
                ['devices.deleted_at', '=', NULL],
            ])
            ->take(1)
            ->get();


        foreach ($device_id_1s as $device_id_1) {
            $pid = $device_id_1->project_id;
        }
        if (!isset($pid))
            return json_encode((object)null);


        $upid = $this->guard()->user()->project_id;
        $type = $this->guard()->user()->type;


        if ($upid === $pid || $type === 1) {
            $start = date_create($request->start_time);
            $end = date_create($request->end_time);


            $alarms = DB::table('sensors')
                ->join('devices', 'devices.id', '=', 'sensors.device_id')
                ->join('displacementsensor1', 'sensors.id', '=', 'displacementsensor1.device_id')
                ->select('displacementsensor1.*', 'sensors.name')
                ->where([
                    ['devices.id2', '=', $device_id],
                    ['displacementsensor1.gps_time', '>', $start],
                    ['displacementsensor1.gps_time', '<', $end],
                    ['devices.deleted_at', '=', NULL]
                ])
                ->groupby('displacementsensor1.gps_time', 'displacementsensor1.device_id')
                ->orderBy('displacementsensor1.gps_time', 'asc')
                ->get();

            $temp_time = null;

            foreach ($alarms as $alarm) {
                $result[$alarm->gps_time][] = $alarm;
            }

            if (!isset($result)) {
                $result = json_encode((object)null);
            }

            return response()->json(['id' => $request->device_id, 'data' => $result]);
        } else {
            return response()->json(['$device_id_1s' => $device_id_1s, 'pid' => $pid, 'upid' => $upid, 'error' => '你不属于当前项目', 'email' => $request->email, 'ip' => $ip], 401);
        }

    }

    public function getVideopic(Request $request){
        $user_type =$this->guard()->user()->type;
        $project_id= $this->guard()->user()->project_id;
        $root_dir = '/mnt/myshare/SNAPSHOT/';
        $server_dir = '/VideoData/SNAPSHOT/';
        if (is_dir($root_dir)) {
            $mydir = dir($root_dir);
            while ($file = $mydir->read()) {
                if ($file != "." && $file != "..") {
                    if ($user_type === 1) {
                        $dev_ids[] = $file;
                    } else {
                        $project = DB::table('cameras')
                            ->join('pois', 'pois.id', '=', 'cameras.poi_id')
                            ->select('pois.project_id')
                            ->where('cameras.uid', '=', $file)
                            ->get();
                        //return response()->json([''=>$project,''=>$file]);

                        foreach ($project as $item){
                            $cam_pro_id = $item->project_id;
                        }
                        if(isset($cam_pro_id)) {
                            if ($project_id === $cam_pro_id) {
                                $dev_ids[] = $file;
                            }
                        }
                    }
                }
            }
            //return response()->json($cam_pro_id);
            if(isset($dev_ids)) {
                foreach ($dev_ids as $dev_id) {
                    $dev_path = $root_dir . $dev_id . '/00/';
                    if (is_dir($dev_path)) {
                        $data_path = dir($dev_path);
                        while ($date_dir = $data_path->read()) {
                            if ($date_dir != "." && $date_dir != "..") {
                                $last_date_path = $date_dir;
                            }
                        }
                        $return_path = $server_dir . $dev_id . '/00/' . $last_date_path;
                        $last_date_path = $dev_path . $last_date_path . '/';
                        if (is_dir($last_date_path)) {
                            $pic_path = dir($last_date_path);
                            while ($pic_dir = $pic_path->read()) {
                                if ($pic_dir != "." && $pic_dir != "..") {
                                    $last_pic_path = $pic_dir;
                                }
                            }

                            $return_path = $return_path . '/' . $last_pic_path;
                            $last_pic_path = $last_date_path . $last_pic_path;

                            $results[] = [
                                'id' => $dev_id,
                                'url' => $return_path,
                            ];
                        }
                    }
                }
                return response()->json($results);
            }else{
                return response()->json([]);
            }
        } else {
            return response()->json(['error' => 'not a dir']);
        }
    }
    public function getVideoPicByDate(Request $request){
        $messages = [
           'id.required' => '需要设备id',
           'date.required' => '需要所需查询的时间',
       ];

       $this->validate($request, [
           'id' => 'required',
           'date' => 'required',
       ], $messages);

        $need_time = date("Ymd",$request->date);

        $real_dir = '/mnt/myshare/SNAPSHOT/'.$request->id.'/00/'.$need_time;
        $server_dir ='/VideoData/SNAPSHOT/'.$request->id.'/00/'.$need_time;

        if (is_dir($real_dir)){
            $pic_dirs = dir($real_dir);
            while ($pic_dir = $pic_dirs->read()) {
                if ($pic_dir != "." && $pic_dir != "..") {
                    $return[] =$server_dir.'/'.$pic_dir;
                }
            }
            return response()->json($return);
        }

        return response()->json(['error'=>'当日没有图片数据'],200);

    }
    public function addCameras(Request $request){
        $uid = $request->uid;
        $poi_id = $request->poi_id;
        $cam = new Camera();
        $cam->uid = $uid;
        $cam->poi_id = $poi_id;
        $cam->save();
        return response()->json(['message'=>'添加成功']);
    }
    public function updateCameras(Request $request){
        $uid = $request->uid;
        $poi_id = $request->poi_id;
        $cam = Camera::findOrFail($request->id);
        $cam->uid = $uid;
        $cam->poi_id = $poi_id;
        $cam->save();
        return response()->json(['message'=>'修改成功']);
    }
    public function delCameras(Request $request){
        $cam = Camera::findOrFail($request->id);
        $cam->delete();
        return response()->json(['message'=>'删除成功']);
    }
    public function getCameras(Request $request){
        $cam = Camera::where('poi_id',$request->poi_id)->get();
        return response()->json($cam);
    }

    public function acceptNBData(Request $request ){
        $host= config('auth.authAmqpHost');
        $port=config('auth.authAmqpPort');
        $user=config('auth.authAmqpUser');
        $password=config('auth.authAmqpPassword');
        $vhost = config("auth.authAmqpVhost");
        $connection = new AMQPStreamConnection($host, $port, $user, $password, $vhost);
        $channel = $connection->channel();

        //$channel->exchange_declare('topic_logs', 'topic', false, false, false);

        $routing_key = 'annte';
        $data = $request->getContent();
        $data = json_encode($data);

        $msg = new AMQPMessage($data);

        $channel->basic_publish($msg, 'amq.topic', $routing_key);

        echo " [x] Sent ",$routing_key,':',$data," \n";
        return response()->json($request);
    }

    public function addMoreDevice(Request $request){

        $device_datas = $request->devices;
        $poi_id  = $request->poi_id;
        $device_datas = json_decode($device_datas);


        foreach ($device_datas as $key => $value){

            $mac = $key;
            $name = $value;


            $id2 = intval($mac);
            $id2 = base_convert($id2,16,10);
            $id2 = $id2 *65536;


            $device = new Device;
            $device->poi_id = $poi_id;
            $device->mac = $mac;
            $device->name = $name;
            $device->id2 = $id2;


            $device->save();
        }
        //$poi_id = $device_datas->poi_id;

        /*$device_id=base_convert($device_id,16,10);
        $device_id = $device_id*65536;*/

        return response()->json(["message"=>'add_success']);
    }

    public function addDeciveTest(Request $request){


        $device_test = new Device_test();


        if ($request->has('device_table_id')){
            $device_test->device_table_id = $request->device_table_id;
        }

        if ($request->has('test_start_time')){
            $device_test->test_start_time = $request->test_start_time;
        }

        if ($request->has('test_end_time')){
            $device_test->test_end_time = $request->test_end_time;
        }

        if ($request->has('test_result')){
            $device_test->test_result = $request->test_result;
        }

        if ($request->has('online')){
            $device_test->online = $request->online;
        }

        if ($request->has('device_hex_id')){
            $device_test->device_hex_id = $request->device_hex_id;
        }

        if ($request->has('device_name')){
            $device_test->device_name = $request->device_name;
        }


        $device_test->save();


        return response()->json(['message'=>'添加成功']);
    }

    public function getDeciveTest(Request $request){

        if ($request->has('device_hex_id')){

            return response()->json(Device_test::where('device_hex_id',$request->device_hex_id)->get());

        }else{

            return response()->json(Device_test::all());

        }
    }

    public function delDeciveTest(Request $request){
        Device_test::findOrFail($request->id)->delete();
        return response()->json(['message'=>'删除成功']);
    }

    public function updateDeciveTest(Request $request){


        $device_test = Device_test::findOrFail($request->id);


        if ($request->has('device_table_id')){
            $device_test->device_table_id = $request->device_table_id;
        }

        if ($request->has('test_start_time')){
            $device_test->test_start_time = $request->test_start_time;
        }

        if ($request->has('test_end_time')){
            $device_test->test_end_time = $request->test_end_time;
        }

        if ($request->has('test_result')){
            $device_test->test_result = $request->test_result;
        }

        if ($request->has('online')){
            $device_test->online = $request->online;
        }

        if ($request->has('device_hex_id')){
            $device_test->device_hex_id = $request->device_hex_id;
        }

        if ($request->has('device_name')){
            $device_test->device_name = $request->device_name;
        }


        $device_test->save();


        return response()->json(['message'=>'修改成功']);
    }


    public function test(Request $request ){

        $device_datas = $request->devices;
        $poi_id  = $request->poi_id;
        $device_datas = json_decode($device_datas);
        foreach ($device_datas as $key => $value){
            $mac = $key;
            $name = $value;


            $id2 = intval($mac);
            $id2 = base_convert($id2,16,10);
            $id2 = $id2 *65536;


            $device = new Device;


            $device->poi_id = $poi_id;
            $device->mac = $mac;
            $device->name = $name;
            $device->id2 = $id2;


            $device->save();
        }
        //$poi_id = $device_datas->poi_id;

        /*$device_id=base_convert($device_id,16,10);
        $device_id = $device_id*65536;*/

        return response()->json(["message"=>'add_success']);




        /*$host= config('auth.authAmqpHost');
        $port=config('auth.authAmqpPort');
        $user=config('auth.authAmqpUser');
        $password=config('auth.authAmqpPassword');
        $vhost = config("auth.authAmqpVhost");
        $connection = new AMQPStreamConnection($host, $port, $user, $password, $vhost);
        $channel = $connection->channel();

        //$channel->exchange_declare('topic_logs', 'topic', false, false, false);

        $routing_key = 'annte';
        $data = json_encode($request->query());

        $msg = new AMQPMessage($request);

        $channel->basic_publish($msg, 'amq.topic', $routing_key);

        echo " [x] Sent ",$routing_key,':',$data," \n";

        $channel->close();
        $connection->close();
        $myfile = fopen("zzz", "w");
        fwrite($myfile, $request);
        fclose($myfile);
        return response()->json($request);

        //return response()->json($request);
*/

        /*$messages = [
            'email.required' => '请填写用户名',
            'password.required' => '请填写密码',
        ];



        $this->validate($request, [
            'email' => 'required',
            'password' => 'required',
            'device_id' => 'required',
            'start_time' => 'required',
            'end_time' => 'required',
        ], $messages);



        $device_id = $request->device_id;
        $device_id=base_convert($device_id,16,10);
        $device_id = $device_id*65536;
        $device_id_1s = DB::table('devices')
            ->join('pois','devices.poi_id','=','pois.id')
            ->select('pois.*')
            ->where([
                ['devices.id2', '=', $device_id],
                ['devices.deleted_at','=',NULL],
            ])
            ->take(1)
            ->get();



        foreach ($device_id_1s as $device_id_1){
            $pid=$device_id_1->project_id;
        }

        //若未登陆过将账户加入缓存
        if (Cache::has($request->email)) {
        } else {
            Cache::put($request->email, 1, 5);
        }

        //错误密码输入超过四次
        if (Cache::get($request->email) < 4) {
            $credentials = $request->only('email', 'password');

            if($token = $this->guard()->attempt($credentials)){


                $upid = $this->guard()->user()->project_id;
                $type =$this->guard()->user()->type;


                if ($upid === $pid || $type === 1 ) {


                    Cache::forget($request->email);
                    $start = date_create($request->start_time);
                    $end = date_create($request->end_time);


                    $datas = DB::table('sensors')
                        ->join('devices','devices.id','=','sensors.device_id')
                        ->join('displacementsensor1', 'sensors.id', '=', 'displacementsensor1.device_id')
                        ->select('displacementsensor1.*','sensors.name')
                        ->where([
                            ['devices.id2', '=', $device_id],
                            ['displacementsensor1.gps_time','>',$start],
                            ['displacementsensor1.gps_time','<',$end],
                            ['devices.deleted_at','=',NULL]
                        ])
                        ->groupby('displacementsensor1.gps_time','displacementsensor1.device_id')
                        ->orderBy('displacementsensor1.gps_time','asc')
                        ->get();


                    foreach ($datas as $data){
                        $result[$data->gps_time][]=$data;
                    }

                    foreach ($result as $resul){

                        $format_data['id'] = 0;
                        $format_data['deviceId'] = $request->device_id;

                        foreach ($resul as $resu){
                            switch ($resu->name){
                                case '经度':
                                    $format_data['gpsTime'] = $resu->gps_time;
                                    $format_data['longitude'] = $resu->displacement;
                                    break;
                                case '纬度':
                                    $format_data['gpsTime'] = $resu->gps_time;
                                    $format_data['latitude'] = $resu->displacement;
                                    break;
                                case '海拔':
                                    $format_data['gpsTime'] = $resu->gps_time;
                                    $format_data['altitude'] = $resu->displacement;
                                    break;
                                case '偏东':
                                    $format_data['gpsTime'] = $resu->gps_time;
                                    $format_data['east'] = $resu->displacement;
                                    break;
                                case '偏北':
                                    $format_data['gpsTime'] = $resu->gps_time;
                                    $format_data['north'] = $resu->displacement;
                                    break;
                                case '高程变化':
                                    $format_data['gpsTime'] = $resu->gps_time;
                                    $format_data['elevationChange'] = $resu->displacement;
                                    break;
                            }
                        }
                        $result_datas[] = $format_data;
                    }

                    if(isset($result)) {
                        return response()->json(['businessName' => '业务名称', 'data' => $result_datas,'serviceType'=>1,'system'=>'1','user'=>$this->guard()->user()->email]);
                    }else{
                        return response()->json(['error' => '该设备此时段不存在数据']);
                    }
                } else {

                    return response()->json(['error' => '你不属于当前项目'], 401);

                }
            }else{

                Cache::increment($request->email);

            }
        } else {

            return response()->json(['error' => '输入密码错误次数过多'], 401);

        }

        return response()->json(['error' => '请输入正确的用户名和密码'], 401);*/

        /*$pinyin = new Pinyin();
        $project_id = $this->guard()->user()->project_id;
        $sId = $q;

        $devices = Device::search($sId)
            ->where('devices.deleted_at', '=', NULL)
            ->get();
        foreach ($devices as $device) {
            if ($device->poi != null)
                $poiss[$device->poi->id] = $device->poi;
        }
        $count = 1;
        foreach ($poiss as $poi) {
            //return response()->json(['type'=>gettype($poi),'project_id'=>$poi->project_id,'poi_name'=>$poi->name]);
            if ($poi->project_id === $project_id || $this->guard()->user()->type === 1) { //权限控制，测试版本暂时不加入相应功能
                $poi_name = $poi->name;

                $poi["pinyin"] = $pinyin->convert($poi_name)[0][0];
                $photos = $poi->photos;
                foreach ($photos as $photo) {
                    $photo["devices"] = $photo->photopostions;
                }
                $poi["photos"] = $photos;
                $poi["devices2"] = $poi->devices;
                $pois[] = $poi;
                //$count++;
            }
        }*/

        //return response()->json($pois);

        /*$all_datas = Displacementsensor1::selectRaw('id,DATE_FORMAT(gps_time,"%Y-%m-%d") as gps_time,device_id,avg(displacement) as displacement')
            ->where('device_id', '=', $request->device_id)
            ->orderBy('gps_time', 'desc')
            ->groupby(DB::raw('DATE_FORMAT(gps_time,"%Y-%m-%d")'))
            -take(30)
            ->get();
        return response()->json($all_datas);*/

        /*$data = 0x000300000001;
        while ($data < 0x00030000000c){
            $device_id=base_convert($data,16,10);
            $device_id = $data*65536;
            $result[] = $device_id;
            $data = $data+1;
        }
        return response()->json($result);*/

        //根据id和日期请求图片路径
        /*$messages = [
            'id.required' => '需要设备id',
            'date.required' => '需要所需查询的时间',
        ];
        $ip = $request->getClientIp();
        $this->validate($request, [
            'id' => 'required',
            'date' => 'required',
        ], $messages);*/
        //$t =date_create($request->date);
        //$t =date_create();
        /*$need_time = date("Ymd",$request->date);
        //return response()->json($need_time);
        $real_dir = '/mnt/myshare/SNAPSHOT/'.$request->id.'/00/'.$need_time;
        $server_dir ='/VideoData/SNAPSHOT/'.$request->id.'/00/'.$need_time;
        if (is_dir($real_dir)){
            $pic_dirs = dir($real_dir);
            while ($pic_dir = $pic_dirs->read()) {
                if ($pic_dir != "." && $pic_dir != "..") {
                    $return[] =$server_dir.$pic_dir;
                }
            }
            return response()->json($return);
        }
        return response()->json(['error'=>'当日没有图片数据'],200);*/

        //测试接口，用于实验功能的实现
        //获取设备信息表中的内容
        /*
        $id = $request->id;
        $result  = DB::table('displacementsensor1')
            ->where('displacementsensor1.device_id','=',$id)
            ->groupby('displacementsensor1.device_id','displacementsensor1.gps_time')
            ->orderBy('displacementsensor1.gps_time','asc')
            ->take(100)
            ->get();
        return response()->json($result);
        */
        //测试读取配置文件中的内容
        /*$value = config('auth.test');
        return response()->json($value);

        */
        //所存储的设备id2与所显示的id2相互转换
        //10/16进制转换，右移
        /*$result = DB::table('devices')
            ->where([['devices.id', '=', $id1],
                ['devices.deleted_at', '=', NULL],
            ])
            ->select("devices.*")
            ->get();
        foreach ($result as $rs) {
            $id = $rs->id;
            $device_id = $rs->id2;
        }*/
      /*  $device_id = 1125899910119424;
        $device_id = $device_id / 65536;
        $device_id = base_convert($device_id, 10, 16);
        $device_id = sprintf("%012d", $device_id);
        return response()->json($device_id);*/
        /*
        //向千寻数据表中插入数据
        DB::table('qianxun')->insert([
        ['email' => 'taylor@example.com', 'votes' => 0],
        ['email' => 'dayle@example.com', 'votes' => 0]
        ]);
        */
        /*//尝试通过模糊搜索查询设备表中设备的监测点信息
        //TODO sql语句仍存在问题，取出的值与期望的值不同
        $id1 = '0004';
        $id1 = $id1.'%';
        $result = DB::table('pois')
            ->join('devices', 'devices.poi_id', '=', 'pois.id')
            ->select("devices.*")
            ->whereRaw('concat("000",LEFT(CONV(devices.id2,10,16),9)) like "0004%"')
            ->orderBy('seq', 'asc')
            ->get();
        return response()->json($result);*/
        //$pois = Poi::search(urldecode($q))
        //测试直接搜索id
        //$p = '/\d*/';
       /* $pinyin = new Pinyin();
        $sId = $request->id;
        $sId = strval($sId);
        $len = strlen($sId);
        if($len<13){
            while($len < 13){
                $sId = $sId.'0';
                $len++;
            }
        }
        $sId = base_convert($sId, 16, 10);
        //$sId = '1125899906908160';
        $sId = $sId *65536;
        $devices = Device::search(urldecode($sId))->get();
        foreach ($devices as $device) {
            $pois[] = $device->poi;
        }
        foreach ($pois as $poi) {
            $poi["pinyin"] = $pinyin->convert($poi->name)[0][0];


            $photos = $poi->photos;
            foreach ($photos as $photo) {
                $photo["devices"] = $photo->photopostions;
            }

            $poi["photos"] = $photos;
            $poi["devices2"] = $poi->devices;

            //$poi["photos"] = Photo::where('poi_id',$poi->id)->get();
            //$poi["photos"] = \App\Photo::all();
            //$queries    = DB::getQueryLog();
            //$last_query = end($queries);
            //dd($last_query);
        }
        return response()->json($pois);
        //return response()->json(['pois'=>$pois]);*/
        //实现遍历文件夹中存在文件的功能
         //测试文件路径 ./file
        //查询路径下存在的文件功能已实现
        //TODO 根据图片文件目录生成规则查询相应文件夹中的文件
        /*$root_dir = '/mnt/myshare/SNAPSHOT/';
        if (is_dir($root_dir)) {
            $mydir = dir($root_dir);
            while ($file = $mydir->read()) {
                if ($file != "." && $file != "..") {
                    $dev_ids[] = $file;
                }
            }
            foreach ($dev_ids as $dev_id){
                $dev_path = $root_dir.$dev_id.'/00/';
                if(is_dir($dev_path)) {
                    $data_path = dir($dev_path);
                    while ($date_dir = $data_path->read()) {
                        if ($date_dir != "." && $date_dir != "..") {
                            $last_date_path = $date_dir;
                        }
                    }

                    $last_date_path = $dev_path . $last_date_path . '/';
                    if (is_dir($last_date_path)) {
                        $pic_path = dir($last_date_path);
                        while ($pic_dir = $pic_path->read()) {
                            if ($pic_dir != "." && $pic_dir != "..") {
                                $last_pic_path = $pic_dir;
                            }
                        }

                        $last_pic_path = $last_date_path . $last_pic_path;

                        $results[] =[
                            'id' => $dev_id,
                            'url' =>  $last_pic_path,
                        ];
                    }
                }
            }
            return response()->json($results);

        } else {
            return response()->json(['error' => 'not a dir']);
        }*/

    }
}

