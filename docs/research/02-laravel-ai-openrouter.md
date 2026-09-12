# Laravel AI SDK + OpenRouter embeddings

Date: 2026-09-12

## Verdict

Use [`laravel/ai` v0.11.2](https://packagist.org/packages/laravel/ai) with OpenRouter for **both chat and embeddings**. The official Laravel 13 provider matrix lists OpenRouter under Text **and** Embeddings. Pin a model id (recommend `openai/text-embedding-3-small` at 1536 dimensions). Do not store vectors in MySQL JSON. Laravel’s first-class vector columns and similarity queries only work on PostgreSQL + [pgvector](https://github.com/pgvector/pgvector) or MariaDB 11.7+. Gaia already has DBngin MySQL + Redis and no running Postgres — start a local Postgres (DBngin or [Herd Pro, which ships pgvector](https://herd.laravel.com/docs/macos/herd-pro-services/postgresql)) and keep MySQL as the app store if needed. Embed a stable **person summary**, not raw dump rows. Resolve entities with deterministic keys first; use embeddings only for similar-entity suggestions. Treat every embedding request as a PII transmission to OpenRouter and the upstream model provider.

---

## 1. Official Laravel AI SDK

### Version, install, publish

| Fact | Source |
| --- | --- |
| Latest stable **v0.11.2**, published 2026-09-03 | [Packagist `laravel/ai`](https://packagist.org/packages/laravel/ai), [CHANGELOG](https://github.com/laravel/ai/blob/0.x/CHANGELOG.md) |
| Marketing site also reports v0.11.2 | [laravel.com/ai](https://laravel.com/ai) |
| Install: `composer require laravel/ai` | [Laravel AI SDK docs (13.x)](https://laravel.com/framework/docs/13.x/ai-sdk), [GitHub README](https://github.com/laravel/ai) |
| Requires PHP `^8.3` and Illuminate `^12.0\|^13.0` | [composer.json on 0.x](https://raw.githubusercontent.com/laravel/ai/0.x/composer.json) |
| Publish: `php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"` then `php artisan migrate` | [Laravel AI SDK docs — Installation](https://laravel.com/framework/docs/13.x/ai-sdk) |
| Publish creates `agent_conversations` and `agent_conversation_messages` | [Laravel AI SDK docs — Installation](https://laravel.com/framework/docs/13.x/ai-sdk) |
| Default embeddings provider in published config is `openai`, not OpenRouter | [config/ai.php](https://raw.githubusercontent.com/laravel/ai/0.x/config/ai.php) (`default_for_embeddings` => `'openai'`) |

OpenRouter env key in the official docs and published config:

```ini
OPENROUTER_API_KEY=
```

Cited: [Laravel AI SDK configuration](https://laravel.com/framework/docs/13.x/ai-sdk), [config/ai.php](https://raw.githubusercontent.com/laravel/ai/0.x/config/ai.php).

Published OpenRouter provider block (no default embedding model in the published file — SDK class defaults apply):

```php
'openrouter' => [
    'driver' => 'openrouter',
    'key' => env('OPENROUTER_API_KEY'),
],
```

Cited: [config/ai.php](https://raw.githubusercontent.com/laravel/ai/0.x/config/ai.php).

Custom base URLs are supported for OpenRouter. Cited: [Laravel AI SDK — Custom Base URLs](https://laravel.com/framework/docs/13.x/ai-sdk).

### Official provider matrix (quote)

From [Laravel AI SDK — Provider Support](https://laravel.com/framework/docs/13.x/ai-sdk):

| Feature | Providers |
| --- | --- |
| Text | OpenAI, OpenAI Compatible, Anthropic, Gemini, Azure, Bedrock, Groq, xAI, DeepSeek, Mistral, Ollama, **OpenRouter** |
| Images | OpenAI, Gemini, xAI, Azure, Bedrock, **OpenRouter** |
| TTS | OpenAI, ElevenLabs, Gemini |
| STT | OpenAI, OpenAI Compatible, ElevenLabs, Groq, Mistral, Gemini |
| Embeddings | OpenAI, OpenAI Compatible, Gemini, Azure, Bedrock, Cohere, Mistral, Jina, VoyageAI, Ollama, **OpenRouter** |
| Reranking | Cohere, Jina, VoyageAI |
| Files | OpenAI, Anthropic, Gemini, Azure |

OpenRouter is on **Embeddings**, not chat-only.

### Lab enum

[`src/Enums/Lab.php`](https://raw.githubusercontent.com/laravel/ai/0.x/src/Enums/Lab.php) includes `OpenRouter = 'openrouter'` plus Anthropic, Azure, Bedrock, Cohere, DeepSeek, ElevenLabs, Gemini, Groq, Jina, Mistral, Ollama, OpenAI, OpenAICompatible, VoyageAI, xAI.

### SDK embeddings API

Official docs ([Embeddings](https://laravel.com/framework/docs/13.x/ai-sdk)):

```php
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;

$response = Embeddings::for([
    'Napa Valley has great wine.',
    'Laravel is a PHP framework.',
])->generate();

$response = Embeddings::for(['Napa Valley has great wine.'])
    ->dimensions(1536)
    ->generate(Lab::OpenAI, 'text-embedding-3-small');
```

`Embeddings::for()` requires a non-empty list (not an associative array) of non-blank strings or file objects. Cited: [`PendingEmbeddingsGeneration` constructor](https://raw.githubusercontent.com/laravel/ai/0.x/src/PendingResponses/PendingEmbeddingsGeneration.php).

Queueing exists on the same pending object (`->queue($provider, $model)`), even though the Embeddings section of the docs shows `generate()` / `cache()` more prominently. Cited: [`PendingEmbeddingsGeneration::queue()`](https://raw.githubusercontent.com/laravel/ai/0.x/src/PendingResponses/PendingEmbeddingsGeneration.php), [testing queued embeddings](https://laravel.com/framework/docs/13.x/ai-sdk).

Caching: enable `ai.caching.embeddings.cache`, or call `->cache()` per request (default 30 days; key includes provider, model, dimensions, input). Cited: [Caching Embeddings](https://laravel.com/framework/docs/13.x/ai-sdk).

### OpenRouter in the SDK (source of truth)

[`OpenRouterProvider`](https://raw.githubusercontent.com/laravel/ai/0.x/src/Providers/OpenRouterProvider.php) implements `EmbeddingProvider` and `TextProvider`. Defaults when `config/ai.php` does not set `models.embeddings`:

- Default embeddings model: `google/gemini-embedding-001`
- Default embeddings dimensions: `1536`

[`OpenRouterGateway::generateEmbeddings()`](https://raw.githubusercontent.com/laravel/ai/0.x/src/Gateway/OpenRouter/OpenRouterGateway.php) POSTs to OpenRouter `embeddings` with `model`, `input` (array), and `dimensions`. Extra `withProviderOptions()` keys are merged into that JSON body.

---

## 2. OpenRouter embeddings (2026)

### API

OpenRouter documents a dedicated embeddings router:

- Guide: [Embeddings](https://openrouter.ai/docs/api_reference/embeddings)
- POST `https://openrouter.ai/api/v1/embeddings` — [Submit an embedding request](https://openrouter.ai/docs/api/api-reference/embeddings/submit-an-embedding-request)
- GET `https://openrouter.ai/api/v1/embeddings/models` — [List all embeddings models](https://openrouter.ai/docs/api/api-reference/embeddings/list-all-embeddings-models)
- Catalog UI: [models?output_modalities=embeddings](https://openrouter.ai/models?fmt=cards&output_modalities=embeddings), [Text Embedding Models collection](https://openrouter.ai/collections/embedding-models)

Request body (official example): `model` (required), `input` (string or `string[]` or multimodal), optional `dimensions` (`>= 1`), `encoding_format` (`float` \| `base64`), `input_type`, `provider`, `user`. Cited: [Submit an embedding request](https://openrouter.ai/docs/api/api-reference/embeddings/submit-an-embedding-request).

Official example pins `openai/text-embedding-3-small` at 1536 dimensions. Same page.

Limitations from the [embeddings guide](https://openrouter.ai/docs/api_reference/embeddings): no streaming; same input is deterministic; texts over the model context are truncated or rejected; 429 = rate limit, 404 = model not an embeddings model.

### Models available for `/embeddings` on 2026-09-12

Live `GET https://openrouter.ai/api/v1/embeddings/models` returned **`total_count` 33**. Catalog IDs (prompt price is USD per token as returned by the API):

| Model id | Context | Input modalities | Prompt price (API) | Official page price |
| --- | --- | --- | --- | --- |
| `openai/text-embedding-3-small` | 8192 | text | `0.00000002` | [$0.02/M tokens](https://openrouter.ai/openai/text-embedding-3-small) |
| `openai/text-embedding-3-large` | 8192 | text | `0.00000013` | [$0.13/M tokens](https://openrouter.ai/openai/text-embedding-3-large) |
| `openai/text-embedding-ada-002` | 8192 | text | `0.0000001` | (listed in catalog API) |
| `google/gemini-embedding-001` | 20000 | text | `0.00000015` | [$0.15/M tokens](https://openrouter.ai/google/gemini-embedding-001) |
| `google/gemini-embedding-2` | 8192 | text, image, file, audio, video | `0.0000002` | [$0.20/M tokens](https://openrouter.ai/collections/embedding-models) |
| `google/gemini-embedding-2-preview` | 8192 | text, image, file, audio, video | `0.0000002` | catalog API |
| `qwen/qwen3-embedding-8b` | 32768 | text | `0.00000001` | [$0.01/M tokens](https://openrouter.ai/qwen/qwen3-embedding-8b) |
| `qwen/qwen3-embedding-4b` | 32768 | text | `0.00000002` | [$0.02/M tokens](https://openrouter.ai/collections/embedding-models) |
| `baai/bge-m3` | 8194 | text | `0.00000001` | [$0.01/M, 1024-d](https://openrouter.ai/collections/embedding-models) |
| `mistralai/mistral-embed-2312` | 8192 | text | `0.0000001` | [$0.10/M, 1024-d](https://openrouter.ai/collections/embedding-models) |
| `mistralai/codestral-embed-2505` | 8192 | text | `0.00000015` | catalog API |
| `perplexity/pplx-embed-v1-0.6b` | 32000 | text | `0.000000004` | [$0.004/M](https://openrouter.ai/collections/embedding-models) |
| `perplexity/pplx-embed-v1-4b` | 32000 | text | `0.00000003` | catalog API |
| `voyageai/voyage-4` | 32000 | text | `0.00000006` | [$0.06/M; 2048/1024/512/256](https://openrouter.ai/collections/embedding-models) |
| `voyageai/voyage-4-lite` | 32000 | text | `0.00000002` | catalog API |
| `voyageai/voyage-4-large` | 32000 | text | `0.00000012` | catalog API |
| `voyageai/voyage-code-4` | 32000 | text | `0.00000012` | catalog API |
| `voyageai/voyage-multimodal-3.5` | 32000 | text, image | `0.00000012` | catalog API |
| `thenlper/gte-base`, `thenlper/gte-large` | 512 | text | `5e-9` / `1e-8` | catalog API |
| `intfloat/e5-base-v2`, `intfloat/e5-large-v2`, `intfloat/multilingual-e5-large` | 512 | text | `5e-9` / `1e-8` | catalog API |
| `baai/bge-base-en-v1.5`, `baai/bge-large-en-v1.5` | 512 | text | `5e-9` / `1e-8` | catalog API |
| several `sentence-transformers/*` | 512 | text | `5e-9` | catalog API |
| `liquid/lfm-2.5-embedding-350m:free` | 512 | text | `0` | catalog API (`:free`) |
| `nvidia/nemotron-3-embed-1b:free` | 32768 | text | `0` | catalog API (`:free`) |
| `nvidia/llama-nemotron-embed-vl-1b-v2:free` | 131072 | text, image | `0` | catalog API (`:free`) |

Collection rankings (week of research): Qwen3 Embedding 8B, Text Embedding 3 Small, Text Embedding 3 Large. Cited: [Text Embedding Models](https://openrouter.ai/collections/embedding-models) (updated September 2026).

**Pinning:** yes. Pass the OpenRouter model id (`openai/text-embedding-3-small`, `google/gemini-embedding-001`, …). The POST schema requires `model`. Cited: [Submit an embedding request](https://openrouter.ai/docs/api/api-reference/embeddings/submit-an-embedding-request).

**Not in the live `/embeddings/models` list:** `qwen/qwen3-embedding-0.6b`. A [model page exists](https://openrouter.ai/qwen/qwen3-embedding-0.6b) and the [embeddings guide](https://openrouter.ai/docs/api_reference/embeddings) still names it as a cheaper example. Do not pin it until `GET /embeddings/models` includes it (404 risk; embeddings guide lists 404 for non-embedding / missing models).

### Dimensions (owned by each model vendor)

The OpenRouter catalog API did **not** return a dimensions field. Use provider docs + OpenRouter `dimensions` parameter.

| Model | Native / default dims | Shortening | Source |
| --- | --- | --- | --- |
| `text-embedding-3-small` | **1536** | `dimensions` API param | [OpenAI embeddings](https://platform.openai.com/docs/guides/embeddings) |
| `text-embedding-3-large` | **3072** | same; 256 still beats ada-002 1536 on MTEB (OpenAI) | [OpenAI embeddings](https://platform.openai.com/docs/guides/embeddings) |
| `gemini-embedding-001` / `gemini-embedding-2` | **3072** | MRL; recommend **768, 1536, or 3072**; 001 needs manual L2-normalize if truncated | [Gemini embeddings](https://ai.google.dev/gemini-api/docs/embeddings) |
| `Qwen3-Embedding-4B` | **2560** (32–2560) | MRL | [Qwen3-Embedding-4B](https://huggingface.co/Qwen/Qwen3-Embedding-4B) |
| `Qwen3-Embedding-8B` | **4096** (32–4096) | MRL | [Qwen3-Embedding-8B](https://huggingface.co/Qwen/Qwen3-Embedding-8B) |
| `Qwen3-Embedding-0.6B` | **1024** (32–1024) | MRL | [Qwen3-Embedding-8B model card table](https://huggingface.co/Qwen/Qwen3-Embedding-8B) |
| `bge-m3` | **1024** | — | [OpenRouter collection](https://openrouter.ai/collections/embedding-models) |
| `mistral-embed-2312` | **1024** | — | [OpenRouter collection](https://openrouter.ai/collections/embedding-models) |
| `voyage-4` | 2048 / 1024 / 512 / 256 | documented options | [OpenRouter collection](https://openrouter.ai/collections/embedding-models) |
| `gemini-embedding-2` (OR page) | 128–3072; recommend 768 / 1536 / 3072 | OpenRouter page | [Gemini Embedding 2 on collection](https://openrouter.ai/collections/embedding-models) |

Laravel’s OpenRouter default of **1536** on `google/gemini-embedding-001` is a **truncated** Gemini size (valid per Google), not Gemini’s native 3072. Cited: [OpenRouterProvider](https://raw.githubusercontent.com/laravel/ai/0.x/src/Providers/OpenRouterProvider.php), [Gemini embeddings](https://ai.google.dev/gemini-api/docs/embeddings).

### Pricing page

Per-model pages and the [embedding collection](https://openrouter.ai/collections/embedding-models) are the official prices. There is no single embeddings-only price list; [openrouter.ai/pricing](https://openrouter.ai/pricing) is the general pricing hub. API `pricing.prompt` matches the $/M figures (e.g. `0.00000002` → $0.02/M).

### Rate limits

From [OpenRouter Limits](https://openrouter.ai/docs/api_reference/limits):

- Two limit types: **credits** (account + optional per-key cap via `GET /api/v1/key`) and **rate limits** (free-model RPM/RPD + Cloudflare DDoS).
- Extra accounts/keys do **not** raise rate limits.
- Paid models: no documented platform request cap besides DDoS protection. Free variants (`:free`) have RPM/RPD that rise after a credit-purchase threshold (table values failed to render in the fetched HTML; read the live page).
- 429 from OpenRouter or the upstream provider. Retry with backoff; honor `Retry-After`. Successful responses do **not** include `X-RateLimit-*`; 429s from OpenRouter do.
- Embeddings 429 body: `{ "error": { "code": 429, "message": "Rate limit exceeded" } }`. Cited: [Submit an embedding request](https://openrouter.ai/docs/api/api-reference/embeddings/submit-an-embedding-request), [Limits](https://openrouter.ai/docs/api_reference/limits).

OpenRouter did not publish a max texts-per-embeddings-request. The guide says to batch an array of strings. Cited: [Embeddings — batch processing / best practices](https://openrouter.ai/docs/api_reference/embeddings).

---

## 3. Recommended embedding pipeline for Gaia

### What to embed

OpenRouter: embeddings are for semantic search, duplicate/near-duplicate detection, clustering, RAG; **cache** them (deterministic); **chunk** long text by meaning, not arbitrary character cuts; respect each model’s context. Cited: [Embeddings guide](https://openrouter.ai/docs/api_reference/embeddings).

For OSINT people:

1. **Person summary (primary).** One stable string per person: display name, aliases, short bio, notes, and *normalized identity tokens you already treat as public in the graph* (not raw dump blobs). Re-embed when that summary hash changes.
2. **Dump excerpts (optional, secondary).** Chunk source excerpts for “find similar passages / RAG,” each row pointing at `person_id` + `source_id`. Do not embed every raw dump row as the person vector — noisy, expensive, and unstable for entity similarity.

Deterministic keys (email, E.164 phone, `platform:username`) remain the merge key. Embeddings are a **suggestion** layer. OpenRouter itself lists “duplicate detection” as a use case, not as a substitute for unique identifiers. Cited: [Embeddings — common use cases](https://openrouter.ai/docs/api_reference/embeddings).

### Where to store vectors locally

Laravel: “store them in a `vector` column.” Native support is **PostgreSQL via pgvector** and **MariaDB**. `Schema::ensureVectorExtensionExists()`, `$table->vector('embedding', dimensions: 1536)->index()` (HNSW + cosine), `AsVector` cast, `whereVectorSimilarTo`. **“Vector queries are currently supported on PostgreSQL connections using the pgvector extension and MariaDB 11.7 or later.”** Cited: [Querying Embeddings](https://laravel.com/framework/docs/13.x/ai-sdk).

Gaia today: DBngin MySQL 8.4.11 + Redis; Postgres not running; PHPUnit on in-memory SQLite. Cited: [docs/research/README.md](./README.md). Laravel does **not** list MySQL for vector queries. Do not put float arrays in MySQL JSON.

| Store | Official capability | Fit for Gaia |
| --- | --- | --- |
| **pgvector on Postgres** | `CREATE EXTENSION vector`; `vector(n)` up to **16,000** dims; HNSW / IVFFlat; cosine `<=>`. [pgvector README](https://github.com/pgvector/pgvector) | **Recommended.** Laravel first-class. [DBngin](https://dbngin.com) runs Postgres locally. [Herd](https://herd.laravel.com/docs/macos/getting-started/databases) says use vendor / DBngin / Herd Pro. [Herd Pro PostgreSQL](https://herd.laravel.com/docs/macos/herd-pro-services/postgresql) **ships pgvector**. DBngin does not advertise pgvector — enable the extension after start (`CREATE EXTENSION vector`). |
| **MariaDB 11.7+ `VECTOR(N)`** | Community 11.7+; max **16383** dims; `VECTOR INDEX`. [MariaDB VECTOR](https://mariadb.com/docs/server/reference/sql-structure/vectors/vector) | Laravel-supported alternative if you **replace** MySQL 8.4. Not a JSON column on current MySQL. Herd Pro also offers [MariaDB](https://herd.laravel.com/docs/macos/herd-pro-services/mariadb). |
| **MySQL 8.4 JSON** | Not in Laravel’s vector-query support list | Reject for ANN. |
| **Neo4j vector index** | `CREATE VECTOR INDEX … OPTIONS { indexConfig: { \`vector.dimensions\`: 1536, \`vector.similarity_function\`: 'cosine' } }`; dims **1–4096**. [Neo4j vector indexes](https://neo4j.com/docs/cypher-manual/current/indexes/semantic-indexes/vector-indexes/) | Use **if** the graph DB choice is Neo4j and you want person vectors on nodes. 4096 cap rules out untruncated Qwen3-8B (4096 is the max, so native 4096 fits; larger would not). Not Laravel-native. |
| **Kuzu** | Native HNSW `vector` extension; node `FLOAT[n]` properties; `CREATE_VECTOR_INDEX` / `QUERY_VECTOR_INDEX`. [Kuzu vector search](https://kuzudb.github.io/docs/extensions/vector) | Repo [archived 2025-10-10](https://github.com/kuzudb/kuzu). Fine for an embedded graph experiment; not a Laravel-maintained path. `vector` is preinstalled on 0.11.3. |
| **OpenSearch** | First-party [vector search](https://docs.opensearch.org/latest/vector-search/) / [vector database](https://opensearch.org/platform/search/vector-database.html) | Extra cluster. Overkill for a local Herd app unless you already need search infra. |
| **sqlite-vec** | `vec0` virtual tables; **pre-v1, breaking changes expected**. [sqlite-vec](https://github.com/asg017/sqlite-vec) | Matches PHPUnit’s SQLite **only as a test double**, not production. Laravel vector query APIs do not target SQLite. |

**Store recommendation:** add local Postgres (DBngin or Herd Pro) + pgvector. Keep DBngin MySQL for existing app tables if you do not want to migrate. Optionally put the same 1536-d vector on a Neo4j/Kuzu person node later; do not make that the only index if you want `whereVectorSimilarTo`.

### Batch size, queues, idempotency, cost

- **Batch:** `Embeddings::for([...])` + OpenRouter array `input`. Official advice: one request for many texts. Cited: [OpenRouter best practices](https://openrouter.ai/docs/api_reference/embeddings), [Laravel Embeddings](https://laravel.com/framework/docs/13.x/ai-sdk). Start with tens-to-low-hundreds of short summaries; OpenRouter does not publish a batch cap.
- **Queue:** `Embeddings::for($texts)->dimensions(1536)->queue(Lab::OpenRouter, 'openai/text-embedding-3-small')` then persist in `then`. Horizon/Redis already available. Cited: [`PendingEmbeddingsGeneration::queue`](https://raw.githubusercontent.com/laravel/ai/0.x/src/PendingResponses/PendingEmbeddingsGeneration.php).
- **Idempotency:** Laravel cache key hashes driver, model, dimensions, options, input. Cited: [Caching Embeddings](https://laravel.com/framework/docs/13.x/ai-sdk), [`cacheKey()`](https://raw.githubusercontent.com/laravel/ai/0.x/src/PendingResponses/PendingEmbeddingsGeneration.php). Also persist `content_sha256` + `model` + `dimensions` on the row and skip if unchanged. OpenRouter: embeddings for the same text are deterministic. Cited: [Embeddings — limitations](https://openrouter.ai/docs/api_reference/embeddings).
- **Cost:** pin one model; cache; embed summaries not dumps. At $0.02/M, 500-token summaries cost ~$0.01 per 1,000 people (`openai/text-embedding-3-small`). `qwen/qwen3-embedding-8b` is $0.01/M but 4096-d unless you pass `dimensions`. Cap spend with OpenRouter per-key limits. Cited: [Limits — credit limits](https://openrouter.ai/docs/api_reference/limits), model pages above.
- **Failover:** Laravel can take a provider list and fail over. Cited: [Laravel failover](https://laravel.com/framework/docs/13.x/ai-sdk). OpenRouter can also fail over providers for the same model. Cited: [Provider routing](https://openrouter.ai/docs/guides/routing/provider-selection). Pinning a model still allows upstream provider failover unless you lock `provider.only`.

### Dedup / entity resolution

| Layer | Use | Do not use for |
| --- | --- | --- |
| Deterministic | email, phone, `platform:username`, government id if present | “looks like the same person” from bio prose |
| Embeddings | similar-name / similar-bio suggestions, dump-chunk retrieval | automatic merge without a human or a hard key |

OpenRouter’s own duplicate-detection blurb is cosine-near-duplicate **text**, not legal identity. Cited: [Embeddings — common use cases](https://openrouter.ai/docs/api_reference/embeddings). Laravel similarity: `whereVectorSimilarTo('embedding', $query, minSimilarity: 0.4)` (0–1 cosine). Cited: [Querying Embeddings](https://laravel.com/framework/docs/13.x/ai-sdk). Tune the threshold on a labeled sample; do not copy 0.4 blindly.

---

## 4. Security and data policy

### API keys

- Put `OPENROUTER_API_KEY` in `.env` only. Cited: [Laravel configuration](https://laravel.com/framework/docs/13.x/ai-sdk).
- OpenRouter: Bearer token; “You must protect your API keys and never commit them to public repositories”; GitHub secret scanning partner; rotate at [key settings](https://openrouter.ai/settings/keys) if leaked. Cited: [Authentication](https://openrouter.ai/docs/api_reference/authentication).
- Never log the key, `Authorization` headers, or dumped `config('ai')`.

### PII in prompts and embeddings

Embedding a name, bio, note, or dump excerpt **is an Input**. OpenRouter’s [Privacy Policy](https://openrouter.ai/privacy) (last updated August 31, 2026): Inputs that include personal data are collected; Inputs are sent to the selected Model Provider; **some providers may train on Inputs/Outputs**; “OpenRouter does not use your Inputs or Outputs for model training.”

[Data Collection](https://openrouter.ai/docs/guides/privacy/data-collection):

- Prompt retention on OpenRouter is **opt-in**.
- OpenRouter does not store prompts/responses unless you enable **Input & Output Logging** (Observability; off by default) and/or **OpenRouter Use of Inputs/Outputs** (1% discount; off by default; Privacy settings).
- Metadata (tokens, latency) is stored without prompt content.
- Small anonymous categorization sample unless you opted into product-improvement logging.

[ZDR](https://openrouter.ai/docs/guides/features/zdr): OpenRouter itself is ZDR unless you opt into prompt logging. Account / guardrail / per-request `provider.zdr: true` restricts routing to zero-retention **endpoints**. Does not cover plugins/tools. In-memory prompt caches are not treated as retention.

[Provider routing](https://openrouter.ai/docs/guides/routing/provider-selection): `data_collection: "deny"` uses only providers that do not collect user data. Also available as an account privacy setting.

[Input & Output Logging](https://openrouter.ai/docs/guides/features/input-output-logging): if enabled, full prompts/completions sit in GCS ≥ 3 months. **Leave this off** for OSINT PII.

Gaia rules implied by those docs:

1. Do not enable I/O logging or the 1% data-discount toggle.
2. Set account privacy to deny training / collect-and-store providers; send `data_collection: deny` (and `zdr: true` if the chosen model still has a ZDR endpoint).
3. Assume embeddings of people are PII in transit to OpenAI / Google / whoever serves the model. `openai/text-embedding-3-small` is served by [Azure and OpenAI](https://openrouter.ai/openai/text-embedding-3-small). Review those providers’ terms from [provider routing — terms](https://openrouter.ai/docs/guides/routing/provider-selection#terms-of-service).
4. Do not log embedding input strings in Laravel logs.

---

## 5. Minimal `.env` and Laravel-shaped sketch

### `.env`

```ini
OPENROUTER_API_KEY=

# Optional: only if you change defaults in config/ai.php
# AI_DEFAULT_EMBEDDINGS=openrouter
```

Published config already reads `OPENROUTER_API_KEY`. Defaults still send embeddings to **OpenAI** unless you pass `Lab::OpenRouter` or set `default_for_embeddings` to `openrouter`. Cited: [config/ai.php](https://raw.githubusercontent.com/laravel/ai/0.x/config/ai.php).

Optional Postgres (Herd Pro example):

```ini
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=gaia
# DB_USERNAME=root
# DB_PASSWORD=
```

Cited: [Herd Pro PostgreSQL](https://herd.laravel.com/docs/macos/herd-pro-services/postgresql).

### Sketch (official APIs only)

```php
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\EmbeddingsResponse;

// Sync, pinned OpenRouter model (do not rely on default_for_embeddings=openai)
$response = Embeddings::for([
    $personSummary, // stable, short, already-normalized text
])
    ->dimensions(1536)
    ->cache()
    ->withProviderOptions([
        'provider' => [
            'data_collection' => 'deny',
            'allow_fallbacks' => true,
        ],
    ])
    ->generate(Lab::OpenRouter, 'openai/text-embedding-3-small');

$vector = $response->embeddings[0]; // list<float>, length 1536

// Background ingest
Embeddings::for($summaries)
    ->dimensions(1536)
    ->cache()
    ->queue(Lab::OpenRouter, 'openai/text-embedding-3-small')
    ->then(function (EmbeddingsResponse $response): void {
        // persist $response->embeddings onto vector columns
    });
```

`withProviderOptions` is on the embeddings pending object and is merged into the OpenRouter JSON body. Cited: [`ResolvesProviderOptions`](https://raw.githubusercontent.com/laravel/ai/0.x/src/PendingResponses/Concerns/ResolvesProviderOptions.php), [`OpenRouterGateway::generateEmbeddings`](https://raw.githubusercontent.com/laravel/ai/0.x/src/Gateway/OpenRouter/OpenRouterGateway.php), [OpenRouter provider object](https://openrouter.ai/docs/api_reference/embeddings).

Vector column (Postgres + pgvector), from Laravel docs:

```php
Schema::ensureVectorExtensionExists();

Schema::create('person_embeddings', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('person_id');
    $table->string('model');
    $table->unsignedSmallInteger('dimensions');
    $table->string('content_sha256', 64);
    $table->vector('embedding', dimensions: 1536)->index();
    $table->timestamps();
    $table->unique(['person_id', 'model', 'dimensions']);
});
```

Cited: [Querying Embeddings](https://laravel.com/framework/docs/13.x/ai-sdk). Query: `PersonEmbedding::query()->whereVectorSimilarTo('embedding', $queryEmbedding, minSimilarity: 0.4)->limit(10)->get()`.

Tests: `Embeddings::fake();` Cited: [Testing — Embeddings](https://laravel.com/framework/docs/13.x/ai-sdk).

Install sequence from docs: `composer require laravel/ai` → `php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"` → `php artisan migrate`.

---

## Recommendation

1. `composer require laravel/ai` (v0.11.2), publish, set `OPENROUTER_API_KEY`.
2. Always call `Lab::OpenRouter` (or set `default_for_embeddings` to `openrouter`). Published default is OpenAI.
3. Pin **`openai/text-embedding-3-small`** at **1536**. Native OpenAI size, official OpenRouter example, Laravel default dim, $0.02/M, 8k context. Recheck `GET /api/v1/embeddings/models` before changing pins.
4. Start **Postgres + pgvector** (Herd Pro includes it; DBngin can run Postgres). Keep MySQL for non-vector app data. Do not use MySQL JSON for vectors.
5. Embed **person summaries**; optional dump chunks as a second table. Merge on email/phone/username; embeddings only suggest.
6. Queue + `->cache()` + content hash. Per-key credit cap on OpenRouter.
7. Leave OpenRouter I/O logging and data-discount **off**. Send `data_collection: deny`. Never log keys or embedding inputs.

---

## Open questions

1. Will Gaia run **Herd Pro Postgres** (pgvector bundled) or **DBngin Postgres** (must `CREATE EXTENSION vector` yourself)?
2. Is the graph store Neo4j, Kuzu, or something else? That decides whether a second vector index on graph nodes is worth it. Kuzu’s upstream is archived (2025-10-10).
3. Confirm `withProviderOptions(['provider' => ['data_collection' => 'deny']])` against a live OpenRouter embeddings call (SDK merges options; OpenRouter documents the field; not shown in Laravel’s embeddings doc examples).
4. Recheck `GET /api/v1/embeddings/models` before pinning Qwen 0.6B — page exists, catalog list did not include it on 2026-09-12.
5. If using Gemini 001 truncated to 1536, confirm you L2-normalize (Google requires this for 001 non-3072; Gemini 2 auto-normalizes). Laravel/OpenRouter may or may not normalize for you — **not documented** in laravel/ai.
6. OpenRouter’s official max batch size for `/embeddings` is unpublished. Measure 413 / provider errors on real dump sizes.
7. Legal basis for embedding third-party OSINT PII and sending it to US/Azure/OpenAI endpoints is a product/legal decision, not an SDK fact.
