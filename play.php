<?php
/**
 * Jay影视 - 播放页
 * 播放链路：
 *   1. 调用默认播放源接口按片名搜索，解析出真实 m3u8 直链
 *   2. rawurlencode(m3u8) 后拼接到解析播放器外壳 https://svip.ffzyplay.com/?url=
 *   3. iframe 嵌入拼接完成的地址
 *   —— 严禁直接把源接口地址传入解析播放器
 * 未登录用户禁止播放：直接跳转登录页并弹窗提示
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/tmdb.php';
require_once __DIR__ . '/includes/source_api.php';

$type = get('type') === 'tv' ? 'tv' : 'movie';
$id = intval(get('id'));
$title = get('title');
$season = max(1, intval(get('season', 1)));
$ep = max(1, intval(get('ep', 1)));
$audio = get('audio', 'ori');
if (!in_array($audio, array('ori', 'cn'), true)) $audio = 'ori';
$srcId = intval(get('source', 0));

if ($id <= 0 || $title === '') redirect('index.php');

/* 播放源：详情页选择的源（无效则回退默认源） */
$curSource = $srcId > 0 ? source_by_id($srcId) : null;
if (!$curSource) $curSource = default_source();

/* 权限控制：未登录禁止播放 */
if (!is_login()) {
    $back = 'play.php?type=' . $type . '&id=' . $id . '&title=' . urlencode($title) . '&season=' . $season . '&ep=' . $ep . '&audio=' . $audio . '&source=' . ($curSource ? intval($curSource['id']) : 0);
    redirect('login.php?msg=play&redirect=' . urlencode($back));
}
$U = current_user();
if (user_banned($U)) {
    redirect('logout.php');
}

/* 从 TMDB 取元数据（封面/季集数），失败不阻断播放 */
$poster = '';
$epList = array();
$orgLang = 'en';
$detail = tmdb_detail($type, $id);
if ($detail) {
    $poster = !empty($detail['poster_path']) ? tmdb_img($detail['poster_path'], 'w342') : '';
    $orgLang = isset($detail['original_language']) ? $detail['original_language'] : 'en';
}
$isZh = in_array($orgLang, array('zh', 'cn'), true);
if ($type === 'tv') {
    $sd = tmdb_season($id, $season);
    if ($sd && !empty($sd['episodes'])) {
        foreach ($sd['episodes'] as $e) {
            $epList[] = array(
                'num' => intval($e['episode_number']),
                'name' => isset($e['name']) && $e['name'] !== '' ? $e['name'] : ('第' . intval($e['episode_number']) . '集'),
                'still' => !empty($e['still_path']) ? tmdb_img($e['still_path'], 'w300') : '',
            );
        }
    }
}

/* 生成解析播放地址（真实 m3u8 → urlencode → 拼接解析外壳） */
$player = build_player($title, $season, $ep, $audio, $curSource);
$epName = '';
foreach ($player['eps'] as $i => $e) { if ($i + 1 === $player['ep']) { $epName = $e['name']; break; } }

$base = 'play.php?type=' . $type . '&id=' . $id . '&title=' . urlencode($title) . '&season=' . $season . '&source=' . ($curSource ? intval($curSource['id']) : 0);
$PAGE_TITLE = $title . ($type === 'tv' ? ' 第' . $ep . '集' : '');
require __DIR__ . '/includes/header.php';
?>

<div class="page-head anim">
  <h1><?php echo h($title); ?><?php echo $type === 'tv' ? ' <span style="color:var(--theme)">S' . $season . 'E' . $player['ep'] . '</span>' : ''; ?></h1>
  <span class="count" style="display:flex;align-items:center;gap:6px"><i class="ico i-globe"></i>解析播放</span>
  <div class="grow"></div>
  <a class="btn ghost sm" href="<?php echo media_link($type, $id); ?>"><i class="ico i-info"></i>影视详情</a>
</div>

<?php if (!$player['ok']): ?>
<div class="player-shell">
  <div class="player-nores">
    <i class="ico i-info"></i>
    <p style="font-size:16px"><?php echo h($player['msg']); ?></p>
    <a class="btn primary" href="<?php echo media_link($type, $id); ?>">返回详情页</a>
  </div>
</div>
<?php else: ?>
<div class="player-shell anim">
  <iframe class="player-frame" src="<?php echo h($player['player_url']); ?>" allowfullscreen frameborder="0" referrerpolicy="no-referrer"></iframe>
  <div class="player-bar">
    <div style="min-width:0;flex:1">
      <h1><?php echo h($title); ?><?php echo $epName !== '' ? ' · ' . h($epName) : ''; ?></h1>
      <div class="pb-sub"><?php echo $type === 'tv' ? '第 ' . $season . ' 季 · 第 ' . $player['ep'] . ' 集' : '正片'; ?><?php echo $audio === 'cn' ? ' · 普通话配音' : ''; ?><?php if ($curSource): ?> · 播放源：<?php echo h($curSource['name']); ?><?php endif; ?></div>
    </div>
    <?php if (!$isZh): ?>
    <div class="audio-switch">
      <a class="btn sm <?php echo $audio === 'cn' ? 'primary' : 'ghost'; ?>" href="<?php echo $base; ?>&ep=<?php echo $player['ep']; ?>&audio=cn"><i class="ico i-audio"></i>普通话</a>
      <a class="btn sm <?php echo $audio === 'ori' ? 'primary' : 'ghost'; ?>" href="<?php echo $base; ?>&ep=<?php echo $player['ep']; ?>&audio=ori"><i class="ico i-globe"></i>原版</a>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- 播放页观看历史上报数据源 -->
<div id="histData" data-id="<?php echo $id; ?>" data-type="<?php echo h($type); ?>"
  data-title="<?php echo h($title); ?>" data-poster="<?php echo h($poster); ?>"
  data-season="<?php echo $season; ?>" data-ep="<?php echo $player['ep']; ?>"></div>
<?php endif; ?>

<?php if (count($player['eps']) > 1): ?>
<section class="section anim" style="margin-top:28px">
  <div class="section-head">
    <span class="s-ico"><i class="ico i-play"></i></span>
    <h2>剧集列表</h2>
  </div>
  <div class="ep-grid">
    <?php foreach ($player['eps'] as $i => $e):
        $n = $i + 1;
        $epUrl = $base . '&ep=' . $n . '&audio=' . $audio;
        $tmdbEp = null;
        foreach ($epList as $te) { if ($te['num'] === $n) { $tmdbEp = $te; break; } }
        $still = $tmdbEp ? $tmdbEp['still'] : '';
        $epTitle = $tmdbEp ? $tmdbEp['name'] : $e['name'];
    ?>
    <a class="ep-card <?php echo $n === $player['ep'] ? 'current' : ''; ?>" href="<?php echo h($epUrl); ?>" style="<?php echo $n === $player['ep'] ? 'border-color:var(--theme)' : ''; ?>">
      <div class="ep-thumb">
        <?php if ($still): ?><img data-src="<?php echo h($still); ?>" alt="">
        <?php else: ?><span class="no-img"><i class="ico i-play"></i></span><?php endif; ?>
        <span class="ep-num-badge">第<?php echo $n; ?>集</span>
      </div>
      <div class="ep-txt">
        <div class="ep-title"><?php echo h($epTitle !== '' ? $epTitle : ('第' . $n . '集')); ?></div>
        <div class="ep-desc"><?php echo $n === $player['ep'] ? '正在播放' : '点击播放'; ?></div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
