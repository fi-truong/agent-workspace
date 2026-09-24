<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolKnowledgeSource extends Model
{
    use HasFactory;

    public const TYPE_UPLOAD = 'upload';

    public const TYPE_WEBSITE = 'website';

    protected $fillable = [
        'created_by', 'type', 'title', 'source_url', 'storage_path', 'original_name',
        'mime_type', 'status', 'failure_reason', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return ['last_synced_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(SchoolKnowledgeChunk::class);
    }
}
