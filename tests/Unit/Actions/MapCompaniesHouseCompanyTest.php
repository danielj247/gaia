<?php

declare(strict_types=1);

use App\Actions\MapCompaniesHouseCompany;
use App\Enums\GraphEdgeType;
use App\Enums\GraphNodeLabel;
use App\Enums\IdentifierKind;
use App\Graph\IdentifierId;

it('maps a free company data row onto an organization with a company number and address', function (): void {
    $mapped = resolve(MapCompaniesHouseCompany::class)->handle([
        'CompanyName' => 'EXAMPLE ONE LTD',
        'CompanyNumber' => '6',
        'RegAddress.AddressLine1' => '100 Example Street',
        'RegAddress.PostTown' => 'London',
        'RegAddress.Country' => 'United Kingdom',
        'RegAddress.PostCode' => 'EC1A 1BB',
        'CompanyCategory' => 'Private Limited Company',
        'CompanyStatus' => 'Active',
        'CountryOfOrigin' => 'United Kingdom',
        'IncorporationDate' => '01/01/2000',
        'PreviousName_1.CompanyName' => 'OLD ONE LTD',
        'PreviousName_1.CONDATE' => '01/01/2010',
        'SICCode.SicText_1' => '62020 - Information technology consultancy activities',
        'URI' => 'http://business.data.gov.uk/id/company/00000006',
    ], 'dump-1');

    $nodes = collect($mapped['nodes']);
    $edges = collect($mapped['edges']);
    $org = $nodes->firstWhere('label', GraphNodeLabel::Organization);

    expect($org)->not->toBeNull()
        ->and($org['id'])->toBe('00000006')
        ->and($org['properties']['sourceId'])->toBe('00000006')
        ->and($org['properties']['name'])->toBe('EXAMPLE ONE LTD')
        ->and($org['properties']['previousNames'])->toContain('OLD ONE LTD')
        ->and($nodes->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Person))->toBeFalse()
        ->and($nodes->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Identifier
            && $node['id'] === IdentifierId::for(IdentifierKind::CompanyNumber, '00000006')
            && $node['properties']['kind'] === IdentifierKind::CompanyNumber->value))->toBeTrue()
        ->and($nodes->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Address))->toBeTrue()
        ->and($edges->contains(fn (array $edge): bool => $edge['type'] === GraphEdgeType::AppearsInDump
            && $edge['toId'] === 'dump-1'))->toBeTrue()
        ->and($edges->contains(fn (array $edge): bool => $edge['type'] === GraphEdgeType::HasIdentifier))->toBeTrue()
        ->and($edges->contains(fn (array $edge): bool => $edge['type'] === GraphEdgeType::LocatedAt
            && $edge['toLabel'] === GraphNodeLabel::Address))->toBeTrue();
});

it('maps pdf-style registered office fields and an integer company number', function (): void {
    $mapped = resolve(MapCompaniesHouseCompany::class)->handle([
        'CompanyNumber' => 6,
        'CompanyName' => 'PDF FIELDS LTD',
        'Careof' => 'The Occupier',
        'AddressLine1' => '10 Field Street',
        'PostTown' => 'Leeds',
        'PostCode' => 'LS1 1AA',
        'CountryofOrigin' => 'United Kingdom',
        'SICCode1' => '62020 - Information technology consultancy activities',
    ], 'dump-1');

    $org = collect($mapped['nodes'])->firstWhere('label', GraphNodeLabel::Organization);

    expect($org['id'] ?? null)->toBe('00000006')
        ->and($org['properties']['sicCodes'] ?? null)->toContain('62020')
        ->and(collect($mapped['nodes'])->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Address
            && str_contains((string) $node['properties']['value'], '10 Field Street')))->toBeTrue();
});

it('skips a company row without a company number', function (): void {
    expect(resolve(MapCompaniesHouseCompany::class)->handle(['CompanyName' => 'NO NUMBER LTD'], 'dump-1'))
        ->toBe(['nodes' => [], 'edges' => []])
        ->and(resolve(MapCompaniesHouseCompany::class)->handle([
            'CompanyNumber' => ['not a string'],
            'CompanyName' => ['not a string'],
        ], 'dump-1'))->toBe(['nodes' => [], 'edges' => []])
        ->and(resolve(MapCompaniesHouseCompany::class)->handle(['id' => 'ch_companies'], 'dump-1'))
        ->toBe(['nodes' => [], 'edges' => []])
        ->and(resolve(MapCompaniesHouseCompany::class)->handle(['id' => 'ch_psc'], 'dump-1'))
        ->toBe(['nodes' => [], 'edges' => []])
        ->and(App\Graph\CompaniesHouseFields::padCompanyNumber(''))->toBe('');
});

it('maps a company number only row without an address or country', function (): void {
    $mapped = resolve(MapCompaniesHouseCompany::class)->handle([
        'CompanyNumber' => 'OC123456',
        'PreviousName_1.CompanyName' => 'OLD LLP',
    ], 'dump-3');

    $nodes = collect($mapped['nodes']);

    expect($nodes->firstWhere('label', GraphNodeLabel::Organization)['id'] ?? null)->toBe('OC123456')
        ->and($nodes->firstWhere('label', GraphNodeLabel::Organization)['properties']['previousNames'] ?? null)->toBe('OLD LLP')
        ->and($nodes->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Address))->toBeFalse()
        ->and($nodes->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Country))->toBeFalse();
});

it('reads converted company fields including a joined address and previous names', function (): void {
    $mapped = resolve(MapCompaniesHouseCompany::class)->handle([
        'id' => 'SC123456',
        'name' => 'EXAMPLE TWO LTD',
        'address' => '1 Castle Terrace, Edinburgh',
        'previousNames' => 'OLD TWO LTD',
        'sicCodes' => '82990 - Other business support service activities n.e.c.',
        'companyStatus' => 'Active',
        'companyCategory' => 'Private Limited Company',
        'countryOfOrigin' => 'United Kingdom',
        'incorporationDate' => '02/02/2001',
        'dissolutionDate' => '',
        'uri' => 'http://business.data.gov.uk/id/company/SC123456',
    ], 'dump-2');

    $org = collect($mapped['nodes'])->firstWhere('label', GraphNodeLabel::Organization);

    expect($org['id'] ?? null)->toBe('SC123456')
        ->and($org['properties']['previousNames'] ?? null)->toBe('OLD TWO LTD')
        ->and($org['properties']['sicCodes'] ?? null)->toContain('82990');
});
