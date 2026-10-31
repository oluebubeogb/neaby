<?php

namespace App\Http\Controllers;

use App\Support\Seo;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Notifications inbox (orders, memberships, supplier requests, imports). */
class InboxController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $items = $user->notifications()->latest()->limit(100)->get();
        $seo = Seo::make('Inbox', index: false);

        return Inertia::render('Inbox', [
            'notifications' => $items->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? 'Update',
                'body' => $n->data['body'] ?? '',
                'url' => $n->data['url'] ?? null,
                'kind' => $n->data['kind'] ?? 'general',
                'read' => (bool) $n->read_at,
                'ago' => $n->created_at?->diffForHumans(),
            ])->values(),
            'unread' => $user->unreadNotifications()->count(),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function read(Request $request)
    {
        $id = $request->input('id');
        $q = $request->user()->unreadNotifications();
        $id ? $q->where('id', $id)->update(['read_at' => now()]) : $q->update(['read_at' => now()]);

        return back();
    }
}
