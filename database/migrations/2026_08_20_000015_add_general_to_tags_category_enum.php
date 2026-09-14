<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Cú pháp ALTER ... MODIFY là MySQL-only. Test dùng SQLite (:memory:) không hỗ trợ
        // ENUM bound — bỏ qua để migration chạy được trên cả hai. Production vẫn dùng MySQL.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE tags MODIFY category ENUM('subject','role','status','general') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE tags MODIFY category ENUM('subject','role','status') NOT NULL");
    }
};
