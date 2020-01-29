<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{

    const UPDATED_AT = null;
    protected $primaryKey = 'locId';
    protected $hidden = ['locId'];
    protected $fillable = ['address', 'note'];

    public function weatherMonitors()
    {
        return $this->hasMany(WeatherMonitor::class, 'locId');
    }

    public function nowcastAqis()
    {
        return $this->hasMany(NowcastAqi::class, 'locId');
    }
}
