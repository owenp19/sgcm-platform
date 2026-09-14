<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tourism_tariffs', function (Blueprint $table) {
            $table->id();
            $table->string('concept', 255);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('COP');
            $table->boolean('active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tourist_cards', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();

            $table->string('first_name', 255)->nullable();
            $table->string('last_name', 255)->nullable();
            $table->string('document_type', 50)->nullable();
            $table->string('document_number', 50);
            $table->date('birth_date')->nullable();
            $table->string('nationality', 100)->nullable();
            $table->string('country_residence', 100)->nullable();
            $table->string('city_residence', 100)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('emergency_contact_name', 255)->nullable();
            $table->string('emergency_contact_phone', 50)->nullable();

            $table->date('entry_date')->nullable();
            $table->date('return_date')->nullable();
            $table->string('transport_type', 100)->nullable();
            $table->string('airline_or_company', 255)->nullable();
            $table->string('flight_number', 50)->nullable();
            $table->string('origin_city', 100)->nullable();
            $table->string('travel_reason', 255)->nullable();

            $table->string('lodging_type', 100)->nullable();
            $table->string('lodging_name', 255)->nullable();
            $table->string('lodging_address', 500)->nullable();
            $table->string('lodging_sector', 100)->nullable();
            $table->string('lodging_phone', 50)->nullable();
            $table->string('lodging_responsible_name', 255)->nullable();

            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 10)->default('COP');
            $table->enum('payment_status', ['Pendiente', 'Pagado', 'Rechazado', 'Anulado'])->default('Pendiente');
            $table->string('card_status', 50)->default('Pendiente de pago');
            $table->text('qr_code')->nullable();
            $table->text('receipt_url')->nullable();
            $table->boolean('accepted_terms')->default(false);
            $table->boolean('accepted_location_consent')->default(false);

            $table->string('receipt_number', 50)->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->string('qr_token', 36)->nullable()->index();
            $table->text('qr_validation_url')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->string('language', 10)->default('es');
            $table->time('return_time')->nullable();
            $table->boolean('notify_email')->default(true);
            $table->boolean('notify_sms')->default(false);
            $table->boolean('notify_push')->default(false);
            $table->integer('reminder_days_before')->default(1);

            $table->timestamps();
        });

        Schema::create('tourist_card_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tourist_card_id')->constrained('tourist_cards')->cascadeOnDelete();
            $table->string('payment_reference', 100);
            $table->string('gateway', 50)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 10)->default('COP');
            $table->string('status', 50)->default('Pendiente');
            $table->json('gateway_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('receipt_number', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('tourist_card_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tourist_card_id')->constrained('tourist_cards')->cascadeOnDelete();
            $table->string('receipt_number', 50)->nullable();
            $table->text('pdf_path')->nullable();
            $table->text('qr_payload')->nullable();
            $table->string('qr_token', 36)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tourist_location_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tourist_card_id')->constrained('tourist_cards')->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy', 10, 2)->nullable();
            $table->string('reason', 100)->default('voluntario');
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('tourist_location_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tourist_card_id')->constrained('tourist_cards')->cascadeOnDelete();
            $table->boolean('consent_given')->default(false);
            $table->string('consent_type', 50)->default('entry');
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('tourist_return_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tourist_card_id')->constrained('tourist_cards')->cascadeOnDelete();
            $table->enum('reminder_type', ['email', 'sms', 'push'])->default('email');
            $table->dateTime('remind_at');
            $table->boolean('sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('tourist_device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tourist_card_id')->constrained('tourist_cards')->cascadeOnDelete();
            $table->string('token', 500);
            $table->enum('platform', ['android', 'ios', 'web']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tourist_device_tokens');
        Schema::dropIfExists('tourist_return_reminders');
        Schema::dropIfExists('tourist_location_consents');
        Schema::dropIfExists('tourist_location_events');
        Schema::dropIfExists('tourist_card_receipts');
        Schema::dropIfExists('tourist_card_payments');
        Schema::dropIfExists('tourist_cards');
        Schema::dropIfExists('tourism_tariffs');
    }
};
