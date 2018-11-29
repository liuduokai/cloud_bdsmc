<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

//$router->get('/login', 'AuthController@login');
$router->get('/login', ['middleware' => 'cors','uses' => 'AuthController@login']);
$router->options('/login', ['middleware' => 'cors','uses' => 'AuthController@login']);

$router->group(['middleware' => ['cors']], function () use ($router) {

    $router->get('/logout', 'AuthController@logout');
    $router->get('/refresh', 'AuthController@refresh');
    $router->get('/me', 'AuthController@me');
	$router->options('/me', 'AuthController@me');
	$router->options('/logout', 'AuthController@logout');
	$router->post('/updateMe', 'AuthController@updateMe');
	$router->options('/updateMe', 'AuthController@updateMe');
  $router->options('/refresh', 'AuthController@refresh');
});

$router->get('/pick', ['middleware' => 'cors','uses' => 'PoiController@pick']);
$router->options('/pick', ['middleware' => 'cors','uses' => 'PoiController@pick']);
$router->get('/gnss', ['middleware' => 'cors','uses' => 'PoiController@gnss']);
$router->options('/gnss', ['middleware' => 'cors','uses' => 'PoiController@gnss']);

$router->post('/Update', ['middleware' => 'cors','uses' => 'AuthController@Update']);
$router->options('/Update', ['middleware' => 'cors','uses' => 'AuthController@Update']);
$router->post('/Del', ['middleware' => 'cors','uses' => 'AuthController@Del']);
$router->options('/Del', ['middleware' => 'cors','uses' => 'AuthController@Del']);

$router->get('/listPois', ['middleware' => 'cors','uses' => 'PoiController@listPois']);
$router->options('/listPois', ['middleware' => 'cors','uses' => 'PoiController@listPois']);

$router->post('/addImage', ['middleware' => 'cors','uses' => 'PoiController@addImage']);
$router->options('/addImage', ['middleware' => 'cors','uses' => 'PoiController@addImage']);
$router->post('/addPos', ['middleware' => 'cors','uses' => 'PoiController@addPos']);
$router->options('/addPos', ['middleware' => 'cors','uses' => 'PoiController@addPos']);

$router->get('/findImage', ['middleware' => 'cors','uses' => 'PoiController@findImage']);
$router->post('/findImage', ['middleware' => 'cors','uses' => 'PoiController@findImage']);
$router->options('/findImage', ['middleware' => 'cors','uses' => 'PoiController@findImage']);

$router->post('/delPos', ['middleware' => 'cors','uses' => 'PoiController@delPos']);
$router->options('/delPos', ['middleware' => 'cors','uses' => 'PoiController@delPos']);

$router->post('/delImage', ['middleware' => 'cors','uses' => 'PoiController@delImage']);
$router->options('/delImage', ['middleware' => 'cors','uses' => 'PoiController@delImage']);
$router->post('/listPoses', ['middleware' => 'cors','uses' => 'PoiController@listPoses']);
$router->options('/listPoses', ['middleware' => 'cors','uses' => 'PoiController@listPoses']);

$router->post('/assignPoi', ['middleware' => 'cors','uses' => 'PoiController@assignPoi']);
$router->options('/assignPoi', ['middleware' => 'cors','uses' => 'PoiController@assignPoi']);

$router->get('/searchPoi/{q}', ['middleware' => 'cors','uses' => 'PoiController@searchPoi']);
$router->options('/searchPoi/{q}', ['middleware' => 'cors','uses' => 'PoiController@searchPoi']);

$router->get('/listDevices', ['middleware' => 'cors','uses' => 'PoiController@listDevices']);
$router->options('/listDevices', ['middleware' => 'cors','uses' => 'PoiController@listDevices']);



$router->get('/listDevices3', ['middleware' => 'cors','uses' => 'PoiController@listDevices3']);
$router->options('/listDevices3', ['middleware' => 'cors','uses' => 'PoiController@listDevices3']);

$router->get('/listSensors', ['middleware' => 'cors','uses' => 'PoiController@listSensors']);
$router->options('/listSensors', ['middleware' => 'cors','uses' => 'PoiController@listSensors']);


$router->get('/poi', ['middleware' => 'cors','uses' => 'PoiController@poi']);
$router->options('/poi', ['middleware' => 'cors','uses' => 'PoiController@poi']);

$router->get('/device', ['middleware' => 'cors','uses' => 'PoiController@device']);
$router->options('/device', ['middleware' => 'cors','uses' => 'PoiController@device']);

$router->get('/devicedata', ['middleware' => 'cors','uses' => 'PoiController@getDevicedata']);
$router->post('/devicedata', ['middleware' => 'cors','uses' => 'PoiController@getDevicedata']);
$router->options('/devicedata', ['middleware' => 'cors','uses' => 'PoiController@getDevicedata']);

$router->get('/history', ['middleware' => 'cors','uses' => 'PoiController@history']);
$router->options('/history', ['middleware' => 'cors','uses' => 'PoiController@history']);


$router->post('/updateSensor', ['middleware' => 'cors','uses' => 'PoiController@UpdateSensor']);
$router->options('/updateSensor', ['middleware' => 'cors','uses' => 'PoiController@UpdateSensor']);

$router->get('/listProjects', ['middleware' => 'cors','uses' => 'ProjectController@listProjects']);
$router->options('/listProjects', ['middleware' => 'cors','uses' => 'ProjectController@listProjects']);

$router->get('/listAlarms', ['middleware' => 'cors','uses' => 'AlarmController@listAlarms']);
$router->options('/listAlarms', ['middleware' => 'cors','uses' => 'AlarmController@listAlarms']);

$router->get('/countAlarms', ['middleware' => 'cors','uses' => 'AlarmController@countAlarms']);
$router->options('/countAlarms', ['middleware' => 'cors','uses' => 'AlarmController@countAlarms']);

$router->get('/updateAlarm/{id}', ['middleware' => 'cors','uses' => 'AlarmController@updateAlarm']);
$router->options('/updateAlarm/{id}', ['middleware' => 'cors','uses' => 'AlarmController@updateAlarm']);

$router->get('/addReg', ['middleware' => 'cors','uses' => 'AlarmController@addReg']);
$router->options('/addReg', ['middleware' => 'cors','uses' => 'AlarmController@addReg']);

$router->get('/listRegs', ['middleware' => 'cors','uses' => 'AlarmController@listRegs']);
$router->options('/listRegs', ['middleware' => 'cors','uses' => 'AlarmController@listRegs']);

$router->get('/listMaintenances', ['middleware' => 'cors','uses' => 'AlarmController@listMaintenances']);
$router->options('/listMaintenances', ['middleware' => 'cors','uses' => 'AlarmController@listMaintenances']);

$router->post('/addMaintenance/{id}', ['middleware' => 'cors','uses' => 'AlarmController@addMaintenance']);
$router->options('/addMaintenance/{id}', ['middleware' => 'cors','uses' => 'AlarmController@addMaintenance']);

$router->post('/handleDeviceAlarm', ['middleware' => 'cors','uses' => 'AlarmController@handleDeviceAlarm']);
$router->options('/handleDeviceAlarm', ['middleware' => 'cors','uses' => 'AlarmController@handleDeviceAlarm']);

$router->post('/handleSensorAlarm', ['middleware' => 'cors','uses' => 'AlarmController@handleSensorAlarm']);
$router->options('/handleSensorAlarm', ['middleware' => 'cors','uses' => 'AlarmController@handleSensorAlarm']);

$router->post('/handleCameraAlarm', ['middleware' => 'cors','uses' => 'AlarmController@handleCameraAlarm']);
$router->options('/handleCameraAlarm', ['middleware' => 'cors','uses' => 'AlarmController@handleCameraAlarm']);


$router->post('/getPWD', ['middleware' => 'cors','uses' => 'AuthController@getPWD']);
$router->options('/getPWD', ['middleware' => 'cors','uses' => 'AuthController@getPWD']);

$router->post('/login2', ['middleware' => 'cors','uses' => 'AuthController@login2']);
$router->options('/login2', ['middleware' => 'cors','uses' => 'AuthController@login2']);

$router->get('/counts', ['middleware' => 'cors','uses' => 'AuthController@counts']);
$router->options('/counts', ['middleware' => 'cors','uses' => 'AuthController@counts']);


$router->post('/user2', ['middleware' => 'cors','uses' => 'SecondaryUserController@me']);
$router->options('/user2', ['middleware' => 'cors','uses' => 'SecondaryUserController@me']);

$router->get('/listUsers', ['middleware' => 'cors','uses' => 'AuthController@listUsers']);
$router->options('/listUsers', ['middleware' => 'cors','uses' => 'AuthController@listUsers']);

$router->options('/addUser', ['middleware' => 'cors','uses' => 'AuthController@addUser2']);
$router->options('/addUserList', ['middleware' => 'cors','uses' => 'AuthController@addUserList']);
$router->options('/updateUser', ['middleware' => 'cors','uses' => 'AuthController@updateUser2']);
$router->options('/delUser', ['middleware' => 'cors','uses' => 'AuthController@delUser2']);

$router->group(['middleware' => ['auth']], function () use ($router) {

$router->get('/addUser', ['middleware' => 'cors','uses' => 'AuthController@addUser']);
$router->post('/addUserList', ['middleware' => 'cors','uses' => 'AuthController@addUserList']);
$router->get('/updateUser', ['middleware' => 'cors','uses' => 'AuthController@updateUser']);
$router->get('/delUser', ['middleware' => 'cors','uses' => 'AuthController@delUser2']);
});

$router->post('/addProjectFile2', ['middleware' => 'cors','uses' => 'ProjectController@addProjectFile2']);
$router->post('/listProjects2', ['middleware' => 'cors','uses' => 'ProjectController@listProjects2']);
$router->post('/addProject2', ['middleware' => 'cors','uses' => 'ProjectController@addProject2']);
$router->post('/updateProject2', ['middleware' => 'cors','uses' => 'ProjectController@updateProject2']);
$router->post('/delProject2', ['middleware' => 'cors','uses' => 'ProjectController@delProject2']);

$router->get('/addProjectFile2', ['middleware' => 'cors','uses' => 'ProjectController@addProjectFile2']);
$router->get('/listProjects2', ['middleware' => 'cors','uses' => 'ProjectController@listProjects2']);
$router->get('/addProject2', ['middleware' => 'cors','uses' => 'ProjectController@addProject2']);
$router->get('/updateProject2', ['middleware' => 'cors','uses' => 'ProjectController@updateProject2']);
$router->get('/delProject2', ['middleware' => 'cors','uses' => 'ProjectController@delProject2']);

$router->options('/addProjectFile2', ['middleware' => 'cors','uses' => 'ProjectController@addProjectFile2']);
$router->options('/listProjects2', ['middleware' => 'cors','uses' => 'ProjectController@listProjects']);
$router->options('/addProject2', ['middleware' => 'cors','uses' => 'ProjectController@addProject2']);
$router->options('/updateProject2', ['middleware' => 'cors','uses' => 'ProjectController@updateProject2']);
$router->options('/delProject2', ['middleware' => 'cors','uses' => 'ProjectController@delProject2']);

$router->get('/listUsers2', ['middleware' => 'cors','uses' => 'AuthController@listUsers2']);
$router->get('/addUser2', ['middleware' => 'cors','uses' => 'AuthController@addUser2']);
$router->get('/updateUser2', ['middleware' => 'cors','uses' => 'AuthController@updateUser2']);
$router->get('/delUser2', ['middleware' => 'cors','uses' => 'AuthController@delUser2']);
$router->options('/listUsers2', ['middleware' => 'cors','uses' => 'AuthController@listUsers2']);
$router->options('/addUser2', ['middleware' => 'cors','uses' => 'AuthController@addUser2']);
$router->options('/updateUser2', ['middleware' => 'cors','uses' => 'AuthController@updateUser2']);
$router->options('/delUser2', ['middleware' => 'cors','uses' => 'AuthController@delUser2']);

$router->get('/listPois2', ['middleware' => 'cors','uses' => 'PoiController@listPois2']);
$router->get('/addPoi2', ['middleware' => 'cors','uses' => 'PoiController@addPoi2']);
$router->get('/updatePoi2', ['middleware' => 'cors','uses' => 'PoiController@updatePoi2']);
$router->get('/delPoi2', ['middleware' => 'cors','uses' => 'PoiController@delPoi2']);
$router->options('/listPois2', ['middleware' => 'cors','uses' => 'PoiController@listPois2']);
$router->options('/addPoi2', ['middleware' => 'cors','uses' => 'PoiController@addPoi2']);
$router->options('/updatePoi2', ['middleware' => 'cors','uses' => 'PoiController@updatePoi2']);
$router->options('/delPoi2', ['middleware' => 'cors','uses' => 'PoiController@delPoi2']);

$router->get('/listDevices2', ['middleware' => 'cors','uses' => 'PoiController@listDevices2']);
$router->get('/addDevice2', ['middleware' => 'cors','uses' => 'PoiController@addDevice2']);
$router->get('/updateDevice2', ['middleware' => 'cors','uses' => 'PoiController@updateDevice2']);
$router->get('/delDevice2', ['middleware' => 'cors','uses' => 'PoiController@delDevice2']);
$router->options('/listDevices2', ['middleware' => 'cors','uses' => 'PoiController@listDevices2']);
$router->options('/addDevice2', ['middleware' => 'cors','uses' => 'PoiController@addDevice2']);
$router->options('/updateDevice2', ['middleware' => 'cors','uses' => 'PoiController@updateDevice2']);
$router->options('/delDevice2', ['middleware' => 'cors','uses' => 'PoiController@delDevice2']);

$router->get('/listSensors2', ['middleware' => 'cors','uses' => 'PoiController@listSensors2']);
$router->get('/addSensor2', ['middleware' => 'cors','uses' => 'PoiController@addSensor2']);
$router->get('/updateSensor2', ['middleware' => 'cors','uses' => 'PoiController@updateSensor2']);
$router->get('/delSensor2', ['middleware' => 'cors','uses' => 'PoiController@delSensor2']);
$router->options('/listSensors2', ['middleware' => 'cors','uses' => 'PoiController@listSensors2']);
$router->options('/addSensor2', ['middleware' => 'cors','uses' => 'PoiController@addSensor2']);
$router->options('/updateSensor2', ['middleware' => 'cors','uses' => 'PoiController@updateSensor2']);
$router->options('/delSensor2', ['middleware' => 'cors','uses' => 'PoiController@delSensor2']);



$router->get('/videosource', ['middleware' => 'cors','uses' => 'AuthController@videosource']);
$router->get('/videoList', ['middleware' => 'cors','uses' => 'AuthController@videoList']);
$router->options('/videosource', ['middleware' => 'cors','uses' => 'AuthController@videosource']);
$router->options('/videoList', ['middleware' => 'cors','uses' => 'AuthController@videoList']);

$router->options('/videoHistory', ['middleware' => 'cors','uses' => 'AuthController@videoHistory']);
$router->get('/videoHistory', ['middleware' => 'cors','uses' => 'AuthController@videoHistory']);

$router->options('/insar', ['middleware' => 'cors','uses' => 'PoiController@insar']);
$router->get('/insar', ['middleware' => 'cors','uses' => 'PoiController@insar']);
$router->options('/insarData', ['middleware' => 'cors','uses' => 'PoiController@insarData']);
$router->get('/insarData', ['middleware' => 'cors','uses' => 'PoiController@insarData']);
$router->options('/genImage', ['middleware' => 'cors','uses' => 'PoiController@genImage']);
$router->get('/genImage', ['middleware' => 'cors','uses' => 'PoiController@genImage']);

$router->options('/GaodeCoord', ['middleware' => 'cors','uses' => 'PoiController@GaodeCoord']);
$router->get('/GaodeCoord', ['middleware' => 'cors','uses' => 'PoiController@GaodeCoord']);


$router->options('/deviceAlarmsById', ['middleware' => 'cors','uses' => 'AlarmController@deviceAlarmsById']);
$router->get('/deviceAlarmsById', ['middleware' => 'cors','uses' => 'AlarmController@deviceAlarmsById']);

$router->options('/cameraAlarmsById', ['middleware' => 'cors','uses' => 'AlarmController@cameraAlarmsById']);
$router->get('/cameraAlarmsById', ['middleware' => 'cors','uses' => 'AlarmController@cameraAlarmsById']);


//$router->options('/sensorAlarmsById', ['middleware' => 'cors','uses' => 'AlarmController@sensorAlarmsById']);
//$router->get('/sensorAlarmsById', ['middleware' => 'cors','uses' => 'AlarmController@sensorAlarmsById']);

$router->options('/alarmsSensor', ['middleware' => 'cors','uses' => 'AlarmController@alarmsSensor']);
$router->get('/alarmsSensor', ['middleware' => 'cors','uses' => 'AlarmController@alarmsSensor']);

$router->options('/alarmsDevice', ['middleware' => 'cors','uses' => 'AlarmController@alarmsDevice']);
$router->get('/alarmsDevice', ['middleware' => 'cors','uses' => 'AlarmController@alarmsDevice']);

$router->options('/alarmsCamera', ['middleware' => 'cors','uses' => 'AlarmController@alarmsCamera']);
$router->get('/alarmsCamera', ['middleware' => 'cors','uses' => 'AlarmController@alarmsCamera']);



$router->options('/UsersLog', ['middleware' => 'cors','uses' => 'AuthController@UsersLog']);
$router->get('/UsersLog', ['middleware' => 'cors','uses' => 'AuthController@UsersLog']);
$router->post('/addUserLog', ['middleware' => 'cors','uses' => 'AuthController@addUserLog']);
$router->options('/addUserLog', ['middleware' => 'cors','uses' => 'AuthController@addUserLog']);





$router->post('/addPoiInfo', ['middleware' => 'cors','uses' => 'PoiController@addPoiInfo']);
$router->options('/addPoiInfo', ['middleware' => 'cors','uses' => 'PoiController@addPoiInfo']);

$router->post('/getPoiInfo', ['middleware' => 'cors','uses' => 'PoiController@getPoiInfo']);
$router->options('/getPoiInfo', ['middleware' => 'cors','uses' => 'PoiController@getPoiInfo']);

$router->post('/updatePoiInfo', ['middleware' => 'cors','uses' => 'PoiController@updatePoiInfo']);
$router->options('/updatePoiInfo', ['middleware' => 'cors','uses' => 'PoiController@updatePoiInfo']);

$router->post('/delPoiInfo', ['middleware' => 'cors','uses' => 'PoiController@delPoiInfo']);
$router->options('/delPoiInfo', ['middleware' => 'cors','uses' => 'PoiController@delPoiInfo']);

$router->post('/deviceData', ['middleware' => 'cors','uses' => 'PoiController@deviceData']);
$router->get('/deviceData', ['middleware' => 'cors','uses' => 'PoiController@deviceData']);
$router->options('/deviceData', ['middleware' => 'cors','uses' => 'PoiController@deviceData']);

$router->post('/deviceData2', ['middleware' => 'cors','uses' => 'PoiController@deviceData2']);
$router->get('/deviceData2', ['middleware' => 'cors','uses' => 'PoiController@deviceData2']);
$router->options('/deviceData2', ['middleware' => 'cors','uses' => 'PoiController@deviceData2']);



$router->post('/test', ['middleware' => 'cors','uses' => 'PoiController@test']);
$router->get('/test', ['middleware' => 'cors','uses' => 'PoiController@test']);
$router->options('/test', ['middleware' => 'cors','uses' => 'PoiController@test']);


$router->post('/login3', ['middleware' => 'cors','uses' => 'AuthController@login3']);
$router->get('/login3', ['middleware' => 'cors','uses' => 'AuthController@login3']);
$router->options('/login3', ['middleware' => 'cors','uses' => 'AuthController@login3']);

$router->post('/getMapInfo', ['middleware' => 'cors','uses' => 'ProjectController@getMapInfo']);
$router->get('/getMapInfo', ['middleware' => 'cors','uses' => 'ProjectController@getMapInfo']);
$router->options('/getMapInfo', ['middleware' => 'cors','uses' => 'ProjectController@getMapInfo']);


$router->post('/resetPwd', ['middleware' => 'cors','uses' => 'AuthController@resetPwd']);
$router->get('/resetPwd', ['middleware' => 'cors','uses' => 'AuthController@resetPwd']);
$router->options('/resetPwd', ['middleware' => 'cors','uses' => 'AuthController@resetPwd']);

$router->post('/getVideopic', ['middleware' => 'cors','uses' => 'PoiController@getVideopic']);
$router->get('/getVideopic', ['middleware' => 'cors','uses' => 'PoiController@getVideopic']);
$router->options('/getVideopic', ['middleware' => 'cors','uses' => 'PoiController@getVideopic']);


$router->post('/getVideoPicByDate', ['middleware' => 'cors','uses' => 'PoiController@getVideoPicByDate']);
$router->get('/getVideoPicByDate', ['middleware' => 'cors','uses' => 'PoiController@getVideoPicByDate']);
$router->options('/getVideoPicByDate', ['middleware' => 'cors','uses' => 'PoiController@getVideoPicByDate']);




$router->post('/addCameras', ['middleware' => 'cors','uses' => 'PoiController@addCameras']);
$router->get('/addCameras', ['middleware' => 'cors','uses' => 'PoiController@addCameras']);
$router->options('/addCameras', ['middleware' => 'cors','uses' => 'PoiController@addCameras']);

$router->post('/updateCameras', ['middleware' => 'cors','uses' => 'PoiController@updateCameras']);
$router->get('/updateCameras', ['middleware' => 'cors','uses' => 'PoiController@updateCameras']);
$router->options('/updateCameras', ['middleware' => 'cors','uses' => 'PoiController@updateCameras']);

$router->post('/delCameras', ['middleware' => 'cors','uses' => 'PoiController@delCameras']);
$router->get('/delCameras', ['middleware' => 'cors','uses' => 'PoiController@delCameras']);
$router->options('/delCameras', ['middleware' => 'cors','uses' => 'PoiController@delCameras']);

$router->post('/getCameras', ['middleware' => 'cors','uses' => 'PoiController@getCameras']);
$router->get('/getCameras', ['middleware' => 'cors','uses' => 'PoiController@getCameras']);
$router->options('/getCameras', ['middleware' => 'cors','uses' => 'PoiController@getCameras']);




$router->post('/addQianXun', ['middleware' => 'cors','uses' => 'ProjectController@addQianXun']);
$router->get('/addQianXun', ['middleware' => 'cors','uses' => 'ProjectController@addQianXun']);
$router->options('/addQianXun', ['middleware' => 'cors','uses' => 'ProjectController@addQianXun']);

$router->post('/delQianXun', ['middleware' => 'cors','uses' => 'ProjectController@delQianXun']);
$router->get('/delQianXun', ['middleware' => 'cors','uses' => 'ProjectController@delQianXun']);
$router->options('/delQianXun', ['middleware' => 'cors','uses' => 'ProjectController@delQianXun']);

$router->post('/updateQianXun', ['middleware' => 'cors','uses' => 'ProjectController@updateQianXun']);
$router->get('/updateQianXun', ['middleware' => 'cors','uses' => 'ProjectController@updateQianXun']);
$router->options('/updateQianXun', ['middleware' => 'cors','uses' => 'ProjectController@updateQianXun']);

$router->post('/getQianXun', ['middleware' => 'cors','uses' => 'ProjectController@getQianXun']);
$router->get('/getQianXun', ['middleware' => 'cors','uses' => 'ProjectController@getQianXun']);
$router->options('/getQianXun', ['middleware' => 'cors','uses' => 'ProjectController@getQianXun']);


$router->post('/acceptNBData', ['middleware' => 'cors','uses' => 'PoiController@acceptNBData']);
$router->get('/acceptNBData', ['middleware' => 'cors','uses' => 'PoiController@acceptNBData']);
$router->options('/acceptNBData', ['middleware' => 'cors','uses' => 'PoiController@acceptNBData']);

$router->post('/addMoreDevice', ['middleware' => 'cors','uses' => 'PoiController@addMoreDevice']);
$router->get('/addMoreDevice', ['middleware' => 'cors','uses' => 'PoiController@addMoreDevice']);
$router->options('/addMoreDevice', ['middleware' => 'cors','uses' => 'PoiController@addMoreDevice']);

$router->post('/testDeviceData', ['middleware' => 'cors','uses' => 'PoiController@testDeviceData']);
$router->get('/testDeviceData', ['middleware' => 'cors','uses' => 'PoiController@testDeviceData']);
$router->options('/testDeviceData', ['middleware' => 'cors','uses' => 'PoiController@testDeviceData']);

$router->post('/addDeviceTest', ['middleware' => 'cors','uses' => 'PoiController@addDeviceTest']);
$router->get('/addDeviceTest', ['middleware' => 'cors','uses' => 'PoiController@addDeviceTest']);
$router->options('/addDeviceTest', ['middleware' => 'cors','uses' => 'PoiController@addDeviceTest']);

$router->post('/delDeviceTest', ['middleware' => 'cors','uses' => 'PoiController@delDeviceTest']);
$router->get('/delDeviceTest', ['middleware' => 'cors','uses' => 'PoiController@delDeviceTest']);
$router->options('/delDeviceTest', ['middleware' => 'cors','uses' => 'PoiController@delDeviceTest']);

$router->post('/updateDeviceTest', ['middleware' => 'cors','uses' => 'PoiController@updateDeviceTest']);
$router->get('/updateDeviceTest', ['middleware' => 'cors','uses' => 'PoiController@updateDeviceTest']);
$router->options('/updateDeviceTest', ['middleware' => 'cors','uses' => 'PoiController@updateDeviceTest']);

$router->post('/getDeviceTest', ['middleware' => 'cors','uses' => 'PoiController@getDeviceTest']);
$router->get('/getDeviceTest', ['middleware' => 'cors','uses' => 'PoiController@getDeviceTest']);
$router->options('/getDeviceTest', ['middleware' => 'cors','uses' => 'PoiController@getDeviceTest']);

$router->post('/addMoreDeviceTest', ['middleware' => 'cors','uses' => 'PoiController@addMoreDeviceTest']);
$router->get('/addMoreDeviceTest', ['middleware' => 'cors','uses' => 'PoiController@addMoreDeviceTest']);
$router->options('/addMoreDeviceTest', ['middleware' => 'cors','uses' => 'PoiController@addMoreDeviceTest']);


$router->post('/getSomeThingByProjectId', ['middleware' => 'cors','uses' => 'PoiController@getSomeThingByProjectId']);
$router->get('/getSomeThingByProjectId', ['middleware' => 'cors','uses' => 'PoiController@getSomeThingByProjectId']);
$router->options('/getSomeThingByProjectId', ['middleware' => 'cors','uses' => 'PoiController@getSomeThingByProjectId']);

$router->post('/getOtherThingsByProjectId', ['middleware' => 'cors','uses' => 'PoiController@getOtherThingsByProjectId']);
$router->get('/getOtherThingsByProjectId', ['middleware' => 'cors','uses' => 'PoiController@getOtherThingsByProjectId']);
$router->options('/getOtherThingsByProjectId', ['middleware' => 'cors','uses' => 'PoiController@getOtherThingsByProjectId']);

$router->post('/getSomeThingByDeviceId', ['middleware' => 'cors','uses' => 'PoiController@getSomeThingByDeviceId']);
$router->get('/getSomeThingByDeviceId', ['middleware' => 'cors','uses' => 'PoiController@getSomeThingByDeviceId']);
$router->options('/getSomeThingByDeviceId', ['middleware' => 'cors','uses' => 'PoiController@getSomeThingByDeviceId']);

$router->post('/getOtherThingsByDeviceId', ['middleware' => 'cors','uses' => 'PoiController@getOtherThingsByDeviceId']);
$router->get('/getOtherThingsByDeviceId', ['middleware' => 'cors','uses' => 'PoiController@getOtherThingsByDeviceId']);
$router->options('/getOtherThingsByDeviceId', ['middleware' => 'cors','uses' => 'PoiController@getOtherThingsByDeviceId']);

$router->post('/getSensorInfoByDeviceId', ['middleware' => 'cors','uses' => 'PoiController@getSensorInfoByDeviceId']);
$router->get('/getSensorInfoByDeviceId', ['middleware' => 'cors','uses' => 'PoiController@getSensorInfoByDeviceId']);
$router->options('/getSensorInfoByDeviceId', ['middleware' => 'cors','uses' => 'PoiController@getSensorInfoByDeviceId']);

$router->post('/searchPoiReturnCorrectDevice', ['middleware' => 'cors','uses' => 'PoiController@searchPoiReturnCorrectDevice']);
$router->get('/searchPoiReturnCorrectDevice', ['middleware' => 'cors','uses' => 'PoiController@searchPoiReturnCorrectDevice']);
$router->options('/searchPoiReturnCorrectDevice', ['middleware' => 'cors','uses' => 'PoiController@searchPoiReturnCorrectDevice']);