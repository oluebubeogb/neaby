<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Present;
use App\Support\Seo;
use Inertia\Inertia;

class PublicProfileController extends Controller
{
    public function show(string $username)
    {
        $user = User::query()->whereRaw('lower(username) = ?', [strtolower(ltrim($username, '@'))])->firstOrFail();
        abort_if($user->isSuspended(), 404);

        $store = $user->store()->active()->withCount(['products' => fn ($q) => $q->where('status', 'published')])->first();
        $products = $store
            ? $store->products()->where('status', 'published')->with(['cover', 'store'])->latest('published_at')->limit(12)->get()->map(fn ($p) => Present::productCard($p))->values()
            : collect();
        $seo = Seo::make("{$user->name} (@{$user->username})", $user->bio);

        return Inertia::render('Users/Show', [
            'profile' => [
                'name' => $user->name,
                'username' => $user->username,
                'bio' => $user->bio,
                'avatar' => Present::user($user)['avatar'],
                'initials' => $user->initials(),
                'city' => $user->city,
                'state' => $user->state,
                'joined' => $user->created_at?->format('M Y'),
            ],
            'store' => $store ? Present::storeCard($store) : null,
            'products' => $products,
            'isSelf' => request()->user()?->id === $user->id,
            'url' => route('users.show', $user->username),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }
}
