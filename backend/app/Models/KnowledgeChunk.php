<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeChunk extends Model
{
    protected $table = 'knowledge_chunks';

    protected $fillable = [
        'title',
        'content',
        'source_label',
        'source_url',
        'embedding_dim',
        'embedding',
    ];

    protected function casts(): array
    {
        return [
            'embedding_dim' => 'integer',
            'embedding' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function cosineSimilarity(array $vectorA, array $vectorB): float
    {
        $count = min(count($vectorA), count($vectorB));
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $a = (float) $vectorA[$i];
            $b = (float) $vectorB[$i];
            $dot += $a * $b;
            $normA += $a * $a;
            $normB += $b * $b;
        }

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}