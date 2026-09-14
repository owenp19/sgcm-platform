<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('population_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('zone_type', 60)->default('sector');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->integer('total_population')->default(0);
            $table->integer('raizal_population')->default(0);
            $table->integer('other_population')->default(0);
            $table->string('color', 20)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('population_zones');
    }
};