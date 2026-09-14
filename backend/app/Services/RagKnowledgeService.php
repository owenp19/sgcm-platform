<?php

namespace App\Services;

use App\Models\KnowledgeChunk;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RagKnowledgeService
{
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta';

    public function embed(?string $text): array
    {
        $text = trim((string) $text);

        if ($text === '') {
            return [];
        }

        $apiKey = config('services.gemini.key');
        $model = config('services.gemini.embed_model', 'text-embedding-004');

        if (! $apiKey) {
            throw new \RuntimeException('GEMINI_API_KEY no configurada');
        }

        $response = Http::timeout(30)->post(
            self::API_BASE."/models/{$model}:embedContent?key={$apiKey}",
            [
                'model' => "models/{$model}",
                'content' => [
                    'parts' => [['text' => mb_substr($text, 0, 4000)]],
                ],
            ]
        );

        if ($response->failed()) {
            $message = $response->json('error.message');
            Log::error('[RAG] Error embeddings', ['status' => $response->status(), 'message' => $message]);

            throw new \RuntimeException($message ?: 'No fue posible generar embeddings.');
        }

        return $response->json('embedding.values') ?? [];
    }

    public function rebuild(): array
    {
        KnowledgeChunk::query()->delete();

        $count = 0;
        $count += $this->indexProcedures();
        $count += $this->indexStaticKnowledge();

        Log::info('[RAG] Índice reconstruido', ['chunks' => $count]);

        return [
            'indexed' => $count,
        ];
    }

    public function indexProcedures(): int
    {
        $procedures = \App\Models\ProcedureType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $count = 0;

        foreach ($procedures as $procedure) {
            $requirements = collect($procedure->requirements ?? [])
                ->map(fn ($req) => is_array($req) ? ($req['description'] ?? json_encode($req)) : (string) $req)
                ->filter()
                ->map(fn ($item) => '- '.trim($item))
                ->implode("\n");

            $cost = $procedure->base_cost > 0 ? " Valor estimado: \$".number_format((float) $procedure->base_cost, 0).' COP.' : '';
            $days = $procedure->estimated_days > 0 ? " Tiempo estimado: {$procedure->estimated_days} días hábiles." : '';

            $content = trim(
                implode("\n", array_filter([
                    $procedure->description,
                    $requirements !== '' ? "Requisitos:\n{$requirements}" : null,
                    $cost,
                    $days,
                ]))
            );

            if ($content === '') {
                continue;
            }

            if ($this->upsertChunk($procedure->name, $content, 'Trámites OCCRE')) {
                $count++;
            }
        }

        return $count;
    }

    public function indexStaticKnowledge(): int
    {
        $path = database_path('knowledge/knowledge_base.json');

        if (! file_exists($path)) {
            Log::warning('[RAG] No existe database/knowledge/knowledge_base.json');

            return 0;
        }

        $entries = json_decode((string) file_get_contents($path), true);

        if (! is_array($entries)) {
            return 0;
        }

        $count = 0;

        foreach ($entries as $entry) {
            $title = trim((string) ($entry['title'] ?? ''));
            $content = trim((string) ($entry['content'] ?? ''));

            if ($title === '' || $content === '') {
                continue;
            }

            if (
                $this->upsertChunk(
                    $title,
                    $content,
                    (string) ($entry['source_label'] ?? 'OCCRE'),
                    (string) ($entry['source_url'] ?? '')
                )
            ) {
                $count++;
            }
        }

        return $count;
    }

    public function search(string $query, int $topK = 4, float $minScore = 0.35): array
    {
        $question = trim($query);

        if ($question === '') {
            return [];
        }

        try {
            $queryVector = $this->embed($question);
        } catch (\RuntimeException $e) {
            Log::warning('[RAG] Embedding de búsqueda falló', ['message' => $e->getMessage()]);

            return [];
        }

        if ($queryVector === []) {
            return [];
        }

        return KnowledgeChunk::query()
            ->whereNotNull('embedding')
            ->get()
            ->map(function (KnowledgeChunk $chunk) use ($queryVector) {
                return (object) [
                    'chunk' => $chunk,
                    'score' => KnowledgeChunk::cosineSimilarity($queryVector, $chunk->embedding ?? []),
                ];
            })
            ->filter(fn ($item) => $item->score >= $minScore)
            ->sortByDesc('score')
            ->take($topK)
            ->values()
            ->all();
    }

    public function buildContext(string $question, int $topK = 4): string
    {
        $results = $this->search($question, $topK);

        if ($results === []) {
            return '';
        }

        $sections = collect($results)->map(function ($item) {
            $chunk = $item->chunk;
            $source = $chunk->source_label !== null ? ' (Fuente: '.$chunk->source_label.')' : '';

            return "[{$chunk->title}]".$source."\n".$chunk->content;
        })->implode("\n\n");

        return $sections;
    }

    private function upsertChunk(
        string $title,
        string $content,
        ?string $sourceLabel = null,
        ?string $sourceUrl = null
    ): bool {
        $content = mb_substr($content, 0, 6000);

        try {
            $embedding = $this->embed($content);
        } catch (\RuntimeException $e) {
            Log::warning('[RAG] No se pudo indexar: '.$title, ['message' => $e->getMessage()]);

            return false;
        }

        if ($embedding === []) {
            return false;
        }

        KnowledgeChunk::create([
            'title' => $title,
            'content' => $content,
            'source_label' => $sourceLabel,
            'source_url' => $sourceUrl,
            'embedding_dim' => count($embedding),
            'embedding' => $embedding,
        ]);

        return true;
    }
}