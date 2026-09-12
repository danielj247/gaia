# Public OSINT dump for Gaia v1

**Date:** 2026-09-12  
**Project:** Gaia — pick one legal, public, downloadable people-and-metadata dataset and map it onto the Neo4j + Laravel / FollowTheMoney Person-vs-Identifier graph.  
**Scope:** OpenSanctions `us_ofac_sdn` / `default` / `sanctions`, raw OFAC SDN, ICIJ Offshore Leaks bulk. Licenses, URLs, sizes, record shape, Gaia label map, admin attribution.  
**Out of scope:** stolen credential dumps, password leaks, scraped private accounts, or ingesting ICIJ’s underlying leaked files (only ICIJ’s published extract is considered).

Every factual claim below is tied to the document or file that owns it. Sizes and entity counts were read from official indexes or HTTP headers on 2026-09-12.

---

## Verdict

**Default for Gaia v1: OpenSanctions `us_ofac_sdn` FollowTheMoney NDJSON.**

Download today (no login, no API key):

```bash
curl -L -o us_ofac_sdn.index.json \
  https://data.opensanctions.org/datasets/latest/us_ofac_sdn/index.json
curl -L -o us_ofac_sdn.entities.ftm.json \
  https://data.opensanctions.org/datasets/latest/us_ofac_sdn/entities.ftm.json
```

The `latest/` URL 307s to the dated artifact. On 2026-09-12 that was `https://data.opensanctions.org/artifacts/us_ofac_sdn/20260912201001-lej/entities.ftm.json` (SHA1 `10e11d4640cdca890bbc482c5472a78b2a8cfb64`, **53,022,637 bytes**, 72,322 entities). Optional skinny table: `targets.simple.csv` (7.55 MB).

This is the only candidate that is (1) openly published, (2) already FollowTheMoney — Person / Organization / Identification / Address / Sanction / Ownership as first-class entities, (3) people **plus** extra types, and (4) laptop-ingestible in hours. OpenSanctions’ own `default` collection is the richer product (they tell you to use it and filter) but it is **2.58 GB / 4.04 million entities** — too large for a first Laravel queue pass. `sanctions` (354 MB / 293k entities) is the v1.1 upgrade. Raw OFAC SDN XML is the license-safer fallback if Gaia ever becomes commercial (OpenSanctions bulk is **CC BY-NC 4.0**). ICIJ’s published CSV/Neo4j extract is legal ODbL + CC BY-SA, but officers mix people and companies, there are no passport/sanction nodes, and 3.34 million edges are a worse first mapper.

---

## 1. Comparison

| | OpenSanctions `us_ofac_sdn` | OpenSanctions `sanctions` | OpenSanctions `default` | OFAC raw SDN | ICIJ Offshore Leaks bulk |
| --- | --- | --- | --- | --- | --- |
| What it is | OFAC SDN rewritten as FtM | 93 national/UN sanctions lists, FtM | 461-source screening collection (sanctions + PEPs + crime + KYB enrichers) | U.S. Treasury SDN as published | ICIJ’s structured extract of Panama / Paradise / Bahamas / Offshore / Pandora Papers |
| Official profile | [opensanctions.org/datasets/us_ofac_sdn](https://www.opensanctions.org/datasets/us_ofac_sdn/) | [opensanctions.org/datasets/sanctions](https://www.opensanctions.org/datasets/sanctions/) | [opensanctions.org/datasets/default](https://www.opensanctions.org/datasets/default/) | [OFAC FAQ 19](https://ofac.treasury.gov/faqs/19); [SLS](https://ofac.treasury.gov/sanctions-list-service) | [offshoreleaks.icij.org/pages/database](https://offshoreleaks.icij.org/pages/database) |
| Stable curl URL | `https://data.opensanctions.org/datasets/latest/us_ofac_sdn/entities.ftm.json` | `…/latest/sanctions/entities.ftm.json` | `…/latest/default/entities.ftm.json` | `https://sanctionslistservice.ofac.treas.gov/api/PublicationPreview/exports/SDN.XML` (legacy XML) or `…/SDN_ADVANCED.XML` | `https://offshoreleaks-data.icij.org/offshoreleaks/csv/full-oldb.LATEST.zip` |
| Index / checksum | `…/latest/us_ofac_sdn/index.json` | `…/latest/sanctions/index.json` | `…/latest/default/index.json` | SLS `Digest` header; `Last-Modified` | Zip `Last-Modified` / ETag |
| Format | NDJSON FtM (`application/json+ftm`); also nested JSON, simple CSV, Senzing, `source.xml` | Same FtM family (no `source.xml`) | Same + `statements.csv` | XML (`sdnList` / `sdnEntry`); also CSV/FF, Advanced/Enhanced XSD | CSV nodes + `relationships.csv`; Neo4j `.dump` |
| Size (2026-09-12) | FtM **50.57 MB** (53,022,637 B); `source.xml` 127 MB; CSV 7.55 MB | FtM **337.65 MB** (354,048,754 B) | FtM **2.41 GB** (2,583,150,581 B); statements 8.72 GB | SLS page: SDN.XML 27.63 MB; Advanced XML 120.71 MB; Enhanced 103.96 MB. Sample `<Record_Count>19388</Record_Count>` | Zip **71,935,075 B** (~68.6 MB); unzipped CSVs ~626 MB. Neo4j 5.13 dump 364,517,980 B; 4.4 dump 474,610,479 B |
| People + extras | People 7,508; Orgs 9,867; Addresses 17,889; Identification 3,010; Passport 2,660; Sanction 19,388; Ownership 5,085; plus vessels, aircraft, crypto wallets, family, membership, directorship | People 41,739; Addresses 24,184; Orgs 19,796; Companies 3,866; plus legal entities, securities, wallets, vessels | People 1,123,439; Companies 227,100; Orgs 66,407; Addresses 31,730; Positions 209,446; Occupancy 994,413; Sanction 624,804; Ownership 199,263 | `sdnType` Individual / Entity / Vessel / Aircraft; nested `akaList`, `addressList`, `idList`, `programList`, dates of birth | Officers 771,368 (person **or** company); Entities 814,616; Addresses 402,320; Intermediaries 26,774; Others 2,989; Relationships 3,339,271 |
| License | OpenSanctions layer: **CC BY-NC 4.0**, free bulk, no key. Commercial / in-house screening needs a paid data license. Upstream facts are OFAC. | Same OS license over many government lists | Same | Public U.S. Treasury compliance list. No click-wrap on SLS. OFAC: get it from their site; Federal Register governs if formats differ ([FAQ 19](https://ofac.treasury.gov/faqs/19); [DAT_SPEC](https://ofac.treasury.gov/media/29976/download?inline=)) | Database **ODbL 1.0**; contents **CC BY-SA 3.0** (hrefs on the official download page). “Always cite the International Consortium of Investigative Journalists.” |
| Laptop hours? | Yes | Tight but yes | No for v1 (multi-GB + 4M nodes) | Yes (XML parse + flatten) | Zip is small; 3.3M edges + mixed officer types make the **mapper** the bottleneck |
| Fit to Gaia FtM split | Native | Native | Native, oversized | Must invent Person vs Identifier | Officers are not typed as people; no Identification/Sanction schemata |

OpenSanctions bulk URL pattern and “no login” rule: [Downloading the data](https://www.opensanctions.org/docs/bulk/updates/). They recommend `default` plus `topics` / `datasets` filters rather than a single-source file, because source-scoped exports omit cross-source enrichment ([Using the bulk data](https://www.opensanctions.org/docs/bulk/)). That advice is correct for a screening product. For Gaia v1 it fights the laptop-hours constraint. `us_ofac_sdn` still uses **canonical** `NK-` / Wikidata `Q` ids and a global `referents` array ([Identifiers](https://www.opensanctions.org/docs/identifiers/)).

ICIJ is allowed here only as ICIJ’s **published** CSV/Neo4j package, not the leaked Mossack/Appleby/etc. files. ICIJ: the public database is “just a fraction of the leaked files”; inclusion “is not intended to suggest or imply that they have engaged in illegal or improper conduct” ([FAQ](https://offshoreleaks.icij.org/pages/faq)).

---

## 2. Gaia v1 default — exact files

| Role | URL |
| --- | --- |
| Always-latest FtM stream | `https://data.opensanctions.org/datasets/latest/us_ofac_sdn/entities.ftm.json` |
| Always-latest metadata (version, SHA1, byte size) | `https://data.opensanctions.org/datasets/latest/us_ofac_sdn/index.json` |
| Dated artifact (example, 2026-09-12) | `https://data.opensanctions.org/artifacts/us_ofac_sdn/20260912201001-lej/entities.ftm.json` |
| Optional CSV of targets only | `https://data.opensanctions.org/datasets/latest/us_ofac_sdn/targets.simple.csv` |
| Optional OFAC Advanced XML mirror they ship | `https://data.opensanctions.org/datasets/latest/us_ofac_sdn/source.xml` |
| Upstream OFAC Advanced XML (what their crawler fetches) | `https://www.treasury.gov/ofac/downloads/sanctions/1.0/sdn_advanced.xml` → 302 to `https://sanctionslistservice.ofac.treas.gov/api/publicationpreview/exports/sdn_advanced.xml` |

Ingest `entities.ftm.json` (one JSON object per line). Do not start with `targets.nested.json` (79.71 MB, one fat object per target) or `default` (2.41 GB).

Poll `index.json` for `version` / resource `checksum` before re-downloading ([updates docs](https://www.opensanctions.org/docs/bulk/updates/)). Re-fetch `latest/` at most every 6 hours.

**v1.1:** swap the dataset segment to `sanctions` (same parser). **Commercial Gaia:** either buy an OpenSanctions license or parse OFAC SLS XML and drop the OS layer.

---

## 3. Record shape (official fields + live samples)

### 3.1 FollowTheMoney / OpenSanctions entity

FtM entities are `{id, schema, properties}` with **multi-valued string properties**. Entity–entity links are properties of type `entity` holding the other id. Interstitial schemata (`Ownership`, `Sanction`, `Identification`) are themselves entities, not bare edges. Streams are newline-delimited JSON ([OpenSanctions entity structure](https://www.opensanctions.org/docs/entities/); [FtM intro](https://followthemoney.tech/docs/)).

OpenSanctions adds root metadata ([entity structure](https://www.opensanctions.org/docs/entities/)):

| Field | Meaning |
| --- | --- |
| `id` | Canonical id after de-duplication (`NK-…` or Wikidata `Q…`; unmerged rows keep `ofac-…`) |
| `schema` | `Person`, `Organization`, `Address`, `Sanction`, `Identification`, `Passport`, `Ownership`, … |
| `caption` | Display name |
| `datasets` | Source keys that contributed facts (`us_ofac_sdn`) |
| `referents` | Source / retired ids merged into this row |
| `first_seen` / `last_change` / `last_seen` | Processing timestamps (UTC). Prefer Sanction `listingDate` / `startDate` for real-world dates |
| `target` | Legacy boolean; use `properties.topics` |
| `properties` | Schema-specific, always arrays of strings |

`us_ofac_sdn` schemata present in [statistics.json](https://data.opensanctions.org/datasets/latest/us_ofac_sdn/statistics.json) on 2026-09-12: `Person`, `Organization`, `Company`, `LegalEntity`, `Address`, `Identification`, `Passport`, `Sanction`, `Ownership`, `Membership`, `Directorship`, `Family`, `Representation`, `UnknownLink`, `Vessel`, `Airplane`, `CryptoWallet`, `Security`.

### 3.2 Live FtM samples (first entities in the 2026-09-12 export)

**Person** `NK-22HtK7WrxZ2sU3rmhz6PuZ` (caption `Michael Kuajien`):

```json
{
  "id": "NK-22HtK7WrxZ2sU3rmhz6PuZ",
  "caption": "Michael Kuajien",
  "schema": "Person",
  "referents": ["ofac-28033", "usgsa-46f697e04764caf32b394e9deddbf170553f3ff4"],
  "datasets": ["us_ofac_sdn"],
  "first_seen": "2023-04-20T10:27:20",
  "last_change": "2026-01-26T16:10:01",
  "properties": {
    "name": ["Michael Kuajien"],
    "firstName": ["Michael"],
    "lastName": ["Kuajien Duer Mayok", "Kuajien", "Kuajian"],
    "alias": ["Michael Kuajien Duer Mayok", "Michael Kuajian"],
    "birthDate": ["1979-01-01"],
    "gender": ["male"],
    "nationality": ["ss"],
    "country": ["ke"],
    "address": ["Nairobi"],
    "addressEntity": ["addr-1bb113a982d9c5755dbfc1521f213c070ba1cb2c"],
    "topics": ["sanction"],
    "programId": ["US-GLOMAG"],
    "sourceUrl": ["https://sanctionssearch.ofac.treas.gov/Details.aspx?id=28033"]
  },
  "target": true
}
```

(`referents` truncated; full row also lists more `usgsa-` / `ofac-pr-` ids.)

**Organization** `NK-223CQDBzp8MRkdJMDiqXn3` (caption `Myanmar Yatai International Holding Group Co., LTD.`): `properties.name`, `alias`, `registrationNumber` `["103919088"]`, `country` `["mm"]`, `addressEntity` (two addr ids), `address` (two strings), `sector`, `topics` `["sanction"]`, `programId` `["US-GLOMAG"]`, `sourceUrl` pointing at `sanctionssearch.ofac.treas.gov`.

**Ownership** `NK-23b3axriX3e3iW4yoCbvGF`: `properties.owner` / `asset` (entity ids), `role` `["Owned or Controlled By"]`.

**Address** `NK-2gvtrqTZN86nCpR9HXfSpZ`: `properties.full`, `street`, `city`, `postalCode`, `region`.

**Family** `NK-2GcoYmS8ueDe9m9ZwjLcAu`: `person`, `relative`, `relationship` `["Family member of"]` (here the endpoints are Wikidata `Q` ids).

**Directorship** `NK-3HwakfvSuEfMUzAoRXvFWF`: `director`, `organization`, `role` `["Leader or official of"]`.

Person / Organization / Address property names match the [FtM Person](https://followthemoney.tech/explorer/schemata/Person/), [Address](https://followthemoney.tech/explorer/schemata/Address/), [Identification](https://followthemoney.tech/explorer/schemata/Identification/), [Sanction](https://followthemoney.tech/explorer/schemata/Sanction/), [Ownership](https://followthemoney.tech/explorer/schemata/Ownership/), [Membership](https://followthemoney.tech/explorer/schemata/Membership/) dictionaries and the [OpenSanctions data dictionary](https://www.opensanctions.org/reference/).

Identification / Passport / Sanction did not appear in the first 25 MB of the stream (ids sort into later clusters). Official fields to parse when they do:

| Schema | Graph-relevant properties |
| --- | --- |
| `Identification` / `Passport` | `holder`, `number`, `type`, `country`, `authority`, `startDate`, `endDate` |
| `Sanction` | `entity`, `authority`, `authorityId`, `program`, `programId`, `programUrl`, `provisions`, `reason`, `country`, `listingDate`, `startDate`, `sourceUrl` |
| `Membership` | `member`, `organization`, `role`, dates |
| `CryptoWallet` | `holder`, `publicKey`, `currency` |

On this snapshot: 3,010 Identification rows (every one has `holder`, `number`, `type`); 2,660 Passports; 19,388 Sanctions (all have `entity`, `authority`, `authorityId`, `program`, `programId`, `provisions`, `reason`, `sourceUrl`). Person fill rates: `name` 100%, `birthDate` 98.7%, `alias` 49.4%, `idNumber` 35.2%, `passportNumber` 27.9%, `email` 0.8% ([statistics.json](https://data.opensanctions.org/datasets/latest/us_ofac_sdn/statistics.json)).

FtM: **weak aliases “should not be used for matching”** ([Person schema](https://followthemoney.tech/explorer/schemata/Person/)). `Ownership` is an **edge** (source `owner`, target `asset`); `Identification` and `Sanction` are **nodes** ([Ownership](https://followthemoney.tech/explorer/schemata/Ownership/); [Identification](https://followthemoney.tech/explorer/schemata/Identification/)).

### 3.3 OFAC legacy SDN.XML (not the v1 ingest, shown for contrast)

Fetched from SLS on 2026-09-12 (`Publish_Date` 09/10/2026, `Record_Count` 19388):

```xml
<sdnEntry>
  <uid>2674</uid>
  <firstName>Abu</firstName>
  <lastName>ABBAS</lastName>
  <title>Director of PALESTINE LIBERATION FRONT - ABU ABBAS FACTION</title>
  <sdnType>Individual</sdnType>
  <programList><program>SDGT</program></programList>
  <idList>
    <id>
      <idType>Secondary sanctions risk:</idType>
      <idNumber>section 1(b) of Executive Order 13224, as amended by Executive Order 13886</idNumber>
    </id>
  </idList>
  <akaList>
    <aka><type>a.k.a.</type><category>strong</category>
      <lastName>ZAYDAN</lastName><firstName>Muhammad</firstName></aka>
  </akaList>
  <dateOfBirthList>
    <dateOfBirthItem><dateOfBirth>10 Dec 1948</dateOfBirth></dateOfBirthItem>
  </dateOfBirthList>
</sdnEntry>
```

Entity example from the same file: `lastName` AEROCARIBBEAN AIRLINES, `sdnType` Entity, `aka` with `category` strong/weak, `addressList` (`address1`, `city`, `postalCode`, `country`). Aliases are labeled weak/strong in this format ([OFAC Advanced FAQ](https://ofac.treasury.gov/sdn-list-data-formats-data-schemas/frequently-asked-questions-on-advanced-sanctions-list-standard)). `idList` is **not** a clean passport table — it also holds program footnotes. Advanced XML is the one OpenSanctions crawls.

### 3.4 ICIJ CSV (runner-up graph, not v1)

Official zip href on [the download page](https://offshoreleaks.icij.org/pages/database): `https://offshoreleaks-data.icij.org/offshoreleaks/csv/full-oldb.LATEST.zip` (71,935,075 B, `Last-Modified: Wed, 09 Sep 2026`). Same page: Neo4j `https://offshoreleaks-data.icij.org/offshoreleaks/neo4j/icij-offshoreleaks-5.13.0.dump` and `…/icij-offshoreleaks-4.4.26.dump`. Guide repo: [ICIJ/offshoreleaks-data-packages](https://github.com/ICIJ/offshoreleaks-data-packages).

Headers and first rows from that zip:

| File | Rows | Header |
| --- | ---: | --- |
| `nodes-entities.csv` | 814,616 | `node_id,name,original_name,former_name,jurisdiction,jurisdiction_description,company_type,address,internal_id,incorporation_date,inactivation_date,struck_off_date,dorm_date,status,service_provider,ibcRUC,country_codes,countries,sourceID,valid_until,note` |
| `nodes-officers.csv` | 771,368 | `node_id,name,countries,country_codes,sourceID,valid_until,note` |
| `nodes-addresses.csv` | 402,320 | `node_id,address,name,countries,country_codes,sourceID,valid_until,note` |
| `nodes-intermediaries.csv` | 26,774 | `node_id,name,status,internal_id,address,countries,country_codes,sourceID,valid_until,note` |
| `nodes-others.csv` | 2,989 | `node_id,name,type,incorporation_date,struck_off_date,closed_date,jurisdiction,…` |
| `relationships.csv` | 3,339,271 | `node_id_start,node_id_end,rel_type,link,status,start_date,end_date,sourceID` |

ICIJ glossary: an **Officer** is “A person or company who plays a role in an offshore entity” ([FAQ](https://offshoreleaks.icij.org/pages/faq)). There is no person/org flag.

`rel_type` counts in this zip: `officer_of` 1,720,357; `registered_address` 832,721; `intermediary_of` 598,546; plus ICIJ-invented similarity edges `same_name_as` 104,170, `similar` 46,761, `same_company_as` 15,523, `same_as` 4,272, `same_id_as` 3,120. ICIJ created those similarity links; they did **not** merge similar names ([FAQ](https://offshoreleaks.icij.org/pages/faq)). Do not treat `same_name_as` as Gaia `SAME_AS`.

---

## 4. Field → Gaia nodes and edges

Keep the locked split from [03-osint-people-graph.md](./03-osint-people-graph.md) and [00-recommended-stack.md](./00-recommended-stack.md): fuzzy `Person` (UUIDv4, **not** name-keyed) vs hash-keyed `Identifier`. Store each FtM line as MySQL `dump_rows` + graph `Observation` before `MERGE`.

### 4.1 Nodes

| Gaia label | FtM `schema` / property | Stable key | Notes |
| --- | --- | --- | --- |
| `Person` | `Person` | Prefer OS `id` (`NK-` / `Q…`) as `sourceId`; Gaia `id` still UUIDv4 until an analyst asserts `SAME_AS` | Names (`name`, `firstName`, `lastName`, `birthDate`) are search properties, not merge keys |
| `Organization` | `Organization`, `Company`, `LegalEntity`, `PublicBody`, `Vessel`, `Airplane` | OS `id` as `sourceId` | Vessels/aircraft can stay `Organization` + `kind` for v1 |
| `Identifier` | `Identification` / `Passport` (`type`+`number`); also explode `email`, `phone`, `idNumber`, `passportNumber`, `taxNumber`, `socialSecurityNumber`, `registrationNumber`, `leiCode`, `innCode`, `ogrnCode`, `vatCode`, `swiftBic`, `CryptoWallet.publicKey` | UUIDv5(`type` + normalized `value`) | This **is** the Person vs Identifier split. Do not leave passport numbers only as Person properties |
| `Address` | `Address` | OS `id` or hash(`full`) | `full`, `street`, `city`, `postalCode`, `region`, `state`, `country` |
| `Sanction` (or `Document`) | `Sanction` | OS `id` | `program`, `programId`, `authority`, `authorityId`, `provisions`, `reason`, `listingDate`, `sourceUrl` |
| `Dump` / `Document` | the ingest | checksum of the FtM file | `parserName=opensanctions-ftm`, `parserVersion` = `index.json` `version` |
| `Observation` | one FtM line | `dumpId` + entity `id` | Keep raw JSON in MySQL |

Do **not** create matchable `Identifier` rows from `weakAlias`. Optionally store `alias` / `previousName` as Person properties and, if the admin needs `ALIAS_OF`, as `Identifier {type:'alias'}` with low confidence — never as a second `Person`.

### 4.2 Edges

| Gaia type | FtM source | From → To |
| --- | --- | --- |
| `HAS_IDENTIFIER` | `Identification.holder` / `Passport.holder`; exploded email/phone/id fields; `CryptoWallet.holder`→`publicKey` | Person \| Organization → Identifier |
| `ALIAS_OF` | `alias`, `previousName` (not `weakAlias`) | Person → Identifier(`type:'alias'`) **or** skip and keep properties |
| `LOCATED_AT` | `addressEntity` (and `Address.things` inverse) | Person \| Organization → Address |
| `SANCTIONED_UNDER` | `Sanction.entity` | Person \| Organization → Sanction |
| `OWNS` | `Ownership.owner` → `Ownership.asset` (edge properties: `role`, `percentage`, dates) | Person \| Organization → Organization (or Vessel) |
| `MEMBER_OF` | `Membership.member`→`organization`; `Directorship.director`→`organization` (`role` on the edge) | Person → Organization |
| `RELATED_TO` | `Family` (`person`/`relative`/`relationship`); `Associate`; `UnknownLink`; `Representation` (`agent`/`client`) | Person ↔ Person or Person ↔ Organization |
| `APPEARS_IN` / `IN_DUMP` | every accepted line | node → Observation → Dump |

Whitelist those types in neighborhood Cypher. Do not walk identity through `gmail.com` or first names. Reconcile stored OS ids against both `id` and `referents` on each refresh ([Identifiers](https://www.opensanctions.org/docs/identifiers/)).

ICIJ mapping if we ever add a second dump: Entity→`Organization`, Officer→`Person` **only when** the name does not look like a company (weak heuristic — ICIJ does not type them), Address→`Address`, Intermediary→`Organization`, `officer_of` + `link` like `director of` / `shareholder of` / `Ultimate Beneficial Owner` → `MEMBER_OF` / `OWNS`, `registered_address` → `LOCATED_AT`. Drop or quarantine `same_name_as` / `similar`.

---

## 5. Attribution text for the admin UI

Show this on every graph / dump screen that renders OpenSanctions-derived nodes. CC BY-NC 4.0 requires **appropriate credit**, a **license link**, and an indication **if you modified** the material; credit means creator name, copyright notice if supplied, license notice, disclaimer, and a link to the material ([CC BY-NC 4.0 deed](https://creativecommons.org/licenses/by-nc/4.0/deed.en)). OpenSanctions: complete database is CC BY-NC 4.0; bulk is free without a key for non-commercial use (hobby / personal research / student); any use inside a for-profit business — including compliance screening — needs a data license ([Free and non-commercial use](https://www.opensanctions.org/docs/commercial/exemption/); [Bulk licensing](https://www.opensanctions.org/docs/bulk/)).

**Required footer (copy):**

> Data transformed by Gaia from the OpenSanctions dataset **US OFAC Specially Designated Nationals (SDN) List** (`us_ofac_sdn`), © OpenSanctions, licensed under [CC BY-NC 4.0](https://creativecommons.org/licenses/by-nc/4.0/). Source: [opensanctions.org/datasets/us_ofac_sdn](https://www.opensanctions.org/datasets/us_ofac_sdn/). Primary source: U.S. Department of the Treasury, Office of Foreign Assets Control, Specially Designated Nationals and Blocked Persons List — [ofac.treasury.gov](https://ofac.treasury.gov/) / [Sanctions List Service](https://ofac.treasury.gov/sanctions-list-service). Entity model: [FollowTheMoney](https://followthemoney.tech/). Snapshot: `{index.version}` exported `{index.last_export}`. Gaia rewrote these records into a property graph; this is **not** an official OFAC or OpenSanctions product. Appearance on a sanctions list is a designation by the issuing authority, not a finding by Gaia. Non-commercial use only unless you hold an OpenSanctions data license.

Also persist per node: `datasets`, `sourceUrl` (deep link, e.g. `https://sanctionssearch.ofac.treas.gov/Details.aspx?id=28033`), `referents`, dump checksum.

If a future dump is ICIJ, the official line is: *“The ICIJ Offshore Leaks Database is licensed under the Open Database License and its contents under Creative Commons Attribution-ShareAlike license. Always cite the International Consortium of Investigative Journalists when using this data.”* ([download page](https://offshoreleaks.icij.org/pages/database), linking [ODbL 1.0](http://opendatacommons.org/licenses/odbl/1.0/) and [CC BY-SA 3.0](http://creativecommons.org/licenses/by-sa/3.0/)). ODbL also requires share-alike on an adapted database ([ODbL summary](https://opendatacommons.org/licenses/odbl/summary/)). Repeat ICIJ’s FAQ disclaimer that inclusion is not an allegation of crime.

---

## 6. Why not the others (short)

- **`default`:** OpenSanctions’ recommended collection ([bulk docs](https://www.opensanctions.org/docs/bulk/)). 4.04M entities / 2.58 GB FtM. Wrong first ingest.
- **`sanctions`:** Right model, 93 lists, 354 MB. Do this after `us_ofac_sdn` parses cleanly.
- **OFAC XML:** Public, curl-able, no NC wrapper. Nested `idList`/`akaList` forces us to invent FtM. Keep as license escape hatch; SLS requires a `User-Agent` or redirects 403 ([OFAC technical notice 2024-05-16](https://ofac.treasury.gov/sdn-list-data-formats-data-schemas/ofac-technical-actions-in-reverse-chronological-order/20240516_44)).
- **ICIJ:** Legal published extract, already a property graph, but leaked-derived, untyped officers, ICIJ-synthesized `same_name_as`, no sanctions/IDs. Second dump, not v1.

Wikidata is already hanging off OpenSanctions person `Q` ids (`referents` / canonical id). Do not dump all of Wikidata for v1.

---

## Sources

- [OpenSanctions — Datasets](https://www.opensanctions.org/datasets/)
- [OpenSanctions — us_ofac_sdn](https://www.opensanctions.org/datasets/us_ofac_sdn/)
- [OpenSanctions — Consolidated Sanctions](https://www.opensanctions.org/datasets/sanctions/)
- [OpenSanctions — Default](https://www.opensanctions.org/datasets/default/)
- [OpenSanctions — Using the bulk data](https://www.opensanctions.org/docs/bulk/)
- [OpenSanctions — Downloading / latest URLs](https://www.opensanctions.org/docs/bulk/updates/)
- [OpenSanctions — FtM JSON format](https://www.opensanctions.org/docs/bulk/json/)
- [OpenSanctions — Simplified CSV](https://www.opensanctions.org/docs/bulk/csv/)
- [OpenSanctions — Entity structure](https://www.opensanctions.org/docs/entities/)
- [OpenSanctions — Identifiers](https://www.opensanctions.org/docs/identifiers/)
- [OpenSanctions — Data dictionary](https://www.opensanctions.org/reference/)
- [OpenSanctions — Metadata](https://www.opensanctions.org/docs/metadata/)
- [OpenSanctions — Free / non-commercial (CC BY-NC 4.0)](https://www.opensanctions.org/docs/commercial/exemption/)
- [OpenSanctions — Licensing](https://www.opensanctions.org/licensing/)
- [us_ofac_sdn index.json](https://data.opensanctions.org/datasets/latest/us_ofac_sdn/index.json)
- [us_ofac_sdn statistics.json](https://data.opensanctions.org/datasets/latest/us_ofac_sdn/statistics.json)
- [default index.json](https://data.opensanctions.org/datasets/latest/default/index.json)
- [sanctions index.json](https://data.opensanctions.org/datasets/latest/sanctions/index.json)
- [FollowTheMoney — Person](https://followthemoney.tech/explorer/schemata/Person/)
- [FollowTheMoney — Identification](https://followthemoney.tech/explorer/schemata/Identification/)
- [FollowTheMoney — Address](https://followthemoney.tech/explorer/schemata/Address/)
- [FollowTheMoney — Sanction](https://followthemoney.tech/explorer/schemata/Sanction/)
- [FollowTheMoney — Ownership](https://followthemoney.tech/explorer/schemata/Ownership/)
- [FollowTheMoney — Membership](https://followthemoney.tech/explorer/schemata/Membership/)
- [CC BY-NC 4.0 deed](https://creativecommons.org/licenses/by-nc/4.0/deed.en)
- [OFAC FAQ 19 — How do I get the SDN List?](https://ofac.treasury.gov/faqs/19)
- [OFAC Sanctions List Service](https://ofac.treasury.gov/sanctions-list-service)
- [OFAC — Advanced XML FAQ](https://ofac.treasury.gov/sdn-list-data-formats-data-schemas/frequently-asked-questions-on-advanced-sanctions-list-standard)
- [OFAC — SLS User-Agent 403 notice](https://ofac.treasury.gov/sdn-list-data-formats-data-schemas/ofac-technical-actions-in-reverse-chronological-order/20240516_44)
- [OFAC DAT_SPEC](https://ofac.treasury.gov/media/29976/download?inline=)
- [ICIJ — How to download](https://offshoreleaks.icij.org/pages/database)
- [ICIJ — FAQ](https://offshoreleaks.icij.org/pages/faq)
- [ICIJ CSV latest zip](https://offshoreleaks-data.icij.org/offshoreleaks/csv/full-oldb.LATEST.zip)
- [ICIJ Neo4j 5.13 dump](https://offshoreleaks-data.icij.org/offshoreleaks/neo4j/icij-offshoreleaks-5.13.0.dump)
- [ICIJ/offshoreleaks-data-packages](https://github.com/ICIJ/offshoreleaks-data-packages)
- [ODbL 1.0](http://opendatacommons.org/licenses/odbl/1.0/)
- [ODbL summary](https://opendatacommons.org/licenses/odbl/summary/)
- [CC BY-SA 3.0](http://creativecommons.org/licenses/by-sa/3.0/)
