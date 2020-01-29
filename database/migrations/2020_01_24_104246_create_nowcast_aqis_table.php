<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNowcastAqisTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nowcast_aqis', function (Blueprint $table) {
            $table->increments('ncId');
            $table->integer('locId')->unsigned();

            //For US_AQI
            $table->double('PM1')->nullable()->default(0);
            $table->double('PM2_5')->nullable()->default(0);
            $table->double('PM10')->nullable()->default(0);
            $table->double('O3')->nullable()->default(0);
            $table->double('CO')->nullable()->default(0);
            $table->double('SO2')->nullable()->default(0);
            $table->double('NO2')->nullable()->default(0);

            //For VN_AQI
            $table->double('VN_PM1')->nullable()->default(0);
            $table->double('VN_PM2_5')->nullable()->default(0);
            $table->double('VN_PM10')->nullable()->default(0);
            $table->double('VN_O3')->nullable()->default(0);
            $table->double('VN_CO')->nullable()->default(0);
            $table->double('VN_SO2')->nullable()->default(0);
            $table->double('VN_NO2')->nullable()->default(0);

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
        Schema::dropIfExists('nowcast_aqis');
    }
}
