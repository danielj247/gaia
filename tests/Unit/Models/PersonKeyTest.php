<?php

declare(strict_types=1);

use App\Models\PersonKey;

it('persists a source id to gaia id mapping', function (): void {
    $key = PersonKey::factory()->create([
        'source_id' => 'ofac-factory',
    ]);

    expect($key->source_id)->toBe('ofac-factory')
        ->and($key->gaia_id)->not->toBeEmpty();
});
