<?php

declare(strict_types=1);

use App\Actions\MapEuFsfEntity;
use App\Enums\GraphEdgeType;
use App\Enums\GraphNodeLabel;
use App\Enums\IdentifierKind;
use App\Graph\IdentifierId;

it('maps an eu person with passport and reference number', function (): void {
    $mapped = resolve(MapEuFsfEntity::class)->handle([
        'id' => 'eu:1',
        'list' => 'eu_fsf',
        'logicalId' => '1',
        'euReferenceNumber' => 'EU.1.1',
        'type' => 'person',
        'name' => 'Ada Example',
        'aliases' => ['A. Example'],
        'programs' => ['UKR'],
        'dateOfBirth' => '1970-01-01',
        'passports' => ['AB123'],
        'nationalities' => ['GB'],
        'addresses' => ['1 Test Street, London, UNITED KINGDOM'],
    ], 'dump-1');

    $nodes = collect($mapped['nodes']);
    $kinds = $nodes->where('label', GraphNodeLabel::Identifier)->pluck('properties.kind');

    expect($nodes->firstWhere('label', GraphNodeLabel::Person)['id'])->toBe('eu:1')
        ->and($kinds)->toContain(IdentifierKind::EuFsfId->value)
        ->and($kinds)->toContain(IdentifierKind::Passport->value)
        ->and($nodes->contains(fn (array $node): bool => $node['id'] === IdentifierId::for(IdentifierKind::EuFsfId, 'EU.1.1')))->toBeTrue()
        ->and($nodes->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Sanction))->toBeTrue()
        ->and(collect($mapped['edges'])->contains(fn (array $edge): bool => $edge['type'] === GraphEdgeType::SanctionedUnder))->toBeTrue()
        ->and(collect($mapped['edges'])->contains(fn (array $edge): bool => $edge['type'] === GraphEdgeType::AppearsInDump))->toBeTrue();
});

it('maps an eu enterprise onto an organization', function (): void {
    $mapped = resolve(MapEuFsfEntity::class)->handle([
        'logicalId' => '2',
        'euReferenceNumber' => 'EU.2.2',
        'type' => 'enterprise',
        'name' => 'Example Holdings',
        'programs' => ['UKR'],
    ], 'dump-1');

    expect(collect($mapped['nodes'])->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Organization))->toBeTrue();
});

it('skips an eu entity without an id and maps remaining fields', function (): void {
    $mapper = resolve(MapEuFsfEntity::class);

    expect($mapper->handle(['name' => 'Nobody'], 'dump-1'))->toBe(['nodes' => [], 'edges' => []]);

    $mapped = $mapper->handle([
        'logicalId' => '9',
        'type' => 'ship',
        'name' => 'Example Ship',
        'programs' => ['UKR'],
        'nationalIds' => ['NID-9'],
        'registrationNumbers' => ['REG-9'],
        'emails' => ['ship@example.test'],
        'phones' => ['+33111'],
        'websites' => ['https://ship.example.test'],
        'imo' => 'IMO9999999',
        'function' => 'Master',
        'gender' => 'M',
        'passports' => 'CD456',
    ], 'dump-1');

    expect(collect($mapped['nodes'])->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Vessel))->toBeTrue()
        ->and(collect($mapped['nodes'])->where('label', GraphNodeLabel::Identifier)->pluck('properties.kind'))
        ->toContain(IdentifierKind::Imo->value, IdentifierKind::EuFsfId->value);
});
