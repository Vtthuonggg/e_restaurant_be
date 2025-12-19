<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Ingredient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Báo cáo doanh thu theo thời gian
     */
    public function revenueReport(Request $request)
    {
        // Mặc định từ đầu tháng đến ngày hiện tại
        $defaultStart = Carbon::now()->startOfMonth()->format('Y-m-d');
        $defaultEnd = Carbon::now()->format('Y-m-d');

        $validated = $request->validate([
            'start' => 'nullable|date',
            'end' => 'nullable|date|after_or_equal:start',
        ]);

        $start = $validated['start'] ?? $defaultStart;
        $end = $validated['end'] ?? $defaultEnd;

        $query = Order::where('user_id', User::getEffectiveUserId())
            ->where('type', 1) // Chỉ lấy đơn bán
            ->where('status_order', 1) // Chỉ lấy đơn hoàn thành
            ->whereBetween('created_at', [
                $start . ' 00:00:00',
                $end . ' 23:59:59'
            ]);

        $orders = $query->get();

        $totalRevenue = 0;
        $totalDiscount = 0;
        $totalOrders = $orders->count();

        foreach ($orders as $order) {
            // Tính tổng giá trị đơn hàng
            $orderTotal = 0;
            foreach ($order->order_detail as $item) {
                $itemTotal = $item['price'] * $item['quantity'];

                // Trừ discount của item
                if (isset($item['discount']) && $item['discount'] > 0) {
                    if ($item['discount_type'] == 1) { // %
                        $itemTotal = $itemTotal * (1 - $item['discount'] / 100);
                    } else { // VNĐ
                        $itemTotal = $itemTotal - $item['discount'];
                    }
                }

                $orderTotal += $itemTotal;

                // Cộng topping
                if (isset($item['topping'])) {
                    foreach ($item['topping'] as $topping) {
                        $orderTotal += $topping['price'] * $topping['quantity'];
                    }
                }
            }

            // Trừ discount của đơn hàng
            if ($order->discount > 0) {
                if ($order->discount_type == 1) { // %
                    $orderTotal = $orderTotal * (1 - $order->discount / 100);
                } else { // VNĐ
                    $orderTotal = $orderTotal - $order->discount;
                }
                $totalDiscount += $order->discount;
            }

            $totalRevenue += $orderTotal;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_revenue' => $totalRevenue,
                'total_discount' => $totalDiscount,
                'total_orders' => $totalOrders,
                'average_order_value' => $totalOrders > 0 ? $totalRevenue / $totalOrders : 0,
                'period' => [
                    'start' => $start,
                    'end' => $end
                ]
            ]
        ]);
    }

    /**
     * Thống kê món ăn đã bán
     */
    public function productSalesReport(Request $request)
    {
        // Mặc định từ đầu tháng đến ngày hiện tại
        $defaultStart = Carbon::now()->startOfMonth()->format('Y-m-d');
        $defaultEnd = Carbon::now()->format('Y-m-d');

        $validated = $request->validate([
            'start' => 'nullable|date',
            'end' => 'nullable|date|after_or_equal:start',
        ]);

        $start = $validated['start'] ?? $defaultStart;
        $end = $validated['end'] ?? $defaultEnd;

        $orders = Order::where('user_id', User::getEffectiveUserId())
            ->where('type', 1) // Chỉ đơn bán
            ->where('status_order', 1) // Chỉ đơn hoàn thành
            ->whereBetween('created_at', [
                $start . ' 00:00:00',
                $end . ' 23:59:59'
            ])
            ->get();

        $productStats = [];

        foreach ($orders as $order) {
            foreach ($order->order_detail as $item) {
                $productId = $item['product_id'];

                if (!isset($productStats[$productId])) {
                    $product = Product::find($productId);
                    $productStats[$productId] = [
                        'product_id' => $productId,
                        'product_name' => $product ? $product->name : 'Sản phẩm đã bị xóa',
                        'total_quantity' => 0,
                        'total_revenue' => 0,
                        'order_count' => 0
                    ];
                }

                $productStats[$productId]['total_quantity'] += $item['quantity'];
                $productStats[$productId]['order_count'] += 1;

                // Tính doanh thu của sản phẩm
                $itemRevenue = $item['price'] * $item['quantity'];
                if (isset($item['discount']) && $item['discount'] > 0) {
                    if ($item['discount_type'] == 1) {
                        $itemRevenue = $itemRevenue * (1 - $item['discount'] / 100);
                    } else {
                        $itemRevenue = $itemRevenue - $item['discount'];
                    }
                }
                $productStats[$productId]['total_revenue'] += $itemRevenue;

                // Thống kê topping
                if (isset($item['topping'])) {
                    foreach ($item['topping'] as $topping) {
                        $toppingId = $topping['product_id'];

                        if (!isset($productStats[$toppingId])) {
                            $toppingProduct = Product::find($toppingId);
                            $productStats[$toppingId] = [
                                'product_id' => $toppingId,
                                'product_name' => $toppingProduct ? $toppingProduct->name . ' (Topping)' : 'Topping đã bị xóa',
                                'total_quantity' => 0,
                                'total_revenue' => 0,
                                'order_count' => 0
                            ];
                        }

                        $productStats[$toppingId]['total_quantity'] += $topping['quantity'];
                        $productStats[$toppingId]['total_revenue'] += $topping['price'] * $topping['quantity'];
                        $productStats[$toppingId]['order_count'] += 1;
                    }
                }
            }
        }

        // Sắp xếp theo doanh thu giảm dần
        $productStats = collect($productStats)->sortByDesc('total_revenue')->values();

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $productStats,
                'period' => [
                    'start' => $start,
                    'end' => $end
                ]
            ]
        ]);
    }

    /**
     * Thống kê nguyên liệu được mua vào (từ đơn nhập)
     */
    public function ingredientPurchaseReport(Request $request)
    {
        // Mặc định từ đầu tháng đến ngày hiện tại
        $defaultStart = Carbon::now()->startOfMonth()->format('Y-m-d');
        $defaultEnd = Carbon::now()->format('Y-m-d');

        $validated = $request->validate([
            'start' => 'nullable|date',
            'end' => 'nullable|date|after_or_equal:start',
        ]);

        $start = $validated['start'] ?? $defaultStart;
        $end = $validated['end'] ?? $defaultEnd;

        $orders = Order::where('user_id', User::getEffectiveUserId())
            ->where('type', 2) // Chỉ đơn nhập
            ->where('status_order', 1) // Chỉ đơn hoàn thành
            ->whereBetween('created_at', [
                $start . ' 00:00:00',
                $end . ' 23:59:59'
            ])
            ->get();

        $ingredientStats = [];
        $totalPurchaseValue = 0;

        foreach ($orders as $order) {
            foreach ($order->order_detail as $item) {
                $ingredientId = $item['product_id']; // Trong đơn nhập, product_id là ingredient_id

                if (!isset($ingredientStats[$ingredientId])) {
                    $ingredient = Ingredient::find($ingredientId);
                    $ingredientStats[$ingredientId] = [
                        'ingredient_id' => $ingredientId,
                        'ingredient_name' => $ingredient ? $ingredient->name : 'Nguyên liệu đã bị xóa',
                        'unit' => $ingredient ? $ingredient->unit : '',
                        'total_quantity' => 0,
                        'total_cost' => 0,
                        'purchase_count' => 0
                    ];
                }

                $ingredientStats[$ingredientId]['total_quantity'] += $item['quantity'];
                $ingredientStats[$ingredientId]['total_cost'] += $item['price'] * $item['quantity'];
                $ingredientStats[$ingredientId]['purchase_count'] += 1;

                $totalPurchaseValue += $item['price'] * $item['quantity'];
            }
        }

        // Sắp xếp theo chi phí giảm dần
        $ingredientStats = collect($ingredientStats)->sortByDesc('total_cost')->values();

        return response()->json([
            'success' => true,
            'data' => [
                'total_purchase_value' => $totalPurchaseValue,
                'total_orders' => $orders->count(),
                'ingredients' => $ingredientStats,
                'period' => [
                    'start' => $start,
                    'end' => $end
                ]
            ]
        ]);
    }

    /**
     * Báo cáo tổng quan
     */
    public function dashboardReport(Request $request)
    {
        // Mặc định từ đầu tháng đến ngày hiện tại
        $defaultStart = Carbon::now()->startOfMonth()->format('Y-m-d');
        $defaultEnd = Carbon::now()->format('Y-m-d');

        $validated = $request->validate([
            'start' => 'nullable|date',
            'end' => 'nullable|date|after_or_equal:start',
        ]);

        $start = $validated['start'] ?? $defaultStart;
        $end = $validated['end'] ?? $defaultEnd;

        // Doanh thu bán hàng
        $salesOrders = Order::where('user_id', User::getEffectiveUserId())
            ->where('type', 1)
            ->where('status_order', 1)
            ->whereBetween('created_at', [
                $start . ' 00:00:00',
                $end . ' 23:59:59'
            ])
            ->get();

        $totalRevenue = 0;
        foreach ($salesOrders as $order) {
            $orderTotal = 0;
            foreach ($order->order_detail as $item) {
                $orderTotal += $item['price'] * $item['quantity'];
                if (isset($item['topping'])) {
                    foreach ($item['topping'] as $topping) {
                        $orderTotal += $topping['price'] * $topping['quantity'];
                    }
                }
            }
            $totalRevenue += $orderTotal;
        }

        // Chi phí nhập hàng
        $purchaseOrders = Order::where('user_id', User::getEffectiveUserId())
            ->where('type', 2)
            ->where('status_order', 1)
            ->whereBetween('created_at', [
                $start . ' 00:00:00',
                $end . ' 23:59:59'
            ])
            ->get();

        $totalPurchaseCost = 0;
        foreach ($purchaseOrders as $order) {
            foreach ($order->order_detail as $item) {
                $totalPurchaseCost += $item['price'] * $item['quantity'];
            }
        }

        // Số đơn chờ xác nhận
        $pendingOrders = Order::where('user_id', User::getEffectiveUserId())
            ->where('status_order', 2)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_revenue' => $totalRevenue,
                'total_purchase_cost' => $totalPurchaseCost,
                'profit' => $totalRevenue - $totalPurchaseCost,
                'total_sales_orders' => $salesOrders->count(),
                'total_purchase_orders' => $purchaseOrders->count(),
                'pending_orders' => $pendingOrders,
                'period' => [
                    'start' => $start,
                    'end' => $end
                ]
            ]
        ]);
    }
}
