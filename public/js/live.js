/* Temps réel natif, sans serveur dédié : interrogation périodique légère. Onglet caché : pause (ou rythme lent si les alertes système sont activées). */
(function () {
  'use strict';
  var body = document.body;
  var liveUrl = body.getAttribute('data-live-url');
  function store(k, v) { try { if (v === undefined) { return sessionStorage.getItem(k); } sessionStorage.setItem(k, v); } catch (e) { return null; } }
  function toastBox() {
    var b = document.getElementById('live-toasts');
    if (!b) { b = document.createElement('div'); b.id = 'live-toasts'; b.className = 'live-toasts'; b.setAttribute('role', 'status'); b.setAttribute('aria-live', 'polite'); document.body.appendChild(b); }
    return b;
  }
  function toast(text, url) {
    var a = document.createElement('a');
    a.className = 'live-toast'; a.href = url; a.textContent = text;
    toastBox().appendChild(a);
    setTimeout(function () { if (a.parentNode) { a.parentNode.removeChild(a); } }, 9000);
  }
  function badges(kind, n) {
    document.querySelectorAll('[data-live-badge="' + kind + '"]').forEach(function (el) { el.textContent = n > 99 ? '99+' : String(n); el.hidden = !n; });
    if (kind === 'notifications') { try { if (navigator.setAppBadge) { n ? navigator.setAppBadge(n) : navigator.clearAppBadge(); } } catch (e) {} }
  }
  function ago(iso) {
    var s = Math.max(1, Math.round((Date.now() - new Date(iso).getTime()) / 1000));
    if (s < 60) { return 'à l\'instant'; }
    if (s < 3600) { return Math.round(s / 60) + ' min'; }
    if (s < 86400) { return Math.round(s / 3600) + ' h'; }
    if (s < 604800) { return Math.round(s / 86400) + ' j'; }
    return new Date(iso).toLocaleDateString('fr-FR');
  }
  function sysNotify(n) {
    if (!('Notification' in window) || Notification.permission !== 'granted' || !document.hidden) { return; }
    var opts = { body: 'La France en Débat', icon: '/pwa/icon-192.png', badge: '/pwa/icon-192.png', tag: 'lfed-' + n.id, data: { url: n.url } };
    if (navigator.serviceWorker && navigator.serviceWorker.ready) { navigator.serviceWorker.ready.then(function (r) { r.showNotification(n.text, opts); }).catch(function () {}); }
  }
  var tick = 0;
  function pollLive() {
    var granted = ('Notification' in window) && Notification.permission === 'granted';
    if (!liveUrl || (document.hidden && !granted)) { return; }
    if (document.hidden && (++tick % 4) !== 0) { return; }
    fetch(liveUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) {
      if (!d) { return; }
      badges('messages', d.messages); badges('notifications', d.notifications);
      var seen = parseInt(store('lfed_seen') || '0', 10), max = seen, fresh = false;
      (d.latest || []).slice().reverse().forEach(function (n) {
        if (n.id > seen) { if (store('lfed_init')) { fresh = true; toast(n.text, n.url); sysNotify(n); } max = Math.max(max, n.id); }
      });
      store('lfed_seen', String(max)); store('lfed_init', '1');
      if (fresh && panelOpen()) { loadPanel(); }
    }).catch(function () {});
  }
  /* Panneau de la cloche, façon réseau social */
  var dd = document.querySelector('[data-notif]');
  function panelOpen() { var m = dd && dd.querySelector('.dropdown-menu'); return !!(m && m.classList.contains('show')); }
  function csrfFetch(url, payload) {
    return fetch(url, { method: 'POST', credentials: 'same-origin', keepalive: true, headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': dd.getAttribute('data-csrf') }, body: JSON.stringify(payload || {}) });
  }
  function renderItem(n) {
    var li = document.createElement('li');
    var a = document.createElement('a'); a.href = n.url; a.className = 'notif-item' + (n.read ? '' : ' unread'); a.setAttribute('data-id', n.id);
    var ic = document.createElement('span'); ic.className = 'notif-ic'; var i = document.createElement('i'); i.className = 'bi ' + (/message/i.test(n.text) ? 'bi-chat-dots-fill' : (/publi|candidature|commande|paiement/i.test(n.text) ? 'bi-check-circle-fill' : 'bi-bell-fill')); i.setAttribute('aria-hidden', 'true'); ic.appendChild(i);
    var tx = document.createElement('span'); tx.className = 'notif-tx';
    var t = document.createElement('span'); t.className = 'notif-t'; t.textContent = n.text;
    var w = document.createElement('span'); w.className = 'notif-w'; w.textContent = ago(n.at);
    tx.appendChild(t); tx.appendChild(w);
    a.appendChild(ic); a.appendChild(tx);
    if (!n.read) { var dot = document.createElement('span'); dot.className = 'notif-dot'; dot.setAttribute('aria-label', 'Non lue'); a.appendChild(dot); }
    a.addEventListener('click', function () { if (!n.read) { csrfFetch(dd.getAttribute('data-read-url'), { ids: [n.id] }).catch(function () {}); } });
    li.appendChild(a); return li;
  }
  function loadPanel() {
    var list = dd.querySelector('[data-notif-list]');
    fetch(dd.getAttribute('data-list-url'), { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) {
      if (!d) { return; }
      list.textContent = '';
      if (!d.items.length) { var e = document.createElement('li'); e.className = 'notif-empty'; e.textContent = 'Rien pour le moment. Vos réponses, messages et décisions apparaîtront ici.'; list.appendChild(e); return; }
      d.items.forEach(function (n) { list.appendChild(renderItem(n)); });
    }).catch(function () {});
  }
  if (dd) {
    dd.addEventListener('show.bs.dropdown', loadPanel);
    var all = dd.querySelector('[data-notif-all]');
    if (all) { all.addEventListener('click', function () { csrfFetch(dd.getAttribute('data-read-url'), {}).then(function () { badges('notifications', 0); loadPanel(); }).catch(function () {}); }); }
    var push = dd.querySelector('[data-notif-push]');
    if (push && 'Notification' in window && Notification.permission === 'default') {
      push.hidden = false;
      push.addEventListener('click', function () { Notification.requestPermission().then(function (p) { push.hidden = true; if (p === 'granted') { toast('Alertes activées sur cet appareil.', '/notifications'); } }); });
    }
  }
  /* Fils de discussion en direct */
  function row(m) {
    var d = document.createElement('div'); d.className = 'carte p-3 mb-2 ' + (m.mine ? 'ms-5' : 'me-5'); d.setAttribute('data-id', m.id);
    var meta = document.createElement('p'); meta.className = 'meta mb-1'; meta.textContent = m.author + ' · ' + m.at;
    var p = document.createElement('p'); p.className = 'mb-0'; p.style.whiteSpace = 'pre-line'; p.textContent = m.body;
    d.appendChild(meta); d.appendChild(p); return d;
  }
  var thread = document.querySelector('[data-thread]');
  function pollThread() {
    if (document.hidden || !thread) { return; }
    var last = parseInt(thread.getAttribute('data-last') || '0', 10);
    fetch(thread.getAttribute('data-feed') + '?apres=' + last, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) {
      if (!d || !d.messages || !d.messages.length) { return; }
      var empty = thread.querySelector('[data-empty]'); if (empty) { empty.remove(); }
      d.messages.forEach(function (m) { if (!thread.querySelector('[data-id="' + m.id + '"]')) { thread.appendChild(row(m)); } });
      thread.setAttribute('data-last', String(d.last));
      var end = document.getElementById('bas'); if (end && end.scrollIntoView) { end.scrollIntoView({ block: 'nearest' }); }
    }).catch(function () {});
  }
  if (liveUrl) { pollLive(); setInterval(pollLive, 15000); }
  if (thread) { setInterval(pollThread, 5000); }
  document.addEventListener('visibilitychange', function () { if (!document.hidden) { pollLive(); pollThread(); } });
})();
