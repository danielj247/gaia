# Local graph databases for Gaia

**Date:** 2026-09-12

**Verdict.** For a solo Laravel 13 / PHP 8.5 / Herd setup that must ingest millions of OSINT nodes and edges, keep app state in DBngin MySQL, and later run OpenRouter embedding search, the default local graph store is **Neo4j Community Edition via Homebrew** (or **Neo4j Desktop** if you want a GUI and an Enterprise-for-development license on one machine). It is the only candidate that combines an afternoon macOS install, a Neo4j-documented PHP client that requires PHP 8.1+, first-class Cypher, Community Edition vector indexes, and disk-backed storage with a page cache rather than an all-in-memory graph. **FalkorDB** is the runner-up: official PHP client, one Docker command, OpenCypher plus vector/full-text indexes, and it is the documented successor to RedisGraph — but it must **not** share the already-running Redis on `127.0.0.1:6379`, its default Docker port **conflicts** with that Redis, and it is a Redis-module / sparse-matrix engine whose RAM cost for multi-million OSINT graphs is not stated as a formula. Do not reuse DBngin MySQL as a graph store. RedisGraph is end-of-life.

## Comparison

| Candidate | License | macOS install | Default ports | Query language | PHP / Laravel | Vector index | Bulk ingest | Scale model | RAM / disk (millions) |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Neo4j Community | GPLv3 ([licensing](https://neo4j.com/licensing/)) | Homebrew, TAR, Docker ([osx](https://neo4j.com/docs/operations-manual/current/installation/osx/)) | HTTP 7474, HTTPS 7473, Bolt 7687 ([ports](https://neo4j.com/docs/operations-manual/current/configuration/ports/)) | Cypher 5 / Cypher 25 ([intro](https://neo4j.com/docs/operations-manual/current/introduction/)) | Community client recommended by Neo4j: `laudis/neo4j-php-client`, PHP 8.1+ ([community drivers](https://neo4j.com/docs/getting-started/languages-guides/community-drivers/), [composer.json](https://raw.githubusercontent.com/neo4j-php/neo4j-php-client/main/composer.json)) | Yes, Community and Enterprise ([vector indexes](https://neo4j.com/docs/cypher-manual/current/indexes/semantic-indexes/vector-indexes/)) | `LOAD CSV`; `neo4j-admin database import full` ([import](https://neo4j.com/docs/operations-manual/current/tutorial/neo4j-admin-import/), [LOAD CSV](https://neo4j.com/docs/cypher-manual/current/clauses/load-csv/)) | CE: single instance. Clustering is Enterprise ([editions](https://neo4j.com/docs/operations-manual/current/introduction/)) | Disk + page cache. Dev: 2 GB min, 16 GB recommended ([requirements](https://neo4j.com/docs/operations-manual/current/installation/requirements/)) |
| Neo4j Desktop | Desktop license; includes EE developer license, one machine ([Desktop install](https://neo4j.com/docs/desktop/current/installation/)) | Universal `.dmg` from Deployment Center ([Desktop](https://neo4j.com/docs/desktop/current/installation/)) | Same Bolt/HTTP defaults when the local DBMS is started | Cypher | Same PHP client over Bolt | Same as the bundled EE instance | Same import tools as the bundled DBMS | Single-machine development, not production ([Desktop](https://neo4j.com/docs/desktop/current/installation/)) | Same as Neo4j; JDK downloaded at runtime on macOS ([Desktop](https://neo4j.com/docs/desktop/current/installation/)) |
| Apache AGE | Apache 2.0 ([LICENSE](https://raw.githubusercontent.com/apache/age/master/LICENSE)) | Build against `pg_config`, or `apache/age` Docker ([README](https://github.com/apache/age/blob/master/README.md)) | Postgres 5432; official Docker maps **5455:5432** ([README](https://github.com/apache/age/blob/master/README.md)) | openCypher inside SQL (`cypher()`) ([README](https://github.com/apache/age/blob/master/README.md)) | No official PHP driver. Built-in: Go, Java, Node, Python ([README](https://github.com/apache/age/blob/master/README.md)). Laravel would use `pdo_pgsql` | Native vector index: **not stated** | `load_labels_from_file` / `load_edges_from_file` CSV ([agload](https://age.apache.org/age-manual/master/intro/agload.html)) | Postgres single-node or whatever Postgres HA you add. AGE itself is an extension | Postgres heap/indexes. No AGE-specific million-node RAM formula stated |
| Kuzu | MIT ([install](https://kuzudb.github.io/docs/installation)) | `brew install kuzu`; CLI tarball v0.11.3 ([install](https://kuzudb.github.io/docs/installation)) | Embedded (no server port). Explorer is a separate web GUI ([install](https://kuzudb.github.io/docs/installation)) | Cypher ([README](https://github.com/kuzudb/kuzu)) | **No PHP** on the official install page (Python, Node, Java, Rust, Go, Swift, C/C++, CLI) ([install](https://kuzudb.github.io/docs/installation)) | Yes (`CREATE_VECTOR_INDEX`) ([docs](https://github.com/kuzudb/docs/blob/main/src/content/docs/extensions/vector.mdx)) | `COPY FROM` CSV and Parquet ([CSV](https://kuzudb.github.io/docs/import/csv), [Parquet](https://github.com/kuzudb/docs/blob/main/src/content/docs/import/parquet.md)) | Embedded, single process | Disk-based columnar ([README](https://github.com/kuzudb/kuzu)). **Project archived** ([README](https://github.com/kuzudb/kuzu)) |
| FalkorDB | SSPL v1 ([docs](https://docs.falkordb.com/), [README](https://github.com/FalkorDB/FalkorDB)) | Official path: Docker. Compile from source on macOS with Homebrew build deps ([README](https://github.com/FalkorDB/FalkorDB)) | **6379** (RESP), **3000** (Browser). Optional Bolt via `BOLT_PORT` (default disabled, `-1`) ([docs](https://docs.falkordb.com/), [config](https://docs.falkordb.com/getting-started/configuration)) | OpenCypher + extensions ([docs](https://docs.falkordb.com/)) | Official `falkordb/falkordb-php` via Composer; uses `phpredis` ([clients](https://docs.falkordb.com/getting-started/clients), [README](https://github.com/FalkorDB/falkordb-php)) | Yes (`CREATE VECTOR INDEX`) ([docs index](https://docs.falkordb.com/)) | `LOAD CSV`; `falkordb-bulk-loader` ([docs](https://docs.falkordb.com/)) | Single node, or Redis Cluster that **shards whole graphs**, not nodes inside one graph ([cluster](https://docs.falkordb.com/operations/cluster)) | In-process Redis + sparse matrices. No official million-node RAM formula stated |
| Memgraph | BSL 1.1 (not OSI open source) ([BSL](https://github.com/memgraph/memgraph/blob/master/licenses/BSL.txt)) | Docker, or `curl -sSf "https://install.memgraph.com" \| sh` ([PHP guide](https://memgraph.com/docs/client-libraries/php), [install](https://memgraph.com/docs/getting-started/install-memgraph)) | Bolt **7687**, logs **7444**, Lab **3000** ([Docker](https://memgraph.com/docs/getting-started/install-memgraph/docker)) | Cypher ([PHP guide](https://memgraph.com/docs/client-libraries/php)) | Community Bolt: `stefanak-michal/bolt`, PHP >= 8.1 ([PHP](https://memgraph.com/docs/client-libraries/php)) | Yes (`CREATE VECTOR INDEX`, USearch) ([vector search](https://memgraph.com/docs/querying/vector-search)) | `LOAD CSV`; analytical mode for bulk ([memory](https://memgraph.com/docs/fundamentals/storage-memory-usage)) | Default in-memory single node. Replication/HA documented for Enterprise-style clusters. On-disk mode exists ([memory](https://memgraph.com/docs/fundamentals/storage-memory-usage)) | Official formula ≈ 204 B/vertex + 154 B/edge **plus properties, indexes, embeddings, ~75 MB baseline** ([memory](https://memgraph.com/docs/fundamentals/storage-memory-usage)). Min 1 GB RAM, recommended ≥ 16 GB ([install](https://memgraph.com/docs/getting-started/install-memgraph)) |
| SurrealDB | BSL 1.1 on core (Apache 2.0 after 4 years); DBaaS restricted ([license](https://surrealdb.com/license)) | `brew install surrealdb/tap/surreal` or install script ([macOS](https://surrealdb.com/docs/surrealdb/installation/macos)) | **8000** (`127.0.0.1:8000`) ([start](https://surrealdb.com/docs/surrealdb/cli/start)) | SurrealQL; graph via `RELATE` ([architecture](https://surrealdb.com/docs/surrealdb/introduction/architecture)) | Official `surrealdb/surrealdb.php` ([GitHub](https://github.com/surrealdb/surrealdb.php/)) | HNSW and DISKANN (`DEFINE INDEX … HNSW` / `DISKANN`) ([indexes](https://surrealdb.com/docs/reference/query-language/statements/define/indexes)) | `surreal import`; `--import-file` `.surql` ([start](https://surrealdb.com/docs/surrealdb/cli/start)). Parquet: **not stated** | Embedded, single-node RocksDB, or multi-node (Community can experiment with TiKV; production HA is Cloud Scale / Enterprise) ([architecture](https://surrealdb.com/docs/surrealdb/introduction/architecture), [start](https://surrealdb.com/docs/surrealdb/cli/start)) | RocksDB/SurrealKV on disk, or `memory`. No official million-node graph RAM formula stated |
| ArcadeDB | Apache 2.0 ([README](https://github.com/ArcadeData/arcadedb)) | Docker, GitHub release binaries, embed in JVM ([README](https://github.com/ArcadeData/arcadedb)) | HTTP **2480**; optional Bolt **7687**, Postgres **5432**, Redis **6379**, Mongo **27017** ([client-server](https://arcadedb.com/client-server.html), [README](https://github.com/ArcadeData/arcadedb)) | SQL, OpenCypher, Gremlin, GraphQL ([README](https://github.com/ArcadeData/arcadedb)) | No first-party PHP SDK. HTTP/JSON, or `pdo_pgsql` / Neo4j Bolt drivers ([README](https://github.com/ArcadeData/arcadedb)) | Vector embedding model documented ([README](https://github.com/ArcadeData/arcadedb)) | Dataset import via server setting; gRPC bulk mentioned on site ([README](https://github.com/ArcadeData/arcadedb), [client-server](https://arcadedb.com/client-server.html)). CSV/Parquet specifics: **not stated** in the sources fetched | Single server or HA/Raft cluster ([README](https://github.com/ArcadeData/arcadedb)) | Disk engine; heap sized as % of container memory ([README](https://github.com/ArcadeData/arcadedb)). Million-node formula: **not stated** |
| NebulaGraph | Check the version you install (source-available / commercial split is version-specific; **do not assume Apache 2.0**) | Docker Desktop extension or multi-container Compose ([quick start](https://docs.nebula-graph.io/master/2.quick-start/1.quick-start-workflow/)) | Graph service default **9669** ([quick start](https://docs.nebula-graph.io/master/2.quick-start/1.quick-start-workflow/)) | nGQL (not Cypher) | PHP is a **community** client with **no uptime guarantee** ([clients](https://docs.nebula-graph.io/3.3.0/14.client/1.nebula-client/)) | **Not stated** in the pages fetched | Official import tools exist in the manual; not evaluated here for Laravel | Distributed meta/storage/graph cluster even in the Compose demo | Cluster-oriented. Not an afternoon single-binary fit |

“Not stated” means no first-party number or feature claim was found in the sources cited for that cell.

---

## Can Gaia reuse DBngin MySQL or Redis?

**MySQL: no.** Gaia’s DBngin MySQL 8.4.11 on `127.0.0.1:3306` is the app store (users, sessions, jobs). None of the products above are MySQL engines or MySQL extensions. Apache AGE is a **PostgreSQL** extension, not a MySQL one ([AGE README](https://github.com/apache/age/blob/master/README.md)). Treat MySQL as remaining the Laravel system of record.

**Redis: not as a graph database, and not by dropping FalkorDB onto the existing `6379` instance.**

1. Redis officially ended RedisGraph. End of support was 31 January 2025; the GitHub repo is unmaintained; Redis Stack 7.2.x onward dropped graph capabilities ([RedisGraph EOL](https://redis.io/blog/redisgraph-eol/), [RedisGraph README](https://github.com/RedisGraph/RedisGraph)).
2. FalkorDB’s own docs call it **the successor to RedisGraph** while stressing they are **separate products**. It speaks the Redis protocol (`GRAPH.QUERY` / `GRAPH.RO_QUERY`), can load RedisGraph `dump.rdb` files, and is API-compatible with those commands ([RedisGraph → FalkorDB](https://docs.falkordb.com/operations/migration/redisgraph-to-falkordb), [docs home](https://docs.falkordb.com/)).
3. FalkorDB still **loads as a Redis module**. The project README says Redis **7.4** is required for the latest FalkorDB, and shows `loadmodule …/falkordb.so` or `redis-server --loadmodule` ([FalkorDB README](https://github.com/FalkorDB/FalkorDB)).
4. The official local install is **FalkorDB’s Docker image**, which exposes **6379** and **3000** ([docs](https://docs.falkordb.com/)). That **collides** with Gaia’s already-running Redis on `127.0.0.1:6379`.
5. DBngin documents a native Redis server and custom config, not Redis modules, `MODULE LOAD`, or FalkorDB ([DBngin](https://dbngin.com/)). DBngin’s Redis major version is **not stated** on that page. Do not assume it is Redis 7.4 or that you can safely `MODULE LOAD` into the same process Laravel uses for queues/cache.

**PostgreSQL via DBngin:** DBngin can start Postgres (including recent 18.x builds on macOS) ([DBngin changelog](https://dbngin.com/blog/2018/09/changelogs.html)). AGE’s official install is `make install` against `pg_config` for Postgres 11–18 ([AGE README](https://github.com/apache/age/blob/master/README.md)). DBngin does **not** document `pg_config`, extension headers, or AGE. Compiling AGE into a DBngin Postgres is therefore **not a documented path**. Use Homebrew Postgres with matching `pg_config`, or `docker pull apache/age`.

---

## Neo4j Community / Neo4j Desktop

Neo4j is a native property-graph DBMS with Cypher, ACID transactions, and Bolt ([operations intro](https://neo4j.com/docs/operations-manual/current/introduction/)). Self-managed editions are Community (GPLv3) and Enterprise; Community is documented as a fully functional **single-instance** product. Clustering, online backup, RBAC, and multiple user databases beyond system/default are Enterprise features ([licensing](https://neo4j.com/licensing/), [editions](https://neo4j.com/docs/operations-manual/current/introduction/)).

Current server docs are calendar-versioned; the operations manual fetched for this note is **2026.08**. Homebrew’s documented cellar path on that page is `neo4j/2026.08.1`. Java 21 is required; Java 25 is supported from Neo4j 2025.10 ([osx](https://neo4j.com/docs/operations-manual/current/installation/osx/), [requirements](https://neo4j.com/docs/operations-manual/current/installation/requirements/)). Cypher 5 is frozen as of 2025.06; Cypher 25 receives new language features. From Neo4j 2026.02 the shipped `neo4j.conf` defaults new databases to Cypher 25 ([intro](https://neo4j.com/docs/operations-manual/current/introduction/)).

**Install (Homebrew Community), from the operations manual:**

```bash
brew install neo4j
brew services start neo4j
```

([osx](https://neo4j.com/docs/operations-manual/current/installation/osx/))

**Install (Docker), from the operations manual:**

```bash
docker run --restart always \
  --publish=7474:7474 --publish=7687:7687 \
  --env NEO4J_AUTH=neo4j/your_password \
  neo4j:{neo4j-version-exact}
```

([Docker introduction](https://github.com/neo4j/docs-operations/blob/dev/modules/ROOT/pages/docker/introduction.adoc) via [operations Docker docs](https://neo4j.com/docs/operations-manual/current/installation/osx/))

**Defaults.** User `neo4j`, password `neo4j`, change on first login. Browser: `http://localhost:7474`. Bolt: `bolt://localhost:7687` or `neo4j://localhost:7687` ([osx](https://neo4j.com/docs/operations-manual/current/installation/osx/), [ports](https://neo4j.com/docs/operations-manual/current/configuration/ports/)).

**Desktop.** Free download; Universal macOS `.dmg`; includes an Enterprise Edition **developer** license limited to you, on one machine; not a production deployment. On macOS the DBMS/JDK are downloaded at runtime. Desktop 2.2.1 is listed on the Deployment Center as released 7 July 2026 ([Desktop](https://neo4j.com/docs/desktop/current/installation/), [Deployment Center](https://neo4j.com/deployment-center/?community=&gdb-selfmanaged=)).

**PHP.** Official first-party drivers are .NET, Go, Java, JavaScript, and Python ([editions](https://neo4j.com/docs/operations-manual/current/introduction/)). Neo4j’s getting-started docs **recommend** the community [Neo4j PHP client](https://github.com/neo4j-php/neo4j-php-client) (`laudis/neo4j-php-client`) for Bolt and HTTP, Neo4j 3.5 / 4 / 5+, PHP 7.4 / 8.0+ in the docs table; the current `composer.json` requires **`php: ^8.1`** ([community drivers](https://neo4j.com/docs/getting-started/languages-guides/community-drivers/), [composer.json](https://raw.githubusercontent.com/neo4j-php/neo4j-php-client/main/composer.json)). PHP 8.5 satisfies `^8.1`. Low-level alternative: `stefanak-michal/php-bolt-driver`, PHP 8.1+ ([community drivers](https://neo4j.com/docs/getting-started/languages-guides/community-drivers/)). Laravel queue jobs can hold a Bolt client and run parameterized Cypher; that is application architecture, not a Neo4j-provided Laravel package.

```bash
composer require laudis/neo4j-php-client
```

**Vectors.** Vector indexes are available in Community and Enterprise. Community can index embeddings stored as `LIST` properties. Native `VECTOR` properties need block format (Enterprise / Aura). Indexes are Lucene-backed. Dimensions such as 256, 768, 1536, 3072 are discussed as typical embedding sizes, not as a hard CE cap. Multi-label filtered vector indexes appear in 2026.01; HFQ in 2026.07 ([vector indexes](https://neo4j.com/docs/cypher-manual/current/indexes/semantic-indexes/vector-indexes/)). Vector index pages live in **OS memory**, not the page cache — leave RAM for the OS if you add embeddings ([memory](https://neo4j.com/docs/operations-manual/current/performance/memory-configuration/)).

**Bulk ingest.** Cypher `LOAD CSV` for transactional loads ([LOAD CSV](https://neo4j.com/docs/cypher-manual/current/clauses/load-csv/)). `neo4j-admin database import full` for large CSV into a new/overwritten store; incremental import is documented separately ([import tutorial](https://neo4j.com/docs/operations-manual/current/tutorial/neo4j-admin-import/)). Parquet as a first-class Neo4j import format: **not stated** on those pages.

**RAM / disk.** Personal/dev: 2 GB RAM minimum, 16 GB recommended, 10 GB disk minimum, SSD preferred. Production on-prem: 8 GB RAM minimum. Size page cache to data + native indexes; use `neo4j-admin server memory-recommendation` ([requirements](https://neo4j.com/docs/operations-manual/current/installation/requirements/), [memory](https://neo4j.com/docs/operations-manual/current/performance/memory-configuration/)). This is a disk-backed store with a cache, which is the right shape for “millions, not billions” on a laptop if the working set is indexed and the page cache is tuned. A hard “N million nodes fit in X GB” number is **not stated**.

---

## Apache AGE (Postgres extension)

AGE (A Graph Extension) adds a property graph and openCypher to PostgreSQL so SQL and Cypher share one store ([README](https://github.com/apache/age/blob/master/README.md)). License is Apache 2.0 ([LICENSE](https://raw.githubusercontent.com/apache/age/master/LICENSE)). GitHub README (current) supports **Postgres 11–18**; latest badge on that README is v1.8.0 / Postgres 18. The AGE manual setup page still says 11–15 — treat the **repository README / releases** as newer ([README](https://github.com/apache/age/blob/master/README.md), [setup](https://age.apache.org/age-manual/master/intro/setup.html), [releases](https://github.com/apache/age/releases)).

**Install on macOS**, official paths: clone or download a release, then `make install` or `make PG_CONFIG=/path/to/pg_config install` ([README](https://github.com/apache/age/blob/master/README.md)). There is no official Homebrew formula for AGE itself in those docs. Docker:

```bash
docker pull apache/age
docker run --name age \
  -p 5455:5432 \
  -e POSTGRES_USER=postgresUser \
  -e POSTGRES_PASSWORD=postgresPW \
  -e POSTGRES_DB=postgresDB \
  -d apache/age
docker exec -it age psql -d postgresDB -U postgresUser
```

([README](https://github.com/apache/age/blob/master/README.md))

Per session:

```sql
CREATE EXTENSION age;
LOAD 'age';
SET search_path = ag_catalog, "$user", public;
SELECT create_graph('graph_name');
```

([README](https://github.com/apache/age/blob/master/README.md), [setup](https://age.apache.org/age-manual/master/intro/setup.html))

Default Docker credentials are whatever you set in `POSTGRES_*`. Host port **5455** avoids colliding with a future DBngin Postgres on 5432.

**PHP.** Official drivers: Go, JDBC, Node, Python. Community: Rust, .NET. **No PHP** ([README](https://github.com/apache/age/blob/master/README.md)). Herd already has `pdo_pgsql`, so Laravel can `SELECT * FROM cypher(...)` over PDO. That is a workable but self-built client story (type mapping to `agtype`, transaction caveats for non-autocommit clients) ([README](https://github.com/apache/age/blob/master/README.md)).

**Vectors.** AGE lists Cypher, hybrid SQL/Cypher, labels, and property indexes — not a vector/HNSW index ([README](https://github.com/apache/age/blob/master/README.md)). You could theoretically add `pgvector` on the same Postgres; that pairing is **not stated** in AGE docs.

**Bulk ingest.** CSV via `load_labels_from_file` / `load_edges_from_file` after creating graph and labels ([agload](https://age.apache.org/age-manual/master/intro/agload.html)). JSON/Parquet: **not stated** on that page.

**Scale / RAM.** You inherit Postgres. Horizontal graph sharding is **not stated** as an AGE feature. No official AGE formula for multi-million graphs. DBngin Postgres is **not** a documented AGE target (see above).

---

## Kuzu (embedded)

Kuzu is an embedded, serverless property-graph engine: Cypher, columnar disk storage, CSR adjacency, vectorized execution, ACID, full-text and vector indexes, Wasm ([README](https://github.com/kuzudb/kuzu)). License: MIT ([install](https://kuzudb.github.io/docs/installation)).

**Critical status.** The official GitHub README states the project is **archived**. Prior releases remain usable; docs moved to `https://kuzudb.github.io/docs`. Latest documented stable CLI/bindings in the install page: **v0.11.3**. The official extension server is gone; 0.11.3 bundles `algo`, `fts`, `json`, `vector` ([README](https://github.com/kuzudb/kuzu), [install](https://kuzudb.github.io/docs/installation)).

**Install (macOS):**

```bash
brew install kuzu
kuzu
```

Or:

```bash
curl -L -O https://github.com/kuzudb/kuzu/releases/download/v0.11.3/kuzu_cli-osx-universal.tar.gz
tar xzf kuzu_cli-osx-universal.tar.gz
./kuzu
```

Bindings: `pip install kuzu`, `npm install kuzu`, Maven `com.kuzudb:kuzu:0.11.3`, `cargo add kuzu`, `go get github.com/kuzudb/go-kuzu@v0.11.3`, Swift package, C/C++ tarball ([install](https://kuzudb.github.io/docs/installation)). **PHP is absent.**

**Ports.** None for the engine (in-process). Kuzu Explorer is a separate web GUI ([install](https://kuzudb.github.io/docs/installation)).

**Vectors / ingest.** `CALL CREATE_VECTOR_INDEX(...)` with metrics such as cosine / L2 ([vector docs](https://github.com/kuzudb/docs/blob/main/src/content/docs/extensions/vector.mdx)). `COPY table FROM "file.csv"` and Parquet; gzipped CSV is supported; nodes must be loaded before relationships ([CSV](https://kuzudb.github.io/docs/import/csv)).

**Scale.** Single-process embedded. Horizontal cluster: **not stated**. Disk-oriented design is attractive for millions on a laptop, but an archived engine is a poor default for a new product. A community fork (RyuGraph) exists in third-party indexes; it is **not** evaluated here as a primary-source successor.

Laravel would have to shell out to the CLI, wrap the C API, or run a sidecar in Python/Node — none of that is first-party PHP.

---

## FalkorDB

FalkorDB is a property-graph database implemented as a **Redis module**, using sparse adjacency matrices and GraphBLAS ([README](https://github.com/FalkorDB/FalkorDB), [docs](https://docs.falkordb.com/)). License: **SSPLv1** ([docs](https://docs.falkordb.com/)). It is the documented **successor to RedisGraph**, fully compatible with RedisGraph RDB files and `GRAPH.QUERY` / `GRAPH.RO_QUERY` clients, but a separate product ([migration](https://docs.falkordb.com/operations/migration/redisgraph-to-falkordb)).

**Install (official local):**

```bash
docker run -p 6379:6379 -p 3000:3000 -it --rm \
  -v ./data:/var/lib/falkordb/data \
  falkordb/falkordb
```

Browser: `http://localhost:3000`. For Gaia, **do not bind 6379** — use another host port, e.g. `-p 6380:6379 -p 3000:3000`, and point the PHP client at 6380 ([docs](https://docs.falkordb.com/), [README](https://github.com/FalkorDB/FalkorDB)).

Password example from official compose/docs:

```bash
docker run -p 6380:6379 -p 3000:3000 -it --rm \
  -e REDIS_ARGS="--requirepass falkordb" \
  -e FALKORDB_ARGS="THREAD_COUNT 4" \
  falkordb/falkordb:latest
```

([configuration](https://docs.falkordb.com/getting-started/configuration), [docs compose snippet](https://docs.falkordb.com/))

Default Docker has **no password** unless you set `REDIS_ARGS`. Compiling on macOS: Homebrew `cmake m4 automake peg libtool autoconf`, plus GCC/OpenMP because Apple Clang lacks OpenMP ([README](https://github.com/FalkorDB/FalkorDB)). That is not an afternoon path compared with Docker.

**Query / protocols.** OpenCypher subset plus extensions. Primary protocol is RESP. Bolt is optional (`BOLT_PORT`, default `-1` / off) ([docs](https://docs.falkordb.com/), [configuration](https://docs.falkordb.com/getting-started/configuration)).

**PHP.** Official `falkordb-php` (MIT), Composer `falkordb/falkordb-php`, connectivity via **phpredis** — compatible with Herd’s `redis` extension ([clients](https://docs.falkordb.com/getting-started/clients), [falkordb-php](https://github.com/FalkorDB/falkordb-php)):

```bash
composer require falkordb/falkordb-php
```

```php
$db = FalkorDB\FalkorDB::connect(['host' => '127.0.0.1', 'port' => 6380]);
$graph = $db->selectGraph('osint');
```

([falkordb-php README](https://github.com/FalkorDB/falkordb-php))

Older RedisGraph PHP clients are listed as untested community leftovers ([clients](https://docs.falkordb.com/getting-started/clients)).

**Vectors / ingest.** `CREATE VECTOR INDEX … OPTIONS {dimension:…, similarityFunction:…}` ([docs](https://docs.falkordb.com/)). Full-text and range indexes are first-class ([docs](https://docs.falkordb.com/)). `LOAD CSV FROM 'file://…'`. Bulk: `pip install falkordb-bulk-loader` then `falkordb-bulk-insert GRAPHNAME -n nodes.csv -r edges.csv` ([docs](https://docs.falkordb.com/)). Parquet: **not stated**. CSV files must live under `IMPORT_FOLDER` (default `/var/lib/FalkorDB/import/`) ([configuration](https://docs.falkordb.com/getting-started/configuration)).

**Scale.** Redis Cluster mode shards the **keyspace**. Each graph is **one Redis key** and lives entirely on one shard. Clustering spreads *different graphs*, not one OSINT graph’s nodes ([cluster](https://docs.falkordb.com/operations/cluster)). For one large people-graph, you are on a **single node**.

**RAM.** Graphs are sparse matrices inside the Redis process. `NODE_CREATION_BUFFER` (default 16,384, min 128) trades RAM vs resize frequency. `QUERY_MEM_CAPACITY` can cap per-query bytes (default unlimited) ([configuration](https://docs.falkordb.com/getting-started/configuration)). A published “N million nodes → X GB” figure is **not stated**. Persistence follows Redis (RDB shown in the migration guide) ([migration](https://docs.falkordb.com/operations/migration/redisgraph-to-falkordb)).

---

## Memgraph

Memgraph is a Cypher / Bolt graph DBMS, default **in-memory transactional** storage, with optional `IN_MEMORY_ANALYTICAL` (faster import, no ACID except manual snapshots) and `ON_DISK_TRANSACTIONAL` ([storage](https://memgraph.com/docs/fundamentals/storage-memory-usage)). License for Community Edition is **Business Source License 1.1** (amended 1 January 2026). The BSL text says it is **not an open-source license**; production use is limited to an “Authorised Purpose” (internal business, no offering it as a hosted standalone service, no competing product) ([BSL](https://github.com/memgraph/memgraph/blob/master/licenses/BSL.txt)). Marketing pages still say “open source”; the license file is the source of truth.

**Install (macOS), official:**

```bash
curl -sSf "https://install.memgraph.com" | sh
```

That script pulls Docker Compose for `memgraph-mage` + Lab ([PHP guide](https://memgraph.com/docs/client-libraries/php), [getting started](https://memgraph.com/docs/getting-started)). Direct Docker:

```bash
docker run -p 7687:7687 -p 7444:7444 --name memgraph memgraph/memgraph-mage
docker run -d -p 3000:3000 --name lab memgraph/lab
```

Lab: `http://localhost:3000`. On Mac, Lab in Docker should use `host.docker.internal` to reach a host-published Bolt ([Docker](https://memgraph.com/docs/getting-started/install-memgraph/docker)). Optional auth:

```bash
docker run -p 7687:7687 -p 7444:7444 \
  -e MEMGRAPH_USER=newUser -e MEMGRAPH_PASSWORD=pass \
  memgraph/memgraph
```

([configuration settings via Memgraph docs](https://memgraph.com/docs/configuration/configuration-settings))

**Defaults.** Bolt `localhost:7687`. PHP quick start authenticates with `scheme => none` (no credentials) ([PHP](https://memgraph.com/docs/client-libraries/php)). Homebrew formula: **not stated** on the official install pages fetched; Docker is the documented macOS path.

**PHP.** Community `stefanak-michal/bolt`, PHP >= 8.1, Composer `composer require stefanak-michal/bolt`. Same Bolt stack Neo4j PHP uses under the hood. Wrapper: `stefanak-michal/memgraph-bolt-wrapper` ([PHP](https://memgraph.com/docs/client-libraries/php)).

**Vectors.** `CREATE VECTOR INDEX` / `CREATE VECTOR EDGE INDEX`; search via `vector_search.search()`; backend USearch; isolation for the vector index is `READ_UNCOMMITTED` while the DB remains ACID otherwise ([vector search](https://memgraph.com/docs/querying/vector-search)). GraphRAG docs warn embeddings inflate RAM (historically stored in the property store and again in the index; a reference-based layout is described as the direction) ([GraphRAG](https://memgraph.com/docs/deployment/workloads/memgraph-in-graphrag)).

**Bulk ingest.** `LOAD CSV`. Analytical mode plus `EDGE IMPORT MODE` for dense imports ([storage](https://memgraph.com/docs/fundamentals/storage-memory-usage)). Parquet: **not stated** on that page.

**RAM.** Official in-memory transactional estimate:

`StorageRAMUsage ≈ vertices × 204 B + edges × 154 B`

plus property bytes, indexes, deltas, query memory, and ~75 MB empty-process overhead on their x86 Ubuntu example. Vector floats were documented as 8 B in the property store + 4 B in usearch (12 B/dimension overhead) in the GraphRAG note ([storage](https://memgraph.com/docs/fundamentals/storage-memory-usage), [GraphRAG](https://memgraph.com/docs/deployment/workloads/memgraph-in-graphrag)). Worked implication (formula only, not a benchmark): 5 million nodes + 20 million edges ≈ 1.0 GB + 3.1 GB ≈ **4.1 GB** storage before properties and embeddings. System table: minimum 1 GB RAM, recommended ≥ 16 GB ECC; disk at least 3× RAM for snapshots ([install](https://memgraph.com/docs/getting-started/install-memgraph)). On-disk mode exists but switching from in-memory to on-disk requires an empty DB ([storage](https://memgraph.com/docs/fundamentals/storage-memory-usage)).

**Scale.** Horizontal: add cores / replicas as documented; Community vs Enterprise feature split for HA is license-gated (`STORAGE_MODE` permission called out for Enterprise) ([storage](https://memgraph.com/docs/fundamentals/storage-memory-usage)). Not a shared-nothing shard of one graph in the Community docs fetched.

---

## SurrealDB graph features

SurrealDB is a **multi-model** Rust database: documents, graph edges (`RELATE`), vectors, full-text, time series, geo, relational tables, one SurrealQL, one transaction ([architecture](https://surrealdb.com/docs/surrealdb/introduction/architecture), [what is SurrealDB](https://surrealdb.com/docs/surrealdb/installation/running/start-surrealdb)). Graph edges are ordinary records with `in` / `out` ([RELATE](https://surrealdb.com/docs/learn/data-models/graph/creating-relations)). This is not a Neo4j-style dedicated graph engine.

**License.** Core: BSL 1.1. Free to use and embed, including production, except offering a commercial DBaaS. Each release converts to Apache 2.0 after four years. SDKs: Apache 2.0 or MIT ([license FAQs](https://surrealdb.com/license)).

**Install (macOS):**

```bash
brew install surrealdb/tap/surreal
# or
curl -sSf https://install.surrealdb.com | sh
```

Docs at fetch time mentioned latest stable **v3.2.4** on that page ([macOS](https://surrealdb.com/docs/surrealdb/installation/macos)).

**Start (persistent local):**

```bash
surreal start rocksdb:./gaia-graph --user root --pass secret
```

Bind default `127.0.0.1:8000`. Omitting the path starts **in-memory** (`memory`) ([start](https://surrealdb.com/docs/surrealdb/cli/start)). Docker:

```bash
docker run --pull always -p 8000:8000 surrealdb/surrealdb:latest \
  start --user root --pass secret rocksdb:/data
```

(pattern from [docs Docker](https://surrealdb.com/docs/surrealdb/installation/macos) / [start](https://surrealdb.com/docs/surrealdb/cli/start))

**PHP.** Official SDK:

```bash
composer require surrealdb/surrealdb.php
```

Connect HTTP `http://localhost:8000` or WebSocket `ws://127.0.0.1:8000/rpc` ([surrealdb.php](https://github.com/surrealdb/surrealdb.php/)). A v2 alpha (`2.0.0-alpha.1`) appears in Context7-indexed docs; the GitHub README still shows the stable Composer package. Confirm packagist stability before pinning.

**Vectors.** `DEFINE INDEX … HNSW DIMENSION n DIST COSINE|EUCLIDEAN|MANHATTAN` (in-memory hot graph, default 256 MiB shared cache via `SURREAL_HNSW_CACHE_SIZE`). `DISKANN` from 3.1+ for corpora that should not hold the full HNSW graph in RAM ([DEFINE INDEX](https://surrealdb.com/docs/reference/query-language/statements/define/indexes), [vector indexes](https://surrealdb.com/docs/learn/data-models/vector-search/vector-indexes)). Hybrid RRF of text + vector is documented ([hybrid search](https://surrealdb.com/docs/learn/data-models/vector-search/hybrid-search)).

**Bulk ingest.** `surreal import` and `--import-file` for SurrealQL. CSV/JSON/Parquet as native bulk graph loaders: **not stated** on the start/import flags fetched.

**Scale.** Same binary: embedded, single-node RocksDB (recommended single-node production in the architecture table), SurrealKV (beta), or distributed storage on Cloud Scale / Enterprise. Community TiKV is for local multi-node *experimentation*; FoundationDB is deprecated in 3.0 ([architecture](https://surrealdb.com/docs/surrealdb/introduction/architecture), [start](https://surrealdb.com/docs/surrealdb/cli/start)).

**Fit.** Strong if Gaia wanted one multi-model server instead of MySQL + graph. Weak as a drop-in people-graph: different query language, graph is “edges as records,” and OSINT Cypher / Neo4j tooling (Browser, Bloom, AGE Viewer, Memgraph Lab) does not apply.

---

## Other local options (official sources only)

### ArcadeDB

Apache 2.0 multi-model graph/document engine from the OrientDB founder. Docker in minutes; Studio on **2480**; optional Bolt (so `laudis` *might* work — Bolt compatibility is advertised, not PHP-tested by ArcadeDB), Postgres wire (Herd `pdo_pgsql`), HTTP/JSON from any language ([README](https://github.com/ArcadeData/arcadedb), [client-server](https://arcadedb.com/client-server.html)).

```bash
docker run --rm -p 2480:2480 \
  -e ARCADEDB_SETTINGS="-Darcadedb.server.rootPassword=playwithdata" \
  arcadedata/arcadedb:latest
```

Root password is **required** (`playwithdata` in the official example). Opening Bolt **7687** or Redis **6379** would collide with Neo4j/Memgraph or Gaia Redis unless remapped ([README](https://github.com/ArcadeData/arcadedb), [client-server](https://arcadedb.com/client-server.html)). Vector embeddings and Cypher are documented. No official PHP SDK. Homebrew was mentioned in ArcadeDB “binaries” docs in search results; the live fetch of that URL returned 409, so **do not treat `brew install arcadedb` as verified** here — use Docker or [GitHub Releases](https://github.com/ArcadeData/arcadedb/releases). Serious and installable, but a smaller PHP/Laravel trail than Neo4j or FalkorDB.

### NebulaGraph

Installable on macOS via Docker Desktop extension or `nebula-docker-compose` (meta + storage + graph containers) ([quick start](https://docs.nebula-graph.io/master/2.quick-start/1.quick-start-workflow/)). Query language is **nGQL**, not Cypher. Official clients: C++, Java, Python, Go. PHP is community, no uptime guarantee ([clients](https://docs.nebula-graph.io/3.3.0/14.client/1.nebula-client/)). This is a distributed cluster product. Not a realistic afternoon default for a solo Herd app.

### Not recommended / out of scope

- **RedisGraph** — EOL ([EOL post](https://redis.io/blog/redisgraph-eol/)).
- **JanusGraph, Dgraph, TypeDB, TigerGraph, Amazon Neptune** — not evaluated as simple local Homebrew/Desktop/single-image options for this note.
- **RyuGraph** — third-party Kuzu fork; not a primary-source continuation of Kùzu Inc.

---

## Ranked recommendation

### 1. Default: Neo4j Community Edition (Homebrew)

**Why this wins for Gaia**

| Need | Why Neo4j CE |
| --- | --- |
| Afternoon macOS DX | `brew install neo4j && brew services start neo4j`, or Desktop `.dmg` ([osx](https://neo4j.com/docs/operations-manual/current/installation/osx/), [Desktop](https://neo4j.com/docs/desktop/current/installation/)) |
| PHP 8.5 | Neo4j-documented client, Composer `php: ^8.1` ([community drivers](https://neo4j.com/docs/getting-started/languages-guides/community-drivers/), [composer.json](https://raw.githubusercontent.com/neo4j-php/neo4j-php-client/main/composer.json)) |
| Laravel queue ingest | Jobs open Bolt (`7687`), run parameterized Cypher, no conflict with MySQL `3306` or Redis `6379` |
| Later OpenRouter embeddings | Community vector indexes on `LIST` properties ([vector indexes](https://neo4j.com/docs/cypher-manual/current/indexes/semantic-indexes/vector-indexes/)) |
| Millions of nodes/edges | Disk store + page cache; CE is explicitly the single-instance edition ([editions](https://neo4j.com/docs/operations-manual/current/introduction/), [memory](https://neo4j.com/docs/operations-manual/current/performance/memory-configuration/)) |
| OSINT admin / viz | Browser at `:7474`; Aura console can attach to local Bolt for Query/Explore ([osx](https://neo4j.com/docs/operations-manual/current/installation/osx/)) |

**How to run it.** Keep MySQL for Laravel. Add Neo4j as a second datastore. Ingest dumps with `neo4j-admin database import full` (offline CSV) or queued `LOAD CSV` / batched `UNWIND` from PHP. Desktop is optional if you want EE-only toys (multi-DB, RBAC) on one Mac under the Desktop developer license — still not a production cluster ([Desktop](https://neo4j.com/docs/desktop/current/installation/)).

**Cost of this choice.** GPL v3 on the server. No HA in Community. PHP driver is community-maintained (but Neo4j-endorsed). JVM + 16 GB RAM recommended for comfortable dev ([requirements](https://neo4j.com/docs/operations-manual/current/installation/requirements/)).

### 2. Runner-up: FalkorDB (Docker, remapped port)

**Why second.** Official PHP client on phpredis (already in Herd), one Docker image, OpenCypher, vector + full-text in-engine, GraphRAG positioning that matches “embed person/org/note text later,” and it is the living RedisGraph line ([clients](https://docs.falkordb.com/getting-started/clients), [docs](https://docs.falkordb.com/)).

**Why not default.** SSPL. Default **6379** fights Gaia Redis — always publish `6380:6379` (or similar). The graph is one Redis key in RAM-backed matrices; cluster will not split one OSINT graph ([cluster](https://docs.falkordb.com/operations/cluster)). No official RAM formula for millions of nodes plus 1536-d embeddings. Cannot treat DBngin Redis as the host without an undocumented module load onto Redis 7.4.

### If the default is rejected

- **Memgraph** — closest Cypher/Bolt twin to Neo4j; same PHP Bolt family; Docker/script install; vector indexes. Default in-memory: plan RAM with their 204 B / 154 B formula **plus** embedding duplication. BSL, not GPL. Port **7687** collides if Neo4j is also running ([PHP](https://memgraph.com/docs/client-libraries/php), [storage](https://memgraph.com/docs/fundamentals/storage-memory-usage)).
- **Apache AGE + Homebrew Postgres or `apache/age` Docker** — only if you want Cypher *and* SQL on one disk engine and accept no official PHP driver and no native vectors. Do not assume DBngin Postgres is enough ([README](https://github.com/apache/age/blob/master/README.md)).
- **SurrealDB** — best official PHP SDK after FalkorDB, Homebrew binary, HNSW/DISKANN. Choose this only if Gaia is willing to be SurrealQL-native rather than Cypher-native ([macOS](https://surrealdb.com/docs/surrealdb/installation/macos), [surrealdb.php](https://github.com/surrealdb/surrealdb.php/)).
- **Kuzu** — technically a strong embedded analytics engine (CSV/Parquet/vector, MIT, `brew install kuzu`) but **archived** and **no PHP** ([README](https://github.com/kuzudb/kuzu)).
- **ArcadeDB** — Apache 2.0, Docker, HTTP + optional Bolt/Postgres. Viable experiment; thinner Laravel evidence ([README](https://github.com/ArcadeData/arcadedb)).

---

## Concrete install cheat-sheet

### Neo4j Community (recommended)

```bash
brew install neo4j
brew services start neo4j
# Browser: http://localhost:7474
# Bolt:    bolt://127.0.0.1:7687
# User:    neo4j
# Pass:    neo4j   (must change on first login)
```

([osx](https://neo4j.com/docs/operations-manual/current/installation/osx/))

```bash
composer require laudis/neo4j-php-client
```

([community drivers](https://neo4j.com/docs/getting-started/languages-guides/community-drivers/))

Docker alternative (set a real password):

```bash
docker run --restart always \
  --publish=7474:7474 --publish=7687:7687 \
  --env NEO4J_AUTH=neo4j/change-me \
  --volume=$HOME/neo4j/data:/data \
  neo4j:2026.08.1
```

Version tag should match the [Deployment Center](https://neo4j.com/deployment-center/?community=&gdb-selfmanaged=) / Homebrew formula; `2026.08.1` is the version named in the current macOS operations page ([osx](https://neo4j.com/docs/operations-manual/current/installation/osx/)).

### FalkorDB (runner-up; avoid port 6379)

```bash
docker run -d --name falkordb \
  -p 6380:6379 -p 3000:3000 \
  -v $HOME/falkordb/data:/var/lib/falkordb/data \
  -e REDIS_ARGS="--requirepass falkordb" \
  falkordb/falkordb:latest
# Browser: http://localhost:3000
# RESP:    127.0.0.1:6380
# Auth:    requirepass falkordb  (example from official docs; change it)
```

([docs](https://docs.falkordb.com/), [configuration](https://docs.falkordb.com/getting-started/configuration))

```bash
composer require falkordb/falkordb-php
```

([falkordb-php](https://github.com/FalkorDB/falkordb-php))

### Memgraph (optional third)

```bash
curl -sSf "https://install.memgraph.com" | sh
# or
docker run -p 7687:7687 -p 7444:7444 --name memgraph memgraph/memgraph-mage
# Bolt: localhost:7687  auth scheme none unless MEMGRAPH_USER/PASSWORD set
# Lab:  docker run -d -p 3000:3000 --name lab memgraph/lab
```

([PHP](https://memgraph.com/docs/client-libraries/php), [Docker](https://memgraph.com/docs/getting-started/install-memgraph/docker))

### Apache AGE (Docker, not DBngin)

```bash
docker run --name age -p 5455:5432 \
  -e POSTGRES_USER=postgresUser \
  -e POSTGRES_PASSWORD=postgresPW \
  -e POSTGRES_DB=postgresDB \
  -d apache/age
```

([README](https://github.com/apache/age/blob/master/README.md))

### SurrealDB

```bash
brew install surrealdb/tap/surreal
surreal start rocksdb:./gaia-graph --user root --pass secret
# HTTP: 127.0.0.1:8000
```

([macOS](https://surrealdb.com/docs/surrealdb/installation/macos), [start](https://surrealdb.com/docs/surrealdb/cli/start))

### Kuzu (only if you accept an archived embedded engine)

```bash
brew install kuzu
```

([install](https://kuzudb.github.io/docs/installation))

---

## Open questions / risks

1. **`laudis/neo4j-php-client` vs Neo4j 2026.08 / Cypher 25 / PHP 8.5.** Docs say Neo4j 5.0+ and the package requires PHP `^8.1`. Explicit CI for 8.5 and server 2026.08 is **not stated**. Smoke-test Bolt handshake and `CYPHER 25` before ingest.
2. **Community Edition limits for “massive” OSINT.** CE is single-instance; store-format headroom (billions) appears in the editions table but checkmarks did not survive HTML extraction — treat “34 billion” as **not independently confirmed for CE** from that table. Millions are within the product’s stated purpose; billions are out of Gaia’s brief anyway.
3. **Vector RAM.** Neo4j vectors sit in OS RAM, not page cache ([memory](https://neo4j.com/docs/operations-manual/current/performance/memory-configuration/)). OpenRouter embeddings (often 1536+ dims) can dominate laptop memory regardless of engine. No first-party “embeddings × N nodes” budget is published for Neo4j Community.
4. **FalkorDB SSPL + Redis port.** Legal review of SSPL for Gaia’s use; operational rule: never publish FalkorDB on 6379 while DBngin Redis is there.
5. **Memgraph BSL “Authorised Purpose”.** Internal solo/admin use likely fits; hosting Memgraph as a service would not ([BSL](https://github.com/memgraph/memgraph/blob/master/licenses/BSL.txt)). Confirm before any multi-tenant deploy.
6. **AGE + DBngin.** Undocumented. If AGE is ever chosen, use Homebrew Postgres or `apache/age`, then point Laravel `pgsql` at that instance — not at MySQL.
7. **Kuzu archive.** Do not build Gaia’s source of truth on an archived embedded DB unless a maintained fork is adopted later, with eyes open.
8. **No benchmarks in this note.** Throughput, ingest hours, and “fits in 32 GB” claims for a specific OSINT dump are **not stated** by vendors as apples-to-apples numbers and were not invented here. Validate with a sample dump after install.
9. **Herd / Docker Desktop.** Neo4j Homebrew talks to `localhost` from PHP with no container DNS issues. FalkorDB/Memgraph/AGE in Docker need published ports and, from other containers, `host.docker.internal`.
10. **Dual-store consistency.** MySQL remains users/sessions/jobs. Graph ingest from Laravel queues must define its own idempotency (OSINT dump IDs). That is architecture, not a database feature.

---

## Source index

Primary pages used (all first-party or project GitHub):

- [Neo4j licensing](https://neo4j.com/licensing/)
- [Neo4j editions / intro](https://neo4j.com/docs/operations-manual/current/introduction/)
- [Neo4j macOS install](https://neo4j.com/docs/operations-manual/current/installation/osx/)
- [Neo4j ports](https://neo4j.com/docs/operations-manual/current/configuration/ports/)
- [Neo4j system requirements](https://neo4j.com/docs/operations-manual/current/installation/requirements/)
- [Neo4j memory](https://neo4j.com/docs/operations-manual/current/performance/memory-configuration/)
- [Neo4j admin import](https://neo4j.com/docs/operations-manual/current/tutorial/neo4j-admin-import/)
- [Neo4j LOAD CSV](https://neo4j.com/docs/cypher-manual/current/clauses/load-csv/)
- [Neo4j vector indexes](https://neo4j.com/docs/cypher-manual/current/indexes/semantic-indexes/vector-indexes/)
- [Neo4j community drivers (PHP)](https://neo4j.com/docs/getting-started/languages-guides/community-drivers/)
- [laudis/neo4j-php-client composer.json](https://raw.githubusercontent.com/neo4j-php/neo4j-php-client/main/composer.json)
- [Neo4j Desktop install](https://neo4j.com/docs/desktop/current/installation/)
- [Apache AGE README](https://github.com/apache/age/blob/master/README.md)
- [Apache AGE LICENSE](https://raw.githubusercontent.com/apache/age/master/LICENSE)
- [Apache AGE setup](https://age.apache.org/age-manual/master/intro/setup.html)
- [Apache AGE load CSV](https://age.apache.org/age-manual/master/intro/agload.html)
- [Kuzu README (archived notice)](https://github.com/kuzudb/kuzu)
- [Kuzu install](https://kuzudb.github.io/docs/installation)
- [Kuzu CSV import](https://kuzudb.github.io/docs/import/csv)
- [FalkorDB docs](https://docs.falkordb.com/)
- [FalkorDB README](https://github.com/FalkorDB/FalkorDB)
- [FalkorDB clients](https://docs.falkordb.com/getting-started/clients)
- [FalkorDB configuration](https://docs.falkordb.com/getting-started/configuration)
- [FalkorDB cluster](https://docs.falkordb.com/operations/cluster)
- [FalkorDB RedisGraph migration](https://docs.falkordb.com/operations/migration/redisgraph-to-falkordb)
- [falkordb-php](https://github.com/FalkorDB/falkordb-php)
- [RedisGraph EOL](https://redis.io/blog/redisgraph-eol/)
- [RedisGraph README](https://github.com/RedisGraph/RedisGraph)
- [Memgraph install](https://memgraph.com/docs/getting-started/install-memgraph)
- [Memgraph Docker](https://memgraph.com/docs/getting-started/install-memgraph/docker)
- [Memgraph PHP](https://memgraph.com/docs/client-libraries/php)
- [Memgraph storage / memory](https://memgraph.com/docs/fundamentals/storage-memory-usage)
- [Memgraph vector search](https://memgraph.com/docs/querying/vector-search)
- [Memgraph BSL](https://github.com/memgraph/memgraph/blob/master/licenses/BSL.txt)
- [SurrealDB macOS](https://surrealdb.com/docs/surrealdb/installation/macos)
- [SurrealDB start](https://surrealdb.com/docs/surrealdb/cli/start)
- [SurrealDB architecture](https://surrealdb.com/docs/surrealdb/introduction/architecture)
- [SurrealDB license](https://surrealdb.com/license)
- [surrealdb.php](https://github.com/surrealdb/surrealdb.php/)
- [SurrealDB DEFINE INDEX](https://surrealdb.com/docs/reference/query-language/statements/define/indexes)
- [ArcadeDB README](https://github.com/ArcadeData/arcadedb)
- [ArcadeDB client-server](https://arcadedb.com/client-server.html)
- [NebulaGraph Docker quick start](https://docs.nebula-graph.io/master/2.quick-start/1.quick-start-workflow/)
- [NebulaGraph clients](https://docs.nebula-graph.io/3.3.0/14.client/1.nebula-client/)
- [DBngin](https://dbngin.com/)
