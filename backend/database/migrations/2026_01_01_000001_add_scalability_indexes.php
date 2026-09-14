<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Índice compuesto para el job horario que marca tarjetas vencidas.
        // Sin él, la consulta recorre toda la tabla en cada ejecución (riesgo con 10k+ filas).
        DB::statement(
            'ALTER TABLE tourist_cards ADD INDEX idx_tourist_cards_expire (payment_status, card_status, expires_at)'
        );

        // Búsquedas por documento/email (verificación, referencia cruzada, futura búsqueda global).
        DB::statement(
            'ALTER TABLE tourist_cards ADD INDEX idx_tourist_cards_document (document_number)'
        );
        DB::statement(
            'ALTER TABLE tourist_cards ADD INDEX idx_tourist_cards_email (email)'
        );

        // Consultas del panel admin sobre el log de auditoría (filtran por user + fecha).
        DB::statement(
            'ALTER TABLE audit_log ADD INDEX idx_audit_log_user_created (user_id, created_at)'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tourist_cards DROP INDEX idx_tourist_cards_expire');
        DB::statement('ALTER TABLE tourist_cards DROP INDEX idx_tourist_cards_document');
        DB::statement('ALTER TABLE tourist_cards DROP INDEX idx_tourist_cards_email');
        DB::statement('ALTER TABLE audit_log DROP INDEX idx_audit_log_user_created');
    }
};
