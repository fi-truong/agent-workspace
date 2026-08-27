<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agent_templates', function (Blueprint $table) {
            $table->renameColumn('title', 'name');
            $table->string('status', 20)->default('draft')->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_templates', function (Blueprint $table) {
            $table->renameColumn('name', 'title');
            $table->dropColumn('status');
        });
    }
};