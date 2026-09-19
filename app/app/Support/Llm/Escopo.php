<?php

namespace App\Support\Llm;

use Illuminate\Support\Str;

/** Barreira de escopo do chat: só se fala da carteira; pedidos para ignorar as regras ou sair do contexto são recusados. */
class Escopo
{
    /** Palavra combinada com a IA (ver `prompt_base`) para marcar uma pergunta fora do assunto. */
    public const MARCADOR = '[FORA_DO_ESCOPO]';

    public const RECUSA = 'Só posso ajudar com a carteira de clientes desta empresa: risco, sinais, prioridades, cancelamentos e o que fazer a respeito. Pergunte algo nessa linha, por exemplo "Quem devo ligar primeiro?".';

    /** Tentativas de mudar o papel da IA, ignorar instruções ou sair do contexto. */
    private const PADROES = [
        '/\b(ignor\w*|esquec\w*|desconsider\w*|abandon\w*|descart\w*)\b.{0,40}\b(instruc\w*|regra\w*|contexto|prompt|restric\w*|anterior\w*|acima)\b/',
        '/\bsa[ia]\w*\s+d[oa]\s+(contexto|papel|escopo|assunto)\b/',
        '/\bfora\s+d[oa]\s+(contexto|escopo)\b/',
        '/\b(finja|faca de conta|aja como|atue como|responda como se|voce agora e|a partir de agora voce)\b/',
        '/\b(prompt|instrucoes?)\s+(do\s+)?(sistema|inicial|oculto|interno)\b/',
        '/\b(ignore|disregard|forget)\b.{0,30}\b(instructions?|rules?|context|prompt)\b/',
        '/\b(jailbreak|modo desenvolvedor|developer mode|dan mode)\b/',
    ];

    public static function tentaBurlar(string $texto): bool
    {
        $t = Str::lower(Str::ascii($texto));

        foreach (self::PADROES as $padrao) {
            if (preg_match($padrao, $t)) {
                return true;
            }
        }

        return false;
    }

    public static function foraDoAssunto(string $resposta): bool
    {
        return stripos($resposta, 'FORA_DO_ESCOPO') !== false; // o modelo às vezes omite os colchetes
    }
}
