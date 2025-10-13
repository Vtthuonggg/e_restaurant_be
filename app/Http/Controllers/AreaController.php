<?php

namespace App\Http\Controllers;

use App\Models\Area;
use Illuminate\Support\Facades\Auth;

use Illuminate\Http\Request;

class AreaController extends Controller
{

    public function index()
    {
        $areas = Area::where('user_id', Auth::id())->get();
        return response()->json($areas);
    }


    public function store(Request $request)
    {
        $validated = $request->validate(['name' => 'required|string|max:255']);
        $validated['user_id'] = Auth::id();
        $area = Area::create($validated);
        return response()->json($area, 201);
    }


    public function show(string $id)
    {
        $area = Area::where('user_id', Auth::id())->find($id);
        if (!$area) return response()->json(['message' => 'Not found'], 404);
        return response()->json($area);
    }

    public function update(Request $request, string $id)
    {
        $area = Area::where('user_id', Auth::id())->find($id);
        if (!$area) return response()->json(['message' => 'Not found'], 404);
        $validated = $request->validate(['name' => 'sometimes|string|max:255']);
        $area->update($validated);
        return response()->json($area);
    }


    public function destroy(string $id)
    {
        $area = Area::where('user_id', Auth::id())->find($id);
        if (!$area) return response()->json(['message' => 'Not found'], 404);
        $area->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
