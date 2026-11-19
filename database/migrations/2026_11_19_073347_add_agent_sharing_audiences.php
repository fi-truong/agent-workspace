<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table): void {
            $table->string('sharing_scope', 20)->default('school');
            $table->string('sharing_department', 100)->nullable();
        });
        Schema::create('agent_share_recipients', function (Blueprint $table): void {
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['agent_id', 'user_id'], 'agent_share_recipients_pk');
            $table->index(['user_id', 'agent_id'], 'agent_share_recipients_user_idx');
        });
    }

    public function down(): void
    {
        // Removing audience restrictions must never turn a limited share into a school-wide share.
        $restricted = DB::table('agents')->where('sharing_scope', '!=', 'school')->select('id');
        DB::table('showcase_posts')->whereIn('source_agent_id', $restricted)->update(['status' => 'draft']);
        DB::table('agents')->where('sharing_scope', '!=', 'school')->update(['is_shared' => false]);
        Schema::dropIfExists('agent_share_recipients');
        Schema::table('agents', function (Blueprint $table): void {
            $table->dropColumn(['sharing_scope', 'sharing_department']);
        });
    }
};
