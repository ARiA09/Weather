<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function index()
    {
        return Location::all();
    }

    public function show(Location $location)
    {
        return $location;
    }

    public function store(Request $request)
    {
        $location = Location::create($request->all());

        return response()->json($location, 201);
    }

    public function update(Request $request, Location $location)
    {
        $location->update($request->all());

        return response()->json($location, 200);
    }

    public function delete(Location $location)
    {
        $location->delete();

        return response()->json(null, 204);
    }
}
