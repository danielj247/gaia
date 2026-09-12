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
        'attribution' => 'Entity data from OpenSanctions (https://www.opensanctions.org), derived from the US Treasury OFAC SDN list. Not a leaked credential dump.',
    ],
];
