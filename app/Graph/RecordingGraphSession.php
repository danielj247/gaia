<?php

declare(strict_types=1);

namespace App\Graph;

final class RecordingGraphSession implements GraphSession
{
    /**
     * @var list<array{statement: string, parameters: array<string, mixed>}>
     */
    private array $statements = [];

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function __construct(private array $rows = []) {}

    /**
     * @return list<array{statement: string, parameters: array<string, mixed>}>
     */
    public function statements(): array
    {
        return $this->statements;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return list<array<string, mixed>>
     */
    public function run(string $statement, array $parameters = []): array
    {
        $this->statements[] = [
            'statement' => $statement,
            'parameters' => $parameters,
        ];

        return $this->rows;
    }
}
