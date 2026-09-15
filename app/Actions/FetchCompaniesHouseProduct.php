<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Dump;
use RuntimeException;

final readonly class FetchCompaniesHouseProduct
{
    public function __construct(
        private FetchCompaniesHouseCompanies $fetchCompanies,
        private FetchCompaniesHousePsc $fetchPsc,
    ) {}

    public function handle(string $product, ?int $limit, ?int $part = null): Dump
    {
        $product = mb_trim($product);
        $names = $this->productNames();

        if ($product === '' || ! in_array($product, $names, true)) {
            throw new RuntimeException(
                'Product must be a published Companies House product: '.($names === [] ? 'ch_companies, ch_psc' : implode(', ', $names)).'.',
            );
        }

        return match ($product) {
            'ch_companies' => $this->fetchCompanies->handle($limit),
            'ch_psc' => $this->fetchPsc->handle($limit, $part),
            default => throw new RuntimeException(
                'Product must be a published Companies House product: '.implode(', ', $names).'.',
            ),
        };
    }

    /**
     * @return list<string>
     */
    private function productNames(): array
    {
        $allowed = config('graph.companies_house.products');

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
