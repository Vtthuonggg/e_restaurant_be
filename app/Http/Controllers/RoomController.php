<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\CreateRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use App\Models\Product;
use App\Models\Order;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $query = Room::with('area')->where('user_id', User::getEffectiveUserId());
        if ($request->has('area_id')) {
            $query->where('area_id', $request->area_id);
        }
        $rooms = $query->get();
        $rooms = $rooms->map(function ($room) {
            if ($room->type === 'using') {
                // Tìm order chưa hoàn thành (status_order = 2) và load customer
                $order = Order::with('customer')
                    ->where('room_id', $room->id)
                    ->where('status_order', 1)
                    ->where('user_id', User::getEffectiveUserId())
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($order) {
                    $room->order = $this->formatOrderForRoom($order);
                } else {
                    $room->order = null;
                }
            } else {
                $room->order = null;
            }
            return $room;
        });
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Thao tác thành công!',
            'data' => $rooms
        ]);
    }

    public function store(CreateRoomRequest $request)
    {
        $validated = $request->validated();
        $validated['user_id'] = User::getEffectiveUserId();
        $validated['type'] = $validated['type'] ?? 'free';
        $room = Room::create($validated);
        return response()->json(['status' => 'success', 'data' => $room], 201);
    }

    public function show($id)
    {
        $room = Room::with('area')->where('user_id', User::getEffectiveUserId())->find($id);
        if (!$room) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy bàn'], 404);
        }
        if ($room->type === 'using') {
            $order = Order::where('room_id', $room->id)
                ->where('status_order', 2)
                ->where('user_id', User::getEffectiveUserId())
                ->orderBy('created_at', 'desc')
                ->first();

            $room->order_id = $order ? $order->id : null;
            if ($order) {
                $room->order = $this->formatOrderForRoom($order);
            } else {
                $room->order = null;
            }
        }
        return response()->json(['status' => 'success', 'data' => $room]);
    }

    public function update(UpdateRoomRequest $request, $id)
    {
        $room = Room::where('user_id', User::getEffectiveUserId())->find($id);
        if (!$room) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy bàn'], 404);
        }
        $validated = $request->validated();
        $room->update($validated);
        return response()->json(['status' => 'success', 'data' => $room]);
    }

    public function destroy($id)
    {
        $room = Room::where('user_id', User::getEffectiveUserId())->find($id);
        if (!$room) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy bàn'], 404);
        }
        $room->delete();
        return response()->json(['status' => 'success', 'message' => 'Xóa bàn thành công']);
    }
    public function list(Request $request)
    {
        $request->validate([
            '*.area_id' => 'required|integer|exists:areas,id',
            '*.name' => 'required|string|max:255',
        ], [
            '*.area_id.required' => 'Khu vực là bắt buộc',
            '*.area_id.integer' => 'ID khu vực phải là số nguyên',
            '*.area_id.exists' => 'Khu vực không tồn tại',
            '*.name.required' => 'Tên bàn là bắt buộc',
            '*.name.string' => 'Tên bàn phải là chuỗi ký tự',
            '*.name.max' => 'Tên bàn không được vượt quá 255 ký tự',
        ]);

        $roomsData = $request->all();
        $userId = User::getEffectiveUserId();
        $now = now();

        $roomsToInsert = array_map(function ($room) use ($userId, $now) {
            return [
                'area_id' => $room['area_id'],
                'name' => $room['name'],
                'user_id' => $userId,
                'type' => 'free',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $roomsData);

        $rooms = Room::insert($roomsToInsert);

        // Lấy lại các bàn vừa tạo để trả về
        $createdRooms = Room::with('area')
            ->where('user_id', $userId)
            ->where('created_at', $now)
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Tạo bàn thành công',
            'data' => $createdRooms
        ], 201);
    }
    private function formatOrderForRoom($order)
    {
        //log ra order_detail
        \Illuminate\Support\Facades\Log::info('Order detail: ' . json_encode($order->order_detail));
        $retailCost = 0;
        if ($order->order_detail && is_array($order->order_detail)) {

            foreach ($order->order_detail as $detail) {
                $userPrice = $detail['user_price'] ?? $detail['price'] ?? 0;
                $quantity = $detail['quantity'] ?? 0;
                $retailCost += $userPrice * $quantity;
            }
        }

        // Lấy name và phone từ customer
        $name = null;
        $phone = null;
        if ($order->customer) {
            $name = $order->customer->name;
            $phone = $order->customer->phone;
        }

        return [
            'id' => $order->id,
            'customer_id' => $order->customer_id,
            'name' => $name,
            'phone' => $phone,
            'retail_cost' => $retailCost,
            'created_at' => $order->created_at,
        ];
    }
}
