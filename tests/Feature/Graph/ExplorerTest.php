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

it('validates neighborhood hops', function (): void {
    $this->actingAs(User::factory()->withoutTwoFactor()->create())
        ->fromRoute('explorer')
        ->get(route('explorer.neighborhood', ['id' => 'p1', 'hops' => 9]))
        ->assertSessionHasErrors('hops');
});
