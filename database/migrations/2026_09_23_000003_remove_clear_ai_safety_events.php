<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The monitoring table is a review queue: retain only events that may
        // require attention, not routine school-work requests.
        DB::table('ai_safety_events')
            ->where('classification', 'school_work')
            ->where('moderation_flagged', false)
            ->delete();
    }

    public function down(): void
    {
        // Deleted routine monitoring events cannot be reconstructed.
    }
};
