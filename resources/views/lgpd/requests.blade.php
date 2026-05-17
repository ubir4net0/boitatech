<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>BoitaTech • Solicitações LGPD</title>
    <style>
        :root { --bg:#070b11; --panel:#0f1724; --text:#ecf2ff; --muted:#aab6cf; --accent:#3DFF9A; }
        body { margin:0; font-family:Inter,system-ui,sans-serif; background:var(--bg); color:var(--text); }
        main { max-width:780px; margin:0 auto; padding:32px 16px 60px; }
        .card { background:var(--panel); border:1px solid rgba(255,255,255,.1); border-radius:14px; padding:18px; }
        .row { display:grid; gap:12px; }
        input,select,textarea { width:100%; border-radius:10px; border:1px solid rgba(255,255,255,.12); background:rgba(255,255,255,.03); color:var(--text); padding:10px; }
        button { border:0; border-radius:10px; padding:10px 14px; background:linear-gradient(135deg,#f97316,#ef4444); color:#fff; cursor:pointer; }
        p,li,label { color:var(--muted); }
        a { color:var(--accent); }
        .ok { background:rgba(61,255,154,.12); border:1px solid rgba(61,255,154,.24); border-radius:10px; padding:10px; margin-bottom:12px; color:#c4ffe1; }
        .err { color:#ffb4b4; margin-top:8px; font-size:13px; }
    </style>
</head>
<body>
<main>
    <h1>Solicitações de Direitos do Titular (LGPD)</h1>
    <p>Canal para solicitações LGPD. DPO: <a href="mailto:{{ $dpoEmail }}">{{ $dpoEmail }}</a>.</p>

    <div class="card">
        @if (session('status'))
            <div class="ok">{{ session('status') }}</div>
        @endif

        <form class="row" method="POST" action="{{ route('lgpd.requests.store') }}">
            @csrf
            <div>
                <label for="email">E-mail para retorno (opcional)</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" />
                @error('email') <div class="err">{{ $message }}</div> @enderror
            </div>

            <div>
                <label for="request_type">Tipo de solicitação</label>
                <select id="request_type" name="request_type" required>
                    <option value="">Selecione</option>
                    <option value="acesso" @selected(old('request_type') === 'acesso')>Acesso aos dados</option>
                    <option value="correcao" @selected(old('request_type') === 'correcao')>Correção</option>
                    <option value="anonimizacao" @selected(old('request_type') === 'anonimizacao')>Anonimização</option>
                    <option value="eliminacao" @selected(old('request_type') === 'eliminacao')>Eliminação</option>
                    <option value="portabilidade" @selected(old('request_type') === 'portabilidade')>Portabilidade</option>
                    <option value="informacoes" @selected(old('request_type') === 'informacoes')>Informações de tratamento</option>
                    <option value="revogacao" @selected(old('request_type') === 'revogacao')>Revogação de consentimento</option>
                </select>
                @error('request_type') <div class="err">{{ $message }}</div> @enderror
            </div>

            <div>
                <label for="description">Descrição</label>
                <textarea id="description" name="description" rows="6" required>{{ old('description') }}</textarea>
                @error('description') <div class="err">{{ $message }}</div> @enderror
            </div>

            <label>
                <input type="checkbox" name="accept_policy" value="1" required />
                Confirmo que li a <a href="{{ route('lgpd.privacy') }}" target="_blank" rel="noopener">Política de Privacidade</a>.
            </label>
            @error('accept_policy') <div class="err">{{ $message }}</div> @enderror

            <div>
                <button type="submit">Enviar solicitação</button>
            </div>
        </form>
    </div>
</main>
</body>
</html>
