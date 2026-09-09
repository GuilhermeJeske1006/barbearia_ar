<?php

namespace App\Livewire\Admin\Configuracoes;

use App\Models\Barbearia;
use App\Models\BarbeariaFoto;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts::app')]
class ConfiguracoesBarbearia extends Component
{
    use WithFileUploads;

    public const TIMEZONES = [
        'America/Argentina/Buenos_Aires', 'America/Sao_Paulo', 'America/Santiago',
        'America/Bogota', 'America/Lima', 'America/Mexico_City', 'America/Montevideo', 'UTC',
    ];

    public const MOEDAS = ['ARS', 'BRL', 'USD', 'MXN', 'CLP', 'COP', 'PEN', 'UYU'];

    #[Validate('required|string|max:255')]
    public string $nome = '';

    #[Validate('required|string|max:255|alpha_dash')]
    public string $slug = '';

    #[Validate('nullable|string|max:30')]
    public string $cuit = '';

    #[Validate('nullable|string|max:255')]
    public string $endereco = '';

    #[Validate('nullable|string|max:100')]
    public string $cidade = '';

    #[Validate('nullable|string|max:100')]
    public string $provincia = '';

    #[Validate('nullable|string|max:30')]
    public string $telefone = '';

    #[Validate('nullable|email|max:255')]
    public string $email = '';

    #[Validate('nullable|string|max:1000')]
    public string $descricao = '';

    #[Validate('nullable|string|max:255')]
    public string $instagram = '';

    #[Validate('required|string|timezone')]
    public string $timezone = '';

    #[Validate('required|string')]
    public string $moeda = '';

    #[Validate('required|in:es,pt')]
    public string $idiomaPadrao = '';

    #[Validate('nullable|image|max:2048')]
    public $logo = null;

    #[Validate('nullable|image|max:4096')]
    public $capa = null;

    #[Validate('nullable|array|max:10')]
    public array $novasFotos = [];

    public function mount(): void
    {
        $barbearia = $this->barbearia();

        $this->nome = $barbearia->nome;
        $this->slug = $barbearia->slug;
        $this->cuit = (string) $barbearia->cuit;
        $this->endereco = (string) $barbearia->endereco;
        $this->cidade = (string) $barbearia->cidade;
        $this->provincia = (string) $barbearia->provincia;
        $this->telefone = (string) $barbearia->telefone;
        $this->email = (string) $barbearia->email;
        $this->descricao = (string) $barbearia->descricao;
        $this->instagram = (string) $barbearia->instagram;
        $this->timezone = $barbearia->timezone;
        $this->moeda = $barbearia->moeda;
        $this->idiomaPadrao = $barbearia->idioma_padrao;
    }

    private function barbearia(): Barbearia
    {
        return app('barbearia');
    }

    public function salvar(): void
    {
        $barbearia = $this->barbearia();

        $this->validate([
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('barbearias', 'slug')->ignore($barbearia->id)],
        ]);
        $this->validate();

        $barbearia->update([
            'nome' => $this->nome,
            'slug' => $this->slug,
            'cuit' => $this->cuit ?: null,
            'endereco' => $this->endereco ?: null,
            'cidade' => $this->cidade ?: null,
            'provincia' => $this->provincia ?: null,
            'telefone' => $this->telefone ?: null,
            'email' => $this->email ?: null,
            'descricao' => $this->descricao ?: null,
            'instagram' => $this->instagram ?: null,
            'timezone' => $this->timezone,
            'moeda' => $this->moeda,
            'idioma_padrao' => $this->idiomaPadrao,
        ]);

        if ($this->logo) {
            $caminho = $this->logo->store('logos', 'public');

            if ($barbearia->logo_path) {
                Storage::disk('public')->delete($barbearia->logo_path);
            }

            $barbearia->update(['logo_path' => $caminho]);
            $this->logo = null;
        }

        if ($this->capa) {
            $caminho = $this->capa->store('capas', 'public');

            if ($barbearia->capa_path) {
                Storage::disk('public')->delete($barbearia->capa_path);
            }

            $barbearia->update(['capa_path' => $caminho]);
            $this->capa = null;
        }

        session()->flash('status', __('painel.configuracoes_salvas'));
    }

    public function adicionarFotos(): void
    {
        $this->validate([
            'novasFotos' => 'array|max:10',
            'novasFotos.*' => 'image|max:4096',
        ]);

        $proximaOrdem = (int) $this->barbearia()->fotos()->max('ordem') + 1;

        foreach ($this->novasFotos as $foto) {
            BarbeariaFoto::create([
                'foto_path' => $foto->store('barbearia-fotos', 'public'),
                'ordem' => $proximaOrdem++,
            ]);
        }

        $this->novasFotos = [];
    }

    public function removerFoto(int $fotoId): void
    {
        $foto = $this->barbearia()->fotos()->findOrFail($fotoId);

        Storage::disk('public')->delete($foto->foto_path);
        $foto->delete();
    }

    public function render()
    {
        return view('livewire.admin.configuracoes.configuracoes-barbearia', [
            'barbearia' => $this->barbearia(),
            'fotos' => $this->barbearia()->fotos,
        ]);
    }
}
