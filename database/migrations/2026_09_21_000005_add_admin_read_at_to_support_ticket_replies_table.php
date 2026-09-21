<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_ticket_replies', function (Blueprint $table) {
            $table->timestamp('admin_read_at')->nullable()->after('read_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('support_ticket_replies', function (Blueprint $table) {
            $table->dropIndex(['admin_read_at']);
            $table->dropColumn('admin_read_at');
        });
    }
};
