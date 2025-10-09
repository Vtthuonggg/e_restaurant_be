<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = ['id', 'name', 'phone', 'address'];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
