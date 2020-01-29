<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//Route::middleware('auth:api')->get('/user', function (Request $request) {
//    return $request->user();
//});

/* Custom APIs */
Route::post('weatherMonitors/needUpdate', 'WeatherMonitorController@needUpdate');
Route::get('nowCast/updateAQI', 'NowcastAqiController@updateAQI');
Route::get('nowCast/testAPI', 'NowcastAqiController@testAPI');


/* Default APIs */
Route::resource('weatherMonitors', 'WeatherMonitorController');
Route::resource('locations', 'LocationController');
Route::resource('nowCast', 'NowcastAqiController');
