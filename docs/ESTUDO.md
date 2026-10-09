# Guia de estudo — DocsIA

Objetivo: você conseguir **explicar e modificar** cada parte deste projeto numa entrevista. Em uma entrevista, diga a verdade: *"Construí com apoio do Claude Code, e estudei e consigo explicar cada parte"*. Isso é aceito hoje; o que derruba é não saber explicar o que está no seu repositório.

## 1. Roteiro de leitura (nesta ordem, ~1h cada bloco)
1. `routes/web.php`, `routes/api.php` → qual URL chama qual controller.
2. `DocumentoController` + `StoreDocumentoRequest` → **validação** (Form Request, regra `after`).
3. `database/migrations/…documentos` + `Models/Documento`, `Trecho` → **Eloquent**, relações `hasMany/belongsTo`, `casts` (json → array), `cascadeOnDelete`.
4. `Jobs/ProcessarDocumento` → **filas**: `ShouldQueue`, `tries`, `failed()`; por que processar em segundo plano.
5. `Services/DocumentProcessor` → **transação** (`DB::transaction`), pipeline de indexação.
6. `Ai/Contracts/*` + `AppServiceProvider` → **container de serviços**, injeção de dependência, **interface + bind** (trocar driver só mudando configuração).
7. `Ai/Drivers/AnthropicClient` e `FakeClient` → HTTP client do Laravel (`Http::withHeaders()->post()`), tratamento de erro.
8. `Services/Chunker`, `Ai/HashingEmbedder`, `Services/VectorSearch` → **RAG**: por que dividir em trechos, o que é embedding, similaridade de cosseno.
9. `Services/DocumentAgent`, `AgentTools` → **tool calling**: o laço, por que existe `agent_max_steps`.
10. `Services/AnswerEvaluator` → avaliação de qualidade (*groundedness*).
11. `resources/js/components/Chat.vue` → Vue 3 `<script setup>`, `ref`, `fetch`, renderização condicional.
12. `tests/` → o que cada teste prova (leia antes de rodar).

## 2. Conceitos que a vaga pede e onde estão
| Conceito | Onde ver | Explique em uma frase |
|---|---|---|
| Integração com LLM via API | `AnthropicClient` | Envio mensagens HTTP com chave e modelo e leio os blocos de resposta |
| Tool / function calling | `DocumentAgent`, `AgentTools` | O modelo pede uma ferramenta, meu código executa e devolve o resultado, até ele responder |
| Structured outputs | `DocumentProcessor::SCHEMA` | Forço a saída a seguir um JSON Schema (aqui, via ferramenta obrigatória) |
| RAG | `Chunker`, `VectorSearch`, `DocumentAgent` | Busco trechos relevantes e uso só eles para responder, citando a fonte |
| Embeddings / busca semântica | `HashingEmbedder`, `VectorSearch` | Texto vira vetor; trechos parecidos ficam com cosseno maior |
| Gerenciamento de contexto | `historico` no `ChatController`, `chunk_size` | Limito o histórico e o tamanho dos trechos para caber no contexto |
| Avaliação de respostas | `AnswerEvaluator` + testes | Checo se há citações e se elas vêm do que foi recuperado |
| Arquitetura/confiabilidade | `Job` com `failed()`, `agent_max_steps`, `throttle`, driver `fake` | Tolero falha, limito laços e testo sem depender da API |

## 3. Perguntas prováveis (treine as respostas em voz alta)
1. **Por que `AiClient` é uma interface?** Para trocar provedor sem mexer na regra de negócio e para testar com um fake.
2. **O que acontece se a API da IA cair durante o processamento?** O job tenta de novo (`tries`); se falhar, `failed()` marca o documento como `erro` com a mensagem.
3. **Como você evita que a IA invente informação?** Prompt restritivo, resposta só com trechos recuperados, citação `[T<id>]` e `AnswerEvaluator`.
4. **Por que dividir o documento em trechos?** Limite de contexto, custo e precisão: recupero só o que importa.
5. **Como funciona a busca semântica daqui?** Embedding por hashing + cosseno em PHP. Em escala: pgvector/Elasticsearch, mesma interface.
6. **O embedder local é "semântico" de verdade?** É lexical (palavras em comum). Honestamente, serve de base testável; em produção usaria embeddings de provedor.
7. **Como testar código que chama LLM?** Driver `fake` e `Http::fake()`; os testes não usam rede.
8. **O que é tool calling e como você limitou o laço?** Explicado acima; `ia.agent_max_steps` evita laço infinito e custo.
9. **Por que `DB::transaction` ao reindexar?** Ou apaga e recria os trechos inteiros, ou nada muda.
10. **Como o container resolve `AiClient`?** `AppServiceProvider::register()` faz `singleton` conforme `config('ia.driver')`.
11. **Fila vs. execução síncrona?** IA é lenta; fila não trava a requisição. Nos testes uso `sync`.
12. **Que melhorias você faria?** Autenticação, PDFs/OCR, banco vetorial, reranking, avaliação com conjunto de perguntas, cache de embeddings.
13. **Onde estão os limites de segurança?** Validação de upload (`mimes`, `max`), `throttle` nas rotas de IA, sem execução de código vindo do modelo.
14. **Como você garantiria a qualidade das respostas em produção?** Conjunto de perguntas com respostas esperadas, métricas de citação válida, revisão amostral.

## 4. Exercícios práticos (faça, não só leia)
1. Adicione o campo `categoria` ao documento (migration, model, formulário, teste).
2. Crie uma ferramenta nova, `list_valores`, em `AgentTools` (definição + execução + teste).
3. Quebre de propósito: coloque `agent_max_steps = 1` e veja o teste falhar; explique por quê.
4. Troque `chunk_size` para 100 e observe a mudança na busca.
5. Escreva um driver `OpenAiClient` implementando `AiClient` e registre no `AppServiceProvider`.
6. Faça a listagem de documentos filtrar por `status` (query string + teste).
7. Rode `php artisan tinker` e consulte `Documento::with('trechos')->first()`.

## 5. O que dizer sobre a sua experiência (sem exagerar)
- Seu histórico profissional **não** é de Laravel; este é um projeto de portfólio feito com apoio de IA.
- Fale do que você **domina de fato** depois de estudar: a arquitetura, o laço do agente, o RAG, os testes.
- Em dúvida numa pergunta, diga como investigaria (docs, `tinker`, testes). Isso é o que a vaga valoriza num júnior.
