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
    'ingest' => [
        'chunk_lines' => (int) env('GRAPH_INGEST_CHUNK_LINES', 250),
        'download_timeout' => (int) env('GRAPH_INGEST_DOWNLOAD_TIMEOUT', 900),
    ],
];
