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
    public function getPaymentAttribute($value)
    {
        $data = $value;

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            $data = $decoded === null ? $value : $decoded;
        }

        if (is_array($data)) {
            return $this->normalizeNumericValues($data);
        }

        return $data;
    }

    /**
     * Accessor to ensure order_detail subtree uses numeric types for price/quantity/discount fields.
     * Accepts either array or JSON string in DB.
     */
    public function getOrderDetailAttribute($value)
    {
        $data = $value;

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            $data = $decoded === null ? $value : $decoded;
        }

        if (is_array($data)) {
            return $this->normalizeNumericValues($data);
        }

        return $data;
    }

    /**
     * Recursively convert numeric-looking values for known keys into int/float.
     * Keys handled: price, price, retail_cost, base_cost, cost, quantity, in_stock, available, discount
     */
    private function normalizeNumericValues(array $data)
    {
        $numericKeys = [
            'price',
            'price',
            'retail_cost',
            'base_cost',
            'cost',
            'quantity',
            'in_stock',
            'available',
            'discount'
        ];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // If this is an indexed array (list), map each item
                if ($this->isAssoc($value)) {
                    $data[$key] = $this->normalizeNumericValues($value);
                } else {
                    $normalizedList = [];
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $normalizedList[] = $this->normalizeNumericValues($item);
                        } else {
                            // non-array scalar in list
                            $normalizedList[] = $this->maybeCastNumeric($item, $numericKeys, $key);
                        }
                    }
                    $data[$key] = $normalizedList;
                }
            } else {
                $data[$key] = $this->maybeCastNumeric($value, $numericKeys, $key);
            }
        }

        return $data;
    }

    /**
     * Casts $value to int/float if numeric and key matches numericKeys or $parentKey is a known numeric container.
     */
    private function maybeCastNumeric($value, array $numericKeys, $currentKey)
    {
        // If key is one of the numeric keys and value is numeric string or numeric
        if (in_array($currentKey, $numericKeys, true) && is_numeric($value)) {
            $num = (float) $value;
            return floor($num) == $num ? (int) $num : $num;
        }

        // Also handle common case where array item is 'payment' and it contains 'price'
        // but this method only sees scalar - so leave as-is
        return $value;
    }

    /**
     * Check if array is associative
     */
    private function isAssoc(array $arr)
    {
        if ([] === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
