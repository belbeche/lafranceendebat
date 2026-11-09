(function () {
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/service-worker.js').catch(function () {});
    });
  }
  var deferred = null;
  var buttons = document.querySelectorAll('[data-pwa-install]');
  var banner = document.querySelector('[data-pwa-banner]');
  var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
  var ua = navigator.userAgent;
  var isIos = /iphone|ipad|ipod/i.test(ua);
  var isMobile = /android|iphone|ipad|ipod|mobile/i.test(ua) || window.innerWidth < 768;
  function ls(k, v) { try { if (v === undefined) { return localStorage.getItem(k); } localStorage.setItem(k, v); } catch (e) { return null; } }
  function show(v) { buttons.forEach(function (b) { b.hidden = !v; }); }
  show(false);
  if (standalone) { return; }
  function dismissed() { var t = parseInt(ls('lfed_pwa_dismiss') || '0', 10); return t && (Date.now() - t) < 14 * 86400000; }
  function install() {
    if (deferred) { deferred.prompt(); deferred.userChoice.finally(function () { deferred = null; show(false); if (banner) { banner.hidden = true; } }); }
  }
  function openBanner() {
    if (!banner || dismissed() || !isMobile) { return; }
    var msg = banner.querySelector('[data-pwa-msg]');
    var btn = banner.querySelector('[data-pwa-install-banner]');
    var how = banner.querySelector('[data-pwa-how]');
    if (deferred) { btn.hidden = false; how.hidden = true; }
    else if (isIos) { msg.textContent = 'Touchez Partager, puis « Sur l\'écran d\'accueil ».'; btn.hidden = true; how.hidden = false; how.textContent = 'Voir les étapes'; }
    else { btn.hidden = true; how.hidden = false; }
    banner.hidden = false;
  }
  window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); deferred = e; show(true); setTimeout(openBanner, 3000); });
  window.addEventListener('appinstalled', function () { show(false); deferred = null; if (banner) { banner.hidden = true; } });
  if (isIos) { show(true); setTimeout(openBanner, 5000); }
  else { setTimeout(function () { if (!deferred) { openBanner(); } }, 8000); }
  buttons.forEach(function (b) {
    b.addEventListener('click', function () {
      if (deferred) { install(); } else if (isIos) { location.href = '/application'; }
    });
  });
  if (banner) {
    var ib = banner.querySelector('[data-pwa-install-banner]'); if (ib) { ib.addEventListener('click', install); }
    var db = banner.querySelector('[data-pwa-dismiss]'); if (db) { db.addEventListener('click', function () { ls('lfed_pwa_dismiss', String(Date.now())); banner.hidden = true; }); }
  }
})();
