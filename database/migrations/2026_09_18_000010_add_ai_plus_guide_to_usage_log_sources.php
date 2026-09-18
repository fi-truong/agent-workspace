<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE usage_logs MODIFY source ENUM('agent_workspace', 'template_used', 'ai_plus_guide') NOT NULL");

            return;
        }

        Schema::table('usage_logs', function (Blueprint $table) {
            $table->string('source')->change();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::table('usage_logs')->where('source', 'ai_plus_guide')->update(['source' => 'agent_workspace']);
            DB::statement("ALTER TABLE usage_logs MODIFY source ENUM('agent_workspace', 'template_used') NOT NULL");

            return;
        }

        Schema::table('usage_logs', function (Blueprint $table) {
            $table->string('source')->change();
        });
    }
};
