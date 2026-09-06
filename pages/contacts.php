<?php
/** Contacts + lead form + public review form. */

require_once __DIR__ . '/layout.php';

$s = site_settings();

layout_head('Контакты', 'Обсудите проект — замер, 3D, смета за 48 часов.');
layout_nav('contacts');
?>
<main id="main-content">
<section id="contacts" class="container section" style="padding-top:clamp(32px,6vw,64px)">
  <div class="section-header reveal">
    <span class="kicker">Связаться</span>
    <h1 class="h2">Контакты</h1>
    <div class="section-divider"></div>
    <p class="sub">Обсудите проект — замер, 3D, смета за 48 часов.</p>
  </div>
  <div class="contact-grid">
    <form class="form card" style="padding:var(--sp-6)" onsubmit="submitLead(event)">
      <div>
        <label class="form-label">Имя</label>
        <input class="input" name="name" placeholder="Ваше имя" required>
      </div>
      <div>
        <label class="form-label">Телефон</label>
        <input class="input" name="phone" placeholder="+7 (___) ___-__-__" required>
      </div>
      <div>
        <label class="form-label">Email</label>
        <input class="input" name="email" placeholder="email@example.com" type="email">
      </div>
      <div>
        <label class="form-label">Расскажите о проекте</label>
        <textarea class="input" name="message" placeholder="Опишите ваш проект, пожелания и сроки" rows="4"></textarea>
      </div>
      <button class="btn btn-accent" type="submit">Отправить заявку</button>
      <div id="leadMsg" class="muted" style="font-size:13px"></div>
    </form>
    <div class="card" style="padding:var(--sp-6)">
      <span class="kicker"><?= e($s['siteName'] ?? 'MEB Studio') ?></span>
      <div class="contact-info" style="margin-top:var(--sp-4)">
        <?php if (!empty($s['phone'])): ?>
        <div>Телефон: <a href="tel:<?= e(preg_replace('/[^+0-9]/', '', $s['phone'])) ?>"><?= e($s['phone']) ?></a></div>
        <?php endif; ?>
        <?php if (!empty($s['email'])): ?>
        <div>Email: <a href="mailto:<?= e($s['email']) ?>"><?= e($s['email']) ?></a></div>
        <?php endif; ?>
        <?php if (!empty($s['address'])): ?>
        <div>Адрес: <?= e($s['address']) ?></div>
        <?php endif; ?>
      </div>
      <?php if (!empty($s['hours'])) echo '<div style="margin-top:var(--sp-4)" class="muted">' . e($s['hours']) . '</div>'; ?>
      <div style="margin-top:var(--sp-4)" class="muted" style="font-size:13px">Отвечаем в течение часа в рабочее время.</div>
    </div>
  </div>
</section>

<section id="review-form" class="container section">
  <div class="section-header reveal">
    <span class="kicker">Отзыв</span>
    <h2 class="h2">Оставить отзыв</h2>
    <div class="section-divider"></div>
    <p class="sub">Расскажите о вашем опыте работы с нами. Отзыв появится после проверки.</p>
  </div>
  <form class="form card" style="padding:var(--sp-6);max-width:600px" onsubmit="submitReview(event)">
    <div>
      <label class="form-label">Ваше имя</label>
      <input class="input" name="name" placeholder="Имя" required maxlength="120">
    </div>
    <div>
      <label class="form-label">Оценка</label>
      <div id="ratingStars" style="font-size:28px;cursor:pointer;color:var(--brass);letter-spacing:4px">★★★★★</div>
      <input type="hidden" name="rating" value="5" id="ratingValue">
    </div>
    <div>
      <label class="form-label">Текст отзыва</label>
      <textarea class="input" name="text" placeholder="Расскажите о вашем опыте..." rows="5" required maxlength="2000"></textarea>
    </div>
    <button class="btn btn-accent" type="submit">Отправить отзыв</button>
    <div id="reviewMsg" class="muted" style="font-size:13px"></div>
  </form>
</section>
</main>

<script>
const stars = document.querySelectorAll('#ratingStars');
const ratingInput = document.getElementById('ratingValue');
if (stars.length) {
  stars[0].addEventListener('click', function(e) {
    const rect = this.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const rating = Math.ceil(x / rect.width * 5);
    ratingInput.value = Math.max(1, Math.min(5, rating));
    this.textContent = '★'.repeat(ratingInput.value) + '☆'.repeat(5 - ratingInput.value);
  });
}
function submitReview(e) {
  e.preventDefault();
  const fd = new FormData(e.target);
  const body = {}; fd.forEach((v, k) => { body[k] = v; });
  body.rating = parseInt(body.rating) || 5;
  const btn = e.target.querySelector('button[type="submit"]');
  if (btn) { btn.disabled = true; btn.textContent = 'Отправка...'; }
  fetch('/api/v1/reviews-public', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  }).then(r => r.json().then(j => {
    if (r.ok) {
      toast('Спасибо! Ваш отзыв отправлен на модерацию.', 'success');
      e.target.reset();
      document.getElementById('ratingStars').textContent = '★★★★★';
      document.getElementById('ratingValue').value = '5';
    } else {
      toast(j.error || 'Ошибка отправки', 'error');
    }
  })).catch(() => toast('Ошибка сети', 'error'))
  .finally(() => { if (btn) { btn.disabled = false; btn.textContent = 'Отправить отзыв'; } });
}
</script>

<?php layout_footer(); ?>
