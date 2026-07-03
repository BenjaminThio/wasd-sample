/* ============================================================
   WASD Game Store — shared client-side script (vanilla JS only)
   Event-driven interactions: nav, dropdown, keycaps, reveals,
   flash messages, cart quantity steppers, form validation.
   ============================================================ */

(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- nav: frosted background after scrolling ---------- */
  var nav = document.getElementById('nav');
  function onScroll() {
    if (nav) nav.classList.toggle('scrolled', window.scrollY > 30);
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* ---------- mobile burger menu ---------- */
  var burger = document.getElementById('nav-burger');
  var links = document.getElementById('nav-links');
  if (burger && links) {
    burger.addEventListener('click', function () {
      var open = links.classList.toggle('open');
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  /* ---------- account dropdown ---------- */
  var toggle = document.getElementById('dropdown-toggle');
  var menu = document.getElementById('dropdown-menu');
  if (toggle && menu) {
    toggle.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = menu.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
      if (!menu.contains(e.target) && e.target !== toggle) {
        menu.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') menu.classList.remove('open');
    });
  }

  /* ---------- flash message: auto-dismiss ---------- */
  var flash = document.getElementById('flash');
  if (flash) {
    setTimeout(function () {
      flash.classList.add('hide');
      setTimeout(function () { flash.remove(); }, 500);
    }, 3800);
  }

  /* ---------- hero keycaps: physical W/A/S/D keys light them up ---------- */
  var caps = {};
  var keycapEls = document.querySelectorAll('.keycap');
  for (var i = 0; i < keycapEls.length; i++) {
    caps[keycapEls[i].getAttribute('data-key')] = keycapEls[i];
  }
  if (keycapEls.length) {
    window.addEventListener('keydown', function (e) {
      var tag = document.activeElement.tagName;
      var k = e.key.toLowerCase();
      if (caps[k] && !e.repeat && tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT') {
        caps[k].classList.add('pressed');
      }
    });
    window.addEventListener('keyup', function (e) {
      var k = e.key.toLowerCase();
      if (caps[k]) caps[k].classList.remove('pressed');
    });
    keycapEls.forEach(function (c) {
      c.addEventListener('pointerdown', function () { c.classList.add('pressed'); });
      c.addEventListener('pointerup', function () { c.classList.remove('pressed'); });
      c.addEventListener('pointerleave', function () { c.classList.remove('pressed'); });
    });
  }

  /* ---------- scroll reveals ---------- */
  var revealEls = document.querySelectorAll('.reveal');
  if (revealEls.length && 'IntersectionObserver' in window && !reduceMotion) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) {
          en.target.classList.add('in');
          io.unobserve(en.target);
        }
      });
    }, { threshold: 0.12 });
    revealEls.forEach(function (el) { io.observe(el); });
  } else {
    revealEls.forEach(function (el) { el.classList.add('in'); });
  }

  /* ---------- cart quantity steppers (+ / −) ---------- */
  document.querySelectorAll('.qty-box').forEach(function (box) {
    var input = box.querySelector('input[type=number]');
    if (!input) return;
    box.querySelectorAll('button[data-step]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var step = parseInt(btn.getAttribute('data-step'), 10);
        var val = parseInt(input.value, 10) || 1;
        var min = parseInt(input.min, 10) || 1;
        var max = parseInt(input.max, 10) || 99;
        input.value = Math.min(max, Math.max(min, val + step));
      });
    });
  });

  /* ---------- client-side form validation (server still validates) ---------- */
  document.querySelectorAll('form[data-validate]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      var problems = [];
      form.querySelectorAll('[data-required]').forEach(function (field) {
        if (!field.value.trim()) {
          problems.push(field.getAttribute('data-label') || 'A required field') ;
        }
      });
      var email = form.querySelector('input[type=email]');
      if (email && email.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
        problems.push('Email address looks invalid');
      }
      var pw = form.querySelector('input[name=password]');
      var pw2 = form.querySelector('input[name=confirm_password]');
      if (pw && pw.value && pw.value.length < 6) problems.push('Password needs at least 6 characters');
      if (pw && pw2 && pw.value !== pw2.value) problems.push('Passwords do not match');

      var errBox = form.querySelector('.form-error');
      if (problems.length) {
        e.preventDefault();
        if (!errBox) {
          errBox = document.createElement('div');
          errBox.className = 'form-error';
          form.insertBefore(errBox, form.firstChild);
        }
        errBox.textContent = problems.join('. ') + '.';
        errBox.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'center' });
      } else if (errBox) {
        errBox.remove();
      }
    });
  });

  /* ---------- confirm dialogs for destructive actions ---------- */
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!window.confirm(form.getAttribute('data-confirm'))) e.preventDefault();
    });
  });
})();