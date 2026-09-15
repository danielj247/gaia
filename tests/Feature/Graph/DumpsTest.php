<?php

declare(strict_types=1);

use App\Enums\DumpChunkStatus;
use App\Enums\DumpStatus;
use App\Models\Dump;
use App\Models\DumpChunk;
use App\Models\DumpError;
use App\Models\User;

it('redirects guests away from dumps', function (): void {
    $this->fromRoute('home')
        ->get(route('dumps'))
        ->assertRedirectToRoute('login');
});

it('renders the dump list for verified users', function (): void {
    $dump = Dump::factory()->create([
        'status' => DumpStatus::Ingesting,
        'dataset' => 'us_ofac_sdn',
    ]);
    DumpChunk::factory()->create([
        'dump_id' => $dump->id,
        'status' => DumpChunkStatus::Completed,
    ]);

    $this->actingAs(User::factory()->withoutTwoFactor()->create())
        ->fromRoute('dashboard')
        ->get(route('dumps'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('graph/Dumps')
            ->has('dumps', 1));
});

it('renders dump chunk progress and errors', function (): void {
    $dump = Dump::factory()->create([
        'status' => DumpStatus::Failed,
        'error' => 'One or more chunks failed.',
    ]);
    DumpChunk::factory()->create([
        'dump_id' => $dump->id,
        'status' => DumpChunkStatus::Failed,
        'error' => 'Dump file is missing.',
    ]);
    DumpError::factory()->create([
        'dump_id' => $dump->id,
        'code' => 'invalid_json',
        'message' => 'Invalid JSON.',
        'line_number' => 2,
    ]);

    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user)
        ->fromRoute('dumps')
        ->get(route('dumps.show', $dump))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('graph/DumpStatus')
            ->where('dump.id', $dump->id)
            ->has('dump.chunks', 1)
            ->has('dump.errors', 1));

    $this->actingAs($user)
        ->getJson(route('dumps.progress', $dump))
        ->assertOk()
        ->assertJsonPath('id', $dump->id)
        ->assertJsonPath('chunks_failed', 1)
        ->assertJsonPath('errors.0.code', 'invalid_json');
});
