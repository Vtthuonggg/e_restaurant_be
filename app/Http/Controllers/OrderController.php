<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Ingredient;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\CreateOrderRequest;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);

        $query = Order::where('user_id', Auth::id())
            ->with(['room.area', 'customer', 'supplier']);

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status_order')) {
            $query->where('status_order', $request->status_order);
        }

        $total = $query->count();
        $orders = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        // Enrich order_detail với thông tin product
        $items = $orders->getCollection()->map(function ($order) {
            return $this->enrichOrderDetail($order);
        });

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Thao tác thành công!',
            'data' => $items,
            'meta' => [
                'total' => $total,
                'size' => $items->count(),
                'current_page' => $page,
                'last_page' => $orders->lastPage()
            ]
        ]);
    }

    public function store(CreateOrderRequest $request)
    {
        $validated = $request->validated();

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

            // Load relationships và enrich order_detail
            $order->load(['room.area', 'customer', 'supplier']);
            $order = $this->enrichOrderDetail($order);

            return response()->json(['status' => 'success', 'data' => $order], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Lỗi khi tạo đơn hàng: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $order = Order::where('user_id', Auth::id())
            ->with(['room.area', 'customer', 'supplier'])
            ->find($id);

        if (!$order) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy đơn hàng'], 404);
        }

        // Enrich order_detail với thông tin product đầy đủ
        $order = $this->enrichOrderDetail($order);

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
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'status_order' => 'sometimes|integer|in:1,2',
            'payment' => 'sometimes|array',
            'payment.type' => 'required_with:payment|integer|in:1,2,3',
            'payment.price' => 'required_with:payment|numeric|min:0',
            'order_detail' => 'sometimes|array|min:1',
            'order_detail.*.product_id' => 'required|integer|exists:products,id',
            'order_detail.*.quantity' => 'required|numeric|min:0.1',
            'order_detail.*.user_price' => 'required|numeric|min:0',
            'order_detail.*.discount' => 'sometimes|numeric|min:0',
            'order_detail.*.discount_type' => 'sometimes|integer|in:1,2',
            'order_detail.*.note' => 'nullable|string',
            'order_detail.*.topping' => 'sometimes|array',
            'order_detail.*.topping.*.product_id' => 'required|integer|exists:products,id',
            'order_detail.*.topping.*.quantity' => 'required|numeric|min:0.1',
            'order_detail.*.topping.*.user_price' => 'required|numeric|min:0',
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

            $order->load(['room.area', 'customer', 'supplier']);
            $order = $this->enrichOrderDetail($order);

            return response()->json(['status' => 'success', 'data' => $order]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Lỗi khi cập nhật đơn hàng: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Enrich order_detail với thông tin product đầy đủ
     */
    private function enrichOrderDetail($order)
    {
        if (!$order->order_detail || !is_array($order->order_detail)) {
            return $order;
        }

        $enrichedDetails = [];

        foreach ($order->order_detail as $detail) {
            $product = Product::find($detail['product_id']);

            $enrichedDetail = [
                'id' => $detail['product_id'],
                'quantity' => $detail['quantity'],
                'user_price' => $detail['user_price'] ?? $detail['price'] ?? 0,
                'discount' => $detail['discount'] ?? 0,
                'discount_type' => $detail['discount_type'] ?? 1,
                'note' => $detail['note'] ?? null,
                'product' => null
            ];

            if ($product) {
                $enrichedDetail['product'] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'image' => $product->image,
                    'unit' => $product->unit,
                    'retail_cost' => $product->retail_cost, // Giá bán gốc
                    'base_cost' => $product->base_cost,     // Giá nhập gốc
                    'user_price' => $detail['user_price'] ?? $detail['price'] ?? 0, // Giá người dùng nhập
                ];
            }

            // Xử lý topping nếu có
            if (isset($detail['topping']) && is_array($detail['topping'])) {
                $enrichedDetail['topping'] = [];

                foreach ($detail['topping'] as $topping) {
                    $toppingProduct = Product::find($topping['product_id']);

                    $enrichedTopping = [
                        'id' => $topping['product_id'],
                        'quantity' => $topping['quantity'],
                        'user_price' => $topping['user_price'] ?? $topping['price'] ?? 0,
                        'product' => null
                    ];

                    if ($toppingProduct) {
                        $enrichedTopping['product'] = [
                            'id' => $toppingProduct->id,
                            'name' => $toppingProduct->name,
                            'image' => $toppingProduct->image,
                            'unit' => $toppingProduct->unit,
                            'retail_cost' => $toppingProduct->retail_cost,
                            'base_cost' => $toppingProduct->base_cost,
                            'user_price' => $topping['user_price'] ?? $topping['price'] ?? 0,
                        ];
                    }

                    $enrichedDetail['topping'][] = $enrichedTopping;
                }
            }

            $enrichedDetails[] = $enrichedDetail;
        }

        // Thay thế order_detail bằng enriched version
        $orderArray = $order->toArray();
        $orderArray['order_detail'] = $enrichedDetails;

        // Tính tổng tiền
        $orderArray['total_amount'] = $this->calculateOrderTotal($enrichedDetails, $order->discount, $order->discount_type);

        return $orderArray;
    }

    /**
     * Tính tổng tiền đơn hàng
     */
    private function calculateOrderTotal($orderDetails, $orderDiscount, $orderDiscountType)
    {
        $total = 0;

        foreach ($orderDetails as $detail) {
            $itemTotal = $detail['user_price'] * $detail['quantity'];

            // Trừ discount của item
            if ($detail['discount'] > 0) {
                if ($detail['discount_type'] == 1) { // %
                    $itemTotal = $itemTotal * (1 - $detail['discount'] / 100);
                } else { // VNĐ
                    $itemTotal = $itemTotal - $detail['discount'];
                }
            }

            // Cộng topping
            if (isset($detail['topping'])) {
                foreach ($detail['topping'] as $topping) {
                    $itemTotal += $topping['user_price'] * $topping['quantity'];
                }
            }

            $total += $itemTotal;
        }

        // Trừ discount của đơn hàng
        if ($orderDiscount > 0) {
            if ($orderDiscountType == 1) { // %
                $total = $total * (1 - $orderDiscount / 100);
            } else { // VNĐ
                $total = $total - $orderDiscount;
            }
        }

        return round($total, 2);
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
