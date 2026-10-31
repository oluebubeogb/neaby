<?php

namespace App\Http\Controllers\Messaging;

use App\Contracts\ObjectStore;
use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Support\Chat;
use App\Support\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 1:1 messaging (text, compressed images, Opus voice notes). Threads are
 * listed server-side; message history is lazy-loaded in pages of 30 and new
 * messages arrive by short polling with an ?after= cursor (tiny payloads).
 */
class MessagesController extends Controller
{
    private const PAGE = 30;

    public function index(Request $request, ?Conversation $conversation = null): Response
    {
        $me = $request->user();
        abort_if($conversation && ! $conversation->involves($me), 404);

        $threads = Conversation::query()->for($me)
            ->where(fn ($q) => $q->whereNotNull('last_message_at')->when($conversation, fn ($w) => $w->orWhere('id', $conversation->id)))
            ->with(['userOne.store', 'userTwo.store', 'latestMessage'])
            ->orderByDesc('last_message_at')->orderByDesc('id')
            ->limit(60)->get();

        $seo = Seo::make('Messages', index: false);

        return Inertia::render('Messages/Index', [
            'threads' => $threads->map(fn ($c) => Chat::thread($c, $me))->values(),
            'active' => $conversation ? [
                'id' => $conversation->id,
                'other' => Chat::person($conversation->other($me)?->loadMissing('store')),
                'context' => Chat::context($conversation),
            ] : null,
            'draft' => $request->query('draft'),
            'limits' => [
                'max_voice_seconds' => config('neaby.messaging.max_voice_seconds'),
                'poll_seconds' => config('neaby.messaging.poll_thread_seconds'),
                'calls' => (bool) config('neaby.rtc.calls_enabled'),
            ],
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    /** Open (or create) the thread with the seller of a product / owner of a store / a profile. */
    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product' => ['nullable', 'integer'],
            'store' => ['nullable', 'integer'],
            'user' => ['nullable', 'string', 'max:40'],
            'draft' => ['nullable', 'string', 'max:300'],
            'call' => ['nullable', 'boolean'],
        ]);
        $me = $request->user();
        [$to, $type, $id] = [null, null, null];
        if (! empty($data['product']) && ($p = Product::query()->find($data['product']))) {
            [$to, $type, $id] = [User::query()->find($p->user_id), 'product', $p->id];
        } elseif (! empty($data['store']) && ($s = Store::query()->find($data['store']))) {
            [$to, $type, $id] = [User::query()->find($s->user_id), 'store', $s->id];
        } elseif (! empty($data['user'])) {
            $to = User::query()->whereRaw('lower(username) = ?', [strtolower(ltrim($data['user'], '@'))])->first();
            $type = 'profile';
        }
        if (! $to || $to->isSuspended()) {
            return back()->with('error', 'That person can’t be messaged right now.');
        }
        if ($to->id === $me->id) {
            return back()->with('error', 'That’s you — share your link instead.');
        }

        $c = Conversation::between($me, $to);
        if ($type && ($type !== 'profile' || ! $c->context_type)) {
            $c->forceFill(['context_type' => $type, 'context_id' => $id])->save();
        }

        return redirect()->route('messages.show', array_filter([
            'conversation' => $c->id,
            'draft' => $data['draft'] ?? null,
            'call' => ! empty($data['call']) ? 1 : null,
        ]));
    }

    /** Lazy history: ?before=id for older pages, ?after=id for new messages since the last poll. */
    public function feed(Request $request, Conversation $conversation): JsonResponse
    {
        $me = $request->user();
        abort_unless($conversation->involves($me), 404);
        $before = (int) $request->query('before');
        $after = (int) $request->query('after');

        $q = $conversation->messages()->select(['id', 'conversation_id', 'sender_id', 'type', 'body', 'media_path', 'meta', 'created_at']);
        if ($after) {
            $rows = $q->where('id', '>', $after)->orderBy('id')->limit(100)->get();
            $more = false;
        } else {
            $rows = $q->when($before, fn ($w) => $w->where('id', '<', $before))->orderByDesc('id')->limit(self::PAGE + 1)->get();
            $more = $rows->count() > self::PAGE;
            $rows = $rows->take(self::PAGE)->reverse()->values();
        }

        $col = $conversation->readColumn($me);
        if (! $before && ($rows->isNotEmpty() || ! $conversation->{$col})) {
            $conversation->forceFill([$col => now()])->save();
        }
        $otherRead = $conversation->{$conversation->user_one_id === $me->id ? 'user_two_read_at' : 'user_one_read_at'};

        return response()->json([
            'messages' => $rows->map(fn ($m) => Chat::message($m, $me))->values(),
            'has_more' => $more,
            'other_read_at' => $otherRead?->toIso8601String(),
        ]);
    }

    public function send(Request $request, Conversation $conversation, ObjectStore $objects): JsonResponse
    {
        $me = $request->user();
        abort_unless($conversation->involves($me), 404);
        $key = 'chat:'.$me->id;
        if (RateLimiter::tooManyAttempts($key, config('neaby.messaging.max_per_minute', 30))) {
            throw ValidationException::withMessages(['body' => 'Slow down a little — try again in a moment.']);
        }
        RateLimiter::hit($key, 60);

        $type = $request->input('type', 'text');
        $meta = [];
        $path = null;
        $body = null;
        if ($type === 'image') {
            $request->validate([
                'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.config('neaby.messaging.max_image_kb')],
                'w' => ['nullable', 'integer', 'max:4096'], 'h' => ['nullable', 'integer', 'max:4096'],
                'body' => ['nullable', 'string', 'max:1000'],
            ]);
            $path = $objects->putFile($request->file('file'), 'chat');
            $meta = ['w' => (int) $request->input('w'), 'h' => (int) $request->input('h'), 'bytes' => $request->file('file')->getSize()];
            $body = $request->input('body');
        } elseif ($type === 'voice') {
            $request->validate([
                'file' => ['required', 'file', 'max:'.config('neaby.messaging.max_voice_kb'), function ($attr, $file, $fail) {
                    $mime = (string) $file->getMimeType();
                    if (! preg_match('#^(audio/|video/webm|application/ogg)#', $mime)) {
                        $fail('That doesn’t look like a voice note.');
                    }
                }],
                'duration' => ['required', 'numeric', 'min:0.5', 'max:'.(config('neaby.messaging.max_voice_seconds') + 2)],
            ]);
            $path = $objects->putFile($request->file('file'), 'chat');
            $meta = ['duration' => round((float) $request->input('duration'), 1), 'bytes' => $request->file('file')->getSize()];
        } else {
            $type = 'text';
            $body = trim((string) $request->validate(['body' => ['required', 'string', 'max:2000']])['body']);
            if ($body === '') {
                throw ValidationException::withMessages(['body' => 'Type a message first.']);
            }
        }

        $m = $conversation->messages()->create(['sender_id' => $me->id, 'type' => $type, 'body' => $body, 'media_path' => $path, 'meta' => $meta ?: null]);
        $conversation->forceFill(['last_message_at' => $m->created_at, $conversation->readColumn($me) => $m->created_at])->save();

        return response()->json(['message' => Chat::message($m, $me)]);
    }

    /** Media is only served to the two participants. */
    public function media(Request $request, Message $message)
    {
        abort_unless($message->media_path && $message->conversation?->involves($request->user()), 404);
        $disk = Storage::disk(config('neaby.uploads.disk', 'public'));
        abort_unless($disk->exists($message->media_path), 404);

        return $disk->response($message->media_path, null, [
            'Cache-Control' => 'private, max-age=604800, immutable',
        ]);
    }

    /** Tiny heartbeat for the badge + incoming call ring. */
    public function pulse(Request $request): JsonResponse
    {
        $me = $request->user();
        $ring = (int) config('neaby.rtc.ring_seconds', 40);
        Call::query()->where('status', 'ringing')->where('created_at', '<', now()->subSeconds($ring))->update(['status' => 'missed', 'ended_at' => now()]);
        $call = config('neaby.rtc.calls_enabled')
            ? Call::query()->where('callee_id', $me->id)->where('status', 'ringing')->with('caller')->latest('id')->first()
            : null;

        return response()->json([
            'unread' => Conversation::unreadCount($me),
            'call' => $call ? ['id' => $call->id, 'conversation_id' => $call->conversation_id, 'from' => Chat::person($call->caller)] : null,
        ]);
    }
}
