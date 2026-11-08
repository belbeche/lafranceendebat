(function () {
  var form = document.getElementById('prop-wizard');
  if (!form) return;
  var steps = [].slice.call(form.querySelectorAll('.wiz-step'));
  var inds = [].slice.call(form.querySelectorAll('[data-step-ind]'));
  var prev = document.getElementById('wiz-prev'), next = document.getElementById('wiz-next'), send = document.getElementById('wiz-send');
  var cur = 0;

  function show(i) {
    cur = i;
    steps.forEach(function (s, k) { s.hidden = k !== i; });
    inds.forEach(function (n, k) { n.classList.toggle('is-on', k === i); n.classList.toggle('is-done', k < i); });
    prev.hidden = i === 0; next.hidden = i === steps.length - 1; send.hidden = i !== steps.length - 1;
    var f = steps[i].querySelector('input:not([type=radio]):not([type=hidden]), textarea, select');
    if (f && i > 0) f.focus();
    window.scrollTo({ top: form.getBoundingClientRect().top + window.scrollY - 80, behavior: 'smooth' });
  }

  function problem(step) {
    var i = steps.indexOf(step);
    if (i === 0) return step.querySelector('input[type=radio]:checked') ? '' : 'Choisissez un thème pour continuer.';
    if (i === 1) {
      var t = step.querySelector('input[type=text]'), d = step.querySelector('textarea');
      if (t.value.trim().length < 10) return 'Le titre doit faire au moins 10 caractères.';
      if (d.value.trim().length < 50) return 'Développez un peu la mesure : 50 caractères minimum (' + d.value.trim().length + ' pour l\'instant).';
    }
    return '';
  }

  function warn(step, msg) {
    var box = step.querySelector('.wiz-msg');
    if (!box) { box = document.createElement('div'); box.className = 'alert alert-danger py-2 mt-2 wiz-msg'; box.setAttribute('role', 'alert'); step.appendChild(box); }
    box.textContent = msg; box.hidden = !msg;
  }

  next.addEventListener('click', function () {
    var m = problem(steps[cur]);
    warn(steps[cur], m);
    if (!m) show(cur + 1);
  });
  prev.addEventListener('click', function () { show(cur - 1); });
  form.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.tagName === 'INPUT' && e.target.type !== 'submit' && cur < steps.length - 1) { e.preventDefault(); next.click(); }
  });
  form.addEventListener('submit', function (e) {
    for (var k = 0; k < 2; k++) { var m = problem(steps[k]); if (m) { e.preventDefault(); show(k); warn(steps[k], m); return; } }
  });

  // Compteur de la description
  var d = form.querySelector('textarea[data-min]'), c = document.getElementById('desc-count');
  function count() { var n = d.value.trim().length; c.textContent = n < 50 ? n + ' / 50 caractères minimum' : n + ' caractères, c\'est bien.'; }
  if (d && c) { d.addEventListener('input', count); count(); }

  // Propositions proches pendant la saisie du titre
  var title = form.querySelector('input[data-min="10"]'), box = document.getElementById('prop-similar');
  var url = document.currentScript ? document.currentScript.getAttribute('data-similar') : null;
  var timer;
  if (title && box && url) {
    title.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(function () {
        fetch(url + '?q=' + encodeURIComponent(title.value), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
          .then(function (r) { return r.ok ? r.json() : []; })
          .then(function (list) {
            box.textContent = '';
            if (!list.length) { box.hidden = true; return; }
            box.appendChild(document.createTextNode('Ça ressemble à : '));
            list.forEach(function (p, i) {
              var a = document.createElement('a'); a.href = p.url; a.target = '_blank'; a.rel = 'noopener'; a.textContent = p.title;
              box.appendChild(a); box.appendChild(document.createTextNode(' (' + p.support + ' soutien' + (p.support > 1 ? 's' : '') + ')' + (i < list.length - 1 ? ', ' : '. Soutenir existe aussi.')));
            });
            box.hidden = false;
          }).catch(function () {});
      }, 400);
    });
  }

  // Si le serveur renvoie des erreurs, on ouvre l'etape concernee
  var firstErr = form.querySelector('.invalid-feedback, .form-error-message, .is-invalid');
  if (firstErr) { var st = firstErr.closest('.wiz-step'); if (st) show(steps.indexOf(st)); else show(0); }
  else show(0);
})();
