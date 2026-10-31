<?php

namespace App\Http\Controllers;

use App\Contracts\ObjectStore;
use App\Http\Requests\ProfileUpdateRequest;
use App\Support\NigeriaStates;
use App\Support\Present;
use App\Support\Seo;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\User;
use App\Notifications\ConfirmAccountDeletion;
use App\Support\Username;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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

    /**
     * Live availability check for username / email (settings + sign-up).
     * Uses the same rules as the save endpoints, so the answer always matches.
     */
    public function availability(Request $request): JsonResponse
    {
        $field = $request->query('field');
        abort_unless(in_array($field, ['username', 'email'], true), 422);
        $uid = $request->user()?->id;
        $value = (string) $request->query('value', '');
        if ($field === 'username') {
            $value = Username::normalize($value);
            $v = Validator::make(['username' => $value], ['username' => Username::rules($uid)], Username::messages());
        } else {
            $value = strtolower(trim($value));
            $v = Validator::make(['email' => $value], ['email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($uid)]], [
                'email.unique' => 'An account already uses that email.',
            ]);
        }

        return response()->json($v->fails()
            ? ['ok' => false, 'value' => $value, 'message' => $v->errors()->first($field)]
            : ['ok' => true, 'value' => $value, 'message' => $field === 'username' ? 'Username available' : 'Email available']);
    }

    /**
     * Step 1 of deletion: password + typed email, then we email a signed link.
     * Nothing is deleted until the link is opened by the same signed-in user.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->validate([
            'password' => ['required', 'current_password'],
            'confirm_email' => ['required', 'string', function ($attr, $value, $fail) use ($user) {
                if (strtolower(trim((string) $value)) !== strtolower($user->email)) {
                    $fail('Type your account email exactly to confirm.');
                }
            }],
        ]);

        $minutes = 30;
        $url = URL::temporarySignedRoute('profile.destroy.confirm', now()->addMinutes($minutes), ['user' => $user->id]);
        $user->notify(new ConfirmAccountDeletion($url, $minutes));

        return Redirect::route('profile.edit')->with('success', "Check {$user->email} — we sent a link to confirm deletion (valid {$minutes} min).");
    }

    /** Step 2 (GET): confirmation screen reached from the email link. */
    public function confirmDestroy(Request $request): Response|RedirectResponse
    {
        if ((int) $request->query('user') !== $request->user()->id) {
            return Redirect::route('profile.edit')->with('error', 'That link belongs to a different account.');
        }
        $seo = Seo::make('Delete account', index: false);

        return Inertia::render('Profile/ConfirmDelete', [
            'action' => $request->fullUrl(),
            'email' => $request->user()->email,
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    /** Step 3 (POST to the same signed URL): delete. */
    public function finalDestroy(Request $request): RedirectResponse
    {
        abort_unless((int) $request->query('user') === $request->user()->id, 403);
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/')->with('success', 'Your account was deleted.');
    }
}
