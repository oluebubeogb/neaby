<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ReturnTo;
use App\Support\SafeMail;
use App\Support\Username;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): Response
    {
        ReturnTo::remember($request);

        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge(['username' => Username::normalize($request->input('username'))]);
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => Username::rules(),
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], Username::messages());

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Registered → SendEmailVerificationNotification (User implements
        // MustVerifyEmail). Sent inline; an SMTP hiccup never blocks sign-up.
        $mailed = SafeMail::attempt(fn () => event(new Registered($user)), 'verify email (signup)');

        Auth::login($user);

        $to = redirect()->to(ReturnTo::afterAuth($request));

        return $mailed
            ? $to->with('success', 'Welcome to neaby! We sent a verification link to '.$user->email.'.')
            : $to->with('error', "Welcome to neaby! We couldn't send your verification email just now — resend it from Settings.");
    }
}
