<?php

declare(strict_types=1);

namespace App\Enums;

enum GraphDriver: string
{
    case Memory = 'memory';
    case Neo4j = 'neo4j';
}
