<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>BoitaTech • Política de Privacidade (LGPD)</title>
    <style>
        :root { --bg:#070b11; --panel:#0f1724; --text:#ecf2ff; --muted:#aab6cf; --accent:#3DFF9A; }
        body { margin:0; font-family:Inter,system-ui,sans-serif; background:var(--bg); color:var(--text); }
        main { max-width:900px; margin:0 auto; padding:32px 16px 60px; line-height:1.65; }
        .card { background:var(--panel); border:1px solid rgba(255,255,255,.1); border-radius:14px; padding:18px; margin-top:14px; }
        h1,h2 { margin:0 0 10px; }
        p,li { color:var(--muted); }
        a { color:var(--accent); }
        .small { font-size:12px; color:var(--muted); }
    </style>
</head>
<body>
<main>
    <h1>Política de Privacidade — LGPD</h1>
    <p class="small">Versão {{ $policyVersion }} · Controlador: BoitaTech · Encarregado (DPO): <a href="mailto:{{ $dpoEmail }}">{{ $dpoEmail }}</a></p>

    <section class="card">
        <h2>1. Dados tratados</h2>
        <ul>
            <li>Denúncias: título, descrição, categoria, estado, cidade, bairro, endereço aproximado e imagens enviadas.</li>
            <li>Dados técnicos de segurança: hash de IP e hash parcial de user-agent.</li>
            <li>Consentimentos de funcionalidades opcionais, como hand tracking por webcam.</li>
        </ul>
    </section>

    <section class="card">
        <h2>2. Finalidades e base legal</h2>
        <ul>
            <li>Execução de interesse público e proteção ambiental da plataforma colaborativa.</li>
            <li>Prevenção a fraude e abuso da API (segurança e integridade).</li>
            <li>Consentimento explícito para recursos opcionais (ex.: webcam para gestos).</li>
        </ul>
    </section>

    <section class="card">
        <h2>3. Publicação e privacidade</h2>
        <ul>
            <li>Denúncias são públicas por padrão para transparência comunitária.</li>
            <li>As coordenadas exibidas são aproximadas com offset de privacidade.</li>
            <li>A webcam do hand tracking é processada localmente no navegador.</li>
        </ul>
    </section>

    <section class="card">
        <h2>4. Retenção</h2>
        <p>Registros técnicos e consentimentos seguem janelas de retenção e expurgo automático conforme política interna e minimização de dados.</p>
    </section>

    <section class="card">
        <h2>5. Direitos do titular</h2>
        <p>Você pode solicitar acesso, correção, anonimização, eliminação, portabilidade, informações e revogação do consentimento.</p>
        <p><a href="{{ route('lgpd.requests.form') }}">Abrir solicitação LGPD</a></p>
    </section>
</main>
</body>
</html>
