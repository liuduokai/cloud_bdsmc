<?php

namespace App\Listeners;

use App\Events\SmsEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Listeners\SmsDemo;
use App\Sensor;

class SmsListener implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  ExampleEvent  $event
     * @return void
     */
    public function handle(SmsEvent $event)
    {
      $pwdObj = $event->msg;
      $demo = new SmsDemo();
      if($pwdObj->type == 1)
      {
        $response = $demo->sendSms(
            "北斗微芯", // 短信签名
            "SMS_122280300", // 短信模板编号
            $pwdObj->phone, // 短信接收者
            Array(  // 短信模板中字段的值
                "code"=>$pwdObj->pwd
            ),
            "123"
        );
        //var_dump($response);
      }else if($pwdObj->type == 2){
        //echo "1245";
        $poi = Sensor::findOrFail($pwdObj->id)->device->poi;
        var_dump($poi);
        if($poi){
        $user = Sensor::findOrFail($pwdObj->id)->device->poi->user;
        var_dump($user);
        if($user){
          if($user->phone){
          $response = $demo->sendSms(
              "北斗微芯", // 短信签名
              "SMS_125015352", // 短信模板编号
              $user->phone, // 短信接收者
              Array(  // 短信模板中字段的值
                "name"=>$user->name,
                "location"=>$poi->location,
                "sensor"=>$poi->name,
                "value"=>(string)$pwdObj->value,
                "alarm"=>(string)$pwdObj->content
              ),
              "123"
          );
          var_dump($response);
        }
        }
      }
      }
    }
}
