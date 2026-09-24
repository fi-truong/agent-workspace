<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolKnowledgeChunk extends Model
{
    protected $fillable = ['school_knowledge_source_id', 'chunk_index', 'content', 'embedding'];

    protected function casts(): array
    {
        return ['embedding' => 'array'];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(SchoolKnowledgeSource::class, 'school_knowledge_source_id');
    }
}
