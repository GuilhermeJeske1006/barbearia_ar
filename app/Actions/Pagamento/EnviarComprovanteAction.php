<?php

namespace App\Actions\Pagamento;

use App\Models\ComprovantePagamento;
use App\Models\Pagamento;
use App\Models\User;
use App\Notifications\PagamentoTransferenciaRecebido;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Envio de comprovante NUNCA aprova o pagamento — só move
 * pendente/recusado -> aguardando_confirmacao e guarda a evidência pro dono
 * analisar (ver ConfirmarPagamentoTransferenciaAction). Arquivo salvo com
 * nome gerado (nunca o nome original) num disco privado (config/filesystems.php,
 * disco 'comprovantes') — sem URL pública, só acessível via
 * ComprovanteDownloadController.
 */
class EnviarComprovanteAction
{
    private const STATUS_PERMITIDOS = ['pendente', 'recusado'];

    private const EXTENSOES_PERMITIDAS = ['jpg', 'jpeg', 'png', 'pdf'];

    public function handle(Pagamento $pagamento, UploadedFile $arquivo): ComprovantePagamento
    {
        $extensao = strtolower((string) $arquivo->getClientOriginalExtension());

        if (! in_array($extensao, self::EXTENSOES_PERMITIDAS, true)) {
            throw new RuntimeException('Formato de arquivo não permitido.');
        }

        [$comprovante, $pagamentoAtualizado] = DB::transaction(function () use ($pagamento, $arquivo, $extensao) {
            $pagamentoLocked = Pagamento::lockForUpdate()->findOrFail($pagamento->id);

            if (! in_array($pagamentoLocked->status, self::STATUS_PERMITIDOS, true)) {
                throw new RuntimeException('Este pagamento não está aguardando envio de comprovante.');
            }

            $nomeInterno = Str::uuid().'.'.$extensao;
            $path = $arquivo->storeAs((string) $pagamentoLocked->id, $nomeInterno, 'comprovantes');

            $comprovante = ComprovantePagamento::create([
                'pagamento_id' => $pagamentoLocked->id,
                'path' => $path,
                'nome_original' => $arquivo->getClientOriginalName(),
                'mime' => $arquivo->getMimeType(),
                'tamanho' => $arquivo->getSize(),
                'enviado_em' => now(),
            ]);

            $pagamentoLocked->update(['status' => 'aguardando_confirmacao', 'motivo_recusa' => null]);

            return [$comprovante, $pagamentoLocked];
        });

        // Fora da transação, mesmo padrão do wizard/webhook: falha ao
        // notificar não pode desfazer o comprovante já salvo.
        try {
            $donos = User::where('barbearia_atual_id', $pagamentoAtualizado->barbearia_id)
                ->where('tipo', 'dono')
                ->where('ativo', true)
                ->get();

            Notification::send($donos, new PagamentoTransferenciaRecebido($pagamentoAtualizado->fresh()));
        } catch (\Throwable $e) {
            report($e);
        }

        return $comprovante;
    }
}
