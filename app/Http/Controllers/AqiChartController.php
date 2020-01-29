<?php

namespace App\Http\Controllers;

use App\Charts\AqiChart;
use App\Models\WeatherMonitor;

class AqiChartController extends Controller
{
    public function index()
    {

        $PM25 = collect([]);
        $PM10 = collect([]);
        $dataDate = collect([]);
        $data = WeatherMonitor::whereDate('created_at', '2020-01-26')->get();

        foreach ($data as $aData) {
            $PM25->push($aData['PM2_5']);
            $PM10->push($aData['PM10']);
            $dataDate->push($aData['created_at']->format('H:00:00'));
        }


        return view('charts.aqichart', compact(['PM25', 'PM10', 'dataDate']));
    }
}
