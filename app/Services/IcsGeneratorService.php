<?php

namespace App\Services;

use App\Models\Agendamento;

class IcsGeneratorService
{
    public function paraAgendamento(Agendamento $agendamento): string
    {
        $inicio = $agendamento->data_hora_inicio->clone()->utc()->format('Ymd\THis\Z');
        $fim = $agendamento->data_hora_fim->clone()->utc()->format('Ymd\THis\Z');
        $agora = now()->utc()->format('Ymd\THis\Z');
        $servicos = $agendamento->servicos->pluck('nome')->join(', ');
        $barbearia = $agendamento->barbearia;

        $linhas = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.$this->escapar($barbearia->nome).'//Agendamento//PT',
            'BEGIN:VEVENT',
            'UID:agendamento-'.$agendamento->id.'@'.parse_url(config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.$agora,
            'DTSTART:'.$inicio,
            'DTEND:'.$fim,
            'SUMMARY:'.$this->escapar($barbearia->nome.' - '.$servicos),
        ];

        if ($barbearia->endereco) {
            $linhas[] = 'LOCATION:'.$this->escapar($barbearia->endereco);
        }

        $linhas[] = 'DESCRIPTION:'.$this->escapar(__('agendamento.ics_descricao', ['numero' => $agendamento->id]));
        $linhas[] = 'END:VEVENT';
        $linhas[] = 'END:VCALENDAR';

        return implode("\r\n", $linhas);
    }

    private function escapar(string $texto): string
    {
        return str_replace(["\\", ",", ";", "\n"], ["\\\\", "\\,", "\\;", "\\n"], $texto);
    }
}
