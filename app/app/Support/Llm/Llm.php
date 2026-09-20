<?php

namespace App\Support\Llm;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Cliente de LLM em cascata: 1) API externa da NVIDIA (formato OpenAI); 2) Ollama local, se a API não estiver
 * configurada, falhar ou devolver uma resposta fraca; 3) o chamador cai nas respostas por regras.
 */
class Llm
{
    /** Trechos de respostas evasivas típicas de modelo, que não ajudam quem perguntou. */
    private const EVASIVAS = ['como um modelo de linguagem', 'como uma ia', 'as an ai', 'i cannot', "i can't", 'não consigo ajudar', 'nao consigo ajudar'];

    /**
     * @param  array<int, array{role: string, content: string}>  $mensagens  histórico + pergunta atual (sem o prompt de sistema)
     * @return array{texto: string, provedor: string}
     *
     * @throws RuntimeException quando nenhum provedor responde bem (o chamador cai para as regras locais)
     */
    public static function responder(string $sistema, array $mensagens, ?string $modeloLocal = null): array
    {
        if (! config('llm.habilitado')) {
            throw new RuntimeException('LLM desabilitado.');
        }

        $todas = [['role' => 'system', 'content' => $sistema], ...$mensagens];
        $erro = null;

        foreach (['api', 'ollama'] as $provedor) {
            if ($provedor === 'api' && ! config('llm.api.key')) {
                continue; // sem chave, a API externa não está disponível
            }
            try {
                $texto = $provedor === 'api' ? self::api($todas) : self::ollama($todas, $modeloLocal);
                if (! self::fraca($texto)) {
                    return ['texto' => $texto, 'provedor' => $provedor];
                }
                $erro = new RuntimeException("Resposta fraca de $provedor.");
            } catch (\Throwable $e) {
                $erro = $e;
            }
        }

        throw new RuntimeException('Nenhum provedor de LLM respondeu bem.', 0, $erro);
    }

    /** Resposta fraca: curta demais ou evasiva. A recusa de escopo é curta de propósito e vale como resposta boa. */
    public static function fraca(string $texto): bool
    {
        if (Escopo::foraDoAssunto($texto)) {
            return false;
        }

        return mb_strlen($texto) < 20 || Str::contains(Str::lower($texto), self::EVASIVAS);
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
        $r = Http::timeout(config('llm.timeout'))->withOptions(array_filter(['verify' => config('llm.api.ca_bundle')]))->withToken(config('llm.api.key'))
            ->post(rtrim(config('llm.api.url'), '/').'/chat/completions', ['model' => config('llm.api.model'), 'messages' => $mensagens])
            ->throw();

        return trim($r->json('choices.0.message.content') ?? throw new RuntimeException('Resposta vazia da API.'));
    }
}
