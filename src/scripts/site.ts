import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { Flip } from 'gsap/Flip';
import Lenis from 'lenis';

gsap.registerPlugin(ScrollTrigger, Flip);

const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function initThemeToggle() {
  const toggles = document.querySelectorAll<HTMLButtonElement>('[data-theme-toggle]');
  if (toggles.length === 0) return;

  function currentTheme(): 'light' | 'dark' {
    return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
  }

  function applyLabels(theme: 'light' | 'dark') {
    toggles.forEach((btn) => {
      btn.setAttribute('aria-pressed', String(theme === 'dark'));
      const label = btn.querySelector<HTMLElement>('.theme-label');
      if (label) label.textContent = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
    });
  }

  applyLabels(currentTheme());

  let transitionTimeout: number | undefined;

  toggles.forEach((btn) => {
    btn.addEventListener('click', () => {
      const next: 'light' | 'dark' = currentTheme() === 'dark' ? 'light' : 'dark';
      const root = document.documentElement;

      if (!prefersReducedMotion) {
        root.classList.add('theme-transitioning');
        window.clearTimeout(transitionTimeout);
        transitionTimeout = window.setTimeout(() => {
          root.classList.remove('theme-transitioning');
        }, 500);
      }

      root.setAttribute('data-theme', next);
      try {
        localStorage.setItem('theme', next);
      } catch {
        // Storage unavailable (private browsing, etc.) — theme still
        // applies for the current page, just won't persist.
      }
      applyLabels(next);
    });
  });
}

function initSmoothScroll() {
  if (prefersReducedMotion) return null;

  const lenis = new Lenis({
    duration: 1.1,
    smoothWheel: true,
  });

  lenis.on('scroll', ScrollTrigger.update);

  gsap.ticker.add((time) => {
    lenis.raf(time * 1000);
  });
  gsap.ticker.lagSmoothing(0);

  return lenis;
}

function scrollToTarget(lenis: Lenis | null, target: HTMLElement) {
  if (lenis) {
    lenis.scrollTo(target, { offset: 0, duration: 1.2 });
  } else {
    target.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth' });
  }
}

function initNav(lenis: Lenis | null) {
  const rigEl = document.querySelector<HTMLElement>('[data-rig]');
  if (!rigEl) return;
  const rig = rigEl;

  const mm = gsap.matchMedia();

  mm.add('(min-width: 900px)', () => {
    // Pages without a hero (e.g. /privacy-policy) have nothing to dock across —
    // render the sidebar permanently docked instead of wiring a trigger to
    // a '#home' that doesn't exist on this page.
    if (!document.getElementById('home')) {
      rig.setAttribute('data-docked', 'true');
      rig.setAttribute('data-settled', 'true');
      return;
    }

    let docked = false;

    function setDocked(next: boolean) {
      if (next === docked) return;
      docked = next;

      const pieces = gsap.utils.toArray<HTMLElement>('.rig-piece', rig);
      const state = Flip.getState(pieces);

      rig.removeAttribute('data-settled');
      if (docked) {
        rig.setAttribute('data-docked', 'true');
      } else {
        rig.removeAttribute('data-docked');
      }

      Flip.from(state, {
        duration: prefersReducedMotion ? 0 : 0.6,
        ease: 'power3.inOut',
        absolute: true,
        onComplete: () => {
          gsap.set(pieces, { clearProps: 'all' });
          if (docked) rig.setAttribute('data-settled', 'true');
          ScrollTrigger.refresh();
        },
      });
    }

    const trigger = ScrollTrigger.create({
      trigger: '#home',
      start: 'bottom 85%',
      onEnter: () => setDocked(true),
      onLeaveBack: () => setDocked(false),
    });

    if (prefersReducedMotion) setDocked(true);

    return () => {
      trigger.kill();
      rig.removeAttribute('data-docked');
    };
  });

  const navLinks = Array.from(
    document.querySelectorAll<HTMLAnchorElement>('.rig-nav a[data-section]')
  );

  function setActiveLink(id: string) {
    navLinks.forEach((link) => {
      link.classList.toggle('is-active', link.dataset.section === id);
    });
  }

  navLinks.forEach(({ dataset }) => {
    const id = dataset.section;
    if (!id) return;
    const section = document.getElementById(id);
    if (!section) return;

    ScrollTrigger.create({
      trigger: section,
      start: 'top center',
      end: 'bottom center',
      onToggle: (self) => {
        if (self.isActive) setActiveLink(id);
      },
    });
  });

  // On a standalone page (no '#home' section here), highlight whichever
  // nav link points at the current path instead of defaulting to Home.
  const pagePathMatch = navLinks.find((link) => {
    const href = link.getAttribute('href') ?? '';
    return !href.includes('#') && href === window.location.pathname;
  });
  const initialId = pagePathMatch?.dataset.section ?? navLinks[0]?.dataset.section;
  if (initialId) setActiveLink(initialId);

  document.querySelectorAll<HTMLAnchorElement>('[data-rail-tick], [data-mobile-link]').forEach((link) => {
    link.addEventListener('click', (event) => {
      // A mobile-menu link should close the menu whether it's a smooth
      // scroll target, a real page link, or an external booking link
      // opening in a new tab — the underlying page shouldn't stay hidden
      // behind an open menu once the tap has been acted on.
      if (link.hasAttribute('data-mobile-link')) closeMobileMenu();

      const href = link.getAttribute('href');
      if (!href) return;
      const hashIndex = href.indexOf('#');
      if (hashIndex === -1) return; // real/external link — let the browser navigate
      const hash = href.slice(hashIndex);
      const pathPart = href.slice(0, hashIndex);
      // A '/#id' link clicked from a different page needs a real navigation
      // to reach that section — only intercept for smooth-scroll when
      // we're already on the page the hash lives on.
      if (pathPart && pathPart !== window.location.pathname) return;
      const target = document.querySelector<HTMLElement>(hash);
      if (!target) return;
      event.preventDefault();
      scrollToTarget(lenis, target);
    });
  });
}

function closeMobileMenu() {
  const toggle = document.querySelector<HTMLButtonElement>('[data-menu-toggle]');
  const menu = document.querySelector<HTMLElement>('[data-mobile-menu]');
  const scrim = document.querySelector<HTMLElement>('[data-mobile-scrim]');
  if (!toggle || !menu) return;
  toggle.setAttribute('aria-expanded', 'false');
  menu.removeAttribute('data-open');
  scrim?.removeAttribute('data-open');
}

function initMobileMenu() {
  const toggle = document.querySelector<HTMLButtonElement>('[data-menu-toggle]');
  const menu = document.querySelector<HTMLElement>('[data-mobile-menu]');
  const scrim = document.querySelector<HTMLElement>('[data-mobile-scrim]');
  if (!toggle || !menu) return;

  toggle.addEventListener('click', () => {
    const isOpen = toggle.getAttribute('aria-expanded') === 'true';
    toggle.setAttribute('aria-expanded', String(!isOpen));
    if (isOpen) {
      menu.removeAttribute('data-open');
      scrim?.removeAttribute('data-open');
    } else {
      menu.setAttribute('data-open', 'true');
      scrim?.setAttribute('data-open', 'true');
    }
  });

  scrim?.addEventListener('click', () => closeMobileMenu());
}

function initReveals() {
  const items = gsap.utils.toArray<HTMLElement>('[data-reveal]');
  items.forEach((item) => {
    if (prefersReducedMotion) {
      gsap.set(item, { opacity: 1, y: 0 });
      return;
    }
    gsap.fromTo(
      item,
      { opacity: 0, y: 28 },
      {
        opacity: 1,
        y: 0,
        duration: 0.8,
        ease: 'power2.out',
        scrollTrigger: {
          trigger: item,
          start: 'top 85%',
        },
      }
    );
  });
}

function initWorkScroll() {
  const viewport = document.querySelector<HTMLElement>('[data-work-viewport]');
  const track = document.querySelector<HTMLElement>('[data-work-track]');
  if (!viewport || !track) return;

  const mm = gsap.matchMedia();

  mm.add('(min-width: 900px) and (prefers-reduced-motion: no-preference)', () => {
    viewport.classList.add('is-pinned');

    const getDistance = () => Math.max(0, track.scrollWidth - viewport.clientWidth);

    const tween = gsap.to(track, {
      x: () => -getDistance(),
      ease: 'none',
      scrollTrigger: {
        trigger: '#work',
        start: 'top top',
        end: () => '+=' + getDistance(),
        scrub: true,
        pin: true,
        invalidateOnRefresh: true,
      },
    });

    return () => {
      viewport.classList.remove('is-pinned');
      tween.scrollTrigger?.kill();
      tween.kill();
      gsap.set(track, { clearProps: 'transform' });
    };
  });
}

// Shared pointer drag-to-scroll: powers both the Work viewport (mobile only
// — the desktop pin owns horizontal movement there) and the testimonial
// carousel (always on, no pin).
function makeDragScroll(viewport: HTMLElement, isDisabled: () => boolean = () => false) {
  let dragging = false;
  let moved = false;
  let startX = 0;
  let startScroll = 0;

  viewport.addEventListener('pointerdown', (event) => {
    if (isDisabled()) return;
    dragging = true;
    moved = false;
    startX = event.clientX;
    startScroll = viewport.scrollLeft;
    viewport.setPointerCapture(event.pointerId);
    viewport.classList.add('is-dragging');
  });

  viewport.addEventListener('pointermove', (event) => {
    if (!dragging) return;
    const dx = event.clientX - startX;
    if (Math.abs(dx) > 4) moved = true;
    viewport.scrollLeft = startScroll - dx;
  });

  const endDrag = () => {
    if (!dragging) return;
    dragging = false;
    viewport.classList.remove('is-dragging');
  };

  viewport.addEventListener('pointerup', endDrag);
  viewport.addEventListener('pointercancel', endDrag);
  viewport.addEventListener('pointerleave', endDrag);

  // A drag that moved the track shouldn't also register as a click on
  // whatever card/link is under the pointer when it's released.
  viewport.addEventListener(
    'click',
    (event) => {
      if (moved) {
        event.preventDefault();
        event.stopPropagation();
      }
    },
    true
  );
}

function initWorkDrag() {
  const viewport = document.querySelector<HTMLElement>('[data-work-viewport]');
  if (!viewport) return;
  // The desktop pin (>=900px) already owns horizontal movement via GSAP
  // scrub — dragging here would fight it.
  makeDragScroll(viewport, () => viewport.classList.contains('is-pinned'));
}

interface SpotlightQuote {
  quote: string;
  name: string;
  role: string;
  company: string;
  accent: string;
}

function initServiceTabs() {
  const buttons = Array.from(document.querySelectorAll<HTMLButtonElement>('[data-tab-btn]'));
  const panels = Array.from(document.querySelectorAll<HTMLElement>('[data-tab-panel]'));
  if (buttons.length === 0 || panels.length === 0) return;

  buttons.forEach((btn) => {
    btn.addEventListener('click', () => {
      const target = btn.dataset.tabBtn;

      buttons.forEach((b) => {
        const isActive = b === btn;
        b.classList.toggle('is-active', isActive);
        b.setAttribute('aria-selected', String(isActive));
      });

      panels.forEach((panel) => {
        panel.hidden = panel.dataset.tabPanel !== target;
      });
    });
  });
}

function initTestimonialsSpotlight() {
  const root = document.querySelector<HTMLElement>('[data-quote-spotlight]');
  if (!root) return;

  let quotes: SpotlightQuote[] = [];
  try {
    quotes = JSON.parse(root.dataset.quotes ?? '[]');
  } catch {
    return;
  }
  if (quotes.length === 0) return;

  const textEl = root.querySelector<HTMLElement>('[data-quote-text]');
  const nameEl = root.querySelector<HTMLElement>('[data-quote-name]');
  const roleEl = root.querySelector<HTMLElement>('[data-quote-role]');
  const dotEl = root.querySelector<HTMLElement>('[data-quote-dot]');
  const currentEl = root.querySelector<HTMLElement>('[data-quote-current]');
  const prevBtn = root.querySelector<HTMLButtonElement>('[data-quote-prev]');
  const nextBtn = root.querySelector<HTMLButtonElement>('[data-quote-next]');
  if (!textEl || !nameEl || !roleEl || !dotEl || !currentEl) return;

  const group = [textEl, nameEl, roleEl, dotEl];
  let index = 0;
  let animating = false;

  function render() {
    const q = quotes[index];
    textEl!.textContent = `“${q.quote}”`;
    nameEl!.textContent = q.name;
    roleEl!.textContent = `${q.role} · ${q.company}`;
    dotEl!.style.background = `var(--a-${q.accent})`;
    currentEl!.textContent = String(index + 1).padStart(2, '0');
  }

  function goTo(next: number) {
    if (animating || quotes.length < 2) return;
    index = (next + quotes.length) % quotes.length;

    if (prefersReducedMotion) {
      render();
      return;
    }

    animating = true;
    gsap.to(group, {
      opacity: 0,
      y: -8,
      duration: 0.25,
      ease: 'power2.in',
      onComplete: () => {
        render();
        gsap.fromTo(
          group,
          { opacity: 0, y: 8 },
          {
            opacity: 1,
            y: 0,
            duration: 0.35,
            ease: 'power2.out',
            onComplete: () => {
              animating = false;
            },
          }
        );
      },
    });
  }

  render();
  prevBtn?.addEventListener('click', () => goTo(index - 1));
  nextBtn?.addEventListener('click', () => goTo(index + 1));
}

function initDevicePreview() {
  if (prefersReducedMotion) return;

  const cards = gsap.utils.toArray<HTMLElement>('[data-work-card]');
  cards.forEach((card) => {
    const shot = card.querySelector<HTMLElement>('[data-device-shot]');
    const screen = card.querySelector<HTMLElement>('.device-screen');
    if (!shot || !screen) return;

    let tween: gsap.core.Tween | null = null;

    card.addEventListener('mouseenter', () => {
      const max = shot.scrollHeight - screen.clientHeight;
      if (max <= 0) return;
      tween?.kill();
      tween = gsap.to(shot, { y: -max, duration: 5, ease: 'none' });
    });

    card.addEventListener('mouseleave', () => {
      tween?.kill();
      tween = gsap.to(shot, { y: 0, duration: 0.6, ease: 'power2.out' });
    });
  });
}

// Same hover-scroll-pan as initDevicePreview above, scoped to the Hero's
// single real screenshot rather than the Work grid's repeated cards.
function initHeroShotPreview() {
  if (prefersReducedMotion) return;

  const mockup = document.querySelector<HTMLElement>('[data-hero-mockup]');
  const screen = mockup?.querySelector<HTMLElement>('.hero-screen');
  const shot = mockup?.querySelector<HTMLElement>('[data-hero-shot]');
  if (!mockup || !screen || !shot) return;

  let tween: gsap.core.Tween | null = null;

  mockup.addEventListener('mouseenter', () => {
    const max = shot.offsetHeight - screen.clientHeight;
    if (max <= 0) return;
    tween?.kill();
    tween = gsap.to(shot, { y: -max, duration: 8, ease: 'none' });
  });

  mockup.addEventListener('mouseleave', () => {
    tween?.kill();
    tween = gsap.to(shot, { y: 0, duration: 0.6, ease: 'power2.out' });
  });
}

function initCapabilitiesLine() {
  const wrap = document.querySelector<HTMLElement>('[data-cap-timeline]');
  const svg = document.querySelector<SVGSVGElement>('[data-cap-line]');
  const track = document.querySelector<SVGPathElement>('[data-cap-line-track]');
  const progress = document.querySelector<SVGPathElement>('[data-cap-line-progress]');
  const rocket = document.querySelector<HTMLElement>('[data-cap-rocket]');
  const cards = gsap.utils.toArray<HTMLElement>('[data-cap-card]');
  if (!wrap || !svg || !track || !progress || cards.length === 0) return;

  let length = 0;

  // Faces the rocket along the path's direction of travel at `len`, by
  // sampling a point just before and after it and turning that into an
  // angle. Path tangents use standard atan2 convention (0deg = pointing
  // +x/right); the rocket artwork's resting orientation points up (-90deg
  // in that convention), so travel-angle + 90 lines the nose up with it.
  function positionRocket(len: number) {
    if (!rocket) return;
    const clampedLen = Math.max(0, Math.min(len, length));
    const point = progress!.getPointAtLength(clampedLen);
    const back = progress!.getPointAtLength(Math.max(0, clampedLen - 1));
    const fwd = progress!.getPointAtLength(Math.min(length, clampedLen + 1));
    const angle = (Math.atan2(fwd.y - back.y, fwd.x - back.x) * 180) / Math.PI;

    rocket!.style.transform =
      `translate(${point.x - rocket!.offsetWidth / 2}px, ${point.y - rocket!.offsetHeight / 2}px) ` +
      `rotate(${angle + 90}deg)`;
  }

  function buildPath() {
    const wrapRect = wrap!.getBoundingClientRect();
    const points = cards
      .map((card) => card.querySelector<HTMLElement>('[data-cap-node]'))
      .filter((node): node is HTMLElement => Boolean(node))
      .map((node) => {
        const rect = node.getBoundingClientRect();
        return {
          x: rect.left + rect.width / 2 - wrapRect.left,
          y: rect.top + rect.height / 2 - wrapRect.top,
        };
      });

    svg!.setAttribute('width', String(wrapRect.width));
    svg!.setAttribute('height', String(wrapRect.height));
    svg!.setAttribute('viewBox', `0 0 ${wrapRect.width} ${wrapRect.height}`);

    if (points.length < 2) return;

    let d = `M ${points[0].x} ${points[0].y}`;
    for (let i = 1; i < points.length; i++) {
      const prev = points[i - 1];
      const curr = points[i];
      const midY = (prev.y + curr.y) / 2;
      d += ` C ${prev.x} ${midY}, ${curr.x} ${midY}, ${curr.x} ${curr.y}`;
    }

    track!.setAttribute('d', d);
    progress!.setAttribute('d', d);

    length = progress!.getTotalLength();
    progress!.style.strokeDasharray = String(length);
    progress!.style.strokeDashoffset = prefersReducedMotion ? '0' : String(length);
    // Reduced motion shows the line fully drawn (dashoffset 0), so the
    // rocket parks at the end to match; otherwise it starts at the tip.
    positionRocket(prefersReducedMotion ? length : 0);
  }

  buildPath();

  const trigger = prefersReducedMotion
    ? null
    : ScrollTrigger.create({
        trigger: wrap,
        start: 'top 70%',
        end: 'bottom 60%',
        scrub: 0.4,
        onUpdate: (self) => {
          if (!length) return;
          const revealLength = length * self.progress;
          progress!.style.strokeDashoffset = String(length - revealLength);
          positionRocket(revealLength);
        },
      });

  let resizeTimeout: number | undefined;
  window.addEventListener('resize', () => {
    window.clearTimeout(resizeTimeout);
    resizeTimeout = window.setTimeout(() => {
      buildPath();
      trigger?.refresh();
    }, 200);
  });

  // Self-hosted fonts can finish loading a tick after first layout,
  // reflowing card heights — rebuild once metrics have settled.
  document.fonts?.ready.then(() => {
    buildPath();
    trigger?.refresh();
  });
}

function initHeroNetwork() {
  const hero = document.querySelector<HTMLElement>('.hero');
  const canvas = document.querySelector<HTMLCanvasElement>('[data-hero-network]');
  if (!hero || !canvas) return;

  const ctx = canvas.getContext('2d');
  if (!ctx) return;

  const LINK_DIST = 120;
  const CURSOR_DIST = 160;
  const canInteract = window.matchMedia('(pointer: fine)').matches;
  const dpr = Math.min(window.devicePixelRatio || 1, 2);

  let width = 0;
  let height = 0;
  let dots: Array<{ x: number; y: number; vx: number; vy: number; r: number }> = [];
  let pointer: { x: number; y: number } | null = null;
  let rafId: number | null = null;

  function resize() {
    const rect = hero!.getBoundingClientRect();
    width = rect.width;
    height = rect.height;
    canvas!.width = width * dpr;
    canvas!.height = height * dpr;
    canvas!.style.width = `${width}px`;
    canvas!.style.height = `${height}px`;
    ctx!.setTransform(dpr, 0, 0, dpr, 0, 0);

    const count = Math.min(110, Math.max(40, Math.round((width * height) / 9000)));
    dots = Array.from({ length: count }, () => ({
      x: Math.random() * width,
      y: Math.random() * height,
      vx: (Math.random() - 0.5) * 0.25,
      vy: (Math.random() - 0.5) * 0.25,
      r: Math.random() * 1.4 + 1,
    }));
  }

  function drawFrame(animate: boolean) {
    ctx!.clearRect(0, 0, width, height);

    if (animate) {
      for (const dot of dots) {
        dot.x += dot.vx;
        dot.y += dot.vy;
        if (dot.x < -10) dot.x = width + 10;
        if (dot.x > width + 10) dot.x = -10;
        if (dot.y < -10) dot.y = height + 10;
        if (dot.y > height + 10) dot.y = -10;
      }
    }

    const isLight = document.documentElement.getAttribute('data-theme') !== 'dark';
    const NODE_RGB = isLight ? '0, 0, 0' : '255, 255, 255';
    const dotAlphaBase = isLight ? 0.78 : 0.5;
    const lineAlphaBase = isLight ? 0.4 : 0.22;
    const cursorLineAlphaBase = isLight ? 0.55 : 0.35;
    const glowBlur = isLight ? 9 : 5;

    for (let i = 0; i < dots.length; i++) {
      const a = dots[i];

      ctx!.shadowBlur = 0;
      for (let j = i + 1; j < dots.length; j++) {
        const b = dots[j];
        const dist = Math.hypot(a.x - b.x, a.y - b.y);
        if (dist < LINK_DIST) {
          ctx!.strokeStyle = `rgba(${NODE_RGB}, ${lineAlphaBase * (1 - dist / LINK_DIST)})`;
          ctx!.lineWidth = 1;
          ctx!.beginPath();
          ctx!.moveTo(a.x, a.y);
          ctx!.lineTo(b.x, b.y);
          ctx!.stroke();
        }
      }

      let alpha = dotAlphaBase;
      let radius = a.r;

      if (pointer) {
        const dist = Math.hypot(a.x - pointer.x, a.y - pointer.y);
        if (dist < CURSOR_DIST) {
          const strength = 1 - dist / CURSOR_DIST;
          alpha = dotAlphaBase + strength * (0.95 - dotAlphaBase);
          radius = a.r + strength * 1.8;
          ctx!.shadowBlur = 0;
          ctx!.strokeStyle = `rgba(${NODE_RGB}, ${cursorLineAlphaBase * strength})`;
          ctx!.lineWidth = 1;
          ctx!.beginPath();
          ctx!.moveTo(a.x, a.y);
          ctx!.lineTo(pointer.x, pointer.y);
          ctx!.stroke();
        }
      }

      // Glow only on the dot fill — applying it to the connector lines too
      // would blur them into a muddy haze instead of crisp threads.
      ctx!.shadowColor = `rgba(${NODE_RGB}, ${Math.min(alpha + 0.15, 1)})`;
      ctx!.shadowBlur = glowBlur;
      ctx!.fillStyle = `rgba(${NODE_RGB}, ${alpha})`;
      ctx!.beginPath();
      ctx!.arc(a.x, a.y, radius, 0, Math.PI * 2);
      ctx!.fill();
      ctx!.shadowBlur = 0;
    }

    if (animate) rafId = requestAnimationFrame(() => drawFrame(true));
  }

  function start() {
    if (rafId !== null || prefersReducedMotion) return;
    rafId = requestAnimationFrame(() => drawFrame(true));
  }

  function stop() {
    if (rafId === null) return;
    cancelAnimationFrame(rafId);
    rafId = null;
  }

  resize();
  if (prefersReducedMotion) {
    drawFrame(false);
  } else {
    start();
  }

  let resizeTimeout: number | undefined;
  window.addEventListener('resize', () => {
    window.clearTimeout(resizeTimeout);
    resizeTimeout = window.setTimeout(() => {
      resize();
      if (prefersReducedMotion) drawFrame(false);
    }, 200);
  });

  if (canInteract && !prefersReducedMotion) {
    hero.addEventListener('pointermove', (event) => {
      const rect = hero!.getBoundingClientRect();
      pointer = { x: event.clientX - rect.left, y: event.clientY - rect.top };
    });
    hero.addEventListener('pointerleave', () => {
      pointer = null;
    });
  }

  if (!prefersReducedMotion) {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => (entry.isIntersecting ? start() : stop()));
      },
      { threshold: 0.05 }
    );
    observer.observe(hero);
  }
}

function initHeroReveal() {
  const introItems = gsap.utils.toArray<HTMLElement>('[data-hero-in]');
  if (introItems.length === 0) return;

  if (prefersReducedMotion) {
    gsap.set(introItems, { opacity: 1, y: 0 });
    return;
  }

  gsap.set(introItems, { opacity: 0, y: 24 });
  gsap.to(introItems, {
    opacity: 1,
    y: 0,
    duration: 0.7,
    stagger: 0.1,
    ease: 'power3.out',
    delay: 0.15,
  });
}

function initHeroBlobs() {
  const hero = document.querySelector<HTMLElement>('.hero');
  const blobs = gsap.utils.toArray<HTMLElement>('[data-blob]');
  if (!hero || blobs.length === 0) return;
  if (prefersReducedMotion || !window.matchMedia('(pointer: fine)').matches) return;

  const movers = blobs.map((blob) => ({
    el: blob,
    xTo: gsap.quickTo(blob, 'x', { duration: 0.7, ease: 'power3.out' }),
    yTo: gsap.quickTo(blob, 'y', { duration: 0.7, ease: 'power3.out' }),
  }));

  const repelRadius = 220;
  const maxPush = 26;

  hero.addEventListener('pointermove', (event) => {
    movers.forEach(({ el, xTo, yTo }) => {
      const rect = el.getBoundingClientRect();
      const cx = rect.left + rect.width / 2;
      const cy = rect.top + rect.height / 2;
      const dx = cx - event.clientX;
      const dy = cy - event.clientY;
      const dist = Math.hypot(dx, dy);

      if (dist < repelRadius && dist > 0.01) {
        const strength = (1 - dist / repelRadius) * maxPush;
        xTo((dx / dist) * strength);
        yTo((dy / dist) * strength);
      } else {
        xTo(0);
        yTo(0);
      }
    });
  });

  hero.addEventListener('pointerleave', () => {
    movers.forEach(({ xTo, yTo }) => {
      xTo(0);
      yTo(0);
    });
  });
}

function initHeroTilt() {
  const hero = document.querySelector<HTMLElement>('.hero');
  const mockup = document.querySelector<HTMLElement>('[data-hero-mockup]');
  if (!hero || !mockup) return;
  if (prefersReducedMotion || !window.matchMedia('(pointer: fine)').matches) return;

  const maxTilt = 14;
  // GSAP's CSSPlugin recognizes rotationX/rotationY, not the CSS function
  // names rotateX/rotateY — using the wrong ones silently no-ops.
  const rotateXTo = gsap.quickTo(mockup, 'rotationX', { duration: 0.6, ease: 'power3.out' });
  const rotateYTo = gsap.quickTo(mockup, 'rotationY', { duration: 0.6, ease: 'power3.out' });

  hero.addEventListener('pointermove', (event) => {
    const rect = mockup.getBoundingClientRect();
    const relX = (event.clientX - rect.left) / rect.width - 0.5;
    const relY = (event.clientY - rect.top) / rect.height - 0.5;
    rotateYTo(relX * maxTilt * 2);
    rotateXTo(relY * -maxTilt * 2);
  });

  hero.addEventListener('pointerleave', () => {
    rotateXTo(0);
    rotateYTo(0);
  });
}

function init() {
  initThemeToggle();
  const lenis = initSmoothScroll();
  // Create the Work section's pin first — it inflates the scrollable
  // distance for #work, and every trigger created afterward measures
  // itself against that already-inflated layout. Creating position-
  // dependent triggers (nav highlighting, reveals) before the pin exists
  // bakes in stale, pre-inflation offsets that refresh() doesn't correct.
  initWorkScroll();
  initWorkDrag();
  initNav(lenis);
  initMobileMenu();
  initReveals();
  initCapabilitiesLine();
  initServiceTabs();
  initTestimonialsSpotlight();
  initDevicePreview();
  initHeroReveal();
  initHeroNetwork();
  initHeroBlobs();
  initHeroTilt();
  initHeroShotPreview();

  // Safety net for late layout shifts (font/image load reflow).
  ScrollTrigger.refresh();

  // The line above runs before async images (e.g. the Hero's real
  // screenshot) have necessarily finished loading and settled their
  // layout — sections further down the page (Capabilities' height in
  // particular, after its zigzag redesign) can still shift afterward,
  // leaving every ScrollTrigger's cached position stale. 'load' fires
  // once everything (images, fonts, stylesheets) has actually finished,
  // so refresh once more there to catch it.
  window.addEventListener('load', () => ScrollTrigger.refresh());
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
