<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\QrOrderController;

Route::prefix('otp')->group(function () {
    Route::post('/send', [OtpController::class, 'sendOtp']);
    Route::post('/verify', [OtpController::class, 'verifyOtp']);
    Route::post('/resend', [OtpController::class, 'resendOtp']);
    Route::post('/register', [OtpController::class, 'registerWithOtp']);
});
Route::prefix('qr-order')->group(function () {
    Route::get('/products', [App\Http\Controllers\QrOrderController::class, 'getProducts']);
    Route::post('/create', [App\Http\Controllers\QrOrderController::class, 'createOrder']);
});
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', function (Request $request) {
        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => $request->user(),
            ]
        ]);
    });
    Route::put('/user', [AuthController::class, 'updateProfile']);
    Route::apiResource('customer', CustomerController::class);
    Route::apiResource('supplier', SupplierController::class);
    Route::apiResource('category', CategoryController::class);
    Route::apiResource('employee', EmployeeController::class);
    Route::apiResource('product', ProductController::class);
    Route::apiResource('ingredient', IngredientController::class);
    Route::apiResource('area', AreaController::class);
    Route::apiResource('room', RoomController::class);
    Route::post('/room/list', [RoomController::class, 'list']);
    Route::apiResource('order', OrderController::class);
    Route::prefix('report')->group(function () {
        Route::get('/revenue', [ReportController::class, 'revenueReport']);
        Route::get('/product-sales', [ReportController::class, 'productSalesReport']);
        Route::get('/ingredient-purchase', [ReportController::class, 'ingredientPurchaseReport']);
        Route::get('/dashboard', [ReportController::class, 'dashboardReport']);
        Route::get('/quick-stats', [ReportController::class, 'quickStats']);
        Route::get('/income-expense', [ReportController::class, 'incomeExpenseReport']);
    });
});
