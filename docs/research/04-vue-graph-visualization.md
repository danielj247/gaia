# Vue graph visualization for Gaia admin

**Date:** 2026-09-12  
**Scope:** Neighborhood (ego) graphs of 200–2000 nodes in a Vue 3 + TypeScript + Tailwind v4 + shadcn-vue / reka-ui + Inertia v3 admin. Not the full OSINT people graph. Primary sources only.

## Verdict

**Use Sigma.js v3 + Graphology as the viewport renderer.** Mount it imperatively in `onMounted` / tear down with `renderer.kill()`. Fetch neighborhood JSON from a dedicated Laravel action (Wayfinder-typed), not from Inertia page props. Merge expansions with Graphology `mergeNode` / `mergeEdge` and ForceAtlas2 in a worker.

That ranking fits this kit because:

1. Sigma’s own site positions the library for **thousands of nodes and edges via WebGL**, and says Canvas/SVG (including d3) is the better fit only for a few hundred nodes or highly custom drawing. Gaia’s 200–2000 band sits on the WebGL side. ([sigmajs.org](https://www.sigmajs.org/), [docs](https://www.sigmajs.org/docs/))
2. Graphology owns the data model, serialization, neighbor iteration, and community detection. Sigma only renders. That split matches “load an ego graph, then expand.” ([graphology serialization](https://graphology.github.io/serialization.html), [neighbors](https://graphology.github.io/iteration.html#neighbors), [Louvain](https://graphology.github.io/standard-library/communities-louvain.html))
3. There is **no official Vue wrapper**. That is not a blocker: G6’s own Vue page tells you to construct the graph in `onMounted` and **not** pass Vue reactive proxies into the instance. The same pattern works for Sigma and Cytoscape. ([G6 Vue integration](https://g6.antv.antgroup.com/en/manual/getting-started/integration/vue))
4. **Do not put 100k nodes in Inertia props.** Inertia v3’s `useHttp` exists specifically for JSON that must not start a page visit. Use a Wayfinder action for the subgraph. Use partial reloads only for small page chrome (search hits, inspector metadata), not for graph deltas. ([Inertia HTTP requests](https://inertiajs.com/docs/v3/the-basics/http-requests), [partial reloads](https://inertiajs.com/docs/v3/data-props/partial-reloads), [Wayfinder](https://github.com/laravel/wayfinder))

**Close second:** Cytoscape.js 3.34 + `cytoscape-fcose` if stylesheet-driven labels, compound grouping, and graph-theory queries matter more than raw WebGL throughput. Default renderer is Canvas; WebGL is a 2025 preview mode, not the documented default. ([js.cytoscape.org](https://js.cytoscape.org/), [fcose](https://github.com/iVis-at-Bilkent/cytoscape.js-fcose), [WebGL design](https://github.com/cytoscape/cytoscape.js/blob/unstable/documentation/webgl.md))

**Do not pick 3D** (`3d-force-graph` / ngraph as a viewport) for this admin. Official React bindings exist; 3D hurts labels, inspector alignment, and canvas accessibility. ngraph is a graph/physics library, not a Vue visualization.

### Ranked recommendation

| Rank | Choice | Use when |
| --- | --- | --- |
| 1 | **Sigma.js 3 + Graphology** | Default Gaia explorer. 200–2000 nodes, expand-on-click, dark mode via node/label colors. |
| 2 | **Cytoscape.js + fcose** | Need CSS-like stylesheets, compound parents, `eles.neighborhood()`, first-party layout quality. Accept Canvas (WebGL preview). |
| 3 | **@antv/g6 5** | Want official Vue docs, built-in `theme: 'dark'`, combos, `addData`. Accept AntV weight and “do not pass reactive data” constraint. |
| 4 | **vis-network** | Want built-in `cluster` / `openCluster` on a Canvas graph that the docs say is smooth up to a few thousand nodes. No official Vue wrapper. |
| 5 | **v-network-graph** | Small reactive SVG graphs. Best Vue 3 DX. Author still lists “performance improvement when using large network graphs” on the v1.0 roadmap. |
| 6 | **3d-force-graph / ngraph** | Skip for the admin viewport. 3D demo / optional physics only. |

## Comparison table

Facts below are owned by the project that ships the library. “Neighborhood expand” means **server-fetched ego expansion** unless the library documents a built-in cluster/compound expand.

| Library | License | TypeScript | Vue 3 | Renderer / perf (owner claim) | Dark theme | Node labels | Clustering | Expand neighborhood | JSON import shape | Official Vue wrapper |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| **Cytoscape.js** 3.34.3 + cose-bilkent / fcose | MIT (core + first-party extensions) | Bundled `index.d.ts` | Works as DOM library; no first-party Vue component | Canvas layers; WebGL is a preview mode on the Canvas renderer. Official factsheet: “Highly optimised”, serialisable JSON | Stylesheet (`background-color`, `label` color). No named dark theme | `style: [{ selector: 'node', style: { label: 'data(id)' } }]` | Compounds + `cytoscape-expand-collapse` (compound/edge collapse; **repo says it is no longer maintained**) | App: `cy.add()` then layout. In-memory: `eles.neighborhood()` / `closedNeighborhood()` | `{ elements: { nodes: [{ data: { id } }], edges: [{ data: { id, source, target } }] } }` or flat array with `group` | **Third-party** `vue-cytoscape` listed on official extensions page. First-party React: `react-cytoscapejs` |
| **Sigma.js** 3.0.3 + **Graphology** | MIT (both) | Native (`dist/sigma.cjs.d.ts`; Graphology generics) | Imperative mount; official FAQ names React (`@react-sigma`) and an Angular example, **not Vue** | **WebGL** “thousands of nodes and edges”. FAQ: WebGL faster than Canvas/SVG; d3 better for a few hundred or custom drawing. Labels/hover use 2D canvas overlays | No theme API. `labelColor`, `defaultNodeColor`, `defaultEdgeColor` | `renderLabels: true` (default); `labelRenderedSizeThreshold`, `labelDensity`; hover via `drawDiscNodeHover` | No built-in cluster widget. Graphology **Louvain** assigns community ids; Sigma storybook draws cluster labels as DOM | App: `graph.mergeNode` / `mergeEdge` + `renderer.refresh()`. Hover neighborhood in official “use reducers” story | Graphology `export()` / `import()` / `Graph.from()`: `{ nodes: [{ key, attributes }], edges: [{ source, target, attributes }] }`. Also GEXF via `graphology-gexf` | **None official.** React wrapper recommended by Sigma FAQ |
| **vis-network** | **Apache-2.0 OR MIT** | Bundled `declarations/index.d.ts` | Imperative; no official Vue package | **HTML canvas**. Docs: “works smooth … for up to a few thousand nodes and edges. To handle a larger amount of nodes, Network has clustering support.” | Options (`nodes.color`, `nodes.font.color`, `edges.color`). No named dark theme | `label` on node objects; custom `ctxRenderer` | First-class: `cluster`, `clusterByHubsize`, `clusterByConnection`, `clusterOutliers`, `openCluster`, `isCluster` | Cluster open is built-in. Server ego-expand is `DataSet.add` | `{ nodes: DataSet\|[{ id, label }], edges: [{ from, to }] }`. Also Gephi JSON and DOT | **None official** |
| **@antv/g6** 5.1.1 | MIT | Native (`lib/index.d.ts`) | Official Vue page + Vue custom nodes. Graphin (insight toolkit) is **React** | Default **Canvas** (`@antv/g-canvas`). Official: switch to `@antv/g-svg` or `@antv/g-webgl`. Layouts can use WebGPU/WASM | Built-in `theme: 'dark' \| 'light'` | Label on `data` / `style.labelText`; `auto-adapt-label` behavior | **Combos** (nested groups) + `collapse-expand` behavior | Combo/node collapse is built-in (`collapseElement` / `expandElement`). Server ego-expand: `addData` / `addNodeData` | `{ nodes: [{ id, data, style }], edges: [{ source, target, data, style }], combos?: [...] }` | **Official integration recipe**, not a wrapper component. Warns: do not pass Vue reactive data into G6 |
| **v-network-graph** 0.9.23 | MIT | Native (`lib/index.d.ts`) | **First-class Vue 3 component**; peer `vue ^3.5.13` | **SVG**. Author roadmap to v1.0 still includes “Performance improvement when using large network graphs” | Style via configs / CSS (light+dark logos in README). No named theme API | `configs.node.label.text` (key or function) | Not documented as a clustering engine | Reactive `nodes` / `edges` objects; add keys on expand | `{ [id]: { name, ... } }` nodes + `{ [id]: { source, target } }` edges | **This is the wrapper** |
| **3d-force-graph** 1.80.0 | MIT | Bundled `dist/3d-force-graph.d.ts` | Imperative web component. Official **React** bindings (`react-force-graph`) | **ThreeJS / WebGL** 3D. Example “~4k elements”. Optional force engine: d3-force-3d or **ngraph** | `backgroundColor` (default `#000011`) | `nodeLabel` (text/HTML) | None as vis-network-style clusters | Official “click to expand/collapse nodes” example; `graphData` incremental updates | `{ nodes: [{ id, name, val }], links: [{ source, target }] }` | **None for Vue.** React yes |
| **ngraph.graph** 20.1.2 | **BSD-3-Clause** | `index.d.ts` | N/A (data structure) | Not a renderer. Used as physics option inside 3d-force-graph | N/A | N/A | N/A | N/A | Library-specific graph API | None |

## Library notes (primary sources)

### Cytoscape.js + cose-bilkent / fcose

- Core 3.34.3, MIT, `"types": "index.d.ts"`. Factsheet: pure JS, no runtime deps, ES modules, **fully serialisable JSON**, stylesheets separate presentation from data, selectors, layouts, graph algorithms. ([package.json](https://raw.githubusercontent.com/cytoscape/cytoscape.js/master/package.json), [js.cytoscape.org](https://js.cytoscape.org/))
- Renderer creates `<canvas>` layers via `document.createElement`. Docs/init still expose `hideEdgesOnViewport` / `textureOnViewport` as large-graph hints, marked “largely moot” after performance work. ([init.md via Context7 / cytoscape docs](https://github.com/cytoscape/cytoscape.js/blob/unstable/documentation/md/core/init.md))
- WebGL is **not** a separate public renderer: March 2025 design note describes a preview `webgl2` layer under the Canvas renderer, switching back to Canvas when zoomed in close. ([webgl.md](https://github.com/cytoscape/cytoscape.js/blob/unstable/documentation/webgl.md), [blog announcement](https://blog.js.cytoscape.org/2025/01/13/webgl-preview/))
- **fCoSE** (first-party list): “faster version of the CoSE-Bilkent layout”; official extensions page says **try fCoSE first** for force-directed. Supports compound graphs and constraints (fixed, alignment, relative). ([extensions.md](https://github.com/cytoscape/cytoscape.js/blob/master/documentation/md/extensions.md), [fcose README](https://github.com/iVis-at-Bilkent/cytoscape.js-fcose))
- **cose-bilkent**: “near-perfect end results” but “more expensive than both `cose` and `fcose`”. ([extensions.md](https://github.com/cytoscape/cytoscape.js/blob/master/documentation/md/extensions.md))
- Expand-collapse extension: compound/edge collapse API (`api.expand`, `api.collapse`). README banner: **no longer maintained** (new complexity-management framework in progress). That is **not** server-side ego expansion. ([cytoscape.js-expand-collapse](https://github.com/iVis-at-Bilkent/cytoscape.js-expand-collapse))
- In-graph neighborhood: `eles.neighborhood()` (open, default) and `eles.closedNeighborhood()`. `cy.add()` accepts the same elements JSON as init. ([js.cytoscape.org traversing](https://js.cytoscape.org/#eles.neighborhood), [cy.add](https://js.cytoscape.org/#cy.add))
- Vue: official extensions list marks `vue-cytoscape` as **third-party**; first-party React component is Plotly’s `react-cytoscapejs`. ([extensions.md](https://github.com/cytoscape/cytoscape.js/blob/master/documentation/md/extensions.md))

### Sigma.js + Graphology

- Sigma v3 site: “visualizing graphs of thousands of nodes and edges”; architecture is Graphology (data + algorithms) + Sigma (render + interaction). MIT. npm `sigma@3.0.3`. v4 exists as **alpha** (`v4.sigmajs.org`) — stay on v3 for Gaia. ([sigmajs.org](https://www.sigmajs.org/), [docs](https://www.sigmajs.org/docs/), [sigma package.json](https://raw.githubusercontent.com/jacomyal/sigma.js/main/packages/sigma/package.json))
- FAQ: WebGL draws larger graphs faster than Canvas/SVG; custom rendering is harder; **few hundreds → d3**; React → `@react-sigma`; Angular → manual lifecycle, example repo, **no wrapper**. Vue is not mentioned. ([sigmajs.org FAQ](https://www.sigmajs.org/))
- Settings (TypeScript `Settings` interface): `renderLabels` default true, `renderEdgeLabels` default false, `labelColor` default `{ color: "#000" }`, `labelRenderedSizeThreshold: 6`, `hideEdgesOnMove` / `hideLabelsOnMove`, `nodeReducer` / `edgeReducer`. Hover labels use `CanvasRenderingContext2D`. ([settings.ts](https://raw.githubusercontent.com/jacomyal/sigma.js/main/packages/sigma/src/settings.ts), [drawDiscNodeHover](https://www.sigmajs.org/docs/typedoc/sigma/src/rendering/functions/drawDiscNodeHover))
- Official stories Gaia can copy: search + hover neighborhood (“use reducers”), GEXF load, cluster-label DOM overlay, ForceAtlas2 worker. ([storybook](https://www.sigmajs.org/storybook))
- Graphology serialization is the JSON contract. `graph.neighbors(node)` is the in-memory neighborhood. `mergeNode` / `mergeEdge` / `import(..., merge)` are the expand primitives. Louvain is a separate package. License MIT. ([serialization](https://graphology.github.io/serialization.html), [mutation](https://graphology.github.io/mutation.html), [LICENSE](https://raw.githubusercontent.com/graphology/graphology/master/LICENSE.txt))

### vis-network

- Docs opening claim: Canvas; smooth up to **a few thousand** nodes/edges; clustering for larger. Dual license in `package.json`: `(Apache-2.0 OR MIT)`. Types: `./declarations/index.d.ts`. ([network docs](https://visjs.github.io/vis-network/docs/network/), [package.json](https://raw.githubusercontent.com/visjs/vis-network/master/package.json))
- Clustering API is the closest “click to expand” **already in the library**: `clusterByHubsize`, `openCluster`, `isCluster`. Official clustering-by-zoom example opens a cluster on `selectNode`. That expands **already-loaded** nodes, not a server hop. ([docs methods](https://visjs.github.io/vis-network/docs/network/), [clustering.html](https://github.com/visjs/vis-network/blob/master/examples/network/other/clustering.html))
- Import: vis DataSets, Gephi JSON, DOT. Node shape `custom` + `ctxRenderer` for canvas drawing. No official Vue wrapper in the docs.

### @antv/g6 5

- Engine 5.1.1, MIT, TypeScript. Homepage [g6.antv.antgroup.com](https://g6.antv.antgroup.com/en). Default Canvas; SVG/WebGL via `renderer: () => new Renderer()`. Layer callback can put WebGL on `main` and SVG on overlays. ([renderer](https://g6.antv.antgroup.com/en/manual/further-reading/renderer), [package.json](https://raw.githubusercontent.com/antvis/G6/v5/packages/g6/package.json))
- JSON: `nodes` / `edges` / `combos`; business fields go in `data`, visuals in `style`. `addData` / `addNodeData` / `addEdgeData` are the documented mutate APIs. Remote load is **your** `fetch`. ([data.en.md](https://raw.githubusercontent.com/antvis/G6/v5/packages/site/docs/manual/data.en.md))
- `collapse-expand` behavior: click/dblclick on **nodes or combos already in the graph**. Programmatic `graph.collapseElement` / `expandElement`. ([CollapseExpand](https://g6.antv.antgroup.com/en/manual/behavior/collapse-expand))
- Vue: official page constructs `new Graph` in `onMounted`. **Warning: do not pass Vue reactive data into G6** (can fail to render or crash). Custom Vue nodes exist as a separate manual page. Graphin is React. ([Vue integration](https://g6.antv.antgroup.com/en/manual/getting-started/integration/vue))
- Dark theme is a first-class Graph option (`theme: 'dark'`). ([extensions.en.md via Context7](https://github.com/antvis/g6/blob/v5/packages/site/docs/manual/graph/extensions.en.md))

### v-network-graph

- Vue 3 SVG component, MIT, types shipped, `peerDependencies.vue: ^3.5.13`. Reactive `nodes` / `edges` maps. Labels via config key or function. ([getting started](https://dash14.github.io/v-network-graph/getting-started.html), [package.json](https://raw.githubusercontent.com/dash14/v-network-graph/main/package.json), [README](https://raw.githubusercontent.com/dash14/v-network-graph/main/README.md))
- Author policy: Vue-reactive graph, not a WebGL engine. Roadmap still includes large-graph performance. No official node-count guarantee. Sigma’s FAQ is the owner claim that SVG/Canvas lose to WebGL as graphs grow.

### 3d-force-graph and ngraph

- 3d-force-graph: ThreeJS/WebGL, MIT, types, `graphData` / `jsonUrl`, incremental updates, official expand/collapse example, React bindings. Default background `#000011`. Force engine `d3` or `ngraph`. ([README](https://raw.githubusercontent.com/vasturiano/3d-force-graph/master/README.md), [package.json](https://raw.githubusercontent.com/vasturiano/3d-force-graph/master/package.json))
- ngraph.graph: BSD-3-Clause graph structure with `index.d.ts`. Not a Vue viewer. ([package.json](https://raw.githubusercontent.com/anvaka/ngraph.graph/master/package.json))
- 3D is useful for a “wow” overview of a few thousand points. It is a poor fit for a shadcn-vue sidebar admin: node labels, provenance inspector, keyboard lists, and PII warnings all want 2D HTML around a 2D graph. Sigma’s FAQ already steers “custom / small” work to 2D libraries.

## Inertia v3: do not dump the graph into page props

### What Inertia is for

Partial reloads fetch **a subset of props for the same page component**. The server should wrap expensive props in closures or `Inertia::optional()` so they are not evaluated unless requested. Inertia then **merges** those props into client memory. ([partial reloads](https://inertiajs.com/docs/v3/data-props/partial-reloads))

`Inertia::merge()` / `deepMerge()` merge **arrays/objects on the page props** during partial reloads (infinite scroll, “load more”). Full visits always replace. That is the wrong place to accumulate a 2000-node subgraph that the renderer already holds. ([merging props](https://inertiajs.com/docs/v3/data-props/merging-props))

### What to use for neighborhood JSON

Inertia v3 documents `useHttp` for “calls to an external API or fetching data from a **non-Inertia endpoint**.” Those requests **do not trigger page navigation** and return parsed JSON. ([HTTP requests](https://inertiajs.com/docs/v3/the-basics/http-requests))

Laravel + Inertia already send the XSRF cookie / `X-XSRF-TOKEN` header for Inertia’s HTTP client. GET neighborhood reads do not need a CSRF dance; mutations would. ([CSRF](https://inertiajs.com/docs/v3/security/csrf-protection))

If any Inertia page still carries person identifiers in props, enable **history encryption** so back-button after logout cannot read the snapshot. ([history encryption](https://inertiajs.com/docs/v3/security/history-encryption))

### Wayfinder action (recommended shape)

Wayfinder generates typed TS functions for controller methods and named routes (beta; API may change before 1.0). Functions return `{ url, method }` and accept `query`. ([Wayfinder README](https://github.com/laravel/wayfinder))

Recommended split:

| Request | Transport | Why |
| --- | --- | --- |
| `/graph` page shell (title, empty viewport, search box) | `Inertia::render` | Layout, auth, shadcn chrome |
| Person search hits | `useHttp` **or** `router.reload({ only: ['hits'] })` | Small lists; partial reload is documented for this |
| Ego / expand subgraph (`?seed=&depth=&limit=`) | **JSON** `useHttp.get(wayfinderAction.url(...))` | 200–2000 nodes must not ride the Inertia page document |
| Inspector properties / provenance | JSON `useHttp` by node id | Keep PII off the history snapshot unless encrypted |

Cap the JSON: depth (1–2), max nodes (e.g. 500 then 2000), and omit raw special-category fields from the **label** payload (ICO data minimisation). Return them only on the inspector endpoint.

### Official sketches (not app code)

**Laravel (Inertia page + JSON action):**

```php
// Page: no graph body.
return Inertia::render('Graph/Explorer', [
    'hits' => Inertia::optional(fn () => PersonSearch::handle($request)),
]);

// JSON: Wayfinder will emit a typed action for this controller method.
return response()->json(
    NeighborhoodSubgraph::handle(
        seed: $request->string('seed'),
        depth: $request->integer('depth', 1),
        limit: min($request->integer('limit', 500), 2000),
    )
);
```

**Vue (Inertia v3 `useHttp` + Wayfinder):**

```ts
import { useHttp } from '@inertiajs/vue3'
import { neighborhood } from '@/actions/App/Http/Controllers/Graph/NeighborhoodController'

const http = useHttp()

async function loadEgo(seed: string) {
  const data = await http.get(neighborhood.url(seed, { query: { depth: 1, limit: 500 } }))
  graph.import(data, true) // Graphology merge
  renderer.refresh()
}
```

G6’s own remote-data docs show the same idea with `fetch` + `data` then `graph` APIs. ([G6 data](https://raw.githubusercontent.com/antvis/G6/v5/packages/site/docs/manual/data.en.md))

## UX sketch (shadcn-vue sidebar kit)

Fit the kit’s existing **sidebar + main + optional Sheet/Drawer**. Do not invent a full-screen Gephi clone.

```
┌ Sidebar (kit) ┬ Main ──────────────────────────────────┬ Sheet (inspector) ┐
│ Graph         │ [PII banner]                            │ Selected node     │
│ People        │ Search person  [depth 1] [limit 500]    │ Properties        │
│               │                                         │ Provenance        │
│               │ ┌ Sigma / Cytoscape viewport ─────────┐ │ Sources / dates   │
│               │ │  ego node + neighbors               │ │ Expand 1-hop      │
│               │ │  click node → fetch + merge         │ │                  │
│               │ └─────────────────────────────────────┘ │                  │
│               │ HTML neighbor list (a11y + keyboard)    │                  │
└───────────────┴─────────────────────────────────────────┴──────────────────┘
```

Flow:

1. **Search person** — HTML combobox (not canvas). Choosing a hit calls the neighborhood JSON action and imports an ego graph.
2. **Ego graph** — ForceAtlas2 (Sigma) or fCoSE (Cytoscape). Camera animates to the seed (Sigma `camera.animate` in the official search story).
3. **Click node to expand** — `clickNode` / `tap` → JSON `?seed={id}&depth=1` → merge new nodes/edges → incremental layout (`randomize: false` on fCoSE; FA2 worker on Sigma). Cap total nodes; show “truncated” when the server hits `limit`.
4. **Inspector drawer** — reka-ui / shadcn Sheet. HTML definition list for properties and provenance. This is the accessible name/description of the current selection (see a11y). Do not put full PII only on the canvas label.

Dark mode: read the kit’s `class="dark"` (or `prefers-color-scheme`) and set Sigma `labelColor` / node colors, or Cytoscape stylesheet, or G6 `theme: 'dark'`.

## Accessibility and “this is PII”

### Canvas / WebGL is not the accessibility tree

MDN (HTML `<canvas>`): the element is a **bitmap**; drawn objects are **not** exposed to accessibility tools as semantic HTML; “in general, you should avoid using canvas in an accessible website or app”; provide **alternative content** inside `<canvas>`. Implicit ARIA role is none. ([MDN canvas](https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/canvas))

WHATWG HTML points at canvas accessibility best practices (fallback content, do not rely on the bitmap). SVG (`v-network-graph`) can expose text nodes, but a 2000-node SVG is still a poor sole UI.

**Required HTML companions (not optional chrome):**

- Search field and result list that can open an ego graph without clicking the canvas.
- Live region announcing “Loaded N people, M links” and “Selected {display name}”.
- Visible neighbor list for the selection (keyboard).
- Inspector Sheet as the full name/role/state of the current node (WCAG-style text alternative for the graphic).
- Do not use color alone for node type (also a WCAG contrast/use-of-color concern). Sigma/Cytoscape shapes + legend.

### Personal data UI warnings (UK GDPR / ICO)

An OSINT **people** graph is personal data: UK GDPR Art. 4 via ICO — information relating to an identified or identifiable natural person, including name, identification number, location, online identifiers. Company-only nodes are not personal data; sole traders, employees, and a name + corporate email **are**. Deceased persons are outside UK GDPR. Pseudonymised ids remain personal data (Recital 26). ([ICO: What is personal data?](https://ico.org.uk/for-organisations/uk-gdpr-guidance-and-resources/personal-information-what-is-it/what-is-personal-data/what-is-personal-data/))

Special category data (race, politics, religion, health, sex life, biometrics for ID, etc.) needs a higher bar — do not paint it on node labels. ([same ICO page](https://ico.org.uk/for-organisations/uk-gdpr-guidance-and-resources/personal-information-what-is-it/what-is-personal-data/what-is-personal-data/))

Data minimisation (Art. 5(1)(c)): adequate, relevant, **limited to what is necessary**. Do not collect “on the off-chance”. Review and delete. Special category: minimum only. ([ICO: Data minimisation](https://ico.org.uk/for-organisations/uk-gdpr-guidance-and-resources/data-protection-principles/a-guide-to-the-data-protection-principles/data-minimisation/))

**UI contract for Gaia:**

- Persistent banner on the explorer: this view can contain **personal data** of living people; access is logged; purpose is OSINT investigation.
- Labels: display name / node type only. Phones, addresses, health, politics → inspector, role-gated.
- Truncation notice when `limit` clips the neighborhood (minimisation + honesty).
- Encrypt Inertia history if the page shell still includes identifiers.
- Export/screenshot controls should repeat the PII warning.

## Official API sketches

**Sigma + Graphology (recommended viewport):**

```ts
import Graph from 'graphology'
import Sigma from 'sigma'
import type { SerializedGraph } from 'graphology-types'
import forceAtlas2 from 'graphology-layout-forceatlas2'
import FA2Layout from 'graphology-layout-forceatlas2/worker'

const graph = Graph.from(payload as SerializedGraph) // or graph.import(payload, true)
const renderer = new Sigma(graph, container, {
  renderLabels: true,
  labelColor: { color: document.documentElement.classList.contains('dark') ? '#e5e5e5' : '#111' },
  labelRenderedSizeThreshold: 6,
})
const fa2 = new FA2Layout(graph, { settings: forceAtlas2.inferSettings(graph) })
fa2.start()

renderer.on('clickNode', ({ node }) => { /* useHttp neighborhood → graph.import(delta, true) */ })
// teardown: fa2.kill(); renderer.kill()
```

**Cytoscape + fcose (second choice):**

```ts
import cytoscape from 'cytoscape'
import fcose from 'cytoscape-fcose'
cytoscape.use(fcose)

const cy = cytoscape({
  container,
  elements: { nodes: [/* { data: { id, label } } */], edges: [/* { data: { id, source, target } } */] },
  style: [
    { selector: 'node', style: { label: 'data(label)', 'background-color': '#64748b', color: '#e5e5e5' } },
    { selector: 'edge', style: { width: 1, 'line-color': '#475569' } },
  ],
  layout: { name: 'fcose', animate: false },
})
cy.on('tap', 'node', (evt) => {
  const id = evt.target.id()
  // fetch JSON → cy.add(elements); cy.layout({ name: 'fcose', randomize: false }).run()
  evt.target.closedNeighborhood() // in-memory only
})
```

**G6 Vue mount (official pattern):**

```vue
<script setup lang="ts">
import { onMounted, onBeforeUnmount } from 'vue'
import { Graph } from '@antv/g6'
let graph: Graph
onMounted(() => {
  graph = new Graph({
    container: document.getElementById('container')!,
    theme: 'dark',
    data, // plain object, not reactive
    behaviors: ['zoom-canvas', 'drag-canvas', 'click-select'],
  })
  graph.render()
})
onBeforeUnmount(() => graph?.destroy())
</script>
```

## What this note does not claim

- No independent benchmark of 200 vs 2000 nodes was run here. Performance sentences are the **vendors’** claims (Sigma “thousands”, vis-network “few thousand”, v-network-graph large-graph work still on the roadmap, Cytoscape WebGL still preview).
- `vue-cytoscape` is listed by Cytoscape as a third-party npm component; this session did not retrieve that package’s README, so Vue 2 vs 3 compatibility is **not** asserted.
- 3D is dismissed for **this admin UX**, not as a general visualization technique.

## Citations

1. Cytoscape.js docs / factsheet, license, JSON, neighborhood, add: [https://js.cytoscape.org/](https://js.cytoscape.org/)
2. Cytoscape `package.json` 3.34.3 MIT + types: [https://raw.githubusercontent.com/cytoscape/cytoscape.js/master/package.json](https://raw.githubusercontent.com/cytoscape/cytoscape.js/master/package.json)
3. Cytoscape LICENSE: [https://github.com/cytoscape/cytoscape.js/blob/master/LICENSE](https://github.com/cytoscape/cytoscape.js/blob/master/LICENSE)
4. Cytoscape extensions (fcose, cose-bilkent, expand-collapse, vue-cytoscape, react-cytoscapejs): [https://github.com/cytoscape/cytoscape.js/blob/master/documentation/md/extensions.md](https://github.com/cytoscape/cytoscape.js/blob/master/documentation/md/extensions.md)
5. fCoSE: [https://github.com/iVis-at-Bilkent/cytoscape.js-fcose](https://github.com/iVis-at-Bilkent/cytoscape.js-fcose)
6. cose-bilkent: [https://github.com/cytoscape/cytoscape.js-cose-bilkent](https://github.com/cytoscape/cytoscape.js-cose-bilkent)
7. expand-collapse (unmaintained banner + API): [https://github.com/iVis-at-Bilkent/cytoscape.js-expand-collapse](https://github.com/iVis-at-Bilkent/cytoscape.js-expand-collapse)
8. Cytoscape WebGL preview design: [https://github.com/cytoscape/cytoscape.js/blob/unstable/documentation/webgl.md](https://github.com/cytoscape/cytoscape.js/blob/unstable/documentation/webgl.md), [https://blog.js.cytoscape.org/2025/01/13/webgl-preview/](https://blog.js.cytoscape.org/2025/01/13/webgl-preview/)
9. Sigma.js site + FAQ + MIT: [https://www.sigmajs.org/](https://www.sigmajs.org/)
10. Sigma.js docs (install, TS usage, v4 alpha note): [https://www.sigmajs.org/docs/](https://www.sigmajs.org/docs/)
11. Sigma LICENSE: [https://github.com/jacomyal/sigma.js/blob/main/LICENSE.txt](https://github.com/jacomyal/sigma.js/blob/main/LICENSE.txt)
12. Sigma `package.json` 3.0.3: [https://raw.githubusercontent.com/jacomyal/sigma.js/main/packages/sigma/package.json](https://raw.githubusercontent.com/jacomyal/sigma.js/main/packages/sigma/package.json)
13. Sigma settings + defaults: [https://raw.githubusercontent.com/jacomyal/sigma.js/main/packages/sigma/src/settings.ts](https://raw.githubusercontent.com/jacomyal/sigma.js/main/packages/sigma/src/settings.ts)
14. Graphology serialization: [https://graphology.github.io/serialization.html](https://graphology.github.io/serialization.html)
15. Graphology mutation / neighbors: [https://graphology.github.io/mutation.html](https://graphology.github.io/mutation.html), [https://graphology.github.io/iteration.html](https://graphology.github.io/iteration.html)
16. Graphology Louvain: [https://graphology.github.io/standard-library/communities-louvain.html](https://graphology.github.io/standard-library/communities-louvain.html)
17. Graphology MIT license: [https://raw.githubusercontent.com/graphology/graphology/master/LICENSE.txt](https://raw.githubusercontent.com/graphology/graphology/master/LICENSE.txt)
18. vis-network docs (Canvas, few thousand, clustering methods, Gephi/DOT): [https://visjs.github.io/vis-network/docs/network/](https://visjs.github.io/vis-network/docs/network/)
19. vis-network `package.json` license + types: [https://raw.githubusercontent.com/visjs/vis-network/master/package.json](https://raw.githubusercontent.com/visjs/vis-network/master/package.json)
20. vis-network MIT text: [https://github.com/visjs/vis-network/blob/master/LICENSE-MIT](https://github.com/visjs/vis-network/blob/master/LICENSE-MIT)
21. G6 introduction / Vue / renderer / collapse-expand / theme: [https://g6.antv.antgroup.com/en/manual/introduction](https://g6.antv.antgroup.com/en/manual/introduction), […/integration/vue](https://g6.antv.antgroup.com/en/manual/getting-started/integration/vue), […/renderer](https://g6.antv.antgroup.com/en/manual/further-reading/renderer), […/collapse-expand](https://g6.antv.antgroup.com/en/manual/behavior/collapse-expand)
22. G6 data format: [https://raw.githubusercontent.com/antvis/G6/v5/packages/site/docs/manual/data.en.md](https://raw.githubusercontent.com/antvis/G6/v5/packages/site/docs/manual/data.en.md)
23. G6 5.1.1 MIT + types: [https://raw.githubusercontent.com/antvis/G6/v5/packages/g6/package.json](https://raw.githubusercontent.com/antvis/G6/v5/packages/g6/package.json)
24. G6 LICENSE: [https://github.com/antvis/G6/blob/v5/LICENSE](https://github.com/antvis/G6/blob/v5/LICENSE)
25. v-network-graph site + getting started: [https://dash14.github.io/v-network-graph/](https://dash14.github.io/v-network-graph/), [getting-started](https://dash14.github.io/v-network-graph/getting-started.html)
26. v-network-graph README (SVG, Vue 3, large-graph roadmap): [https://raw.githubusercontent.com/dash14/v-network-graph/main/README.md](https://raw.githubusercontent.com/dash14/v-network-graph/main/README.md)
27. v-network-graph `package.json` 0.9.23 MIT + types: [https://raw.githubusercontent.com/dash14/v-network-graph/main/package.json](https://raw.githubusercontent.com/dash14/v-network-graph/main/package.json)
28. 3d-force-graph README + JSON + ngraph engine + expand example: [https://raw.githubusercontent.com/vasturiano/3d-force-graph/master/README.md](https://raw.githubusercontent.com/vasturiano/3d-force-graph/master/README.md)
29. 3d-force-graph `package.json` MIT + types: [https://raw.githubusercontent.com/vasturiano/3d-force-graph/master/package.json](https://raw.githubusercontent.com/vasturiano/3d-force-graph/master/package.json)
30. ngraph.graph BSD-3-Clause + types: [https://raw.githubusercontent.com/anvaka/ngraph.graph/master/package.json](https://raw.githubusercontent.com/anvaka/ngraph.graph/master/package.json)
31. Inertia v3 partial reloads: [https://inertiajs.com/docs/v3/data-props/partial-reloads](https://inertiajs.com/docs/v3/data-props/partial-reloads)
32. Inertia v3 merging props: [https://inertiajs.com/docs/v3/data-props/merging-props](https://inertiajs.com/docs/v3/data-props/merging-props)
33. Inertia v3 HTTP requests (`useHttp`): [https://inertiajs.com/docs/v3/the-basics/http-requests](https://inertiajs.com/docs/v3/the-basics/http-requests)
34. Inertia v3 CSRF: [https://inertiajs.com/docs/v3/security/csrf-protection](https://inertiajs.com/docs/v3/security/csrf-protection)
35. Inertia v3 history encryption: [https://inertiajs.com/docs/v3/security/history-encryption](https://inertiajs.com/docs/v3/security/history-encryption)
36. Laravel Wayfinder: [https://github.com/laravel/wayfinder](https://github.com/laravel/wayfinder)
37. MDN `<canvas>` accessibility: [https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/canvas](https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/canvas)
38. ICO personal data (UK GDPR definition, special category, companies, deceased, pseudonymisation): [https://ico.org.uk/for-organisations/uk-gdpr-guidance-and-resources/personal-information-what-is-it/what-is-personal-data/what-is-personal-data/](https://ico.org.uk/for-organisations/uk-gdpr-guidance-and-resources/personal-information-what-is-it/what-is-personal-data/what-is-personal-data/)
39. ICO data minimisation Art. 5(1)(c): [https://ico.org.uk/for-organisations/uk-gdpr-guidance-and-resources/data-protection-principles/a-guide-to-the-data-protection-principles/data-minimisation/](https://ico.org.uk/for-organisations/uk-gdpr-guidance-and-resources/data-protection-principles/a-guide-to-the-data-protection-principles/data-minimisation/)
