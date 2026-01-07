<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Ingredient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;

class ReportController extends Controller
{
    /**
     * Báo cáo doanh thu theo thời gian
     */
    public function revenueReport(Request $request)
    {
        $defaultStart = Carbon::now()->startOfMonth()->format('Y-m-d');
        $defaultEnd = Carbon::now()->format('Y-m-d');

        $validated = $request->validate([
            'start' => 'nullable|date',
            'end' => 'nullable|date|after_or_equal:start',
        ]);

        $start = $validated['start'] ?? $defaultStart;
        $end = $validated['end'] ?? $defaultEnd;

        // Lấy các order hoàn thành trong khoảng
        $orders = Order::where('user_id', User::getEffectiveUserId())
            ->where('type', 1) // chỉ đơn bán
            ->where('status_order', 1) // hoàn thành
            ->whereBetween('created_at', [
                $start . ' 00:00:00',
                $end . ' 23:59:59'
            ])->get();

        $totalOrders = $orders->count();

        // Doanh thu thực tế lấy từ payment.price
        $totalRevenue = $this->calculateRevenue(User::getEffectiveUserId(), $start . ' 00:00:00', $end . ' 23:59:59');

        // Tổng giảm giá lấy từ order-level discount (nếu cần)
        $totalDiscount = $orders->sum(function ($order) {
            return (float) ($order->discount ?? 0);
        });

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
                $ingredientId = $item['ingredient_id']; // Trong đơn nhập, product_id là ingredient_id

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
        $defaultStart = Carbon::now()->startOfMonth()->format('Y-m-d');
        $defaultEnd = Carbon::now()->format('Y-m-d');

        $validated = $request->validate([
            'start' => 'nullable|date',
            'end' => 'nullable|date|after_or_equal:start',
        ]);

        $start = $validated['start'] ?? $defaultStart;
        $end = $validated['end'] ?? $defaultEnd;

        // Doanh thu bán hàng (từ payment.price)
        $totalRevenue = $this->calculateRevenue(User::getEffectiveUserId(), $start . ' 00:00:00', $end . ' 23:59:59');

        // Chi phí nhập hàng (vẫn dùng order_detail.price nếu order nhập lưu ở đó)
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
                $price = $item['price'] ?? 0;
                $qty = $item['quantity'] ?? 0;
                $totalPurchaseCost += $price * $qty;
            }
        }

        $pendingOrders = Order::where('user_id', User::getEffectiveUserId())
            ->where('status_order', 2)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_revenue' => $totalRevenue,
                'total_purchase_cost' => $totalPurchaseCost,
                'profit' => $totalRevenue - $totalPurchaseCost,
                'total_sales_orders' => Order::where('user_id', User::getEffectiveUserId())->where('type', 1)->where('status_order', 1)->count(),
                'total_purchase_orders' => $purchaseOrders->count(),
                'pending_orders' => $pendingOrders,
                'period' => [
                    'start' => $start,
                    'end' => $end
                ]
            ]
        ]);
    }


    public function quickStats()
    {
        $userId = User::getEffectiveUserId();
        $now = Carbon::now();

        // Hôm nay
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();

        $todayRevenue = $this->calculateRevenue($userId, $todayStart, $todayEnd);


        $weekStart = $now->copy()->startOfWeek();
        $weekEnd = $now->copy()->endOfDay();

        $weekRevenue = $this->calculateRevenue($userId, $weekStart, $weekEnd);

        // Tháng này
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfDay();

        $monthRevenue = $this->calculateRevenue($userId, $monthStart, $monthEnd);

        return response()->json([
            'success' => true,
            'data' => [
                'today' => [
                    'revenue' => $todayRevenue,
                    'date' => $todayStart->format('Y-m-d')
                ],
                'this_week' => [
                    'revenue' => $weekRevenue,
                    'start_date' => $weekStart->format('Y-m-d'),
                    'end_date' => $now->format('Y-m-d')
                ],
                'this_month' => [
                    'revenue' => $monthRevenue,
                    'start_date' => $monthStart->format('Y-m-d'),
                    'end_date' => $now->format('Y-m-d')
                ]
            ]
        ]);
    }

    /**
     * Tính doanh thu trong khoảng thời gian
     */
    private function calculateRevenue($userId, $start, $end)
    {
        $orders = Order::where('user_id', $userId)
            ->where('type', 1) // Chỉ đơn bán
            ->where('status_order', 1) // Chỉ đơn hoàn thành
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $totalRevenue = 0;

        foreach ($orders as $order) {
            if (isset($order->payment)) {
                // Nếu payment là array of payments
                if (is_array($order->payment) && isset($order->payment[0])) {
                    foreach ($order->payment as $payment) {
                        $totalRevenue += $payment['price'] ?? 0;
                    }
                }
                // Nếu payment là object đơn
                else if (isset($order->payment['price'])) {
                    $totalRevenue += $order->payment['price'];
                }
            }
        }

        return round($totalRevenue, 2);
    }
    /**
     * Báo cáo thu chi (phiếu thu từ đơn bán, phiếu chi từ đơn nhập)
     */
    public function incomeExpenseReport(Request $request)
    {
        $defaultStart = Carbon::now()->startOfMonth()->format('Y-m-d');
        $defaultEnd = Carbon::now()->format('Y-m-d');

        $validated = $request->validate([
            'start' => 'nullable|date',
            'end' => 'nullable|date|after_or_equal:start',
        ]);

        $start = $validated['start'] ?? $defaultStart;
        $end = $validated['end'] ?? $defaultEnd;

        // Lấy tất cả đơn hàng đã hoàn thành (cả bán và nhập) trong khoảng thời gian
        $orders = Order::where('user_id', User::getEffectiveUserId())
            ->where('status_order', 1) // Chỉ đơn hoàn thành
            ->whereBetween('created_at', [
                $start . ' 00:00:00',
                $end . ' 23:59:59'
            ])
            ->with(['customer', 'supplier'])
            ->orderBy('created_at', 'desc')
            ->get();

        $transactions = [];
        $totalIncome = 0;  // Tổng thu (từ đơn bán - type 1)
        $totalExpense = 0; // Tổng chi (từ đơn nhập - type 2)

        foreach ($orders as $order) {
            // Lấy số tiền từ payment
            $amount = 0;
            if (isset($order->payment)) {
                // Nếu payment là array of payments
                if (is_array($order->payment) && isset($order->payment[0])) {
                    foreach ($order->payment as $payment) {
                        $amount += $payment['price'] ?? 0;
                    }
                }
                // Nếu payment là object đơn
                else if (is_array($order->payment) && isset($order->payment['price'])) {
                    $amount = $order->payment['price'];
                }
            }

            // Lấy payment_type (ưu tiên payment đầu tiên nếu là array)
            $paymentType = null;
            if (isset($order->payment)) {
                if (is_array($order->payment) && isset($order->payment[0]['type'])) {
                    $paymentType = $order->payment[0]['type'];
                } else if (is_array($order->payment) && isset($order->payment['type'])) {
                    $paymentType = $order->payment['type'];
                }
            }

            // Lấy tên customer (đơn bán) hoặc supplier (đơn nhập)
            $name = null;
            if ($order->type == 1 && $order->customer) {
                $name = $order->customer->name;
            } else if ($order->type == 2 && $order->supplier) {
                $name = $order->supplier->name;
            }

            // Phân loại thu/chi
            if ($order->type == 1) {
                $totalIncome += $amount;
            } else if ($order->type == 2) {
                $totalExpense += $amount;
            }

            // Map payment_type sang tên dễ hiểu
            $paymentTypeName = match ($paymentType) {
                1 => 'Tiền mặt',
                2 => 'Chuyển khoản',
                3 => 'Thẻ',
                default => null
            };

            $transactions[] = [
                'id' => $order->id,
                'type' => $order->type, // 1: Thu (đơn bán), 2: Chi (đơn nhập)
                'type_name' => $order->type == 1 ? 'Phiếu thu' : 'Phiếu chi',
                'created_date' => $order->created_at->format('Y-m-d H:i:s'),
                'amount' => $amount,
                'name' => $name,
                'payment_type' => $paymentType,
                'payment_type_name' => $paymentTypeName,
                'note' => $order->note ?? null,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $transactions,
            'meta' => [
                'total_income' => round($totalIncome, 2),
                'total_expense' => round($totalExpense, 2),
                'net_profit' => round($totalIncome - $totalExpense, 2),
                'total_transactions' => count($transactions),
                'period' => [
                    'start' => $start,
                    'end' => $end
                ]
            ]
        ]);
    }
}
