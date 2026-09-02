import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function initHeaderShrink() {
    const header = document.querySelector('.site-header');
    if (!header) return;

    const onScroll = () => header.classList.toggle('is-scrolled', window.scrollY > 8);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
}

function initMagneticButtons() {
    const strength = 0.25;

    document.querySelectorAll('[data-magnetic]').forEach((el) => {
        el.addEventListener('mousemove', (e) => {
            const rect = el.getBoundingClientRect();
            const x = (e.clientX - rect.left - rect.width / 2) * strength;
            const y = (e.clientY - rect.top - rect.height / 2) * strength;
            el.style.transform = `translate(${x}px, ${y}px)`;
        });

        el.addEventListener('mouseleave', () => {
            el.style.transform = '';
        });

        el.addEventListener('mousedown', () => {
            el.style.transform += ' scale(0.96)';
        });

        el.addEventListener('mouseup', () => {
            el.style.transform = el.style.transform.replace(' scale(0.96)', '');
        });
    });
}

function initTiltCards() {
    const maxTilt = 6;

    document.querySelectorAll('[data-tilt]').forEach((el) => {
        el.addEventListener('mousemove', (e) => {
            const rect = el.getBoundingClientRect();
            const px = (e.clientX - rect.left) / rect.width - 0.5;
            const py = (e.clientY - rect.top) / rect.height - 0.5;
            el.style.transform = `perspective(600px) rotateX(${-py * maxTilt}deg) rotateY(${px * maxTilt}deg) translateY(-2px)`;
        });

        el.addEventListener('mouseleave', () => {
            el.style.transform = '';
        });
    });
}

function initScrollReveals() {
    gsap.utils.toArray('[data-reveal]').forEach((el) => {
        gsap.from(el, {
            opacity: 0,
            y: 28,
            duration: 0.6,
            ease: 'power2.out',
            scrollTrigger: {
                trigger: el,
                start: 'top 85%',
                once: true,
            },
        });
    });

    gsap.utils.toArray('[data-reveal-group]').forEach((group) => {
        const items = group.querySelectorAll('[data-reveal-item]');
        if (!items.length) return;

        gsap.from(items, {
            opacity: 0,
            y: 24,
            duration: 0.5,
            ease: 'power2.out',
            stagger: 0.08,
            scrollTrigger: {
                trigger: group,
                start: 'top 85%',
                once: true,
            },
        });
    });
}

function initHeroEntrance() {
    const hero = document.querySelector('[data-hero]');
    if (!hero) return;

    const targets = hero.querySelectorAll('[data-hero-item]');
    if (!targets.length) return;

    gsap.from(targets, {
        opacity: 0,
        y: 20,
        duration: 0.7,
        ease: 'power2.out',
        stagger: 0.12,
        delay: 0.1,
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initHeaderShrink();
    initMagneticButtons();

    if (!reduceMotion) {
        initTiltCards();
        initScrollReveals();
        initHeroEntrance();
    }
});
