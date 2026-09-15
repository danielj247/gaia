<?php

declare(strict_types=1);

use App\Actions\MapCompaniesHousePsc;
use App\Enums\GraphEdgeType;
use App\Enums\GraphNodeLabel;
use App\Enums\IdentifierKind;
use App\Graph\IdentifierId;

function companiesHousePscRecord(int $line): array
{
    $decoded = json_decode(
        explode("\n", mb_trim((string) file_get_contents(base_path('tests/Fixtures/companies-house/psc.sample.jsonl'))))[$line],
        true,
    );

    expect($decoded)->toBeArray();

    return $decoded;
}

it('maps an individual psc onto a person, company stub, owns edge and service address', function (): void {
    $mapped = resolve(MapCompaniesHousePsc::class)->handle(companiesHousePscRecord(0), 'dump-1');
    $nodes = collect($mapped['nodes']);
    $edges = collect($mapped['edges']);
    $person = $nodes->firstWhere('label', GraphNodeLabel::Person);

    expect($person)->not->toBeNull()
        ->and($person['id'])->toBe('ch-psc:notifAda1')
        ->and($person['properties']['sourceId'])->toBe('ch-psc:notifAda1')
        ->and($person['properties']['name'])->toBe('Ada Example')
        ->and($person['properties']['birthDate'])->toBe('1970-01')
        ->and($nodes->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Organization
            && $node['id'] === '00000006'))->toBeTrue()
        ->and($nodes->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Identifier
            && $node['id'] === IdentifierId::for(IdentifierKind::CompanyNumber, '00000006')))->toBeTrue()
        ->and($nodes->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Address))->toBeTrue()
        ->and($edges->contains(fn (array $edge): bool => $edge['type'] === GraphEdgeType::Owns
            && $edge['fromId'] === 'ch-psc:notifAda1'
            && $edge['toId'] === '00000006'
            && ($edge['properties']['endDate'] ?? null) === '2020-01-01'))->toBeTrue()
        ->and($edges->contains(fn (array $edge): bool => $edge['type'] === GraphEdgeType::CitizenOf))->toBeTrue();
});

it('maps a corporate psc onto an owner organization and not a person', function (): void {
    $mapped = resolve(MapCompaniesHousePsc::class)->handle(companiesHousePscRecord(1), 'dump-1');
    $nodes = collect($mapped['nodes']);

    expect($nodes->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Person))->toBeFalse()
        ->and($nodes->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Organization
            && $node['id'] === 'ch-psc:notifCorp1'
            && $node['properties']['name'] === 'EXAMPLE HOLDINGS LTD'))->toBeTrue()
        ->and($nodes->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Identifier
            && $node['properties']['kind'] === IdentifierKind::RegistrationNumber->value
            && $node['properties']['value'] === '12345678'))->toBeTrue()
        ->and(collect($mapped['edges'])->contains(fn (array $edge): bool => $edge['type'] === GraphEdgeType::Owns
            && $edge['fromId'] === 'ch-psc:notifCorp1'
            && $edge['toId'] === '00000006'))->toBeTrue();
});

it('builds a person from name elements and an etag when the self link is missing', function (): void {
    $mapped = resolve(MapCompaniesHousePsc::class)->handle([
        'company_number' => '6',
        'data' => [
            'kind' => 'individual-person-with-significant-control',
            'name_elements' => [
                'title' => 'Ms',
                'forename' => 'Ada',
                'middle_name' => 'B',
                'surname' => 'Example',
            ],
            'etag' => 'etag-only',
            'address' => '100 Example Street, London',
            'natures_of_control' => 'ownership-of-shares-75-to-100-percent',
            'notified_on' => '2016-04-06',
            'birthDate' => '1970-01',
        ],
    ], 'dump-1');

    $person = collect($mapped['nodes'])->firstWhere('label', GraphNodeLabel::Person);

    expect($person['id'] ?? null)->toBe('ch-psc:etag-only')
        ->and($person['properties']['name'] ?? null)->toBe('Ms Ada B Example')
        ->and($person['properties']['birthDate'] ?? null)->toBe('1970-01');
});

it('maps a legal person psc and country of registration', function (): void {
    $mapped = resolve(MapCompaniesHousePsc::class)->handle(companiesHousePscRecord(2), 'dump-1');
    $nodes = collect($mapped['nodes']);

    expect($nodes->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Organization
        && $node['id'] === 'ch-psc:notifLegal1'))->toBeTrue()
        ->and($nodes->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Person))->toBeFalse()
        ->and(collect($mapped['edges'])->contains(fn (array $edge): bool => $edge['type'] === GraphEdgeType::Owns))->toBeTrue();
});

it('returns an empty graph when a psc row has no company number or notification id', function (): void {
    $mapper = resolve(MapCompaniesHousePsc::class);

    expect($mapper->handle([
        'data' => ['kind' => 'individual-person-with-significant-control', 'name' => 'Nobody'],
    ], 'dump-1'))->toBe(['nodes' => [], 'edges' => []])
        ->and($mapper->handle([
            'company_number' => '00000006',
            'data' => ['kind' => 'individual-person-with-significant-control', 'name' => 'Nobody'],
        ], 'dump-1'))->toBe(['nodes' => [], 'edges' => []]);

    $nameless = $mapper->handle([
        'company_number' => '00000006',
        'id' => 'ch-psc:etag-nameless',
        'data' => [
            'kind' => 'individual-person-with-significant-control',
            'etag' => 'etag-nameless',
            'date_of_birth' => ['year' => 1970],
            'name_elements' => 'not-an-array',
            'natures_of_control' => [],
            'links' => ['self' => '/'],
            'address' => ['premises' => ''],
        ],
    ], 'dump-1');

    $person = collect($nameless['nodes'])->firstWhere('label', GraphNodeLabel::Person);

    expect($person['properties']['name'] ?? null)->toBe('ch-psc:etag-nameless')
        ->and($person['properties']['birthDate'] ?? null)->toBeNull();

    $emptyNames = $mapper->handle([
        'company_number' => '00000006',
        'data' => [
            'kind' => 'individual-person-with-significant-control',
            'etag' => 'empty-names',
            'name_elements' => ['forename' => '', 'surname' => ''],
        ],
    ], 'dump-1');

    expect(collect($emptyNames['nodes'])->firstWhere('label', GraphNodeLabel::Person)['properties']['name'] ?? null)
        ->toBe('ch-psc:empty-names');

    $flat = $mapper->handle([
        'kind' => 'individual-person-with-significant-control',
        'company_number' => '00000006',
        'etag' => 'flat-etag',
        'name' => 'Flat Person',
        'naturesOfControl' => 'ownership-of-shares-75-to-100-percent',
        'startDate' => '2016-04-06',
        'endDate' => '2021-01-01',
        'identification' => 12,
        'address' => 12,
    ], 'dump-1');

    $owns = collect($flat['edges'])->firstWhere('type', GraphEdgeType::Owns);

    expect(collect($flat['nodes'])->firstWhere('label', GraphNodeLabel::Person)['id'] ?? null)->toBe('ch-psc:flat-etag')
        ->and($owns['properties']['startDate'] ?? null)->toBe('2016-04-06')
        ->and($owns['properties']['endDate'] ?? null)->toBe('2021-01-01');

    $registered = $mapper->handle([
        'company_number' => '00000007',
        'data' => [
            'kind' => 'corporate-entity-person-with-significant-control',
            'name' => 'REG OWNER LTD',
            'etag' => 'reg-owner',
            'identification' => [
                'registration_number' => 99,
                'country_registered' => 'Ireland',
            ],
        ],
    ], 'dump-1');

    expect(collect($registered['nodes'])->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Identifier
        && $node['properties']['value'] === '99'))->toBeTrue()
        ->and(collect($registered['edges'])->contains(fn (array $edge): bool => $edge['type'] === GraphEdgeType::LocatedAt
            && ($edge['properties']['field'] ?? null) === 'country_registered'))->toBeTrue();
});

it('creates no person from super-secure, statement, exemption or totals rows', function (): void {
    $mapper = resolve(MapCompaniesHousePsc::class);

    foreach ([3, 4, 5, 6] as $line) {
        $mapped = $mapper->handle(companiesHousePscRecord($line), 'dump-1');

        expect(collect($mapped['nodes'])->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Person))->toBeFalse()
            ->and(collect($mapped['edges'])->contains(fn (array $edge): bool => $edge['type'] === GraphEdgeType::Owns))->toBeFalse();
    }
});
