<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Dump;
use RuntimeException;

final readonly class FetchOfficialList
{
    public function __construct(
        private FetchUkSanctionsList $fetchUk,
        private FetchOfacSdn $fetchOfac,
        private FetchEuFsf $fetchEu,
    ) {}

    public function handle(string $list, ?int $limit): Dump
    {
        $list = mb_trim($list);
        $allowed = config('graph.official.lists');

        $names = $this->listNames($allowed);

        if ($list === '' || ! in_array($list, $names, true)) {
            throw new RuntimeException(
                'List must be a published official sanctions list: '.($names === [] ? 'uksl' : implode(', ', $names)).'.',
            );
        }

        return match ($list) {
            'uksl' => $this->fetchUk->handle($limit),
            'ofac_sdn' => $this->fetchOfac->handle($limit),
            'eu_fsf' => $this->fetchEu->handle($limit),
            default => throw new RuntimeException(
                'List must be a published official sanctions list: '.implode(', ', $names).'.',
            ),
        };
    }

    /**
     * @return list<string>
     */
    private function listNames(mixed $allowed): array
    {
        if (! is_array($allowed)) {
            return [];
        }

        $names = [];

        foreach ($allowed as $name) {
            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }
}
