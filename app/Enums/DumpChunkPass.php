<?php

declare(strict_types=1);

namespace App\Enums;

enum DumpChunkPass: string
{
    case Entities = 'entities';
    case Intervals = 'intervals';
}
