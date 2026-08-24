<?php
/**
 * Jay影视 - 网站设置（自定义全站主题颜色 / 站点名称 / TMDB Key）
 */
require_once __DIR__ . '/includes/auth.php';

$ADMIN_PAGE = 'settings';
$PAGE_TITLE = '网站设置';
$msg = ''; $msgType = 'ok';

$presets = array('#e50914', '#ff5f54', '#7c3aed', '#2563eb', '#0891b2', '#059669', '#d97706', '#db2777');

if (is_post() && post('action') === 'save' && csrf_check()) {
    $siteName = post('site_name');
    $theme = strtolower(post('theme_color'));
    $tmdbKey = post('tmdb_key');
    if ($siteName === '') { $msg = '站点名称不能为空'; $msgType = 'err'; }
    elseif (!preg_match('/^#[0-9a-f]{6}$/', $theme)) { $msg = '主题颜色格式不正确（如 #e50914）'; $msgType = 'err'; }
    else {
        setting_save('site_name', $siteName);
        setting_save('theme_color', $theme);
        if ($tmdbKey !== '') setting_save('tmdb_key', $tmdbKey);
        /* 主题色变更后清理媒体缓存之外的设置缓存（settings 为静态缓存，进程级，无需处理） */
        $msg = '设置已保存，全站主题颜色已更新';
    }
}

$siteName = setting('site_name', 'Jay影视');
$theme = setting('theme_color', '#e50914');
$tmdbKey = setting('tmdb_key', '');
require __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?><div class="alert <?php echo $msgType; ?>"><?php echo h($msg); ?></div><?php endif; ?>

<div class="panel">
  <div class="panel-head"><i class="ico i-sliders"></i>全站设置</div>
  <div class="panel-body adm-form">
    <form method="post">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
      <div class="grid-2">
        <div class="field">
          <label>站点名称（显示于导航栏 / 页脚 / 邮件）</label>
          <input type="text" name="site_name" value="<?php echo h($siteName); ?>" required>
        </div>
        <div class="field">
          <label>全站主题颜色（实时作用于前台与后台所有页面）</label>
          <input type="color" name="theme_color" id="themePicker" value="<?php echo h($theme); ?>" style="height:46px;cursor:pointer;padding:4px">
          <div class="color-presets">
            <?php foreach ($presets as $p): ?>
            <button type="button" class="color-preset <?php echo $p === $theme ? 'on' : ''; ?>" style="background:<?php echo $p; ?>" onclick="pick('<?php echo $p; ?>')"></button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="field">
        <label>TMDB API Key（影视元数据来源，留空保持不变；默认 Key 失效时可在此更换）</label>
        <input type="text" name="tmdb_key" value="<?php echo h($tmdbKey); ?>" placeholder="TMDB v3 api_key">
      </div>
      <button class="btn primary" type="submit"><i class="ico i-check"></i>保存设置</button>
    </form>
  </div>
</div>

<script>
function pick(c){
  document.getElementById('themePicker').value = c;
  document.querySelectorAll('.color-preset').forEach(function(b){ b.classList.remove('on'); });
  event.target.classList.add('on');
}
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
