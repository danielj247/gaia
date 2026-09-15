<?php

declare(strict_types=1);

namespace App\Graph;

use App\Enums\IdentifierKind;

final readonly class IdentifierId
{
    public static function for(IdentifierKind $kind, string $value): string
    {
        return 'id:'.$kind->value.':'.hash('sha256', mb_strtolower(mb_trim($value)));
    }
}
