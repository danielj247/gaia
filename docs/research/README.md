# Gaia research

Primary-source notes for turning this Laravel 13 / Vue 3 Herd app into an OSINT people-graph admin.

Machine facts at research time:

- App: `joshdonnell/laravel-starter-kit-vue` v1.3.8 on Laravel 13.31, Inertia v3, Vue 3, PHP 8.5 via [Laravel Herd](https://herd.laravel.com)
- Site: `https://gaia.test`
- App DB: DBngin MySQL 8.4.11 at `127.0.0.1:3306`, database `gaia`, user `root`, empty password
- Redis: `127.0.0.1:6379` (DBngin)
- PostgreSQL: not running locally
- Tests stay on in-memory SQLite (`phpunit.xml`)
- Starter conventions: `final readonly` Actions with `handle()`, Cruddy controllers, Spatie Data + Wayfinder types

| File | Question |
| --- | --- |
| [00-recommended-stack.md](./00-recommended-stack.md) | Synthesis: what to install and build first |
| [01-local-graph-databases.md](./01-local-graph-databases.md) | Which graph database can we install locally? |
| [02-laravel-ai-openrouter.md](./02-laravel-ai-openrouter.md) | Laravel AI SDK + OpenRouter embeddings |
| [03-osint-people-graph.md](./03-osint-people-graph.md) | People / metadata node-edge model and ingest |
| [04-vue-graph-visualization.md](./04-vue-graph-visualization.md) | Vue admin graph visualization |
| [05-laravel-graph-architecture.md](./05-laravel-graph-architecture.md) | PHP drivers and dual-store architecture |
| [06-public-osint-dump.md](./06-public-osint-dump.md) | Legal public dump: OpenSanctions `us_ofac_sdn` |
