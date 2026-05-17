/* ============================================================
   BOITATECH — landing.js
   Motion + interações (GSAP + Anime.js + vanilla)
   ============================================================ */
(() => {
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- NAVBAR ---------- */
  const nav = document.getElementById('nav');
  const burger = document.getElementById('navBurger');
  const drawer = document.getElementById('navDrawer');

  const onScroll = () => {
    if (window.scrollY > 24) nav.classList.add('is-scrolled');
    else nav.classList.remove('is-scrolled');
  };
  let ticking = false;
  window.addEventListener('scroll', () => {
    if (!ticking) {
      requestAnimationFrame(() => { onScroll(); ticking = false; });
      ticking = true;
    }
  }, { passive: true });
  onScroll();

  burger?.addEventListener('click', () => {
    const open = drawer.classList.toggle('is-open');
    burger.classList.toggle('is-open', open);
    burger.setAttribute('aria-expanded', String(open));
  });
  drawer?.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
    drawer.classList.remove('is-open');
    burger.classList.remove('is-open');
    burger.setAttribute('aria-expanded', 'false');
  }));

  /* ---------- HERO CANVAS (fallback Boitatá em partículas) ---------- */
  const canvas = document.getElementById('heroCanvas');
  if (canvas && !reduced) {
    const ctx = canvas.getContext('2d', { alpha: true });
    const DPR = Math.min(window.devicePixelRatio || 1, 2);
    let W = 0, H = 0;

    const resize = () => {
      W = canvas.clientWidth; H = canvas.clientHeight;
      canvas.width = W * DPR; canvas.height = H * DPR;
      ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
    };
    resize();
    window.addEventListener('resize', () => requestAnimationFrame(resize), { passive: true });

    const N = 90;
    const pts = Array.from({ length: N }, (_, i) => ({
      a: i / N * Math.PI * 2,
      r: 80 + Math.random() * 120,
      s: 0.4 + Math.random() * 0.6,
      o: Math.random() * Math.PI * 2,
    }));
    let t = 0, raf = 0, running = true;

    const draw = () => {
      if (!running) return;
      t += 0.006;
      ctx.clearRect(0, 0, W, H);
      const cx = W * 0.5, cy = H * 0.55;

      for (let i = 0; i < N; i++) {
        const p = pts[i];
        const k = i / N;
        const x = cx + Math.cos(p.a + t * p.s) * (p.r + Math.sin(t * 0.7 + p.o) * 60);
        const y = cy + Math.sin(p.a * 2 + t * p.s * 1.3) * (p.r * 0.6 + Math.cos(t + p.o) * 40);
        const radius = 1.4 + Math.sin(t * 2 + i) * 1.2;
        const hue = 145 + Math.sin(t + k * 6) * 20;
        ctx.beginPath();
        ctx.fillStyle = `hsla(${hue},90%,60%,${0.55 - k * 0.3})`;
        ctx.shadowColor = `hsla(${hue},100%,55%,.9)`;
        ctx.shadowBlur = 14;
        ctx.arc(x, y, radius, 0, Math.PI * 2);
        ctx.fill();
      }
      raf = requestAnimationFrame(draw);
    };
    draw();

    document.addEventListener('visibilitychange', () => {
      running = !document.hidden;
      if (running) draw();
      else cancelAnimationFrame(raf);
    });
  }

  /* ---------- GSAP / Anime — aguarda scripts carregarem ---------- */
  const ready = () => {
    const hasGsap = typeof window.gsap !== 'undefined';
    const hasST = hasGsap && typeof window.ScrollTrigger !== 'undefined';
    const hasAnime = typeof window.anime !== 'undefined';

    /* ----- Hero reveal ----- */
    if (hasGsap && !reduced) {
      // Quebra título em palavras
      document.querySelectorAll('[data-reveal-words]').forEach(el => {
        const words = el.textContent.trim().split(/\s+/);
        el.innerHTML = words.map(w => `<span class="word"><span style="display:inline-block">${w}</span></span>`).join(' ');
      });

      const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });
      tl.to('[data-reveal]', { opacity: 1, y: 0, duration: 0.9, stagger: 0.12 }, 0.1)
        .to('[data-reveal-words] .word > span', {
          y: 0, opacity: 1, duration: 1.1, stagger: 0.05,
          onStart() { gsap.set('[data-reveal-words] .word > span', { opacity: 0, y: '60%' }); }
        }, 0.15);
    } else {
      document.querySelectorAll('[data-reveal]').forEach(e => { e.style.opacity = 1; e.style.transform = 'none'; });
    }

    /* ----- Scroll reveals (IntersectionObserver universal) ----- */
    const io = new IntersectionObserver((entries) => {
      entries.forEach(en => {
        if (en.isIntersecting) {
          const delay = parseInt(en.target.dataset.animDelay || '0', 10);
          setTimeout(() => en.target.classList.add('is-in'), delay);
          io.unobserve(en.target);
        }
      });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
    document.querySelectorAll('[data-anim]').forEach(el => io.observe(el));

    /* ----- ScrollTrigger: parallax leve no hero + zoom no mapa ----- */
    if (hasST && !reduced) {
      gsap.registerPlugin(ScrollTrigger);

      gsap.to('.hero__content', {
        y: 80, opacity: 0.6, ease: 'none',
        scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: true }
      });
      gsap.to('.hero__video, .hero__canvas', {
        scale: 1.08, ease: 'none',
        scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: true }
      });

      const mapMedia = document.getElementById('mapMedia');
      if (mapMedia) {
        gsap.fromTo(mapMedia,
          { scale: 0.96 },
          {
            scale: 1.04, ease: 'none',
            scrollTrigger: { trigger: mapMedia, start: 'top 85%', end: 'bottom 20%', scrub: true }
          });
      }
    }

    /* ----- Anime.js — pulse sutil em dots dos chips ----- */
    if (hasAnime && !reduced) {
      anime({
        targets: '.hud__chip .dot',
        scale: [1, 1.25, 1],
        duration: 2200,
        delay: anime.stagger(220),
        easing: 'easeInOutSine',
        loop: true
      });
    }

    /* ----- Smooth scroll para âncoras (offset navbar) ----- */
    document.querySelectorAll('a[href^="#"]').forEach(a => {
      a.addEventListener('click', (e) => {
        const id = a.getAttribute('href');
        if (id.length > 1) {
          const tgt = document.querySelector(id);
          if (tgt) {
            e.preventDefault();
            const top = tgt.getBoundingClientRect().top + window.scrollY - 70;
            window.scrollTo({ top, behavior: 'smooth' });
          }
        }
      });
    });
  };

  // Aguarda libs externas (defer) carregarem
  if (document.readyState === 'complete') ready();
  else window.addEventListener('load', ready);
})();
