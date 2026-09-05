<?php

namespace App\Actions\SuperAdmin;

use App\Models\Barbearia;
use Illuminate\Support\Facades\Log;

/**
 * Único ponto de suspensão/reativação de barbearia, usado pela lista e pelo
 * detalhe de Super Admin — evita duplicar a regra + o log de auditoria.
 */
class AlternarStatusBarbeariaAction
{
    public function handle(Barbearia $barbearia, int $superAdminId): Barbearia
    {
        $novoStatus = $barbearia->status === 'suspensa' ? 'ativa' : 'suspensa';

        $barbearia->update(['status' => $novoStatus]);

        Log::info('superadmin.barbearia.status_alterado', [
            'super_admin_id' => $superAdminId,
            'barbearia_id' => $barbearia->id,
            'novo_status' => $novoStatus,
        ]);

        return $barbearia->fresh();
    }
}
