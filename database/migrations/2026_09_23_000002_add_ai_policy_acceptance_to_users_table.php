<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('ai_policy_accepted_at')->nullable()->after('last_login_at');
            $table->string('ai_policy_version', 20)->nullable()->after('ai_policy_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ai_policy_accepted_at', 'ai_policy_version']);
        });
    }
};
