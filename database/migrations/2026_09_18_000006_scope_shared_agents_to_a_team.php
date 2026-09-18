<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->foreignId('shared_with_team_id')->nullable()->after('is_shared')->constrained('teams')->nullOnDelete();
        });

        DB::table('agents')->where('is_shared', true)->orderBy('id')->each(function (object $agent): void {
            $teamId = DB::table('users')->where('id', $agent->user_id)->value('current_team_id');
            DB::table('agents')->where('id', $agent->id)->update(['shared_with_team_id' => $teamId]);
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shared_with_team_id');
        });
    }
};
