const $ = s => document.querySelector(s);
const $$ = s => document.querySelectorAll(s);
const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

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
  requestAnimationFrame(() => {
    t.classList.add('show');
  });
  toastTimer = setTimeout(() => t.classList.remove('show'), 4000);
}

/* ---- Card HTML ---- */
function cardHTML(x, kind, href, imgOverride) {
  const img = imgOverride || (x.images && x.images[0]) || x.image || x.cover || '/assets/img/placeholder.svg';
  const inner = `<div class="card-img-wrap"><img loading="lazy" src="${esc(img)}" alt="${esc(x.title)}"></div><div class="card-body"><div class="eyebrow">${esc(kind || '')}</div><div class="card-title">${esc(x.title)}</div><div class="card-desc">${esc((x.description || x.desc || '').slice(0, 120))}</div></div>`;
  return href ? `<a href="${esc(href)}" class="card reveal img-reveal">${inner}</a>` : `<article class="card reveal img-reveal">${inner}</article>`;
}

/* ---- Scroll Nav Shadow ---- */
const siteNav = document.getElementById('siteNav');
if (siteNav) {
  const checkScroll = () => {
    siteNav.classList.toggle('scrolled', window.scrollY > 10);
  };
  window.addEventListener('scroll', checkScroll, { passive: true });
  checkScroll();
}

/* ---- Mobile Menu (full-height overlay) ---- */
const burger = document.getElementById('burger');
const navLinksM = document.getElementById('navLinks');
function setMenu(open) {
  if (!navLinksM) return;
  navLinksM.classList.toggle('open', open);
  document.body.classList.toggle('menu-open', open);
  if (burger) burger.setAttribute('aria-expanded', String(open));
}
if (burger && navLinksM) {
  burger.addEventListener('click', () => {
    setMenu(!navLinksM.classList.contains('open'));
  });
  navLinksM.addEventListener('click', e => {
    if (e.target.closest('a')) setMenu(false);
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && navLinksM.classList.contains('open')) setMenu(false);
  });
}

/* ============================================
   HERO SLIDER
   ============================================ */
function initHeroSlider() {
  const slider = $('#heroSlider');
  const track = $('#heroTrack');
  const prevBtn = $('#heroPrev');
  const nextBtn = $('#heroNext');
  const pagination = $('#heroPagination');
  if (!slider || !track) return;

  const INTERVAL = 6000;
  let slides = [];
  let current = 0;
  let timer = null;
  let paused = false;
  let touchStartX = 0;
  let touchStartY = 0;
  let isSwiping = false;

  async function loadBanners() {
    let banners = [];
    try {
      banners = await jget('/api/v1/banners-public');
    } catch (e) {}
    return Array.isArray(banners) ? banners : [];
  }

  function renderSlide(banner, index) {
    const img = banner.image_url || banner.image || banner.cover || '/assets/img/placeholder.svg';
    const mobileImg = banner.mobile_image_url || '';
    const title = banner.title || banner.name || '';
    const desc = banner.description || banner.subtitle || '';
    const link = banner.button_url || banner.url || banner.link || '';
    const linkLabel = banner.button_text || banner.button || 'Открыть';
    const kicker = banner.kicker || banner.badge || '';
    const pos = banner.text_position || banner.position || 'left';
    const posClass = 'pos-' + pos;
    const overlayClass = (pos === 'center') ? 'center' : (pos === 'right' ? 'right' : 'left');

    let actionsHtml = '';
    if (link) {
      actionsHtml = `<div class="hero-slide-actions">
        <a class="link-arrow" href="${esc(link)}">${esc(linkLabel)}</a>
      </div>`;
    }

    const isFirst = index === 0;
    const loadingAttr = isFirst ? 'loading="eager"' : 'loading="lazy"';

    return `<div class="hero-slide${isFirst ? ' active' : ''}" data-index="${index}">
      <img class="hero-slide-img" src="${esc(img)}" alt="${esc(title)}" ${loadingAttr}>
      ${mobileImg ? `<img class="hero-slide-img-mobile" src="${esc(mobileImg)}" alt="${esc(title)}" ${loadingAttr}>` : ''}
      <div class="hero-slide-overlay ${overlayClass}"></div>
      <div class="hero-slide-content ${posClass}">
        ${kicker ? '<div class="hero-slide-kicker">' + esc(kicker) + '</div>' : ''}
        <h2 class="hero-slide-title">${esc(title).replace(/\n/g, '<br>')}</h2>
        ${desc ? '<div class="hero-slide-desc">' + esc(desc) + '</div>' : ''}
        ${actionsHtml}
      </div>
    </div>`;
  }

  function renderFallback() {
    const siteName = 'ГОДНАЯ МЕБЕЛЬ';
    const tagline = 'Кухни, гардеробные и интерьеры из массива, камня и латуни. Ручная доводка и точность до миллиметра.';
    slider.outerHTML = `<section class="hero-fallback">
      <div class="hero-fallback-inner container">
        <div class="hero-fallback-kicker">Мебель созданная вручную</div>
        <h1 class="hero-fallback-title">ПРОСТРАНСТВО,<br>МАТЕРИАЛ,<br>ХАРАКТЕР.</h1>
        <p class="hero-fallback-desc">${esc(tagline)}</p>
        <div class="hero-fallback-links">
          <a class="link-arrow" href="/catalog">Коллекция</a>
          <a class="link-arrow" href="/projects">Проекты</a>
        </div>
      </div>
      <div class="hero-fallback-meta">
        <span><em>01</em> / МЕБЕЛЬ С ХАРАКТЕРОМ</span>
        <span>Листайте вниз ↓</span>
      </div>
    </section>`;
  }

  function goTo(index) {
    if (index < 0) index = slides.length - 1;
    if (index >= slides.length) index = 0;

    track.classList.add('no-transition');
    track.style.transform = 'translateX(-' + (current * 100) + '%)';
    requestAnimationFrame(() => {
      track.classList.remove('no-transition');
      current = index;
      track.style.transform = 'translateX(-' + (current * 100) + '%)';

      const allSlides = $$('.hero-slide');
      allSlides.forEach((s, i) => {
        s.classList.toggle('active', i === current);
      });

      const dots = $$('.hero-dot');
      dots.forEach((d, i) => {
        d.classList.toggle('active', i === current);
      });
      const heroIndexEl = document.getElementById('heroIndex');
      if (heroIndexEl) heroIndexEl.textContent = String(current + 1).padStart(2, '0');
    });
  }

  function next() { goTo(current + 1); }
  function prev() { goTo(current - 1); }

  function startAutoplay() {
    stopAutoplay();
    if (!paused && slides.length > 1) {
      timer = setInterval(() => {
        if (!paused) next();
      }, INTERVAL);
    }
  }

  function stopAutoplay() {
    if (timer) {
      clearInterval(timer);
      timer = null;
    }
  }

  function initPagination() {
    if (!pagination) return;
    pagination.innerHTML = slides.map((_, i) =>
      `<button class="hero-dot${i === 0 ? ' active' : ''}" data-index="${i}" aria-label="Слайд ${i + 1}"></button>`
    ).join('');

    pagination.addEventListener('click', e => {
      const dot = e.target.closest('.hero-dot');
      if (!dot) return;
      const idx = parseInt(dot.dataset.index, 10);
      if (!isNaN(idx)) {
        goTo(idx);
        startAutoplay();
      }
    });
  }

  // Arrow buttons
  if (prevBtn) {
    prevBtn.addEventListener('click', () => { prev(); startAutoplay(); });
  }
  if (nextBtn) {
    nextBtn.addEventListener('click', () => { next(); startAutoplay(); });
  }

  // Pause on hover
  slider.addEventListener('mouseenter', () => {
    paused = true;
    stopAutoplay();
  });
  slider.addEventListener('mouseleave', () => {
    paused = false;
    startAutoplay();
  });

  // Touch / swipe support
  slider.addEventListener('touchstart', e => {
    touchStartX = e.touches[0].clientX;
    touchStartY = e.touches[0].clientY;
    isSwiping = false;
    stopAutoplay();
  }, { passive: true });

  slider.addEventListener('touchmove', e => {
    const dx = e.touches[0].clientX - touchStartX;
    const dy = e.touches[0].clientY - touchStartY;
    if (!isSwiping && Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 10) {
      isSwiping = true;
    }
    if (isSwiping) {
      e.preventDefault();
    }
  }, { passive: false });

  slider.addEventListener('touchend', e => {
    if (!isSwiping) {
      startAutoplay();
      return;
    }
    const dx = e.changedTouches[0].clientX - touchStartX;
    if (Math.abs(dx) > 50) {
      if (dx < 0) next();
      else prev();
    }
    startAutoplay();
  }, { passive: true });

  // Keyboard navigation
  slider.setAttribute('tabindex', '0');
  slider.addEventListener('keydown', e => {
    if (e.key === 'ArrowLeft') { prev(); startAutoplay(); }
    if (e.key === 'ArrowRight') { next(); startAutoplay(); }
  });

  // Pause when page not visible
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      stopAutoplay();
    } else if (!paused) {
      startAutoplay();
    }
  });

  // Build
  async function build() {
    const banners = await loadBanners();
    if (banners.length === 0) {
      renderFallback();
      return;
    }

    slides = banners;
    track.innerHTML = banners.map((b, i) => renderSlide(b, i)).join('');

    if (slides.length <= 1) {
      if (prevBtn) prevBtn.style.display = 'none';
      if (nextBtn) nextBtn.style.display = 'none';
      if (pagination) pagination.style.display = 'none';
    } else {
      initPagination();
      startAutoplay();
    }
  }

  build();
}

/* ============================================
   SCROLL REVEAL
   ============================================ */
function observe() {
  const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) e.target.classList.add('in'); }), { threshold: .12 });
  document.querySelectorAll('.reveal').forEach(el => io.observe(el));
  if (matchMedia('(prefers-reduced-motion: reduce)').matches) document.querySelectorAll('.reveal').forEach(el => el.classList.add('in'));
}

/* ---- Hero 3D Tilt ---- */
let ticking = false;
const hero = $('#hero3d');
if (hero) window.addEventListener('mousemove', e => {
  if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  if (ticking) return;
  ticking = true;
  requestAnimationFrame(() => {
    const r = hero.getBoundingClientRect();
    const x = (e.clientX - r.left) / r.width - .5;
    const y = (e.clientY - r.top) / r.height - .5;
    hero.style.transform = `perspective(900px) rotateY(${x * 4}deg) rotateX(${-y * 4}deg)`;
    ticking = false;
  });
});

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
    if (r.ok) {
      toast('Спасибо! Свяжемся в ближайшее время.', 'success');
      e.target.reset();
    } else {
      toast(j.error || 'Ошибка отправки', 'error');
    }
  } catch (err) {
    toast('Ошибка сети. Попробуйте позже.', 'error');
  }
  if (btn) { btn.disabled = false; btn.textContent = 'Отправить'; }
}
window.submitLead = submitLead;

observe();
initHeroSlider();
