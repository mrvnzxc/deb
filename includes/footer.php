<?php if (!empty($nav)): ?>
</div><!-- /.container -->
<?php endif; ?>
<script>
// Ask for confirmation on forms that have data-confirm="message"
document.addEventListener('submit', function (ev) {
  var msg = ev.target.getAttribute('data-confirm');
  if (msg && !confirm(msg)) ev.preventDefault();
});

// Show / hide password: <button data-toggle-password="#inputId">
document.addEventListener('click', function (ev) {
  var btn = ev.target.closest('[data-toggle-password]');
  if (!btn) return;
  var input = document.querySelector(btn.getAttribute('data-toggle-password'));
  if (!input) return;
  var show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  btn.setAttribute('title', show ? 'Hide password' : 'Show password');
  var icon = btn.querySelector('.bi');
  if (icon) icon.className = 'bi ' + (show ? 'bi-eye-slash' : 'bi-eye');
});

// Mobile menu toggle
(function () {
  var toggle = document.querySelector('.nav-toggle');
  var links  = document.getElementById('nav-links');
  if (!toggle || !links) return;
  toggle.addEventListener('click', function () {
    var open = links.classList.toggle('open');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    toggle.querySelector('.bi').className = 'bi ' + (open ? 'bi-x-lg' : 'bi-list');
  });
})();

// Count-up numbers: <span data-count="1500" data-decimals="2" data-prefix="&#8369;">
(function () {
  var els = document.querySelectorAll('[data-count]');
  if (!els.length) return;
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function fmt(n, d) {
    return n.toLocaleString('en-PH', { minimumFractionDigits: d, maximumFractionDigits: d });
  }
  function run(el) {
    var target = parseFloat(el.getAttribute('data-count')) || 0;
    var dec    = parseInt(el.getAttribute('data-decimals') || '0', 10);
    var prefix = el.getAttribute('data-prefix') || '';
    var suffix = el.getAttribute('data-suffix') || '';
    if (reduce || target === 0) { el.textContent = prefix + fmt(target, dec) + suffix; return; }
    var dur = 1400, start = null;
    function step(ts) {
      if (start === null) start = ts;
      var p = Math.min((ts - start) / dur, 1);
      var eased = 1 - Math.pow(1 - p, 3); // ease-out cubic
      el.textContent = prefix + fmt(target * eased, dec) + suffix;
      if (p < 1) requestAnimationFrame(step);
    }
    el.textContent = prefix + fmt(0, dec) + suffix;
    requestAnimationFrame(step);
  }

  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { run(en.target); io.unobserve(en.target); }
      });
    }, { threshold: 0.4 });
    els.forEach(function (el) { io.observe(el); });
  } else {
    els.forEach(run);
  }
})();
</script>
</body>
</html>
