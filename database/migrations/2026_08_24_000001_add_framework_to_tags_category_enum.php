<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE tags MODIFY category ENUM('subject','role','status','general','framework') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE tags MODIFY category ENUM('subject','role','status','general') NOT NULL");
    }
};