<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LgpdConsent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LgpdConsentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'purpose' => ['required', 'string', 'max:64'],
            'granted' => ['required', 'boolean'],
            'policy_version' => ['nullable', 'string', 'max:32'],
            'context' => ['nullable', 'array'],
        ]);

        LgpdConsent::query()->create([
            'purpose' => $validated['purpose'],
            'granted' => (bool) $validated['granted'],
            'policy_version' => (string) ($validated['policy_version'] ?? config('lgpd.policy_version')),
            'context' => $validated['context'] ?? null,
            'ip_hash' => hash_hmac('sha256', (string) ($request->ip() ?? 'unknown'), (string) config('app.key')),
            'user_agent_hash' => hash_hmac('sha256', substr((string) ($request->userAgent() ?? ''), 0, 160), (string) config('app.key')),
            'consented_at' => now(),
        ]);

        return response()->json([
            'message' => 'Consentimento LGPD registrado.',
        ], 201);
    }
}
