<?php

declare(strict_types=1);

namespace App\Enums;

enum DumpStatus: string
{
    case Pending = 'pending';
    case Downloading = 'downloading';
    case Ingesting = 'ingesting';
    case Completed = 'completed';
    case Failed = 'failed';
}
