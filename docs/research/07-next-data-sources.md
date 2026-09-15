# Next legal data sources after OpenSanctions

**Date:** 2026-09-15  
**Project:** Gaia — rank the next *legal, public, downloadable* people-and-org datasets after OpenSanctions `us_ofac_sdn` / `sanctions` (already allow-listed in `config/graph.php`).  
**Scope:** Companies House REST vs bulk; other UK public registers that are bulk or API-downloadable; OpenSanctions `gb_*` / PEPs / crime / Companies House enrichers that reuse the existing FollowTheMoney parser; ICIJ’s published extract only.  
**Out of scope:** stolen credential dumps, combo lists, HaveIBeenPwned raw dumps from criminal markets, scraped private accounts, raw Panama Papers (or other leak) files, anything obtained by bypassing access controls. See §5.

Every factual claim below is tied to the page that owns it. If a size or record count is not stated by that owner, it is written **not stated**. Figures were read from official pages on 2026-09-15.

Prior notes: [06-public-osint-dump.md](./06-public-osint-dump.md) (v1 = OpenSanctions; ICIJ published extract is runner-up); [03-osint-people-graph.md](./03-osint-people-graph.md) (Person vs Identifier split).

---

## Verdict

**Do not write a new mapper next.** Gaia already parses FollowTheMoney NDJSON. The cheapest next ingest is another OpenSanctions collection on the same `latest/{dataset}/entities.ftm.json` URL pattern, still under **CC BY-NC 4.0**.

Ranked next sources:

1. **OpenSanctions `crime` (Warrants and Criminal Entities)** — same parser, laptop-sized (**359.33 MB** FtM). Adds UK Companies House disqualified directors, NCA Most Wanted, and Home Office proscribed organisations, which are **not** in the `sanctions` collection. Official profile: [opensanctions.org/datasets/crime](https://www.opensanctions.org/datasets/crime/).
2. **OpenSanctions `gb_fcdo_sanctions` only if Gaia wants a UK-only slice** — **20.87 MB** FtM, 18,543 entities. Already bundled inside allow-listed `sanctions` (93 sources, including “UK FCDO Sanctions List”). Official profile: [opensanctions.org/datasets/gb_fcdo_sanctions](https://www.opensanctions.org/datasets/gb_fcdo_sanctions/); collection membership: [opensanctions.org/datasets/sanctions](https://www.opensanctions.org/datasets/sanctions/).
3. **Official UK Sanctions List (UKSL) CSV/XML** — first *new* mapper, but the license-safer commercial escape hatch for UK designations. OFSI’s Consolidated List **closed 28 January 2026**; UKSL is now the only official UK designations file. Static curl: `https://sanctionslist.fcdo.gov.uk/docs/UK-Sanctions-List.csv` (also `.xml`). [The UK Sanctions List](https://www.gov.uk/government/publications/the-uk-sanctions-list); [single-list guidance](https://www.gov.uk/guidance/moving-to-a-single-list-for-uk-sanctions-designations-28-january-2026).
4. **Companies House Free Company Data Product, then the daily PSC snapshot** — first native UK org/ownership graph. Company CSV is **469 Mb** as one zip (`BasicCompanyDataAsOneFile-2026-09-01.zip`). PSC is daily JSON, 32 zip parts on 2026-09-15 (most **65–69 Mb** each; last two **33 Mb** / **27 Mb**). **No free officers bulk file** — officers are REST-per-company (600 requests / 5 minutes) or a streaming API that is not a snapshot. [CH data products](https://www.gov.uk/guidance/companies-house-data-products); [company download](https://download.companieshouse.gov.uk/en_output.html); [PSC download](https://download.companieshouse.gov.uk/en_pscdata.html); [rate limits](https://developer.company-information.service.gov.uk/developer-guidelines).
5. **Charity Commission for England and Wales full register** (daily JSON / tab-delimited, including `charity_trustee`) plus **OSCR** daily Scottish Charity Register (OGL v3.0). People (trustees) + organisations, laptop-plausible, new mapper. [CCEW download](https://register-of-charities.charitycommission.gov.uk/en/register/full-register-download); [OSCR download](https://www.oscr.org.uk/about-charities/search-the-register/download-the-scottish-charity-register/).
6. **ICIJ published CSV / Neo4j extract** — still the only legal leak-derived path. ICIJ: the public database is “just a fraction of the leaked files”; journalists wanting “the totality of the leaked files” must apply. [FAQ](https://offshoreleaks.icij.org/pages/faq); [download](https://offshoreleaks.icij.org/pages/database).

**Skip as next ingest:** OpenSanctions `peps` (880.81 MB FtM — they discourage using it alone); OpenSanctions `gb_coh_psc` / `kyb` (14.69 GB FtM / 151 million entities); REST-crawling every Companies House officer list; FCA Register Extract (paid); Land Registry CCOD/OCOD (account + extra address licence); Find Case Law bulk computational analysis (needs a separate licence); Individual Insolvency Register (search UI only); stolen credential dumps.

---

## 1. Comparison

| | OS `crime` | OS `gb_fcdo_sanctions` | Official UKSL | CH Free Company Data | CH PSC snapshot | CCEW register | ICIJ published extract |
| --- | --- | --- | --- | --- | --- | --- | --- |
| What it is | 60-source crime / wanted / disqualification collection, FtM | UK Sanctions List rewritten as FtM | FCDO designations under SAMLA | Monthly CSV of live companies | Daily JSON of people with significant control | Daily charity + trustee extract | ICIJ’s structured extract of Panama / Paradise / Bahamas / Offshore / Pandora |
| Official profile | [datasets/crime](https://www.opensanctions.org/datasets/crime/) | [datasets/gb_fcdo_sanctions](https://www.opensanctions.org/datasets/gb_fcdo_sanctions/) | [UK Sanctions List](https://www.gov.uk/government/publications/the-uk-sanctions-list) | [en_output.html](https://download.companieshouse.gov.uk/en_output.html); [data products](https://www.gov.uk/guidance/companies-house-data-products) | [en_pscdata.html](https://download.companieshouse.gov.uk/en_pscdata.html) | [full-register-download](https://register-of-charities.charitycommission.gov.uk/en/register/full-register-download) | [offshoreleaks.icij.org/pages/database](https://offshoreleaks.icij.org/pages/database) |
| Stable URL | `…/latest/crime/entities.ftm.json` | `…/latest/gb_fcdo_sanctions/entities.ftm.json` | `https://sanctionslist.fcdo.gov.uk/docs/UK-Sanctions-List.csv` (also `.xml`, `.html`, `.txt`, `.pdf`, `.odt`, `.ods`) | `https://download.companieshouse.gov.uk/` dated zip names | Same host, dated `persons-with-significant-control-snapshot-YYYY-MM-DD.zip` | Per-table zip links on the download page | `https://offshoreleaks-data.icij.org/offshoreleaks/csv/full-oldb.LATEST.zip` |
| Format | NDJSON FtM | NDJSON FtM | CSV / XML / HTML / TXT / PDF / ODT / ODS | ZIP of CSV | ZIP of JSON | ZIP of JSON or tab-delimited | CSV nodes + relationships; Neo4j `.dump` |
| Size (2026-09-15, owner-stated) | FtM **359.33 MB** | FtM **20.87 MB**; `source.csv` 47.62 MB | **not stated** | One file **469 Mb**; 7 parts 69+70+70+70+70+70+48 Mb | One-file zip size **not stated**; 32 parts mostly 65–69 Mb, last two 33 Mb / 27 Mb | **not stated** | See note 06 (2026-09-12): zip 71,935,075 B |
| People + extras (owner-stated) | People 179,183; Legal entities 46,825; Companies 19,035; plus wallets, articles, orgs, addresses, vessels | People 3,997; Organizations 1,897; Vessels 664; Companies 411. Total 18,543 | **not stated** (fields cover Individual / Entity / Ship) | Live-company basics only; **no officers** in this product. Record count **not stated** | Record count **not stated**. OS KYB rewrite of this source: People 7,508,473; Companies 6,468,403 ([gb_coh_psc](https://www.opensanctions.org/datasets/gb_coh_psc/)) | Tables include `charity` and `charity_trustee`. Counts **not stated** | Officers 771,368 (person **or** company); see note 06 |
| License | OS layer **CC BY-NC 4.0**; commercial use needs a paid data license ([exemption](https://www.opensanctions.org/docs/commercial/exemption/); [bulk](https://www.opensanctions.org/docs/bulk/)) | Same OS layer. Upstream is FCDO / GOV.UK | GOV.UK publication. Explicit licence line on the UKSL page: **not stated** in the retrieved HTML. Withdrawn OFSI list was OGL v3.0 | CH **does not** publish register data under OGL. No use restrictions from CH; third-party copyright; take legal advice ([public task](https://www.gov.uk/government/publications/companies-house-accreditation-to-information-fair-traders-scheme/public-task-copyright-and-crown-copyright); [data products](https://www.gov.uk/guidance/companies-house-data-products)) | Same as other CH register data | Developer portal: **OGL v3.0** except where otherwise stated ([api-portal](https://api-portal.charitycommission.gov.uk/)) | Database **ODbL 1.0**; contents **CC BY-SA** ([download](https://offshoreleaks.icij.org/pages/database)) |
| Laptop hours? | Yes | Yes | Yes (new mapper) | Yes for companies. PSC zips are ~2 GB compressed (sum of stated part sizes); unzipped JSON **not stated** | Tight | Yes if the zips stay small (sizes **not stated**) | Zip small; mapper is the bottleneck (note 06) |
| Fit to Gaia FtM split | Native | Native | Must invent Person / Identifier / Sanction | Organization + Address + `company_number` Identifier only | Person / Organization + Ownership + Address. Month/year DOB on the public record | Organization + Person (trustee name) + weak Identifier | Officers untyped; no Identification/Sanction schemata |

OpenSanctions still recommend `default` plus `topics` / `datasets` filters rather than a single-source file ([Using the bulk data](https://www.opensanctions.org/docs/bulk/)). That is correct for a screening product. For Gaia it fights the laptop-hours constraint, same as note 06. `default` is **1,980,819** entities on the dataset index ([datasets](https://www.opensanctions.org/datasets/)).

---

## 2. Companies House (UK)

Companies House is an executive agency of the Department for Business and Trade. The public REST API “lets you retrieve information about limited companies (and other companies that fall within the Companies Act 2006)”. Data is “live and real-time”. ([Developer Hub](https://developer.company-information.service.gov.uk/))

### 2.1 REST API

**Auth.** Register a Companies House user account, create an application, then an API key, stream key, or OAuth web client. Every request needs credentials. API / stream keys use HTTP Basic: key as username, password blank. ([Get started](https://developer.company-information.service.gov.uk/get-started); [Authentication](https://developer.company-information.service.gov.uk/authentication); [How to create an application](https://developer.company-information.service.gov.uk/how-to-create-an-application))

```bash
curl -XGET -u my_api_key: https://api.company-information.service.gov.uk/company/00000006
```

OAuth 2.0 bearer tokens are for end-user filing / account actions, not for a public-register dump. ([Authentication](https://developer.company-information.service.gov.uk/authentication))

**Rate limits.** “You can make up to 600 requests within a 5 minute period.” Over the limit: HTTP `429` until the window resets. Higher limits: contact them. They “reserve the right to ban without notice applications that regularly exceed or attempt to bypass the rate limits.” TLS only; TLS 1.2 recommended. ([Developer guidelines](https://developer.company-information.service.gov.uk/developer-guidelines))

**Graph-relevant endpoints (official specs).**

| Resource | Request | Notes |
| --- | --- | --- |
| Company profile | `GET /company/{company_number}` | `company_name`, `company_number`, `company_status`, `type`, `sic_codes`, `registered_office_address`, `previous_company_names`, `date_of_creation`, links to officers / PSC / insolvency ([companyProfile](https://developer-specs.company-information.service.gov.uk/companies-house-public-data-api/resources/companyprofile)) |
| Officers | `GET /company/{company_number}/officers` | Paginated. `name`, `officer_role`, `appointed_on`, `resigned_on`, `nationality`, `occupation`, `address`, `date_of_birth.month` / `.year`, optional `person_number`, `identification.registration_number` for corporate officers ([list](https://developer-specs.company-information.service.gov.uk/companies-house-public-data-api/reference/officers/list); [officerList](https://developer-specs.company-information.service.gov.uk/companies-house-public-data-api/resources/officerlist)) |
| PSC list | `GET /company/{company_number}/persons-with-significant-control` | Paginated ([list](https://developer-specs.company-information.service.gov.uk/companies-house-public-data-api/reference/persons-with-significant-control/list)) |
| Individual PSC | `GET /company/{company_number}/persons-with-significant-control/individual/{notification_id}` | `name` / `name_elements`, `natures_of_control`, `notified_on`, `ceased_on`, service `address`, `date_of_birth` month/year (day optional) ([individual](https://developer-specs.company-information.service.gov.uk/companies-house-public-data-api/resources/individual)) |
| Disqualified officers | search + get natural / corporate | Separate officer-disqualifications resources on the same public data API |

**Why REST is the wrong next *dump*.** There is no “download all officers” REST operation. At 600 requests / 5 minutes, walking every company for `/officers` is a multi-week crawl, not a laptop-hours ingest. Use REST to refresh one company after a bulk snapshot, not to build the graph.

**Personal data on the public record.** Usual residential address and the **day** of date of birth are not placed on the public register. Month and year of birth are. Credit-reference agencies may receive full DOB / URA under the Companies Act unless the person has protection. From July 2025 people can apply to protect date of birth, signature, business occupation and residential address. ([Personal information charter](https://www.gov.uk/government/organisations/companies-house/about/personal-information-charter); [annual report 2025–26](https://www.gov.uk/government/publications/companies-house-annual-report-and-accounts-2025-to-2026/companies-house-annual-report-and-accounts-2025-to-2026))

### 2.2 Bulk products

Official catalogue: [Companies House data products](https://www.gov.uk/guidance/companies-house-data-products) (last updated 25 October 2022).

#### Free Company Data Product

- “Downloadable data snapshot containing basic company data of **live** companies on the register.” ZIP of CSV; one large file or multiple parts. “Provided free of charge and will not be supported.” ([en_output.html](https://download.companieshouse.gov.uk/en_output.html))
- Compiled to the end of the previous month; published within 5 working days of month end. ([en_output.html](https://download.companieshouse.gov.uk/en_output.html); [data products](https://www.gov.uk/guidance/companies-house-data-products))
- Snapshot dated **2026-09-01** on the download page: `BasicCompanyDataAsOneFile-2026-09-01.zip` **(469Mb)**; parts 1–7 = 69+70+70+70+70+70+48 Mb. ([en_output.html](https://download.companieshouse.gov.uk/en_output.html))
- Fields the GOV.UK product page names: company type, registered office address, SIC, status (live / dissolved), last / next accounts or confirmation statement, previous company names. Full field list: PDF linked from the download page (filename on that page: **not stated** in the HTML). ([data products](https://www.gov.uk/guidance/companies-house-data-products); [en_output.html](https://download.companieshouse.gov.uk/en_output.html))
- Record count: **not stated**.
- Officers: **not in this product**.

#### PSC snapshot

- “Full list of PSC’s provided to Companies House.” JSON. One file or multiple files. Overwritten daily. Free, unsupported. Updated every morning before 10am GMT; contents compiled to the end of the previous day. ([en_pscdata.html](https://download.companieshouse.gov.uk/en_pscdata.html); [data products](https://www.gov.uk/guidance/companies-house-data-products))
- Snapshot dated **2026-09-15**: `persons-with-significant-control-snapshot-2026-09-15.zip` (one-file size **not stated**); 32 parts, sizes as listed in §1. ([en_pscdata.html](https://download.companieshouse.gov.uk/en_pscdata.html))
- Field list / examples: linked from that page (“details of the PSC Streaming API, a list of the data fields… and example data files”). Exact field-list URL: follow the link on that page. REST `individual` resource above is the documented public field shape.
- Record count: **not stated** by Companies House.

#### Officers bulk

- **No officers product** on [data products](https://www.gov.uk/guidance/companies-house-data-products) or `download.companieshouse.gov.uk`.
- `officerList.person_number` is documented as “Unique person identifier as displayed in bulk products 195, 198, 208, 209 and 216” — those are **paid** XML-gateway / bulk products, not the free snapshot. ([officerList](https://developer-specs.company-information.service.gov.uk/companies-house-public-data-api/resources/officerlist))
- XML gateway: £4.70 / month subscription plus per-request charges; 15% invoice discount; basic company information free. ([data products](https://www.gov.uk/guidance/companies-house-data-products))

#### Accounts data product

- Daily / monthly ZIP of electronically filed accounts (iXBRL / XBRL). “About 60% of the 2.2 million accounts filed at Companies House each year.” Not a people graph. ([data products](https://www.gov.uk/guidance/companies-house-data-products))

#### URI service

- `http://data.companieshouse.gov.uk/doc/company/{companynumber}` — HTML / RDF / JSON / XML / CSV / YAML of basic details. Not a bulk dump. ([data products](https://www.gov.uk/guidance/companies-house-data-products))

### 2.3 Streaming API

- Host: `https://stream.companieshouse.gov.uk`. Long-running HTTP GET; one JSON envelope per line; heartbeats are blank lines. Same resource bodies as the REST API, plus `event.timepoint`, `resource_id`, `resource_kind`, `resource_uri`. ([overview](https://developer-specs.company-information.service.gov.uk/streaming-api/guides/overview))
- Streams: `/companies`, `/filings`, `/insolvency-cases`, `/charges`, `/officers`, `/persons-with-significant-control`, `/disqualified-officers`, `/company-exemptions`, `/persons-with-significant-control-statements`. ([reference](https://developer-specs.company-information.service.gov.uk/streaming-api/reference))
- **Not a snapshot.** “You cannot use it to obtain a complete copy of the data.” CH “produces snapshot datasets” to import, then you resume from the snapshot `timepoint`. Company and PSC stream parameter docs still say “In the future data snapshots will be available.” ([overview](https://developer-specs.company-information.service.gov.uk/streaming-api/guides/overview); [companies stream](https://developer-specs.company-information.service.gov.uk/streaming-api/reference/company-information/stream?v=latest); [PSC stream](https://developer-specs.company-information.service.gov.uk/streaming-api/reference/persons-with-significant-control/stream))
- Auth: **stream keys are not interchangeable with REST API keys**. Register a Streaming API application. Basic auth. Max **two concurrent connections** per account. Slow readers and extra connections are dropped. `429` → wait one minute. ([authentication](https://developer-specs.company-information.service.gov.uk/streaming-api/guides/authentication); [overview](https://developer-specs.company-information.service.gov.uk/streaming-api/guides/overview))
- Sandbox does not run streaming APIs. ([API testing](https://developer.company-information.service.gov.uk/api-testing))

**Practical Gaia path:** ingest Free Company Data + PSC snapshot with a new mapper; use streaming (or REST) only to keep those tables current. Do not start with officers REST.

### 2.4 Licence / terms

Companies House “impose[s] no rules or requirements on how the information on the public register is used” and is “not responsible for your use”. You must comply with data protection, copyright and other law. ([data products](https://www.gov.uk/guidance/companies-house-data-products))

Split in the public-task statement ([public task, copyright and Crown copyright](https://www.gov.uk/government/publications/companies-house-accreditation-to-information-fair-traders-scheme/public-task-copyright-and-crown-copyright)):

- **Material produced by CH** (guidance, website, statistical tables): Crown copyright, **OGL v3.0** except where otherwise stated. Credit CH. Do not reproduce the Crown insignia / Royal Arms.
- **Material on the public register:** supplied under CDPA 1988 s.47 and Database Regulations SI 1997/3032 Sch. 1. Copyright in filings belongs to the company (or its agents), not the Registrar. “Companies House imposes no rules or requirements on how the information on the public register is used.” Bulk customers “must take their own legal advice regarding possible breach of third party copyright.”

CH-produced pages on GOV.UK are OGL; **register dumps are not OGL**. A 2014 FOI release states the same in those words: “CH does not make public information, including the Free Data Product, available under the Open Government Licence (OGL).” Treat that FOI as historical confirmation, not the current licence page.

### 2.5 Field → Gaia (Companies House)

Keep the locked split from [03-osint-people-graph.md](./03-osint-people-graph.md): fuzzy `Person` (UUIDv4) vs hash-keyed `Identifier`.

| Gaia label | CH source | Stable key | Notes |
| --- | --- | --- | --- |
| `Organization` | Free Company Data row / `companyProfile` | `company_number` as `sourceId` | Also previous names as properties, not extra Person nodes |
| `Person` | PSC `individual-*`; officer `director` / `secretary` / `llp-member` / etc. | Prefer `person_number` when present; else do **not** merge on name | Month/year DOB is a search property, not a merge key. Corporate officers (`corporate-director`, …) are `Organization` |
| `Identifier` | `company_number`; PSC/officer `identification.registration_number`; explode email/phone if ever present (usually absent) | UUIDv5(`type` + normalized value) | Public CH does not publish passport numbers |
| `Address` | `registered_office_address` / officer or PSC service `address` | Hash of normalised lines | Not usual residential address (not on the public record) |
| `Directorship` / `MEMBER_OF` | Officer `officer_role` + `appointed_on` / `resigned_on` | Appointment `links.self` | Edge Person\|Organization → Organization |
| `Ownership` / `OWNS` | PSC `natures_of_control` + `notified_on` / `ceased_on` | Notification id | Individual PSC → company; corporate PSC is org→org |
| `Dump` / `Observation` | the ingest file | checksum | `parserName=companies-house-csv` or `companies-house-psc-json` |

Do not treat PSC “super secure” persons as graph-visible people.

---

## 3. Other UK (and UK-graph-relevant) public registers

| Source | Official URL | Format | License | Laptop? | Gaia map | Why / why not next |
| --- | --- | --- | --- | --- | --- | --- |
| **UK Sanctions List (FCDO)** | [publication](https://www.gov.uk/government/publications/the-uk-sanctions-list); [format guide](https://www.gov.uk/guidance/format-guide-for-the-uk-sanctions-list); [single list 28 Jan 2026](https://www.gov.uk/guidance/moving-to-a-single-list-for-uk-sanctions-designations-28-january-2026). Files: `https://sanctionslist.fcdo.gov.uk/docs/UK-Sanctions-List.{csv,xml,html,txt,pdf,odt,ods}` | 7 static formats; fields include Unique ID, historic OFSI Group ID, names, Individual/Entity/Ship, regime, sanctions imposed, addresses, phone/email/website, DOB, national ID, passport, position, business registration number, IMO | Licence line on the UKSL page: **not stated** in the retrieved HTML | Yes | Person / Organization / Vessel; Identifier (`uksl_unique_id`, passport, national id, `company_number`); Address; Sanction | **Best official UK sanctions file.** OpenSanctions already wraps it as `gb_fcdo_sanctions` inside `sanctions`. Ingest raw UKSL only to drop CC BY-NC. Size/count **not stated** by FCDO |
| **OFSI Consolidated List** | [withdrawn publication](https://www.gov.uk/government/publications/financial-sanctions-consolidated-list-of-targets) | Historic CSV/HTML | That page: OGL v3.0 except where otherwise stated | n/a | — | **Closed 28 January 2026.** Do not ingest as current. Historic Group IDs remain on UKSL for pre-2026-01-28 designations |
| **OFSI / HMT investment bans** | Separate Russia Schedule 2 list, still published; OS: [gb_hmt_invbans](https://www.opensanctions.org/datasets/gb_hmt_invbans/) (22 entities / 11 legal entities) | HTML on GOV.UK | See that GOV.UK page | Yes | Organization + Sanction | Tiny; already in OS `sanctions`. Official page title: “Russia: list of entities named in relation to financial and investment restrictions” ([UKSL details](https://www.gov.uk/government/publications/the-uk-sanctions-list)) |
| **Charity Commission (England & Wales)** | [Full register download](https://register-of-charities.charitycommission.gov.uk/en/register/full-register-download); [API docs](https://register-of-charities.charitycommission.gov.uk/en/documentation-on-the-api); [developer portal](https://api-portal.charitycommission.gov.uk/) | Daily JSON **or** tab-delimited zips: `charity`, `charity_trustee`, names, classification, annual returns, … Search export cap **5,000** rows | Portal: **OGL v3.0** except where otherwise stated | Likely (sizes **not stated**) | Organization (charity number); Person (`trustee_name`); `MEMBER_OF`; Identifier(`charity_number`) | **Strong v1.2 people+org dump** after CH. API needs a key; bulk download does not. Trustee API fields: `name`, `is_chair`, `date_of_appointment`, cross-charity numbers ([data definition PDF](https://api-portal.charitycommission.gov.uk/content/API_data_definition_v1.1.pdf)) |
| **OSCR (Scotland)** | [Download the Scottish Charity Register](https://www.oscr.org.uk/about-charities/search-the-register/download-the-scottish-charity-register/); [Crown copyright / OGL](https://www.oscr.org.uk/misc-pages/crown-copyright/); [API](https://www.oscr.org.uk/about-charities/search-the-register/download-the-scottish-charity-register/oscr-public-apis/) | Daily full register + 5-year returns + former charities. Format of the file: **not stated** on the download page (follow “download details”). BETA API: `GET https://oscrapi.azurewebsites.net/api/all_charities` (apply; key in header; no marketing) | **OGL v3.0**. Required attribution on the download T&Cs. No direct marketing; do not republish as “the” Scottish Charity Register. Crown database right 2006 | Yes (size **not stated**) | Organization + Identifier(`oscr_number`) | Pair with CCEW. API is application-gated |
| **CCNI (Northern Ireland)** | Commission site [charitycommissionni.org.uk](https://www.charitycommissionni.org.uk/); register dataset on [Open Data NI](https://admin.opendatani.gov.uk/en_GB/dataset/register-of-charities) (updated daily; contact `admin@charitycommissionni.org.uk`) | Open Data NI resource format: follow that dataset page | Open Data NI listing uses UK OGL in the data.gov.uk mirror; confirm on the dataset licence field (one National Data Library row showed “TBD” — treat licence as **verify on the file**) | Unknown (size **not stated**) | Organization | Third UK charity register. Weaker documentation than CCEW/OSCR. Not next |
| **FCA Financial Services Register** | [FS Register](https://www.fca.org.uk/firms/financial-services-register); [RES extract](https://www.fca.org.uk/firms/financial-services-register/data-extract); [RES handbook](https://www.fca.org.uk/publication/documents/register-extract-handbook.pdf); API signup `https://register.fca.org.uk/Developer/s/` | Free API: one entity per query, **50 requests / 10 seconds**, no SLA, no premium raise. Bulk = paid Register Extract Service (firms, or firms+individuals) | RES is a paid licensed extract (FCA + SDM fees; handbook). API “currently free of charge” | API yes for lookups; full register **no** without paying | Organization + Person (Directory / approved individuals) | **Not next.** Weekly firms extract £4,467.83 (FCA £2,599 + SDM £1,868.83) on the extract page |
| **HM Land Registry Price Paid** | [PPD downloads](https://www.gov.uk/government/statistical-data-sets/price-paid-data-downloads); [about PPD](https://www.gov.uk/guidance/about-the-price-paid-data); [Use land and property data](https://use-land-property-data.service.gov.uk/) | CSV / text; complete file from 1 Jan 1995; yearly files **115–230 MB**; portal lists a current-month file **30.5MB CSV (estimated)** | **OGL v3.0** with required attribution. Address fields include OS AddressBase / Royal Mail PAF — personal/non-commercial display of prices is permitted; other address reuse needs Royal Mail | Yes | Address + transaction. **No Person names** (“Price paid information is not personal, but property-related”) | Weak people graph. Useful later as Address enrichment only |
| **UK / overseas companies that own property (ex-CCOD / OCOD)** | [Use land and property data](https://use-land-property-data.service.gov.uk/); [OCOD dataset](https://use-land-property-data.service.gov.uk/datasets/ocod); [API docs](https://use-land-property-data.service.gov.uk/api-documentation) | CSV (full + change-only), monthly, second working day. Account + agree licence. API key after that | Dataset-specific licence (address / PAF third-party rights). Not the bare OGL of PPD | After registration: yes (example historic full file **1.23 MB** in API docs — current size **not stated** on the live dataset card beyond that example) | Organization + Address + optional price | Graph-relevant (company → property). Extra licence friction. After CH companies, not before |
| **The Gazette** | [Re-using our data](https://www.thegazette.co.uk/data); [formats](https://www.thegazette.co.uk/data/formats); [longitudinal](https://www.thegazette.co.uk/data/longitudinal-datasets); [paid data service](https://www.thegazette.co.uk/all-notices/content/103871) | Linked-data URIs (JSON-LD, RDF, XML, …); SPARQL; some free longitudinal dumps; FTP RDF dumps; paid XLS/CSV notice feeds | Crown copyright / **OGL**, “unless stated otherwise”. “This licence does not cover the re-use of personal data.” Fair-use policy | SPARQL / notice-by-notice yes; all-notices dump is the paid service (London daily **£8,539** ex VAT on the 2023 price table) | Organization / Person + insolvency / deceased-estate Document | Good provenance edges later. Personal-data caveat + paid bulk. Not next |
| **Electoral Commission donations** | [Political Finance Online](https://www.electoralcommission.org.uk/political-registration-and-regulation/financial-reporting/political-finance-online); search UI `https://search.electoralcommission.org.uk/` | Search + filters for donations, loans, spending, accounts. NI donations/loans before **1 July 2017** are not published | Licence for a full dump: **not stated** on the overview page. No official “download entire database” URL found | Search/export of filtered views; full-dump size **not stated** | Person / Organization (donor, donee) + donation edge | People-graph relevant, but not a documented bulk file. Not next |
| **Find Case Law** | [What you can do freely](https://caselaw.nationalarchives.gov.uk/what-you-can-do-freely); [terms](https://caselaw.nationalarchives.gov.uk/terms-of-use); [using records](https://caselaw.nationalarchives.gov.uk/using-find-case-law-records) | Website + API; judgments as HTML / LegalDocML XML / PDF | **Open Justice Licence**: read, quote, commercial incorporate of individual judgments. **Computational analysis / programmatic bulk search to extract or enrich contents needs a separate licence.** No external indexing (robots.txt). Excessive request volume may be blocked | Individual downloads yes; Gaia-scale NLP over the corpus **no** without the extra licence | Document (judgment) + cited Person/Organization — high mapper cost | Not next. Do not scrape the collection |
| **UK Parliament members / interests** | [Developer hub](https://developer.parliament.uk/) (Open Parliament Licence); [Members API](https://members-api.parliament.uk/index.html); legacy [MNIS query](http://data.parliament.uk/membersdataplatform/memberquery.aspx) | REST / XML. Interests via MNIS `Interests` output or linked-data endpoints | **Open Parliament Licence** (hub statement) | Yes (member counts are small) | Person + Position + interest edges | Already inside OpenSanctions `peps` as “UK House of Commons” (1,687) and “UK House of Lords” (1,135). Prefer OS FtM over a second mapper unless Gaia goes commercial |
| **Insolvency Service** | [IIR search](https://www.insolvencydirect.bis.gov.uk/eiir/); [GOV.UK search guide](https://www.gov.uk/government/publications/find-insolvent-people-and-companies/search-for-people-or-companies-in-insolvency-proceedings); [API catalogue](https://www.api.gov.uk/is/) | Name search for bankruptcies / IVAs / DROs. Records usually removed within 3 months of completion. Catalogue lists only Debt Respite (Breathing Space), not IIR | IIR T&Cs on that site | Search only — **no bulk file** | Person (if we ever typed a single result) | **Not ingestible as a dump.** Company insolvency is on the CH API / stream instead |
| **GLEIF LEI** | [Concatenated files](https://www.gleif.org/en/lei-data/gleif-concatenated-file/download-the-concatenated-file); [about](https://www.gleif.org/en/lei-data/gleif-concatenated-file/about-the-concatenated-file/); [terms of use](https://www.gleif.org/en/lei-data/gleif-concatenated-file/download-the-concatenated-file) (page: “take note of the LEI Data Terms of Use”) | Daily ZIP of XML. 2026-09-15: Level 1 **3,430,859** LEIs / **514.87 MB**; RR **669,219** / **36.23 MB**; reporting exceptions **6,189,999** / **47.67 MB** | Free download; bind to GLEIF **LEI Data Terms of Use** (do not paraphrase beyond that) | Yes for Level 1 + RR | Organization + Identifier(`lei`) + org→org `OWNS`. **No natural persons** (Open Ownership say this explicitly when they map GLEIF to BODS) | Excellent Identifier backbone after CH `company_number`. Not a people dump. OS also ships `gleif` inside `kyb` (12,671,471 entities) |
| **OpenOwnership BODS tools** | [bods-data.openownership.org](https://bods-data.openownership.org/); [UK BODS news](https://www.openownership.org/en/news/united-kingdom-beneficial-ownership-data-available-in-line-with-latest-version-of-global-standard/); [user guidance](https://www.openownership.org/en/publications/beneficial-ownership-data-analysis-tools/user-guidance/) | UK PSC + Register of Overseas Entities mapped to BODS 0.4; CSV / SQLite / PostgreSQL / Parquet / JSON. Datasette `uk_version_0_4`: **162,006,481** rows / 16 tables | “Open licence” / republished for reuse (news). GLEIF mapping they publish is **CC0 1.0** | The UK BODS table count is a warehouse, not a laptop first pass | Same as CH PSC, already transformed | **Derivative of CH**, not a new primary register. Prefer official CH JSON unless Gaia wants BODS |

**OpenSanctions already wraps UK sanctions.** `sanctions` lists “UK FCDO Sanctions List” (6,969 searchable) and “UK HMT/OFSI Investment Bans” (11). It does **not** list CH disqualified directors, NCA Most Wanted, or proscribed organisations — those sit in `crime`. `gb_hmt_sanctions` returns **404** (deprecated; changelog told users to switch to `gb_fcdo_sanctions` by 28 January 2026). ([sanctions](https://www.opensanctions.org/datasets/sanctions/); [changelog 38](https://www.opensanctions.org/changelog/38/))

---

## 4. OpenSanctions datasets that reuse the existing FtM parser

Allow-list today: `us_ofac_sdn`, `sanctions` (`config/graph.php`). Same download pattern as note 06:

`https://data.opensanctions.org/datasets/latest/{dataset}/entities.ftm.json`  
`https://data.opensanctions.org/datasets/latest/{dataset}/index.json`

Bulk is free without a key for **non-commercial** use. Complete database: **CC BY-NC 4.0**. Any use inside a for-profit business — including compliance screening — needs a data license. ([Free and non-commercial use](https://www.opensanctions.org/docs/commercial/exemption/); [Using the bulk data](https://www.opensanctions.org/docs/bulk/))

### 4.1 UK / `gb_*` (checked 2026-09-15)

| Dataset | In `sanctions`? | Entities / FtM size (OS page) | Notes |
| --- | --- | --- | --- |
| `gb_fcdo_sanctions` | Yes | 18,543 / **20.87 MB** | Current UK designations. Source data: GOV.UK HTML ([page](https://www.opensanctions.org/datasets/gb_fcdo_sanctions/)) |
| `gb_hmt_invbans` | Yes | 22 / size on page: **not stated** in the fetch beyond entity counts | Russia investment-restriction names ([page](https://www.opensanctions.org/datasets/gb_hmt_invbans/)) |
| `gb_hmt_sanctions` | — | — | **404.** Replaced by `gb_fcdo_sanctions` ([changelog](https://www.opensanctions.org/changelog/38/)) |
| `gb_proscribed_orgs` | No (`crime` + `default`) | 192 / 94 orgs | Home Office Schedule 2; OS: “Data is provided under the Open Government License” ([page](https://www.opensanctions.org/datasets/gb_proscribed_orgs/)) |
| `gb_coh_disqualified` | No (`crime` + `default`) | 33,500 (People 8,787; Companies 6,144; Orgs 1,370) | CH / Insolvency Service disqualified directors via `api.company-information.service.gov.uk` ([page](https://www.opensanctions.org/datasets/gb_coh_disqualified/)) |
| `gb_nca_most_wanted` | No (`crime` + `default`) | 21 people / **17.73 KB** FtM | NCA HTML ([page](https://www.opensanctions.org/datasets/gb_nca_most_wanted/)) |
| `gb_coh_psc` | No — **KYB only** | 22,287,350; People 7,508,473; Companies 6,468,403. FtM **14.69 GB** | Full CH PSC rewrite. Weekly. Not de-duplicated. **Not laptop v1.2** ([page](https://www.opensanctions.org/datasets/gb_coh_psc/)) |
| `ext_gb_coh_psc` | Enricher inside `default` | 641 total / 191 people / 21 targets (one snapshot of that page) | Only PSC rows **linked to a risk entity**. Wrong as a UK company graph; already in `default` if Gaia ever takes `default` ([page](https://www.opensanctions.org/datasets/ext_gb_coh_psc/)) |

UK PEPs inside `peps`: “UK House of Commons” 1,687; “UK House of Lords” 1,135. ([peps](https://www.opensanctions.org/datasets/peps/))

### 4.2 Collections

| Collection | Entities | FtM size | Why / why not |
| --- | --- | --- | --- |
| `sanctions` (already allow-listed) | 300,924 (People 41,783) | **348.3 MB** | Do this before adding more OS collections if it is not ingested yet. 93 sources ([page](https://www.opensanctions.org/datasets/sanctions/)) |
| `crime` | 516,788 (People 179,183) | **359.33 MB** | **Next allow-list.** 60 sources including the three UK lists above ([page](https://www.opensanctions.org/datasets/crime/)) |
| `peps` | 1,950,284 (People 719,280; Positions 210,181) | **880.81 MB** | “IMPORTANT: We discourage the use of this collection on its own, as it does not include some relevant information from enrichment sources.” 202 sources ([page](https://www.opensanctions.org/datasets/peps/)) |
| `default` | 1,980,819 on the index card | **not re-fetched** here (note 06: 2.41 GB on 2026-09-12) | Still the OS-recommended product; still too large for a first extra pass ([datasets](https://www.opensanctions.org/datasets/)) |
| `kyb` | 151,632,405 | per-source (UK PSC 14.69 GB) | “roughly 50× more entities than the default”. Monthly. Not de-duplicated. Delivery catalog uses `delivery.opensanctions.com` + token in their yente example ([kyb docs](https://www.opensanctions.org/docs/kyb/); [kyb collection](https://www.opensanctions.org/datasets/kyb/)) |

Zero new mapper means: add the dataset id to `config/graph.php` `opensanctions.datasets` and reuse `graph:ingest-opensanctions`. Do **not** enable `gb_coh_psc` / `kyb` that way on a laptop.

---

## 5. ICIJ Offshore Leaks — still the only legal leak-derived path

Confirmed 2026-09-15 on ICIJ’s own pages:

- The **published** database may be downloaded as CSV zip or Neo4j 4 / 5 dumps. Licence: **ODbL** (database) + **CC BY-SA** (contents). “Always cite the International Consortium of Investigative Journalists.” ([download](https://offshoreleaks.icij.org/pages/database); [FAQ](https://offshoreleaks.icij.org/pages/faq))
- Zip still covers Offshore Leaks (2013), Panama Papers (2016), Bahamas Leaks (2016), Paradise Papers (2017), Pandora Papers (2021). ([download](https://offshoreleaks.icij.org/pages/database))
- “The information contained in the ICIJ Offshore Leaks Database is **just a fraction of the leaked files**.” Inclusion “is not intended to suggest or imply that they have engaged in illegal or improper conduct.” ([FAQ](https://offshoreleaks.icij.org/pages/faq))
- An **Officer** is “A person or company who plays a role in an offshore entity.” Still no person/org flag. ([FAQ](https://offshoreleaks.icij.org/pages/faq))
- Journalists wanting “the **totality of the leaked files**” email `data@icij.org` and are vetted. That path is **not** Gaia’s ingest. ([FAQ](https://offshoreleaks.icij.org/pages/faq))

There is still **no** legal public dump of the raw Mossack / Appleby / Alcogal / etc. file stores. Gaia may ingest ICIJ’s extract (second dump, new mapper) and must not ingest “Panama Papers files” obtained any other way.

OpenSanctions KYB also redistributes `icij_offshoreleaks` (1,614,277 entities) as FtM — same published extract, plus CC BY-NC on the OS layer. Prefer ICIJ’s own zip if the goal is ODbL rather than OS NC. ([kyb collection](https://www.opensanctions.org/datasets/kyb/))

---

## 6. Explicitly out of scope

These are **not** next sources. Do not ingest, scrape, buy, or “just parse a copy someone has”:

- Stolen credential dumps, combo lists, stealer logs, or “OSINT” password collections.
- HaveIBeenPwned **raw** dumps circulated from criminal markets (HIBP’s own public service is a k-anonymity API, not a people-graph dump; it is not evaluated here as an ingest).
- Scraped private / authenticated accounts, inboxes, social DMs, or any source that requires bypassing access controls, CAPTCHAs-as-auth, or ToS login walls.
- Raw Panama Papers, Paradise Papers, Pandora Papers, or other leak file trees — including journalist workbench copies that are not ICIJ’s published CSV/Neo4j package.
- Companies House (or any other) data obtained by ignoring `429` bans, forging keys, or using another user’s API credential.
- Find Case Law full-text mining without the computational-analysis licence.

This matches [03-osint-people-graph.md](./03-osint-people-graph.md): Gaia processes dumps the operator is legally allowed to hold.

---

## 7. Field → Gaia (all next sources)

Same node/edge rules as note 06 §4. Additions only:

| Gaia label | New source field | Notes |
| --- | --- | --- |
| `Person` | UKSL Individual; CH individual PSC / natural officer; CCEW/OSCR trustee name; OS `crime` / `peps` `schema=Person` | Never key on name. CH `person_number` is the only CH-issued person id on the public officer resource |
| `Organization` | UKSL Entity / Ship; CH company; charity; GLEIF legal entity; Gazette company notice subject | Vessels can stay Organization + `kind` (note 06) |
| `Identifier` | UKSL Unique ID, OFSI Group ID (historic), passport, national id, IMO, `company_number`, charity number, LEI | Hash-keyed. Explode multi-valued UKSL passport / national-id columns |
| `Address` | UKSL Address Line 1–6; CH ROA / service address; PPD address fields (no names) | |
| `Sanction` | UKSL row (regime + sanctions imposed + Unique ID) or OS `Sanction` | Prefer OS if still on FtM; official UKSL if dropping NC |
| `Directorship` | CH officer; CCEW trustee (`is_chair`, `date_of_appointment`) | |
| `Ownership` | CH PSC `natures_of_control`; GLEIF Level 2 RR (org→org only) | |
| `Document` | Gazette notice; Find Case Law judgment (if ever licensed) | |

Whitelist neighbourhood types stay: `HAS_IDENTIFIER`, `LOCATED_AT`, `SANCTIONED_UNDER`, `OWNS`, `MEMBER_OF`, `RELATED_TO`, `APPEARS_IN`. Do not walk identity through company-number check-digits or first names.

---

## 8. Attribution text for the admin UI

**If the next dump is still OpenSanctions** (`crime`, `gb_fcdo_sanctions`, `peps`): reuse the note 06 footer, swap the dataset name / URL, keep CC BY-NC 4.0 credit, creator, licence link, and “if you modified” ([CC BY-NC 4.0 deed](https://creativecommons.org/licenses/by-nc/4.0/deed.en); [exemption](https://www.opensanctions.org/docs/commercial/exemption/)).

**If the dump is official UKSL:**

> Data transformed by Gaia from the **UK Sanctions List**, published by the Foreign, Commonwealth & Development Office. Source: [gov.uk/government/publications/the-uk-sanctions-list](https://www.gov.uk/government/publications/the-uk-sanctions-list). Snapshot: `{downloaded_at}`. Gaia rewrote these records into a property graph. This is not an official FCDO or OFSI product. Appearance on the list is a designation by the issuing authority, not a finding by Gaia.

**If the dump is Companies House:**

> Data transformed by Gaia from Companies House public register products (Free Company Data Product and/or People with Significant Control snapshot). Source: [download.companieshouse.gov.uk](https://download.companieshouse.gov.uk/) / [Companies House data products](https://www.gov.uk/guidance/companies-house-data-products). Companies House does not verify filings and “imposes no rules or requirements” on reuse of public-register information; copyright in filings may remain with the filing company. This is not an official Companies House product. Snapshot: `{filename}`.

**If the dump is CCEW / OSCR:** credit the commission/regulator and **OGL v3.0**. OSCR’s required line: “© Crown Copyright and database right [year]. Contains information from the Scottish Charity Register supplied by the Office of the Scottish Charity Regulator and licensed under the Open Government Licence v.3.0.” ([OSCR download](https://www.oscr.org.uk/about-charities/search-the-register/download-the-scottish-charity-register/))

**If the dump is ICIJ:** keep the official sentence from note 06 / the download page, plus the FAQ disclaimer that inclusion is not an allegation of crime.

**If the dump is GLEIF:** follow the LEI Data Terms of Use and GLEIF’s required attribution on that terms page (do not invent a footer here).

**If the dump is Price Paid:** “Contains HM Land Registry data © Crown copyright and database right 2021. This data is licensed under the Open Government Licence v3.0.” ([PPD](https://www.gov.uk/government/statistical-data-sets/price-paid-data-downloads))

---

## 9. Why not the others (short)

- **`gb_fcdo_sanctions` as a second OS ingest:** already inside `sanctions`. Only allow-list it alone if Gaia wants a UK-only graph without the other 92 lists.
- **`peps`:** official “do not use this collection on its own.” 881 MB. Use `default`+filter if Gaia ever leaves the laptop constraint.
- **`gb_coh_psc` / `kyb`:** FtM, but 14.69 GB / 151 M entities. Wrong next step. Ingest official CH snapshots instead if the goal is a UK company graph without OS NC on the CH facts.
- **CH officers via REST:** 600 / 5 min. Not a dump.
- **CH streaming without a snapshot:** explicitly not a complete copy; officer/company stream docs still say snapshots are “in the future.”
- **FCA RES:** paid. API is look-up only.
- **PPD:** no people.
- **CCOD/OCOD:** account + address licence.
- **Gazette paid feed / IIR:** not a free bulk people dump.
- **Find Case Law corpus:** Open Justice Licence forbids unlicensed computational analysis.
- **Electoral Commission:** no documented full-register file.
- **OpenOwnership UK BODS:** CH in another schema; 162 M Datasette rows.
- **Raw leaks / stealer dumps:** illegal or out of ICIJ’s published licence; see §5–§6.

---

## Sources

- [Companies House — Developer Hub](https://developer.company-information.service.gov.uk/)
- [Companies House — Get started](https://developer.company-information.service.gov.uk/get-started)
- [Companies House — Authentication](https://developer.company-information.service.gov.uk/authentication)
- [Companies House — How to create an application](https://developer.company-information.service.gov.uk/how-to-create-an-application)
- [Companies House — Developer guidelines (rate limits)](https://developer.company-information.service.gov.uk/developer-guidelines)
- [Companies House — API testing / sandbox](https://developer.company-information.service.gov.uk/api-testing)
- [Companies House — REST overview](https://developer.company-information.service.gov.uk/overview)
- [Companies House — companyProfile](https://developer-specs.company-information.service.gov.uk/companies-house-public-data-api/resources/companyprofile)
- [Companies House — officers list](https://developer-specs.company-information.service.gov.uk/companies-house-public-data-api/reference/officers/list)
- [Companies House — officerList](https://developer-specs.company-information.service.gov.uk/companies-house-public-data-api/resources/officerlist)
- [Companies House — PSC list](https://developer-specs.company-information.service.gov.uk/companies-house-public-data-api/reference/persons-with-significant-control/list)
- [Companies House — PSC individual](https://developer-specs.company-information.service.gov.uk/companies-house-public-data-api/resources/individual)
- [Companies House — Streaming overview](https://developer-specs.company-information.service.gov.uk/streaming-api/guides/overview)
- [Companies House — Streaming reference](https://developer-specs.company-information.service.gov.uk/streaming-api/reference)
- [Companies House — Streaming authentication](https://developer-specs.company-information.service.gov.uk/streaming-api/guides/authentication)
- [Companies House — companies stream](https://developer-specs.company-information.service.gov.uk/streaming-api/reference/company-information/stream?v=latest)
- [Companies House — PSC stream](https://developer-specs.company-information.service.gov.uk/streaming-api/reference/persons-with-significant-control/stream)
- [GOV.UK — Companies House data products](https://www.gov.uk/guidance/companies-house-data-products)
- [download.companieshouse.gov.uk — Free Company Data](https://download.companieshouse.gov.uk/en_output.html)
- [download.companieshouse.gov.uk — PSC snapshot](https://download.companieshouse.gov.uk/en_pscdata.html)
- [GOV.UK — CH public task, copyright and Crown copyright](https://www.gov.uk/government/publications/companies-house-accreditation-to-information-fair-traders-scheme/public-task-copyright-and-crown-copyright)
- [GOV.UK — CH personal information charter](https://www.gov.uk/government/organisations/companies-house/about/personal-information-charter)
- [GOV.UK — CH annual report 2025 to 2026](https://www.gov.uk/government/publications/companies-house-annual-report-and-accounts-2025-to-2026/companies-house-annual-report-and-accounts-2025-to-2026)
- [GOV.UK — The UK Sanctions List](https://www.gov.uk/government/publications/the-uk-sanctions-list)
- [GOV.UK — Format guide for the UK Sanctions List](https://www.gov.uk/guidance/format-guide-for-the-uk-sanctions-list)
- [GOV.UK — Moving to a single list, 28 January 2026](https://www.gov.uk/guidance/moving-to-a-single-list-for-uk-sanctions-designations-28-january-2026)
- [GOV.UK — Withdrawn OFSI consolidated list](https://www.gov.uk/government/publications/financial-sanctions-consolidated-list-of-targets)
- [Charity Commission — Full register download](https://register-of-charities.charitycommission.gov.uk/en/register/full-register-download)
- [Charity Commission — API documentation](https://register-of-charities.charitycommission.gov.uk/en/documentation-on-the-api)
- [Charity Commission — Developer portal](https://api-portal.charitycommission.gov.uk/)
- [Charity Commission — API data definition](https://api-portal.charitycommission.gov.uk/content/API_data_definition_v1.1.pdf)
- [OSCR — Download the Scottish Charity Register](https://www.oscr.org.uk/about-charities/search-the-register/download-the-scottish-charity-register/)
- [OSCR — Crown copyright / OGL](https://www.oscr.org.uk/misc-pages/crown-copyright/)
- [OSCR — Public APIs](https://www.oscr.org.uk/about-charities/search-the-register/download-the-scottish-charity-register/oscr-public-apis/)
- [CCNI](https://www.charitycommissionni.org.uk/)
- [Open Data NI — Register of Charities](https://admin.opendatani.gov.uk/en_GB/dataset/register-of-charities)
- [FCA — Financial Services Register](https://www.fca.org.uk/firms/financial-services-register)
- [FCA — Register extract](https://www.fca.org.uk/firms/financial-services-register/data-extract)
- [FCA — RES subscribers’ handbook](https://www.fca.org.uk/publication/documents/register-extract-handbook.pdf)
- [GOV.UK — Price Paid Data](https://www.gov.uk/government/statistical-data-sets/price-paid-data-downloads)
- [GOV.UK — About Price Paid Data](https://www.gov.uk/guidance/about-the-price-paid-data)
- [HM Land Registry — Use land and property data](https://use-land-property-data.service.gov.uk/)
- [HM Land Registry — OCOD](https://use-land-property-data.service.gov.uk/datasets/ocod)
- [HM Land Registry — API documentation](https://use-land-property-data.service.gov.uk/api-documentation)
- [The Gazette — Re-using our data](https://www.thegazette.co.uk/data)
- [The Gazette — Data formats](https://www.thegazette.co.uk/data/formats)
- [The Gazette — Longitudinal datasets](https://www.thegazette.co.uk/data/longitudinal-datasets)
- [The Gazette — Data service pricing](https://www.thegazette.co.uk/all-notices/content/103871)
- [Electoral Commission — Political Finance Online](https://www.electoralcommission.org.uk/political-registration-and-regulation/financial-reporting/political-finance-online)
- [Find Case Law — What you can do freely](https://caselaw.nationalarchives.gov.uk/what-you-can-do-freely)
- [Find Case Law — Terms of use](https://caselaw.nationalarchives.gov.uk/terms-of-use)
- [Find Case Law — Using records](https://caselaw.nationalarchives.gov.uk/using-find-case-law-records)
- [UK Parliament — Developer hub](https://developer.parliament.uk/)
- [UK Parliament — Members API](https://members-api.parliament.uk/index.html)
- [UK Parliament — MNIS member query](http://data.parliament.uk/membersdataplatform/memberquery.aspx)
- [Individual Insolvency Register](https://www.insolvencydirect.bis.gov.uk/eiir/)
- [GOV.UK — Search insolvency proceedings](https://www.gov.uk/government/publications/find-insolvent-people-and-companies/search-for-people-or-companies-in-insolvency-proceedings)
- [GOV.UK API catalogue — Insolvency Service](https://www.api.gov.uk/is/)
- [GLEIF — Download concatenated files](https://www.gleif.org/en/lei-data/gleif-concatenated-file/download-the-concatenated-file)
- [GLEIF — About concatenated files](https://www.gleif.org/en/lei-data/gleif-concatenated-file/about-the-concatenated-file/)
- [Open Ownership — BODS data tools](https://bods-data.openownership.org/)
- [Open Ownership — UK BODS 0.4 announcement](https://www.openownership.org/en/news/united-kingdom-beneficial-ownership-data-available-in-line-with-latest-version-of-global-standard/)
- [Open Ownership — BODS tools user guidance](https://www.openownership.org/en/publications/beneficial-ownership-data-analysis-tools/user-guidance/)
- [OpenSanctions — Datasets](https://www.opensanctions.org/datasets/)
- [OpenSanctions — Consolidated Sanctions](https://www.opensanctions.org/datasets/sanctions/)
- [OpenSanctions — crime](https://www.opensanctions.org/datasets/crime/)
- [OpenSanctions — peps](https://www.opensanctions.org/datasets/peps/)
- [OpenSanctions — kyb collection](https://www.opensanctions.org/datasets/kyb/)
- [OpenSanctions — KYB docs](https://www.opensanctions.org/docs/kyb/)
- [OpenSanctions — gb_fcdo_sanctions](https://www.opensanctions.org/datasets/gb_fcdo_sanctions/)
- [OpenSanctions — gb_hmt_invbans](https://www.opensanctions.org/datasets/gb_hmt_invbans/)
- [OpenSanctions — gb_proscribed_orgs](https://www.opensanctions.org/datasets/gb_proscribed_orgs/)
- [OpenSanctions — gb_coh_disqualified](https://www.opensanctions.org/datasets/gb_coh_disqualified/)
- [OpenSanctions — gb_nca_most_wanted](https://www.opensanctions.org/datasets/gb_nca_most_wanted/)
- [OpenSanctions — gb_coh_psc](https://www.opensanctions.org/datasets/gb_coh_psc/)
- [OpenSanctions — ext_gb_coh_psc](https://www.opensanctions.org/datasets/ext_gb_coh_psc/)
- [OpenSanctions — UK lists consolidation changelog](https://www.opensanctions.org/changelog/38/)
- [OpenSanctions — Using the bulk data](https://www.opensanctions.org/docs/bulk/)
- [OpenSanctions — Free / non-commercial (CC BY-NC 4.0)](https://www.opensanctions.org/docs/commercial/exemption/)
- [CC BY-NC 4.0 deed](https://creativecommons.org/licenses/by-nc/4.0/deed.en)
- [ICIJ — How to download](https://offshoreleaks.icij.org/pages/database)
- [ICIJ — FAQ](https://offshoreleaks.icij.org/pages/faq)
- [OGL v3.0](https://www.nationalarchives.gov.uk/doc/open-government-licence/version/3/)
- [06-public-osint-dump.md](./06-public-osint-dump.md)
- [03-osint-people-graph.md](./03-osint-people-graph.md)
