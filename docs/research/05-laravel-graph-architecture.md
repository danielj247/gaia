# Laravel graph architecture

**Date:** 2026-09-12

**Verdict:** Keep DBngin MySQL as Laravel’s default connection for auth, dump metadata, jobs, cache, and sessions. Do **not** register Neo4j, FalkorDB, or Kuzu as an Eloquent / `config/database.php` SQL driver. Bind a small `GraphClient` interface as a Laravel singleton and have `final readonly` Actions call that interface. For a server graph, the maintained PHP client is `laudis/neo4j-php-client` **3.6.0** over Bolt (`bolt://` / `neo4j://`). HTTP was removed from that client in 3.3.0; Neo4j’s current HTTP surface is the Query API (`POST /db/{name}/query/v2`), which the same authors ship separately as `neo4j-php/query-api` **1.0.0**. Apache AGE is usable only as a **second PostgreSQL** connection plus `cypher()` SQL — there is no official PHP driver. Kuzu has an official C API and **no official PHP client**, and the Kuzu repo is archived. FalkorDB has an official `falkordb/falkordb-php` **0.2.0** on phpredis; treat it as a separate process from Laravel’s Redis cache/queue. `graphaware/neo4j-php-ogm` is abandoned.

## Recommended architecture

```mermaid
flowchart TB
  subgraph http [HTTP / Inertia]
    C[Thin controllers]
  end

  subgraph app [Laravel app process]
    A1[IngestDump]
    A2[ParseDumpChunk]
    A3[UpsertPerson]
    A4[LinkIdentifier]
    A5[ExpandNeighborhood]
    A6[SearchEntities]
    J[Queue jobs wrapping Actions]
    DTO[Spatie Data: GraphNode / GraphEdge / Neighborhood]
    GC[GraphClient interface]
    AD[Driver adapter]
    MY[(MySQL default connection)]
  end

  subgraph graph [Graph system of record]
    N[(Neo4j via Bolt)]
    F[(FalkorDB via RESP)]
    AGE[(PostgreSQL plus AGE cypher)]
  end

  C --> A1
  C --> A5
  C --> A6
  A1 --> J
  J --> A2
  A2 --> A3
  A2 --> A4
  A3 --> GC
  A4 --> GC
  A5 --> GC
  A6 --> GC
  GC --> AD
  AD -->|GRAPH_DRIVER=neo4j| N
  AD -->|GRAPH_DRIVER=falkordb| F
  AD -->|GRAPH_DRIVER=age| AGE
  A1 --> MY
  J --> MY
  DTO --> C
```

MySQL remains the application system of record. The graph store remains the system of record for entities and relationships. Controllers stay thin; Actions stay `final readonly` with a single `handle()`; Spatie Data objects cross the HTTP / TypeScript boundary.

---

## 1. PHP clients

### 1.1 `laudis/neo4j-php-client` (recommended Bolt driver)

| Fact | Source |
| --- | --- |
| Latest stable **3.6.0**, published 2026-06-20 | Packagist `p2` metadata |
| `require.php`: `^8.1` (no upper bound; 8.5 is allowed by Composer, not claimed as tested) | Packagist 3.6.0 `require` |
| Runtime deps: `stefanak-michal/bolt` `^7.4.0`, PSR HTTP discovery, `ext-json`, `ext-mbstring` | Packagist 3.6.0 |
| Suggests `ext-bcmath` for Bolt and `ext-sysvsem` for cross-process pooling | Packagist 3.6.0 `suggest` |
| GitHub: `neo4j-php/neo4j-php-client` | Packagist `source.url` |
| Built/tested with the official Neo4j driver team; validated with TestKit; typed with Psalm | Client README |
| Drivers after 3.3.0: **bolt** (single server) and **neo4j** (client-side routing). HTTP/HTTPS **removed** | Client README; 3.3.0 release notes |
| URI form: `<scheme>://<user>:<password>@<host>:<port>?database=<database>`; default `bolt://localhost:7687?database=neo4j` | Client README |
| Schemes: `bolt` / `bolt+s` / `bolt+ssc`; `neo4j` / `neo4j+s` / `neo4j+ssc` | Client README |
| Preferred write path: `writeTransaction()` (retries transient errors; function must be idempotent) | Client README |
| Result types: `Node`, `Relationship`, `Path`, `CypherMap`, `CypherList`, plus temporal/point/vector | Client README |
| Compatibility matrix in README: driver `^3.0` → PHP `^8.0`, Neo4j `^4.0, ^5.0` (matrix is older than 3.6.0) | Client README |
| Integration tests talk to `CONNECTION=neo4j://neo4j:testtest@localhost:7687` | `phpunit.xml.dist` |
| Mock surface: `Laudis\Neo4j\Contracts\ClientInterface` | Client source |

**PHP 8.5:** Composer will install 3.6.0 on `^8.5.0`. The package pins `"platform": {"php": "8.1.17"}` for its own CI and does **not** publish an 8.5 compatibility statement. Treat 8.5 as constraint-compatible, not vendor-certified.

**Herd:** Bolt needs `ext-sockets` and (suggested) `ext-bcmath`. Confirm both in Herd before relying on ingest jobs.

### 1.2 HTTP vs Bolt vs Query API

Neo4j owns three HTTP-adjacent surfaces:

1. **Bolt** (binary, port 7687 by default) — what `laudis/neo4j-php-client` implements. Neo4j’s “Create applications” index lists first-party drivers for Python, Go, Java, JDBC, JavaScript, and .NET. PHP is not on that list. The laudis README still states it targets the official driver API and is TestKit-validated.
2. **Transactional HTTP API** — Neo4j documents this as **deprecated in 5.26**, **not available on Aura**, replaced by the Query API. Default ports 7474 (HTTP) / 7473 (HTTPS). Neo4j says: if an official library exists for the language, use that instead.
3. **Query API** (introduced in 5.19, enabled by default; on self-managed `< 5.25` it required `QUERY_API_ENDPOINTS` in `server.http_enabled_modules`). Endpoint:

   `POST http://<host>:<port>/db/<databaseName>/query/v2`

   Body: `{"statement": "...", "parameters": {...}}`. Aura uses HTTPS on 443. Neo4j again says: use an official driver if one exists.

`laudis/neo4j-php-client` 3.3.0 **removed HTTP** and pointed at the Query API. The HTTP successor from the same maintainers is **`neo4j-php/query-api` 1.0.0** (Packagist, 2025-03-12): `php ^8.1`, PSR-18 HTTP, `Neo4jQueryAPI::login('http://localhost:7474', Authentication::basic(...))`. It claims Neo4j `> 5.25` or Aura. Last published **15 months** before laudis 3.6.0 — treat it as a fallback when Bolt is blocked (corporate proxies, Aura HTTP-only constraints), not as the default.

Laravel’s HTTP client (`Illuminate\Support\Facades\Http`) can POST JSON to `/db/{name}/query/v2` if you refuse another package. That is an app-owned adapter, not a Neo4j-supported PHP driver.

**Recommendation:** Bolt via `laudis/neo4j-php-client`. Do not plan on the removed HTTP driver. Use Query API only if Bolt cannot reach the server.

### 1.3 `graphaware/neo4j-php-ogm` (do not use)

Packagist marks the package **`abandoned: true`**. Latest tagged release is `1.0.0-RC10` (2018-02-04). `require.php` is `^5.6 || ^7.0`. It depends on the dead `graphaware/neo4j-php-client` `^4.6` and creates an `EntityManager` against `http://localhost:7474`. That HTTP client and PHP range are incompatible with Laravel 13 / PHP 8.5. Neo4j’s current OGM docs are for the **Java** OGM, not PHP.

`laudis/graphaware-neo4j-php-client-legacy` exists only as a compatibility fork; its own README sends new work to `neo4j-php/neo4j-php-client`.

### 1.4 Apache AGE via `pdo_pgsql` + `cypher()`

AGE is a **PostgreSQL extension**, not a PHP library. Official language drivers listed in the AGE README: Go, Java (JDBC), Node.js, Python. Community: Rust, .NET. **No official PHP driver.**

Packagist search for `apache-age` returns only the unofficial `danny50610/laravel-apache-age-driver` (439 installs). That package is not owned by Apache AGE.

SQL surface (AGE docs):

```sql
CREATE EXTENSION age;
LOAD 'age';
SET search_path = ag_catalog, "$user", public;

SELECT * FROM ag_catalog.create_graph('graph_name');

SELECT * FROM cypher('graph_name', $$
    /* Cypher */
$$) AS (result1 agtype, result2 agtype);
```

`cypher(graph_name, query_string, parameters)` returns `SETOF` records. The third argument is only valid with prepared statements; otherwise AGE errors. Cypher parameters use `$name` (letter-first), not Postgres `$1` inside the Cypher string. The Postgres parameter is the **third** `cypher()` argument and must be an `agtype` map:

```sql
PREPARE cypher_stored_procedure(agtype) AS
SELECT *
FROM cypher('expr', $$
    MATCH (v:Person)
    WHERE v.name = $name
    RETURN v
$$, $1) AS (v agtype);

EXECUTE cypher_stored_procedure('{"name": "Tobias"}');
```

`cypher(...)` is not allowed as a standalone `SELECT` expression; AGE tells you to use a subquery.

Return type is always **`agtype`** (JSON/JSONB-like). Vertices look like `{id, label, properties}::vertex`; edges like `{id, start_id, end_id, label, properties}::edge`. PDO will typically hand these to PHP as strings.

AGE README (master) supports PostgreSQL **11–18** (v1.8.0 badge). The Sphinx “Setup” page still says 11–15 — prefer the GitHub README for the version list.

**Laravel implication:** AGE is the **only** candidate that belongs in `config/database.php`, as a second `pgsql` connection. Session setup (`LOAD 'age'`, `SET search_path`) must run on every connection. AGE’s own README warns that `create_graph` / `create_vlabel` / Cypher writes are transactional catalog/data writes: clients that do not autocommit (psycopg, JDBC, and **Laravel `DB::transaction()` / PDO without autocommit**) will hide graphs from other sessions until `commit()`. Laravel’s `DB` facade transaction helpers apply to that pgsql connection independently of MySQL.

Herd already has `pdo_pgsql`. This workspace’s research index notes PostgreSQL is **not running locally** today.

### 1.5 Kuzu PHP / C bindings

Official Kuzu client APIs: CLI, Python, Node.js, Java, Rust, Go, Swift, C, C++. Community: .NET, Elixir, Ruby, Nim. **PHP is not listed.**

The C API is a native library (`libkuzu.so` / `.dylib` / `.lib`) plus `kuzu.h`. Latest prebuilt example in the C docs: `v0.11.3`. There is no Packagist package owned by `kuzudb`. A Packagist search for `kuzu` does not return an official binding; `crazy-goat/ladybug-php` is a third-party LadybugDB client (1 download) and is **not** a Kuzu binding.

Kuzu persistence (owned by Kuzu docs):

- On-disk: pass a path such as `example.kuzu`.
- In-memory: omit the path, or pass `""` or `:memory:`. No WAL; data dies with the process.

The GitHub repo `kuzudb/kuzu` is **archived (2025-10-10)**. Prior releases remain usable; the official extension server is gone; v0.11.3 bundles some extensions.

**Gaia implication:** a PHP adapter would be FFI or a custom extension over the C API. That fights PHPStan max, Pest type-coverage 100%, and Herd’s stock extensions. Do not pick Kuzu as the Laravel-side driver unless you are willing to own a C binding.

### 1.6 FalkorDB / Redis clients

FalkorDB official docs: property graph, OpenCypher, RESP **and Bolt**, Docker `falkordb/falkordb:latest` on **6379** (server) and **3000** (browser). `GRAPH.QUERY <graph> "<cypher>"` is the CLI/RESP command.

Official PHP client: [`falkordb/falkordb-php`](https://github.com/FalkorDB/falkordb-php), listed on FalkorDB’s client page.

| Fact | Source |
| --- | --- |
| Latest stable **0.2.0** (2026-08-05); `dev-main` updated 2026-08-16 | Packagist |
| `require.php`: `^8.2` | Packagist |
| Connectivity: **phpredis** | README + Packagist description |
| API: `FalkorDB::connect(['host' => '127.0.0.1', 'port' => 6379])` then `selectGraph()`, `query()`, `constraintCreate()` | README |
| Commands: `GRAPH.QUERY`, `GRAPH.RO_QUERY`, `GRAPH.INFO`, `GRAPH.LIST`, `GRAPH.CONSTRAINT`, `GRAPH.UDF` | README |
| Unit tests: `composer qa`. Integration: Docker Compose + `FALKORDB_RUN_INTEGRATION=1` | README / `composer.json` scripts |
| Downloads: 2738 total, 0 dependents | Packagist |
| No PHP OGM; official OGMs are Python, Go, Spring | FalkorDB clients page |

FalkorDB’s clients page also lists **legacy RedisGraph** PHP clients (`kjdev/php-redis-graph`, `jpbourbon/redisgraph_php`) and says they are **not officially tested or supported**.

Laravel Redis (13.x): configure extra connections under `config/database.php` → `redis`, select with `Redis::connection('name')`, or `Redis::command(...)`. That is a key-value client. It can fire `GRAPH.QUERY` only if you speak RESP yourself. Prefer `falkordb-php` for compact-reply parsing (nodes, edges, paths, maps, vectors).

**Do not** point `GRAPH_DRIVER=falkordb` at the same Redis used for Laravel cache/queue (`REDIS_HOST=127.0.0.1:6379` in `.env.example`). FalkorDB is a graph engine that speaks RESP; it is not interchangeable with DBngin Redis.

### 1.7 Client comparison

| Client | Latest | PHP constraint | Protocol | Official? | Maintained? | Fit for Gaia |
| --- | --- | --- | --- | --- | --- | --- |
| `laudis/neo4j-php-client` | 3.6.0 (2026-06) | `^8.1` | Bolt / `neo4j://` | Neo4j-adjacent (TestKit) | Yes | **Default if Neo4j is the store** |
| `neo4j-php/query-api` | 1.0.0 (2025-03) | `^8.1` | HTTP Query API | Same authors | Quiet since 1.0.0 | Fallback only |
| `graphaware/neo4j-php-ogm` | RC10 (2018) | `^5.6 \|\| ^7.0` | HTTP 7474 | Abandoned | No | **No** |
| AGE + `pdo_pgsql` | AGE v1.8.0 / PG 18 | N/A (SQL) | Postgres | AGE owns SQL; no PHP SDK | Yes (C/SQL) | Only if you run Postgres+AGE |
| Kuzu C API | 0.11.3 | C, not PHP | Embedded file / `:memory:` | Official C; no PHP | Repo archived | **No PHP path** |
| `falkordb/falkordb-php` | 0.2.0 (2026-08) | `^8.2` | RESP via phpredis | Official FalkorDB | Young | **Default if FalkorDB is the store** |

---

## 2. Second Laravel connection vs a Graph service

### What Laravel 13 actually supports

Laravel’s first-party databases are MariaDB 10.3+, MySQL 5.7+, PostgreSQL 10+, SQLite 3.26+, SQL Server 2017+. MongoDB is a separate official package. **Graph databases are not a Laravel connection driver.**

Official multi-connection pattern:

1. Define another entry in `config/database.php` → `connections`.
2. Call `DB::connection('sqlite')->select(...)` (name must match config or a runtime `config()` value).
3. Optionally pin an Eloquent model with `#[Connection('mysql')]` or a migration with `protected $connection = 'pgsql'`.
4. `DB::connection()->getPdo()` when you need raw PDO.
5. Redis is a **separate** `config/database.php` → `redis` map, selected with `Redis::connection('name')`.

Gaia already has unused `pgsql` and `redis` blocks in `config/database.php`. Default app connection is MySQL via `.env`; PHPUnit forces `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`.

### What not to do

- Do **not** invent a `neo4j` or `falkordb` driver inside `connections`. Eloquent, the query builder, migrations, `RefreshDatabase`, and `db:show` all assume SQL.
- Do **not** put Person / Identifier vertices on Eloquent models. That fights the dual-store design and the kit’s Action boundary.
- Do **not** share FalkorDB’s port with Laravel’s Redis `default` / `cache` connections.

### What to do

**A. Dedicated Graph service (recommended for Neo4j and FalkorDB)**

Laravel’s container owns the binding:

```php
$this->app->singleton(GraphClient::class, function (Application $app): GraphClient {
    return $app->make(GraphClientFactory::class)->make();
});
```

(`singleton` is the official “resolve once” API; `scoped` if you want a fresh client per Octane request / queue job.)

The factory reads `config/graph.php` and returns a Neo4j or FalkorDB adapter. Actions type-hint `GraphClient`. Controllers never see laudis or FalkorDB types.

**B. Second SQL connection (AGE only)**

Add a dedicated pgsql connection (do **not** reuse `DB_HOST` / `DB_DATABASE` from MySQL):

```php
'age' => [
    'driver' => 'pgsql',
    'host' => env('GRAPH_HOST', '127.0.0.1'),
    'port' => env('GRAPH_PORT', '5432'),
    'database' => env('GRAPH_DATABASE', 'gaia_graph'),
    'username' => env('GRAPH_USERNAME', 'postgres'),
    'password' => env('GRAPH_PASSWORD', ''),
    'charset' => 'utf8',
    'prefix' => '',
    'search_path' => 'ag_catalog,public',
    'sslmode' => 'prefer',
],
```

Then `DB::connection('age')->select(...)` / `statement(...)`. Still wrap AGE behind `GraphClient` so Actions never embed `cypher()` SQL. After connect: `LOAD 'age'` (and `CREATE EXTENSION age` once). Commit `create_graph` outside a leftover MySQL transaction — use the `age` connection’s own `DB::connection('age')->transaction(...)`.

**C. Official Laravel example that already does Neo4j**

The laudis README points at `neo4j-examples/php-laravel-neo4j-realworld-example`. That is a demo app, not a Laravel framework feature. Copy the idea (service + client), not Eloquent-on-Neo4j.

---

## 3. Action map (kit conventions)

Kit facts used here: `app/Actions/CreateUser.php` is `final readonly` with `handle()`; tests `resolve(CreateUser::class)` and assert; `app/Data/UserData.php` is a `final` Spatie `Data` object; `phpstan.neon` is `level: max` plus Larastan and bleeding edge; `composer test:unit` is `pest --parallel --coverage --exactly=100.0`; `test:type-coverage` is `--min=100`.

### Actions (`app/Actions/Graph/`)

| Action | `handle()` responsibility | Writes MySQL? | Writes graph? |
| --- | --- | --- | --- |
| `IngestDump` | Persist dump upload metadata (user, path, status=`queued`); dispatch `ParseDumpChunkJob` | Yes | No |
| `ParseDumpChunk` | Parse one chunk; call `UpsertPerson` / `LinkIdentifier`; update job/chunk status | Yes | Via child Actions |
| `UpsertPerson` | Idempotent MERGE of a Person node + properties | Optional audit row | Yes |
| `LinkIdentifier` | MERGE identifier node; MERGE relationship to Person | Optional | Yes |
| `ExpandNeighborhood` | Read N-hop neighborhood; return `Neighborhood` | No | Read |
| `SearchEntities` | Parameterised search; return `list<GraphNode>` | No | Read |

Each Action receives Spatie Data (or a narrow array already validated by the controller) plus `GraphClient` via the constructor. `handle()` stays the only public method.

Idempotency: laudis `writeTransaction()` **retries**. Generate UUIDs / MERGE keys **outside** the closure, then pass them in — the client README’s own anti-pattern is incrementing an external counter or minting IDs inside the retryable function.

### Jobs (`app/Jobs/`)

| Job | Wraps | Queue notes |
| --- | --- | --- |
| `IngestDumpJob` | `IngestDump` (if ingest is too heavy for the request) | Default `database` queue (`.env.example`) |
| `ParseDumpChunkJob` | `ParseDumpChunk` | Chunked; `ShouldBeUnique` per dump+offset if you add uniqueness later |
| `UpsertPersonJob` | `UpsertPerson` | Only if parse fans out per entity |
| `LinkIdentifierJob` | `LinkIdentifier` | Same |
| `ExpandNeighborhood` / `SearchEntities` | **No job** | Synchronous read from controllers |

Job `handle()` is a one-liner: `resolve(ParseDumpChunk::class)->handle(...)`. That keeps Pest Action tests and Job tests separate (`Queue::fake()` vs Action assertions).

### Data objects (`app/Data/Graph/`)

Mirror `UserData`: `final class … extends Data` with `public readonly` constructor props so TypeScript transformer can emit types.

```php
final class GraphNode extends Data
{
    /**
     * @param  list<string>  $labels
     * @param  array<string, scalar|null>  $properties
     */
    public function __construct(
        public readonly string $id,
        public readonly array $labels,
        public readonly array $properties,
    ) {}
}

final class GraphEdge extends Data
{
    /**
     * @param  array<string, scalar|null>  $properties
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $start_id,
        public readonly string $end_id,
        public readonly array $properties,
    ) {}
}

final class Neighborhood extends Data
{
    /**
     * @param  list<GraphNode>  $nodes
     * @param  list<GraphEdge>  $edges
     */
    public function __construct(
        public readonly GraphNode $origin,
        public readonly array $nodes,
        public readonly array $edges,
    ) {}
}
```

The adapter maps laudis `Node` / `Relationship` (or AGE `agtype` strings, or FalkorDB compact replies) into these DTOs **inside the driver**, so PHPStan never sees `mixed` Cypher values in Actions.

---

## 4. Testing strategy

In-memory SQLite (`phpunit.xml`: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`) cannot host Neo4j, AGE, FalkorDB, or Kuzu. Laravel’s `RefreshDatabase` / database testing docs apply to SQL connections only.

### What the client owners actually do

| Owner | How they test |
| --- | --- |
| `laudis/neo4j-php-client` | PHPUnit 10 suites: Unit, Integration, Performance. Integration uses `CONNECTION=neo4j://neo4j:testtest@localhost:7687`. 3.3.0 re-enabled a **TestKit** backend and Docker-cached GitHub Actions. They do **not** document Testcontainers-PHP as a first-party API. |
| FalkorDB PHP | PHPUnit 11; `composer qa` = lint + **unit**. Integration is opt-in: `docker compose -f docker/standalone-compose.yml up -d` then `FALKORDB_RUN_INTEGRATION=1`. |
| AGE | SQL test suite in the C project; Docker image `apache/age`. PHP would be Laravel hitting that Postgres. |
| Kuzu | Embedded: create a DB at a tmp path or `:memory:` via the C API. Irrelevant until a PHP binding exists. |
| Laravel 13 | `$this->instance(Service::class, Mockery::mock(...))` or Pest `test()` + `Mockery\MockInterface`. `Queue::fake()` / `assertPushed`. Jobs: `withFakeQueueInteractions()`. |

### Pest-friendly split for Gaia

1. **Unit / Feature (default `composer test:unit`)**  
   Bind a Mockery mock of **your** `GraphClient` (not laudis internals) with `$this->mock(GraphClient::class, ...)`. Actions and Jobs stay at 100% coverage without a graph server. Keep PHPUnit’s sqlite env for MySQL-backed tables (users, dumps, jobs).

2. **Opt-in integration** (`tests/Integration/Graph`, Pest group `graph`)  
   Skip unless `GRAPH_INTEGRATION=1`:

   ```php
   pest()->group('graph');

   it('upserts a person', function (): void {
       //
   })->skip(
       fn (): bool => env('GRAPH_INTEGRATION') !== '1',
       'Requires a graph server (see docs/research/05).',
   );
   ```

   Exclude that directory from the default Pest coverage run, **or** do not count it toward `--exactly=100.0`. A skipped integration test that still instantiates a real driver will fail CI when Neo4j is down.

3. **Driver process**  
   - Neo4j: Docker `neo4j` on 7687; set `GRAPH_URI=bolt://neo4j:password@127.0.0.1:7687`. Same idea as laudis `CONNECTION`.  
   - AGE: `docker run apache/age` (AGE docs use host port 5455 → 5432); `GRAPH_DRIVER=age`.  
   - FalkorDB: official compose file + `FALKORDB_RUN_INTEGRATION=1` pattern.  
   - Kuzu: tmp dir `sys_get_temp_dir().'/gaia-'.$this->getName().'.kuzu'` or `:memory:` — only if you own a C/FFI wrapper.

4. **Do not** mock Bolt frames or `GRAPH.QUERY` RESP arrays in every Action test. Mock `GraphClient`. Adapter tests can mock `ClientInterface` (laudis) or FalkorDB’s `Graph` if you need adapter coverage without Docker.

Testcontainers is a reasonable CI wrapper around the same Docker images the clients already assume. No official laudis/FalkorDB/AGE doc names a PHP Testcontainers module as required.

---

## 5. Config / `.env` shape

Add `config/graph.php` (not a fake SQL connection). Keep MySQL vars as they are.

```php
<?php

declare(strict_types=1);

return [
    'driver' => env('GRAPH_DRIVER', 'neo4j'),

    'neo4j' => [
        'uri' => env('GRAPH_URI', 'bolt://127.0.0.1:7687'),
        'database' => env('GRAPH_DATABASE', 'neo4j'),
        'username' => env('GRAPH_USERNAME', 'neo4j'),
        'password' => env('GRAPH_PASSWORD', ''),
        'timeout' => (float) env('GRAPH_TIMEOUT', 5),
    ],

    'falkordb' => [
        'host' => env('GRAPH_HOST', '127.0.0.1'),
        'port' => (int) env('GRAPH_PORT', 6380),
        'password' => env('GRAPH_PASSWORD'),
        'graph' => env('GRAPH_NAME', 'gaia'),
    ],

    'age' => [
        // Used only to document the pairing; the live DSN lives in database.connections.age
        'connection' => env('GRAPH_CONNECTION', 'age'),
        'graph' => env('GRAPH_NAME', 'gaia'),
    ],
];
```

`.env` / `.env.example` sketch:

```dotenv
# Graph system of record (entities + relationships). App DB stays MySQL.
GRAPH_DRIVER=neo4j
GRAPH_URI=bolt://127.0.0.1:7687
GRAPH_DATABASE=neo4j
GRAPH_USERNAME=neo4j
GRAPH_PASSWORD=
GRAPH_TIMEOUT=5

# FalkorDB alternative (separate process from REDIS_HOST)
# GRAPH_DRIVER=falkordb
# GRAPH_HOST=127.0.0.1
# GRAPH_PORT=6380
# GRAPH_NAME=gaia

# AGE alternative (requires a real Postgres + AGE; add database.connections.age)
# GRAPH_DRIVER=age
# GRAPH_CONNECTION=age
# GRAPH_HOST=127.0.0.1
# GRAPH_PORT=5432
# GRAPH_DATABASE=gaia_graph
# GRAPH_USERNAME=postgres
# GRAPH_PASSWORD=
# GRAPH_NAME=gaia

GRAPH_INTEGRATION=0
```

`phpunit.xml` should **not** change `DB_CONNECTION=sqlite`. Optionally add `<env name="GRAPH_DRIVER" value="null"/>` once you have a null/in-memory fake adapter for unit tests.

`config/services.php` is for third-party **HTTP** credentials (Postmark, SES, Slack). Graph connection data belongs in `config/graph.php` the same way SQL belongs in `config/database.php`.

---

## 6. Risks with 100% coverage and PHPStan max

| Risk | Why it is real | Mitigation |
| --- | --- | --- |
| `mixed` from graph results | laudis `CypherMap::get()`, `Node` properties, AGE `agtype` strings, FalkorDB `result->data` are not Laravel-typed. PHPStan max + Pest type-coverage `--min=100` will fail if Actions touch them. | Map to `GraphNode` / `GraphEdge` in the adapter. Annotate `@param array<string, scalar|null>`. |
| Vendor typed with Psalm, not PHPStan | laudis and `neo4j-php/query-api` are Psalm-typed. Larastan will not inherit those generics. | Do not leak vendor types past `GraphClient`. |
| Final + Mockery | Spatie Data and kit Actions are `final`. Mockery cannot mock `final` Actions easily. | Test Actions by constructing them with a mocked `GraphClient`. Do not mock the Action. |
| Coverage of the adapter | `--exactly=100.0` includes `app/`. An untested Bolt adapter tanks CI. | Keep the adapter thin and unit-test it against `ClientInterface`, **or** give it a `NullGraphClient` used in PHPUnit. |
| Integration tests vs `--exactly=100.0` | Hitting Neo4j in the default suite makes coverage depend on Docker. | Group + skip; exclude `tests/Integration` from the coverage gate. |
| laudis retry vs coverage | Retry closures run twice on transient errors; flaky integration coverage. | Keep MERGE keys outside the closure (client README). |
| AGE `agtype` + PDO | AGE is the only type `cypher()` returns. PHPStan sees `string` or `mixed`. | `json_decode` / a dedicated parser behind the adapter; never `select` into Eloquent. |
| AGE + Laravel transactions | Catalog DDL is invisible until commit (AGE README). | Dedicated `age` connection; never nest AGE setup inside the MySQL `DB::transaction()`. |
| Kuzu FFI | PHPStan cannot see C types; FFI is `mixed` everywhere. | Do not take this path under max + 100% type coverage. |
| HTTP Query API vs Bolt | Two clients, two result models, Aura vs self-managed flags. | One `GRAPH_DRIVER`. Do not implement both until needed. |
| FalkorDB 0.2.0 | Official but young (0 dependents). API may move. | Keep `GraphClient` stable; isolate vendor types. |
| PHP 8.5 uncertified | No client publishes 8.5 CI. | Smoke `ClientBuilder::create()->build()` and `verifyConnectivity()` on Herd before ingest. |
| `ext-sockets` / `ext-bcmath` | laudis in-depth requirements list them; Bolt is suggested to need bcmath. | Fail fast in the factory if extensions are missing. |
| Redis collision | `.env.example` already uses `127.0.0.1:6379` for Redis. FalkorDB defaults to 6379. | Put FalkorDB on another port (`6380`) or another host. |
| OGM temptation | Abandoned GraphAware OGM still ranks in search. | Cypher + DTOs, not annotations. |

---

## Action list (implementation order, not this research)

1. Add `config/graph.php` + `.env.example` keys. Leave MySQL default.
2. Add `GraphClient` + `GraphNode` / `GraphEdge` / `Neighborhood`.
3. Implement one adapter (`Neo4jBoltGraphClient` via laudis **or** `FalkorDbGraphClient`).
4. Bind the interface as a singleton in `AppServiceProvider::register()`.
5. Add `UpsertPerson`, `LinkIdentifier`, `ExpandNeighborhood`, `SearchEntities`.
6. Add `IngestDump` / `ParseDumpChunk` + jobs; MySQL for dump/job rows only.
7. Unit-test Actions with a mocked `GraphClient`; keep sqlite PHPUnit env.
8. Add a skipped `graph` Pest group for Docker/Herd integration.

Do not implement in this note.

---

## Citations

Primary sources only. Each claim above is owned by the linked document.

1. Packagist package metadata: `laudis/neo4j-php-client` 3.6.0 — <https://repo.packagist.org/p2/laudis/neo4j-php-client.json>
2. Packagist HTML / README mirror — <https://packagist.org/packages/laudis/neo4j-php-client>
3. Neo4j PHP client README — <https://raw.githubusercontent.com/neo4j-php/neo4j-php-client/main/README.md>
4. Neo4j PHP client `composer.json` — <https://raw.githubusercontent.com/neo4j-php/neo4j-php-client/main/composer.json>
5. Neo4j PHP client `phpunit.xml.dist` — <https://raw.githubusercontent.com/neo4j-php/neo4j-php-client/main/phpunit.xml.dist>
6. `Laudis\Neo4j\Contracts\ClientInterface` — <https://raw.githubusercontent.com/neo4j-php/neo4j-php-client/main/src/Contracts/ClientInterface.php>
7. laudis 3.3.0 release (HTTP removed; TestKit; Query API pointer) — <https://github.com/neo4j-php/neo4j-php-client/releases/tag/3.3.0>
8. Packagist `neo4j-php/query-api` 1.0.0 — <https://repo.packagist.org/p2/neo4j-php/query-api.json> and <https://packagist.org/packages/neo4j-php/query-api>
9. Packagist `graphaware/neo4j-php-ogm` (abandoned, PHP `^5.6 \|\| ^7.0`) — <https://packagist.org/packages/graphaware/neo4j-php-ogm.json>
10. Neo4j HTTP API (deprecated, not on Aura) — <https://neo4j.com/docs/http-api/current/>
11. Neo4j Query API introduction — <https://neo4j.com/docs/query-api/current/>
12. Neo4j Query API `POST /db/<databaseName>/query/v2` — <https://neo4j.com/docs/query-api/current/query/>
13. Apache AGE `cypher()` — <https://age.apache.org/age-manual/master/intro/cypher.html>
14. Apache AGE setup / Docker / `LOAD 'age'` — <https://age.apache.org/age-manual/master/intro/setup.html>
15. Apache AGE graphs (`create_graph`, storage) — <https://age.apache.org/age-manual/master/intro/graphs.html>
16. Apache AGE prepared statements — <https://age.apache.org/age-manual/master/advanced/prepared_statements.html>
17. Apache AGE `agtype` — <https://age.apache.org/age-manual/master/intro/types.html>
18. Apache AGE README (PG 11–18, drivers, non-autocommit) — <https://raw.githubusercontent.com/apache/age/master/README.md>
19. Packagist search `apache-age` (only unofficial Laravel driver) — <https://packagist.org/search.json?q=apache-age>
20. Kuzu docs home — <https://kuzudb.github.io/docs/>
21. Kuzu official client API list (no PHP) — <https://kuzudb.github.io/docs/client-apis/>
22. Kuzu C API — <https://kuzudb.github.io/docs/client-apis/c>
23. Kuzu on-disk vs `:memory:` — <https://kuzudb.github.io/docs/get-started/>
24. `kuzudb/kuzu` archive notice — <https://github.com/kuzudb/kuzu>
25. Packagist search `kuzu` — <https://packagist.org/search.json?q=kuzu>
26. FalkorDB docs (RESP + Bolt, Docker 6379) — <https://docs.falkordb.com/>
27. FalkorDB official clients (includes PHP) — <https://docs.falkordb.com/getting-started/clients>
28. `falkordb/falkordb-php` README — <https://raw.githubusercontent.com/FalkorDB/falkordb-php/main/README.md>
29. Packagist `falkordb/falkordb-php` — <https://packagist.org/packages/falkordb/falkordb-php.json>
30. FalkorDB PHP `composer.json` — <https://raw.githubusercontent.com/FalkorDB/falkordb-php/main/composer.json>
31. Laravel 13.x database (supported engines, `DB::connection`, transactions) — <https://laravel.com/docs/13.x/database>
32. Laravel 13.x Eloquent `#[Connection]` — <https://laravel.com/docs/13.x/eloquent>
33. Laravel 13.x Redis (`Redis::connection`, `command`) — <https://laravel.com/docs/13.x/redis>
34. Laravel 13.x container `singleton` — <https://laravel.com/docs/13.x/container>
35. Laravel 13.x mocking (`instance` + Mockery) — <https://laravel.com/docs/13.x/mocking>
36. Laravel 13.x queues (`Queue::fake`, `withFakeQueueInteractions`) — <https://laravel.com/docs/13.x/queues>
37. Gaia kit conventions — `app/Actions/CreateUser.php`, `app/Data/UserData.php`, `tests/Unit/Actions/CreateUserTest.php`, `phpunit.xml`, `phpstan.neon`, `composer.json`, `config/database.php`, `.env.example`
