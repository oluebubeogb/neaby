<?php

namespace App\Http\Controllers\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Conversation;
use App\Support\Chat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * WebRTC 1:1 voice-call signalling over plain HTTP polling (no websocket
 * server required). Media flows peer-to-peer (or via TURN when configured).
 */
class CallsController extends Controller
{
    public function start(Request $request, Conversation $conversation): JsonResponse
    {
        $me = $request->user();
        abort_unless(config('neaby.rtc.calls_enabled') && $conversation->involves($me), 404);
        $calleeId = $conversation->otherId($me);

        // End any stale live calls between these two.
        Call::query()->where('conversation_id', $conversation->id)->whereIn('status', ['ringing', 'active'])
            ->update(['status' => 'ended', 'ended_at' => now()]);

        $call = Call::create(['conversation_id' => $conversation->id, 'caller_id' => $me->id, 'callee_id' => $calleeId, 'status' => 'ringing']);

        return response()->json(['call' => $this->shape($call, $me)]);
    }

    public function show(Request $request, Call $call): JsonResponse
    {
        abort_unless($call->involves($request->user()), 404);
        $this->expire($call);

        return response()->json(['call' => $this->shape($call->fresh(), $request->user())]);
    }

    public function answer(Request $request, Call $call): JsonResponse
    {
        $me = $request->user();
        abort_unless($call->callee_id === $me->id, 404);
        $this->expire($call);
        if ($call->fresh()->status === 'ringing') {
            $call->forceFill(['status' => 'active', 'answered_at' => now()])->save();
        }

        return response()->json(['call' => $this->shape($call->fresh(), $me)]);
    }

    public function decline(Request $request, Call $call): JsonResponse
    {
        return $this->finish($request, $call, 'declined');
    }

    public function end(Request $request, Call $call): JsonResponse
    {
        return $this->finish($request, $call, 'ended');
    }

    public function signal(Request $request, Call $call): JsonResponse
    {
        $me = $request->user();
        abort_unless($call->involves($me) && $call->isLive(), 404);
        $data = $request->validate([
            'type' => ['required', 'in:offer,answer,ice'],
            'payload' => ['required', 'string', 'max:20000'],
        ]);
        $s = $call->signals()->create(['from_user_id' => $me->id, 'type' => $data['type'], 'payload' => $data['payload'], 'created_at' => now()]);

        return response()->json(['id' => $s->id]);
    }

    /** Signals from the other party since ?after=, plus current status. */
    public function signals(Request $request, Call $call): JsonResponse
    {
        $me = $request->user();
        abort_unless($call->involves($me), 404);
        $this->expire($call);
        $rows = $call->signals()->where('id', '>', (int) $request->query('after'))->where('from_user_id', '!=', $me->id)
            ->orderBy('id')->limit(100)->get(['id', 'type', 'payload']);

        return response()->json(['call' => $this->shape($call->fresh(), $me), 'signals' => $rows]);
    }

    private function finish(Request $request, Call $call, string $status): JsonResponse
    {
        $me = $request->user();
        abort_unless($call->involves($me), 404);
        if ($call->isLive()) {
            $final = $call->status === 'ringing' ? ($status === 'declined' ? 'declined' : ($call->caller_id === $me->id ? 'missed' : 'declined')) : 'ended';
            $call->forceFill(['status' => $final, 'ended_at' => now()])->save();
            $this->log($call);
        }

        return response()->json(['call' => $this->shape($call->fresh(), $me)]);
    }

    private function expire(Call $call): void
    {
        if ($call->status === 'ringing' && $call->created_at->lt(now()->subSeconds((int) config('neaby.rtc.ring_seconds', 40)))) {
            $call->forceFill(['status' => 'missed', 'ended_at' => now()])->save();
            $this->log($call);
        }
    }

    /** Leave a call record in the thread. */
    private function log(Call $call): void
    {
        $secs = $call->answered_at && $call->ended_at ? $call->answered_at->diffInSeconds($call->ended_at) : 0;
        $label = match ($call->status) {
            'missed' => 'Missed voice call',
            'declined' => 'Declined voice call',
            default => 'Voice call · '.gmdate($secs >= 3600 ? 'H:i:s' : 'i:s', (int) $secs),
        };
        $m = $call->conversation->messages()->create(['sender_id' => $call->caller_id, 'type' => 'call', 'body' => $label, 'meta' => ['call_id' => $call->id, 'seconds' => (int) $secs]]);
        $call->conversation->forceFill(['last_message_at' => $m->created_at])->save();
        $call->signals()->delete(); // signalling is ephemeral
    }

    private function shape(Call $call, $me): array
    {
        return [
            'id' => $call->id,
            'conversation_id' => $call->conversation_id,
            'status' => $call->status,
            'caller' => $call->caller_id === $me->id,
            'peer' => Chat::person($call->caller_id === $me->id ? $call->callee : $call->caller),
        ];
    }

    public static function iceServers(): array
    {
        $servers = [];
        $stun = config('neaby.rtc.stun_urls', []);
        if ($stun) {
            $servers[] = ['urls' => $stun];
        }
        if ($turn = config('neaby.rtc.turn_url')) {
            $servers[] = array_filter([
                'urls' => array_map('trim', explode(',', $turn)),
                'username' => config('neaby.rtc.turn_username'),
                'credential' => config('neaby.rtc.turn_credential'),
            ]);
        }

        return $servers;
    }
}
