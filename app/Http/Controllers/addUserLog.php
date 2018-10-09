<?php
 use App\UsersLog;

      function addUserLog(/* Request $request */$action,$id,$type){
        $addLog = new UsersLog;
        $addLog->content = $action;
        $addLog->user_id = $id;
        $addLog->time = date_create();
        $addLog->type = $type;
        /* $addLog->content = $request->action;
        $addLog->user_id = $request->id;
        $addLog->time = date_create();
        $addLog->type = $request->type;
        $addLog->save(); */
        $addLog->save();
        return response()->json(['message' => 'add_ok']);
      }