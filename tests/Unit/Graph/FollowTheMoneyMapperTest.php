<?php

declare(strict_types=1);

use App\Enums\GraphEdgeType;
use App\Enums\GraphNodeLabel;
use App\Graph\FollowTheMoneyMapper;

it('maps a person with identifiers address country and sanction', function (): void {
    $mapper = new FollowTheMoneyMapper();

    $mapped = $mapper->map([
        'id' => 'ofac-100',
        'caption' => 'Ada Example',
        'schema' => 'Person',
        'properties' => [
            0 => 'skip-int-key',
            'name' => 'Ada Example',
            'alias' => ['A. Example'],
            'nationality' => ['us'],
            'email' => ['ada@example.test'],
            'address' => ['1 Test Street'],
            'topics' => ['sanction'],
            'programId' => ['OFAC-SDN'],
        ],
    ], 'dump-1');

    expect($mapped['nodes'])->not->toBeEmpty()
        ->and(collect($mapped['nodes'])->firstWhere('label', GraphNodeLabel::Person)['id'])->toBe('ofac-100')
        ->and(collect($mapped['nodes'])->firstWhere('label', GraphNodeLabel::Person)['properties']['sourceId'])->toBe('ofac-100')
        ->and(collect($mapped['edges'])->contains(fn (array $edge): bool => $edge['type'] === GraphEdgeType::HasIdentifier))->toBeTrue()
        ->and(collect($mapped['edges'])->contains(fn (array $edge): bool => $edge['type'] === GraphEdgeType::CitizenOf))->toBeTrue()
        ->and(collect($mapped['edges'])->contains(fn (array $edge): bool => $edge['type'] === GraphEdgeType::LocatedAt))->toBeTrue()
        ->and(collect($mapped['edges'])->contains(fn (array $edge): bool => $edge['type'] === GraphEdgeType::SanctionedUnder))->toBeTrue()
        ->and(collect($mapped['edges'])->contains(fn (array $edge): bool => $edge['type'] === GraphEdgeType::AppearsInDump))->toBeTrue();
});

it('maps ownership intervals without creating nodes', function (): void {
    $mapper = new FollowTheMoneyMapper();

    $mapped = $mapper->map([
        'id' => 'rel-1',
        'schema' => 'Ownership',
        'properties' => [
            'owner' => ['ofac-100'],
            'asset' => ['ofac-200'],
        ],
    ], 'dump-1');

    expect($mapper->isInterval(['schema' => 'Ownership']))->toBeTrue()
        ->and($mapper->isInterval(['schema' => 'Person']))->toBeFalse()
        ->and($mapper->isInterval([]))->toBeFalse()
        ->and($mapped['nodes'])->toBeEmpty()
        ->and($mapped['edges'])->toHaveCount(1)
        ->and($mapped['edges'][0]['type'])->toBe(GraphEdgeType::Owns)
        ->and($mapped['edges'][0]['fromId'])->toBe('ofac-100')
        ->and($mapped['edges'][0]['toId'])->toBe('ofac-200');
});

it('unwraps object-shaped interval references', function (): void {
    $mapper = new FollowTheMoneyMapper();

    $mapped = $mapper->map([
        'id' => 'rel-obj',
        'schema' => 'Ownership',
        'properties' => [
            'owner' => [12, ['id' => 'ofac-100', 'caption' => 'Ada'], ['id' => '']],
            'asset' => [['id' => 'ofac-200']],
        ],
    ], 'dump-1');

    expect($mapped['edges'])->toHaveCount(1)
        ->and($mapped['edges'][0]['fromId'])->toBe('ofac-100')
        ->and($mapped['edges'][0]['toId'])->toBe('ofac-200');
});

it('treats missing property bags as empty', function (): void {
    $mapper = new FollowTheMoneyMapper();

    $mapped = $mapper->map([
        'id' => 'ofac-102',
        'schema' => 'Person',
        'properties' => 'ignored',
    ], 'dump-1');

    expect(collect($mapped['nodes'])->firstWhere('label', GraphNodeLabel::Person)['id'])->toBe('ofac-102');
});

it('skips entities without an id', function (): void {
    $mapper = new FollowTheMoneyMapper();

    expect($mapper->map(['schema' => 'Person'], 'dump-1'))->toBe([
        'nodes' => [],
        'edges' => [],
    ]);
});

it('maps address entity references onto located-at edges', function (): void {
    $mapper = new FollowTheMoneyMapper();

    $mapped = $mapper->map([
        'id' => 'ofac-100',
        'schema' => 'Person',
        'properties' => [
            'address' => ['addr-99'],
        ],
    ], 'dump-1');

    expect(collect($mapped['edges'])->firstWhere('type', GraphEdgeType::LocatedAt)['toId'])->toBe('addr-99')
        ->and(collect($mapped['nodes'])->firstWhere('label', GraphNodeLabel::Address)['id'])->toBe('addr-99');
});

it('maps directorship family and generic intervals', function (): void {
    $mapper = new FollowTheMoneyMapper();

    $directorship = $mapper->map([
        'id' => 'rel-2',
        'schema' => 'Directorship',
        'properties' => [
            'director' => ['ofac-100'],
            'organization' => ['ofac-200'],
        ],
    ], 'dump-1');
    $family = $mapper->map([
        'id' => 'rel-3',
        'schema' => 'Family',
        'properties' => [
            'person' => ['ofac-100'],
            'personRelative' => ['ofac-300'],
        ],
    ], 'dump-1');
    $related = $mapper->map([
        'id' => 'rel-4',
        'schema' => 'UnknownLink',
        'properties' => [
            'subject' => ['ofac-100'],
            'object' => ['ofac-400'],
        ],
    ], 'dump-1');

    expect($directorship['edges'][0]['type'])->toBe(GraphEdgeType::MemberOf)
        ->and($family['edges'][0]['type'])->toBe(GraphEdgeType::FamilyOf)
        ->and($related['edges'][0]['type'])->toBe(GraphEdgeType::RelatedTo);
});

it('maps remaining identifier kinds country fields and skips empty values', function (): void {
    $mapper = new FollowTheMoneyMapper();

    $mapped = $mapper->map([
        'id' => 'ofac-101',
        'schema' => 'Person',
        'properties' => [
            'email' => [''],
            'phone' => ['+15555550100'],
            'passportNumber' => ['AB123'],
            'idNumber' => ['NID-1'],
            'taxNumber' => ['TAX-1'],
            'registrationNumber' => ['REG-1'],
            'imoNumber' => ['IMO-1'],
            'leiCode' => ['LEI-1'],
            'swiftBic' => ['SWFTUS33'],
            'wikidataId' => ['Q1'],
            'currencyCode' => ['wallet-1'],
            'website' => ['https://example.test'],
            'country' => ['gb'],
            'address' => [''],
        ],
    ], 'dump-1');

    expect(collect($mapped['edges'])->contains(fn (array $edge): bool => $edge['type'] === GraphEdgeType::LocatedAt))->toBeTrue()
        ->and(collect($mapped['nodes'])->firstWhere('label', GraphNodeLabel::Country)['id'])->toBe('country:gb');
});

it('maps follow-the-money schemas onto labels', function (): void {
    expect(GraphNodeLabel::fromFollowTheMoney('Company'))->toBe(GraphNodeLabel::Organization)
        ->and(GraphNodeLabel::fromFollowTheMoney('Airplane'))->toBe(GraphNodeLabel::Aircraft)
        ->and(GraphNodeLabel::fromFollowTheMoney('Thing'))->toBe(GraphNodeLabel::Other)
        ->and(GraphNodeLabel::Person->isEntity())->toBeTrue()
        ->and(GraphNodeLabel::Identifier->isEntity())->toBeFalse();
});
