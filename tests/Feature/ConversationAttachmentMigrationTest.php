<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

it('completes the attachment migration when the table exists but its index is missing', function () {
    $index = 'conv_att_conv_user_msg_idx';

    expect(Schema::hasTable('conversation_attachments'))->toBeTrue()
        ->and(Schema::hasIndex('conversation_attachments', $index))->toBeTrue();

    Schema::table('conversation_attachments', function (Blueprint $table) use ($index): void {
        $table->dropIndex($index);
    });

    $migration = require database_path('migrations/2026_11_04_100331_create_conversation_attachments_table.php');
    $migration->up();

    expect(Schema::hasIndex('conversation_attachments', $index))->toBeTrue();
});
