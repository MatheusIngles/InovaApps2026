<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Support\Assistente;
use App\Support\Llm\Contexto;
use App\Support\Llm\Escopo;
use App\Support\Llm\Llm;
use App\Support\Tenancy\CompanyContext;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Chat em tela cheia. Com uma empresa em foco a IA recebe o contexto dela; sem foco, o resumo da carteira.
 * A conversa vive apenas no estado do componente. Sair ou recarregar a tela apaga o histórico;
 * enquanto ela está aberta, o modelo recebe as mensagens recentes junto com dados atuais da carteira.
 */
class AssistenteChat extends Component
{
    #[Url(as: 'empresa')]
    public ?string $codigo = null;

    public string $pergunta = '';

    /** @var array<int, array{eu: bool, texto: string, fonte?: string}> */
    public array $mensagens = [];

    public function updatedCodigo(): void
    {
        $this->mensagens = [];
    }

    public function enviar(?string $texto = null): void
    {
        $texto = mb_substr(trim($texto ?? $this->pergunta), 0, 500);
        if ($texto === '') {
            return;
        }
        $this->pergunta = '';

        $historico = collect($this->mensagens)->take(-20)
            ->map(fn (array $mensagem): array => ['role' => $mensagem['eu'] ? 'user' : 'assistant', 'content' => $mensagem['texto']])->all();
        $empresa = $this->empresa($texto);
        $config = app(CompanyContext::class)->current()->chat();
        $this->mensagens[] = ['eu' => true, 'texto' => $texto];

        if (Escopo::tentaBurlar($texto)) {
            $this->mensagens[] = ['eu' => false, 'texto' => Escopo::RECUSA, 'fonte' => 'Fora do escopo do assistente'];

            return;
        }

        try {
            if (! $config['enabled']) {
                throw new \RuntimeException('IA desativada para esta empresa.');
            }
            $r = Llm::responder(Contexto::sistema($empresa), [...$historico, ['role' => 'user', 'content' => $texto]], $config['ollama_model']);
            if (Escopo::foraDoAssunto($r['texto'])) {
                $this->mensagens[] = ['eu' => false, 'texto' => Escopo::RECUSA, 'fonte' => 'Fora do escopo do assistente'];

                return;
            }
            $this->mensagens[] = ['eu' => false, 'texto' => $r['texto'], 'fonte' => $r['provedor'] === 'api' ? 'Modelo avançado (API)' : 'Modelo local (Ollama)'];
        } catch (\Throwable) {
            $this->mensagens[] = ['eu' => false, 'texto' => Assistente::responder($texto, $empresa?->codigo), 'fonte' => 'Respostas por regras (IA indisponível)'];
        }
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
                ? ['Por que está em risco?', 'O que devo fazer primeiro?', 'Quais métricas priorizei?', 'Compare com os cancelados parecidos', 'Resuma o histórico de NPS']
                : ['Quem devo ligar primeiro?', 'Quais são minhas prioridades métricas?', 'Resumo da carteira', 'Qual a receita em risco?', 'Risco por segmento'],
            'empresas' => Customer::ordenar(Customer::dashboard())->get(),
        ]);
    }
}
