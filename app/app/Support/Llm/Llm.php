<?php

namespace App\Support\Llm;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Cliente de LLM: Ollama local por padrão, API externa (OpenAI-compatível) quando o
 * contexto exige um modelo maior ou o Ollama está fora do ar.
 */
class Llm
{
    /**
     * @param  array<int, array{role: string, content: string}>  $mensagens  histórico + pergunta atual (sem o prompt de sistema)
     * @return array{texto: string, provedor: string}
     *
     * @throws RuntimeException quando nenhum provedor responde (o chamador cai para as regras locais)
     */
    public static function responder(string $sistema, array $mensagens, ?string $modeloLocal = null): array
    {
        if (! config('llm.habilitado')) {
            throw new RuntimeException('LLM desabilitado.');
        }

        $todas = [['role' => 'system', 'content' => $sistema], ...$mensagens];
        $ordem = self::precisaModeloMaior($todas) ? ['api', 'ollama'] : ['ollama', 'api'];
        $erro = null;

        foreach ($ordem as $provedor) {
            if ($provedor === 'api' && ! config('llm.api.key')) {
                continue; // sem chave, a API externa não está disponível
            }
            try {
                return ['texto' => $provedor === 'api' ? self::api($todas) : self::ollama($todas, $modeloLocal), 'provedor' => $provedor];
            } catch (\Throwable $e) {
                $erro = $e;
            }
        }

        throw new RuntimeException('Nenhum provedor de LLM respondeu.', 0, $erro);
    }

    /** Contexto grande demais para o modelo local, ou pergunta que pede raciocínio mais robusto. */
    public static function precisaModeloMaior(array $mensagens): bool
    {
        $tokens = (int) ceil(array_sum(array_map(fn ($m) => mb_strlen($m['content']), $mensagens)) / 4);
        $pergunta = Str::lower(Str::ascii(collect($mensagens)->where('role', 'user')->last()['content'] ?? ''));

        return $tokens > config('llm.limite_tokens_local') || Str::contains($pergunta, config('llm.palavras_complexas'));
    }

    private static function ollama(array $mensagens, ?string $modelo = null): string
    {
        $r = Http::timeout(config('llm.timeout'))->post(rtrim(config('llm.ollama.url'), '/').'/api/chat', [
            'model' => $modelo ?: config('llm.ollama.model'), 'messages' => $mensagens, 'stream' => false,
        ])->throw();

        return trim($r->json('message.content') ?? throw new RuntimeException('Resposta vazia do Ollama.'));
    }

    private static function api(array $mensagens): string
    {
        $r = Http::timeout(config('llm.timeout'))->withToken(config('llm.api.key'))
            ->post(rtrim(config('llm.api.url'), '/').'/chat/completions', ['model' => config('llm.api.model'), 'messages' => $mensagens])
            ->throw();

        return trim($r->json('choices.0.message.content') ?? throw new RuntimeException('Resposta vazia da API.'));
    }
}
