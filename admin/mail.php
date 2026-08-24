<?php
/**
 * Jay影视 - 邮件推送（163 SMTP 向用户邮箱发送自定义通知）
 */
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/smtp.php';

$ADMIN_PAGE = 'mail';
$PAGE_TITLE = '邮件推送';
$msg = ''; $msgType = 'ok';

$users = db_all("SELECT id,username,email,status FROM users ORDER BY id ASC");

if (is_post() && post('action') === 'send' && csrf_check()) {
    $uids = isset($_POST['uids']) && is_array($_POST['uids']) ? array_map('intval', $_POST['uids']) : array();
    $subject = post('subject');
    $content = post('content');
    if (empty($uids)) { $msg = '请至少选择一位收件用户'; $msgType = 'err'; }
    elseif ($subject === '' || $content === '') { $msg = '请填写邮件主题与内容'; $msgType = 'err'; }
    else {
        @set_time_limit(120);
        $okCnt = 0; $failList = array();
        $body = mail_template_notice($subject, $content);
        foreach ($users as $u) {
            if (!in_array(intval($u['id']), $uids, true)) continue;
            if (send_mail($u['email'], $u['username'], '【Jay影视】' . $subject, $body)) { $okCnt++; }
            else { $failList[] = $u['username']; }
        }
        $msg = '推送完成：成功发送 ' . $okCnt . ' 封';
        if (!empty($failList)) { $msg .= '，失败：' . h(implode('、', $failList)); $msgType = 'warn'; }
    }
}
require __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?><div class="alert <?php echo $msgType; ?>"><?php echo $msg; ?></div><?php endif; ?>

<div class="panel">
  <div class="panel-head"><i class="ico i-mail"></i>发送自定义邮件（经 163 SMTP 发出，发件人：jtxnb886@163.com）</div>
  <div class="panel-body adm-form">
    <form method="post" id="mailForm">
      <input type="hidden" name="action" value="send">
      <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;flex-wrap:wrap">
        <label class="chk"><input type="checkbox" id="selAll" checked><span class="chk-box"><i class="ico i-check"></i></span>全选用户（<?php echo count($users); ?>）</label>
        <span style="font-size:12.5px;color:var(--muted)">已选择 <b id="selCnt" style="color:var(--theme)">0</b> 位</span>
      </div>
      <div class="field" style="max-height:230px;overflow-y:auto;border:1px solid var(--line);border-radius:12px;padding:12px 14px">
        <?php foreach ($users as $u): ?>
        <label class="chk" style="display:flex;margin:7px 0">
          <input type="checkbox" class="mail-user" name="uids[]" value="<?php echo intval($u['id']); ?>">
          <span class="chk-box"><i class="ico i-check"></i></span>
          <span style="color:var(--txt)"><?php echo h($u['username']); ?></span>
          <span style="color:var(--muted);font-size:12px"><?php echo h($u['email']); ?></span>
          <?php if ($u['status'] == 1): ?><span class="st ban" style="margin-left:4px">封禁中</span><?php endif; ?>
        </label>
        <?php endforeach; ?>
      </div>
      <div class="field">
        <label>邮件主题</label>
        <input type="text" name="subject" placeholder="如：新片上线通知" required>
      </div>
      <div class="field">
        <label>邮件内容（支持换行排版，将以精致 HTML 模板发送）</label>
        <textarea name="content" style="min-height:170px" placeholder="输入邮件正文内容…" required></textarea>
      </div>
      <button class="btn primary" type="submit"><i class="ico i-send"></i>立即推送</button>
    </form>
  </div>
</div>

<script>
(function () {
  var selAll = document.getElementById('selAll');
  var boxes = document.querySelectorAll('.mail-user');
  var cnt = document.getElementById('selCnt');
  function refresh() {
    var n = 0;
    boxes.forEach(function (b) { if (b.checked) n++; });
    cnt.textContent = n;
  }
  if (selAll) {
    selAll.addEventListener('change', function () {
      boxes.forEach(function (b) { b.checked = selAll.checked; });
      refresh();
    });
  }
  boxes.forEach(function (b) { b.addEventListener('change', refresh); });
  /* 默认全选 */
  if (selAll) { boxes.forEach(function (b) { b.checked = true; }); refresh(); }
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
