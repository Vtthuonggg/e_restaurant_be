<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $fillable = ['name', 'area_id', 'user_id', 'status'];
    public function area()
    {
        return $this->belongsTo(Area::class);
    }
}
