<?php

namespace App\Http\Controllers;

use App\Models\Area;
use Illuminate\Support\Facades\Auth;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\CreateAreaRequest;
use App\Http\Requests\UpdateAreaRequest;



class AreaController extends Controller
{

    public function index()
    {
        $areas = Area::where('user_id', Auth::id())->get();
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Thao tác thành công!',
            'data' => $areas
        ]);
    }


    public function store(CreateAreaRequest $request)
    {
        $validated = $request->validated();
        $validated['user_id'] = Auth::id();
        $area = Area::create($validated);
        return response()->json(
            [
                'success' => true,
                'status' => 200,
                'message' => 'Thao tác thành công!',
                'data' => $area
            ],
            201
        );
    }


    public function show(string $id)
    {
        $area = Area::where('user_id', Auth::id())->find($id);
        if (!$area) return response()->json(['message' => 'Not found'], 404);
        return response()->json($area);
    }

    public function update(UpdateAreaRequest $request, string $id)
    {
        $area = Area::where('user_id', Auth::id())->find($id);
        if (!$area) return response()->json(['message' => 'Not found'], 404);
        $validated = $request->validated();
        $area->update($validated);
        return response()->json($area);
    }


    public function destroy(string $id)
    {
        $area = Area::where('user_id', Auth::id())->find($id);
        if (!$area) return response()->json(['message' => 'Not found'], 404);

        DB::transaction(function () use ($area) {
            Room::where('area_id', $area->id)->where('user_id', Auth::id())->delete();
            $area->delete();
        });
        return response()->json(['message' => 'Deleted']);
    }
}
