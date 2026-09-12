# Gaia

Laravel 13 + Vue 3 admin for a local **people graph**: ingest public OSINT dumps, store entities and relationships in Neo4j, browse neighborhoods from `https://gaia.test`.

Based on [joshdonnell/laravel-starter-kit-vue](https://github.com/joshdonnell/laravel-starter-kit-vue).

## Local stack

| Piece | Where |
| --- | --- |
| App | Laravel Herd → [https://gaia.test](https://gaia.test) |
| App DB | DBngin MySQL `gaia` on `127.0.0.1:3306` |
| Redis | DBngin `127.0.0.1:6379` |
| Graph | Neo4j Community, Browser `7474`, Bolt `7687` |
| Tests | in-memory SQLite (`phpunit.xml`) |

```bash
composer setup
herd link gaia --secure --update-env
docker compose up -d
composer dev
```

Register at `/register`, then open **Explorer**.

```bash
# After Neo4j is running and GRAPH_* is set in .env
php artisan graph:ingest-opensanctions us_ofac_sdn --limit=400
```

Default auth for the bundled Neo4j container is `neo4j` / `gaia-dev`. Browser: [http://127.0.0.1:7474](http://127.0.0.1:7474).

## Research

See [docs/research/README.md](docs/research/README.md). Default stack: [00-recommended-stack.md](docs/research/00-recommended-stack.md).

Default dump is the public [OpenSanctions US OFAC SDN](https://www.opensanctions.org/datasets/us_ofac_sdn/) dataset (FollowTheMoney JSON), not a stolen credential leak.

## License

MIT, same as the starter kit. OpenSanctions / OFAC data remains under their own terms — attribution is shown in the admin.
