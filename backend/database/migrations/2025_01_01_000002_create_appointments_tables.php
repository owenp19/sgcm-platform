<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_services', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->string('icon', 100)->default('document-text-outline');
            $table->boolean('requires_documents')->default(false);
            $table->integer('duration_minutes')->default(15);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('address', 500)->nullable();
            $table->string('phone', 30)->nullable();
            $table->json('hours_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('procedure_type_id')->nullable()->index();
            $table->unsignedBigInteger('office_id')->nullable()->index();
            $table->string('appointment_code', 20)->unique();
            $table->date('scheduled_date');
            $table->time('scheduled_time');
            $table->enum('status', ['pendiente', 'confirmada', 'cancelada', 'completada'])->default('pendiente');
            $table->text('notes')->nullable();
            $table->string('citizen_full_name', 200)->nullable();
            $table->string('citizen_document_type', 50)->nullable();
            $table->string('citizen_document_number', 50)->nullable();
            $table->string('citizen_email', 255)->nullable();
            $table->string('citizen_phone', 30)->nullable();
            $table->timestamps();

            $table->foreign('procedure_type_id')->references('id')->on('procedure_types')->nullOnDelete();
            $table->foreign('office_id')->references('id')->on('offices')->nullOnDelete();
        });

        Schema::create('appointment_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->string('original_name', 255);
            $table->string('stored_name', 255);
            $table->string('mime_type', 100);
            $table->integer('file_size');
            $table->string('file_path', 500);
            $table->string('document_type', 100)->default('support');
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_documents');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('offices');
        Schema::dropIfExists('appointment_services');
    }
};
