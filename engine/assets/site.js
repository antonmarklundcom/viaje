/* Engine front-end: mobile nav, WhatsApp composer, form UX. No dependencies. */
(function () {
  'use strict';

  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('nav-principal');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.setAttribute('aria-label', toggle.getAttribute(open ? 'data-close' : 'data-open') || toggle.getAttribute('aria-label'));
    });
    nav.addEventListener('click', function (ev) {
      var sub = ev.target.closest && ev.target.closest('.nav__sub-toggle');
      if (sub) {
        var li = sub.parentNode, o = li.classList.toggle('is-open');
        sub.setAttribute('aria-expanded', o ? 'true' : 'false');
        return;
      }
      if (ev.target.tagName === 'A') { nav.classList.remove('is-open'); toggle.setAttribute('aria-expanded', 'false'); }
    });
  }

  /* Keep the WhatsApp button in sync with what the visitor is typing. */
  var wa = document.getElementById('lf-wa');
  var form = document.querySelector('.lead__form');
  if (wa && form) {
    var base = wa.getAttribute('href').split('?')[0];
    var fields = ['lf-name', 'lf-topic', 'lf-message'].map(function (id) { return document.getElementById(id); });
    var sync = function () {
      var name = (document.getElementById('lf-name') || {}).value || '';
      var topicEl = document.getElementById('lf-topic') || {};
      var topic = topicEl.type === 'hidden' ? '' : (topicEl.value || '');
      var msg = (document.getElementById('lf-message') || {}).value || '';
      var parts = [];
      if (name) { parts.push('Hola, soy ' + name + '.'); }
      if (topic) { parts.push(topic + '.'); }
      if (msg) { parts.push(msg); }
      /* The page being read travels with the message (title + canonical URL). */
      var ctx = wa.getAttribute('data-context') || '';
      if (ctx && parts.length) { parts.push('(' + ctx + ')'); }
      wa.setAttribute('href', parts.length ? base + '?text=' + encodeURIComponent(parts.join(' ')) : wa.getAttribute('href'));
    };
    fields.forEach(function (el) { if (el) { el.addEventListener('input', sync); el.addEventListener('change', sync); } });
  }

  /* Client-side required-field hints; the server validates for real. */
  if (form) {
    form.addEventListener('submit', function (ev) {
      var missing = null;
      ['lf-name', 'lf-phone', 'lf-message'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el && !el.value.trim() && !missing) { missing = el; }
      });
      if (missing) { ev.preventDefault(); missing.focus(); missing.setAttribute('aria-invalid', 'true'); }
    });
  }
})();
