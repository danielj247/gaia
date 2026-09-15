<?php

declare(strict_types=1);

use App\Enums\GraphEdgeType;
use App\Enums\GraphNodeLabel;
use App\Graph\GraphClient;
use App\Models\Dump;
use App\Models\User;

it('redirects guests away from the explorer', function (): void {
    $this->fromRoute('home')
        ->get(route('explorer'))
        ->assertRedirectToRoute('login');
});

it('renders the explorer for verified users', function (): void {
    Dump::factory()->create();

    $this->actingAs(User::factory()->withoutTwoFactor()->create())
        ->fromRoute('dashboard')
        ->get(route('explorer'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('graph/Explorer')
            ->has('stats'));
});

it('searches and expands a neighborhood', function (): void {
    $graph = app(GraphClient::class);
    $graph->mergeNode(GraphNodeLabel::Person, 'p1', ['caption' => 'Ada Example']);
    $graph->mergeNode(GraphNodeLabel::Organization, 'o1', ['caption' => 'Example Holdings']);
    $graph->mergeEdge(GraphEdgeType::Owns, GraphNodeLabel::Person, 'p1', GraphNodeLabel::Organization, 'o1');

    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user)
        ->getJson(route('explorer.search', ['q' => 'ada', 'limit' => 5]))
        ->assertOk()
        ->assertJsonPath('hits.0.id', 'p1');

    $this->actingAs($user)
        ->getJson(route('explorer.neighborhood', ['id' => 'p1', 'hops' => 1, 'limit' => 50]))
        ->assertOk()
        ->assertJsonPath('nodes.0.id', 'p1');
});

it('validates search queries', function (): void {
    $this->actingAs(User::factory()->withoutTwoFactor()->create())
        ->fromRoute('explorer')
        ->get(route('explorer.search', ['q' => 'a']))
        ->assertSessionHasErrors('q');
});

it('searches aliases and excludes dump hubs from neighborhoods', function (): void {
    $graph = app(GraphClient::class);
    $graph->mergeNode(GraphNodeLabel::Person, 'p1', [
        'caption' => 'Ada Example',
        'aliases' => 'Ada AKA',
    ]);
    $graph->mergeNode(GraphNodeLabel::Organization, 'o1', ['caption' => 'Example Holdings']);
    $graph->mergeNode(GraphNodeLabel::Dump, 'd1', ['caption' => 'us_ofac_sdn']);
    $graph->mergeNode(GraphNodeLabel::Country, 'c1', ['caption' => 'Exampleland']);
    $graph->mergeNode(GraphNodeLabel::Sanction, 's1', ['caption' => 'OFAC-SDN']);
    $graph->mergeEdge(GraphEdgeType::Owns, GraphNodeLabel::Person, 'p1', GraphNodeLabel::Organization, 'o1');
    $graph->mergeEdge(GraphEdgeType::AppearsInDump, GraphNodeLabel::Person, 'p1', GraphNodeLabel::Dump, 'd1');
    $graph->mergeEdge(GraphEdgeType::CitizenOf, GraphNodeLabel::Person, 'p1', GraphNodeLabel::Country, 'c1');
    $graph->mergeEdge(GraphEdgeType::SanctionedUnder, GraphNodeLabel::Person, 'p1', GraphNodeLabel::Sanction, 's1');

    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user)
        ->getJson(route('explorer.search', ['q' => 'aka', 'limit' => 5]))
        ->assertOk()
        ->assertJsonPath('hits.0.id', 'p1');

    $ids = $this->actingAs($user)
        ->getJson(route('explorer.neighborhood', ['id' => 'p1', 'hops' => 1, 'limit' => 50]))
        ->assertOk()
        ->json('nodes.*.id');

    expect($ids)->toContain('p1')
        ->and($ids)->toContain('o1')
        ->and($ids)->not->toContain('d1')
        ->and($ids)->not->toContain('c1')
        ->and($ids)->not->toContain('s1');

    $hubIds = $this->actingAs($user)
        ->getJson(route('explorer.neighborhood', ['id' => 'c1', 'hops' => 2, 'limit' => 50]))
        ->assertOk()
        ->json('nodes.*.id');

    expect($hubIds)->toContain('c1')
        ->and($hubIds)->not->toContain('p1');
});

it('asserts analyst same-as without merging identifiers', function (): void {
    $graph = app(GraphClient::class);
    $graph->mergeNode(GraphNodeLabel::Person, 'p-left', [
        'caption' => 'Ada Example',
        'sourceId' => 'ofac-left',
    ]);
    $graph->mergeNode(GraphNodeLabel::Person, 'p-right', [
        'caption' => 'Ada Example',
        'sourceId' => 'ofac-right',
    ]);

    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user)
        ->postJson(route('explorer.same-as'), [
            'from' => 'p-left',
            'to' => 'p-right',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    $types = collect($graph->neighborhood('p-left', 1, 20)->edges)->pluck('type');

    expect($types)->toContain(GraphEdgeType::SameAs->value);

    $this->actingAs($user)
        ->postJson(route('explorer.same-as'), [
            'from' => 'p-left',
            'to' => 'p-missing',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to']);

    $this->actingAs($user)
        ->postJson(route('explorer.same-as'), [
            'from' => 'p-left',
            'to' => 'p-left',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to']);
});

it('rejects same-as when the request has no user', function (): void {
    $this->withoutMiddleware()
        ->postJson(route('explorer.same-as'), [
            'from' => 'p-left',
            'to' => 'p-right',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to']);
});

it('validates neighborhood hops', function (): void {
    $this->actingAs(User::factory()->withoutTwoFactor()->create())
        ->fromRoute('explorer')
        ->get(route('explorer.neighborhood', ['id' => 'p1', 'hops' => 9]))
        ->assertSessionHasErrors('hops');
});

it('returns json validation errors for explorer xhr', function (): void {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user)
        ->getJson(route('explorer.search', ['q' => 'a']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['q']);

    $this->actingAs($user)
        ->getJson(route('explorer.neighborhood', ['id' => 'p1', 'hops' => 9]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['hops']);
});
