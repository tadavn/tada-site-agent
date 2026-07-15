/* TADA Site Agent — admin JS: quét điểm SEO tuần tự (tránh timeout). */
(function () {
  'use strict';
  if (typeof TSA === 'undefined') return;
  var btn = document.getElementById('tsa-scan-all');
  if (!btn) return;
  var prog = document.getElementById('tsa-scan-progress');

  function post(action, data) {
    data = data || {};
    data.action = action;
    data.nonce = TSA.nonce;
    var body = Object.keys(data).map(function (k) {
      return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]);
    }).join('&');
    return fetch(TSA.ajaxurl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body
    }).then(function (r) { return r.json(); });
  }

  btn.addEventListener('click', function () {
    btn.disabled = true;
    prog.textContent = '…';
    post('tsa_list_targets').then(function (res) {
      if (!res || !res.success) { prog.textContent = 'Error'; btn.disabled = false; return; }
      var list = res.data, i = 0, n = list.length;
      if (!n) { prog.textContent = '0'; btn.disabled = false; return; }
      (function next() {
        if (i >= n) {
          prog.textContent = TSA.i18n.scan_done + ' (' + n + ')';
          setTimeout(function () { location.reload(); }, 700);
          return;
        }
        var item = list[i];
        prog.textContent = TSA.i18n.scanning + ' ' + (i + 1) + '/' + n + ' — ' + (item.title || '');
        post('tsa_score_post', { post_id: item.id })
          .then(function () { i++; next(); })
          .catch(function () { i++; next(); });
      })();
    });
  });
})();
