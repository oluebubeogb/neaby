<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\SafeMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('home', absolute: false));
        }

        if (! SafeMail::attempt(fn () => $request->user()->sendEmailVerificationNotification(), 'verify email (resend)')) {
            return back()->with('error', SafeMail::friendly());
        }

        return back()->with('status', 'verification-link-sent');
    }
}
