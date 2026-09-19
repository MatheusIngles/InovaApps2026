<?php

return [
    /*
    | Provedor padrão: modelos locais via Ollama. O provedor "api" (externo, formato OpenAI
    | /chat/completions) só é usado quando a pergunta/contexto exige um modelo mais robusto
    | ou quando o Ollama está indisponível. Sem nenhum dos dois, o chat cai nas respostas por regras.
    */
    'habilitado' => env('LLM_ENABLED', true),
    'timeout' => (int) env('LLM_TIMEOUT', 60),

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        'model' => env('OLLAMA_MODEL', 'llama3.1'),
    ],

    'api' => [
        'url' => env('LLM_API_URL', 'https://api.openai.com/v1'),
        'key' => env('LLM_API_KEY'),
        'model' => env('LLM_API_MODEL', 'gpt-4o'),
    ],

    /*
    | Quando escalar para o modelo maior (API externa):
    |  - prompt estimado acima de `limite_tokens_local` (contexto grande demais para o modelo local);
    |  - a pergunta contém alguma das `palavras_complexas` (análise/estratégia/comparação).
    */
    'limite_tokens_local' => (int) env('LLM_LOCAL_MAX_TOKENS', 3000),
    'palavras_complexas' => ['estrateg', 'compar', 'plano de acao', 'plano de retencao', 'priorize', 'analise completa', 'toda a carteira', 'projec', 'negoci'],

    /*
    | Prompt de sistema base. {{contexto}} recebe, dinamicamente, os dados da empresa acessada
    | (ou o resumo da carteira quando não há empresa em foco).
    */
    'prompt_base' => <<<'TXT'
Você é um especialista em relacionamento e retenção de clientes de uma empresa de serviços com contratos recorrentes.
Responda sempre em português do Brasil, de forma objetiva e acionável. Baseie-se APENAS nos dados abaixo;
se algo não estiver nos dados, diga que não sabe em vez de inventar. Cite números e evidências quando explicar o risco.

{{contexto}}
TXT,
];
