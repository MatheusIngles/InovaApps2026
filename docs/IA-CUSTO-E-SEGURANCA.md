# IA no Seer: consumo, segurança e como desligar

Perguntas que costumam aparecer sobre o uso de inteligência artificial no projeto, com as respostas e as justificativas.

## 1. Onde a IA é usada (e onde não é)

| Usa IA | Não usa IA |
|---|---|
| Chat do assistente (explicar, resumir e sugerir ações) | **Cálculo da atenção** (regras e pesos fixos) |
| Texto do relatório de evidências em PDF | Fila de atendimento e prazos |
| Explicação da configuração recomendada | Níveis, exposição mensal e notificações |
| | Validação com cancelados (backtest) |

A IA **não decide nada**: não altera dados, não muda pesos e não roda ações. Ela lê um resumo dos dados da empresa e escreve um texto. Por isso, o núcleo do produto continua igual, com ou sem IA.

## 2. Consumo e custo

> **Ressalva sobre a API da NVIDIA.** O Seer usa a API do [build.nvidia.com](https://build.nvidia.com), que é **gratuita para desenvolvimento, testes e prototipagem**, com limite de requisições e sem garantia de disponibilidade. Por isso ela é a primeira opção, mas nunca a única: se a chave não existir, o limite estourar ou a resposta vier fraca, o sistema usa o Ollama e, por último, respostas por regras. Para uso comercial em produção, confirme os termos e os planos da NVIDIA antes de depender dela, ou use só o Ollama local (sem enviar dados para fora).

**O consumo é baixo por desenho.** A ideia é um usuário compartilhado por empresa, que acompanha os problemas da própria carteira; assim, poucas pessoas usam o assistente ao mesmo tempo, e não milhares de acessos simultâneos. Conforme as empresas crescerem, as chamadas ao Ollama local podem passar por uma fila de jobs (o projeto já usa filas), para atender um pedido de cada vez sem travar o servidor. Cada pergunta leva só um resumo (o top 10 da fila ou um cliente), e não a base inteira, então o volume de texto processado é pequeno e não cresce com o tamanho da carteira.

**Uma empresa não precisa pagar por IA em nuvem.** Com um servidor comum, sem nada de sofisticado, já dá para rodar o modelo local (Ollama) e atender esse uso. O servidor em que o Seer roda hoje (Intel Core i5-9400F, 8 GB de RAM, sem placa de vídeo) roda o assistente local: as respostas são mais lentas do que na API, mas funcionam. Hardware um pouco melhor deixa o assistente mais rápido.

**O que segura o consumo (já implementado)**
- **Cascata:** primeiro a API externa (NVIDIA), depois o **Ollama local**, que roda no servidor da própria empresa. Se a API falhar ou não estiver configurada, o sistema segue sem depender de nenhum serviço externo.
- **Limites:** pergunta de até 500 caracteres, histórico limitado às últimas 20 mensagens, resposta com tempo máximo de 60 s.
- **Contexto enxuto:** só o resumo da empresa vai ao modelo.
- **Voz:** o Edge TTS é gratuito. É um serviço não oficial, então pode mudar ou parar; nesse caso o navegador lê com a voz local.
- **A empresa pode desligar a IA** e o uso cai a zero (item 4).
- **Restrição de escopo:** tentativas de manipular o assistente (ex.: "ignore as regras") são barradas no nosso servidor, sem chamar o modelo. Perguntas de outro assunto são recusadas pelo próprio prompt, com uma resposta curta.

**O que acompanhar:** se o uso crescer muito acima do previsto, dá para limitar o número de perguntas por empresa; a API gratuita também pode impor limites e mudar de condições.

## 3. Segurança relacionada à IA

| Risco | O que fazemos |
|---|---|
| **Dados saindo do servidor** | Só um resumo vai à API externa: nome e código de clientes, valor mensal, atenção e motivos. Não vão senhas, e-mails de usuários nem a base bruta. Com a API desligada e só o Ollama, **nada sai do servidor**. Com a IA desligada, nada é enviado. |
| **Vazamento entre empresas** | O contexto é montado só com os dados da empresa logada (escopo global por `company_id`), e o prompt diz que a IA atende exclusivamente aquela empresa. Há testes de isolamento. |
| **Prompt injection** ("ignore as regras...") | `Escopo::tentaBurlar` recusa padrões de manipulação antes de chamar o modelo; o prompt tem uma regra de escopo que o usuário não altera e um marcador `[FORA_DO_ESCOPO]` que o sistema respeita na resposta. |
| **Resposta com HTML/JS malicioso (XSS)** | A resposta da IA é convertida de Markdown com `html_input => escape` e links inseguros bloqueados. |
| **Alucinação** | O prompt manda usar só os dados e dizer "não sei"; destaques numéricos (maior contrato, maior atenção) já vão calculados; a atenção e os motivos exibidos na tela vêm das regras, não da IA. |
| **Chave da API exposta** | Fica só no `.env` do servidor, nunca vai ao navegador nem ao Git. Se vazar, gere outra no painel do provedor. |
| **Abuso do endpoint de voz** | Exige login e tem limite de 20 requisições por minuto. |
| **Histórico da conversa** | Não é gravado no banco: recomeça ao sair da tela. Ficam só as últimas 20 mensagens da sessão aberta, mandadas ao modelo como contexto. |
| **Ação indevida da IA** | Nenhuma: a IA não tem ferramentas nem acesso de escrita. Só devolve texto. |

**Pontos que uma empresa cliente deve saber:** ao usar a API da NVIDIA, os dados do resumo trafegam para esse provedor, sujeitos às políticas dele. Empresas com exigência de LGPD ou sigilo contratual devem usar somente o Ollama local ou desligar a IA. Os dados dos clientes finais são de empresas (nome e código), não dados pessoais de indivíduos, mas o nome de um cliente pode identificar uma pessoa em empresas individuais; por isso a opção de desligar existe.

## 4. Como desligar a IA

Cada empresa decide sozinha, sem depender do time técnico:

1. Abra **Configurações › Assistente de IA**.
2. Desligue **Usar o assistente de IA**. Vale na hora.

**O que acontece quando está desligada**
- O chat continua funcionando com **perguntas prontas** e respostas por regras (`Assistente::responder`), sem nenhuma chamada externa. As respostas usam os **dados reais da carteira da empresa**, calculados localmente, e não dados de exemplo. A mensagem aparece marcada como "Respostas por regras (IA desligada)".
- O relatório em PDF e a explicação de configuração usam textos por regras, com os mesmos números.
- A atenção, a fila, o painel e as notificações não mudam.

Há também uma chave global para o operador do sistema: `LLM_ENABLED=false` no `.env` desliga a IA para todas as empresas.

## 5. Resumo para uma banca

- O produto **não depende** de IA para o que importa (atenção e fila são regras explicáveis).
- A IA é um **recurso opcional** de conversa e texto, com **três camadas** (API, local, regras).
- O consumo é **baixo por desenho** (poucos usuários simultâneos, contexto pequeno) e um servidor comum já sustenta a IA local.
- Os dados enviados são **mínimos**, isolados por empresa, e podem **não sair do servidor**.
