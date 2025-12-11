<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class EmployeeManager extends Model
{
    protected $table = 'employee_manager';

    protected $fillable = [
        'user_id',      // chủ nhà hàng (user_type = 2)
        'employee_id',  // nhân viên (user_type = 3) 
        'role'

    ];



    // Quan hệ với User (Manager)
    public function manager()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Quan hệ với User (Employee)
    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }


    // Scope để lấy theo manager
    public function scopeForManager($query, $managerId)
    {
        return $query->where('user_id', $managerId);
    }
}
