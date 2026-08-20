<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage_logs', function (Blueprint $table) {
            // Dùng để gộp nhóm cho "Top Tasks" ở trang My Usage (vd "Email drafting", "Lesson planning")
            $table->string('task_category', 100)->nullable()->after('activity_title');
        });
    }

    public function down(): void
    {
        Schema::table('usage_logs', function (Blueprint $table) {
            $table->dropColumn('task_category');
        });
    }
};
