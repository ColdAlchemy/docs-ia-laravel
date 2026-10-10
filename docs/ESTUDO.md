# Guia de estudo — DocsIA

Objetivo: você conseguir **explicar e modificar** cada parte deste projeto numa entrevista. Em uma entrevista, diga a verdade: *"Desenvolvi com o auxílio do Claude Code, e estudei e consigo explicar cada parte"*. Isso é aceito hoje; o que derruba é não saber explicar o que está no seu repositório.

## 1. Roteiro de leitura (nesta ordem, ~1h cada bloco)
1. `routes/web.php` → qual URL chama qual controller; o que fica atrás de `auth` + `espaco`. (`routes/api.php` está vazio de propósito: os endpoints JSON usam sessão e CSRF.)
2. `AuthController` + `bootstrap/app.php` → **autenticação por sessão** (`Auth::attempt`, `session()->regenerate()`, `throttle`), redirecionamento de visitantes.
3. `Middleware/EspacoAtual` + `Models/Espaco` + migration dos espaços → **multi-tenant simples**: todo dado tem `espaco_id`; o middleware só aceita espaços de que o usuário participa (relação `belongsToMany`).
4. `Controller::doDocumentoDoEspaco`, `DocumentoController` + `StoreDocumentoRequest` → **autorização por escopo** (404 para dado de outro espaço, sem revelar que existe) e **validação** (Form Request, regra `after`).
5. `Services/PdfTexto` → extração de texto de PDF (`smalot/pdfparser`), erro claro para PDF sem texto (sem OCR).
6. `Models/Documento`, `Trecho`, `Prazo`, `Conversa`, `Mensagem` → **Eloquent**: `hasMany/belongsTo`, `casts` (json → array, date), `cascadeOnDelete`.
7. `Jobs/ProcessarDocumento` → **filas**: `ShouldQueue`, `tries`, `failed()`; por que processar em segundo plano.
8. `Services/DocumentProcessor` → **transação** (`DB::transaction`), pipeline de indexação, prazos viram linhas com data (`registrarPrazos`, `checkdate`).
9. `Ai/Contracts/*` + `AppServiceProvider` → **container de serviços**, injeção de dependência, **interface + bind** (trocar driver só mudando configuração).
10. `Ai/Drivers/AnthropicClient` e `FakeClient` → HTTP client do Laravel, tratamento de erro; o `FakeClient` é a base de demonstração e testes.
11. `Services/Chunker` (com posições), `Ai/HashingEmbedder`, `Services/VectorSearch` → **RAG**: dividir em trechos, embedding, cosseno. O `Chunker` guarda `inicio/fim` no texto original para o leitor destacar o trecho citado.
12. `Services/Escopo`, `DocumentAgent`, `AgentTools` → **tool calling** com escopo: o agente só enxerga o espaço (e, no leitor, um só documento). É a barreira contra vazamento entre espaços.
13. `Services/AnswerEvaluator`, `Qualidade` → avaliação de *groundedness*; indicadores, série por semana e **conjunto de verificação** executado em lote.
14. `Services/Consultas` + `Api/ChatController` → conversas persistidas; o **histórico vem do banco**, não do navegador.
15. `Services/Leitura` + `resources/views/documentos/show.blade.php` → como o texto original vira blocos com anotações de margem.
16. `PrazoController` → calendário montado no servidor (grade domingo–sábado) e exportação `.ics` (RFC 5545).
17. `resources/js/components/Perguntas.vue` → Vue 3 `<script setup>`: `ref`, `computed`, `fetch` com CSRF, notas de rodapé numeradas a partir de `[T<id>]`, `aria-live`.
18. `resources/css/app.css` → tokens de cor/tipografia, `prefers-reduced-motion`, `:focus-visible`, layout responsivo, CSS de impressão.
19. `tests/` → o que cada teste prova (leia antes de rodar). Os de `EspacosTest` são os mais importantes de explicar.

## 2. Conceitos que a vaga pede e onde estão
| Conceito | Onde ver | Explique em uma frase |
|---|---|---|
| Integração com LLM via API | `AnthropicClient` | Envio mensagens HTTP com chave e modelo e leio os blocos de resposta |
| Tool / function calling | `DocumentAgent`, `AgentTools` | O modelo pede uma ferramenta, meu código executa (dentro do escopo do espaço) e devolve o resultado, até ele responder |
| Structured outputs | `DocumentProcessor::SCHEMA` | Forço a saída a seguir um JSON Schema (aqui, via ferramenta obrigatória) |
| RAG | `Chunker`, `VectorSearch`, `DocumentAgent` | Busco trechos relevantes e uso só eles para responder, citando a fonte |
| Embeddings / busca semântica | `HashingEmbedder`, `VectorSearch` | Texto vira vetor; trechos parecidos ficam com cosseno maior |
| Gerenciamento de contexto | `Consultas::MAX_HISTORICO`, `chunk_size` | Limito o histórico e o tamanho dos trechos para caber no contexto |
| Avaliação de respostas | `AnswerEvaluator`, `Qualidade`, perguntas de verificação | Checo citações, acompanho a taxa e reexecuto um conjunto fixo de perguntas |
| Isolamento de dados | `EspacoAtual`, `Escopo`, `EspacosTest` | Cada consulta e cada ferramenta recebe o espaço; testes provam que não há vazamento |
| Arquitetura/confiabilidade | `Job` com `failed()`, `agent_max_steps`, `throttle`, driver `fake` | Tolero falha, limito laços e testo sem depender da API |

## 3. Perguntas prováveis (treine as respostas em voz alta)
1. **Por que `AiClient` é uma interface?** Para trocar provedor sem mexer na regra de negócio e para testar com um fake.
2. **O que acontece se a API da IA cair durante o processamento?** O job tenta de novo (`tries`); se falhar, `failed()` marca o documento como `erro` com a mensagem, e a tela oferece "Reprocessar".
3. **Como você evita que a IA invente informação?** Prompt restritivo, resposta só com trechos recuperados, citação `[T<id>]` e `AnswerEvaluator`. E o painel de qualidade mostra quando isso falha.
4. **Por que dividir o documento em trechos?** Limite de contexto, custo e precisão: recupero só o que importa. E guardo a posição para mostrar o trecho no documento.
5. **Como funciona a busca semântica daqui?** Embedding por hashing + cosseno em PHP. Em escala: pgvector/Elasticsearch, mesma interface.
6. **O embedder local é "semântico" de verdade?** É lexical (palavras em comum). Honestamente, serve de base testável; em produção usaria embeddings de provedor.
7. **Como testar código que chama LLM?** Driver `fake` e `Http::fake()`; os testes não usam rede.
8. **O que é tool calling e como você limitou o laço?** `ia.agent_max_steps` evita laço infinito e custo.
9. **Como você garante que um espaço não vê dados de outro?** Toda consulta filtra por `espaco_id`; o middleware só aceita espaços do usuário; documento de outro espaço devolve 404; o agente recebe um `Escopo` e a busca vetorial filtra por ele. Há testes para listagem, busca, agente, prazos e rotas.
10. **Por que 404 e não 403 para dado de outro espaço?** Para não revelar que o registro existe.
11. **Por que o histórico da conversa vem do banco?** O navegador poderia mandar mensagens falsas (inclusive de "assistente"); o servidor monta o contexto sozinho.
12. **Por que `DB::transaction` ao reindexar?** Ou apaga e recria os trechos e prazos inteiros, ou nada muda.
13. **Como o container resolve `AiClient`?** `AppServiceProvider::register()` faz `singleton` conforme `config('ia.driver')`.
14. **Fila vs. execução síncrona?** IA é lenta; fila não trava a requisição. Nos testes e na demonstração local uso `sync`.
15. **O que o PDF não faz?** Só lê PDF com texto selecionável; não há OCR. PDF digitalizado dá um erro explicando isso.
16. **Que melhorias você faria?** OCR, banco vetorial, reranking, convites por e-mail, papéis (leitor/editor), cache de embeddings, PDF gerado no servidor.
17. **Onde estão os limites de segurança?** Validação de upload (`mimes`, `max`), `throttle` no login e nas rotas de IA, CSRF nos endpoints JSON, senhas com hash, sem execução de código vindo do modelo.
18. **Como você garantiria a qualidade das respostas em produção?** Conjunto de perguntas com respostas esperadas (a tela de Qualidade já executa um), métricas de citação válida, revisão amostral das reprovadas.

## 4. Exercícios práticos (faça, não só leia)
1. Adicione o campo `categoria` ao documento (migration, model, formulário, filtro na listagem, teste).
2. Crie uma ferramenta nova, `list_valores`, em `AgentTools` (definição + execução + teste).
3. Quebre de propósito: coloque `agent_max_steps = 1` e veja o teste falhar; explique por quê.
4. Troque `chunk_size` para 100 e observe, no leitor, como os blocos e as anotações mudam.
5. Escreva um driver `OpenAiClient` implementando `AiClient` e registre no `AppServiceProvider`.
6. Tente "vazar": comente o filtro de `espaco_id` em `VectorSearch` e veja qual teste de `EspacosTest` falha.
7. Adicione um papel "leitor" (só consulta) ao membro do espaço: migration na tabela `espaco_user`, checagem no controller, teste.
8. Rode `php artisan tinker` e consulte `Documento::with('trechos', 'prazos')->first()`.

## 5. O que dizer sobre a sua experiência (sem exagerar)
- Seu histórico profissional **não** é de Laravel; este é um projeto de portfólio desenvolvido por você com o auxílio do Claude Code.
- Fale do que você **domina de fato** depois de estudar: a arquitetura, o laço do agente, o RAG, o isolamento por espaço, os testes.
- O modo de demonstração usa um "LLM falso" determinístico: diga isso claramente. A chamada real à API está implementada e testada com HTTP simulado, mas não foi exercitada em produção.
- Em dúvida numa pergunta, diga como investigaria (docs, `tinker`, testes). Isso é o que a vaga valoriza num júnior.
