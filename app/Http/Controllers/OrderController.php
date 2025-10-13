<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Ingredient;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);

        $query = Order::where('user_id', Auth::id())
            ->with(['room', 'customer']);

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status_order')) {
            $query->where('status_order', $request->status_order);
        }

        $total = $query->count();
        $orders = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Thao tác thành công!',
            'data' => $orders->items(),
            'meta' => [
                'total' => $total,
                'size' => $orders->count(),
                'current_page' => $page,
                'last_page' => $orders->lastPage()
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'sometimes|integer|in:1,2',
            'room_id' => 'nullable|integer|exists:rooms,id',
            'room_type' => 'sometimes|string|in:free,using,pre_book',
            'note' => 'nullable|string',
            'discount' => 'sometimes|numeric|min:0',
            'discount_type' => 'sometimes|integer|in:1,2',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'status_order' => 'sometimes|integer|in:1,2',
            'payment' => 'required|array',
            'payment.type' => 'required|integer|in:1,2,3',
            'payment.price' => 'required|numeric|min:0',
            'order_detail' => 'required|array|min:1',
            'order_detail.*.product_id' => 'required|integer|exists:products,id',
            'order_detail.*.quantity' => 'required|numeric|min:0.1',
            'order_detail.*.discount' => 'sometimes|numeric|min:0',
            'order_detail.*.discount_type' => 'sometimes|integer|in:1,2',
            'order_detail.*.note' => 'nullable|string',
            'order_detail.*.price' => 'required|numeric|min:0',
            'order_detail.*.topping' => 'sometimes|array',
            'order_detail.*.topping.*.product_id' => 'required|integer|exists:products,id',
            'order_detail.*.topping.*.quantity' => 'required|numeric|min:0.1',
            'order_detail.*.topping.*.price' => 'required|numeric|min:0',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['type'] = $validated['type'] ?? 1;
        $validated['room_type'] = $validated['room_type'] ?? 'free';
        $validated['discount'] = $validated['discount'] ?? 0;
        $validated['discount_type'] = $validated['discount_type'] ?? 1;
        $validated['status_order'] = $validated['status_order'] ?? 2;

        DB::beginTransaction();
        try {
            // Tạo order
            $order = Order::create($validated);

            // Xử lý trừ ingredient từ kho
            $this->processIngredientStock($validated['order_detail'], $validated['status_order']);

            // Cập nhật status room nếu có
            if ($validated['room_id']) {
                $room = Room::where('user_id', Auth::id())->find($validated['room_id']);
                if ($room) {
                    $room->update(['status' => $validated['room_type']]);
                }
            }

            DB::commit();
            return response()->json(['status' => 'success', 'data' => $order], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Lỗi khi tạo đơn hàng: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $order = Order::where('user_id', Auth::id())
            ->with(['room', 'customer'])
            ->find($id);

        if (!$order) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy đơn hàng'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $order]);
    }

    public function update(Request $request, $id)
    {
        $order = Order::where('user_id', Auth::id())->find($id);
        if (!$order) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy đơn hàng'], 404);
        }

        $validated = $request->validate([
            'type' => 'sometimes|integer|in:1,2',
            'room_id' => 'nullable|integer|exists:rooms,id',
            'room_type' => 'sometimes|string|in:free,using,pre_book',
            'note' => 'nullable|string',
            'discount' => 'sometimes|numeric|min:0',
            'discount_type' => 'sometimes|integer|in:1,2',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'status_order' => 'sometimes|integer|in:1,2',
            'payment' => 'sometimes|array',
            'payment.type' => 'required_with:payment|integer|in:1,2,3',
            'payment.price' => 'required_with:payment|numeric|min:0',
            'order_detail' => 'sometimes|array|min:1',
            'order_detail.*.product_id' => 'required|integer|exists:products,id',
            'order_detail.*.quantity' => 'required|numeric|min:0.1',
            'order_detail.*.discount' => 'sometimes|numeric|min:0',
            'order_detail.*.discount_type' => 'sometimes|integer|in:1,2',
            'order_detail.*.note' => 'nullable|string',
            'order_detail.*.price' => 'required|numeric|min:0',
            'order_detail.*.topping' => 'sometimes|array',
            'order_detail.*.topping.*.product_id' => 'required|integer|exists:products,id',
            'order_detail.*.topping.*.quantity' => 'required|numeric|min:0.1',
            'order_detail.*.topping.*.price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Nếu thay đổi status_order từ 2 -> 1, cần trừ thêm từ in_stock
            if (isset($validated['status_order']) && $order->status_order == 2 && $validated['status_order'] == 1) {
                $this->processIngredientStock($order->order_detail, 1, true);
            }

            $order->update($validated);

            // Cập nhật room status nếu có
            if (isset($validated['room_id']) && isset($validated['room_type'])) {
                $room = Room::where('user_id', Auth::id())->find($validated['room_id']);
                if ($room) {
                    $room->update(['status' => $validated['room_type']]);
                }
            }

            DB::commit();
            return response()->json(['status' => 'success', 'data' => $order]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Lỗi khi cập nhật đơn hàng: ' . $e->getMessage()], 500);
        }
    }

    private function processIngredientStock($orderDetail, $statusOrder, $onlyInStock = false)
    {
        foreach ($orderDetail as $item) {
            $product = Product::where('user_id', Auth::id())->find($item['product_id']);
            if ($product && $product->ingredients) {
                foreach ($product->ingredients as $ingredientData) {
                    $ingredient = Ingredient::where('user_id', Auth::id())->find($ingredientData['id']);
                    if ($ingredient) {
                        $quantityToReduce = $ingredientData['quantity'] * $item['quantity'];

                        if ($onlyInStock) {
                            // Chỉ trừ từ in_stock (khi chuyển từ chờ xác nhận -> hoàn thành)
                            $ingredient->decrement('in_stock', $quantityToReduce);
                        } else {
                            if ($statusOrder == 2) {
                                // Chờ xác nhận: chỉ trừ available
                                $ingredient->decrement('available', $quantityToReduce);
                            } elseif ($statusOrder == 1) {
                                // Hoàn thành: trừ cả available và in_stock
                                $ingredient->decrement('available', $quantityToReduce);
                                $ingredient->decrement('in_stock', $quantityToReduce);
                            }
                        }
                    }
                }
            }

            // Xử lý topping nếu có
            if (isset($item['topping'])) {
                foreach ($item['topping'] as $topping) {
                    $toppingProduct = Product::where('user_id', Auth::id())->find($topping['product_id']);
                    if ($toppingProduct && $toppingProduct->ingredients) {
                        foreach ($toppingProduct->ingredients as $ingredientData) {
                            $ingredient = Ingredient::where('user_id', Auth::id())->find($ingredientData['id']);
                            if ($ingredient) {
                                $quantityToReduce = $ingredientData['quantity'] * $topping['quantity'];

                                if ($onlyInStock) {
                                    $ingredient->decrement('in_stock', $quantityToReduce);
                                } else {
                                    if ($statusOrder == 2) {
                                        $ingredient->decrement('available', $quantityToReduce);
                                    } elseif ($statusOrder == 1) {
                                        $ingredient->decrement('available', $quantityToReduce);
                                        $ingredient->decrement('in_stock', $quantityToReduce);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
