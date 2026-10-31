<?php

namespace App\Http\Controllers;

use App\Contracts\ObjectStore;
use App\Http\Requests\ProfileUpdateRequest;
use App\Support\NigeriaStates;
use App\Support\Present;
use App\Support\Seo;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /** Profile hub (the "Profile" tab). */
    public function show(Request $request): Response
    {
        $user = $request->user();
        $store = $user->store()->withCount(['products' => fn ($q) => $q->where('status', 'published')])->first();
        $seo = Seo::make('Profile', index: false);

        return Inertia::render('Profile/Show', [
            'store' => $store ? Present::storeCard($store) : null,
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function edit(Request $request): Response
    {
        $seo = Seo::make('Settings', index: false);

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'states' => NigeriaStates::all(),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function update(ProfileUpdateRequest $request, ObjectStore $objects): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->safe()->except(['avatar', 'remove_avatar']));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        if ($request->boolean('remove_avatar') && $user->avatar_path) {
            $objects->delete($user->avatar_path);
            $user->avatar_path = null;
        }
        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                $objects->delete($user->avatar_path);
            }
            $user->avatar_path = $objects->putFile($request->file('avatar'), 'avatars');
        }

        $user->save();

        return Redirect::route('profile.edit')->with('success', 'Profile saved.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
