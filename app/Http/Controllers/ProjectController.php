<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
//use Illuminate\Supl\Facades\DB;
use Illuminate\Http\Request;
use App\Project;
use Illuminate\Support\Facades\Auth;
include_once 'addUserLog.php';

class ProjectController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api',
            ['except' => [
            ]]);

        DB::connection()->enableQueryLog();
    }

    public function guard()
    {
        return Auth::guard();
    }

    public function listProjects(Request $request)
    {
        $users = Project::all();
        return response()->json($users);
    }
    public function listProjects2(Request $request)
    {
        if($uesr = $this->guard()->user()->type === 1){
            $users = Project::all();
            return response()->json($users);
        }else {
            $uesr = $this->guard()->user()->id;
            $result = DB::table('projects')
                ->where("projects.user_id", $uesr)
                ->get();
            return response()->json($result);
        }
    }

    public function addProject2(Request $request)
    {
      $this->validate($request, [
          'name' => 'required|unique:projects,name,,,deleted_at,NULL',
          'lt_point'=>'required',
          'rd_point'=>'required',
          'center_point'=>'required',
          'sate_path'=>'required',
          // 'sate_lvl'=>'required',
          'map_path'=>'required',
          'map_change_lvl'=>'required',
          'zom'=>'required',
          // 'user_id'=>'required'
      ]);

      $user = new Project;
      $user->name= $request->name;
      $user->lt_point= $request->lt_point;
      $user->rd_point= $request->rd_point;
      $user->center_point= $request->center_point;
      $user->sate_path= $request->sate_path;
      // $user->sate_lvl= $request->sate_lvl;
      $user->map_path= $request->map_path;
      $user->map_change_lvl = $request->map_change_lvl;
      $user->zom = $request->zom;
      if ($request->has('user_id'))
        $user->user_id= $request->user_id;
      $user->save();
      addUserLog('addProject2',$this->guard()->user()->id,1);
            //return response()->json(['message' => 'error']);
      // addUserLog('addProject2',$this->guard()->user()->id,1);
      return response()->json(['message' => 'add_ok','user'=>$user]);
    }
    public function addProjectFile2(Request $request){
        $this->validate($request, [
            'id' => 'required|exists:projects',
        ]);
        if($request->hasFile('pro_bord_path') && $request->file('pro_bord_path')->isValid()){

            $filename = uniqid()."_".$request->file('pro_bord_path')->getClientOriginalName();
            $path = "file/".$filename;
            $request->file('pro_bord_path')->move("./file",$filename);
            $user = Project::find(intval($request->id));
            $user->pro_bord_path = $path;
            $user->save();
        }else{
            return response()->json(['message' => 'need_file']);
        }
    }
    public function updateProject2(Request $request)
    {
      $this->validate($request, [
          'id' => 'required|exists:projects',
          'name' => 'required',
          'lt_point'=>'required',
          'rd_point'=>'required',
          'sate_path'=>'required',
          // 'sate_lvl'=>'required',
          'map_path'=>'required',
          'map_change_lvl'=>'required',
          //'zom'=>'required',
      ]);

      $user = Project::find(intval($request->id));
      $user->name = $request->name;
      $user->lt_point= $request->lt_point;
      $user->rd_point= $request->rd_point;
      $user->sate_path= $request->sate_path;
      $user->center_point= $request->center_point;
      // $user->sate_lvl= $request->sate_lvl;
      $user->map_path= $request->map_path;
      $user->map_change_lvl = $request->map_change_lvl;
      if ($request->has('user_id'))
          $user->user_id= $request->user_id;
      if ($request->has('zom'))
        $user->zom = $request->zom;
      $user->save();

      //addUserLog('addProject2',$this->guard()->user()->id,1);
            //return response()->json(['message' => 'error']);
        addUserLog('updateProject2',$this->guard()->user()->id,3);
        return response()->json(['message' => 'update_ok','user'=>$user]);
    }

    public function delProject2(Request $request)
    {
      $this->validate($request, [
          'id' => 'required|exists:projects',
      ]);

      $user = Project::find(intval($request->id));
      $user->delete();
      addUserLog('delProject2',$this->guard()->user()->id,2);
      return response()->json(['message' => 'del_ok']);
    }

    public function getMapInfo()
    {
        $projectId = $this->guard()->user()->project_id;
        $project = Project::where('id', '=', $projectId)->get();
        //$result = [];
        foreach ($project as $item) {
            $latlng = explode(',', $item->center_point);
            $lat = (double)$latlng[0];
            $lng = (double)$latlng[1];
            /*$result[] = ['lat'=>$lat];
            $result[] = ['lng'=>$lng];*/
            $lt = explode(',', $item->lt_point);
            $left = (double)$lt[0];
            $top = (double)$lt[1];
            $rd = explode(',', $item->rd_point);
            $right = (double)$rd[0];
            $down = (double)$rd[1];
            /*$result[] = ['top'=>$top];
            $result[] = ['right'=>$right];
            $result[] = ['left'=>$left];
            $result[] = ['bottom'=>$down];*/
            $maxmin = explode(',', $item->map_change_lvl);
            $min = (double)$maxmin[0];
            $max = (double)$maxmin[1];
            /*$result[] = ['maxZoom'=>$max];
            $result[] =['minZoom'=>$min];
            $result[] =['vp' =>$item->map_path];
            $result[] =['vt'=>$item->sate_path];*/
            $myfile = fopen($item->pro_bord_path, "r") or die("Unable to open file!");
            $border_path = fread($myfile, filesize($item->pro_bord_path));
            //$border = explode(',', $border_path);
            //$result []=['border'=>$border_path];
            /*if(!is_dir($item->map_path))
                return response()->json(['error'=>'街道地图目录错误','path'=>$item->map_path],403);
            if(!is_dir($item->sate_path))
                return response()->json(['error'=>'卫星地图目录错误','path'=>$item->sate_path],403);*/
            fclose($myfile);
            $object = (object)[
                'lat' => $lat,
                'lng' => $lng,
                'top' => $top,
                'right' => $right,
                'left' => $left,
                'bottom' => $down,
                'maxZoom' => $max,
                'minZoom' => $min,
                'vp' => $item->map_path,
                'vt' => $item->sate_path,
                'border' => $border_path,
                'zom'=> $item->zom,
            ];
        }
        return response()->json($object);
    }
}
