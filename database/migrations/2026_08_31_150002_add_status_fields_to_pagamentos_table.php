<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 'status' é um campo genérico só pro fluxo manual (transferência) —
     * pendente/aguardando_confirmacao/aprovado/recusado/cancelado. Mercado
     * Pago e PDV continuam decidindo se um pagamento está "pago" via
     * mp_status/pago_em, exatamente como hoje (ver ProcessarWebhookMercadoPagoAction
     * e TelaVendaDireta::registrarPagamentoEmDinheiro) — nenhum código
     * existente lê ou escreve essa coluna nova, então não precisa de
     * backfill: fica null pra todo pagamento que não passou pelo fluxo de
     * transferência.
     */
    public function up(): void
    {
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->string('status')->nullable()->after('metodo');
            $table->foreignId('decidido_por_id')->nullable()->after('raw_payload')->constrained('users')->nullOnDelete();
            $table->dateTime('decidido_em')->nullable()->after('decidido_por_id');
            $table->string('motivo_recusa')->nullable()->after('decidido_em');

            $table->index(['barbearia_id', 'metodo', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->dropIndex(['barbearia_id', 'metodo', 'status']);
            $table->dropConstrainedForeignId('decidido_por_id');
            $table->dropColumn(['status', 'decidido_em', 'motivo_recusa']);
        });
    }
};
