<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Đảm bảo email là unique và not null
            if (!Schema::hasColumn('users', 'email')) {
                $table->string('email')->unique()->after('name');
            } else {
                // Nếu đã có email nhưng chưa unique
                DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NOT NULL');

                // Xóa unique constraint cũ nếu có
                try {
                    $table->dropUnique(['email']);
                } catch (\Exception $e) {
                    // Ignore nếu không có unique constraint
                }

                // Thêm lại unique constraint
                $table->unique('email');
            }

            // Phone chỉ là thông tin bổ sung, nullable
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 20)->nullable()->after('email');
            } else {
                DB::statement('ALTER TABLE users MODIFY phone VARCHAR(20) NULL');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Không rollback để tránh mất dữ liệu
        });
    }
};
