# DocsIA — arquivo de cartório com IA

Aplicação Laravel para equipes que lidam com contratos, notificações e políticas. Você arquiva um documento (texto ou PDF), a IA **extrai partes, prazos e valores**, e você lê o documento com **anotações na margem** e faz perguntas que são respondidas com **notas de rodapé** apontando o trecho exato. Cada resposta recebe um carimbo de **fundamentada** ou **sem fundamento**.

> Desenvolvido por mim com o auxílio do Claude Code. Eu defini o escopo, revisei e estudei o código; o guia `docs/ESTUDO.md` documenta cada parte. Os documentos de exemplo são fictícios, e a aplicação **não dá aconselhamento jurídico**.

## O que ele faz
| Tela | O que tem |
|---|---|
| **Entrar / Criar conta** | Autenticação por sessão do Laravel, com limite de tentativas |
| **Arquivo** | Índice dos documentos com nº de registro, tipo, próximo prazo e estado; busca e filtros na URL |
| **Arquivar** | Texto colado, `.txt`, `.md` ou **PDF com texto** (sem OCR); processamento em fila |
| **Leitor** | O documento em "folha", com **anotações de margem** (prazos, valores, partes) ao lado do trecho onde aparecem; perguntas ao documento com **notas de rodapé** que destacam o trecho citado |
| **Consultas** | Perguntas sobre o espaço todo, com histórico de conversas; cada nota abre o documento no trecho citado |
| **Prazos** | Calendário mensal e lista (a vencer / vencidos), com alerta de 7 dias e exportação `.ics` |
| **Qualidade** | Taxa de respostas fundamentadas, por semana, respostas reprovadas para revisar e um **conjunto de verificação** executado em lote |
| **Relatório** | Página de impressão do documento (ficha, prazos, valores, perguntas e respostas, texto integral); "Salvar como PDF" pelo navegador |
| **Espaços** | Cada equipe tem seu espaço; o dono adiciona membros que já têm conta. Nada vaza entre espaços (há testes para isso) |

## Como a IA entra
| Recurso | Como funciona |
|---|---|
| Extração estruturada | A IA devolve JSON validado por **JSON Schema**: tipo, resumo, partes, prazos e valores |
| Busca semântica (RAG) | Documentos viram **trechos** (`Chunker`, com posição no texto original) e **embeddings**; consulta por cosseno (`VectorSearch`), sempre dentro do espaço |
| Agente com ferramentas | Laço de **tool calling**: `search_documents`, `get_document`, `list_prazos` (`DocumentAgent` + `AgentTools`), limitado por `Escopo` |
| Avaliação | `AnswerEvaluator` confere se a resposta cita `[T<id>]` e se toda citação vem de trecho recuperado; o painel de Qualidade acompanha isso |

## Arquitetura
```
Controller / Job ──► DocumentProcessor ──► AiClient::structured()  (extração, JSON Schema)
                                      ├──► Chunker ► Embedder ► trechos (com início/fim)
                                      └──► prazos (datas validadas)
Pergunta ──► Consultas ──► DocumentAgent(Escopo) ──► AiClient::chat(tools) ──► AgentTools ──► VectorSearch / Eloquent
                      └──► AnswerEvaluator  →  mensagem salva com fontes, passos e avaliação
```
- **`AiClient` é uma interface**: o container injeta o driver de `config/ia.php`.
  - `anthropic`: Messages API real (tool use e saída estruturada via ferramenta forçada).
  - `fake`: offline e determinístico (padrão em dev/testes; não precisa de chave).
- **`Embedder`** também é uma interface. O padrão é um embedder local por *feature hashing* (lexical, sem rede). Em produção, troque por um embedder de provedor e o armazenamento por um banco vetorial (pgvector, Elasticsearch/OpenSearch) mantendo a interface de `VectorSearch`.

## Stack
PHP 8.4 · Laravel 13 · Eloquent · Filas · Form Requests · Vue 3 · Vite · SQLite · PHPUnit · `smalot/pdfparser`

## Design
Papel creme, tinta azul-marinho, vermelho de carimbo e marca-texto amarelo. Títulos e leitura em **Newsreader** (serifa editorial); registros, datas, valores e citações em **IBM Plex Mono**. Fontes servidas pelo próprio projeto (`@fontsource`). Respeita `prefers-reduced-motion`, tem foco visível, link de pular para o conteúdo e layout responsivo; o relatório tem CSS de impressão.

## Como rodar
```bash
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed        # cria 2 usuários, 1 espaço, 5 documentos fictícios, prazos e conversas
php artisan serve
```
Abra http://localhost:8000 e entre com a conta de demonstração (dados fictícios): `demo@docsia.test` / `demonstracao`. Há também `colega@docsia.test`, com a mesma senha, que participa do mesmo espaço. Precisa das extensões `pdo_sqlite` e `sqlite3` do PHP.

Para ver os documentos serem processados sem rodar um worker, use `QUEUE_CONNECTION=sync` no `.env`. Com `QUEUE_CONNECTION=database`, rode também `php artisan queue:work`.

### Usando um LLM de verdade
No `.env`:
```
AI_DRIVER=anthropic
ANTHROPIC_API_KEY=sua-chave
```
Sem isso, o modo de demonstração usa o driver `fake`: as respostas são montadas a partir dos trechos encontrados, e o resumo é uma frase automática. **A chamada real à API está implementada e testada com HTTP simulado, mas não foi exercitada em produção.**

## Testes
```bash
php artisan test
```
67 testes, sem rede: chunking e posições, embeddings, busca, extração, prazos, PDF (gerado em memória), laço de tool calling, avaliação, autenticação, **isolamento entre espaços**, calendário e `.ics`, painel de qualidade e o driver real com HTTP simulado (`Http::fake`).

## Rotas principais
| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/documentos` | Arquivo (filtros: `q`, `tipo`, `status`) |
| `GET` | `/documentos/{id}?trecho=ID` | Leitor, com o trecho citado destacado |
| `POST` | `/api/perguntar` | `{pergunta, conversa_id?, documento_id?}` → `{conversa_id, mensagem}` (sessão + CSRF) |
| `GET` | `/api/busca?q=` | Busca semântica nos trechos do espaço |
| `GET` | `/prazos?mes=AAAA-MM`, `/prazos.ics` | Calendário e exportação |

## Limitações conhecidas
- Similaridade calculada em PHP (adequado para poucos milhares de trechos) e embedder lexical.
- PDFs só com texto selecionável; sem OCR.
- Espaços sem convite por e-mail nem papéis: o dono adiciona quem já tem conta, e todo membro pode tudo, menos gerenciar membros.
- Tema claro apenas.
