<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',           // Dùng để đăng nhập
        'phone',           // Chỉ là thông tin, nullable
        'password',
        'image',
        'api_key',
        'user_type',       // 2: manager, 3: employee
        'owner_id',
        'store_name',
        'address',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function suppliers()
    {
        return $this->hasMany(Supplier::class);
    }

    public function managedEmployees()
    {
        return $this->hasManyThrough(
            User::class,
            EmployeeManager::class,
            'user_id',
            'id',
            'id',
            'employee_id'
        );
    }

    public function managers()
    {
        return $this->hasManyThrough(
            User::class,
            EmployeeManager::class,
            'employee_id',
            'id',
            'id',
            'user_id'
        );
    }

    public function employeeManagerRelation()
    {
        return $this->hasMany(EmployeeManager::class, 'employee_id');
    }

    public function managerEmployeeRelation()
    {
        return $this->hasMany(EmployeeManager::class, 'user_id');
    }
}
