<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StoreModerationController extends Controller
{
    public function update(Request $request, Store $store)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $store->forceFill(['status' => $data['status']])->save();
        AuditLog::record('store.'.$data['status'], $store, $data['reason'] ?? null);

        return back()->with('success', "{$store->name} is now {$store->status}.");
    }
}
