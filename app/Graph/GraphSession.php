<?php

declare(strict_types=1);

namespace App\Graph;

interface GraphSession
{
    /**
     * @param  array<string, mixed>  $parameters
     * @return list<array<string, mixed>>
     */
    public function run(string $statement, array $parameters = []): array;
}
