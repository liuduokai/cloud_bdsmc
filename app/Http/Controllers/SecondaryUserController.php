<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use \Firebase\JWT\JWT;
use App\SecondaryUser;
use App\Poi;
use App\Events\PwdEvent;


class SecondaryUserController extends Controller
{
    public $user;

    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
          'login',
          'getPWD',
          ]]);
    }

    public function me(Request $request)
    {
      $key = "example_key";
      $token = $request->header('Authorization');
      $tmp = explode(" ",$token);

      if(count($tmp)==2)
      {
        $token = $tmp[1];
        JWT::$leeway = 60; // $leeway in seconds
        try{
          $decoded = JWT::decode($token, $key, array('HS256'));
          return response()->json($decoded->user);
        }catch(\Exception $e){
          return NULL;
        }
      }
    }

    public function genToken($phone)
    {
      $user = SecondaryUser::where('phone', $phone)->firstOrFail();
      $key = "example_key";
      $token = array(
          "iss" => "http://cloud.bdsmc.net",
          "aud" => "http://cloud.bdsmc.net",
          "iat" => time(),
          "nbf" => time(),
          "exp" => time()+3600,
          "user"=> $user
      );
      return [
        'access_token' => JWT::encode($token, $key),
        'token_type' => 'bearer',
        'me' => $user
      ];
    }

    public function guard($token)
    {

    }

    public function login(Request $request)
    {
      $this->validate($request, [
          'phone' => 'required|numeric|digits:11',
          'password' => 'required|numeric|digits:4',
      ]);
      $pwd = cache('pwd_'.$request->phone);
      if($pwd)
      {
        if($pwd === strval($request->password))
        {
          Cache::forget('pwd_'.$request->phone);
          return response()->json($this->genToken($request->phone));
        }
      }

      return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function getPWD(Request $request)
    {
      $this->validate($request, [
          'phone' => 'required|numeric|digits:11|exists:secondary_users,phone',
      ]);
        $pwd = strval(rand(0,9)).strval(rand(0,9)).strval(rand(0,9)).strval(rand(0,9));
        Cache::put('pwd_'.$request->phone, $pwd, 3);
        $pwdObj = (object)null;
        $pwdObj->phone = $request->phone;
        $pwdObj->pwd = $pwd;
        event(new PwdEvent($pwdObj));
        return response()->json([$request->phone => $pwd]);

    }
}
