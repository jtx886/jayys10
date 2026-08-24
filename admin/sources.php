<?php
/**
 * Jay影视 - 播放源管理
 * 新增 / 编辑 / 删除播放源，设置默认播放源
 */
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/source_api.php';

$ADMIN_PAGE = 'sources';
$PAGE_TITLE = '播放源管理';
$msg = ''; $msgType = 'ok';

/* ---------- 新增 ---------- */
if (is_post() && post('action') === 'add' && csrf_check()) {
    $name = post('name');
    $apiUrl = post('api_url');
    if ($name === '' || $apiUrl === '' || !preg_match('~^https?://~i', $apiUrl)) {
        $msg = '请填写正确的播放源名称与 API 地址（http/https）'; $msgType = 'err';
    } else {
        $setDef = post('is_default') === '1';
        if ($setDef) db_exec("UPDATE sources SET is_default=0");
        db_exec("INSERT INTO sources (name,api_url,is_default) VALUES (?,?,?)", array($name, $apiUrl, $setDef ? 1 : 0));
        $msg = '播放源已添加';
    }
}

/* ---------- 编辑 ---------- */
if (is_post() && post('action') === 'edit' && csrf_check()) {
    $id = intval(post('id'));
    $name = post('name');
    $apiUrl = post('api_url');
    if ($name === '' || $apiUrl === '' || !preg_match('~^https?://~i', $apiUrl)) {
        $msg = '名称与 API 地址不能为空'; $msgType = 'err';
    } elseif (!db_one("SELECT id FROM sources WHERE id=?", array($id))) {
        $msg = '播放源不存在'; $msgType = 'err';
    } else {
        db_exec("UPDATE sources SET name=?, api_url=? WHERE id=?", array($name, $apiUrl, $id));
        $msg = '播放源已更新';
    }
}

/* ---------- 删除 ---------- */
if (is_post() && post('action') === 'del' && csrf_check()) {
    $id = intval(post('id'));
    $src = db_one("SELECT * FROM sources WHERE id=?", array($id));
    if ($src) {
        db_exec("DELETE FROM sources WHERE id=?", array($id));
        /* 删除的是默认源：自动将最早的一个源设为默认 */
        if ($src['is_default']) {
            $first = db_one("SELECT id FROM sources ORDER BY id ASC LIMIT 1");
            if ($first) db_exec("UPDATE sources SET is_default=1 WHERE id=?", array($first['id']));
        }
        $msg = '播放源已删除';
    }
}

/* ---------- 设默认 ---------- */
if (is_post() && post('action') === 'setdef' && csrf_check()) {
    $id = intval(post('id'));
    if (db_one("SELECT id FROM sources WHERE id=?", array($id))) {
        db_exec("UPDATE sources SET is_default=0");
        db_exec("UPDATE sources SET is_default=1 WHERE id=?", array($id));
        $msg = '已设为默认播放源';
    }
}

$rows = db_all("SELECT * FROM sources ORDER BY is_default DESC, id ASC");
$editRow = null;
if (get('edit') !== '') {
    $editRow = db_one("SELECT * FROM sources WHERE id=?", array(intval(get('edit'))));
}
require __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?><div class="alert <?php echo $msgType; ?>"><?php echo h($msg); ?></div><?php endif; ?>

<div class="panel">
  <div class="panel-head"><i class="ico i-plus"></i><?php echo $editRow ? '编辑播放源' : '新增播放源'; ?></div>
  <div class="panel-body adm-form">
    <form method="post">
      <input type="hidden" name="action" value="<?php echo $editRow ? 'edit' : 'add'; ?>">
      <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
      <?php if ($editRow): ?><input type="hidden" name="id" value="<?php echo intval($editRow['id']); ?>"><?php endif; ?>
      <div class="grid-2">
        <div class="field">
          <label>播放源名称</label>
          <input type="text" name="name" value="<?php echo h($editRow ? $editRow['name'] : ''); ?>" placeholder="如：YY资源" required>
        </div>
        <div class="field">
          <label>API 接口地址（苹果CMS JSON 格式，如 https://api.yyzy-tv.vip/inc/apijson.php）</label>
          <input type="url" name="api_url" value="<?php echo h($editRow ? $editRow['api_url'] : ''); ?>" placeholder="https://..." required>
        </div>
      </div>
      <?php if (!$editRow): ?>
      <label class="chk"><input type="checkbox" name="is_default" value="1"><span class="chk-box"><i class="ico i-check"></i></span>同时设为默认播放源</label>
      <?php endif; ?>
      <div style="display:flex;gap:12px;margin-top:14px">
        <button class="btn primary" type="submit"><i class="ico i-check"></i><?php echo $editRow ? '保存修改' : '添加播放源'; ?></button>
        <?php if ($editRow): ?><a class="btn ghost" href="sources.php">取消编辑</a><?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><i class="ico i-film"></i>播放源列表（<?php echo count($rows); ?>）· 默认源用于全站播放</div>
  <div class="panel-body no-pad">
    <table class="adm-table">
      <thead><tr><th>名称</th><th>API 地址</th><th>状态</th><th>添加时间</th><th>操作</th></tr></thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="5" class="empty-tip">暂无播放源，请先添加</td></tr>
      <?php else: foreach ($rows as $s): ?>
        <tr>
          <td style="font-weight:700"><?php echo h($s['name']); ?></td>
          <td style="max-width:380px;overflow:hidden;text-overflow:ellipsis"><?php echo h($s['api_url']); ?></td>
          <td><?php echo $s['is_default'] ? '<span class="st def">默认播放源</span>' : '<span class="st mut">备用</span>'; ?></td>
          <td><?php echo fmt_date($s['created_at']); ?></td>
          <td>
            <div class="td-actions">
              <?php if (!$s['is_default']): ?>
              <form method="post" onsubmit="return confirm('将该播放源设为默认？')">
                <input type="hidden" name="action" value="setdef"><input type="hidden" name="id" value="<?php echo intval($s['id']); ?>">
                <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
                <button class="mini-btn pri" type="submit"><i class="ico i-check"></i>设为默认</button>
              </form>
              <?php endif; ?>
              <a class="mini-btn" href="sources.php?edit=<?php echo intval($s['id']); ?>"><i class="ico i-edit"></i>编辑</a>
              <form method="post" onsubmit="return confirm('确定删除该播放源吗？')">
                <input type="hidden" name="action" value="del"><input type="hidden" name="id" value="<?php echo intval($s['id']); ?>">
                <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
                <button class="mini-btn dan" type="submit"><i class="ico i-trash"></i>删除</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
