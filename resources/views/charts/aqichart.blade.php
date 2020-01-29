@extends('layouts.app')

@section('content')
    <div class="flex-center position-ref full-height">
        <div class="content">
                <script src="https://code.jquery.com/jquery-3.3.1.js"></script>
            <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
            <link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
            <div class="title m-b-md">
                <script>
                    var dataDate = {!! $dataDate !!};
                    var dataPM25 = {!! $PM25 !!};
                    var dataPM10 = {!! $PM10 !!};

                    var barChartData = {
                        labels: dataDate,
                        datasets: [{
                            label: 'PM 2.5',
                            backgroundColor: "rgba(220,220,220,0.5)",
                            data: dataPM25
                        }, {
                            label: 'PM 10',
                            backgroundColor: "rgba(151,187,205,0.5)",
                            data: dataPM10
                        }]
                    };


                    window.onload = function () {
                        var ctx = document.getElementById("canvas").getContext("2d");
                        window.myChart = new Chart(ctx, {
                            type: 'line',
                            data: barChartData,
                            options: {
                                scales: {
                                    yAxes: [{
                                        ticks: {
                                            beginAtZero: true
                                        }
                                    }]
                                }
                            }
                        });
                        $('#dateChart .input-group.date').datepicker({
                            todayBtn: true,
                            clearBtn: true
                        });
                    };


                </script>
                <canvas id="canvas" height="280" width="600"></canvas>
                <div id="dateChart">
                    <div class="input-group date">
                        <input type="text" class="form-control">
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
