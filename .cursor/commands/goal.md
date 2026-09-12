---
description: Set a goal Cursor will pursue to completion (CreateGoal + Gaia OSINT graph context)
---

# /goal

Follow the Cursor Goal skill exactly:

1. Parse `/goal <objective>`. If the objective is empty, show `Usage: /goal <objective>`.
2. Restate the objective, including every explicit deliverable.
3. Call `CreateGoal` exactly once. Do not create goal files manually.
4. Do the first concrete unit of work in the same turn.

This repo is Gaia: a Laravel 13 / Vue 3 / Herd admin for a local Neo4j people-graph built from legal public OSINT dumps.

When the objective is about Gaia, keep these decisions unless the user overrides them:

- App state stays on DBngin MySQL (`gaia`). Tests stay on sqlite memory.
- Graph store is Neo4j Community via Bolt. PHP talks through `GraphClient`, never Eloquent.
- Use `final readonly` Actions with `handle()`. Thin controllers.
- People are fuzzy; emails/phones/ids are hash-keyed `Identifier` nodes. Embeddings only propose `SAME_AS`.
- Ingest only operator-held or openly published datasets (OpenSanctions, OFAC, etc.). No stolen dumps, no scraping authenticated sites.
- Explorer fetches neighborhoods via JSON (`useHttp` / Wayfinder), not Inertia page props. Sigma.js + Graphology for the viewport.
- Prefer `cursor-grok-4.6-xhigh-fast` subagents for parallel research or isolated implementation slices.

Read `docs/research/00-recommended-stack.md` before changing the stack.

Use grok 4.6 xhigh fast subagents when the work splits cleanly (research, backend, frontend). Keep the goal active until every deliverable is verified against the working tree.
