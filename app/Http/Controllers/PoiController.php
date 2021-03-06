<?php

namespace App\Http\Controllers;

use App\AlarmsSensor;
use http\Env\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use function MongoDB\BSON\fromJSON;
use phpDocumentor\Reflection\Project;
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
include_once 'delFunction.php';

class PoiController extends Controller
{
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
                'getSensorInfoByDeviceId',
                'getTotalDataCount',
                'getDeviceOnlineCount',
                'test1',
            ]]);

        DB::connection()->enableQueryLog();
    }
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
            $alarm->time = $request->time;
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
                }
            }
        }

        //根据监测点名搜索
        if ($this->guard()->user()->type == 1)
            $pois_name = Poi::search(urldecode($q))
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
        $devices = Device::where('poi_id', $request->id)->get();
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
                when '高程变化' then 1
		        when '偏北' then 2
		        when '偏东' then 3
		        when '经度' then 4
		        when '纬度' then 5
		        when '海拔' then 6
		        when '小时雨量' then 7
		        when '当天雨量' then 8
		        when '裂缝' then 9
		        when '裂缝值' then 10
		        when '湿度' then 11
		        when '温度' then 12
		        when '30cm含水量' then 13
		        when '30cm温度' then 14
		        when '60cm含水量' then 15
		        when '60cm温度' then 16
		        when '90cm含水量' then 17
		        when '90cm温度' then 18
		        when '方位角x' then 19
		        when '方位角' then 20
		        when '俯仰角y' then 21
		        when '横滚角z' then 22
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
                    $sheet->setCellValue('A' . $row, $record->displacement);
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
        ini_set('memory_limit','2048M');
        $projectId = $this->guard()->user()->project_id;
        if ($this->guard()->user()->type == 3) {
            $colors = DB::table('insar')
                ->select('color')
                ->where([['mean_velocity','<',-20],['project_id','=',$projectId]])
                ->distinct()
                ->get();

            foreach ($colors as $color) {
                $insars = DB::table('insar')->select('id', 'latitude', 'longitude', 'lng_g', 'lat_g', 'mean_velocity')
                    ->where([['color', $color->color],['mean_velocity','<',-20],['project_id','=',$projectId]])
                    ->get();
                $color->insars = $insars;

            }

        ini_set('memory_limit','1024M');
            return response()->json($colors);
        }else{
            $colors = DB::table('insar')
                ->select('color')
                ->where('project_id','=',$projectId)
                ->distinct()
                ->get();

            foreach ($colors as $color) {
                $insars = DB::table('insar')
                    ->select('id', 'latitude', 'longitude', 'lng_g', 'lat_g')
                    ->where([['color','=', $color->color],['project_id','=',$projectId]])
                    ->get();
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
                $all_data->displacement = floatval($all_data->displacement);
            }
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
        ]);

        $poi = new Poi;

        $poi->project_id = $request->project_id;
        $poi->name = $request->name;
        $poi->location = $request->location;
        if ($request->has('user_id'))
            $poi->user_id = $request->input('user_id');
        if ($request->has('lng'))
            $poi->lng = $request->input('lng');
        if ($request->has('lat'))
            $poi->lat = $request->input('lat');
        if ($request->has('altitude'))
            $poi->altitude = $request->input('altitude');
        if ($request->has('type'))
            $poi->type = $request->input('type');


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
        if ($request->has('type'))
            $poi->type = $request->input('type');

        $poi->save();
        addUserLog('updatePoi2', $this->guard()->user()->id, 3);
        return response()->json(['message' => 'update_ok']);
    }

    public function delPoi2(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
        ]);

        $result = _delPoi($request->id);
        return response()->json(['message' => 'delete_ok']);
    }


    public function listDevices2(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
        ]);

        if($request->has('keyword')){
            return  response()->json(Device::search(urldecode($request->keyword))
                                            ->where('poi_id', intval($request->id))
                                            ->get());
        }

        $devices = Device::where('poi_id', intval($request->id))->get();
        return response()->json($devices);
    }

    public function addDevice2(Request $request)
    {
        $this->validate($request, [
            'poi_id' => 'required',
            'name' => 'required',
            'id2' => 'required|numeric|unique:devices,id2,,,deleted_at,NULL',
        ]);

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


        $device_mac = $request->id2;
        $device_mac = $device_mac / 65536;
        $device_mac = base_convert($device_mac, 10, 16);
        $device_mac = sprintf("%012s", $device_mac);


        $device = new Device;
        $device->poi_id = $request->poi_id;
        $device->name = $request->name;
        $device->id2 = $request->id2;
        $device->mac =$device_mac;


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

        $device_id = $device->id;

        $device_type = substr($device_mac,0,4);

        switch ($device_type){
            case '0003':
                DB::table('qianxun')
                    ->insert(['device_id' => $device_mac]);
                DB::table('gnss_device_info')
                    ->insert(
                        ['device_hex_id' => $device_mac,'device_table_id' => $device_id]
                    );
                break;
            case '0005':
                DB::table('crack_device_info')
                    ->insert(['device_hex_id' => $device_mac,'stand' => 0]);
                break;
        }

        return response()->json(['message' => 'add_ok','id'=>$device_id]);
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
        _delDevice($request->id);
        addUserLog('delDevice2', $this->guard()->user()->id, 2);
        return response()->json(['message' => 'del_ok']);
    }

    public function listSensors2(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
        ]);

        $sensors = Sensor::where([['device_id', intval($request->id)],['deleted_at', '=', NULL],])->get();
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
            ->where([
                ['sensors.id2', $id2],
                ['sensors.deleted_at', '=', NULL]
            ])
            ->exists();
        if (!$result) {
        } else {
            return response()->json(['error' => '该id已经存在，无法继续添加']);
        }
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
        return response()->json(['message' => 'add_ok']);
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

        _delSensor($request->id);

        addUserLog('delSensor2', $this->guard()->user()->id, 2);
        return response()->json(['message' => 'del_ok']);
    }

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
        }
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
        return response()->json(["message"=>'add_success']);
    }

    public function addDeviceTest(Request $request){


        $device_test = new Device_test();


        if ($request->has('device_table_id')){
            $device_test->device_table_id = $request->device_table_id;
        }

        if ($request->has('gprs_test_start_time')){
            $device_test->gprs_test_start_time = $request->gprs_test_start_time;
        }

        if ($request->has('gprs_test_end_time')){
            $device_test->gprs_test_end_time = $request->gprs_test_end_time;
        }

        if ($request->has('gprs_test_result')){
            $device_test->gprs_test_result = $request->gprs_test_result;
        }

        if ($request->has('gprs_test_status')){
            $device_test->gprs_test_status = $request->gprs_test_status;
        }

        if ($request->has('lora_test_start_time')){
            $device_test->lora_test_start_time = $request->lora_test_start_time;
        }

        if ($request->has('lora_test_end_time')){
            $device_test->lora_test_end_time = $request->lora_test_end_time;
        }

        if ($request->has('lora_test_result')){
            $device_test->lora_test_result = $request->lora_test_result;
        }

        if ($request->has('lora_test_status')){
            $device_test->lora_test_status = $request->lora_test_status;
        }

        if ($request->has('online')){
            $device_test->online = $request->online;
        }

        if ($request->has('remarks')){
            $device_test->remarks = $request->remarks;
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

    public function getDeviceTest(Request $request){

        if ($request->has('device_hex_id')){

            return response()->json(Device_test::where('device_hex_id',$request->device_hex_id)->get());

        }elseif($request->has('keyword')){
            return response()->json( Device_test::search($request->keyword)->get());
        }else{

            return response()->json(Device_test::all());

        }
    }

    public function delDeviceTest(Request $request){
        Device_test::findOrFail($request->id)->delete();
        return response()->json(['message'=>'删除成功']);
    }

    public function updateDeviceTest(Request $request){


        $device_test = Device_test::findOrFail($request->id);


        if ($request->has('device_table_id')){
            $device_test->device_table_id = $request->device_table_id;
        }

        if ($request->has('gprs_test_start_time')){
            $device_test->gprs_test_start_time = $request->gprs_test_start_time;
        }

        if ($request->has('gprs_test_end_time')){
            $device_test->gprs_test_end_time = $request->gprs_test_end_time;
        }

        if ($request->has('gprs_test_result')){
            $device_test->gprs_test_result = $request->gprs_test_result;
        }

        if ($request->has('gprs_test_status')){
            $device_test->gprs_test_status = $request->gprs_test_status;
        }

        if ($request->has('lora_test_start_time')){
            $device_test->lora_test_start_time = $request->lora_test_start_time;
        }

        if ($request->has('lora_test_end_time')){
            $device_test->lora_test_end_time = $request->lora_test_end_time;
        }

        if ($request->has('lora_test_result')){
            $device_test->lora_test_result = $request->lora_test_result;
        }

        if ($request->has('lora_test_status')){
            $device_test->lora_test_status = $request->lora_test_status;
        }

        if ($request->has('online')){
            $device_test->online = $request->online;
        }

        if ($request->has('remarks')){
            $device_test->remarks = $request->remarks;
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

    public function addMoreDeviceTest(Request $request){


        $device_test_datas = $request->devices_test;
        $device_datas = json_decode($device_test_datas);


        foreach ($device_datas as $key => $value){

            $mac = $key;
            $name = $value;


            $device_test = new Device_test();
            $device_test->device_hex_id = $mac;
            $device_test->device_name = $name;


            $device_test->save();
        }


        return response()->json(['message'=>'添加成功']);
    }

    public function getSomeThingByProjectId(Request $request){
        $this->validate($request, [
            'projectId' => 'required',
            'recentUpdate'=>'numeric'
        ]);

        $project_id = $request->projectId;
        if($request->has('online') && $request->has('recentUpdate')){
            if((int)($request->recentUpdate) === 0){
                $result = DB::select('select 
	baseInf.projectId, baseInf.projectName, baseInf.poiId
	, concat(baseInf.poiName,"-",baseInf.poiLocation) as poiName
	, baseInf.device_type, baseInf.deviceTypeName, baseInf.deviceNum
	, baseInf.mac, baseInf.deviceId, baseInf.online
    ,dataInf.maxId, dataInf.gps_time 
	,dataInf.sensorId ,dataInf.data
from 
	(
		select 
			SummaryPoi.projectId, SummaryPoi.projectName, SummaryPoi.poiId, SummaryPoi.poiName, SummaryPoi.poiLocation
			, SummaryPoi.device_type, SummaryPoi.deviceTypeName, SummaryPoi.deviceNum
			, SummaryDev.deviceId, SummaryDev.mac, SummaryDev.online
		from
			(
				select 
					A.id as projectId, A.name as projectName, B.id as poiId, B.name as poiName, B.location as poiLocation,
					replace(Left(C.mac,4),\'0\',\'\') as device_type,
					(case replace(Left(C.mac,4),\'0\',\'\') 
						when 1 then \'天线\'
						when 2 then \'其他\'
						when 3 then \'GNSS\'
						when 4 then \'雨量\'
						when 5 then \'裂缝\'
						when 6 then \'土壤含水\'
					else \'其他\' end) as deviceTypeName,
					count(*) as deviceNum
				from 
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by projectId, projectName, poiId, poiName,  
					device_type,  deviceTypeName
			) SummaryPoi
			left join 
			(
				select 
					B.id as poiId, C.id as deviceId, 
					replace(Left(C.mac,4),\'0\',\'\') as device_type, 
					C.mac, C.online
				from
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					#暂不统计兰州项目（锚索计）
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by A.id, B.id, C.id
			) SummaryDev
			on SummaryPoi.poiId = SummaryDev.poiId and SummaryPoi.device_type = SummaryDev.device_type
    ) baseInf
    left join
    (
		select 
			maxId.deviceId, maxId.maxId, displacementsensor1.gps_time, 
			displacementsensor1.device_id as sensorId, 
			displacementsensor1.displacement as data
		from 
			(
				select 
					A.id deviceId, max(C.id) maxId 
				from 
					projects 
					left join pois on projects.id = pois.project_id
					left join devices A on pois.id = A.poi_id
					left join sensors B on A.id = B.device_id
					left join displacementsensor1 C on B.id = C.device_id
				where projects.id = ?
				group by A.id  
			) maxId
			left join displacementsensor1 on maxId.maxId = displacementsensor1.id
		where 
			maxId.maxId is not NULL
    ) dataInf on baseInf.deviceId = dataInf.deviceId
where 
	baseInf.online = ?
	and dataInf.gps_time is NULL;', [$project_id,$project_id,$project_id,$request->online]);
            }
            if((int)($request->recentUpdate) === 1){
                $result = DB::select('select 
	baseInf.projectId, baseInf.projectName, baseInf.poiId
	, concat(baseInf.poiName,"-",baseInf.poiLocation) as poiName
	, baseInf.device_type, baseInf.deviceTypeName, baseInf.deviceNum
	, baseInf.mac, baseInf.deviceId, baseInf.online
    ,dataInf.maxId, dataInf.gps_time 
	,dataInf.sensorId ,dataInf.data
from 
	(
		select 
			SummaryPoi.projectId, SummaryPoi.projectName, SummaryPoi.poiId, SummaryPoi.poiName, SummaryPoi.poiLocation
			, SummaryPoi.device_type, SummaryPoi.deviceTypeName, SummaryPoi.deviceNum
			, SummaryDev.deviceId, SummaryDev.mac, SummaryDev.online
		from
			(
				select 
					A.id as projectId, A.name as projectName, B.id as poiId, B.name as poiName, B.location as poiLocation,
					replace(Left(C.mac,4),\'0\',\'\') as device_type,
					(case replace(Left(C.mac,4),\'0\',\'\') 
						when 1 then \'天线\'
						when 2 then \'其他\'
						when 3 then \'GNSS\'
						when 4 then \'雨量\'
						when 5 then \'裂缝\'
						when 6 then \'土壤含水\'
					else \'其他\' end) as deviceTypeName,
					count(*) as deviceNum
				from 
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by projectId, projectName, poiId, poiName,  
					device_type,  deviceTypeName
			) SummaryPoi
			left join 
			(
				select 
					B.id as poiId, C.id as deviceId, 
					replace(Left(C.mac,4),\'0\',\'\') as device_type, 
					C.mac, C.online
				from
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					#暂不统计兰州项目（锚索计）
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by A.id, B.id, C.id
			) SummaryDev
			on SummaryPoi.poiId = SummaryDev.poiId and SummaryPoi.device_type = SummaryDev.device_type
    ) baseInf
    left join
    (
		select 
			maxId.deviceId, maxId.maxId, displacementsensor1.gps_time, 
			displacementsensor1.device_id as sensorId, 
			displacementsensor1.displacement as data
		from 
			(
				select 
					A.id deviceId, max(C.id) maxId 
				from 
					projects 
					left join pois on projects.id = pois.project_id
					left join devices A on pois.id = A.poi_id
					left join sensors B on A.id = B.device_id
					left join displacementsensor1 C on B.id = C.device_id
				where projects.id = ?
				group by A.id  
			) maxId
			left join displacementsensor1 on maxId.maxId = displacementsensor1.id
		where 
			maxId.maxId is not NULL
    ) dataInf on baseInf.deviceId = dataInf.deviceId
where 
	baseInf.online = ?
	and TIMESTAMPDIFF(HOUR, dataInf.gps_time, now()) >=2;', [$project_id,$project_id,$project_id,$request->online]);
            }
            if((int)($request->recentUpdate) === 2){
                $result = DB::select('select 
	baseInf.projectId, baseInf.projectName, baseInf.poiId
	, concat(baseInf.poiName,"-",baseInf.poiLocation) as poiName
	, baseInf.device_type, baseInf.deviceTypeName, baseInf.deviceNum
	, baseInf.mac, baseInf.deviceId, baseInf.online
    ,dataInf.maxId, dataInf.gps_time 
	,dataInf.sensorId ,dataInf.data
from 
	(
		select 
			SummaryPoi.projectId, SummaryPoi.projectName, SummaryPoi.poiId, SummaryPoi.poiName, SummaryPoi.poiLocation
			, SummaryPoi.device_type, SummaryPoi.deviceTypeName, SummaryPoi.deviceNum
			, SummaryDev.deviceId, SummaryDev.mac, SummaryDev.online
		from
			(
				select 
					A.id as projectId, A.name as projectName, B.id as poiId, B.name as poiName, B.location as poiLocation,
					replace(Left(C.mac,4),\'0\',\'\') as device_type,
					(case replace(Left(C.mac,4),\'0\',\'\') 
						when 1 then \'天线\'
						when 2 then \'其他\'
						when 3 then \'GNSS\'
						when 4 then \'雨量\'
						when 5 then \'裂缝\'
						when 6 then \'土壤含水\'
					else \'其他\' end) as deviceTypeName,
					count(*) as deviceNum
				from 
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by projectId, projectName, poiId, poiName,  
					device_type,  deviceTypeName
			) SummaryPoi
			left join 
			(
				select 
					B.id as poiId, C.id as deviceId, 
					replace(Left(C.mac,4),\'0\',\'\') as device_type, 
					C.mac, C.online
				from
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					#暂不统计兰州项目（锚索计）
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by A.id, B.id, C.id
			) SummaryDev
			on SummaryPoi.poiId = SummaryDev.poiId and SummaryPoi.device_type = SummaryDev.device_type
    ) baseInf
    left join
    (
		select 
			maxId.deviceId, maxId.maxId, displacementsensor1.gps_time, 
			displacementsensor1.device_id as sensorId, 
			displacementsensor1.displacement as data
		from 
			(
				select 
					A.id deviceId, max(C.id) maxId 
				from 
					projects 
					left join pois on projects.id = pois.project_id
					left join devices A on pois.id = A.poi_id
					left join sensors B on A.id = B.device_id
					left join displacementsensor1 C on B.id = C.device_id
				where projects.id = ?
				group by A.id  
			) maxId
			left join displacementsensor1 on maxId.maxId = displacementsensor1.id
		where 
			maxId.maxId is not NULL
    ) dataInf on baseInf.deviceId = dataInf.deviceId
where 
	baseInf.online = ?
	and TIMESTAMPDIFF(HOUR, dataInf.gps_time, now()) < 2;', [$project_id,$project_id,$project_id,$request->online]);
            }
        }else if($request->has('online')){
            $result = DB::select('select 
	baseInf.projectId, baseInf.projectName, baseInf.poiId
	, concat(baseInf.poiName,"-",baseInf.poiLocation) as poiName
	, baseInf.device_type, baseInf.deviceTypeName, baseInf.deviceNum
	, baseInf.mac, baseInf.deviceId, baseInf.online
    ,dataInf.maxId, dataInf.gps_time 
	,dataInf.sensorId ,dataInf.data
from 
	(
		select 
			SummaryPoi.projectId, SummaryPoi.projectName, SummaryPoi.poiId, SummaryPoi.poiName, SummaryPoi.poiLocation
			, SummaryPoi.device_type, SummaryPoi.deviceTypeName, SummaryPoi.deviceNum
			, SummaryDev.deviceId, SummaryDev.mac, SummaryDev.online
		from
			(
				select 
					A.id as projectId, A.name as projectName, B.id as poiId, B.name as poiName, B.location as poiLocation,
					replace(Left(C.mac,4),\'0\',\'\') as device_type,
					(case replace(Left(C.mac,4),\'0\',\'\') 
						when 1 then \'天线\'
						when 2 then \'其他\'
						when 3 then \'GNSS\'
						when 4 then \'雨量\'
						when 5 then \'裂缝\'
						when 6 then \'土壤含水\'
					else \'其他\' end) as deviceTypeName,
					count(*) as deviceNum
				from 
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by projectId, projectName, poiId, poiName,  
					device_type,  deviceTypeName
			) SummaryPoi
			left join 
			(
				select 
					B.id as poiId, C.id as deviceId, 
					replace(Left(C.mac,4),\'0\',\'\') as device_type, 
					C.mac, C.online
				from
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					#暂不统计兰州项目（锚索计）
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by A.id, B.id, C.id
			) SummaryDev
			on SummaryPoi.poiId = SummaryDev.poiId and SummaryPoi.device_type = SummaryDev.device_type
    ) baseInf
    left join
    (
		select 
			maxId.deviceId, maxId.maxId, displacementsensor1.gps_time, 
			displacementsensor1.device_id as sensorId, 
			displacementsensor1.displacement as data
		from 
			(
				select 
					A.id deviceId, max(C.id) maxId 
				from 
					projects 
					left join pois on projects.id = pois.project_id
					left join devices A on pois.id = A.poi_id
					left join sensors B on A.id = B.device_id
					left join displacementsensor1 C on B.id = C.device_id
				where projects.id = ?
				group by A.id  
			) maxId
			left join displacementsensor1 on maxId.maxId = displacementsensor1.id
		where 
			maxId.maxId is not NULL
    ) dataInf on baseInf.deviceId = dataInf.deviceId
where 
	baseInf.online = ?;', [$project_id,$project_id,$project_id,$request->online]);
        }else if($request->has('recentUpdate')){
            if((int)($request->recentUpdate) === 0){
                $result = DB::select('select 
	baseInf.projectId, baseInf.projectName, baseInf.poiId
	, concat(baseInf.poiName,"-",baseInf.poiLocation) as poiName
	, baseInf.device_type, baseInf.deviceTypeName, baseInf.deviceNum
	, baseInf.mac, baseInf.deviceId, baseInf.online
    ,dataInf.maxId, dataInf.gps_time 
	,dataInf.sensorId ,dataInf.data
from 
	(
		select 
			SummaryPoi.projectId, SummaryPoi.projectName, SummaryPoi.poiId, SummaryPoi.poiName, SummaryPoi.poiLocation
			, SummaryPoi.device_type, SummaryPoi.deviceTypeName, SummaryPoi.deviceNum
			, SummaryDev.deviceId, SummaryDev.mac, SummaryDev.online
		from
			(
				select 
					A.id as projectId, A.name as projectName, B.id as poiId, B.name as poiName, B.location as poiLocation,
					replace(Left(C.mac,4),\'0\',\'\') as device_type,
					(case replace(Left(C.mac,4),\'0\',\'\') 
						when 1 then \'天线\'
						when 2 then \'其他\'
						when 3 then \'GNSS\'
						when 4 then \'雨量\'
						when 5 then \'裂缝\'
						when 6 then \'土壤含水\'
					else \'其他\' end) as deviceTypeName,
					count(*) as deviceNum
				from 
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by projectId, projectName, poiId, poiName,  
					device_type,  deviceTypeName
			) SummaryPoi
			left join 
			(
				select 
					B.id as poiId, C.id as deviceId, 
					replace(Left(C.mac,4),\'0\',\'\') as device_type, 
					C.mac, C.online
				from
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					#暂不统计兰州项目（锚索计）
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by A.id, B.id, C.id
			) SummaryDev
			on SummaryPoi.poiId = SummaryDev.poiId and SummaryPoi.device_type = SummaryDev.device_type
    ) baseInf
    left join
    (
		select 
			maxId.deviceId, maxId.maxId, displacementsensor1.gps_time, 
			displacementsensor1.device_id as sensorId, 
			displacementsensor1.displacement as data
		from 
			(
				select 
					A.id deviceId, max(C.id) maxId 
				from 
					projects 
					left join pois on projects.id = pois.project_id
					left join devices A on pois.id = A.poi_id
					left join sensors B on A.id = B.device_id
					left join displacementsensor1 C on B.id = C.device_id
				where projects.id = ?
				group by A.id  
			) maxId
			left join displacementsensor1 on maxId.maxId = displacementsensor1.id
		where 
			maxId.maxId is not NULL
    ) dataInf on baseInf.deviceId = dataInf.deviceId
where 
	 dataInf.gps_time is NULL;', [$project_id,$project_id,$project_id]);
            }
            if((int)($request->recentUpdate) === 1){
                $result = DB::select('select 
	baseInf.projectId, baseInf.projectName, baseInf.poiId
	, concat(baseInf.poiName,"-",baseInf.poiLocation) as poiName
	, baseInf.device_type, baseInf.deviceTypeName, baseInf.deviceNum
	, baseInf.mac, baseInf.deviceId, baseInf.online
    ,dataInf.maxId, dataInf.gps_time 
	,dataInf.sensorId ,dataInf.data
from 
	(
		select 
			SummaryPoi.projectId, SummaryPoi.projectName, SummaryPoi.poiId, SummaryPoi.poiName, SummaryPoi.poiLocation
			, SummaryPoi.device_type, SummaryPoi.deviceTypeName, SummaryPoi.deviceNum
			, SummaryDev.deviceId, SummaryDev.mac, SummaryDev.online
		from
			(
				select 
					A.id as projectId, A.name as projectName, B.id as poiId, B.name as poiName, B.location as poiLocation,
					replace(Left(C.mac,4),\'0\',\'\') as device_type,
					(case replace(Left(C.mac,4),\'0\',\'\') 
						when 1 then \'天线\'
						when 2 then \'其他\'
						when 3 then \'GNSS\'
						when 4 then \'雨量\'
						when 5 then \'裂缝\'
						when 6 then \'土壤含水\'
					else \'其他\' end) as deviceTypeName,
					count(*) as deviceNum
				from 
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by projectId, projectName, poiId, poiName,  
					device_type,  deviceTypeName
			) SummaryPoi
			left join 
			(
				select 
					B.id as poiId, C.id as deviceId, 
					replace(Left(C.mac,4),\'0\',\'\') as device_type, 
					C.mac, C.online
				from
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					#暂不统计兰州项目（锚索计）
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by A.id, B.id, C.id
			) SummaryDev
			on SummaryPoi.poiId = SummaryDev.poiId and SummaryPoi.device_type = SummaryDev.device_type
    ) baseInf
    left join
    (
		select 
			maxId.deviceId, maxId.maxId, displacementsensor1.gps_time, 
			displacementsensor1.device_id as sensorId, 
			displacementsensor1.displacement as data
		from 
			(
				select 
					A.id deviceId, max(C.id) maxId 
				from 
					projects 
					left join pois on projects.id = pois.project_id
					left join devices A on pois.id = A.poi_id
					left join sensors B on A.id = B.device_id
					left join displacementsensor1 C on B.id = C.device_id
				where projects.id = ?
				group by A.id  
			) maxId
			left join displacementsensor1 on maxId.maxId = displacementsensor1.id
		where 
			maxId.maxId is not NULL
    ) dataInf on baseInf.deviceId = dataInf.deviceId
where 
	TIMESTAMPDIFF(HOUR, dataInf.gps_time, now()) >=2;', [$project_id,$project_id,$project_id]);
            }
            if((int)($request->recentUpdate) === 2){
                $result = DB::select('select 
	baseInf.projectId, baseInf.projectName, baseInf.poiId
	, concat(baseInf.poiName,"-",baseInf.poiLocation) as poiName
	, baseInf.device_type, baseInf.deviceTypeName, baseInf.deviceNum
	, baseInf.mac, baseInf.deviceId, baseInf.online
    ,dataInf.maxId, dataInf.gps_time 
	,dataInf.sensorId ,dataInf.data
from 
	(
		select 
			SummaryPoi.projectId, SummaryPoi.projectName, SummaryPoi.poiId, SummaryPoi.poiName, SummaryPoi.poiLocation
			, SummaryPoi.device_type, SummaryPoi.deviceTypeName, SummaryPoi.deviceNum
			, SummaryDev.deviceId, SummaryDev.mac, SummaryDev.online
		from
			(
				select 
					A.id as projectId, A.name as projectName, B.id as poiId, B.name as poiName, B.location as poiLocation,
					replace(Left(C.mac,4),\'0\',\'\') as device_type,
					(case replace(Left(C.mac,4),\'0\',\'\') 
						when 1 then \'天线\'
						when 2 then \'其他\'
						when 3 then \'GNSS\'
						when 4 then \'雨量\'
						when 5 then \'裂缝\'
						when 6 then \'土壤含水\'
					else \'其他\' end) as deviceTypeName,
					count(*) as deviceNum
				from 
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by projectId, projectName, poiId, poiName,  
					device_type,  deviceTypeName
			) SummaryPoi
			left join 
			(
				select 
					B.id as poiId, C.id as deviceId, 
					replace(Left(C.mac,4),\'0\',\'\') as device_type, 
					C.mac, C.online
				from
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					#暂不统计兰州项目（锚索计）
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by A.id, B.id, C.id
			) SummaryDev
			on SummaryPoi.poiId = SummaryDev.poiId and SummaryPoi.device_type = SummaryDev.device_type
    ) baseInf
    left join
    (
		select 
			maxId.deviceId, maxId.maxId, displacementsensor1.gps_time, 
			displacementsensor1.device_id as sensorId, 
			displacementsensor1.displacement as data
		from 
			(
				select 
					A.id deviceId, max(C.id) maxId 
				from 
					projects 
					left join pois on projects.id = pois.project_id
					left join devices A on pois.id = A.poi_id
					left join sensors B on A.id = B.device_id
					left join displacementsensor1 C on B.id = C.device_id
				where projects.id = ?
				group by A.id  
			) maxId
			left join displacementsensor1 on maxId.maxId = displacementsensor1.id
		where 
			maxId.maxId is not NULL
    ) dataInf on baseInf.deviceId = dataInf.deviceId
where 
	TIMESTAMPDIFF(HOUR, dataInf.gps_time, now()) < 2;', [$project_id,$project_id,$project_id]);
            }
        }else{
            $result = DB::select('select 
	baseInf.projectId, baseInf.projectName, baseInf.poiId
	, concat(baseInf.poiName,"-",baseInf.poiLocation) as poiName
	, baseInf.device_type, baseInf.deviceTypeName, baseInf.deviceNum
	, baseInf.mac, baseInf.deviceId, baseInf.online
    ,dataInf.maxId, dataInf.gps_time 
	,dataInf.sensorId ,dataInf.data
from 
	(
		select 
			SummaryPoi.projectId, SummaryPoi.projectName, SummaryPoi.poiId, SummaryPoi.poiName, SummaryPoi.poiLocation
			, SummaryPoi.device_type, SummaryPoi.deviceTypeName, SummaryPoi.deviceNum
			, SummaryDev.deviceId, SummaryDev.mac, SummaryDev.online
		from
			(
				select 
					A.id as projectId, A.name as projectName, B.id as poiId, B.name as poiName, B.location as poiLocation,
					replace(Left(C.mac,4),\'0\',\'\') as device_type,
					(case replace(Left(C.mac,4),\'0\',\'\') 
						when 1 then \'天线\'
						when 2 then \'其他\'
						when 3 then \'GNSS\'
						when 4 then \'雨量\'
						when 5 then \'裂缝\'
						when 6 then \'土壤含水\'
					else \'其他\' end) as deviceTypeName,
					count(*) as deviceNum
				from 
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by projectId, projectName, poiId, poiName,  
					device_type,  deviceTypeName
			) SummaryPoi
			left join 
			(
				select 
					B.id as poiId, C.id as deviceId, 
					replace(Left(C.mac,4),\'0\',\'\') as device_type, 
					C.mac, C.online
				from
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					#暂不统计兰州项目（锚索计）
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by A.id, B.id, C.id
			) SummaryDev
			on SummaryPoi.poiId = SummaryDev.poiId and SummaryPoi.device_type = SummaryDev.device_type
    ) baseInf
    left join
    (
		select 
			maxId.deviceId, maxId.maxId, displacementsensor1.gps_time, 
			displacementsensor1.device_id as sensorId, 
			displacementsensor1.displacement as data
		from 
			(
				select 
					A.id deviceId, max(C.id) maxId 
				from 
					projects 
					left join pois on projects.id = pois.project_id
					left join devices A on pois.id = A.poi_id
					left join sensors B on A.id = B.device_id
					left join displacementsensor1 C on B.id = C.device_id
				where projects.id = ?
				group by A.id  
			) maxId
			left join displacementsensor1 on maxId.maxId = displacementsensor1.id
		where 
			maxId.maxId is not NULL
    ) dataInf on baseInf.deviceId = dataInf.deviceId
where 
	1=1;', [$project_id,$project_id,$project_id]);
        }
        return response()->json($result);
    }

    public function getOtherThingsByProjectId(Request $request){
        $this->validate($request, [
            'projectId' => 'required',
        ]);
        $result = DB::select('select 
	sensors.device_id as deviceId,
    alarmsSensor.time,
    alarmsSensor.content
from 
	sensors
    left join alarmsSensor on sensors.id = alarmsSensor.sensor_id
    right join 
	(
		select 
			max(D.id) recentAlarmId
		from 
			pois A
			left join devices B on A.id = B.poi_id
			left join sensors C on B.id = C.device_id
			left join alarmsSensor D on C.id = D.sensor_id
		where 
			A.project_id = ?
			and A.deleted_at is NULL
			and D.sensor_id is not NULL
			and hour(timediff(now(),D.time)) <=24
		group by C.id
	) recentAlarm
    on alarmsSensor.id = recentAlarm.recentAlarmId',[$request->projectId]);
        return response()->json($result);
    }

    public function getSomeThingByDeviceId(Request $request){
        $this->validate($request, [
            'deviceId' => 'required',
        ]);
        $result = DB::select('select 
	A.id pojectId, A.name projectName, 
    B.id poiId, B.name poiName, B.location,
    C.id deviceId, C.name deviceName, C.mac, C.online
from
	projects A 
    left join pois B on A.id = B.project_id
    left join devices C on B.id = C.poi_id
where
	C.id = ?
    and A.deleted_at is NULL
    and B.deleted_at is NULL
    and C.deleted_at is NULL',
    [$request->deviceId]);
        return response()->json($result);
    }

    public function getOtherThingsByDeviceId(Request $request){
        $this->validate($request, [
            'deviceId' => 'required',
            'pageNumber'=> 'required',
            'pageSize'=> 'required'
        ]);

        $sensor_count = DB::select('select 
    count(*) as count
from 
    devices A 
    left join sensors B on A.id = B.device_id
where 
    A.deleted_at is NULL 
    and B.deleted_at is NULL
    and A.id = ?',[$request->deviceId]);
        $offset = $sensor_count[0]->count*($request->pageNumber-1)*$request->pageSize;
        $take = $sensor_count[0]->count*$request->pageSize;

        $total_count = DB::select('select 
	count(*) as count
from 
	devices A
    left join sensors B on A.id = B.device_id
    left join displacementsensor1 C on B.id = C.device_id
where 
	A.id = ?
    and A.deleted_at is NULL
    and B.deleted_at is NULL
order by  C.id desc',
            [$request->deviceId]);

        $result = DB::select('select 
	A.id as deviceId, A.mac as deviceMac, A.name as deviceName
    , B.id as sensorId, B.name as sensorName
    , C.id as dataId, C.gps_time, C.displacement as data
from 
	devices A
    left join sensors B on A.id = B.device_id
    left join displacementsensor1 C on B.id = C.device_id
where 
	A.id = ?
    and A.deleted_at is NULL
    and B.deleted_at is NULL
order by  C.id desc
limit  ?,? ',
            [$request->deviceId,$offset,$take]);
        return response()->json(['count'=>($total_count[0]->count)/$sensor_count[0]->count,'result'=>$result]);
    }

    public function getSensorInfoByDeviceId(Request $request)
    {
        $this->validate($request, [
            'deviceId' => 'required',
            'pageNumber'=> 'required',
            'pageSize'=> 'required'
        ]);

        $device_id = $request->deviceId;

        $sensors_count = DB::table('sensors')
            ->join('devices', 'devices.id', '=', 'sensors.device_id')
            ->select('sensors.name','sensors.id')
            ->where([
                ['devices.id', '=', $device_id],
                ['devices.deleted_at', '=', NULL]
            ])
            ->count();
        $sensors = DB::table('sensors')
            ->join('devices', 'devices.id', '=', 'sensors.device_id')
            ->select('sensors.name','sensors.id')
            ->where([
                ['devices.id', '=', $device_id],
                ['devices.deleted_at', '=', NULL]
            ])
            ->get();
        foreach ($sensors as $sensor){
            $sensorsArray[] =$sensor->name;
        }

        foreach ($sensors as $sensor){
            $sensorsIdArrays[] =$sensor->id;
        }
        $sensorsArray[] = '时间';
        $sql_where = array();
        $sql_part_project_id = ['devices.id', '=', $device_id];
        $sql_part_project_not_null = ['devices.deleted_at', '=', NULL];
        array_push($sql_where,$sql_part_project_id);
        array_push($sql_where,$sql_part_project_not_null);
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


        $datas = DB::table('sensors')
            ->join('devices', 'devices.id', '=', 'sensors.device_id')
            ->join('displacementsensor1', 'sensors.id', '=', 'displacementsensor1.device_id')
            ->select('displacementsensor1.*', 'sensors.name')
            ->where($sql_where)
            ->groupby('displacementsensor1.gps_time', 'displacementsensor1.device_id')
            ->orderBy('displacementsensor1.gps_time', 'asc')
            ->skip($sensors_count*($request->pageNumber-1)*$request->pageSize)
            ->take($sensors_count*$request->pageSize)
            ->get();
        $temp_time = null;
        foreach ($datas as $data) {
            $result[$data->gps_time][] = $data;
        }
        if (!isset($result)) {
            $result = json_encode((object)null);
        }

        foreach ($result as $resulKey=>$resulValue){
            foreach ($sensorsIdArrays as $sensorsIdArray){
                $tempIdToDatas[$sensorsIdArray] = NULL;
            }
            foreach ($resulValue as $resulValu){
                $tempIdToDatas[$resulValu->device_id] = $resulValu->displacement;
            }
            foreach ($tempIdToDatas as $tempIdToDataKey => $tempIdToDataValue ){
                $result_final_part[] =  $tempIdToDataValue;
            }
            $result_final_part[] = $resulKey;
            $result_final_full[] =$result_final_part;
            unset($result_final_part);
        }

        return response()->json(['sensorsArray' =>$sensorsArray, 'data' => $result_final_full]);
    }

    public function searchPoiReturnCorrectDevice(Request $request)
    {
        $keyWord = $request->keyWord;

        if($request->has('projectId')){
            $pois = Poi::search(urldecode($keyWord))
                ->where([['pois.project_id','=',$request->projectId],['pois.deleted_at', '=', NULL]])
                ->get();
            return response()->json($pois);
        }elseif($request->has('poiId')){
            $devices =  $devices = Device::search($keyWord)
                ->where([['devices.deleted_at', '=', NULL],['devices.poi_id', '=',$request->poiId]])
                ->get();
            return response()->json($devices);
        }else{
            return response()->json("参数错误");
        }
    }

    public function getTotalDataCount(Request $request){

        if($request->has('id')){
            $new_final_ids = DB::table('displacementsensor1')
                    ->select('displacementsensor1.id')
                    ->orderBy('id', 'desc')
                    ->limit(1)
                    ->get();
            foreach($new_final_ids as $new_final_id){
                $new_end_id = $new_final_id->id;
            }
            $add = $new_end_id - $request->id;
            return response()->json(['id'=>$new_end_id,'add'=>$add]);
        }
        
        $today_time_stamp = time();
        $today = getdate($today_time_stamp);
        $today_year = strval($today['year']);
        $today_mon = strval($today['mon']);
        $today_day = strval($today['mday']);

        $start = date_create($today_year.'-'.$today_mon.'-'.$today_day.' '.'00:00:00');
        $end = date_create($today_year.'-'.$today_mon.'-'.$today_day.' '.'23:59:59');

        $count_total = DB::table('displacementsensor1')
                    ->count();
        
        $count_today = DB::table('displacementsensor1')
                    ->where([['displacementsensor1.gps_time', '>', $start],
                            ['displacementsensor1.gps_time', '<', $end]])
                    ->count();

        $final_ids = DB::table('displacementsensor1')
                    ->select('displacementsensor1.id')
                    ->orderBy('id', 'desc')
                    ->limit(1)
                    ->get();
        foreach($final_ids as $final_id){
            $end_id = $final_id->id;
        }
        return response()->json(['total'=>$count_total, 'today'=>$count_today, 'id'=>$end_id]);
    }

    public function getDeviceOnlineCount(){

        $online_counts = DB::select(
            'SELECT
            (case devices.type
            WHEN 0 THEN \'未知\'
            WHEN 15 THEN \'锚索计\'
            WHEN 100 THEN \'gnss基准站\' 
            WHEN 1 THEN	\'天线\' 
            WHEN 3 THEN	\'gnss监测站\' 
            WHEN 4 THEN	\'雨量计\' 
            WHEN 5 THEN	\'裂缝计\' 
            WHEN 6 THEN	\'土壤含水\' 
            WHEN 8 THEN	\'电源控制器\'
            ELSE \'其他\' end) as deviceTypeName,
            COUNT( * ) as count 
        FROM
            devices
            RIGHT JOIN pois ON devices.poi_id = pois.id
            RIGHT JOIN projects ON pois.project_id = projects.id 
        WHERE
            projects.id = 12 
            AND `online` = 1 
        GROUP BY
            devices.type;'
        );

        $offline_counts = DB::select(
            'SELECT
            (case devices.type
            WHEN 0 THEN \'未知\'
            WHEN 15 THEN \'锚索计\'
            WHEN 100 THEN \'gnss基准站\' 
            WHEN 1 THEN	\'天线\' 
            WHEN 3 THEN	\'gnss监测站\' 
            WHEN 4 THEN	\'雨量计\' 
            WHEN 5 THEN	\'裂缝计\' 
            WHEN 6 THEN	\'土壤含水\' 
            WHEN 8 THEN	\'电源控制器\'
            ELSE \'其他\' end) as deviceTypeName,
            COUNT( * ) as count
        FROM
            devices
            RIGHT JOIN pois ON devices.poi_id = pois.id
            RIGHT JOIN projects ON pois.project_id = projects.id 
        WHERE
            projects.id = 12 
            AND `online` = 0 
        GROUP BY
            devices.type;'
        );
        
        foreach($online_counts as $online_count){   
            $results[$online_count->deviceTypeName]['online'] = $online_count->count;
        }

        foreach($offline_counts as $offline_count){
            $results[$offline_count->deviceTypeName]['offline'] = $offline_count->count;
        }
        
        foreach($results as $k=>$v){

            $temp['name'] = $k;
            $online_flag = 0;
            $offline_flag = 0;

            foreach($v as $kk=>$vv){
                if($kk === 'online')
                    $online_flag = 1;
                if($kk === 'offline')
                    $offline_flag = 1;  

                $temp[$kk] = $vv;
            }

            if($online_flag == 0){
                $temp['online'] = 0;
            }
            if($offline_flag == 0){
                $temp['offline'] = 0;
            }

            $final_return[] =$temp;
            unset($temp);
        }
        
        return response()->json($final_return);
    }       

    public function test(Request $request )
    {
        /* $str = $request->str;
         $str = substr($str,0,3);
         return response()->json($str);*/

        /* $device_mac = $request->id2;
         $device_mac = $device_mac / 65536;

         $device_mac = base_convert($device_mac, 10, 16);
         //return response()->json($device_mac);
         $device_mac = sprintf("%012s", $device_mac);
         return response()->json($device_mac);*/

        $this->validate($request, [
            'projectId' => 'required',
        ]);
        $project_name = '';
        $project_id = $request->projectId;
        if($request->has('online') && $request->has('recentUpdate')){
           if($request->recentUpdate === 0){
               $result = DB::select('select 
	baseInf.projectId, baseInf.projectName, baseInf.poiId
	, concat(baseInf.poiName,"-",baseInf.poiLocation) as poiName
	, baseInf.device_type, baseInf.deviceTypeName, baseInf.deviceNum
	, baseInf.mac, baseInf.deviceId, baseInf.online
    ,dataInf.maxId, dataInf.gps_time 
	,dataInf.sensorId ,dataInf.data
from 
	(
		select 
			SummaryPoi.projectId, SummaryPoi.projectName, SummaryPoi.poiId, SummaryPoi.poiName, SummaryPoi.poiLocation
			, SummaryPoi.device_type, SummaryPoi.deviceTypeName, SummaryPoi.deviceNum
			, SummaryDev.deviceId, SummaryDev.mac, SummaryDev.online
		from
			(
				select 
					A.id as projectId, A.name as projectName, B.id as poiId, B.name as poiName, B.location as poiLocation,
					replace(Left(C.mac,4),\'0\',\'\') as device_type,
					(case replace(Left(C.mac,4),\'0\',\'\') 
						when 1 then \'天线\'
						when 2 then \'其他\'
						when 3 then \'GNSS\'
						when 4 then \'雨量\'
						when 5 then \'裂缝\'
						when 6 then \'土壤含水\'
					else \'其他\' end) as deviceTypeName,
					count(*) as deviceNum
				from 
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by projectId, projectName, poiId, poiName,  
					device_type,  deviceTypeName
			) SummaryPoi
			left join 
			(
				select 
					B.id as poiId, C.id as deviceId, 
					replace(Left(C.mac,4),\'0\',\'\') as device_type, 
					C.mac, C.online
				from
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					#暂不统计兰州项目（锚索计）
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by A.id, B.id, C.id
			) SummaryDev
			on SummaryPoi.poiId = SummaryDev.poiId and SummaryPoi.device_type = SummaryDev.device_type
    ) baseInf
    left join
    (
		select 
			maxId.deviceId, maxId.maxId, displacementsensor1.gps_time, 
			displacementsensor1.device_id as sensorId, 
			displacementsensor1.displacement as data
		from 
			(
				select 
					A.id deviceId, max(C.id) maxId 
				from 
					projects 
					left join pois on projects.id = pois.project_id
					left join devices A on pois.id = A.poi_id
					left join sensors B on A.id = B.device_id
					left join displacementsensor1 C on B.id = C.device_id
				where projects.id = ?
				group by A.id  
			) maxId
			left join displacementsensor1 on maxId.maxId = displacementsensor1.id
		where 
			maxId.maxId is not NULL
    ) dataInf on baseInf.deviceId = dataInf.deviceId
where 
	baseInf.online = ?
	and dataInf.gps_time is NULL;', [$project_id,$project_id,$project_id,$request->online]);
           }
           if($request->recentUpdate === 1){
               $result = DB::select('select 
	baseInf.projectId, baseInf.projectName, baseInf.poiId
	, concat(baseInf.poiName,"-",baseInf.poiLocation) as poiName
	, baseInf.device_type, baseInf.deviceTypeName, baseInf.deviceNum
	, baseInf.mac, baseInf.deviceId, baseInf.online
    ,dataInf.maxId, dataInf.gps_time 
	,dataInf.sensorId ,dataInf.data
from 
	(
		select 
			SummaryPoi.projectId, SummaryPoi.projectName, SummaryPoi.poiId, SummaryPoi.poiName, SummaryPoi.poiLocation
			, SummaryPoi.device_type, SummaryPoi.deviceTypeName, SummaryPoi.deviceNum
			, SummaryDev.deviceId, SummaryDev.mac, SummaryDev.online
		from
			(
				select 
					A.id as projectId, A.name as projectName, B.id as poiId, B.name as poiName, B.location as poiLocation,
					replace(Left(C.mac,4),\'0\',\'\') as device_type,
					(case replace(Left(C.mac,4),\'0\',\'\') 
						when 1 then \'天线\'
						when 2 then \'其他\'
						when 3 then \'GNSS\'
						when 4 then \'雨量\'
						when 5 then \'裂缝\'
						when 6 then \'土壤含水\'
					else \'其他\' end) as deviceTypeName,
					count(*) as deviceNum
				from 
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by projectId, projectName, poiId, poiName,  
					device_type,  deviceTypeName
			) SummaryPoi
			left join 
			(
				select 
					B.id as poiId, C.id as deviceId, 
					replace(Left(C.mac,4),\'0\',\'\') as device_type, 
					C.mac, C.online
				from
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					#暂不统计兰州项目（锚索计）
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by A.id, B.id, C.id
			) SummaryDev
			on SummaryPoi.poiId = SummaryDev.poiId and SummaryPoi.device_type = SummaryDev.device_type
    ) baseInf
    left join
    (
		select 
			maxId.deviceId, maxId.maxId, displacementsensor1.gps_time, 
			displacementsensor1.device_id as sensorId, 
			displacementsensor1.displacement as data
		from 
			(
				select 
					A.id deviceId, max(C.id) maxId 
				from 
					projects 
					left join pois on projects.id = pois.project_id
					left join devices A on pois.id = A.poi_id
					left join sensors B on A.id = B.device_id
					left join displacementsensor1 C on B.id = C.device_id
				where projects.id = ?
				group by A.id  
			) maxId
			left join displacementsensor1 on maxId.maxId = displacementsensor1.id
		where 
			maxId.maxId is not NULL
    ) dataInf on baseInf.deviceId = dataInf.deviceId
where 
	baseInf.online = ?
	and TIMESTAMPDIFF(HOUR, dataInf.gps_time, now()) >=2;', [$project_id,$project_id,$project_id,$request->online]);
           }
           if($request->recentUpdate === 2){
               $result = DB::select('select 
	baseInf.projectId, baseInf.projectName, baseInf.poiId
	, concat(baseInf.poiName,"-",baseInf.poiLocation) as poiName
	, baseInf.device_type, baseInf.deviceTypeName, baseInf.deviceNum
	, baseInf.mac, baseInf.deviceId, baseInf.online
    ,dataInf.maxId, dataInf.gps_time 
	,dataInf.sensorId ,dataInf.data
from 
	(
		select 
			SummaryPoi.projectId, SummaryPoi.projectName, SummaryPoi.poiId, SummaryPoi.poiName, SummaryPoi.poiLocation
			, SummaryPoi.device_type, SummaryPoi.deviceTypeName, SummaryPoi.deviceNum
			, SummaryDev.deviceId, SummaryDev.mac, SummaryDev.online
		from
			(
				select 
					A.id as projectId, A.name as projectName, B.id as poiId, B.name as poiName, B.location as poiLocation,
					replace(Left(C.mac,4),\'0\',\'\') as device_type,
					(case replace(Left(C.mac,4),\'0\',\'\') 
						when 1 then \'天线\'
						when 2 then \'其他\'
						when 3 then \'GNSS\'
						when 4 then \'雨量\'
						when 5 then \'裂缝\'
						when 6 then \'土壤含水\'
					else \'其他\' end) as deviceTypeName,
					count(*) as deviceNum
				from 
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by projectId, projectName, poiId, poiName,  
					device_type,  deviceTypeName
			) SummaryPoi
			left join 
			(
				select 
					B.id as poiId, C.id as deviceId, 
					replace(Left(C.mac,4),\'0\',\'\') as device_type, 
					C.mac, C.online
				from
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					#暂不统计兰州项目（锚索计）
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by A.id, B.id, C.id
			) SummaryDev
			on SummaryPoi.poiId = SummaryDev.poiId and SummaryPoi.device_type = SummaryDev.device_type
    ) baseInf
    left join
    (
		select 
			maxId.deviceId, maxId.maxId, displacementsensor1.gps_time, 
			displacementsensor1.device_id as sensorId, 
			displacementsensor1.displacement as data
		from 
			(
				select 
					A.id deviceId, max(C.id) maxId 
				from 
					projects 
					left join pois on projects.id = pois.project_id
					left join devices A on pois.id = A.poi_id
					left join sensors B on A.id = B.device_id
					left join displacementsensor1 C on B.id = C.device_id
				where projects.id = ?
				group by A.id  
			) maxId
			left join displacementsensor1 on maxId.maxId = displacementsensor1.id
		where 
			maxId.maxId is not NULL
    ) dataInf on baseInf.deviceId = dataInf.deviceId
where 
	baseInf.online = ?
	and TIMESTAMPDIFF(HOUR, dataInf.gps_time, now()) < 2;', [$project_id,$project_id,$project_id,$request->online]);
           }
        }else if($request->has('online')){
            $result = DB::select('select 
	baseInf.projectId, baseInf.projectName, baseInf.poiId
	, concat(baseInf.poiName,"-",baseInf.poiLocation) as poiName
	, baseInf.device_type, baseInf.deviceTypeName, baseInf.deviceNum
	, baseInf.mac, baseInf.deviceId, baseInf.online
    ,dataInf.maxId, dataInf.gps_time 
	,dataInf.sensorId ,dataInf.data
from 
	(
		select 
			SummaryPoi.projectId, SummaryPoi.projectName, SummaryPoi.poiId, SummaryPoi.poiName, SummaryPoi.poiLocation
			, SummaryPoi.device_type, SummaryPoi.deviceTypeName, SummaryPoi.deviceNum
			, SummaryDev.deviceId, SummaryDev.mac, SummaryDev.online
		from
			(
				select 
					A.id as projectId, A.name as projectName, B.id as poiId, B.name as poiName, B.location as poiLocation,
					replace(Left(C.mac,4),\'0\',\'\') as device_type,
					(case replace(Left(C.mac,4),\'0\',\'\') 
						when 1 then \'天线\'
						when 2 then \'其他\'
						when 3 then \'GNSS\'
						when 4 then \'雨量\'
						when 5 then \'裂缝\'
						when 6 then \'土壤含水\'
					else \'其他\' end) as deviceTypeName,
					count(*) as deviceNum
				from 
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by projectId, projectName, poiId, poiName,  
					device_type,  deviceTypeName
			) SummaryPoi
			left join 
			(
				select 
					B.id as poiId, C.id as deviceId, 
					replace(Left(C.mac,4),\'0\',\'\') as device_type, 
					C.mac, C.online
				from
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					#暂不统计兰州项目（锚索计）
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by A.id, B.id, C.id
			) SummaryDev
			on SummaryPoi.poiId = SummaryDev.poiId and SummaryPoi.device_type = SummaryDev.device_type
    ) baseInf
    left join
    (
		select 
			maxId.deviceId, maxId.maxId, displacementsensor1.gps_time, 
			displacementsensor1.device_id as sensorId, 
			displacementsensor1.displacement as data
		from 
			(
				select 
					A.id deviceId, max(C.id) maxId 
				from 
					projects 
					left join pois on projects.id = pois.project_id
					left join devices A on pois.id = A.poi_id
					left join sensors B on A.id = B.device_id
					left join displacementsensor1 C on B.id = C.device_id
				where projects.id = ?
				group by A.id  
			) maxId
			left join displacementsensor1 on maxId.maxId = displacementsensor1.id
		where 
			maxId.maxId is not NULL
    ) dataInf on baseInf.deviceId = dataInf.deviceId
where 
	baseInf.online = ?;', [$project_id,$project_id,$project_id,$request->online]);
        }else if($request->has('recentUpdate')){
            if($request->recentUpdate === 0){
                $result = DB::select('select 
	baseInf.projectId, baseInf.projectName, baseInf.poiId
	, concat(baseInf.poiName,"-",baseInf.poiLocation) as poiName
	, baseInf.device_type, baseInf.deviceTypeName, baseInf.deviceNum
	, baseInf.mac, baseInf.deviceId, baseInf.online
    ,dataInf.maxId, dataInf.gps_time 
	,dataInf.sensorId ,dataInf.data
from 
	(
		select 
			SummaryPoi.projectId, SummaryPoi.projectName, SummaryPoi.poiId, SummaryPoi.poiName, SummaryPoi.poiLocation
			, SummaryPoi.device_type, SummaryPoi.deviceTypeName, SummaryPoi.deviceNum
			, SummaryDev.deviceId, SummaryDev.mac, SummaryDev.online
		from
			(
				select 
					A.id as projectId, A.name as projectName, B.id as poiId, B.name as poiName, B.location as poiLocation,
					replace(Left(C.mac,4),\'0\',\'\') as device_type,
					(case replace(Left(C.mac,4),\'0\',\'\') 
						when 1 then \'天线\'
						when 2 then \'其他\'
						when 3 then \'GNSS\'
						when 4 then \'雨量\'
						when 5 then \'裂缝\'
						when 6 then \'土壤含水\'
					else \'其他\' end) as deviceTypeName,
					count(*) as deviceNum
				from 
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by projectId, projectName, poiId, poiName,  
					device_type,  deviceTypeName
			) SummaryPoi
			left join 
			(
				select 
					B.id as poiId, C.id as deviceId, 
					replace(Left(C.mac,4),\'0\',\'\') as device_type, 
					C.mac, C.online
				from
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					#暂不统计兰州项目（锚索计）
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by A.id, B.id, C.id
			) SummaryDev
			on SummaryPoi.poiId = SummaryDev.poiId and SummaryPoi.device_type = SummaryDev.device_type
    ) baseInf
    left join
    (
		select 
			maxId.deviceId, maxId.maxId, displacementsensor1.gps_time, 
			displacementsensor1.device_id as sensorId, 
			displacementsensor1.displacement as data
		from 
			(
				select 
					A.id deviceId, max(C.id) maxId 
				from 
					projects 
					left join pois on projects.id = pois.project_id
					left join devices A on pois.id = A.poi_id
					left join sensors B on A.id = B.device_id
					left join displacementsensor1 C on B.id = C.device_id
				where projects.id = ?
				group by A.id  
			) maxId
			left join displacementsensor1 on maxId.maxId = displacementsensor1.id
		where 
			maxId.maxId is not NULL
    ) dataInf on baseInf.deviceId = dataInf.deviceId
where 
	and dataInf.gps_time is NULL;', [$project_id,$project_id,$project_id]);
            }
            if($request->recentUpdate === 1){
                $result = DB::select('select 
	baseInf.projectId, baseInf.projectName, baseInf.poiId
	, concat(baseInf.poiName,"-",baseInf.poiLocation) as poiName
	, baseInf.device_type, baseInf.deviceTypeName, baseInf.deviceNum
	, baseInf.mac, baseInf.deviceId, baseInf.online
    ,dataInf.maxId, dataInf.gps_time 
	,dataInf.sensorId ,dataInf.data
from 
	(
		select 
			SummaryPoi.projectId, SummaryPoi.projectName, SummaryPoi.poiId, SummaryPoi.poiName, SummaryPoi.poiLocation
			, SummaryPoi.device_type, SummaryPoi.deviceTypeName, SummaryPoi.deviceNum
			, SummaryDev.deviceId, SummaryDev.mac, SummaryDev.online
		from
			(
				select 
					A.id as projectId, A.name as projectName, B.id as poiId, B.name as poiName, B.location as poiLocation,
					replace(Left(C.mac,4),\'0\',\'\') as device_type,
					(case replace(Left(C.mac,4),\'0\',\'\') 
						when 1 then \'天线\'
						when 2 then \'其他\'
						when 3 then \'GNSS\'
						when 4 then \'雨量\'
						when 5 then \'裂缝\'
						when 6 then \'土壤含水\'
					else \'其他\' end) as deviceTypeName,
					count(*) as deviceNum
				from 
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by projectId, projectName, poiId, poiName,  
					device_type,  deviceTypeName
			) SummaryPoi
			left join 
			(
				select 
					B.id as poiId, C.id as deviceId, 
					replace(Left(C.mac,4),\'0\',\'\') as device_type, 
					C.mac, C.online
				from
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					#暂不统计兰州项目（锚索计）
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by A.id, B.id, C.id
			) SummaryDev
			on SummaryPoi.poiId = SummaryDev.poiId and SummaryPoi.device_type = SummaryDev.device_type
    ) baseInf
    left join
    (
		select 
			maxId.deviceId, maxId.maxId, displacementsensor1.gps_time, 
			displacementsensor1.device_id as sensorId, 
			displacementsensor1.displacement as data
		from 
			(
				select 
					A.id deviceId, max(C.id) maxId 
				from 
					projects 
					left join pois on projects.id = pois.project_id
					left join devices A on pois.id = A.poi_id
					left join sensors B on A.id = B.device_id
					left join displacementsensor1 C on B.id = C.device_id
				where projects.id = ?
				group by A.id  
			) maxId
			left join displacementsensor1 on maxId.maxId = displacementsensor1.id
		where 
			maxId.maxId is not NULL
    ) dataInf on baseInf.deviceId = dataInf.deviceId
where 
	and TIMESTAMPDIFF(HOUR, dataInf.gps_time, now()) >=2;', [$project_id,$project_id,$project_id]);
            }
            if($request->recentUpdate === 2){
                $result = DB::select('select 
	baseInf.projectId, baseInf.projectName, baseInf.poiId
	, concat(baseInf.poiName,"-",baseInf.poiLocation) as poiName
	, baseInf.device_type, baseInf.deviceTypeName, baseInf.deviceNum
	, baseInf.mac, baseInf.deviceId, baseInf.online
    ,dataInf.maxId, dataInf.gps_time 
	,dataInf.sensorId ,dataInf.data
from 
	(
		select 
			SummaryPoi.projectId, SummaryPoi.projectName, SummaryPoi.poiId, SummaryPoi.poiName, SummaryPoi.poiLocation
			, SummaryPoi.device_type, SummaryPoi.deviceTypeName, SummaryPoi.deviceNum
			, SummaryDev.deviceId, SummaryDev.mac, SummaryDev.online
		from
			(
				select 
					A.id as projectId, A.name as projectName, B.id as poiId, B.name as poiName, B.location as poiLocation,
					replace(Left(C.mac,4),\'0\',\'\') as device_type,
					(case replace(Left(C.mac,4),\'0\',\'\') 
						when 1 then \'天线\'
						when 2 then \'其他\'
						when 3 then \'GNSS\'
						when 4 then \'雨量\'
						when 5 then \'裂缝\'
						when 6 then \'土壤含水\'
					else \'其他\' end) as deviceTypeName,
					count(*) as deviceNum
				from 
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by projectId, projectName, poiId, poiName,  
					device_type,  deviceTypeName
			) SummaryPoi
			left join 
			(
				select 
					B.id as poiId, C.id as deviceId, 
					replace(Left(C.mac,4),\'0\',\'\') as device_type, 
					C.mac, C.online
				from
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					#暂不统计兰州项目（锚索计）
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by A.id, B.id, C.id
			) SummaryDev
			on SummaryPoi.poiId = SummaryDev.poiId and SummaryPoi.device_type = SummaryDev.device_type
    ) baseInf
    left join
    (
		select 
			maxId.deviceId, maxId.maxId, displacementsensor1.gps_time, 
			displacementsensor1.device_id as sensorId, 
			displacementsensor1.displacement as data
		from 
			(
				select 
					A.id deviceId, max(C.id) maxId 
				from 
					projects 
					left join pois on projects.id = pois.project_id
					left join devices A on pois.id = A.poi_id
					left join sensors B on A.id = B.device_id
					left join displacementsensor1 C on B.id = C.device_id
				where projects.id = ?
				group by A.id  
			) maxId
			left join displacementsensor1 on maxId.maxId = displacementsensor1.id
		where 
			maxId.maxId is not NULL
    ) dataInf on baseInf.deviceId = dataInf.deviceId
where 
	and TIMESTAMPDIFF(HOUR, dataInf.gps_time, now()) < 2;', [$project_id,$project_id,$project_id]);
            }
        }else{
            $result = DB::select('select 
	baseInf.projectId, baseInf.projectName, baseInf.poiId
	, concat(baseInf.poiName,"-",baseInf.poiLocation) as poiName
	, baseInf.device_type, baseInf.deviceTypeName, baseInf.deviceNum
	, baseInf.mac, baseInf.deviceId, baseInf.online
    ,dataInf.maxId, dataInf.gps_time 
	,dataInf.sensorId ,dataInf.data
from 
	(
		select 
			SummaryPoi.projectId, SummaryPoi.projectName, SummaryPoi.poiId, SummaryPoi.poiName, SummaryPoi.poiLocation
			, SummaryPoi.device_type, SummaryPoi.deviceTypeName, SummaryPoi.deviceNum
			, SummaryDev.deviceId, SummaryDev.mac, SummaryDev.online
		from
			(
				select 
					A.id as projectId, A.name as projectName, B.id as poiId, B.name as poiName, B.location as poiLocation,
					replace(Left(C.mac,4),\'0\',\'\') as device_type,
					(case replace(Left(C.mac,4),\'0\',\'\') 
						when 1 then \'天线\'
						when 2 then \'其他\'
						when 3 then \'GNSS\'
						when 4 then \'雨量\'
						when 5 then \'裂缝\'
						when 6 then \'土壤含水\'
					else \'其他\' end) as deviceTypeName,
					count(*) as deviceNum
				from 
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by projectId, projectName, poiId, poiName,  
					device_type,  deviceTypeName
			) SummaryPoi
			left join 
			(
				select 
					B.id as poiId, C.id as deviceId, 
					replace(Left(C.mac,4),\'0\',\'\') as device_type, 
					C.mac, C.online
				from
					projects A 
					left join pois B on A.id = B.project_id
					left join devices C on B.id = C.poi_id
				where 
					A.deleted_at is NULL
					#暂不统计兰州项目（锚索计）
					and A.id = ?
					and B.id is not NULL
					and B.deleted_at is NULL 
					and C.id is not null
					and C.deleted_at is NULL
				group by A.id, B.id, C.id
			) SummaryDev
			on SummaryPoi.poiId = SummaryDev.poiId and SummaryPoi.device_type = SummaryDev.device_type
    ) baseInf
    left join
    (
		select 
			maxId.deviceId, maxId.maxId, displacementsensor1.gps_time, 
			displacementsensor1.device_id as sensorId, 
			displacementsensor1.displacement as data
		from 
			(
				select 
					A.id deviceId, max(C.id) maxId 
				from 
					projects 
					left join pois on projects.id = pois.project_id
					left join devices A on pois.id = A.poi_id
					left join sensors B on A.id = B.device_id
					left join displacementsensor1 C on B.id = C.device_id
				where projects.id = ?
				group by A.id  
			) maxId
			left join displacementsensor1 on maxId.maxId = displacementsensor1.id
		where 
			maxId.maxId is not NULL
    ) dataInf on baseInf.deviceId = dataInf.deviceId
where 
	1=1;', [$project_id,$project_id,$project_id]);
        }

        $A_S = 2;
        $B_S = 2;
        $C_S = 2;

        $A_T = -1;
        $B_T = -1;
        $C_T = -1;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1','项目名称');
        $sheet->setCellValue('B1', '监测点名称');
        $sheet->setCellValue('C1', '设备类型');
        $sheet->setCellValue('D1', '设备名称');
        $sheet->setCellValue('E1', '设备数量');
        $sheet->setCellValue('F1', '设备MAC');
        $sheet->setCellValue('G1', '设备ID');
        $sheet->setCellValue('H1', '是否在线');
        $sheet->setCellValue('I1', '时间');
        $sheet->setCellValue('J1', '传感器ID');
        $sheet->setCellValue('K1', '传感器数据');

        $row = 2;

        foreach ($result as $resul) {
            if($A_T === -1 && $B_T === -1 && $C_T === -1){
                $A_T = $resul->projectId;
                $B_T = $resul->poiId;
                $C_T = $resul->device_type;
            }
            $project_name = $resul->projectName;
            $sheet->setCellValue('A' . $row, $resul->projectName);
            $sheet->setCellValue('B' . $row, $resul->poiName);
            $sheet->setCellValue('C' . $row, $resul->device_type);
            $sheet->setCellValue('D' . $row, $resul->deviceTypeName);
            $sheet->setCellValue('E' . $row, $resul->deviceNum);
            $sheet->setCellValue('F' . $row, $resul->mac);
            $sheet->setCellValue('G' . $row, $resul->deviceId);
            $sheet->setCellValue('H' . $row, $resul->online);
            $sheet->setCellValue('I' . $row, $resul->gps_time);
            $sheet->setCellValue('J' . $row, $resul->sensorId);
            $sheet->setCellValue('K' . $row, $resul->data);
            if($A_T != $resul->projectId){
                $A_T_S = 'A'.(string)$A_S.':'.'A'.(string)($row-1);
                $sheet->mergeCells($A_T_S);

                $A_S_S = 'A'.(string)$A_S;
                $sheet->getStyle( $A_S_S)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);

                $A_S = $row;
                $A_T = $resul->projectId;
            }
            if($B_T != $resul->poiId){
                $B_T_S = 'B'.(string)$B_S.':'.'B'.(string)($row-1);
                $sheet->mergeCells($B_T_S);

                $B_S_S = 'B'.(string)$B_S;
                $sheet->getStyle( $B_S_S)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);


                $B_S = $row;
                $B_T = $resul->poiId;

                $C_T_S = 'C'.(string)$C_S.':'.'C'.(string)($row-1);
                $D_T_S = 'D'.(string)$C_S.':'.'D'.(string)($row-1);
                $E_T_S = 'E'.(string)$C_S.':'.'E'.(string)($row-1);
                $sheet->mergeCells($C_T_S);
                $sheet->mergeCells($D_T_S);
                $sheet->mergeCells($E_T_S);

                $C_S_S = 'C'.(string)$C_S;
                $D_S_S = 'D'.(string)$C_S;
                $E_S_S = 'E'.(string)$C_S;

                $sheet->getStyle( $C_S_S)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);
                $sheet->getStyle( $D_S_S)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);
                $sheet->getStyle( $E_S_S)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);


                $C_S = $row;
                $C_T = $resul->device_type;
            }
            if($C_T != $resul->device_type){
                $C_T_S = 'C'.(string)$C_S.':'.'C'.(string)($row-1);
                $D_T_S = 'D'.(string)$C_S.':'.'D'.(string)($row-1);
                $E_T_S = 'E'.(string)$C_S.':'.'E'.(string)($row-1);
                $sheet->mergeCells($C_T_S);
                $sheet->mergeCells($D_T_S);
                $sheet->mergeCells($E_T_S);

                $C_S_S = 'C'.(string)$C_S;
                $D_S_S = 'D'.(string)$C_S;
                $E_S_S = 'E'.(string)$C_S;

                $sheet->getStyle( $C_S_S)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);
                $sheet->getStyle( $D_S_S)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);
                $sheet->getStyle( $E_S_S)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);


                $C_S = $row;
                $C_T = $resul->device_type;
            }

            $row = $row + 1;
        }
        if($A_S<$row) {
            $A_T_S = 'A' . (string)$A_S . ':' . 'A' . (string)($row - 1);
            $sheet->mergeCells($A_T_S);
        }

        if($B_S<$row) {
            $B_T_S = 'B' . (string)$B_S . ':' . 'B' . (string)($row - 1);
            $sheet->mergeCells($B_T_S);
        }

        if($C_S<$row) {
            $C_T_S = 'C' . (string)$C_S . ':' . 'C' . (string)($row - 1);
            $D_T_S = 'D' . (string)$C_S . ':' . 'D' . (string)($row - 1);
            $E_T_S = 'E' . (string)$C_S . ':' . 'E' . (string)($row - 1);
            $sheet->mergeCells($C_T_S);
            $sheet->mergeCells($D_T_S);
            $sheet->mergeCells($E_T_S);
        }

        $A_S_S = 'A'.(string)$A_S;
        $sheet->getStyle( $A_S_S)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);
        $B_S_S = 'B'.(string)$B_S;
        $sheet->getStyle( $B_S_S)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);
        $C_S_S = 'C'.(string)$C_S;
        $D_S_S = 'D'.(string)$C_S;
        $E_S_S = 'E'.(string)$C_S;
        $sheet->getStyle( $C_S_S)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle( $D_S_S)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle( $E_S_S)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);

        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(10);
        $sheet->getColumnDimension('F')->setAutoSize(true);
        $sheet->getColumnDimension('G')->setWidth(10);
        $sheet->getColumnDimension('H')->setWidth(10);
        $sheet->getColumnDimension('I')->setAutoSize(true);
        $sheet->getColumnDimension('J')->setWidth(10);
        $sheet->getColumnDimension('K')->setWidth(15);

        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle('B1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle('C1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle('D1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle('E1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);

        for($I = 1;$I <= $row;$I++){
            $T_S = 'F'.$I;
            $sheet->getStyle( $T_S)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);
            $T_S = 'G'.$I;
            $sheet->getStyle( $T_S)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);
            $T_S = 'H'.$I;
            $sheet->getStyle( $T_S)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);
            $T_S = 'I'.$I;
            $sheet->getStyle( $T_S)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);
            $T_S = 'J'.$I;
            $sheet->getStyle( $T_S)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);
            $T_S = 'K'.$I;
            $sheet->getStyle( $T_S)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);
        }

        $filename = $request->projectId.'-'.$project_name.'.xlsx';

        $writer = new Xlsx($spreadsheet);
        $writer->save('file/' . $filename);

        return response()->download('file/' . $filename, $filename, ['Access-Control-Allow-Origin' => '*', 'Access-Control-Expose-Headers' => 'Content-Disposition'])->deleteFileAfterSend(true);;


    }

    public function test1(){
        $devices = Device::findOrFail(intval(945));
        $sensors = Sensor::where('device_id',intval(945))->get();
        foreach ($sensors as $sensor){
            $sensor->delete();
        }
        //return response()->json($poi);
        $devices->delete();
    }

    public function _delSensor($id){

        $alarms = Alarm::where('sensor_id',$id)
            ->get();
        foreach ($alarms as $alarm){
            $alarm->delete();
        }

        $alarmsSensors = AlarmsSensor::where('sensor_id',$id)
            ->get();
        foreach ($alarmsSensors as $alarmsSensor){
            $alarmsSensor>delete();
        }

        $displacementsensors = Displacementsensor1::where('device_id',$id)
            ->get();
        foreach ($displacementsensors as $displacementsensor){
            $displacementsensor->delete();
        }

        $sensor = Sensor::findOrFail(intval($id));
        $sensor->delete();

    }
}