<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('barbearias', function (Blueprint $table) {
            // plano_id era uma coluna solta sem tabela 'planos' e sem uso —
            // resquício de um planejamento anterior nunca implementado.
            // Assinatura SaaS é plano único, controlado via Stripe.
            $table->dropColumn('plano_id');

            $table->string('stripe_customer_id')->nullable()->after('status');
            $table->string('stripe_subscription_id')->nullable()->after('stripe_customer_id');
            // Espelha o status da Subscription no Stripe (active|past_due|
            // canceled|incomplete), atualizado só via webhook ou logo após
            // a confirmação do checkout no onboarding. Não confundir com
            // 'status' (ativa|suspensa|trial) da barbearia em si.
            $table->string('subscription_status')->nullable()->after('stripe_subscription_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('barbearias', function (Blueprint $table) {
            $table->dropColumn(['stripe_customer_id', 'stripe_subscription_id', 'subscription_status']);
            $table->foreignId('plano_id')->nullable();
        });
    }
};
