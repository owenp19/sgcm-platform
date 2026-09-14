<?php

namespace App\Console\Commands;

use App\Services\RagKnowledgeService;
use Illuminate\Console\Command;

class IndexKnowledge extends Command
{
    protected $signature = 'rag:index';

    protected $description = 'Reconstruye el índice de conocimiento RAG (procedimientos + base de conocimiento estática)';

    public function handle(RagKnowledgeService $rag): int
    {
        try {
            $result = $rag->rebuild();

            $this->info("Índice reconstruido: {$result['indexed']} fragmentos indexados.");

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}