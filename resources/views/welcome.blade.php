{{--
    BoitaTech — Landing Page Oficial CINEMATOGRÁFICA
    Design Premium • Geoespacial • Monitoramento Ambiental
    Stack: Laravel Blade + CSS3 + Vite + GSAP Local
    Animações: ScrollTrigger • Parallax • Reveal • 3D
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="theme-color" content="#050505" />
    <title>BoitaTech — Inteligência Ambiental da Amazônia em Tempo Real</title>
    <meta name="description" content="Plataforma geoespacial de inteligência ambiental. Monitoramento de queimadas, desmatamento e ameaças à Amazônia com dados do INPE em tempo real. Visualização 3D cinematográfica, BoitaNews e análise ambiental profunda." />

    {{-- Open Graph --}}
    <meta property="og:title" content="BoitaTech — A Floresta em Tempo Real" />
    <meta property="og:description" content="Inteligência geoespacial para monitoramento ambiental da Amazônia." />
    <meta property="og:type" content="website" />
    <meta property="og:image" content="{{ asset('images/og-image.jpg') }}" />

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />

    {{-- Vite Assets --}}
    @vite(['resources/js/landing.js'])
    

    <style>
        /* ============ DESIGN TOKENS ============ */
        :root {
            --green-amazon:   #0B3D2E;
            --green-deep:     #071E17;
            --green-neon:     #18A558;
            --green-glow:     #3DFF9A;
            --orange-fire:    #FF6B00;
            --red-burn:       #FF3B1D;
            --yellow-energy:  #FFC857;
            --black-1:        #050505;
            --black-2:        #0E0E0E;
            --gray-dark:      #1B1B1B;
            --white-soft:     #F5F5F5;
            --gray-text:      #B8B8B8;

            --font-display: "Space Grotesk", system-ui, sans-serif;
            --font-body:    "Inter", system-ui, sans-serif;

            --ease-cinema: cubic-bezier(.2,.8,.2,1);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { background: var(--black-1); color: var(--white-soft); font-family: var(--font-body); -webkit-font-smoothing: antialiased; overflow-x: hidden; }
        a { color: inherit; text-decoration: none; }
        img, svg, video { display: block; max-width: 100%; height: auto; }
        button { font: inherit; background: none; border: 0; color: inherit; cursor: pointer; }
        ::selection { background: var(--green-glow); color: var(--black-1); }

        /* ============ NAVBAR ============ */
        .nav {
            position: fixed; inset: 0 0 auto 0; z-index: 50;
            display: flex; align-items: center; justify-content: space-between;
            padding: 22px clamp(20px, 5vw, 64px);
            transition: background-color .5s var(--ease-cinema), backdrop-filter .5s var(--ease-cinema), border-color .5s var(--ease-cinema), padding .4s var(--ease-cinema);
            border-bottom: 1px solid transparent;
        }
        .nav.scrolled {
            background: rgba(5,5,5,0.65);
            backdrop-filter: blur(18px) saturate(140%);
            -webkit-backdrop-filter: blur(18px) saturate(140%);
            border-bottom-color: rgba(61,255,154,0.08);
            padding-top: 14px; padding-bottom: 14px;
        }
        .nav__brand { display: flex; align-items: center; gap: 12px; font-family: var(--font-display); font-weight: 800; letter-spacing: .02em; font-size: 18px; }
        .nav__mark {
            width: 30px; height: 30px; border-radius: 8px; position: relative;
            background: radial-gradient(circle at 30% 30%, var(--green-glow), var(--green-amazon) 60%, var(--black-1) 100%);
            box-shadow: 0 0 24px rgba(61,255,154,0.45), inset 0 0 12px rgba(0,0,0,0.6);
        }
        .nav__mark::after {
            content: ""; position: absolute; inset: 6px; border-radius: 4px;
            background: linear-gradient(135deg, var(--orange-fire), transparent 60%);
            mix-blend-mode: screen; opacity: .85;
        }
        .nav__brand span em { font-style: normal; color: var(--green-glow); }
        .nav__links { display: flex; gap: 36px; font-size: 14px; font-weight: 500; }
        .nav__links a { position: relative; color: var(--white-soft); opacity: .82; transition: opacity .3s ease, color .3s ease; }
        .nav__links a::after {
            content: ""; position: absolute; left: 0; bottom: -6px; height: 1px; width: 100%;
            background: linear-gradient(90deg, var(--green-glow), var(--orange-fire));
            transform: scaleX(0); transform-origin: right; transition: transform .4s var(--ease-cinema);
        }
        .nav__links a:hover { opacity: 1; color: var(--green-glow); }
        .nav__links a:hover::after { transform: scaleX(1); transform-origin: left; }
        .nav__cta {
            font-size: 13px; font-weight: 600; padding: 10px 18px; border-radius: 999px;
            border: 1px solid rgba(61,255,154,0.4); color: var(--green-glow);
            background: rgba(24,165,88,0.08); transition: all .35s var(--ease-cinema);
        }
        .nav__cta:hover { background: var(--green-glow); color: var(--black-1); box-shadow: 0 0 30px rgba(61,255,154,0.45); }
        .nav__burger { display: none; width: 38px; height: 38px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.12); background: rgba(0,0,0,0.4); cursor: pointer; }
        .nav__burger span { display: block; width: 16px; height: 1.5px; background: var(--white-soft); margin: 4px auto; transition: transform .3s; }

        @media (max-width: 860px) {
            .nav__links, .nav__cta { display: none; }
            .nav__burger { display: block; }
        }

        /* ============ SHARED SECTION STYLES ============ */
        .section {
            position: relative;
            padding: clamp(80px, 10vw, 140px) 0;
            overflow: hidden;
        }
        .section--dark {
            background: linear-gradient(180deg, var(--black-2) 0%, var(--black-1) 100%);
        }
        .section--alt {
            background: linear-gradient(180deg, var(--black-1) 0%, var(--black-2) 100%);
        }
        .container {
            width: 100%; max-width: 1240px; margin: 0 auto;
            padding: 0 clamp(20px, 5vw, 48px);
        }
        .section__head {
            text-align: center; max-width: 760px; margin: 0 auto 60px;
            display: flex; flex-direction: column; align-items: center; gap: 14px;
        }

        .h2 {
            font-family: var(--font-display); font-weight: 800;
            font-size: clamp(32px, 5vw, 56px);
            line-height: 1.1; letter-spacing: -0.02em;
        }
        .h2__accent {
            background: linear-gradient(120deg, var(--green-glow), var(--yellow-energy));
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }

        .lead {
            font-size: clamp(15px, 1.15vw, 18px);
            color: var(--gray-text); max-width: 60ch;
            line-height: 1.6;
        }

        .eyebrow {
            display: inline-flex; align-items: center; gap: 8px;
            width: fit-content;
            padding: 8px 14px; border-radius: 999px;
            border: 1px solid rgba(61,255,154,0.25);
            background: rgba(7,30,23,0.4);
            font-size: 11px; letter-spacing: .2em; text-transform: uppercase;
            color: var(--green-glow); font-weight: 600;
        }
        .eyebrow .dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: var(--green-glow); box-shadow: 0 0 8px var(--green-glow);
        }

        .two-col {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: clamp(32px, 6vw, 80px); align-items: center;
        }
        .two-col--reverse .col--media { order: 1; }
        .module-copy { display: flex; flex-direction: column; gap: 18px; }
        .module-copy__title {
            font-family: var(--font-display); font-weight: 700; font-size: clamp(24px, 2.8vw, 40px);
            line-height: 1.08; letter-spacing: -0.02em;
        }
        .module-copy__title .accent {
            background: linear-gradient(120deg, var(--green-glow), var(--yellow-energy));
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .module-copy__lead {
            font-size: clamp(15px, 1.08vw, 18px);
            color: var(--gray-text); line-height: 1.65; max-width: 58ch;
        }
        .module-copy__meta { display: flex; gap: 12px; flex-wrap: wrap; }
        .module-pill {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 8px 12px; border-radius: 999px;
            border: 1px solid rgba(61,255,154,0.18); background: rgba(7,30,23,0.35);
            color: var(--gray-text); font-size: 12px; font-weight: 600;
        }
        .module-pill .dot {
            width: 6px; height: 6px; border-radius: 50%; background: var(--green-glow); box-shadow: 0 0 10px var(--green-glow);
        }
        .module-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 8px; }
        .module-actions--center { justify-content: center; }
        .section-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 24px; }
        .module-media {
            position: relative; width: 100%; aspect-ratio: 4/3; border-radius: 20px; overflow: hidden;
            background: linear-gradient(135deg, rgba(7,30,23,0.4), rgba(14,61,46,0.25));
            border: 1px solid rgba(61,255,154,0.16); box-shadow: 0 8px 32px -4px rgba(61,255,154,0.15);
            backdrop-filter: blur(14px); transition: transform .6s var(--ease-cinema), box-shadow .6s var(--ease-cinema);
        }
        .module-media:hover { transform: translateY(-8px); box-shadow: 0 20px 60px -10px rgba(61,255,154,0.32); }
        .module-media__frame { position: absolute; inset: 0; }
        .module-media__frame .media-frame__placeholder { text-align: center; padding: 20px; }
        .module-media__caption {
            position: absolute; left: 16px; right: 16px; bottom: 16px; z-index: 2;
            display: flex; justify-content: space-between; gap: 12px; align-items: end;
            padding: 12px 14px; border-radius: 14px;
            background: rgba(5,10,15,0.62); backdrop-filter: blur(14px);
            border: 1px solid rgba(61,255,154,0.14);
        }
        .module-media__caption strong { display: block; font-family: var(--font-display); font-size: 13px; }
        .module-media__caption span { display: block; color: var(--gray-text); font-size: 11px; margin-top: 4px; line-height: 1.4; }
        .module-split--media-first-mobile { display: grid; gap: inherit; }

        .col--text { display: flex; flex-direction: column; gap: 16px; }
        .col--text .eyebrow {
            width: fit-content;
        }

        .checks { list-style: none; display: flex; flex-direction: column; gap: 10px; }
        .checks li {
            display: flex; align-items: center; gap: 10px;
            color: var(--gray-text); font-size: 15px;
        }
        .checks li svg { color: var(--green-glow); flex-shrink: 0; }

        /* ============ MEDIA FRAMES ============ */
        .media-frame {
            position: relative; aspect-ratio: 4/3; width: 100%;
            border-radius: 18px;
            background: linear-gradient(135deg, rgba(7,30,23,0.4), rgba(14,61,46,0.25));
            border: 1px solid rgba(61,255,154,0.15);
            overflow: hidden; backdrop-filter: blur(14px);
            box-shadow: var(--shadow-card);
            transition: transform .6s var(--ease-cinema), box-shadow .6s var(--ease-cinema);
        }
        .media-frame img, .media-frame video { width: 100%; height: 100%; object-fit: cover; display: block; }

        .media-frame__placeholder {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            font-family: var(--font-display); letter-spacing: .2em; text-transform: uppercase;
            font-size: 12px; font-weight: 600; color: var(--gray-muted);
            background:
                radial-gradient(60% 60% at 50% 40%, rgba(61,255,154,0.12), transparent 70%),
                linear-gradient(135deg, var(--gray-dark), var(--black-2));
        }

        .media-frame--glow {
            box-shadow: var(--shadow-card), var(--shadow-glow);
        }
        .media-frame--glow::before {
            content: ""; position: absolute; inset: -2px; border-radius: inherit; padding: 1px;
            background: linear-gradient(135deg, rgba(61,255,154,0.4), rgba(14,165,233,0.2), transparent 60%);
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite: xor; mask-composite: exclude;
            pointer-events: none;
        }

        .media-frame:hover { transform: translateY(-8px); box-shadow: var(--shadow-card), 0 40px 100px -30px rgba(61,255,154,0.4); }

        /* ============ CARDS GRID ============ */
        .cards {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .card {
            position: relative; padding: 28px;
            background: rgba(15,25,35,0.55); border: 1px solid rgba(61,255,154,0.12);
            border-radius: 16px; backdrop-filter: blur(10px);
            transition: transform .35s var(--ease-cinema), border-color .35s var(--ease-cinema), box-shadow .35s var(--ease-cinema), background .35s var(--ease-cinema);
            overflow: hidden; perspective: 1200px;
            will-change: transform, box-shadow;
        }
        .card::before {
            content: ""; position: absolute; inset: 0; opacity: 0;
            background: radial-gradient(60% 60% at 30% 0%, rgba(61,255,154,0.12), transparent 70%);
            transition: opacity .4s var(--ease-cinema);
        }
        .card::after {
            content: ""; position: absolute; inset: -2px; border-radius: 20px; opacity: 0;
            background: conic-gradient(from 0deg, var(--green-glow), var(--orange-fire), var(--green-glow));
            filter: blur(8px); z-index: -1;
            transition: opacity .4s var(--ease-cinema);
            animation: none;
        }
        .card:hover {
            transform: translateY(-8px) rotateX(2deg) rotateY(-2deg);
            border-color: rgba(61,255,154,0.45);
            background: rgba(15,25,35,0.75);
            box-shadow:
                0 20px 60px -10px rgba(61,255,154,0.35),
                0 0 40px -4px rgba(61,255,154,0.2),
                inset 0 0 30px -12px rgba(61,255,154,0.1);
        }
        .card:hover::before { opacity: 1; }
        .card:hover::after { opacity: 0.4; animation: spin 4s linear infinite; }

        .card__ico {
            font-size: 32px; width: 56px; height: 56px;
            display: grid; place-items: center; margin-bottom: 14px;
            background: linear-gradient(135deg, rgba(61,255,154,0.15), rgba(14,165,233,0.1));
            border: 1px solid rgba(61,255,154,0.2); border-radius: 14px;
            transition: all .35s var(--ease-cinema);
            will-change: transform, filter;
        }
        .card:hover .card__ico {
            transform: scale(1.15) rotateZ(-8deg);
            background: linear-gradient(135deg, rgba(61,255,154,0.25), rgba(14,165,233,0.18));
            border-color: rgba(61,255,154,0.4);
            filter: drop-shadow(0 0 12px rgba(61,255,154,0.3));
        }

        .card h3 {
            font-family: var(--font-display); font-weight: 700; font-size: 18px;
            color: var(--white-soft); margin-bottom: 8px;
            transition: color .35s var(--ease-cinema);
        }
        .card:hover h3 { color: var(--green-glow); }

        .card p {
            color: var(--gray-text); font-size: 14.5px; line-height: 1.55;
            transition: color .35s var(--ease-cinema);
        }
        .card:hover p { color: var(--white-soft); }

        /* ============ PLATFORM SHOWCASE ============ */
        .platform-showcase {
            position: relative; width: 100%; aspect-ratio: 4/3;
            border-radius: 20px; overflow: hidden;
            background: linear-gradient(135deg, rgba(7,30,23,0.3), rgba(14,61,46,0.2));
            border: 1px solid rgba(61,255,154,0.2);
        }
        .showcase__frame {
            position: relative; width: 100%; height: 100%;
            display: flex; flex-direction: column;
            background: linear-gradient(135deg, rgba(5,10,15,0.8), rgba(10,20,30,0.85));
            backdrop-filter: blur(20px);
        }
        .showcase__header {
            display: flex; gap: 8px; padding: 14px 16px;
            border-bottom: 1px solid rgba(61,255,154,0.1);
        }
        .showcase__dot {
            width: 12px; height: 12px; border-radius: 50%;
            background: rgba(61,255,154,0.3); border: 1px solid rgba(61,255,154,0.2);
        }
        .showcase__content {
            flex: 1; padding: 0; position: relative; overflow: hidden;
        }
        .showcase__glow {
            position: absolute; inset: -40%; z-index: 0;
            background: radial-gradient(circle, var(--green-glow), transparent 70%);
            filter: blur(80px); opacity: 0.15; pointer-events: none;
            animation: glow-pulse 3s ease-in-out infinite;
        }
        @keyframes glow-pulse { 0%, 100% { opacity: 0.15; transform: scale(1); } 50% { opacity: 0.25; transform: scale(1.1); } }

        /* ============ MAP CONTAINER ============ */
        .map-container {
            position: relative; width: 100%; aspect-ratio: 4/3;
            border-radius: 20px; overflow: hidden;
            background: linear-gradient(135deg, rgba(7,30,23,0.4), rgba(14,61,46,0.25));
            border: 1.5px solid rgba(61,255,154,0.3);
            box-shadow: 0 8px 32px -4px rgba(61,255,154,0.15);
        }

        .map-frame {
            position: relative; width: 100%; height: 100%;
            background: linear-gradient(135deg, rgba(5,10,15,0.8), rgba(10,20,30,0.85));
            backdrop-filter: blur(20px);
            overflow: hidden; isolation: isolate;
        }

        .map-frame__placeholder {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            background: radial-gradient(ellipse at 50% 50%, rgba(61,255,154,0.08), transparent 80%);
        }

        .map-grid {
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(61,255,154,0.08) 1px, transparent 1px),
                linear-gradient(90deg, rgba(61,255,154,0.08) 1px, transparent 1px);
            background-size: 80px 80px;
            opacity: 0.5;
        }

        .map-focus {
            position: relative; z-index: 1;
            display: flex; align-items: center; justify-content: center;
            animation: spin 20s linear infinite;
        }

        .map-glow {
            position: absolute; inset: -40%; z-index: 0; pointer-events: none;
            background: radial-gradient(circle, var(--green-glow), transparent 60%);
            filter: blur(100px); opacity: 0.1;
            animation: glow-pulse 4s ease-in-out infinite;
        }

        /* Floating Widgets */
        .floating-widget {
            position: absolute; z-index: 10;
            display: flex; flex-direction: column; align-items: center; gap: 6px;
            padding: 12px 14px; border-radius: 12px;
            background: rgba(5,10,15,0.6); backdrop-filter: blur(16px);
            border: 1px solid rgba(61,255,154,0.25);
            box-shadow: 0 8px 24px -8px rgba(61,255,154,0.2);
            animation: float-in 0.8s var(--ease-cinema) backwards;
            transition: all .35s var(--ease-cinema);
        }

        .floating-widget--focos { animation-delay: 0.1s; border-color: rgba(255,107,0,0.3); }
        .floating-widget--areas { animation-delay: 0.3s; border-color: rgba(24,165,88,0.3); }
        .floating-widget--alerts { animation-delay: 0.5s; border-color: rgba(255,59,29,0.3); }
        .floating-widget--coverage { animation-delay: 0.7s; border-color: rgba(61,255,154,0.3); }

        @keyframes float-in {
            from { opacity: 0; transform: scale(0.8) translateY(16px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .floating-widget:hover {
            transform: translateY(-6px);
            background: rgba(5,10,15,0.8);
            border-color: rgba(61,255,154,0.5);
            box-shadow: 0 12px 40px -6px rgba(61,255,154,0.3);
        }

        .widget-icon {
            font-size: 20px; filter: drop-shadow(0 0 8px rgba(61,255,154,0.2));
        }

        .widget-label {
            font-family: var(--font-display); font-weight: 700; font-size: 12px;
            letter-spacing: .1em; text-transform: uppercase;
            background: linear-gradient(120deg, var(--white-soft), var(--green-glow));
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }

        .widget-label span {
            display: block; font-size: 10px; letter-spacing: .15em;
            background: none; -webkit-background-clip: unset; color: var(--gray-text);
            margin-top: 2px;
        }

        /* ============ BOITANEWS EDITORIAL ============ */
        .boitanews-section {
            position: relative;
        }
        .boitanews-layout {
            align-items: stretch;
        }
        .boitanews-copy {
            justify-content: center;
            max-width: 600px;
        }
        .boitanews-label {
            display: inline-flex; align-items: center; gap: 8px;
            width: fit-content;
            padding: 8px 14px; border-radius: 999px;
            border: 1px solid rgba(61,255,154,0.16);
            background: rgba(9,24,20,0.42);
            font-size: 11px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase;
            color: var(--green-glow);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.03);
        }
        .boitanews-label::before {
            content: ""; width: 7px; height: 7px; border-radius: 50%;
            background: var(--orange-fire); box-shadow: 0 0 14px rgba(255,107,0,0.55);
        }
        .boitanews-title {
            font-family: var(--font-display); font-weight: 800;
            font-size: clamp(34px, 4vw, 58px); line-height: .96; letter-spacing: -0.04em;
            color: var(--white-soft);
        }
        .boitanews-title span {
            display: block;
            background: linear-gradient(120deg, var(--white-soft), var(--green-glow) 45%, var(--yellow-energy));
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .boitanews-subtitle {
            font-family: var(--font-display); font-size: 15px; font-weight: 600;
            letter-spacing: .16em; text-transform: uppercase;
            color: rgba(224,234,242,0.72);
        }
        .boitanews-lead {
            font-size: clamp(16px, 1.25vw, 19px);
            line-height: 1.72; color: var(--gray-text);
            max-width: 56ch;
        }
        .boitanews-lead strong {
            color: var(--white-soft);
            font-weight: 600;
        }
        .boitanews-meta {
            display: flex; flex-wrap: wrap; gap: 12px;
        }
        .boitanews-pill {
            display: inline-flex; align-items: center; gap: 9px;
            padding: 10px 14px; border-radius: 999px;
            border: 1px solid rgba(61,255,154,0.14);
            background: linear-gradient(135deg, rgba(10,22,30,0.78), rgba(8,18,25,0.5));
            color: rgba(224,234,242,0.78);
            font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
        }
        .boitanews-pill::before {
            content: ""; width: 6px; height: 6px; border-radius: 50%;
            background: var(--green-glow); box-shadow: 0 0 12px rgba(61,255,154,0.45);
        }

        .boitanews-mockup {
            position: relative; min-height: 100%;
            display: flex; align-items: center;
        }
        .boitanews-mockup__frame {
            position: relative; width: 100%; aspect-ratio: 16 / 11;
            border-radius: 28px; overflow: hidden;
            background:
                radial-gradient(70% 80% at 20% 10%, rgba(61,255,154,0.14), transparent 60%),
                radial-gradient(50% 60% at 80% 20%, rgba(255,107,0,0.12), transparent 55%),
                linear-gradient(135deg, rgba(7,14,20,0.94), rgba(10,20,30,0.86));
            border: 1px solid rgba(61,255,154,0.18);
            box-shadow:
                0 30px 80px -32px rgba(0,0,0,0.75),
                0 18px 48px -24px rgba(61,255,154,0.24),
                inset 0 1px 0 rgba(255,255,255,0.05);
            isolation: isolate;
            transition: transform .45s var(--ease-cinema), box-shadow .45s var(--ease-cinema), border-color .45s var(--ease-cinema);
        }
        .boitanews-mockup__frame::before {
            content: ""; position: absolute; inset: 0;
            background: linear-gradient(145deg, rgba(255,255,255,0.12), transparent 18%, transparent 62%, rgba(255,255,255,0.05));
            opacity: .45; pointer-events: none;
        }
        .boitanews-mockup__frame::after {
            content: ""; position: absolute; inset: -18%; z-index: -1;
            background: radial-gradient(circle, rgba(61,255,154,0.2), transparent 60%);
            filter: blur(90px); opacity: .45; pointer-events: none;
        }
        .boitanews-mockup:hover .boitanews-mockup__frame {
            transform: translateY(-8px);
            border-color: rgba(61,255,154,0.28);
            box-shadow:
                0 36px 90px -34px rgba(0,0,0,0.85),
                0 24px 56px -24px rgba(61,255,154,0.28),
                inset 0 1px 0 rgba(255,255,255,0.08);
        }

        .boitanews-mockup__chrome {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 18px;
            border-bottom: 1px solid rgba(61,255,154,0.1);
            background: linear-gradient(180deg, rgba(255,255,255,0.04), rgba(255,255,255,0.01));
        }
        .boitanews-mockup__dots {
            display: flex; gap: 8px;
        }
        .boitanews-mockup__dots span {
            width: 10px; height: 10px; border-radius: 50%;
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.08);
        }
        .boitanews-mockup__dots span:nth-child(1) { background: rgba(255,107,0,0.48); }
        .boitanews-mockup__dots span:nth-child(2) { background: rgba(255,215,64,0.38); }
        .boitanews-mockup__dots span:nth-child(3) { background: rgba(61,255,154,0.38); }
        .boitanews-mockup__status {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 7px 12px; border-radius: 999px;
            border: 1px solid rgba(61,255,154,0.14);
            background: rgba(9,20,26,0.7);
            color: rgba(224,234,242,0.72);
            font-size: 10px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase;
        }
        .boitanews-mockup__status::before {
            content: ""; width: 7px; height: 7px; border-radius: 50%;
            background: var(--green-glow); box-shadow: 0 0 12px rgba(61,255,154,0.55);
        }

        .boitanews-mockup__body {
            position: relative; display: grid; grid-template-columns: 1.2fr .8fr;
            gap: 16px; height: calc(100% - 67px);
            padding: 18px;
        }
        .boitanews-mockup__hero,
        .boitanews-mockup__panel,
        .boitanews-mockup__card {
            position: relative;
            background: rgba(7,14,20,0.66);
            border: 1px solid rgba(61,255,154,0.11);
            backdrop-filter: blur(14px);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
        }
        .boitanews-mockup__hero {
            display: flex; flex-direction: column; justify-content: space-between;
            min-height: 100%; padding: 20px; border-radius: 22px;
            background:
                radial-gradient(90% 100% at 0% 0%, rgba(61,255,154,0.12), transparent 55%),
                linear-gradient(180deg, rgba(10,18,26,0.82), rgba(5,12,18,0.94));
            overflow: hidden;
        }
        .boitanews-mockup__hero::before {
            content: ""; position: absolute; inset: auto -8% -35% 35%; height: 180px;
            background: radial-gradient(circle, rgba(61,255,154,0.18), transparent 60%);
            filter: blur(45px); opacity: .5;
        }
        .boitanews-kicker {
            display: inline-flex; align-items: center; gap: 8px; width: fit-content;
            padding: 8px 12px; border-radius: 999px;
            background: rgba(255,255,255,0.04); border: 1px solid rgba(61,255,154,0.14);
            font-size: 10px; font-weight: 700; letter-spacing: .16em; text-transform: uppercase;
            color: var(--green-glow);
        }
        .boitanews-kicker::before {
            content: ""; width: 6px; height: 6px; border-radius: 50%; background: var(--orange-fire); box-shadow: 0 0 12px rgba(255,107,0,0.4);
        }
        .boitanews-mockup__headline {
            font-family: var(--font-display); font-weight: 800;
            font-size: clamp(20px, 2vw, 30px); line-height: 1.05; letter-spacing: -0.03em;
            color: var(--white-soft); max-width: 10ch;
        }
        .boitanews-mockup__summary {
            max-width: 28ch; font-size: 13px; line-height: 1.6; color: rgba(224,234,242,0.7);
        }
        .boitanews-mockup__graph {
            display: flex; align-items: end; gap: 10px; height: 120px;
        }
        .boitanews-mockup__bar {
            flex: 1; border-radius: 999px 999px 6px 6px;
            background: linear-gradient(180deg, rgba(61,255,154,0.9), rgba(14,61,46,0.3));
            box-shadow: 0 0 16px rgba(61,255,154,0.1);
        }
        .boitanews-mockup__bar:nth-child(1) { height: 38%; }
        .boitanews-mockup__bar:nth-child(2) { height: 68%; }
        .boitanews-mockup__bar:nth-child(3) { height: 54%; }
        .boitanews-mockup__bar:nth-child(4) { height: 86%; background: linear-gradient(180deg, rgba(255,107,0,0.92), rgba(98,44,8,0.28)); }
        .boitanews-mockup__bar:nth-child(5) { height: 62%; }
        .boitanews-mockup__metrics {
            display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px;
        }
        .boitanews-metric {
            padding: 12px 12px 10px; border-radius: 16px;
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);
        }
        .boitanews-metric strong {
            display: block; font-family: var(--font-display); font-size: 16px; color: var(--white-soft);
        }
        .boitanews-metric span {
            display: block; margin-top: 4px; font-size: 10px; letter-spacing: .12em; text-transform: uppercase; color: var(--gray-text);
        }

        .boitanews-mockup__sidebar {
            display: grid; gap: 14px;
        }
        .boitanews-mockup__panel {
            border-radius: 20px; padding: 16px;
        }
        .boitanews-mockup__panel h4 {
            font-family: var(--font-display); font-size: 13px; letter-spacing: .12em; text-transform: uppercase; color: rgba(224,234,242,0.7);
            margin-bottom: 12px;
        }
        .boitanews-mockup__card {
            padding: 14px; border-radius: 16px;
            transition: transform .35s var(--ease-cinema), border-color .35s var(--ease-cinema), background .35s var(--ease-cinema);
        }
        .boitanews-mockup__card:hover {
            transform: translateY(-3px);
            border-color: rgba(61,255,154,0.22);
            background: rgba(9,18,25,0.82);
        }
        .boitanews-mockup__tag {
            display: inline-flex; margin-bottom: 8px;
            font-size: 9px; font-weight: 700; letter-spacing: .16em; text-transform: uppercase;
            color: var(--green-glow);
        }
        .boitanews-mockup__card strong {
            display: block; font-family: var(--font-display); font-size: 14px; line-height: 1.35; color: var(--white-soft);
        }
        .boitanews-mockup__card p {
            margin-top: 8px; font-size: 11px; line-height: 1.55; color: rgba(224,234,242,0.62);
        }
        .boitanews-mockup__footer {
            display: flex; align-items: center; justify-content: space-between; gap: 10px;
            padding: 14px 16px; border-radius: 18px;
            background: linear-gradient(135deg, rgba(255,107,0,0.12), rgba(61,255,154,0.08));
            border: 1px solid rgba(255,255,255,0.06);
        }
        .boitanews-mockup__footer span {
            font-size: 10px; letter-spacing: .14em; text-transform: uppercase; color: rgba(224,234,242,0.62);
        }
        .boitanews-mockup__footer strong {
            font-family: var(--font-display); font-size: 14px; color: var(--white-soft);
        }
        .boitanews-preview-note {
            position: absolute; left: 22px; bottom: 20px; z-index: 2;
            display: inline-flex; align-items: center; gap: 10px;
            padding: 10px 14px; border-radius: 999px;
            background: rgba(5,10,15,0.72); border: 1px solid rgba(255,255,255,0.08);
            color: rgba(224,234,242,0.72); font-size: 11px; font-weight: 700; letter-spacing: .08em;
            backdrop-filter: blur(14px);
        }
        .boitanews-preview-note::before {
            content: ""; width: 8px; height: 8px; border-radius: 50%; background: var(--green-glow); box-shadow: 0 0 12px rgba(61,255,154,0.45);
        }

        @media (max-width: 1180px) {
            .boitanews-mockup__body { grid-template-columns: 1fr; }
            .boitanews-mockup__hero { min-height: 280px; }
        }
        @media (max-width: 1024px) {
            .boitanews-copy { max-width: none; }
        }
        @media (max-width: 640px) {
            .boitanews-title { font-size: clamp(30px, 10vw, 44px); }
            .boitanews-mockup__frame { aspect-ratio: 4 / 5; border-radius: 24px; }
            .boitanews-mockup__body { padding: 14px; gap: 12px; }
            .boitanews-mockup__chrome { padding: 14px; }
            .boitanews-mockup__metrics { grid-template-columns: 1fr; }
            .boitanews-preview-note { left: 14px; right: 14px; bottom: 14px; justify-content: center; }
        }


            position: relative; padding: clamp(100px, 14vw, 180px) 0;
            overflow: hidden; isolation: isolate; text-align: center;
        }
        .cta-section__bg {
            position: absolute; inset: 0; z-index: -2;
            background:
                radial-gradient(60% 50% at 50% 50%, rgba(61,255,154,0.12), transparent 70%),
                linear-gradient(180deg, var(--black-2), var(--black-1));
        }
        .cta-section__glow {
            position: absolute; z-index: -1; filter: blur(100px); opacity: .35; border-radius: 50%; pointer-events: none;
        }
        .cta-section__glow--g { width: 520px; height: 520px; background: radial-gradient(circle, var(--green-glow), transparent 60%); top: -120px; left: 10%; }
        .cta-section__glow--o { width: 520px; height: 520px; background: radial-gradient(circle, var(--orange-fire), transparent 60%); bottom: -160px; right: 10%; }

        .cta-section__inner { display: flex; flex-direction: column; align-items: center; gap: 30px; }
        .cta-section .h2 { max-width: 18ch; }
        .cta-section__btns { display: flex; gap: 14px; flex-wrap: wrap; justify-content: center; }

        /* ============ FOOTER ============ */
        .footer {
            position: relative; border-top: 1px solid rgba(61,255,154,0.08);
            background: var(--black-1);
        }
        .footer__inner {
            display: grid; grid-template-columns: 1.2fr 2fr;
            gap: 40px; padding: 60px 0 40px;
        }
        .footer__brand h3 { font-family: var(--font-display); font-weight: 800; font-size: 16px; margin-bottom: 8px; }
        .footer__brand h3 em { font-style: normal; color: var(--green-glow); }
        .footer__brand p { color: var(--gray-text); font-size: 14px; max-width: 36ch; line-height: 1.6; }

        .footer__cols { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
        .footer__cols h5 {
            font-family: var(--font-display); font-weight: 700; font-size: 11px;
            letter-spacing: .18em; text-transform: uppercase; color: var(--white-soft); margin-bottom: 14px;
        }
        .footer__cols a { display: block; color: var(--gray-text); font-size: 14px; padding: 6px 0; transition: color .2s var(--ease-cinema); }
        .footer__cols a:hover { color: var(--green-glow); }

        .footer__bottom {
            position: relative; padding: 20px 0; border-top: 1px solid rgba(61,255,154,0.08);
            display: flex; justify-content: center; color: var(--gray-muted); font-size: 13px;
        }

        /* ============ ANIMATION HOOKS ============ */
        [data-anim] { opacity: 0; transform: translateY(28px); transition: opacity .9s var(--ease-cinema), transform .9s var(--ease-cinema); }
        [data-anim="zoom-in"] { transform: scale(.96); }
        [data-anim].in { opacity: 1; transform: none; }

        /* ============ RESPONSIVE ============ */
        @media (max-width: 1024px) {
            .two-col { grid-template-columns: 1fr; }
            .two-col--reverse .col--media { order: 0; }
            .module-media { aspect-ratio: 16 / 10; }
            .footer__cols { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 820px) {
            .nav__links, .nav__cta { display: none; }
            .nav__burger { display: flex; }
            .hero__stats { grid-template-columns: 1fr; gap: 14px; }
            .stat { border-left: none; padding: 12px 0; }
            .cards { grid-template-columns: 1fr; }
            .module-actions, .section-actions { flex-direction: column; align-items: stretch; }
            .module-actions .btn, .section-actions .btn { width: 100%; }
            .cta-section__btns { flex-direction: column; align-items: center; }
            .cta-section__btns .btn { width: 100%; justify-content: center; }
            .footer__inner { grid-template-columns: 1fr; }
            .footer__cols { grid-template-columns: 1fr; }
            .module-split--media-first-mobile .col--media { order: -1; }
        }
        @media (max-width: 480px) {
            .hero__content { padding: 110px 20px 90px; }
            .hero__ctas { gap: 10px; }
            .btn { padding: 14px 22px; font-size: 13px; }
            .section { padding: 60px 0; }
            .h2 { font-size: clamp(28px, 4vw, 36px); }
            .module-actions { gap: 10px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
        }

        @media (max-width: 860px) {
            .nav__links, .nav__cta { display: none; }
            .nav__burger { display: block; }
        }

        /* ============ HERO ============ */
        .hero {
            position: relative; min-height: 100svh; min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            overflow: hidden; isolation: isolate;
        }
        .hero__media { position: absolute; inset: 0; z-index: -2; }
        .hero__video, .hero__canvas {
            position: absolute; inset: 0; width: 100%; height: 100%;
            object-fit: cover;
        }
        .hero__canvas { z-index: 0; }   /* fallback animado */
        .hero__video  { z-index: 1; opacity: 0; transition: opacity 1.2s var(--ease-cinema); }
        .hero__video.is-ready { opacity: 1; }

        /* Overlay cinematográfico */
        .hero__overlay {
            position: absolute; inset: 0; z-index: 2; pointer-events: none;
            background:
                radial-gradient(ellipse at 50% 110%, rgba(255,107,0,0.18), transparent 55%),
                radial-gradient(ellipse at 50% -10%, rgba(11,61,46,0.55), transparent 60%),
                linear-gradient(180deg, rgba(5,5,5,0.55) 0%, rgba(5,5,5,0.35) 35%, rgba(5,5,5,0.85) 100%);
        }

        /* Glows Cinematográficos */
        .hero__glow {
            position: absolute; z-index: 1.5; pointer-events: none; filter: blur(120px); opacity: 0;
            animation: glow-fade 2s var(--ease-cinema) forwards;
        }
        .hero__glow--green {
            width: 600px; height: 600px; background: radial-gradient(circle, var(--green-glow), transparent 60%);
            top: -100px; left: 5%; animation-delay: 0.3s;
        }
        .hero__glow--orange {
            width: 500px; height: 500px; background: radial-gradient(circle, var(--orange-fire), transparent 60%);
            bottom: -80px; right: 10%; animation-delay: 0.6s;
        }
        @keyframes glow-fade { from { opacity: 0; } to { opacity: 0.4; } }

        .hero__grain {
            position: absolute; inset: 0; z-index: 3; pointer-events: none;
            opacity: .07; mix-blend-mode: overlay;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='160' height='160'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/></filter><rect width='100%' height='100%' filter='url(%23n)' opacity='0.7'/></svg>");
        }

        /* Conteúdo */
        .hero__content {
            position: relative; z-index: 4;
            max-width: 1100px; padding: 120px clamp(20px, 5vw, 48px) 80px;
            text-align: center;
        }
        .hero__eyebrow {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 8px 16px; border-radius: 999px;
            border: 1px solid rgba(61,255,154,0.25);
            background: rgba(7,30,23,0.5); backdrop-filter: blur(8px);
            font-size: 12px; letter-spacing: .22em; text-transform: uppercase;
            color: var(--green-glow); font-weight: 600;
            opacity: 0; /* GSAP */
        }
        .hero__eyebrow .dot {
            width: 7px; height: 7px; border-radius: 50%;
            background: var(--green-glow);
            box-shadow: 0 0 12px var(--green-glow);
            animation: pulse 1.8s var(--ease-cinema) infinite;
        }
        @keyframes pulse { 0%,100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.4); opacity: .55; } }

        .hero__title {
            font-family: var(--font-display); font-weight: 800;
            font-size: clamp(44px, 8.4vw, 124px);
            line-height: .96; letter-spacing: -0.03em;
            margin: 28px 0 24px;
        }
        .hero__title .line { display: block; overflow: hidden; }
        .hero__title .word { display: inline-block; will-change: transform; }
        .hero__title .accent {
            background: linear-gradient(120deg, var(--green-glow) 0%, var(--yellow-energy) 50%, var(--orange-fire) 100%);
            -webkit-background-clip: text; background-clip: text; color: transparent;
            filter: drop-shadow(0 0 28px rgba(61,255,154,0.25));
        }

        .hero__sub {
            max-width: 720px; margin: 0 auto;
            font-size: clamp(15px, 1.6vw, 19px); line-height: 1.6;
            color: var(--gray-text); font-weight: 400;
            opacity: 0;
        }
        .hero__sub strong { color: var(--white-soft); font-weight: 600; }

        .hero__ctas {
            display: flex; flex-wrap: wrap; gap: 14px; justify-content: center;
            margin-top: 44px; opacity: 0;
        }
        .btn {
            position: relative; display: inline-flex; align-items: center; justify-content: center; gap: 10px;
            min-height: 48px; padding: 14px 28px; border-radius: 14px;
            font-family: var(--font-display); font-weight: 700; font-size: 14px;
            letter-spacing: .05em; text-transform: uppercase;
            cursor: pointer; border: 1px solid transparent; overflow: hidden;
            transition: transform .35s var(--ease-cinema), box-shadow .35s var(--ease-cinema), background .35s var(--ease-cinema), border-color .35s var(--ease-cinema), color .35s var(--ease-cinema);
            will-change: transform, box-shadow;
        }
        .btn::before {
            content: ""; position: absolute; inset: 0;
            background: linear-gradient(120deg, rgba(255,255,255,0.18), transparent 35%, rgba(255,255,255,0.04) 65%, transparent);
            opacity: 0; transform: translateX(-18%); transition: opacity .35s var(--ease-cinema), transform .55s var(--ease-cinema);
            pointer-events: none;
        }
        .btn:hover::before { opacity: 1; transform: translateX(18%); }
        .btn:focus-visible {
            outline: 2px solid rgba(61,255,154,0.7);
            outline-offset: 3px;
        }
        .btn svg { width: 16px; height: 16px; transition: transform .35s var(--ease-cinema); }
        .btn:hover svg { transform: translateX(4px); }
        .btn:hover { transform: translateY(-3px); }

        .btn--primary {
            color: var(--black-1);
            background: linear-gradient(135deg, var(--green-glow), var(--green-neon));
            box-shadow: 0 12px 32px -12px rgba(61,255,154,0.65), inset 0 0 0 1px rgba(255,255,255,0.18);
        }
        .btn--primary:hover { box-shadow: 0 18px 40px -10px rgba(61,255,154,0.75), 0 0 18px rgba(61,255,154,0.15); }

        .btn--secondary {
            color: #dff3ff;
            background: linear-gradient(135deg, rgba(14,165,233,0.26), rgba(14,61,110,0.42));
            border-color: rgba(56,189,248,0.28);
            box-shadow: 0 12px 30px -14px rgba(14,165,233,0.55);
        }
        .btn--secondary:hover { border-color: rgba(56,189,248,0.52); box-shadow: 0 18px 38px -12px rgba(14,165,233,0.52), 0 0 16px rgba(14,165,233,0.14); }

        .btn--ambiental {
            color: #fff3ea;
            background: linear-gradient(135deg, rgba(255,176,88,0.96), rgba(255,107,0,0.86));
            border-color: rgba(255,176,88,0.18);
            box-shadow: 0 12px 30px -14px rgba(255,107,0,0.58);
        }
        .btn--ambiental:hover { box-shadow: 0 18px 38px -12px rgba(255,107,0,0.52), 0 0 16px rgba(255,107,0,0.12); }

        .btn--institutional {
            color: #f2ecff;
            background: linear-gradient(135deg, rgba(92,48,182,0.96), rgba(51,27,102,0.98));
            border-color: rgba(167,139,250,0.22);
            box-shadow: 0 12px 30px -14px rgba(92,48,182,0.54);
        }
        .btn--institutional:hover { box-shadow: 0 18px 38px -12px rgba(92,48,182,0.5), 0 0 16px rgba(167,139,250,0.12); }

        .btn--ghost {
            color: var(--white-soft);
            background: rgba(255,255,255,0.04);
            border-color: rgba(255,255,255,0.18);
            backdrop-filter: blur(10px);
        }
        .btn--ghost:hover { background: rgba(255,255,255,0.1); border-color: rgba(61,255,154,0.45); color: var(--green-glow); box-shadow: 0 10px 24px -12px rgba(61,255,154,0.22); }

        @keyframes spin { to { transform: rotate(1turn); } }

        /* KPIs Cinematográficos */
        .hero__kpis {
            margin-top: 64px; display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 24px; max-width: 720px; margin-left:auto; margin-right:auto;
            opacity: 0;
        }
        .kpi { text-align: center; padding: 18px 8px; border-left: 1px solid rgba(61,255,154,0.2); transition: all .3s var(--ease-cinema); }
        .kpi:hover { border-left-color: rgba(61,255,154,0.6); transform: translateY(-2px); }
        .kpi:first-child { border-left: none; }
        .kpi__num { font-family: var(--font-display); font-weight: 800; font-size: clamp(22px, 3vw, 30px); color: var(--white-soft); background: linear-gradient(120deg, var(--white-soft), var(--green-glow)); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .kpi__num em { font-style: normal; color: var(--orange-fire); }
        .kpi__label { font-size: 11px; letter-spacing: .2em; text-transform: uppercase; color: var(--gray-text); margin-top: 6px; font-weight: 500; }

        /* Hero Stats (deprecated, manter por compatibilidade) */
        .hero__stats {
            margin-top: 64px; display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 24px; max-width: 720px; margin-left:auto; margin-right:auto;
            opacity: 0;
        }
        .stat { text-align: center; padding: 18px 8px; border-left: 1px solid rgba(255,255,255,0.08); }
        .stat:first-child { border-left: none; }
        .stat__num { font-family: var(--font-display); font-weight: 800; font-size: clamp(22px, 3vw, 30px); color: var(--white-soft); }
        .stat__num em { font-style: normal; color: var(--orange-fire); }
        .stat__label { font-size: 11px; letter-spacing: .2em; text-transform: uppercase; color: var(--gray-text); margin-top: 6px; }

        /* Scroll cue */
        .hero__scroll {
            position: absolute; bottom: 28px; left: 50%; transform: translateX(-50%);
            z-index: 4; font-size: 10px; letter-spacing: .35em; text-transform: uppercase;
            color: var(--gray-text); display: flex; flex-direction: column; align-items: center; gap: 10px;
            opacity: 0;
        }
        .scroll__bar {
            width: 1px; height: 50px; background: linear-gradient(to bottom, var(--green-glow), transparent);
            animation: drop 2.4s var(--ease-cinema) infinite;
        }
        @keyframes drop { 0% { transform: scaleY(0); transform-origin: top; } 50% { transform: scaleY(1); transform-origin: top; } 51% { transform: scaleY(1); transform-origin: bottom; } 100% { transform: scaleY(0); transform-origin: bottom; } }
    </style>
</head>
<body>

    {{-- ============ NAVBAR ============ --}}
    <header class="nav" id="nav">
        <a href="#" class="nav__brand" aria-label="BoitaTech — início">
            <span class="nav__mark" aria-hidden="true"></span>
            <span>Boita<em>Tech</em></span>
        </a>

        <nav class="nav__links" aria-label="Navegação principal">
            <a href="#inicio">Início</a>
            <a href="#denuncias">Denúncias</a>
            <a href="#ecopontos">Ecopontos</a>
            <a href="#noticias">BoitaNews</a>
        </nav>



        <button class="nav__burger" aria-label="Abrir menu">
            <span></span><span></span><span></span>
        </button>
    </header>

    {{-- ============ HERO ============ --}}
    <section class="hero" id="inicio">
        <div class="hero__media" aria-hidden="true">
            {{-- Canvas fallback animado (Boitatá em partículas de fogo) --}}
            <canvas class="hero__canvas" id="boitataCanvas"></canvas>

            {{-- Vídeo cinematográfico do Boitatá (substitua o src abaixo) --}}
            <video
                class="hero__video"
                id="boitataVideo"
                autoplay muted loop playsinline preload="auto"
            >
                <source src="{{ asset('video/boitata.mp4') }}" type="video/mp4" />
            </video>
        </div>

        <div class="hero__overlay"></div>
        <div class="hero__grain"></div>

        <div class="hero__content">
            <div class="hero__eyebrow" data-anim="eyebrow">
                <span class="dot"></span>
                Monitoramento ao vivo • Dados INPE
            </div>

            <h1 class="hero__title">
                <span class="line"><span class="word">Inteligência</span></span>
                <span class="line"><span class="word">para a</span></span>
                <span class="line"><span class="word accent">Amazônia</span></span>
            </h1>

            <p class="hero__sub" data-anim="sub">
                <strong>BoitaTech</strong> monitora queimadas, desmatamento e ameaças ambientais
                em tempo quase real. Dados oficiais do INPE visualizados em mapa 3D cinematográfico.
                A floresta merece tecnologia de classe mundial.
            </p>


            {{-- KPIs Animados --}}
            <div class="hero__kpis" data-anim="stats">
                <div class="kpi">
                    <div class="kpi__num">24/7</div>
                    <div class="kpi__label">monitoramento ativo</div>
                </div>
                <div class="kpi">
                    <div class="kpi__num"><em>Real-time</em></div>
                    <div class="kpi__label">dados geoespaciais</div>
                </div>
            </div>
        </div>

        <div class="hero__scroll" data-anim="scroll">
            <span>Role para explorar</span>
            <div class="scroll__bar"></div>
        </div>
    </section>

    {{-- ============ SEÇÃO: PLATAFORMA BOITATECH ============ --}}
    <section class="section section--dark" id="sobre">
        <div class="container">
            <div class="section__head">
                <div class="eyebrow">
                    <span class="dot"></span>
                    Plataforma inteligente
                </div>
                <h2 class="h2">BoitaTech <span class="h2__accent">Intelligence Platform</span></h2>
                <p class="lead">Monitoramento ambiental de classe mundial com tecnologia geoespacial 3D, dados do INPE e análise em tempo real da Amazônia</p>
            </div>

            <div class="two-col module-split--media-first-mobile">
                <div class="col--text">
                    <div class="eyebrow">
                        <span class="dot"></span>
                        Inteligência geoespacial
                    </div>

                    <h3 style="font-family: var(--font-display); font-weight: 700; font-size: 20px; color: var(--white-soft); margin: 14px 0;">
                        Monitoramento <span style="color: var(--green-glow);">em tempo real</span> da Amazônia
                    </h3>

                    <p style="color: var(--gray-text); font-size: 15px; line-height: 1.7; margin-bottom: 24px;">
                        Nossa plataforma integra dados geoespaciais do INPE com visualização 3D cinematográfica, oferecendo uma visão integrada e profunda da Amazônia. Detecte queimadas ativas, padrões de desmatamento e ameaças ambientais com precisão cirúrgica e latência mínima.
                    </p>

                    <ul class="checks" style="margin: 24px 0;">
                        <li>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            <span><strong>Dados INPE</strong> em tempo quase real</span>
                        </li>
                        <li>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            <span><strong>Mapa 3D</strong> com CesiumJS cinematográfico</span>
                        </li>
                        <li>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            <span><strong>Alertas inteligentes</strong> com clustering automático</span>
                        </li>
                        <li>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            <span><strong>BoitaNews</strong> integrado com análise jornalística</span>
                        </li>
                    </ul>

                    <div class="section-actions">
                        <a href="{{ route('mapa.index') }}" class="btn btn--primary">
                            <span>Explorar plataforma</span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>

                <div class="col--media">
                    {{-- Mockup Premium com Glassmorphism --}}
                    <div class="platform-showcase" data-anim="zoom-in">
                        <div class="showcase__frame">
                            <div class="showcase__header">
                                <div class="showcase__dot"></div>
                                <div class="showcase__dot"></div>
                                <div class="showcase__dot"></div>
                            </div>
                            <div class="showcase__content">
                                <div class="media-frame media-frame--glow" style="height: 100%; margin: 0;">
                                    <img src="{{ asset('assets/landing/mapa.png') }}" alt="Dashboard do mapa 3D BoitaTech" loading="lazy" />
                                </div>
                            </div>
                        </div>
                        <div class="showcase__glow" aria-hidden="true"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ SEÇÃO: BOITANEWS ============ --}}
    <section class="section section--dark" id="noticias">
        <div class="container">
            <div class="section__head">
                <div class="eyebrow">
                    <span class="dot"></span>
                    Jornalismo investigativo
                </div>
                <h2 class="h2">BoitaNews: <span class="h2__accent">Inteligência Editorial</span></h2>
                <p class="lead">Uma camada editorial pensada para transformar monitoramento ambiental, sinais territoriais e contexto climático em leitura estratégica.</p>
            </div>

            <div class="two-col boitanews-layout module-split--media-first-mobile">
                <div class="col--text module-copy boitanews-copy">
                    <div class="boitanews-label">Radar editorial em tempo quase real</div>

                  

                    <p class="boitanews-subtitle">Notícias ambientais, inteligência climática e leitura geoespacial da Amazônia.</p>

                    <p class="boitanews-lead">
                        BoitaNews conecta <strong>sinais ambientais, contexto territorial e atualizações críticas</strong> em uma experiência editorial desenhada para quem deseja entender o nosso contexto ambiental atual.
                    </p>

                    <div class="boitanews-meta">
                        <span class="boitanews-pill">Monitoramento editorial</span>
                        <span class="boitanews-pill">Eventos ambientais relevantes</span>
                        <span class="boitanews-pill">Leitura premium de dados</span>
                    </div>

                    <div class="module-actions">
                        <a href="{{ route('boitanews.index') }}" class="btn btn--ambiental">
                            <span>Acessar BoitaNews</span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>

                <div class="col--media">
                    <div class="module-media" data-anim="zoom-in">
                        <div class="module-media__frame media-frame media-frame--glow">
                            <img src="{{ asset('assets/landing/noticias.png') }}" alt="Preview do painel do BoitaNews" loading="lazy" />
                        </div>
                        <div class="module-media__caption">
                            <div>
                                <strong>Preview do sistema BoitaNews</strong>
                                <span>Painel editorial com inteligência climática e leitura geoespacial da Amazônia.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ SEÇÃO: DENÚNCIAS AMBIENTAIS ============ --}}
    <section class="section section--alt" id="denuncias">
        <div class="container">
            <div class="section__head">
                <div class="eyebrow">
                    <span class="dot"></span>
                    Participação social
                </div>
                <h2 class="h2">Denúncias ambientais <span class="h2__accent">georreferenciadas</span></h2>
                <p class="lead">A comunidade também monitora o território: relatos ambientais organizados em mapa, com leitura clara, impacto social e visão em tempo real.</p>
            </div>

            <div class="two-col">
                <div class="col--text module-copy">
                    <div class="eyebrow">
                        <span class="dot"></span>
                        Rede cidadã de monitoramento
                    </div>

                    <h3 class="module-copy__title">A inteligência ambiental também nasce <span class="accent">da comunidade</span></h3>

                    <p class="module-copy__lead">
                        Registre ocorrências ambientais, visualize relatos por localização e acompanhe um fluxo territorial pensado para revelar padrões, priorizar respostas e ampliar a consciência coletiva.
                    </p>

                    <div class="module-copy__meta">
                        <span class="module-pill"><span class="dot"></span> Relatos públicos e organizados</span>
                        <span class="module-pill"><span class="dot"></span> Visualização georreferenciada</span>
                        <span class="module-pill"><span class="dot"></span> Impacto social em escala</span>
                    </div>

                    <ul class="checks">
                        <li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><span><strong>Monitoramento cidadão</strong> com leitura territorial clara</span></li>
                        <li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><span><strong>Denúncias georreferenciadas</strong> com contexto visual premium</span></li>
                        <li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><span><strong>Experiência pronta para expansão</strong> nacional sem mudar o frontend</span></li>
                    </ul>

                    <div class="module-actions">
                        <a href="{{ route('denuncias.index') }}" class="btn btn--ambiental">
                            <span>Visualizar denúncias</span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>

                <div class="col--media">
                    <div class="module-media" data-anim="zoom-in">
                        <div class="module-media__frame media-frame media-frame--glow">
                            <img src="{{ asset('assets/landing/Denuncias%20portal.png') }}" alt="Preview do portal de denúncias ambientais" loading="lazy" />
                        </div>
                        <div class="module-media__caption">
                            <div>
                                <strong>Preview do sistema de denúncias</strong>
                                <span>Relatos georreferenciados e leitura territorial da participação social.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="module-actions module-actions--center" style="margin-top: 30px;">
            </div>
        </div>
    </section>

    {{-- ============ SEÇÃO: PONTOS DE COLETA ============ --}}
    <section class="section section--dark" id="ecopontos">
        <div class="container">
            <div class="section__head">
                <div class="eyebrow">
                    <span class="dot"></span>
                    Reciclagem inteligente
                </div>
                <h2 class="h2">Pontos de coleta <span class="h2__accent">seletiva</span></h2>
                <p class="lead">Encontre ecopontos próximos, descubra locais de descarte correto e conecte sustentabilidade urbana com uma navegação visual elegante.</p>
            </div>

            <div class="two-col two-col--reverse">
                <div class="col--media">
                    <div class="module-media" data-anim="zoom-in">
                        <div class="module-media__frame media-frame media-frame--glow">
                            <img src="{{ asset('assets/landing/pontos%20coleta.png') }}" alt="Preview dos pontos de coleta seletiva" loading="lazy" />
                        </div>
                        <div class="module-media__caption">
                            <div>
                                <strong>Preview do mapa de ecopontos</strong>
                                <span>Mapa urbano com pontos de descarte correto e navegação inteligente.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col--text module-copy">
                    <div class="eyebrow">
                        <span class="dot"></span>
                        Descarte correto e acessível
                    </div>

                    <h3 class="module-copy__title">Um mapa urbano para <span class="accent">reciclar com precisão</span></h3>

                    <p class="module-copy__lead">
                        Visualize ecopontos em Manaus, encontre alternativas confiáveis de descarte e incentive rotas de reciclagem mais simples, rápidas e sustentáveis para a cidade.
                    </p>

                    <div class="module-copy__meta">
                        <span class="module-pill"><span class="dot"></span> Mapa inteligente de ecopontos</span>
                        <span class="module-pill"><span class="dot"></span> Descoberta por bairro e zona</span>
                        <span class="module-pill"><span class="dot"></span> Experiência premium e rápida</span>
                    </div>

                    <ul class="checks">
                        <li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><span><strong>Sustentabilidade urbana</strong> com navegação prática</span></li>
                        <li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><span><strong>Descarte correto</strong> com foco em acessibilidade e impacto positivo</span></li>
                        <li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><span><strong>Escalável para novas cidades</strong> sem alterar a experiência do frontend</span></li>
                    </ul>

                    <div class="module-actions">
                        <a href="{{ route('ecopontos.index') }}" class="btn btn--primary">
                            <span>Ver pontos de coleta</span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ CTA FINAL ============ --}}
    <section class="cta-section">
        <div class="cta-section__bg"></div>
        <div class="cta-section__glow cta-section__glow--g"></div>
        <div class="cta-section__glow cta-section__glow--o"></div>

        <div class="container">
            <div class="cta-section__inner">
                <h2 class="h2" data-anim="zoom-in">
                    Tecnologia ambiental para a <span class="h2__accent">Amazônia</span>
                </h2>
                <p class="lead" style="margin: 0; color: var(--gray-text); max-width: 720px;" data-anim="zoom-in">
                    Inteligência geoespacial em tempo real, dados oficiais do INPE, e jornalismo ambiental integrado. A floresta merece monitoramento de classe mundial.
                </p>

                <div class="cta-section__btns" data-anim="zoom-in">
                    <a href="{{ route('mapa.index') }}" class="btn btn--primary">
                        Acessar plataforma
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                    </a>
                    <a href="#denuncias" class="btn btn--secondary">
                        Explorar denúncias
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ FOOTER ============ --}}
    <footer class="footer">
        <div class="container">
            <div class="footer__inner">
                <div class="footer__brand">
                    <h3>Boita<em>Tech</em></h3>
                    <p>Inteligência ambiental de ponta para monitorar a Amazônia em tempo real. Dados, precisão e urgência a serviço da floresta.</p>
                </div>
            </div>

            <div class="footer__bottom">
                <p>&copy; 2026 BoitaTech. Inteligência ambiental para a Amazônia.</p>
            </div>
        </div>
    </footer>


</body>
</html>
