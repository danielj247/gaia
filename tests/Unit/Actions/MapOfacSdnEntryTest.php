<?php

declare(strict_types=1);

use App\Actions\MapOfacSdnEntry;
use App\Enums\GraphEdgeType;
use App\Enums\GraphNodeLabel;
use App\Enums\IdentifierKind;
use App\Graph\IdentifierId;

it('maps an ofac individual with passport and uid', function (): void {
    $mapped = resolve(MapOfacSdnEntry::class)->handle([
        'id' => 'ofac:100',
        'list' => 'ofac_sdn',
        'uid' => '100',
        'type' => 'Individual',
        'name' => 'Ada Example',
        'aliases' => ['A. Example'],
        'programs' => ['SDGT'],
        'dateOfBirth' => '01 Jan 1970',
        'passports' => ['AB123'],
        'nationalities' => ['United Kingdom'],
        'addresses' => ['1 Test Street, London, United Kingdom'],
    ], 'dump-1');

    $nodes = collect($mapped['nodes']);
    $edges = collect($mapped['edges']);
    $kinds = $nodes->where('label', GraphNodeLabel::Identifier)->pluck('properties.kind');

    expect($nodes->firstWhere('label', GraphNodeLabel::Person)['id'])->toBe('ofac:100')
        ->and($kinds)->toContain(IdentifierKind::OfacId->value)
        ->and($kinds)->toContain(IdentifierKind::Passport->value)
        ->and($nodes->contains(fn (array $node): bool => $node['id'] === IdentifierId::for(IdentifierKind::OfacId, '100')))->toBeTrue()
        ->and($nodes->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Sanction))->toBeTrue()
        ->and($edges->contains(fn (array $edge): bool => $edge['type'] === GraphEdgeType::SanctionedUnder))->toBeTrue()
        ->and($edges->contains(fn (array $edge): bool => $edge['type'] === GraphEdgeType::AppearsInDump))->toBeTrue();
});

it('maps an ofac entity onto an organization and a vessel onto a vessel', function (): void {
    $mapper = resolve(MapOfacSdnEntry::class);

    $entity = $mapper->handle([
        'uid' => '200',
        'type' => 'Entity',
        'name' => 'Example Holdings',
        'programs' => ['SDGT'],
    ], 'dump-1');

    $vessel = $mapper->handle([
        'uid' => '300',
        'type' => 'Vessel',
        'name' => 'Example Vessel',
        'programs' => ['SDGT'],
        'imo' => 'IMO1234567',
    ], 'dump-1');

    expect(collect($entity['nodes'])->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Organization))->toBeTrue()
        ->and(collect($vessel['nodes'])->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Vessel))->toBeTrue();
});

it('skips an ofac entry without a uid and maps remaining identifier kinds', function (): void {
    $mapper = resolve(MapOfacSdnEntry::class);

    expect($mapper->handle(['name' => 'Nobody'], 'dump-1'))->toBe(['nodes' => [], 'edges' => []]);

    $mapped = $mapper->handle([
        'uid' => '400',
        'type' => 'Aircraft',
        'name' => 'Example Jet',
        'programs' => ['SDGT'],
        'nationalIds' => ['NID-9'],
        'registrationNumbers' => ['REG-9'],
        'emails' => ['jet@example.test'],
        'phones' => ['+1555'],
        'websites' => ['https://jet.example.test'],
        'title' => 'Captain',
        'passports' => 'CD456',
    ], 'dump-1');

    expect(collect($mapped['nodes'])->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Aircraft))->toBeTrue()
        ->and(collect($mapped['nodes'])->where('label', GraphNodeLabel::Identifier)->pluck('properties.kind'))
        ->toContain(IdentifierKind::Email->value, IdentifierKind::NationalId->value);
});
