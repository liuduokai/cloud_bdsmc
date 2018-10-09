<?php

namespace App\Http\Middleware;

use \Firebase\JWT\JWT;
use Closure;

class Authenticate2
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
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
            return $next($request);
          }catch(\Exception $e){

          }
        }

        return response('Unauthorized.', 401);
    }
}
