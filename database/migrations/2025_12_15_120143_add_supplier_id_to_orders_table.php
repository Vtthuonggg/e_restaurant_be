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
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'supplier_id')) {
                $table->unsignedBigInteger('supplier_id')->nullable()->after('customer_id');
                $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'supplier_id')) {
                $table->dropForeign([$table->getConnection()->getTablePrefix() . 'supplier_id']);
                $table->dropColumn('supplier_id');
            }
        });
    }
};
