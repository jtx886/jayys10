/* Jay影视 - 前端交互脚本 */
(function () {
  'use strict';

  var JAY = window.JAY || {};

  /* ---------- 工具 ---------- */
  function $(s, p) { return (p || document).querySelector(s); }
  function $$(s, p) { return Array.prototype.slice.call((p || document).querySelectorAll(s)); }

  /* ---------- Toast ---------- */
  var toastTimer = null;
  window.jayToast = function (msg) {
    var t = $('#toast');
    if (!t) return;
    $('#toastMsg').textContent = msg;
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { t.classList.remove('show'); }, 2600);
  };

  /* ---------- 图片渐入 ---------- */
  function lazyImg() {
    $$('img[data-src]').forEach(function (img) {
      if (img.__bind) return;
      img.__bind = true;
      img.loading = 'lazy';
      img.onload = function () { img.classList.add('loaded'); };
      img.src = img.getAttribute('data-src');
      if (img.complete) img.classList.add('loaded');
    });
  }

  /* ---------- 移动导航收起 ---------- */
  $$('.main-nav a').forEach(function (a) {
    a.addEventListener('click', function () {
      var t = $('#nav-toggle');
      if (t) t.checked = false;
    });
  });

  /* ---------- 弹窗 ---------- */
  window.openModal = function (sel) {
    var m = $(sel);
    if (m) { m.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
  };
  window.closeModal = function (sel) {
    var m = typeof sel === 'string' ? $(sel) : sel;
    if (m) { m.style.display = 'none'; document.body.style.overflow = ''; }
  };
  $$('.modal-mask').forEach(function (m) {
    m.addEventListener('click', function (e) { if (e.target === m) closeModal(m); });
  });

  /* ---------- 公告弹窗（仅首页渲染） ---------- */
  window.closeNotice = function (read) {
    var noMore = $('#noticeNoMore');
    var id = $('#noticeModal') ? $('#noticeModal').getAttribute('data-nid') : '';
    if (read && noMore && noMore.checked) {
      document.cookie = 'notice_seen=' + id + ';path=/;max-age=' + (3600 * 24 * 365);
    } else if (read) {
      /* 未勾选不再提示：会话级关闭 */
      document.cookie = 'notice_seen=' + id + ';path=/';
    }
    closeModal('#noticeModal');
  };
  var nm = $('#noticeModal');
  if (nm) {
    /* data-nid 由 PHP 输出到元素上 */
    document.body.style.overflow = 'hidden';
  }

  /* ---------- 需要登录弹窗（点击播放未登录） ---------- */
  window.requireLogin = function () {
    location.href = 'login.php?msg=play';
  };

  /* ---------- 收藏（事件委托：按钮无论何时渲染均可点击） ---------- */
  document.addEventListener('click', function (e) {
    var t = e.target;
    var btn = t && t.closest ? t.closest('.btn-fav') : null;
    if (!btn) return;
    if (!JAY.isLogin) return requireLogin();
    var d = btn.dataset;
    fetch('api/favorite.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF': JAY.csrf },
      body: 'tmdb_id=' + d.id + '&type=' + d.type + '&title=' + encodeURIComponent(d.title || '') + '&poster=' + encodeURIComponent(d.poster || '')
    }).then(function (r) { return r.json(); }).then(function (res) {
      if (res.code === 401) return requireLogin();
      if (res.code !== 0) return jayToast(res.msg || '操作失败');
      var added = res.data === 'added';
      btn.classList.toggle('on', added);
      var label = btn.querySelector('span');
      if (label) label.textContent = added ? '已收藏' : '收藏';
      jayToast(added ? '已加入收藏' : '已取消收藏');
    }).catch(function () { jayToast('网络异常，请重试'); });
  });

  /* ---------- 反馈点赞（事件委托） ---------- */
  document.addEventListener('click', function (e) {
    var t = e.target;
    var btn = t && t.closest ? t.closest('.btn-like') : null;
    if (!btn) return;
    if (!JAY.isLogin) return requireLogin();
    var id = btn.dataset.id;
    fetch('api/like.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF': JAY.csrf },
      body: 'feedback_id=' + id
    }).then(function (r) { return r.json(); }).then(function (res) {
      if (res.code === 401) return requireLogin();
      if (res.code !== 0) return jayToast(res.msg || '操作失败');
      btn.classList.toggle('liked', !!res.data.liked);
      var cnt = btn.querySelector('span');
      if (cnt) cnt.textContent = res.data.count;
    }).catch(function () { jayToast('网络异常，请重试'); });
  });

  /* ---------- 反馈回复折叠（>3条自动折叠） ---------- */
  $$('.replies-box').forEach(function (box) {
    var items = $$('.reply-item', box);
    var toggle = $('.replies-toggle', box);
    if (items.length > 3 && toggle) {
      items.forEach(function (it, i) { if (i >= 3) it.classList.add('hidden-rep'); });
      toggle.addEventListener('click', function () {
        var open = toggle.classList.toggle('open');
        items.forEach(function (it, i) {
          if (i >= 3) it.classList.toggle('hidden-rep', !open);
        });
        toggle.querySelector('span').textContent = open ? '收起回复' : ('展开全部' + items.length + '条回复');
      });
    }
  });

  /* ---------- 验证码发送 ---------- */
  var codeBtn = $('#sendCode');
  if (codeBtn) {
    codeBtn.addEventListener('click', function () {
      var email = $('#regEmail') ? $('#regEmail').value.trim() : '';
      if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) return jayToast('请先输入正确的邮箱地址');
      codeBtn.disabled = true;
      codeBtn.textContent = '发送中...';
      fetch('api/send_code.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF': JAY.csrf },
        body: 'email=' + encodeURIComponent(email)
      }).then(function (r) { return r.json(); }).then(function (res) {
        if (res.code !== 0) {
          jayToast(res.msg || '发送失败');
          codeBtn.disabled = false;
          codeBtn.textContent = '获取验证码';
          return;
        }
        jayToast('验证码已发送，请查收邮箱（含垃圾箱）');
        var left = 60;
        codeBtn.textContent = left + 's 后重发';
        var timer = setInterval(function () {
          left--;
          if (left <= 0) { clearInterval(timer); codeBtn.disabled = false; codeBtn.textContent = '重新发送'; }
          else codeBtn.textContent = left + 's 后重发';
        }, 1000);
      }).catch(function () {
        codeBtn.disabled = false;
        codeBtn.textContent = '获取验证码';
        jayToast('网络异常，请重试');
      });
    });
  }

  /* ---------- 头像预览 ---------- */
  var avatarInput = $('#avatarInput');
  if (avatarInput) {
    avatarInput.addEventListener('change', function () {
      if (this.files && this.files[0]) {
        var f = this.files[0];
        if (f.size > 2 * 1024 * 1024) { jayToast('头像不能超过 2MB'); this.value = ''; return; }
        var reader = new FileReader();
        reader.onload = function (e) {
          var prev = $('#avatarPreview');
          if (prev) prev.src = e.target.result;
        };
        reader.readAsDataURL(f);
      }
    });
  }

  /* ---------- 播放页：观看历史上报（累计播放秒数） ---------- */
  var hist = $('#histData');
  if (hist && JAY.isLogin) {
    var data = {
      tmdb_id: hist.dataset.id, type: hist.dataset.type, title: hist.dataset.title,
      poster: hist.dataset.poster || '', season: hist.dataset.season || 1, episode: hist.dataset.ep || 1
    };
    var send = function (sec) {
      data.sec = sec;
      var body = Object.keys(data).map(function (k) { return k + '=' + encodeURIComponent(data[k]); }).join('&');
      fetch('api/history.php', {
        method: 'POST', keepalive: true,
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF': JAY.csrf },
        body: body
      }).catch(function () {});
    };
    send(0); /* 进入即记录 */
    setInterval(function () { send(15); }, 15000);
    window.addEventListener('beforeunload', function () {
      data.sec = 15;
      var body = Object.keys(data).map(function (k) { return k + '=' + encodeURIComponent(data[k]); }).join('&');
      if (navigator.sendBeacon) navigator.sendBeacon('api/history.php', new Blob([body], { type: 'application/x-www-form-urlencoded' }));
    });
  }

  /* ---------- 卡片整体可点击 ---------- */
  lazyImg();
});
