(function () {
  var f = document.getElementById('debat-form'); if (!f) return;
  var themeDossier = JSON.parse(f.getAttribute('data-theme-dossier') || '{}');
  var themeSources = JSON.parse(f.getAttribute('data-theme-sources') || '{}');
  var checkUrl = f.getAttribute('data-check');
  var $ = function (id) { return document.getElementById(id); };
  var theme = $('theme'), dossier = $('dossier'), facts = $('facts'), list = $('obl-list'), sum = $('obl-summary');
  var ICON = { ok: '✔', wip: '…', todo: '○' }, timer;

  function showSources() {
    var box = $('theme-sources'), l = $('theme-sources-list'), urls = themeSources[theme.value] || null;
    l.textContent = '';
    if (!theme.value || theme.value === '__autre') { urls = ['https://www.insee.fr', 'https://www.vie-publique.fr', 'https://www.legifrance.gouv.fr', 'https://www.data.gouv.fr', 'https://www.ccomptes.fr']; }
    if (!theme.value) { box.hidden = true; return; }
    (urls || []).forEach(function (u) {
      var b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-sm btn-outline-primary'; b.textContent = u.replace('https://', '').replace('www.', '');
      b.addEventListener('click', function () { facts.value = (facts.value.trim() ? facts.value.trim() + '\n' : '') + u; facts.dispatchEvent(new Event('input')); facts.focus(); });
      l.appendChild(b);
    });
    box.hidden = false;
  }

  function onTheme() {
    var slug = themeDossier[theme.value];
    if (slug && dossier && (!dossier.value || dossier.dataset.auto === '1')) { dossier.value = slug; dossier.dataset.auto = '1'; dossier.dispatchEvent(new Event('change')); }
    showSources(); refresh();
  }
  if (dossier) dossier.addEventListener('input', function () { dossier.dataset.auto = '0'; });

  function render(data) {
    list.textContent = '';
    data.items.forEach(function (it) {
      var li = document.createElement('li'); li.className = 'obl obl-' + it.status;
      var tag = document.createElement('span'); tag.className = 'obl-tag'; tag.textContent = ICON[it.status] + ' ' + it.statusLabel;
      var txt = document.createElement('span'); txt.textContent = ' ' + it.label + (it.required ? '' : ' (recommandé)');
      li.appendChild(tag); li.appendChild(txt);
      if (it.status !== 'ok') { var h = document.createElement('small'); h.className = 'd-block text-muted ms-4'; h.textContent = it.hint; li.appendChild(h); }
      list.appendChild(li);
    });
    sum.textContent = data.ready ? 'Tous les points obligatoires sont respectés : votre débat est prêt pour la relecture.' : 'Il reste des points obligatoires : ils doivent tous être « Respecté » pour que le débat soit relu.';
  }

  function refresh() {
    clearTimeout(timer);
    timer = setTimeout(function () {
      var q = new URLSearchParams({ title: $('title').value, theme: theme.value === '__autre' ? '' : theme.value, summary: $('summary').value, facts: facts.value, authority: $('authority').value });
      fetch(checkUrl + '?' + q.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; }).then(function (d) { if (d) render(d); }).catch(function () {});
    }, 350);
  }
  ['title', 'summary', 'facts', 'authority'].forEach(function (id) { $(id).addEventListener('input', refresh); });
  theme.addEventListener('change', onTheme);
  onTheme();
})();
