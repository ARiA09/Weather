<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NowcastAqi extends Model
{

    const UPDATED_AT = null;
    protected $dateFormat = 'Y-m-d H:00:00';
    protected $hidden = ['ncId', 'locId'];

    protected $primaryKey = 'ncId';
    protected $fillable = ['PM1', 'PM2_5', 'PM10', 'O3', 'CO', 'SO2', 'NO2'];

    public function location()
    {
        return $this->belongsTo(Location::class, 'locId');
    }
}
