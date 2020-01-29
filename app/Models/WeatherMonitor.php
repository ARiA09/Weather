<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeatherMonitor extends Model
{

    const UPDATED_AT = null;
    protected $appends = array('address');

    // code for $this->mimeType attribute
    protected $hidden = ['wmId', 'locId', 'location'];
    protected $primaryKey = 'wmId';
    protected $fillable = ['PM1', 'PM2_5', 'PM10', 'O3', 'CO', 'SO2', 'NO2'];

    public function location()
    {
        return $this->belongsTo(Location::class, 'locId');
    }

    public function getAddressAttribute($value)
    {
        $address = null;
        if ($this->location) {
            $address = $this->location->address;
        }
        return $address;
    }

}
