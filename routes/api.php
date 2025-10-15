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
use App\Services\CloudinaryService;


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
    Route::apiResource('order', OrderController::class);
});
