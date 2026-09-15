<?php

declare(strict_types=1);

use App\Actions\MapUkSanctionsRow;
use App\Enums\GraphEdgeType;
use App\Enums\GraphNodeLabel;
use App\Enums\IdentifierKind;
use App\Graph\IdentifierId;

it('maps an individual with passport and unique id', function (): void {
    $mapped = resolve(MapUkSanctionsRow::class)->handle([
        'Unique ID' => 'GBR001',
        'OFSI Group ID' => '12345',
        'Name 1' => 'Ada',
        'Name 6' => 'Example',
        'Name type' => 'Primary Name',
        'Designation Type' => 'Individual',
        'Regime Name' => 'The Russia (Sanctions) (EU Exit) Regulations 2019',
        'Passport number' => 'AB123',
        'D.O.B' => '01/01/1970',
    ], 'dump-1');

    $nodes = collect($mapped['nodes']);
    $edges = collect($mapped['edges']);
    $person = $nodes->firstWhere('label', GraphNodeLabel::Person);
    $kinds = $nodes->where('label', GraphNodeLabel::Identifier)->pluck('properties.kind');

    expect($person)->not->toBeNull()
        ->and($person['properties']['name'])->toBe('Ada Example')
        ->and($person['properties']['birthDate'])->toBe('01/01/1970')
        ->and($person['id'])->toBe('uksl:GBR001')
        ->and($kinds)->toContain(IdentifierKind::UkslId->value)
        ->and($kinds)->toContain(IdentifierKind::Passport->value)
        ->and($kinds)->toContain(IdentifierKind::OfsiGroupId->value)
        ->and($nodes->firstWhere('label', GraphNodeLabel::Identifier)['id'] ?? null)
        ->not->toBeNull()
        ->and($nodes->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Identifier
            && $node['id'] === IdentifierId::for(IdentifierKind::UkslId, 'GBR001')))->toBeTrue()
        ->and($nodes->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Sanction))->toBeTrue()
        ->and($edges->contains(fn (array $edge): bool => $edge['type'] === GraphEdgeType::SanctionedUnder))->toBeTrue()
        ->and($edges->contains(fn (array $edge): bool => $edge['type'] === GraphEdgeType::AppearsInDump
            && $edge['toId'] === 'dump-1'))->toBeTrue();
});

it('maps an entity onto an organization and a ship onto a vessel', function (): void {
    $mapper = resolve(MapUkSanctionsRow::class);

    $entity = $mapper->handle([
        'Unique ID' => 'GBR002',
        'Name 6' => 'Example Holdings Ltd',
        'Designation Type' => 'Entity',
        'Regime Name' => 'The Russia (Sanctions) (EU Exit) Regulations 2019',
        'Business registration number (s)' => '12345678',
    ], 'dump-1');

    $ship = $mapper->handle([
        'Unique ID' => 'GBR003',
        'Name 6' => 'Example Vessel',
        'Designation Type' => 'Ship',
        'Regime Name' => 'The Russia (Sanctions) (EU Exit) Regulations 2019',
        'IMO number' => 'IMO1234567',
    ], 'dump-1');

    expect(collect($entity['nodes'])->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Organization))->toBeTrue()
        ->and(collect($ship['nodes'])->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Vessel))->toBeTrue()
        ->and(collect($ship['nodes'])->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Identifier
            && $node['properties']['kind'] === IdentifierKind::Imo->value))->toBeTrue();
});

it('skips a uksl row without a unique id and maps aliases plus contact fields', function (): void {
    $mapper = resolve(MapUkSanctionsRow::class);

    expect($mapper->handle(['Name 6' => 'Nobody'], 'dump-1'))->toBe(['nodes' => [], 'edges' => []]);

    $mapped = $mapper->handle([
        'uniqueId' => 'GBR009',
        'type' => 'Individual',
        'name' => 'Ada Example',
        'aliases' => ['A. Example'],
        'emails' => ['ada@example.test'],
        'phones' => ['+44111'],
        'websites' => ['https://ada.example.test'],
        'nationalIds' => ['NID-1'],
        'nationalities' => ['United Kingdom'],
        'addresses' => ['1 Test Street, London'],
    ], 'dump-1');

    expect(collect($mapped['edges'])->contains(fn (array $edge): bool => $edge['type'] === GraphEdgeType::AlsoKnownAs))->toBeTrue()
        ->and(collect($mapped['edges'])->contains(fn (array $edge): bool => $edge['type'] === GraphEdgeType::CitizenOf))->toBeTrue()
        ->and(collect($mapped['edges'])->contains(fn (array $edge): bool => $edge['type'] === GraphEdgeType::LocatedAt))->toBeTrue();
});
