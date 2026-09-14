<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('content');
            $table->string('source_label', 255)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->unsignedInteger('embedding_dim')->default(0);
            $table->json('embedding')->nullable();
            $table->timestamps();

            $table->index('title', 'idx_knowledge_title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
    }
};