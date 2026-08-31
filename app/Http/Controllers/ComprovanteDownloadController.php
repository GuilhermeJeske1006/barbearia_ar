<?php

namespace App\Http\Controllers;

use App\Models\Pagamento;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Único jeito de ler um comprovante — nunca uma URL pública direta pro disco
 * 'comprovantes'. {pagamento} é resolvido por binding implícito, então já
 * cai sob o scope fail-closed de BelongsToBarbearia: usuário de outra
 * barbearia recebe 404, não 403 (não revela nem que o registro existe). A
 * rota (routes/auth.php) já exige 'can:financeiro.gerenciar' antes de chegar
 * aqui.
 */
class ComprovanteDownloadController extends Controller
{
    public function __invoke(Pagamento $pagamento): StreamedResponse
    {
        $comprovante = $pagamento->comprovantes()->latest('enviado_em')->firstOrFail();

        return Storage::disk('comprovantes')->response($comprovante->path, $comprovante->nome_original);
    }
}
