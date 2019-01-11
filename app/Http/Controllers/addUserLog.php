<?php
 use App\UsersLog;

      function addUserLog($action,$id,$type){
        $addLog = new UsersLog;
        $addLog->content = $action;
        $addLog->user_id = $id;
        $addLog->time = date_create();
        $addLog->type = $type;
        $addLog->save();
        return response()->json(['message' => 'add_ok']);
      }