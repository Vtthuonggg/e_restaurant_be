<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\User;
use App\Models\EmployeeManager;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Di chuyển dữ liệu từ owner_id sang employee_manager
        $employees = User::where('user_type', 3)
            ->whereNotNull('owner_id')
            ->get();

        foreach ($employees as $employee) {
            EmployeeManager::create([
                'user_id' => $employee->owner_id,
                'employee_id' => $employee->id,
                'role' => 'employee'
            ]);
        }
    }

    public function down(): void
    {
        EmployeeManager::truncate();
    }
};
