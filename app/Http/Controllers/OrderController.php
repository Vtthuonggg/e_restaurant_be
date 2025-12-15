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
        $validated['discount'] = $validated['discount'] ?? 0;
        $validated['discount_type'] = $validated['discount_type'] ?? 1;
        $validated['status_order'] = $validated['status_order'] ?? 2;

        DB::beginTransaction();
        try {
            // Type 1: Đơn BÁN
            if ($validated['type'] == 1) {
                // Validate room thuộc về user
                $room = Room::where('user_id', Auth::id())->find($validated['room_id']);
                if (!$room) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Bàn không tồn tại hoặc không thuộc về bạn'
                    ], 404);
                }

                // Tạo order
                $order = Order::create($validated);

                // Trừ kho nguyên liệu từ product
                $this->processProductSale($validated['order_detail'], $validated['status_order']);

                // Cập nhật type của room
                $room->update(['type' => $validated['room_type']]);
            }
            // Type 2: Đơn NHẬP
            else if ($validated['type'] == 2) {
                // Xóa các field không cần thiết
                unset($validated['room_id'], $validated['room_type']);

                // Tạo order
                $order = Order::create($validated);

                // Cộng kho nguyên liệu
                $this->processIngredientImport($validated['order_detail'], $validated['status_order']);
            }

            DB::commit();

            // Load relationships và enrich order_detail
            $order->load(['room.area', 'customer', 'supplier']);
            $enrichedOrder = $this->enrichOrderDetail($order);

            return response()->json([
                'status' => 'success',
                'message' => 'Tạo đơn hàng thành công',
                'data' => $enrichedOrder
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi tạo đơn hàng: ' . $e->getMessage()
            ], 500);
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
        ]);

        DB::beginTransaction();
        try {
            // Nếu thay đổi status_order từ 2 -> 1
            if (isset($validated['status_order']) && $order->status_order == 2 && $validated['status_order'] == 1) {
                if ($order->type == 1) {
                    // Đơn bán: trừ thêm từ in_stock
                    $this->processProductSale($order->order_detail, 1, true);
                } else if ($order->type == 2) {
                    // Đơn nhập: cộng vào in_stock
                    $this->processIngredientImport($order->order_detail, 1, true);
                }
            }

            $order->update($validated);

            // Cập nhật room type nếu có (chỉ với đơn bán)
            if ($order->type == 1 && isset($validated['room_id']) && isset($validated['room_type'])) {
                $room = Room::where('user_id', Auth::id())->find($validated['room_id']);
                if ($room) {
                    $room->update(['type' => $validated['room_type']]);
                }
            }

            DB::commit();

            $order->load(['room.area', 'customer', 'supplier']);
            $enrichedOrder = $this->enrichOrderDetail($order);

            return response()->json(['status' => 'success', 'data' => $enrichedOrder]);
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
            $enrichedDetail = [
                'quantity' => $detail['quantity'],
                'user_price' => $detail['user_price'] ?? $detail['price'] ?? 0,
                'discount' => $detail['discount'] ?? 0,
                'discount_type' => $detail['discount_type'] ?? 1,
                'note' => $detail['note'] ?? null,
            ];

            // Đơn BÁN: lấy thông tin product
            if ($order->type == 1 && isset($detail['product_id'])) {
                $product = Product::find($detail['product_id']);

                $enrichedDetail['id'] = $detail['product_id'];
                $enrichedDetail['type'] = 'product';
                $enrichedDetail['product'] = null;

                if ($product) {
                    $enrichedDetail['product'] = [
                        'id' => $product->id,
                        'name' => $product->name,
                        'image' => $product->image,
                        'unit' => $product->unit,
                        'retail_cost' => $product->retail_cost,
                        'base_cost' => $product->base_cost,
                        'user_price' => $detail['user_price'] ?? $detail['price'] ?? 0,
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
            }
            // Đơn NHẬP: lấy thông tin ingredient
            else if ($order->type == 2 && isset($detail['ingredient_id'])) {
                $ingredient = Ingredient::find($detail['ingredient_id']);

                $enrichedDetail['id'] = $detail['ingredient_id'];
                $enrichedDetail['type'] = 'ingredient';
                $enrichedDetail['ingredient'] = null;

                if ($ingredient) {
                    $enrichedDetail['ingredient'] = [
                        'id' => $ingredient->id,
                        'name' => $ingredient->name,
                        'image' => $ingredient->image,
                        'unit' => $ingredient->unit,
                        'cost' => $ingredient->cost,
                        'user_price' => $detail['user_price'] ?? $detail['price'] ?? 0,
                    ];
                }
            }

            $enrichedDetails[] = $enrichedDetail;
        }

        // Thay thế order_detail bằng enriched version
        $orderArray = $order->toArray();
        $orderArray['order_detail'] = $enrichedDetails;

        // Tính tổng tiền
        $orderArray['total_amount'] = $this->calculateOrderTotal($enrichedDetails, $order->discount, $order->discount_type, $order->type);

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

    private function processProductSale($orderDetail, $statusOrder, $onlyInStock = false)
    {
        foreach ($orderDetail as $item) {
            if (!isset($item['product_id'])) continue;

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

    /**
     * Xử lý NHẬP nguyên liệu - cộng vào kho
     */
    private function processIngredientImport($orderDetail, $statusOrder, $onlyInStock = false)
    {
        foreach ($orderDetail as $item) {
            if (!isset($item['ingredient_id'])) continue;

            $ingredient = Ingredient::where('user_id', Auth::id())->find($item['ingredient_id']);
            if ($ingredient) {
                $quantityToAdd = $item['quantity'];

                if ($onlyInStock) {
                    // Chỉ cộng vào in_stock khi chuyển từ chờ -> hoàn thành
                    $ingredient->increment('in_stock', $quantityToAdd);
                } else {
                    if ($statusOrder == 2) {
                        // Chờ xác nhận: cộng vào available
                        $ingredient->increment('available', $quantityToAdd);
                    } elseif ($statusOrder == 1) {
                        // Hoàn thành: cộng vào cả available và in_stock
                        $ingredient->increment('available', $quantityToAdd);
                        $ingredient->increment('in_stock', $quantityToAdd);
                    }
                }
            }
        }
    }
}
