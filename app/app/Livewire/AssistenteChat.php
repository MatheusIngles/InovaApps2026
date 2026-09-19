<?php

namespace App\Livewire;

use App\Models\ChatMessage;
use App\Models\Customer;
use App\Support\Assistente;
use App\Support\Llm\Contexto;
use App\Support\Llm\Llm;
use App\Support\Tenancy\CompanyContext;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Chat em tela cheia. Com uma empresa em foco a IA recebe o contexto dela; sem foco, o resumo da carteira.
 * O histórico é persistido por (empresa/tenant, usuário, foco) e o escopo do tenant impede qualquer vazamento entre empresas.
 */
class AssistenteChat extends Component
{
    #[Url(as: 'empresa')]
    public ?string $codigo = null;

    public string $pergunta = '';

    /** @var array<int, array{eu: bool, texto: string, fonte?: string}> */
    public array $mensagens = [];

    public function mount(): void
    {
        $this->carregar();
    }

    public function updatedCodigo(): void
    {
        $this->carregar();
    }

    public function enviar(?string $texto = null): void
    {
        $texto = mb_substr(trim($texto ?? $this->pergunta), 0, 500);
        if ($texto === '') {
            return;
        }
        $this->pergunta = '';

        $empresa = $this->empresa($texto);
        $config = app(CompanyContext::class)->current()->chat();
        $this->guardar('user', $texto);

        try {
            if (! $config['enabled']) {
                throw new \RuntimeException('IA desativada para esta empresa.');
            }
            // Sem histórico: cada pergunta é respondida só com a carteira/empresa atual, sempre atualizada.
            $r = Llm::responder(Contexto::sistema($empresa), [['role' => 'user', 'content' => $texto]], $config['ollama_model']);
            $this->guardar('assistant', $r['texto'], $r['provedor'] === 'api' ? 'Modelo avançado (API)' : 'Modelo local (Ollama)');
        } catch (\Throwable) {
            $this->guardar('assistant', Assistente::responder($texto, $empresa?->codigo), 'Respostas por regras (IA indisponível)');
        }

        $this->carregar();
    }

    public function limpar(): void
    {
        $this->consulta()->delete();
        $this->mensagens = [];
    }

    private function escopo(): ?string
    {
        return $this->codigo ? strtoupper($this->codigo) : null;
    }

    private function consulta()
    {
        return ChatMessage::where('user_id', auth()->id())->where('customer_code', $this->escopo());
    }

    private function guardar(string $papel, string $texto, ?string $fonte = null): void
    {
        ChatMessage::create(['user_id' => auth()->id(), 'customer_code' => $this->escopo(), 'role' => $papel, 'content' => $texto, 'provider' => $fonte]);
    }

    private function carregar(): void
    {
        $this->mensagens = $this->consulta()->orderByDesc('id')->limit(30)->get()->reverse()
            ->map(fn (ChatMessage $m) => ['eu' => $m->role === 'user', 'texto' => $m->content] + ($m->provider ? ['fonte' => $m->provider] : []))->values()->all();
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
