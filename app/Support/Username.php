<?php

namespace App\Support;

use App\Models\Store;
use App\Models\User;
use Illuminate\Validation\Rule;

class Username
{
    public static function rules(?int $ignoreUserId = null): array
    {
        return [
            'required', 'string', 'min:3', 'max:24',
            'regex:/^[a-z0-9_]+$/',
            Rule::notIn(Store::RESERVED_SLUGS),
            Rule::unique(User::class, 'username')->ignore($ignoreUserId),
        ];
    }

    public static function messages(): array
    {
        return [
            'username.regex' => 'Use lowercase letters, numbers and underscores only.',
            'username.not_in' => 'That username is reserved.',
            'username.unique' => 'That username is taken.',
        ];
    }

    public static function normalize(?string $value): string
    {
        return strtolower(ltrim(trim((string) $value), '@'));
    }
}
