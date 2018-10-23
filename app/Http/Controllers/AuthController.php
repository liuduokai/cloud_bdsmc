<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\User;
use App\Poi;
use App\Alarm;
use App\UsersLog;
use App\Events\SmsEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use \PhpOffice\PhpSpreadsheet\IOFactory;
use Validator;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

include_once 'addUserLog.php';

class AuthController extends Controller
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
          'login',
          'login2',
        'listProjects',
        'addProject2',
        'delProject2',
        'updateProject2',
        'listUsers2',
        'getPWD',
        'addUser2',
        'updateUser2',
        'delUser2',
        'videosource',
        'videoList',
        'videoHistory',
        'resetPwd',
        'login3',
        ]]);
    }

    public function listUsers(Request $request)
    {
      if($this->guard()->user()->type == 1)
      {
        $users = User::where('project_id', $this->guard()->user()->project_id)->get();
      } else {
        return response()->json(['error' => '未经授权的操作'], 401);
      }

      return response()->json($users);
    }
	 public function addUserList(Request $request)                     //从文件中增加用户列表
    {
      /*
      $messages = [
          'file.mimetypes' => '文件类型必须为：xls,xlsx',
      ];
      $this->validate($request, [
          'file' => 'mimes:xls,xlsx',
      ]);
      */
      $messages = [
          'email.required' => '请填写用户名',
          'email.unique' => '用户名已经存在',
          'name.required' => '请填写姓名',
          'name.unique' => '姓名已经存在',
          'phone.required' => '请填写手机号码',
          'phone.numeric' => '手机号码必须是数字',
          'phone.digits' => '手机号码必须是:digits位',
          'phone.unique' => '手机号码已经存在',
          'password.required' => '请填写密码',
      ];



      if ($request->file('file')->isValid()) {                            //验证文件
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($request->file('file')->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();      ////获取Excel中信息的行数
        $errors = [];
        for ($row = 2; $row <= $highestRow; ++$row) {       //遍历所有行
          $user = array();
          $user['email'] = $worksheet->getCellByColumnAndRow(1, $row)->getValue();
          $user['password'] = $worksheet->getCellByColumnAndRow(2, $row)->getValue();
          $user['name'] = $worksheet->getCellByColumnAndRow(3, $row)->getValue();
          $user['gender'] = $worksheet->getCellByColumnAndRow(4, $row)->getValue();
          $user['phone'] = $worksheet->getCellByColumnAndRow(5, $row)->getValue();
          $user['id_number'] = $worksheet->getCellByColumnAndRow(6, $row)->getValue();
          $user['home'] = $worksheet->getCellByColumnAndRow(7, $row)->getValue();
          $user['project_id'] = $worksheet->getCellByColumnAndRow(8, $row)->getValue();
                                                                                              //获取所有列中的信息
          $validator = Validator::make($user, [
            'name' => 'required|unique:users,name,,,deleted_at,NULL',
            'email' => 'required|unique:users,email,,,deleted_at,NULL',
            'phone' => 'required|numeric|digits:11|unique:users,phone,,,deleted_at,NULL',
            'password' => 'required',
            'project_id' => 'required',
          ],$messages);
          if ($validator->fails()) {
            $errors[$row] = $validator->errors();
          }else{
            $users = new User;           
            $users->email = $user['email'];
            $users->password = app('hash')->make($user['password']);        
            $users->password2 = $user['password'];
            $users->name = $user['name'];
            $users->project_id = $user['project_id'];
            $users->phone = $user['phone'];
            if(!empty($user['gender']))
              $users->gender = $user['gender'];              
            if(!empty($user['id_number']))
              $users->id_number = $user['id_number'];
            if(!empty($user['home']))
              $users->home = $user['home'];     
            $users->save(); 
            addUserLog('addUserList',$this->guard()->user()->id,1);
            return response()->json(['message' => 'add_ok']);
          }
        }
        if(count($errors)){
           return response()->json(['error' => $errors], 403);
         }else{


        }
       }
    }
    public function addUser(Request $request)
    {
      $messages = [
          'email.required' => '请填写用户名',
          'email.unique' => '用户名已经存在',
          'name.required' => '请填写姓名',
          'name.unique' => '姓名已经存在',
          'phone.required' => '请填写手机号码',
          'phone.numeric' => '手机号码必须是数字',
          'phone.digits' => '手机号码必须是:digits位',
          'phone.unique' => '手机号码已经存在',
          'password.required' => '请填写密码',
      ];
      $this->validate($request, [
          'name' => 'required|unique:users,name,,,deleted_at,NULL',
          'email' => 'required|unique:users,email,,,deleted_at,NULL',
          'phone' => 'required|numeric|digits:11|unique:users,phone,,,deleted_at,NULL',
          'password' => 'required',
          'project_id' => 'required',
      ],$messages);

      $user = new User;

      $user->name = $request->name;
      $user->email = $request->email;
      $user->password = app('hash')->make($request->password);
      $user->password2 = $request->password;
      $user->project_id = $request->project_id;
      $user->phone = $request->phone;
      if($request->has('home'))
        $user->home = $request->input('home');
        if($request->has('type'))
          $user->type = $request->input('type');
      if($request->has('id_number'))
        $user->id_number = $request->input('id_number');
      if($request->has('gender'))
        $user->gender = $request->input('gender');

      $user->save();
      addUserLog('addUser', $this->guard()->user()->id,1);

      return response()->json($user);
    }

    public function updateUser(Request $request)
    {
      $messages = [
          'email.required' => '请填写用户名',
          'email.unique' => '用户名已经存在',
          'name.required' => '请填写姓名',
          'name.unique' => '姓名已经存在',
          'phone.required' => '请填写手机号码',
          'phone.numeric' => '手机号码必须是数字',
          'phone.digits' => '手机号码必须是:digits位',
          'phone.unique' => '手机号码已经存在',
          //'password.required' => '请填写密码',
      ];
      $this->validate($request, [
        'id' => 'required|numeric|exists:users',
      ],$messages);
      $this->validate($request, [
          //'id' => 'required|numeric|exists:users',
          'name' => 'required|unique:users,name,'.$request->id.',,deleted_at,NULL',

          // 'name' => ['required',
          // //'unique:users,name,,,deleted_at,NULL',
          //     Rule::unique('users')->ignore($request->id)->where(function ($query) {
          //       return $query->where('deleted_at', NULL);
          //   })],

          'email' => 'required|unique:users,email,'.$request->id.',,deleted_at,NULL',
          'phone' => 'required|numeric|digits:11|unique:users,phone,'.$request->id.',,deleted_at,NULL',

          //'project_id' => 'required',
      ],$messages);


      $user = User::find($request->id);

      $user->email = $request->input('email');
      $user->name = $request->input('name');
      $user->project_id = $request->input('project_id');
      $user->phone = $request->input('phone');

      if($request->has('gender'))
        $user->gender = $request->input('gender');
      if($request->has('type'))
        $user->type = $request->input('type');
      if($request->has('password'))
      {
        $user->password = app('hash')->make($request->password);
        $user->password2 = $request->password;
      }
      if($request->has('home'))
        $user->home = $request->input('home');
      if($request->has('id_number'))
        $user->id_number = $request->input('id_number');
      $user->save();
      addUserLog('updateUser',$this->guard()->user()->id,3);
      return response()->json(['message' => 'update_ok']);
    }


    //2



    public function listUsers2(Request $request)
    {
        $type = $this->guard()->user()->type;
        if ($type === 1) {
            if ($request->has('id')) {
                $users = User::where('project_id', $request->id)->get();
                return response()->json($users);
            } else {
                $users = User::all();
                return response()->json($users);
            }
        }else{
            if ($request->has('id')){
                $users = User::where('project_id', $request->id)->get();
                return response()->json($users);
            }else{
                return response()->json(['error'=>"需要id"]);
            }
        }

    }
    public function addUser2(Request $request)
    {
      $user = new User;
      if($request->has('name')){
      $user->name = $request->name;
      if(User::where([
        ['name', $request->name],
        ['deleted_at', '=', NULL],
      ])->count()>0)
      return response()->json(['message' => '名字已经存在']);
    }
      if($request->has('email')){
      $user->email = $request->email;
      if(User::where([
        ['email', $request->email],
        ['deleted_at', '=', NULL],
      ])->count()>0)
      return response()->json(['message' => '用户名已经存在']);
    }
      if($request->has('password'))
      {
      $user->password = app('hash')->make($request->password);
      $user->password2 = $request->password;
    }
      if($request->has('project_id'))
      $user->project_id = $request->project_id;
      if($request->has('phone')){
      $user->phone = $request->phone;
      if(User::where([
        ['phone', $request->phone],
        ['deleted_at', '=', NULL],
      ])->count()>0)
      return response()->json(['message' => '手机号已经存在']);
    }
      if($request->has('home'))
        $user->home = $request->input('home');
      if($request->has('type'))
        $user->type = $request->input('type');
      if($request->has('id_number'))
        $user->id_number = $request->input('id_number');
      if($request->has('gender'))
        $user->gender = $request->input('gender');

      $user->save();
      addUserLog('addUser2',$this->guard()->user()->id,1);
      return response()->json(['message' => 'add_ok']);
    }

    public function updateUser2(Request $request)
    {
      $user = User::findOrFail($request->id);

      if($request->has('email')){
        $user->email = $request->input('email');
        if(User::where([
          ['id', '<>', $request->id],
          ['email', $request->email],
          ['deleted_at', '=', NULL],
        ])->count()>0)
        return response()->json(['message' => '用户名已经存在']);
      }
      if($request->has('name')){
        $user->name = $request->input('name');
        if(User::where([
          ['id', '<>', $request->id],
          ['name', $request->name],
          ['deleted_at', '=', NULL],
        ])->count()>0)
        return response()->json(['message' => '名字已经存在']);
      }
      if($request->has('project_id'))
        $user->project_id = $request->input('project_id');
      if($request->has('phone')){

        $user->phone = $request->input('phone');
        if(User::where([
          ['id', '<>', $request->id],
          ['phone', $request->phone],
          ['deleted_at', '=', NULL],
        ])->count()>0)
        return response()->json(['message' => '手机号已经存在']);
      }

      if($request->has('gender'))
        $user->gender = $request->input('gender');
      if($request->has('type'))
        $user->type = $request->input('type');
      if($request->has('password'))
      {
        $user->password = app('hash')->make($request->password);
        $user->password2 = $request->password;
      }
      if($request->has('home'))
        $user->home = $request->input('home');
      if($request->has('id_number'))
        $user->id_number = $request->input('id_number');
      $user->save();
      addUserLog('updateUser2',$this->guard()->user()->id,3);
      return response()->json(['message' => 'update_ok']);
    }

    public function delUser2(Request $request)
    {
      $this->validate($request, [
          'id' => 'required|numeric',
      ]);

      Poi::where('user_id', $request->id)->update(['user_id' => NULL]);
      addUserLog('delUser2',$this->guard()->user()->id,2);
      return User::destroy($request->id);
      
    }

    /**
     * Get a JWT token via given credentials.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
      $messages = [
          'email.required' => '请填写用户名',
          'password.required' => '请填写密码',
      ];
      $ip = $request->getClientIp();
      $this->validate($request, [
          'email' => 'required',
          'password' => 'required',
      ],$messages);

      if(Cache::has($request->email)){
           }else{
       Cache::put($request->email, 1, 5);
      }
      if(Cache::get($request->email) < 4){
        $credentials = $request->only('email', 'password');

        if ($token = $this->guard()->attempt($credentials)) {
            Cache::forget($request->email);
            return $this->respondWithToken($token);
        }else{
          Cache::increment($request->email);
        }
      }else{
        return response()->json(['error' => '输入密码错误次数过多',  $request->email,'wrong'=>Cache::get($request->email),'ip'=>$ip], 403);
       // return response()->json(['error' => 'Unauthorized','email' => $request->email,'ip'=>$ip], 401);
      }
         $ip = $request->getClientIp();
        //echo $ip; */
        return response()->json(['error' => '请输入正确的用户名和密码','email' => $request->email,'ip'=>$ip], 401);
    }

    public function login3(Request $request)
    {
        $messages = [
            'email.required' => '请填写用户名',
            'password.required' => '请填写密码',
        ];
        $ip = $request->getClientIp();
        $this->validate($request, [
            'email' => 'required',
            'password' => 'required',
        ],$messages);

        if(Cache::has($request->email)){
        }else{
            Cache::put($request->email, 1, 5);
        }
        if(Cache::get($request->email) < 4){
            $credentials = $request->only('email', 'password');

            if ($token = $this->guard()->attempt($credentials)) {
                $uesr = $this->guard()->user()->id;
                $result = DB::table('projects')
                    ->where("projects.user_id", $uesr)
                    ->exists();
                if($uesr = $this->guard()->user()->type === 1 || $result) {
                    Cache::forget($request->email);
                    return $this->respondWithToken($token);
                }else{
                    return response()->json(['error' => '此账户无权登录','email' => $request->email,'ip'=>$ip], 401);
                }
            }else{
                Cache::increment($request->email);
            }
        }else{
            return response()->json(['error' => '输入密码错误次数过多',  $request->email,'wrong'=>Cache::get($request->email),'ip'=>$ip], 403);
            // return response()->json(['error' => 'Unauthorized','email' => $request->email,'ip'=>$ip], 401);
        }
        $ip = $request->getClientIp();
        //echo $ip; */
        return response()->json(['error' => '请输入正确的用户名和密码','email' => $request->email,'ip'=>$ip], 401);
    }
    /**
     * Get the authenticated User
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return response()->json($this->guard()->user());
    }

    /**
     * Log the user out (Invalidate the token)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        $this->guard()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken($this->guard()->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        $this->guard()->user()->project;
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'me' => $this->guard()->user(),
            'expires_in' => $this->guard()->factory()->getTTL() * 60
        ]);
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

	public function updateMe(Request $request)
    {
  		//$this->guard()->user()->name = '123456';
  		//$this->guard()->user()->save();

  		$user = $this->guard()->user();
      //var_dump($request->input());
      if($request->has('name'))
        $user->name = $request->input('name');
      if($request->has('phone'))
        $user->phone = $request->input('phone');
      if($request->has('gender'))
        $user->gender = $request->input('gender');
      /*if($request->has('project_name'))
        $user->project_name = $request->input('project_name');*/
      if($request->has('home'))
        $user->home = $request->input('home');
      if($request->has('id_number'))
        $user->id_number = $request->input('id_number');
          //$attributes = array_filter($request->input());

      $user->save();
      addUserLog('updateMe',$this->guard()->user()->id,3);
        // if ($attributes) {
        //     $user->update(['phone' => '13330243024']);
        // }



        return response()->json(['message' => 'update_ok']);
    }

    public function Update(Request $request)
    {
        $user = new User;
        if($request->has('phone'))$user->phone = $request->phone;
        if($request->has('name'))$user->name = $request->name;
        if($request->has('id_number'))$user->id_number = $request->input('id_number');
        if($request->has('home'))$user->home = $request->input('home');
        if($request->has('gender'))$user->gender = $request->input('gender');
        $user->user_id = Auth::guard()->user()->id;
        $tmp = $user->save();
        addUserLog('Update',$this->guard()->user()->id,3);
        return response()->json($user);

    }


    public function getPWD(Request $request)
    {
      $messages = [
          'phone.required' => '请填写手机号码',
          'phone.numeric' => '手机号码必须为数字.',
          'phone.digits' => '手机号码必须为:digits 位数.',
          'phone.exists' => '手机号码没有注册.',
      ];
      $this->validate($request, [
          'phone' => 'required|numeric|digits:11|exists:users',
      ],$messages);

      $user = User::where('phone',$request->phone)->first();
      if(!$user)
        return response()->json(["phone"=>["手机号码没有注册"]],200);

      if($user->type == 1)
        return response()->json(["phone"=>"管理员不能使用短信验证码登录"],200);

      $pwd = strval(rand(0,9)).strval(rand(0,9)).strval(rand(0,9)).strval(rand(0,9));
      //$pwd = "0780";
      Cache::put('pwd_'.$request->phone, $pwd, 3);
      $pwdObj = (object)null;
      //$pwdObj->type = 1;
      $pwdObj->phone = $request->phone;
      $pwdObj->pwd = $pwd;
      //event(new SmsEvent($pwdObj));
      $this->amqpsms($pwdObj);
      //dispatch(new MsgJob);
      return response()->json(['message'=>'send it']);
    }
    
    public function amqpsms($pwdObj)
    {
        $host= config('auth.authAmqpHost');
        $port=config('auth.authAmqpPort');
        $user=config('auth.authAmqpUser');
        $password=config('auth.authAmqpPassword');
        $vhost = config("auth.authAmqpVhost");
        $connection = new AMQPStreamConnection($host, $port, $user, $password, $vhost);
        $channel = $connection->channel();

        //$channel->exchange_declare('topic_logs', 'topic', false, false, false);

        $routing_key = 'sms';
        $data = json_encode($pwdObj);

        $msg = new AMQPMessage($data);

        $channel->basic_publish($msg, 'amq.topic', $routing_key);

        //echo " [x] Sent ",$routing_key,':',$data," \n";

        $channel->close();
        $connection->close();
        
    }

    public function login2(Request $request)
    {
      $messages = [
          'phone.required' => '请填写手机号码',
          'phone.numeric' => '手机号码必须为数字.',
          'phone.digits' => '手机号码必须为:digits 位数.',
          'password.required' => '请填写验证码',
          'password.numeric' => '验证码必须为数字.',
          'password.digits' => '验证码必须为:digits 位数.',
      ];
      $this->validate($request, [
          'phone' => 'required|numeric|digits:11',
          'password' => 'required|numeric|digits:4',
      ],$messages);
      $pwd = Cache::get('pwd_'.$request->phone);
      if($pwd)
      {
        if($pwd === strval($request->password))
        {
          $user = DB::table('users')->where('phone', $request->phone)->first();
          $credentials['email']=$user->email;
          $credentials['password']=$user->password2;
          if ($token = $this->guard()->attempt($credentials)) {
              Cache::forget('pwd_'.$request->phone);
              return $this->respondWithToken($token);
          }
        }
      }

      return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function counts()
    {
      $userCount = User::where('project_id', $this->guard()->user()->project->id)->count();

      if($this->guard()->user()->type == 1){
        $pois=$this->guard()->user()->project->pois;
        $poiCount = Poi::where('project_id', $this->guard()->user()->project->id)->count();

      }else{
        $pois=$this->guard()->user()->pois;
        $poiCount = Poi::where('user_id', $this->guard()->user()->id)->count();

      }
      $alarmCount = 0;
      foreach ($pois as $poi) {
          $devices = $poi->devices;
          foreach ($devices as $device) {
              $count_tmp = $device->alarms()->where('state',0)->count();
              $alarmCount = $alarmCount + $count_tmp;
              
              
            $sensors = $device->sensors;
              foreach ($sensors as $sensor) {
                //$alarms_tmp = $sensor->alarms()->where('state',0)->orderBy('time', 'desc')->get();
                $count_tmp = $sensor->alarms()->where('state',0)->count();
                $alarmCount = $alarmCount + $count_tmp;
              }
          }
      }

      return response()->json(['user' => $userCount,'poi' => $poiCount,'alarm' => $alarmCount]);
    }

    public function videosource(Request $request)
    {
      $messages = [
          'puid.required' => '请填写puid',
          
      ];
      $this->validate($request, [
          'puid' => 'required',
          
      ],$messages);

      $stream_type = "hls";
      if($request->has("type"))
      {
        $stream_type = "rtmp";
      }

      $req = array(
        "request"=>array(
          "puid"=>$request->puid,
          "idx"=>"0",
          "videoformat"=>$stream_type,
          "begin"=>"",
          "end"=>"",
          "stream"=>"1",
          "expiretimes"=>"86400"
        )
      );
      $req = json_encode($req);

      $sign = "";
      $epid = "system";
      $uid = "admin";
      $psw = strtoupper(md5(""));
      $t=time();
      $sign =  strtoupper(md5($epid.$uid.$psw.$t));
      $Authorization  = base64_encode($epid."_".$uid."_".$t);
      /*
      echo "请求参数：".$req;
      echo "</br>";
      echo "sign：".$sign;
      echo "</br>";
      echo "Authorization：".$Authorization;
      echo "</br>";
      */
      //$url = "http://218.76.43.93:9580/nmc/rest/realstream?sign=".$sign;
        $url = "http://192.168.1.140:9580/nmc/rest/realstream?sign=".$sign;
        $header = array(
            'Accept:' . 'Accept:application/json',
            'Content-Type:Accept:application/json;charset=utf-8',
            'Authorization:' .$Authorization 
        );
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch,CURLOPT_POST,1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$req);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $result = curl_exec($ch);
        curl_close($ch);
        

        //echo urldecode($result);
        return response(urldecode($result));
    }

    public function videoList(Request $request)
    {
      $messages = [
          'page.required' => '请填写page',
          'rows.required' => '请填写rows',
      ];
      $this->validate($request, [
          'page' => 'required',
          'rows' => 'required',
      ],$messages);

      $req = [
        
          "page"=>$request->page,
          "rows"=>$request->rows,
          "epid"=>"system",
          "userId"=>"admin"
        
      ];
      //$req = json_encode($req);
      //echo $req;
      //var_dump($req);
      $url = "http://218.76.43.93:9580/nmc/rest/v1/pu/query";

        $header = array(
            'Accept:' . 'Accept:application/json',
            'Content-Type:Accept:application/json;charset=utf-8',
            //'Authorization:' .$Authorization 
        );
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        //curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch,CURLOPT_POST,1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$req);
        /*curl_setopt($ch,CURLOPT_POSTFIELDS,["page"=>1,
          "rows"=>100,
          "epid"=>"system",
          "userId"=>"admin"]);*/
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        //curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        $result = curl_exec($ch);
        //$info = curl_getinfo($ch);
        //var_dump($info);
        
        curl_close($ch);
        

        //echo urldecode($result);
        return response(urldecode($result));

    }


    public function videoHistory(Request $request)
    {
      $messages = [
          'puid.required' => '请填写puid',
          'begin.required' => '请填写begin',
          'end.required' => '请填写end',
      ];
      $this->validate($request, [
          'puid' => 'required',
          'begin' => 'required',
          'end' => 'required',
          
      ],$messages);

      $req = array(
        "request"=>array(
          "puid"=>$request->puid,
          "idx"=>"0",
          "videoformat"=>"hls",
          "begin"=>$request->begin,
          "end"=>$request->end,
          //"stream"=>"1",
          "expiretimes"=>"86400"
        )
      );
      $req = json_encode($req);

      $sign = "";
      $epid = "system";
      $uid = "admin";
      $psw = strtoupper(md5(""));
      $t=time();
      $sign =  strtoupper(md5($epid.$uid.$psw.$t));
      $Authorization  = base64_encode($epid."_".$uid."_".$t);
      /*
      echo "请求参数：".$req;
      echo "</br>";
      echo "sign：".$sign;
      echo "</br>";
      echo "Authorization：".$Authorization;
      echo "</br>";
      */
      $url = "http://218.76.43.93:9580/nmc/rest/vodstream?sign=".$sign;

        $header = array(
            'Accept:' . 'Accept:application/json',
            'Content-Type:Accept:application/json;charset=utf-8',
            'Authorization:' .$Authorization 
        );
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch,CURLOPT_POST,1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$req);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $result = curl_exec($ch);
        curl_close($ch);
        

        //echo urldecode($result);
        return response(urldecode($result));
    }
    public function addUserLog(Request $request /* $action,$id,$type */){
     
      return addUserLog($request->action,$request->id,$request->type);
     
      
    }
    public function UsersLog(Request $request){
      if($this->guard()->user()->type == 1){
       /*  $userlogdate = UsersLog::all();
        $userInfo  = UsersLog::all()->users; */
        $userlogdate = DB::table('usersLog')
        ->join('users', 'usersLog.user_id', '=', 'users.id')
        ->select(
          'usersLog.*',
          'users.name')
         ->get();
        return response()->json($userlogdate);
      }else{
        return response()->json(['error' => '没有权限查看日志'], 403);
      } 
    }
    public function resetPwd(Request $request)
    {
        $messages = [
            'phone.required' => '请填写手机号码',
            'phone.numeric' => '手机号码必须为数字.',
            'phone.digits' => '手机号码必须为:digits 位数.',
            'code.required' => '请填写验证码',
            'code.numeric' => '验证码必须为数字.',
            'code.digits' => '验证码必须为:digits 位数.',
            'password.required' => '请填写密码',
        ];
        $numb =
        //Cache::put('pwd_'.$request->phone, "1234", 3);
        $this->validate($request, [
            'phone' => 'required|numeric|digits:11',
            'code' => 'required|numeric|digits:4',
            'password'=>'required'
        ],$messages);
        $pwd = Cache::get('pwd_'.$request->phone);
        if($pwd)
        {
            if($pwd === strval($request->code))
            {
                $user = DB::table('users')
                    ->where('phone', $request->phone)
                    ->update(['password'=>(app('hash')->make($request->password))]);
                //$user->password = app('hash')->make($request->password);
                //$user->name = "llltest";
                //$user->save();
                return response()->json(['message'=>'修改密码成功']);
            }else {
                return response()->json(['error'=>'验证码错误'],403);
            }
        }else {
            return response()->json(['error'=>'请先获取验证码'],403);
        }
    }
}
