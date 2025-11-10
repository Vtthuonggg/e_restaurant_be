<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'retail_cost',
        'base_cost',
        'image',
        'unit',
        'category_ids',
        'ingredients'
    ];

    protected $casts = [
        'category_ids' => 'array',
        'ingredients' => 'array',
        'retail_cost' => 'integer',
    ];

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'product_category');
    }

    // Helper method để lấy ingredients với thông tin chi tiết
    public function getIngredientsWithDetails()
    {
        if (!$this->ingredients) return [];

        $ingredientIds = collect($this->ingredients)->pluck('id');
        $ingredientDetails = Ingredient::whereIn('id', $ingredientIds)->get()->keyBy('id');

        return collect($this->ingredients)->map(function ($ingredient) use ($ingredientDetails) {
            $detail = $ingredientDetails->get($ingredient['id']);
            return [
                'id' => $ingredient['id'],
                'quantity' => $ingredient['quantity'],
                'name' => $detail->name ?? null,
                'unit' => $detail->unit ?? null,
            ];
        });
    }
}
