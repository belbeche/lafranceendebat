// Bouton afficher/masquer sur les champs mot de passe (data-toggle-password)
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('input[data-toggle-password]').forEach(function (input) {
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'btn btn-link btn-sm px-0'; b.textContent = '\u{1F441} Afficher / masquer';
    b.addEventListener('click', function () { input.type = input.type === 'password' ? 'text' : 'password'; });
    input.insertAdjacentElement('afterend', b);
  });
});

// Remplace les gestionnaires en ligne (onclick/onsubmit), interdits par la politique de securite du contenu.
document.addEventListener('submit', function (e) {
  var f = e.target.closest('form[data-confirm]');
  if (f && !window.confirm(f.getAttribute('data-confirm'))) { e.preventDefault(); }
});
document.addEventListener('click', function (e) {
  var p = e.target.closest('[data-print]');
  if (p) { window.print(); return; }
  var c = e.target.closest('[data-confirm-submit]');
  if (c && window.confirm(c.getAttribute('data-confirm'))) {
    var form = document.getElementById(c.getAttribute('data-confirm-submit'));
    if (form) { form.submit(); }
  }
});

// Partage : API native si disponible (mobile), copie du lien sinon.
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-share]').forEach(function (b) {
    if (navigator.share) { b.hidden = false; }
    b.addEventListener('click', function () {
      navigator.share({ title: b.getAttribute('data-share-title'), url: b.getAttribute('data-share-url') }).catch(function () {});
    });
  });
});
document.addEventListener('click', function (e) {
  var c = e.target.closest('[data-copy]');
  if (!c) { return; }
  var text = c.getAttribute('data-copy');
  var done = function () { var old = c.innerHTML; c.textContent = 'Lien copié'; setTimeout(function () { c.innerHTML = old; }, 2000); };
  if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(text).then(done, function () { window.prompt('Copiez ce lien :', text); }); }
  else { window.prompt('Copiez ce lien :', text); }
});
document.addEventListener('click', function (e) {
  var c = e.target.closest('[data-copy-from]');
  if (!c) { return; }
  var src = document.querySelector(c.getAttribute('data-copy-from'));
  if (!src) { return; }
  src.focus(); src.select();
  var done = function () { var old = c.textContent; c.textContent = 'Texte copié'; setTimeout(function () { c.textContent = old; }, 2000); };
  if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(src.value).then(done, function () {}); }
  else { try { document.execCommand('copy'); done(); } catch (x) {} }
});
// Fil en direct : interroge le serveur toutes les 20 s tant que la page est visible.
(function () {
  var box = document.getElementById('fil-reponses');
  if (!box || !window.fetch) { return; }
  var state = document.getElementById('fil-etat');
  var url = box.getAttribute('data-live-url');
  var last = parseInt(box.getAttribute('data-last') || '0', 10);
  var busy = false;
  function poll() {
    if (busy || document.hidden) { return; }
    busy = true;
    fetch(url + '?apres=' + last, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { if (!r.ok) { throw new Error(); } return r.json(); })
      .then(function (d) {
        if (d.count > 0) {
          var empty = document.getElementById('fil-vide'); if (empty) { empty.remove(); }
          box.insertAdjacentHTML('beforeend', d.html);
          last = d.last;
          if (state) { state.textContent = d.count + ' nouvelle' + (d.count > 1 ? 's' : '') + ' réponse' + (d.count > 1 ? 's' : '') + ' ajoutée' + (d.count > 1 ? 's' : '') + '.'; }
        }
      })
      .catch(function () {})
      .then(function () { busy = false; });
  }
  setInterval(poll, 20000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) { poll(); } });
})();
