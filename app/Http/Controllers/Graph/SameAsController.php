<?php

declare(strict_types=1);

namespace App\Http\Controllers\Graph;

use App\Actions\AssertSameAs;
use App\Http\Requests\AssertSameAsRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class SameAsController
{
    public function store(AssertSameAsRequest $request, AssertSameAs $assert): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'to' => 'SAME_AS requires an authenticated analyst.',
            ]);
        }

        try {
            $assert->handle(
                $request->string('from')->toString(),
                $request->string('to')->toString(),
                $user->email,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'to' => $exception->getMessage(),
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
