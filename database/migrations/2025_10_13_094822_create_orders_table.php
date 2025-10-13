<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('type')->default(1); // 1: đơn bán, 2: đơn nhập
            $table->unsignedBigInteger('room_id')->nullable();
            $table->string('room_type')->default('free'); // free, using, pre_book
            $table->text('note')->nullable();
            $table->decimal('discount', 15, 2)->default(0);
            $table->tinyInteger('discount_type')->default(1); // 1: %, 2: VNĐ
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->tinyInteger('status_order')->default(2); // 1: hoàn thành, 2: chờ xác nhận
            $table->json('payment'); // {"type": 1, "price": 0}
            $table->json('order_detail'); // array của products
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('set null');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
