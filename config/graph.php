<?php

declare(strict_types=1);

return [
    'driver' => env('GRAPH_DRIVER', 'memory'),
    'uri' => env('GRAPH_URI', 'bolt://127.0.0.1:7687'),
    'user' => env('GRAPH_USER', 'neo4j'),
    'password' => env('GRAPH_PASSWORD', ''),
    'database' => env('GRAPH_DATABASE', 'neo4j'),
    'opensanctions' => [
        'base_url' => 'https://data.opensanctions.org/datasets/latest',
        'filename' => 'entities.ftm.json',
        'datasets' => ['us_ofac_sdn', 'sanctions'],
        'attribution' => 'Data transformed by Gaia from OpenSanctions public FollowTheMoney datasets, © OpenSanctions, licensed under CC BY-NC 4.0 (https://creativecommons.org/licenses/by-nc/4.0/). Gaia currently ingests the US OFAC Specially Designated Nationals list (us_ofac_sdn) and the OpenSanctions sanctions collection (sanctions). Dataset pages: https://www.opensanctions.org/datasets/us_ofac_sdn/ and https://www.opensanctions.org/datasets/sanctions/. Primary OFAC source: U.S. Department of the Treasury, Office of Foreign Assets Control (https://ofac.treasury.gov/). Entity model: FollowTheMoney (https://followthemoney.tech/). This is not an official OFAC or OpenSanctions product, not a leaked credential dump, and not a finding by Gaia. Non-commercial use only unless you hold an OpenSanctions data license.',
    ],
    'official' => [
        'lists' => ['uksl', 'ofac_sdn', 'eu_fsf'],
        'user_agent' => env('GRAPH_OFFICIAL_USER_AGENT', 'GaiaSanctionsIngest/1.0'),
        'uksl' => [
            'url' => 'https://sanctionslist.fcdo.gov.uk/docs/UK-Sanctions-List.csv',
            'attribution' => 'Data transformed by Gaia from the UK Sanctions List, published by the Foreign, Commonwealth & Development Office (FCDO). Source: https://www.gov.uk/government/publications/the-uk-sanctions-list. Gaia rewrote these records into a property graph. This is not an official FCDO or OFSI product. Appearance on the list is a designation by the issuing authority, not a finding by Gaia.',
        ],
        'ofac_sdn' => [
            'url' => 'https://sanctionslistservice.ofac.treas.gov/api/PublicationPreview/exports/SDN.XML',
            'attribution' => 'Data transformed by Gaia from the Specially Designated Nationals and Blocked Persons List (SDN), published by the U.S. Department of the Treasury, Office of Foreign Assets Control (OFAC). Source: https://ofac.treasury.gov/sanctions-list-service. Gaia rewrote these records into a property graph. This is not an official OFAC product. Appearance on the list is a designation by the issuing authority, not a finding by Gaia.',
        ],
        'eu_fsf' => [
            'url' => env('GRAPH_EU_FSF_URL', 'https://webgate.ec.europa.eu/fsd/fsf/public/files/xmlFullSanctionsList_1_1/content'),
            'token' => env('GRAPH_EU_FSF_TOKEN'),
            'attribution' => 'Data transformed by Gaia from the EU Financial Sanctions Files (consolidated list of persons, groups and entities subject to EU financial sanctions), published by the European Commission. Source: https://webgate.ec.europa.eu/fsd/fsf. Gaia rewrote these records into a property graph. This is not an official European Commission product. Appearance on the list is a designation by the issuing authority, not a finding by Gaia.',
        ],
    ],
    'companies_house' => [
        'products' => ['ch_companies', 'ch_psc'],
        'user_agent' => env('GRAPH_CH_USER_AGENT', 'GaiaCompaniesHouseIngest/1.0'),
        'download_base' => env('GRAPH_CH_DOWNLOAD_BASE', 'https://download.companieshouse.gov.uk/'),
        'min_free_bytes' => (int) env('GRAPH_CH_MIN_FREE_BYTES', 4 * 1024 * 1024 * 1024),
        'psc_min_free_bytes' => (int) env('GRAPH_CH_PSC_MIN_FREE_BYTES', 24 * 1024 * 1024 * 1024),
        'store_paths' => array_values(array_filter([
            storage_path('app/dumps'),
            env('GRAPH_NEO4J_STORE_PATH'),
        ])),
        'attribution' => 'Data transformed by Gaia from Companies House public register products (Free Company Data Product and/or People with Significant Control snapshot). Source: https://download.companieshouse.gov.uk/ / https://www.gov.uk/guidance/companies-house-data-products. Companies House does not verify filings and imposes no rules or requirements on reuse of public-register information; copyright in filings may remain with the filing company. This is not an official Companies House product.',
        'ch_companies' => [
            'index_url' => env('GRAPH_CH_COMPANIES_INDEX_URL', 'https://download.companieshouse.gov.uk/en_output.html'),
            'url' => env('GRAPH_CH_COMPANIES_URL'),
        ],
        'ch_psc' => [
            'index_url' => env('GRAPH_CH_PSC_INDEX_URL', 'https://download.companieshouse.gov.uk/en_pscdata.html'),
            'url' => env('GRAPH_CH_PSC_URL'),
        ],
    ],
    'ingest' => [
        'chunk_lines' => (int) env('GRAPH_INGEST_CHUNK_LINES', 250),
        'download_timeout' => (int) env('GRAPH_INGEST_DOWNLOAD_TIMEOUT', 900),
    ],
];
