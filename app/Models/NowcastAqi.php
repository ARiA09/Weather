<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NowcastAqi extends Model
{

    const UPDATED_AT = null;
    protected $dateFormat = 'Y-m-d H:00:00';
    protected $hidden = ['ncId', 'locId'];

    protected $primaryKey = 'ncId';
    protected $fillable =
            [
                    'PM1', 'PM2_5', 'PM10', 'O3', 'CO', 'SO2', 'NO2',
                    'VN_PM1', 'VN_PM2_5', 'VN_PM10', 'VN_O3', 'VN_CO', 'VN_SO2', 'VN_NO2'
            ];

    public function location()
    {
        return $this->belongsTo(Location::class, 'locId');
    }
}
