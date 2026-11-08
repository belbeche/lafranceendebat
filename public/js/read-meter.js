(function () {
  var s = document.currentScript; if (!s) return;
  var url = s.getAttribute('data-url'), kind = s.getAttribute('data-kind'), id = s.getAttribute('data-id');
  if (!url || !kind || !id) return;
  var active = 0, last = Date.now(), maxScroll = 0, sent = 0, idleAfter = 30000, lastInput = Date.now();
  function tick() {
    var now = Date.now();
    if (document.visibilityState === 'visible' && now - lastInput < idleAfter) active += Math.min(now - last, 2000);
    last = now;
    var h = document.documentElement, total = h.scrollHeight - h.clientHeight;
    var pct = total <= 0 ? 100 : Math.round(100 * (window.scrollY || h.scrollTop) / total);
    if (pct > maxScroll) maxScroll = pct;
  }
  ['scroll', 'mousemove', 'keydown', 'touchstart', 'click'].forEach(function (e) { window.addEventListener(e, function () { lastInput = Date.now(); }, { passive: true }); });
  setInterval(tick, 1000);
  function send() {
    tick();
    var secs = Math.round(active / 1000);
    if (secs < 1 || secs === sent) return;
    sent = secs;
    var d = new FormData(); d.append('kind', kind); d.append('id', id); d.append('seconds', secs); d.append('scroll', maxScroll);
    if (navigator.sendBeacon) navigator.sendBeacon(url, d); else fetch(url, { method: 'POST', body: d, keepalive: true, credentials: 'same-origin' });
  }
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') send(); });
  window.addEventListener('pagehide', send);
  setInterval(send, 20000);
})();
