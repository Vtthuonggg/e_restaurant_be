<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $fillable = ['name', 'area_id', 'user_id', 'status'];
    protected $hidden = ['area_id'];
    public function area()
    {
        return $this->belongsTo(Area::class);
    }
}
