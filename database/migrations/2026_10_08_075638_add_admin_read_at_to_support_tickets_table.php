<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->timestamp('admin_read_at')->nullable()->index();
        });

        DB::table('support_tickets')
            ->whereNull('admin_read_at')
            ->update(['admin_read_at' => DB::raw('created_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropIndex(['admin_read_at']);
            $table->dropColumn('admin_read_at');
        });
    }
};
