<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class NeighborhoodRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'hops' => ['sometimes', 'integer', 'min:1', 'max:3'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
