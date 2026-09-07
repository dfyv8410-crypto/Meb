const $ = s => document.querySelector(s);
const $$ = s => document.querySelectorAll(s);
const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const reduceMotion = () => matchMedia('(prefers-reduced-motion: reduce)').matches;

function _unwrap(j) { return j && j.data !== undefined ? j.data : j; }
async function jget(p) {
  const r = await fetch(p);
  const j = await r.json();
  return j.success !== false ? _unwrap(j) : null;
}

/* ---- Toast Notifications ---- */
let toastTimer = null;
function toast(msg, type = 'info') {
  let t = document.getElementById('mebToast');
  if (!t) {
    t = document.createElement('div');
    t.id = 'mebToast';
    t.className = 'toast';
    document.body.appendChild(t);
  }
  clearTimeout(toastTimer);
  t.className = 'toast ' + type;
  t.textContent = msg;
  requestAnimationFrame(() => t.classList.add('show'));
  toastTimer = setTimeout(() => t.classList.remove('show'), 4000);
}

function arrowSVG() {
  return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
}

/* ============================================
   NAVIGATION — floating header, showroom panel,
   fullscreen mobile menu
   ============================================ */
const siteNav = document.getElementById('siteNav');
const burger = document.getElementById('burger');
const mobileMenu = document.getElementById('mobileMenu');
const isHoverable = matchMedia('(hover:hover) and (pointer:fine)').matches;

/* ---- shrink-on-scroll: serene over hero → compact glass capsule ---- */
if (siteNav) {
  const checkScroll = () => {
    const sc = window.scrollY > 24;
    siteNav.classList.toggle('scrolled', sc);
    document.body.classList.toggle('nav-scrolled', sc);
  };
  window.addEventListener('scroll', checkScroll, { passive: true });
  checkScroll();
}

/* ---- «Категории» showroom dropdown ---- */
function setDrop(item, open) {
  if (!item) return;
  item.classList.toggle('drop-open', open);
  const btn = item.querySelector('.nav-trigger');
  if (btn) btn.setAttribute('aria-expanded', String(open));
}
function toggleDrop(btn) {
  if (!btn) return;
  setDrop(btn.closest('.nav-item.drop'), !btn.closest('.nav-item.drop').classList.contains('drop-open'));
}
function closeDrops() {
  document.querySelectorAll('.nav-item.drop.drop-open').forEach(it => setDrop(it, false));
}

document.querySelectorAll('.nav-item.drop').forEach(item => {
  const btn = item.querySelector('.nav-trigger');
  if (isHoverable) {
    item.addEventListener('mouseenter', () => setDrop(item, true));
    item.addEventListener('mouseleave', () => setDrop(item, false));
    if (btn) {
      btn.addEventListener('focus', () => setDrop(item, true));
      btn.addEventListener('keydown', e => {
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          setDrop(item, true);
          const first = item.querySelector('.mega a');
          if (first) first.focus();
        }
      });
    }
  } else if (btn) {
    btn.addEventListener('click', () => toggleDrop(btn));
  }
});

/* close whenever the pointer or keyboard focus leaves the dropdown */
document.addEventListener('click', e => {
  if (e.target.closest('.nav-item.drop')) return;
  closeDrops();
});
document.addEventListener('focusin', () => {
  const inDrop = document.activeElement && document.activeElement.closest('.nav-item.drop');
  if (!inDrop) closeDrops();
});

/* ---- fullscreen mobile menu (numbered premium panel) ---- */
function setMenu(open) {
  if (!mobileMenu) return;
  mobileMenu.classList.toggle('open', open);
  document.body.classList.toggle('menu-open', open);
  if (burger) {
    burger.setAttribute('aria-expanded', String(open));
    burger.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню');
  }
  if (open) {
    closeDrops(); // fresh panel: accordions collapsed
    const first = mobileMenu.querySelector('.nav-link, .nav-cta-link');
    if (first) first.focus();
  }
}
if (burger && mobileMenu) {
  burger.addEventListener('click', () => setMenu(!mobileMenu.classList.contains('open')));
  mobileMenu.addEventListener('click', e => {
    if (e.target.closest('.nav-trigger')) return;
    if (e.target.closest('a')) setMenu(false);
  });
  // light focus trap inside the open panel
  mobileMenu.addEventListener('keydown', e => {
    if (e.key !== 'Tab' || !mobileMenu.classList.contains('open')) return;
    const focusables = [...mobileMenu.querySelectorAll('a, button')];
    if (!focusables.length) return;
    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    const activeEl = document.activeElement;
    if (e.shiftKey && (activeEl === first || !mobileMenu.contains(activeEl))) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && (activeEl === last || !mobileMenu.contains(activeEl))) { e.preventDefault(); first.focus(); }
  });
}

/* Escape: close the panel first, then the dropdown */
document.addEventListener('keydown', e => {
  if (e.key !== 'Escape') return;
  if (mobileMenu && mobileMenu.classList.contains('open')) {
    setMenu(false);
    if (burger) burger.focus();
  } else {
    closeDrops();
  }
});

/* ============================================
   HERO SLIDER — cinematic crossfade cover
   ============================================ */
function initHeroSlider() {
  const slider = $('#heroSlider');
  const track = $('#heroTrack');
  const prevBtn = $('#heroPrev');
  const nextBtn = $('#heroNext');
  const pagination = $('#heroPagination');
  if (!slider || !track) return;

  const INTERVAL = 7000;
  const FALLBACK_SLIDES = [
    {
      image_url: '/assets/img/scene-hero-1.svg',
      title: 'Кухни, которые\nживут десятилетиями',
      description: 'Массив, камень, латунь. Ручная доводка и точность до миллиметра. Кухни, гардеробные и гостиные на заказ.',
      kicker: 'Премиальная мебель на заказ',
      button_text: 'Смотреть коллекцию',
      button_url: '/catalog',
      text_position: 'left'
    },
    {
      image_url: '/assets/img/scene-hero-2.svg',
      title: 'Интерьер как\nархитектура дома',
      description: 'Проектируем под ваше пространство. 3D-проект и смета в течение 48 часов.',
      kicker: 'Студия и производство',
      button_text: 'Обсудить проект',
      button_url: '/contacts',
      text_position: 'left'
    },
    {
      image_url: '/assets/img/scene-hero-3.svg',
      title: 'Материалы,\nкоторые стареют красиво',
      description: 'Массив, камень, латунь, стекло. Мы собираем мебель навсегда.',
      kicker: 'Честные материалы',
      button_text: 'Смотреть материалы',
      button_url: '/materials',
      text_position: 'right'
    }
  ];

  let slides = [];
  let current = 0;
  let timer = null;
  let paused = false;
  let touchStartX = 0, touchStartY = 0;
  let isSwiping = false;

  async function loadBanners() {
    let banners = [];
    try { banners = await jget('/api/v1/banners-public'); } catch (e) {}
    return Array.isArray(banners) ? banners : [];
  }

  function renderSlide(banner, index) {
    const img = banner.image_url || banner.image || banner.cover || '';
    const title = banner.title || banner.name || '';
    const desc = banner.description || banner.subtitle || '';
    const link = banner.button_url || banner.url || banner.link || '';
    const linkLabel = banner.button_text || banner.button || 'Подробнее';
    const kicker = banner.kicker || banner.badge || '';
    const pos = banner.text_position || banner.position || 'left';
    const posClass = 'pos-' + pos;

    const primaryHref = link || '/catalog';
    const primaryLabel = link ? linkLabel : 'Смотреть коллекцию';

    const actionsHtml = `<div class="hero-slide-actions">
      <a class="btn btn-light" href="${esc(primaryHref)}"><span>${esc(primaryLabel)}</span><span class="btn-ar">${arrowSVG()}</span></a>
      <a class="btn btn-ghost-light" href="/catalog"><span>В каталог</span></a>
    </div>`;

    const isFirst = index === 0;
    const loadingAttr = isFirst ? 'loading="eager"' : 'loading="lazy"';

    return `<div class="hero-slide${isFirst ? ' active' : ''}" data-index="${index}">
      <img class="hero-slide-img" src="${esc(img)}" alt="${esc(title)}" ${loadingAttr}>
      <div class="hero-slide-overlay ${pos === 'center' ? 'center' : (pos === 'right' ? 'right' : 'left')}"></div>
      <div class="hero-slide-content ${posClass}">
        ${kicker ? '<div class="hero-slide-kicker">' + esc(kicker) + '</div>' : '<div class="hero-slide-kicker">Премиальная мебель на заказ</div>'}
        <h2 class="hero-slide-title">${esc(title).replace(/\n/g, '<br>') || 'Мебель на заказ'}</h2>
        ${desc ? '<p class="hero-slide-desc">' + esc(desc) + '</p>' : ''}
        ${actionsHtml}
      </div>
    </div>`;
  }

  function renderFallbackSlide(slide, index) {
    const banner = Object.assign({}, slide);
    if (!banner.title) banner.title = 'Премиальная мебель на заказ';
    return renderSlide(banner, index);
  }

  function goTo(index) {
    if (slides.length === 0) return;
    if (index < 0) index = slides.length - 1;
    if (index >= slides.length) index = 0;
    const prev = current;
    current = index;

    $$('.hero-slide').forEach((s, i) => {
      const on = i === index;
      s.classList.toggle('active', on);
      if (on && !reduceMotion()) {
        // restart the slow cinematic zoom on the freshly shown image
        const imgs = s.querySelectorAll('.hero-slide-img');
        imgs.forEach(im => { im.style.transition = 'none'; im.style.transform = 'scale(1.12)'; });
        requestAnimationFrame(() => { imgs.forEach(im => { im.style.transition = ''; im.style.transform = ''; }); });
      }
    });
    const dots = $$('.hero-dot');
    dots.forEach((d, i) => d.classList.toggle('active', i === current));
    const heroIndexEl = document.getElementById('heroIndex');
    if (heroIndexEl) heroIndexEl.textContent = String(current + 1).padStart(2, '0');
    if (prev !== current) { slider.dispatchEvent(new CustomEvent('slidechange', { detail: { from: prev, to: current } })); }
  }

  function next() { goTo(current + 1); }
  function prev() { goTo(current - 1); }

  function startAutoplay() {
    stopAutoplay();
    if (!paused && slides.length > 1) {
      timer = setInterval(() => { if (!paused) next(); }, INTERVAL);
    }
  }
  function stopAutoplay() { if (timer) { clearInterval(timer); timer = null; } }

  function initPagination() {
    if (!pagination) return;
    pagination.innerHTML = slides.map((_, i) =>
      `<button class="hero-dot${i === 0 ? ' active' : ''}" data-index="${i}" aria-label="Слайд ${i + 1}"></button>`
    ).join('');
    pagination.addEventListener('click', e => {
      const dot = e.target.closest('.hero-dot');
      if (!dot) return;
      const idx = parseInt(dot.dataset.index, 10);
      if (!isNaN(idx)) { goTo(idx); startAutoplay(); }
    });
  }

  if (prevBtn) prevBtn.addEventListener('click', () => { prev(); startAutoplay(); });
  if (nextBtn) nextBtn.addEventListener('click', () => { next(); startAutoplay(); });

  slider.addEventListener('mouseenter', () => { paused = true; stopAutoplay(); });
  slider.addEventListener('mouseleave', () => { paused = false; startAutoplay(); });

  // Touch / swipe
  slider.addEventListener('touchstart', e => {
    touchStartX = e.touches[0].clientX;
    touchStartY = e.touches[0].clientY;
    isSwiping = false;
    stopAutoplay();
  }, { passive: true });

  slider.addEventListener('touchmove', e => {
    const dx = e.touches[0].clientX - touchStartX;
    const dy = e.touches[0].clientY - touchStartY;
    if (!isSwiping && Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 10) isSwiping = true;
    if (isSwiping) e.preventDefault();
  }, { passive: false });

  slider.addEventListener('touchend', e => {
    if (!isSwiping) { startAutoplay(); return; }
    const dx = e.changedTouches[0].clientX - touchStartX;
    if (Math.abs(dx) > 50) { if (dx < 0) next(); else prev(); }
    startAutoplay();
  }, { passive: true });

  // Keyboard
  slider.setAttribute('tabindex', '0');
  slider.addEventListener('keydown', e => {
    if (e.key === 'ArrowLeft') { prev(); startAutoplay(); }
    if (e.key === 'ArrowRight') { next(); startAutoplay(); }
  });

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) stopAutoplay();
    else if (!paused) startAutoplay();
  });

  // Hero parallax — whole cover drifts slightly slower than the page
  function heroParallax() {
    const hero = track.closest('.hero-slider');
    if (!hero || reduceMotion()) return;
    const r = hero.getBoundingClientRect();
    if (r.bottom < 0 || r.top > innerHeight) return;
    const drift = Math.min(Math.max(-r.top, 0), 170);
    track.style.transform = 'translateY(' + drift * 0.28 + 'px)';
  }

  async function build() {
    const banners = await loadBanners();
    const hasImagery = Array.isArray(banners) && banners.some(b => b.image_url || b.image || b.cover);
    if (!hasImagery) {
      slides = FALLBACK_SLIDES;
    } else {
      slideLoop: {
        slides = banners;
      }
    }
    track.innerHTML = slides.length === 0
      ? renderFallbackSlide(FALLBACK_SLIDES[0], 0)
      : slides.map((b, i) => renderSlide(b, i)).join('');

    if (slides.length <= 1) {
      if (prevBtn) prevBtn.style.display = 'none';
      if (nextBtn) nextBtn.style.display = 'none';
      if (pagination) pagination.style.display = 'none';
    } else {
      initPagination();
      startAutoplay();
    }
    track.closest('.hero-slider').classList.add('ready');
  }

  build();
  window.__heroParallax = heroParallax;
}

/* ============================================
   PARALLAX ON SCROLL — media depth shift
   ============================================ */
let parTicking = false;
function runParallax() {
  if (parTicking) return;
  parTicking = true;
  requestAnimationFrame(() => {
    parTicking = false;
    if (window.__heroParallax) window.__heroParallax();
    if (reduceMotion()) return;
    const scY = window.scrollY;
    $$('.parallax-media').forEach(p => {
      const r = p.getBoundingClientRect();
      if (r.bottom < -60 || r.top > innerHeight + 60) return;
      const rel = (r.top + r.height / 2) - innerHeight / 2;
      const speed = parseFloat(p.dataset.speed || '0.16');
      const img = p.querySelector('img');
      if (img) {
        img.style.transform = 'translateY(' + (rel * speed * -1) + 'px)';
      }
    });
  });
}
if (window.__heroParallax || $$('.parallax-media').length) {
  window.addEventListener('scroll', runParallax, { passive: true });
  runParallax();
}

/* ============================================
   SCROLL REVEAL — staggered, slow cinema
   ============================================ */
function observe() {
  const io = new IntersectionObserver(es => es.forEach(e => {
    if (!e.isIntersecting) return;
    // Stagger within the parent so grids breathe one card at a time.
    let d = 0;
    const parent = e.target.parentElement;
    if (parent && !e.target.classList.contains('reveal-solo')) {
      const idx = Array.prototype.indexOf.call(parent.children, e.target);
      d = Math.min(Math.max(idx, 0), 8) * 90;
    } else if (e.target.dataset.delay) {
      d = parseInt(e.target.dataset.delay, 10) || 0;
    }
    setTimeout(() => e.target.classList.add('in'), d);
    io.unobserve(e.target);
  }), { threshold: .14 });
  document.querySelectorAll('.reveal').forEach(el => io.observe(el));
  if (reduceMotion()) document.querySelectorAll('.reveal').forEach(el => el.classList.add('in'));
}

/* ============================================
   CARD TILT — gentle 3D depth on hover
   ============================================ */
function initTilt() {
  if (reduceMotion() || !matchMedia('(hover:hover) and (pointer:fine)').matches) return;
  $$('.tilt').forEach(el => {
    let raf = null;
    el.addEventListener('mousemove', e => {
      if (raf) cancelAnimationFrame(raf);
      raf = requestAnimationFrame(() => {
        const r = el.getBoundingClientRect();
        const x = (e.clientX - r.left) / r.width - .5;
        const y = (e.clientY - r.top) / r.height - .5;
        el.style.transform = `perspective(950px) rotateY(${x * 3.5}deg) rotateX(${-y * 3.5}deg) translateY(-7px)`;
      });
    });
    el.addEventListener('mouseleave', () => {
      if (raf) cancelAnimationFrame(raf);
      el.style.transition = 'transform .8s var(--ease-out)';
      el.style.transform = '';
      setTimeout(() => { el.style.transition = ''; }, 820);
    });
  });
}

/* ---- Lead Form ---- */
async function submitLead(e) {
  e.preventDefault();
  const fd = new FormData(e.target);
  const body = {}; fd.forEach((v, k) => { body[k] = v; });
  const btn = e.target.querySelector('button[type="submit"]');
  if (btn) { btn.disabled = true; btn.textContent = 'Отправка...'; }
  try {
    const r = await fetch('/api/v1/leads-public', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    const j = await r.json();
    if (r.ok) { toast('Спасибо! Свяжемся в ближайшее время.', 'success'); e.target.reset(); }
    else toast(j.error || 'Ошибка отправки', 'error');
  } catch (err) {
    toast('Ошибка сети. Попробуйте позже.', 'error');
  }
  if (btn) { btn.disabled = false; btn.textContent = 'Отправить'; }
}
window.submitLead = submitLead;

observe();
initHeroSlider();
initTilt();