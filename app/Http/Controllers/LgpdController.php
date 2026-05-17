<?php

namespace App\Http\Controllers;

use App\Models\LgpdDataRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LgpdController extends Controller
{
    public function privacy(): View
    {
        return view('lgpd.privacy', [
            'policyVersion' => config('lgpd.policy_version'),
            'dpoEmail' => config('lgpd.dpo_email'),
        ]);
    }

    public function requestForm(): View
    {
        return view('lgpd.requests', [
            'dpoEmail' => config('lgpd.dpo_email'),
        ]);
    }

    public function storeRequest(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['nullable', 'email:rfc,dns', 'max:160'],
            'request_type' => ['required', 'string', 'in:acesso,correcao,anonimizacao,eliminacao,portabilidade,informacoes,revogacao'],
            'description' => ['required', 'string', 'min:12', 'max:2500'],
            'accept_policy' => ['accepted'],
        ]);

        $protocol = 'LGPD-' . now()->format('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        LgpdDataRequest::query()->create([
            'protocol' => $protocol,
            'email' => $validated['email'] ?? null,
            'request_type' => $validated['request_type'],
            'description' => strip_tags((string) $validated['description']),
            'status' => 'recebido',
            'response_due_at' => now()->addDays((int) config('lgpd.request_due_days', 15)),
            'metadata' => [
                'policy_version' => config('lgpd.policy_version'),
                'source' => 'web_form',
            ],
            'ip_hash' => hash_hmac('sha256', (string) ($request->ip() ?? 'unknown'), (string) config('app.key')),
            'user_agent_hash' => hash_hmac('sha256', substr((string) ($request->userAgent() ?? ''), 0, 160), (string) config('app.key')),
        ]);

        return redirect()
            ->route('lgpd.requests.form')
            ->with('status', "Solicitação recebida com sucesso. Protocolo: {$protocol}");
    }
}
