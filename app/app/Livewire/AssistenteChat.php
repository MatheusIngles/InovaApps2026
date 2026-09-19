<?php

namespace App\Livewire;

use App\Models\Empresa;
use App\Support\Assistente;
use Livewire\Component;

/** Chatbot local. Com $codigo fixo fala de uma empresa; com $seletor deixa escolher o foco. */
class AssistenteChat extends Component
{
    public ?string $codigo = null;

    public bool $seletor = false;

    public string $pergunta = '';

    /** @var array<int, array{eu: bool, texto: string}> */
    public array $mensagens = [];

    public function mount(): void
    {
        $this->mensagens[] = ['eu' => false, 'texto' => $this->codigo || $this->seletor
            ? 'Pergunte por que uma empresa está em risco, o que fazer, cancelados parecidos ou NPS.'
            : 'Olá! Pergunte sobre a carteira ou cite o código de uma empresa (ex.: C012).'];
    }

    public function enviar(?string $texto = null): void
    {
        $texto = trim($texto ?? $this->pergunta);
        if ($texto === '') {
            return;
        }
        $this->mensagens[] = ['eu' => true, 'texto' => $texto];
        $this->mensagens[] = ['eu' => false, 'texto' => Assistente::responder(mb_substr($texto, 0, 300), $this->codigo ?: null)];
        $this->pergunta = '';
    }

    public function render()
    {
        return view('livewire.assistente-chat', [
            'sugestoes' => $this->codigo || $this->seletor
                ? ['Por que está em risco?', 'O que fazer?', 'Cancelados parecidos', 'NPS']
                : ['Quem devo ligar primeiro?', 'Resumo da carteira', 'Receita em risco', 'Risco por segmento', 'Quem já cancelou?'],
            'empresas' => $this->seletor ? Empresa::ordenar(Empresa::query())->get(['codigo', 'nome', 'status']) : [],
        ]);
    }
}
