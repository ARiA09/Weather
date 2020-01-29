<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\WeatherMonitor;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WeatherMonitorController extends Controller
{
    public function index()
    {
        return WeatherMonitor::all()->groupBy('address');
    }

    public function show(WeatherMonitor $weatherMonitor)
    {
        return $weatherMonitor;
    }

    public function store(Request $request)
    {

        $location = Location::updateOrCreate(
            [
                'address' => $request['address']
            ],
            [
                'address' => $request['address']
            ]);

        $weatherMonitor = $location->weatherMonitors()->create($request->all());
        $weatherMonitor->location()->associate($location);

        return response()->json($weatherMonitor, 201);
    }

    public function update(Request $request, WeatherMonitor $weatherMonitor)
    {
        $weatherMonitor->update($request->all());

        return response()->json($weatherMonitor, 200);
    }

    public function delete(WeatherMonitor $weatherMonitor)
    {
        $weatherMonitor->delete();

        return response()->json(null, 204);
    }

    /*Custom API*/
    public function needUpdate(Request $request)
    {
        $timeNow = Carbon::now();

        $dt = $timeNow->format('Y-m-d H:00:00');
        $dt1 = $timeNow->addHour()->format('Y-m-d H:00:00');

        $weatherMonitor = WeatherMonitor::all()
            ->load('location')
            ->where('address', $request['address'])
            ->whereBetween('created_at', [$dt, $dt1])
            ->first();

        return $weatherMonitor ?
            response()->json(1, 200) :
            response()->json(0, 200);
    }

}
