<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'type',
        'room_id',
        'room_type',
        'note',
        'discount',
        'discount_type',
        'customer_id',
        'supplier_id',
        'status_order',
        'payment',
        'order_detail',
        'user_id'
    ];

    protected $casts = [
        'payment' => 'array',
        'order_detail' => 'array',
        'discount' => 'decimal:2'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
