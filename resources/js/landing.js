/**
 * BoitaTech Landing Page — Animações Cinematográficas
 * GSAP + ScrollTrigger + Canvas
 */

import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// ============ NAVBAR SCROLL ============
function initNavbar() {
    const nav = document.getElementById('nav');
    if (!nav) return;

    const onScroll = () => nav.classList.toggle('scrolled', window.scrollY > 24);
    onScroll();

    let ticking = false;
    window.addEventListener(
        'scroll',
        () => {
            if (!ticking) {
                requestAnimationFrame(() => {
                    onScroll();
                    ticking = false;
                });
                ticking = true;
            }
        },
        { passive: true }
    );
}

// ============ SMOOTH SCROLL ============
function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(a => {
        a.addEventListener('click', e => {
            const id = a.getAttribute('href');
            if (id.length > 1) {
                const target = document.querySelector(id);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        });
    });
}

// ============ CANVAS: SERPENTE DE FOGO BOITATÁ ============
function initCanvas() {
    const canvas = document.getElementById('boitataCanvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    let W, H, dpr, particles = [], t = 0, raf, running = true;

    function resize() {
        dpr = Math.min(window.devicePixelRatio || 1, 2);
        W = canvas.width = window.innerWidth * dpr;
        H = canvas.height = window.innerHeight * dpr;
        canvas.style.width = window.innerWidth + 'px';
        canvas.style.height = window.innerHeight + 'px';
    }

    resize();
    window.addEventListener('resize', () => requestAnimationFrame(resize), { passive: true });

    function serpentPoint(time) {
        const cx = W * 0.5, cy = H * 0.55;
        const ax = W * 0.35, ay = H * 0.18;
        return {
            x: cx + Math.sin(time * 0.7) * ax + Math.sin(time * 1.7) * 40 * dpr,
            y: cy + Math.sin(time * 1.1) * ay + Math.cos(time * 0.5) * 30 * dpr,
        };
    }

    function spawn(time) {
        const head = serpentPoint(time);
        const count = 4;
        for (let i = 0; i < count; i++) {
            particles.push({
                x: head.x + (Math.random() - 0.5) * 18 * dpr,
                y: head.y + (Math.random() - 0.5) * 18 * dpr,
                vx: (Math.random() - 0.5) * 0.6 * dpr,
                vy: -Math.random() * 0.8 * dpr - 0.2,
                life: 0,
                max: 80 + Math.random() * 60,
                size: (2 + Math.random() * 3) * dpr,
                hue: 18 + Math.random() * 30,
            });
        }
    }

    function draw() {
        if (!running) return;
        t += 0.008;

        // Trail fade
        ctx.fillStyle = 'rgba(5,5,5,0.18)';
        ctx.fillRect(0, 0, W, H);

        // Luz ambiental verde-amazônia
        const g = ctx.createRadialGradient(W * 0.5, H * 0.55, 0, W * 0.5, H * 0.55, W * 0.6);
        g.addColorStop(0, 'rgba(11,61,46,0.25)');
        g.addColorStop(1, 'rgba(5,5,5,0)');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, W, H);

        spawn(t);

        ctx.globalCompositeOperation = 'lighter';
        for (let i = particles.length - 1; i >= 0; i--) {
            const p = particles[i];
            p.life++;
            p.x += p.vx;
            p.y += p.vy;
            p.vy -= 0.01 * dpr;
            const a = 1 - p.life / p.max;
            if (a <= 0) {
                particles.splice(i, 1);
                continue;
            }
            const r = p.size * a * 4;
            const grad = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, r);
            grad.addColorStop(0, `hsla(${p.hue}, 100%, 60%, ${0.55 * a})`);
            grad.addColorStop(0.4, `hsla(${p.hue - 10}, 100%, 45%, ${0.25 * a})`);
            grad.addColorStop(1, 'hsla(0,0%,0%,0)');
            ctx.fillStyle = grad;
            ctx.beginPath();
            ctx.arc(p.x, p.y, r, 0, Math.PI * 2);
            ctx.fill();
        }
        ctx.globalCompositeOperation = 'source-over';

        raf = requestAnimationFrame(draw);
    }

    document.addEventListener('visibilitychange', () => {
        running = !document.hidden;
        if (running) raf = requestAnimationFrame(draw);
        else cancelAnimationFrame(raf);
    });

    if (!reduce) raf = requestAnimationFrame(draw);
}

// ============ VÍDEO LAZY ============
function initVideo() {
    const video = document.getElementById('boitataVideo');
    if (!video) return;

    const isMobile = window.matchMedia('(max-width: 768px)').matches;
    if (isMobile) return; // Não carregar vídeo em mobile

    const loadVideo = () => {
        video.load();
        video.addEventListener(
            'canplay',
            () => {
                video.classList.add('is-ready');
            },
            { once: true }
        );
    };

    if (typeof requestIdleCallback !== 'undefined') {
        requestIdleCallback(loadVideo);
    } else {
        setTimeout(loadVideo, 1200);
    }
}

// ============ GSAP HERO ANIMATIONS ============
function initHeroAnimations() {
    if (reduce) {
        document.querySelectorAll('[data-anim]').forEach(el => {
            el.style.opacity = '1';
            el.style.transform = 'none';
        });
        gsap.set('.hero__title .word', { y: 0 });
        return;
    }

    // Timeline de entrada da hero
    const tl = gsap.timeline({ defaults: { ease: 'expo.out' } });
    gsap.set('.hero__title .word', { yPercent: 110 });

    tl.to('[data-anim="eyebrow"]', { opacity: 1, y: 0, duration: 1, ease: 'power3.out' })
        .to('.hero__title .word', { yPercent: 0, duration: 1.4, stagger: 0.12 }, '-=0.6')
        .to('[data-anim="sub"]', { opacity: 1, y: 0, duration: 1 }, '-=0.7')
        .to('[data-anim="ctas"]', { opacity: 1, y: 0, duration: 0.9 }, '-=0.6')
        .to('[data-anim="stats"]', { opacity: 1, y: 0, duration: 0.9 }, '-=0.6')
        .to('[data-anim="scroll"]', { opacity: 1, duration: 0.8 }, '-=0.4');

    // Parallax hero
    gsap.to('.hero__content', {
        yPercent: -8,
        ease: 'none',
        scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: true },
    });
}

// ============ SCROLL REVEAL ANIMATIONS ============
function initScrollReveals() {
    const revealOpts = { threshold: 0.15, rootMargin: '0px 0px -40px 0px' };
    const io = new IntersectionObserver(entries => {
        entries.forEach(en => {
            if (en.isIntersecting) {
                en.target.classList.add('in');
                io.unobserve(en.target);
            }
        });
    }, revealOpts);

    document.querySelectorAll('[data-anim]').forEach(el => io.observe(el));
}

// ============ MEDIA FRAME ZOOM ON SCROLL ============
function initMediaFrameAnimations() {
    document.querySelectorAll('[data-anim="zoom-in"]').forEach(el => {
        gsap.fromTo(
            el,
            { scale: 0.96 },
            {
                scale: 1,
                ease: 'none',
                scrollTrigger: {
                    trigger: el,
                    start: 'top 85%',
                    end: 'bottom 20%',
                    scrub: true,
                },
            }
        );
    });
}

// ============ INIT ALL ============
document.addEventListener('DOMContentLoaded', () => {
    initNavbar();
    initSmoothScroll();
    initCanvas();
    initVideo();
});

window.addEventListener('load', () => {
    initHeroAnimations();
    initScrollReveals();
    initMediaFrameAnimations();
});
