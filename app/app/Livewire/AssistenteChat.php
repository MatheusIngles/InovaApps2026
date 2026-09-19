<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Support\Assistente;
use App\Support\Llm\Contexto;
use App\Support\Llm\Llm;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Chat em tela cheia. Com uma empresa em foco a IA recebe o contexto dela; sem foco, o resumo da carteira. */
class AssistenteChat extends Component
{
    #[Url(as: 'empresa')]
    public ?string $codigo = null;

    public string $pergunta = '';

    /** @var array<int, array{eu: bool, texto: string, fonte?: string}> */
    public array $mensagens = [];

    public function enviar(?string $texto = null): void
    {
        $texto = mb_substr(trim($texto ?? $this->pergunta), 0, 500);
        if ($texto === '') {
            return;
        }
        $this->pergunta = '';

        $empresa = $this->empresa($texto);
        $historico = array_map(fn ($m) => ['role' => $m['eu'] ? 'user' : 'assistant', 'content' => $m['texto']], array_slice($this->mensagens, -8));
        $this->mensagens[] = ['eu' => true, 'texto' => $texto];

        try {
            $r = Llm::responder(Contexto::sistema($empresa), [...$historico, ['role' => 'user', 'content' => $texto]]);
            $this->mensagens[] = ['eu' => false, 'texto' => $r['texto'], 'fonte' => $r['provedor'] === 'api' ? 'Modelo avançado (API)' : 'Modelo local (Ollama)'];
        } catch (\Throwable) {
            $this->mensagens[] = ['eu' => false, 'texto' => Assistente::responder($texto, $empresa?->codigo), 'fonte' => 'Respostas por regras (IA indisponível)'];
        }
    }

    public function limpar(): void
    {
        $this->mensagens = [];
    }

    /** Empresa selecionada ou citada na pergunta (ex.: C012). */
    private function empresa(string $texto): ?Customer
    {
        $codigo = $this->codigo ?: (preg_match('/\bc\d{3}\b/i', $texto, $m) ? $m[0] : null);

        return $codigo ? Customer::dashboard()->where('customers.external_code', strtoupper($codigo))->first() : null;
    }

    public function render()
    {
        $foco = $this->codigo ? Customer::dashboard()->where('customers.external_code', strtoupper($this->codigo))->first() : null;

        return view('livewire.assistente-chat', [
            'foco' => $foco,
            'sugestoes' => $foco
                ? ['Por que está em risco?', 'O que devo fazer primeiro?', 'Compare com os cancelados parecidos', 'Resuma o histórico de NPS']
                : ['Quem devo ligar primeiro?', 'Resumo da carteira', 'Qual a receita em risco?', 'Risco por segmento'],
            'empresas' => Customer::ordenar(Customer::dashboard())->get(),
        ]);
    }
}
