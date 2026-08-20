<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('initials', 4)->nullable();
            $table->string('role', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('entra_id')->nullable()->unique();
            $table->unsignedInteger('daily_prompt_quota')->default(50);
            $table->string('source_system', 50)->default('Entra ID');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['initials', 'role', 'department', 'entra_id', 'daily_prompt_quota', 'source_system']);
        });
    }
};
