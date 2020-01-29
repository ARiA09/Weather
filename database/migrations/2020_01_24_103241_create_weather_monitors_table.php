<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWeatherMonitorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('weather_monitors', function (Blueprint $table) {
            $table->increments('wmId');
            $table->integer('locId')->unsigned();
            $table->double('PM1')->nullable()->default(0);
            $table->double('PM2_5')->nullable()->default(0);
            $table->double('PM10')->nullable()->default(0);
            $table->double('O3')->nullable()->default(0);
            $table->double('CO')->nullable()->default(0);
            $table->double('SO2')->nullable()->default(0);
            $table->double('NO2')->nullable()->default(0);
            $table->double('Temp')->nullable()->default(0);
            $table->double('Hum')->nullable()->default(0);

            $table->dateTimeTz('created_at');

            $table->foreign('locId')->references('locId')->on('locations')
                ->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('weather_monitors');
    }
}
