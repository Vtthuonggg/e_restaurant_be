<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Order;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QrOrderController extends Controller
{

    public function getProducts(Request $request)
    {
        $apiKey = $request->query('apiKey');

        if (!$apiKey) {
            return response()->json([
                'status' => 'error',
                'message' => 'Thiếu thông tin apiKey'
            ], 400);
        }

        // Tìm user theo api_key
        $user = User::where('api_key', $apiKey)->first();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'API Key không hợp lệ'
            ], 401);
        }

        // Lấy danh sách sản phẩm
        $products = Product::where('user_id', $user->id)
            ->with('categories')
            ->get()
            ->map(function ($product) {
                $ingredients = [];
                if (method_exists($product, 'getIngredientsWithDetails')) {
                    $ingredients = $product->getIngredientsWithDetails();
                }

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'retail_cost' => $product->retail_cost,
                    'base_cost' => $product->base_cost,
                    'image' => $product->image,
                    'unit' => $product->unit,
                    'categories' => $product->categories,
                    'ingredients' => $ingredients,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'products' => $products,
                'store_name' => $user->store_name ?? $user->name,
            ]
        ]);
    }

    /**
     * Tạo đơn hàng từ QR Order (không cần auth)
     */
    public function createOrder(Request $request)
    {
        try {
            $validated = $request->validate([
                'apiKey' => 'required|string',
                'roomId' => 'required|string',
                'order_detail' => 'required|array',
                'order_detail.*.product_id' => 'required|integer',
                'order_detail.*.quantity' => 'required|integer|min:1',
                'order_detail.*.price' => 'required|numeric',
                'note' => 'nullable|string',
            ]);

            // Tìm user theo api_key
            $user = User::where('api_key', $validated['apiKey'])->first();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'API Key không hợp lệ'
                ], 401);
            }

            // Decode roomId
            try {
                $roomId = base64_decode($validated['roomId']);
                if (!is_numeric($roomId)) {
                    throw new \Exception('Room ID không đúng định dạng');
                }
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Room ID không hợp lệ: ' . $e->getMessage()
                ], 400);
            }

            // Verify room belongs to user
            $room = Room::where('id', $roomId)
                ->where('user_id', $user->id)
                ->first();

            if (!$room) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Phòng/Bàn không tồn tại hoặc không thuộc về cửa hàng này'
                ], 404);
            }

            DB::beginTransaction();
            try {
                // Lấy thông tin sản phẩm để enrich order_detail
                $productIds = collect($validated['order_detail'])->pluck('product_id');
                $products = Product::whereIn('id', $productIds)
                    ->where('user_id', $user->id)
                    ->get()
                    ->keyBy('id');

                $enrichedOrderDetail = collect($validated['order_detail'])->map(function ($item) use ($products) {
                    $product = $products->get($item['product_id']);
                    if (!$product) {
                        throw new \Exception("Sản phẩm ID {$item['product_id']} không tồn tại");
                    }
                    return [
                        'product_id' => $item['product_id'],
                        'name' => $product->name,
                        'image' => $product->image,
                        'unit' => $product->unit,
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                    ];
                })->toArray();

                // Kiểm tra trạng thái room
                if ($room->type === 'free') {
                    // Tạo order mới
                    $order = Order::create([
                        'type' => 1, // Đơn bán
                        'room_id' => $roomId,
                        'room_type' => 'using',
                        'note' => $validated['note'] ?? null,
                        'discount' => 0,
                        'discount_type' => 1,
                        'status_order' => 2, // Chờ xác nhận (FIXED)
                        'payment' => ['type' => 1, 'price' => 0],
                        'order_detail' => $enrichedOrderDetail,
                        'user_id' => $user->id,
                    ]);

                    // Cập nhật type của room
                    $room->update(['type' => 'using']);

                    // Trừ kho nguyên liệu
                    $this->processProductSale($enrichedOrderDetail, $user->id);
                } else if ($room->type === 'using') {
                    // Tìm order chưa hoàn thành của room này
                    $existingOrder = Order::where('room_id', $roomId)
                        ->where('status_order', 2) // Chờ xác nhận
                        ->where('user_id', $user->id)
                        ->orderBy('created_at', 'desc')
                        ->first();

                    if (!$existingOrder) {
                        // Nếu không tìm thấy order, tạo mới
                        $order = Order::create([
                            'type' => 1,
                            'room_id' => $roomId,
                            'room_type' => 'using',
                            'note' => $validated['note'] ?? null,
                            'discount' => 0,
                            'discount_type' => 1,
                            'status_order' => 2, // Chờ xác nhận (FIXED)
                            'payment' => ['type' => 1, 'price' => 0],
                            'order_detail' => $enrichedOrderDetail,
                            'user_id' => $user->id,
                        ]);

                        // Trừ kho nguyên liệu
                        $this->processProductSale($enrichedOrderDetail, $user->id);
                    } else {
                        // Merge order_detail mới vào order_detail cũ
                        $existingOrderDetail = $existingOrder->order_detail ?? [];
                        $mergedOrderDetail = $this->mergeOrderDetails($existingOrderDetail, $enrichedOrderDetail);

                        // Cập nhật order
                        $existingOrder->update([
                            'order_detail' => $mergedOrderDetail,
                            'note' => $validated['note'] ?? $existingOrder->note,
                        ]);

                        $order = $existingOrder;

                        // Trừ kho nguyên liệu cho phần mới thêm
                        $this->processProductSale($enrichedOrderDetail, $user->id);
                    }
                } else {
                    // Trạng thái khác (pre_book, etc.) - tạo order mới
                    $order = Order::create([
                        'type' => 1,
                        'room_id' => $roomId,
                        'room_type' => 'using',
                        'note' => $validated['note'] ?? null,
                        'discount' => 0,
                        'discount_type' => 1,
                        'status_order' => 2, // Chờ xác nhận (FIXED)
                        'payment' => ['type' => 1, 'price' => 0],
                        'order_detail' => $enrichedOrderDetail,
                        'user_id' => $user->id,
                    ]);

                    $room->update(['type' => 'using']);
                    $this->processProductSale($enrichedOrderDetail, $user->id);
                }

                DB::commit();

                $order->load(['room']);
                $productList = collect($order->order_detail)->map(function ($item) {
                    $quantity = $item['quantity'] ?? 0;
                    $name = $item['name'] ?? 'Unknown';
                    return "-{$name} ({$quantity})";
                })->join("\n");

                $roomName = $order->room->name ?? $order->room_id ?? 'Không xác định';

                // Prepare socket data
                $socketData = [
                    'user_id' =>  $user->id,
                    'roomName' => $room->name,
                    'id' => $order->id,
                    'order_id' => $order->id,
                    'code' =>  "#$order->id",
                    'description' => "Đặt món từ bàn: {$roomName}\n\n{$productList}",
                    'total' => collect($order->order_detail)->sum(function ($item) {
                        return ($item['price'] ?? 0) * ($item['quantity'] ?? 0);
                    }),
                    'created_at' => $order->created_at->toISOString(),
                ];

                return response()->json([
                    'status' => 'success',
                    'message' => $room->type === 'using' && isset($existingOrder) ? 'Cập nhật đơn hàng thành công' : 'Đặt món thành công',
                    'data' => [
                        'order' => $order,
                        'socket_data' => $socketData,
                    ]
                ], 201);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('QrOrderController@createOrder transaction error: ' . $e->getMessage());
                return response()->json([
                    'status' => 'error',
                    'message' => 'Lỗi khi tạo đơn hàng: ' . $e->getMessage()
                ], 500);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('QrOrderController@createOrder error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi hệ thống: ' . $e->getMessage()
            ], 500);
        }
    }
    private function mergeOrderDetails($existingDetails, $newDetails)
    {
        $merged = $existingDetails;

        foreach ($newDetails as $newItem) {
            $productId = $newItem['product_id'] ?? null;
            $found = false;

            // Tìm xem có item cùng product_id không
            foreach ($merged as $index => $existingItem) {
                if (($existingItem['product_id'] ?? null) == $productId) {
                    // Cộng quantity
                    $merged[$index]['quantity'] = ($existingItem['quantity'] ?? 0) + ($newItem['quantity'] ?? 0);
                    $found = true;
                    break;
                }
            }

            // Nếu không tìm thấy, append vào cuối
            if (!$found) {
                $merged[] = $newItem;
            }
        }

        return $merged;
    }
    private function processProductSale($orderDetail, $userId)
    {
        foreach ($orderDetail as $item) {
            $product = Product::where('user_id', $userId)
                ->find($item['product_id']);

            if (!$product || !$product->ingredients) {
                continue;
            }

            foreach ($product->ingredients as $ingredient) {
                $ingredientId = $ingredient['id'] ?? null;
                $ingredientQuantity = $ingredient['quantity'] ?? 0;

                if ($ingredientId) {
                    $ingredientModel = \App\Models\Ingredient::where('user_id', $userId)
                        ->find($ingredientId);

                    if ($ingredientModel) {
                        $totalToDeduct = $ingredientQuantity * $item['quantity'];
                        $ingredientModel->decrement('in_stock', $totalToDeduct);
                    }
                }
            }
        }
    }
}
