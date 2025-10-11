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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('base_cost')->default(0);
            $table->integer('retail_cost')->default(0);
            $table->double('in_stock', 15, 2)->default(0);
            $table->string('image')->nullable();
            $table->string('unit')->nullable();
            $table->json('category_ids')->nullable(); 
            $table->json('ingredients')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
