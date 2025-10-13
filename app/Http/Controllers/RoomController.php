<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $query = Room::where('user_id', Auth::id());
        if ($request->has('area_id')) {
            $query->where('area_id', $request->area_id);
        }
        $rooms = $query->get();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Thao tác thành công!',
            'data' => $rooms
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'area_id' => 'required|integer|exists:areas,id',
        ]);
        $validated['user_id'] = Auth::id();
        $validated['status'] = $validated['status'] ?? 'free';
        $room = Room::create($validated);
        return response()->json(['status' => 'success', 'data' => $room], 201);
    }

    public function show($id)
    {
        $room = Room::where('user_id', Auth::id())->find($id);
        if (!$room) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy bàn'], 404);
        }
        return response()->json(['status' => 'success', 'data' => $room]);
    }

    public function update(Request $request, $id)
    {
        $room = Room::where('user_id', Auth::id())->find($id);
        if (!$room) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy bàn'], 404);
        }
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'area_id' => 'sometimes|required|integer|exists:areas,id',
        ]);
        $room->update($validated);
        return response()->json(['status' => 'success', 'data' => $room]);
    }

    public function destroy($id)
    {
        $room = Room::where('user_id', Auth::id())->find($id);
        if (!$room) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy bàn'], 404);
        }
        $room->delete();
        return response()->json(['status' => 'success', 'message' => 'Xóa bàn thành công']);
    }
}
