<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\NowcastAqi;
use App\Models\WeatherMonitor;
use Carbon\Carbon;
use Illuminate\Http\Request;

class NowcastAqiController extends Controller
{

    public function index()
    {
        return NowcastAqi::all()->groupBy('address');
    }

    public function show(NowcastAqi $nowcastAqi)
    {
        return $nowcastAqi;
    }

    public function store(Request $request)
    {
        $nowcastAqi = NowcastAqi::create($request->all());

        return response()->json($nowcastAqi, 201);
    }

    public function update(Request $request, NowcastAqi $nowcastAqi)
    {
        $nowcastAqi->update($request->all());

        return response()->json($nowcastAqi, 200);
    }

    public function delete(NowcastAqi $nowcastAqi)
    {
        $nowcastAqi->delete();

        return response()->json(null, 204);
    }

    /*Custom API*/
    public function updateAQI()
    {

        $locations = WeatherMonitor::select('locId')->distinct()->get()->toArray();

        foreach ($locations as $aLocation) {

            $dataWeather = $this->getDataConcentration($aLocation['address']);

            // Get Data
            //PM2.5
            $arrPM25 = array_column($dataWeather, 'PM2_5');
            $nowCastPM25 = $this->calNowCast($arrPM25);
            //PM10
            $arrPM10 = array_column($dataWeather, 'PM10');
            $nowCastPM10 = $this->calNowCast($arrPM10);
            //O3
            // NOT FINISH ---- Need Update For New Update Base On:
            // https://forum.airnowtech.org/t/the-nowcast-for-ozone-2019-update-partial-least-squares-method/356
            $arrPM10 = array_column($dataWeather, 'O3');

            //US AQI
            $AQIPM25 = $this->calAQI('PM25', $nowCastPM25, 'US_AQI');
            $AQIPM10 = $this->calAQI('PM10', $nowCastPM10, 'US_AQI');
            $AQIO3 = $this->calAQI('O3', $arrPM10[0], 'US_AQI');

            //VN AQI
            $VN_AQIPM25 = $this->calAQI('PM25', $nowCastPM25, 'VN_AQI');
            $VN_AQIPM10 = $this->calAQI('PM10', $nowCastPM10, 'VN_AQI');
            $VN_AQIO3 = $this->calAQI('O3', $arrPM10[0], 'VN_AQI');

            $location = Location::select('locId')->get()->first();
            $location->nowcastAqis()->updateOrCreate
            (
                    [
                            'locId' => $location['locId'], 'created_at' => Carbon::now()->format('Y-m-d H:00:00')
                    ],
                    [
                            'PM2_5' => round($AQIPM25), 'PM10' => round($AQIPM10), 'O3' => round($AQIO3),
                            'VN_PM2_5' => round($VN_AQIPM25), 'VN_PM10' => round($VN_AQIPM10), 'VN_O3' => round($VN_AQIO3)
                    ]
            );
        }

        return response()->json('UPDATE', 200);

    }

    private
    function getDataConcentration(
            $address
    ) {
        $timeNow = Carbon::now();
        $dt = $timeNow->format('Y-m-d H:59:59');
        $dt1 = $timeNow->subHours(11)->format('Y-m-d H:00:00');


        $concentration = WeatherMonitor::all()
                ->load('location')
                ->whereBetween('created_at', [$dt1, $dt])
                ->where('address', $address)
                ->sortByDesc('created_at')->toArray();

        return $concentration;
    }


    private
    function calNowCast(
            $data
    ) {

        /* 1 - Compute the concentrations range (max-min) over the last 12 hours. */
        $min = min(array_diff($data, array(0)));
        $max = max($data);
        $range = $max - $min;

        /* 2 - Divide the range by the maximum concentration in the 12 hour period
        to obtain the scaled rate of change. */
        $scaled = $range / $max;

        /* 3 - Compute the weight factor by subtracting the scaled rate from 1.
        The weight factor must be between .5 and 1. The minimum limit approximates a 3-hour average.
        If the weight factor is less than .5, then set it equal to .5. */
        $weightFactor = 1 - $scaled;
        if ($weightFactor <= .5) {
            $weightFactor = .5;
        } elseif ($weightFactor >= 1) {
            $weightFactor = 1;
        }

        /* 4 - Multiply each hourly concentration by the weight factor raised to the power of how many hours ago
        the concentration was measured (for the current hour, the factor is raised to the zero power). */

        /* 5 - Compute the NowCast by summing these products and dividing by the sum of the weight factors
        raised to the power of how many hours ago the concentration was measured. */

        $i = 0;
        $factor1 = 0;
        $factor2 = 0;

        foreach ($data as $aData) {
            if ($aData <= 0) {
                $i++;
                continue;
            }
            $factor1 += $aData * pow($weightFactor, $i);
            $factor2 += pow($weightFactor, $i);
            $i++;
        }

        return $factor1 / $factor2;
    }

    private
    function getIndexAQI(
            $type,
            $data,
            $typeAqi
    ) {
        $INDEX_CON = [];

        if ($typeAqi == 'VN_AQI') {
            if ($data >= config('constants.'.$typeAqi.'.'.$type.'.GOOD.C.LOW')
                    && $data <= config('constants.'.$typeAqi.'.'.$type.'.GOOD.C.HIGH')) {
                $INDEX_CON[0] = config('constants.'.$typeAqi.'.'.$type.'.GOOD.I.LOW');
                $INDEX_CON[1] = config('constants.'.$typeAqi.'.'.$type.'.GOOD.I.HIGH');
                $INDEX_CON[2] = config('constants.'.$typeAqi.'.'.$type.'.GOOD.C.LOW');
                $INDEX_CON[3] = config('constants.'.$typeAqi.'.'.$type.'.GOOD.C.HIGH');

            } elseif ($data > config('constants.'.$typeAqi.'.'.$type.'.MODERATE.C.LOW')
                    && $data <= config('constants.'.$typeAqi.'.'.$type.'.MODERATE.C.HIGH')) {
                $INDEX_CON[0] = config('constants.'.$typeAqi.'.'.$type.'.MODERATE.I.LOW');
                $INDEX_CON[1] = config('constants.'.$typeAqi.'.'.$type.'.MODERATE.I.HIGH');
                $INDEX_CON[2] = config('constants.'.$typeAqi.'.'.$type.'.MODERATE.C.LOW');
                $INDEX_CON[3] = config('constants.'.$typeAqi.'.'.$type.'.MODERATE.C.HIGH');

            } elseif ($data > config('constants.'.$typeAqi.'.'.$type.'.UNHEALTHY_SENSITIVE.C.LOW')
                    && $data <= config('constants.'.$typeAqi.'.'.$type.'.UNHEALTHY_SENSITIVE.C.HIGH')) {
                $INDEX_CON[0] = config('constants.'.$typeAqi.'.'.$type.'.UNHEALTHY_SENSITIVE.I.LOW');
                $INDEX_CON[1] = config('constants.'.$typeAqi.'.'.$type.'.UNHEALTHY_SENSITIVE.I.HIGH');
                $INDEX_CON[2] = config('constants.'.$typeAqi.'.'.$type.'.UNHEALTHY_SENSITIVE.C.LOW');
                $INDEX_CON[3] = config('constants.'.$typeAqi.'.'.$type.'.UNHEALTHY_SENSITIVE.C.HIGH');

            } elseif ($data > config('constants.'.$typeAqi.'.'.$type.'.UNHEALTHY.C.LOW')
                    && $data <= config('constants.'.$typeAqi.'.'.$type.'.UNHEALTHY.C.HIGH')) {
                $INDEX_CON[0] = config('constants.'.$typeAqi.'.'.$type.'.UNHEALTHY.I.LOW');
                $INDEX_CON[1] = config('constants.'.$typeAqi.'.'.$type.'.UNHEALTHY.I.HIGH');
                $INDEX_CON[2] = config('constants.'.$typeAqi.'.'.$type.'.UNHEALTHY.C.LOW');
                $INDEX_CON[3] = config('constants.'.$typeAqi.'.'.$type.'.UNHEALTHY.C.HIGH');

            } elseif ($data > config('constants.'.$typeAqi.'.'.$type.'.VERY_UNHEALTHY.C.LOW')
                    && $data <= config('constants.'.$typeAqi.'.'.$type.'.VERY_UNHEALTHY.C.HIGH')) {
                $INDEX_CON[0] = config('constants.'.$typeAqi.'.'.$type.'.VERY_UNHEALTHY.I.LOW');
                $INDEX_CON[1] = config('constants.'.$typeAqi.'.'.$type.'.VERY_UNHEALTHY.I.HIGH');
                $INDEX_CON[2] = config('constants.'.$typeAqi.'.'.$type.'.VERY_UNHEALTHY.C.LOW');
                $INDEX_CON[3] = config('constants.'.$typeAqi.'.'.$type.'.VERY_UNHEALTHY.C.HIGH');

            } elseif ($data > config('constants.'.$typeAqi.'.'.$type.'.HAZARDOUS.C.LOW')
                    && $data <= config('constants.'.$typeAqi.'.'.$type.'.HAZARDOUS.C.HIGH')) {
                $INDEX_CON[0] = config('constants.'.$typeAqi.'.'.$type.'.HAZARDOUS.I.LOW');
                $INDEX_CON[1] = config('constants.'.$typeAqi.'.'.$type.'.HAZARDOUS.I.HIGH');
                $INDEX_CON[2] = config('constants.'.$typeAqi.'.'.$type.'.HAZARDOUS.C.LOW');
                $INDEX_CON[3] = config('constants.'.$typeAqi.'.'.$type.'.HAZARDOUS.C.HIGH');

            } elseif ($data > config('constants.'.$typeAqi.'.'.$type.'.VERY_HAZARDOUS.C.LOW')
                    && $data <= config('constants.'.$typeAqi.'.'.$type.'.VERY_HAZARDOUS.C.HIGH')) {
                $INDEX_CON[0] = config('constants.'.$typeAqi.'.'.$type.'.VERY_HAZARDOUS.I.LOW');
                $INDEX_CON[1] = config('constants.'.$typeAqi.'.'.$type.'.VERY_HAZARDOUS.I.HIGH');
                $INDEX_CON[2] = config('constants.'.$typeAqi.'.'.$type.'.VERY_HAZARDOUS.C.LOW');
                $INDEX_CON[3] = config('constants.'.$typeAqi.'.'.$type.'.VERY_HAZARDOUS.C.HIGH');

            } else {
                $INDEX_CON[0] = 0;
                $INDEX_CON[1] = 0;
                $INDEX_CON[2] = 0;
                $INDEX_CON[3] = 0;
            }
        } else if ($typeAqi == 'US_AQI') {
            if ($data >= config('constants.'.$typeAqi.'.'.$type.'.GOOD.C.LOW')
                    && $data <= config('constants.'.$typeAqi.'.'.$type.'.GOOD.C.HIGH')) {
                $INDEX_CON[0] = config('constants.'.$typeAqi.'.'.$type.'.GOOD.I.LOW');
                $INDEX_CON[1] = config('constants.'.$typeAqi.'.'.$type.'.GOOD.I.HIGH');
                $INDEX_CON[2] = config('constants.'.$typeAqi.'.'.$type.'.GOOD.C.LOW');
                $INDEX_CON[3] = config('constants.'.$typeAqi.'.'.$type.'.GOOD.C.HIGH');

            } elseif ($data >= config('constants.'.$typeAqi.'.'.$type.'.MODERATE.C.LOW')
                    && $data <= config('constants.'.$typeAqi.'.'.$type.'.MODERATE.C.HIGH')) {
                $INDEX_CON[0] = config('constants.'.$typeAqi.'.'.$type.'.MODERATE.I.LOW');
                $INDEX_CON[1] = config('constants.'.$typeAqi.'.'.$type.'.MODERATE.I.HIGH');
                $INDEX_CON[2] = config('constants.'.$typeAqi.'.'.$type.'.MODERATE.C.LOW');
                $INDEX_CON[3] = config('constants.'.$typeAqi.'.'.$type.'.MODERATE.C.HIGH');

            } elseif ($data >= config('constants.'.$typeAqi.'.'.$type.'.UNHEALTHY_SENSITIVE.C.LOW')
                    && $data <= config('constants.'.$typeAqi.'.'.$type.'.UNHEALTHY_SENSITIVE.C.HIGH')) {
                $INDEX_CON[0] = config('constants.'.$typeAqi.'.'.$type.'.UNHEALTHY_SENSITIVE.I.LOW');
                $INDEX_CON[1] = config('constants.'.$typeAqi.'.'.$type.'.UNHEALTHY_SENSITIVE.I.HIGH');
                $INDEX_CON[2] = config('constants.'.$typeAqi.'.'.$type.'.UNHEALTHY_SENSITIVE.C.LOW');
                $INDEX_CON[3] = config('constants.'.$typeAqi.'.'.$type.'.UNHEALTHY_SENSITIVE.C.HIGH');

            } elseif ($data >= config('constants.'.$typeAqi.'.'.$type.'.UNHEALTHY.C.LOW')
                    && $data <= config('constants.'.$typeAqi.'.'.$type.'.UNHEALTHY.C.HIGH')) {
                $INDEX_CON[0] = config('constants.'.$typeAqi.'.'.$type.'.UNHEALTHY.I.LOW');
                $INDEX_CON[1] = config('constants.'.$typeAqi.'.'.$type.'.UNHEALTHY.I.HIGH');
                $INDEX_CON[2] = config('constants.'.$typeAqi.'.'.$type.'.UNHEALTHY.C.LOW');
                $INDEX_CON[3] = config('constants.'.$typeAqi.'.'.$type.'.UNHEALTHY.C.HIGH');

            } elseif ($data >= config('constants.'.$typeAqi.'.'.$type.'.VERY_UNHEALTHY.C.LOW')
                    && $data <= config('constants.'.$typeAqi.'.'.$type.'.VERY_UNHEALTHY.C.HIGH')) {
                $INDEX_CON[0] = config('constants.'.$typeAqi.'.'.$type.'.VERY_UNHEALTHY.I.LOW');
                $INDEX_CON[1] = config('constants.'.$typeAqi.'.'.$type.'.VERY_UNHEALTHY.I.HIGH');
                $INDEX_CON[2] = config('constants.'.$typeAqi.'.'.$type.'.VERY_UNHEALTHY.C.LOW');
                $INDEX_CON[3] = config('constants.'.$typeAqi.'.'.$type.'.VERY_UNHEALTHY.C.HIGH');

            } elseif ($data >= config('constants.'.$typeAqi.'.'.$type.'.HAZARDOUS.C.LOW')
                    && $data <= config('constants.'.$typeAqi.'.'.$type.'.HAZARDOUS.C.HIGH')) {
                $INDEX_CON[0] = config('constants.'.$typeAqi.'.'.$type.'.HAZARDOUS.I.LOW');
                $INDEX_CON[1] = config('constants.'.$typeAqi.'.'.$type.'.HAZARDOUS.I.HIGH');
                $INDEX_CON[2] = config('constants.'.$typeAqi.'.'.$type.'.HAZARDOUS.C.LOW');
                $INDEX_CON[3] = config('constants.'.$typeAqi.'.'.$type.'.HAZARDOUS.C.HIGH');

            } elseif ($data >= config('constants.'.$typeAqi.'.'.$type.'.VERY_HAZARDOUS.C.LOW')
                    && $data <= config('constants.'.$typeAqi.'.'.$type.'.VERY_HAZARDOUS.C.HIGH')) {
                $INDEX_CON[0] = config('constants.'.$typeAqi.'.'.$type.'.VERY_HAZARDOUS.I.LOW');
                $INDEX_CON[1] = config('constants.'.$typeAqi.'.'.$type.'.VERY_HAZARDOUS.I.HIGH');
                $INDEX_CON[2] = config('constants.'.$typeAqi.'.'.$type.'.VERY_HAZARDOUS.C.LOW');
                $INDEX_CON[3] = config('constants.'.$typeAqi.'.'.$type.'.VERY_HAZARDOUS.C.HIGH');

            } else {
                $INDEX_CON[0] = 0;
                $INDEX_CON[1] = 0;
                $INDEX_CON[2] = 0;
                $INDEX_CON[3] = 0;
            }
        }
        return $INDEX_CON;
    }

    private
    function calAQI(
            $type,
            $data,
            $typeAqi
    ) {
        $index = $this->getIndexAQI($type, $data, $typeAqi);

        if ($index[0] <= 0 && $index[1] <= 0) {
            return 500;
        }

        return (($index[1] - $index[0]) / ($index[3] - $index[2])) * ($data - $index[2]) + $index[0];
    }
}
