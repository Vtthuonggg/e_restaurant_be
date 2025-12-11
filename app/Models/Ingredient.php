<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    protected $fillable = [
        'name',
        'user_id',
        'base_cost',
        'retail_cost',
        'in_stock',
        'available',
        'image',
        'unit'
    ];

    protected $casts = [
        'base_cost' => 'integer',
        'retail_cost' => 'integer',
        'in_stock' => 'double',
        'available' => 'double'
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

