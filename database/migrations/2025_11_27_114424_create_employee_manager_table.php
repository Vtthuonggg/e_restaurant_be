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
        Schema::create('employee_manager', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // user_type = 2 (chủ nhà hàng)
            $table->unsignedBigInteger('employee_id'); // user_type = 3 (nhân viên)
            $table->string('role')->default('employee'); // vai trò: staff, supervisor, cashier...
            $table->timestamps();

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('users')->onDelete('cascade');

            // Đảm bảo không có quan hệ trùng lặp
            $table->unique(['user_id', 'employee_id']);

            // Indexes
            $table->index('user_id');
            $table->index('employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_manager');
    }
};
