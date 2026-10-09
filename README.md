# DocsIA — documentos com IA em Laravel

Aplicação Laravel que recebe documentos de texto (contratos, notificações, políticas), **extrai dados estruturados com IA**, **indexa os trechos para busca semântica (RAG)** e responde perguntas por meio de um **agente com tool calling** que cita as fontes e avalia se a resposta está fundamentada.

> Projeto de portfólio de **Albert Ryan**, desenvolvido com apoio de IA (Claude Code). Eu defini o escopo, revisei e estudei o código; o guia `docs/ESTUDO.md` documenta cada parte. Os documentos de exemplo são fictícios, e a aplicação **não dá aconselhamento jurídico**.

## O que ele faz
| Recurso | Como funciona |
|---|---|
| Upload de documentos | Texto colado ou arquivo `.txt/.md`; o processamento roda numa **fila** (`ProcessarDocumento`) |
| Extração estruturada | A IA devolve JSON validado por um **JSON Schema**: tipo, resumo, partes, prazos e valores |
| Busca semântica (RAG) | Documentos viram **trechos** (`Chunker`) com **embeddings**; consulta por similaridade de cosseno (`VectorSearch`) |
| Agente com ferramentas | Laço de **tool calling**: `search_documents`, `get_document`, `list_prazos` (`DocumentAgent` + `AgentTools`) |
| Avaliação da resposta | `AnswerEvaluator` confere se a resposta cita `[T<id>]` e se todas as citações vêm de trechos recuperados |
| Interface | Blade + chat em **Vue 3** (Vite), com fontes, passos do agente e selo de fundamentação |

## Arquitetura
```
Controller / Job ──► DocumentProcessor ──► AiClient::structured()  (extração, JSON Schema)
                                      └──► Chunker ► Embedder ► tabela trechos
Pergunta ──► DocumentAgent ──► AiClient::chat(tools) ──► AgentTools ──► VectorSearch / Eloquent
                         └──► AnswerEvaluator (groundedness)
```
- **`AiClient` é uma interface**: o container injeta o driver de `config/ia.php`.
  - `anthropic`: Messages API real (tool use e saída estruturada via ferramenta forçada).
  - `fake`: offline e determinístico (padrão em dev/testes; não precisa de chave).
- **`Embedder`** também é uma interface. O padrão é um embedder local por *feature hashing* (sem rede). Em produção, troque por um embedder de provedor e o armazenamento por um banco vetorial (pgvector, Elasticsearch/OpenSearch) mantendo a interface de `VectorSearch`.

## Stack
PHP 8.4 · Laravel 13 · Eloquent · Filas · Form Requests · Vue 3 · Vite · SQLite · PHPUnit

## Como rodar
```bash
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed        # cria 3 documentos fictícios já processados
php artisan serve
```
Abra http://localhost:8000 (Documentos) e `/chat` (Perguntar à IA). Precisa das extensões `pdo_sqlite` e `sqlite3` do PHP.

### Usando um LLM de verdade
No `.env`:
```
AI_DRIVER=anthropic
ANTHROPIC_API_KEY=sua-chave
```
Para processar em segundo plano, rode também `php artisan queue:work` e use `QUEUE_CONNECTION=database`.

## Testes
```bash
php artisan test
```
28 testes: chunking, embeddings, busca, extração, laço de tool calling, avaliação, validação e o driver real com HTTP simulado (`Http::fake`, sem rede).

## API
| Método | Rota | Descrição |
|---|---|---|
| `POST` | `/api/chat` | `{pergunta, historico?}` → `{resposta, fontes, passos, avaliacao}` |
| `GET` | `/api/busca?q=` | Busca semântica nos trechos |

## Limitações conhecidas
- Similaridade calculada em PHP (adequado para poucos milhares de trechos).
- Sem autenticação nem multiusuário (escopo de demonstração).
- Só texto; PDFs e OCR não estão incluídos.
