<?php
/**
 * Jay影视 - 分类浏览页
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/tmdb.php';

$catMap = array(
    'movie'   => array('name' => '电影', 'icon' => 'i-film'),
    'tv'      => array('name' => '剧集', 'icon' => 'i-tv'),
    'anime'   => array('name' => '动漫', 'icon' => 'i-spark'),
    'variety' => array('name' => '综艺', 'icon' => 'i-mic'),
);
$cat = get('cat', 'movie');
if (!isset($catMap[$cat])) $cat = 'movie';
$page = max(1, intval(get('page', 1)));

$data = tmdb_discover($cat, $page);
$items = isset($data['results']) ? $data['results'] : array();
$totalPages = min(500, isset($data['total_pages']) ? intval($data['total_pages']) : 0);

$NAV_ACTIVE = $cat;
$PAGE_TITLE = $catMap[$cat]['name'];

/** 分页 */
function pager_html($page, $total)
{
    if ($total <= 1) return '';
    $q = $_GET; unset($q['page']);
    $url = function ($p) use ($q) { $q['page'] = $p; return '?' . http_build_query($q); };
    $html = '<div class="pager">';
    $html .= $page > 1 ? '<a href="' . $url($page - 1) . '"><i class="ico i-chev-l"></i></a>' : '<span class="dis"><i class="ico i-chev-l"></i></span>';
    $from = max(1, $page - 2); $to = min($total, $page + 2);
    if ($from > 1) $html .= '<a href="' . $url(1) . '">1</a>' . ($from > 2 ? '<span class="dis">…</span>' : '');
    for ($i = $from; $i <= $to; $i++) $html .= ($i === $page) ? '<span class="cur">' . $i . '</span>' : '<a href="' . $url($i) . '">' . $i . '</a>';
    if ($to < $total) $html .= ($to < $total - 1 ? '<span class="dis">…</span>' : '') . '<a href="' . $url($total) . '">' . $total . '</a>';
    $html .= $page < $total ? '<a href="' . $url($page + 1) . '"><i class="ico i-chev-r"></i></a>' : '<span class="dis"><i class="ico i-chev-r"></i></span>';
    return $html . '</div>';
}

require __DIR__ . '/includes/header.php';
?>
<div class="page-head anim">
  <span class="s-ico" style="width:42px;height:42px;border-radius:12px;background:var(--theme-soft);color:var(--theme);display:flex;align-items:center;justify-content:center"><i class="ico <?php echo $catMap[$cat]['icon']; ?>" style="font-size:19px"></i></span>
  <h1><?php echo $catMap[$cat]['name']; ?></h1>
  <span class="count">共 <?php echo intval(isset($data['total_results']) ? $data['total_results'] : 0); ?> 部作品</span>
</div>

<?php if (empty($items)): ?>
<div class="empty-box"><i class="ico i-info"></i><p>暂无数据，请稍后再试</p><a class="btn ghost" href="index.php">返回首页</a></div>
<?php else: ?>
<div class="grid-movies">
  <?php foreach ($items as $i => $r):
      $m = tmdb_item($r);
      if (!$m['id'] || $m['poster'] === '') continue;
      $typeTxt = $m['type'] === 'tv' ? '剧集' : '电影';
  ?>
  <a class="media-card" style="animation-delay:<?php echo $i * 0.02; ?>s" href="<?php echo media_link($m['type'], $m['id']); ?>">
    <div class="mc-poster">
      <img data-src="<?php echo h($m['poster']); ?>" alt="<?php echo h($m['title']); ?>">
      <?php if ($m['rate'] > 0): ?><span class="mc-rate"><i class="ico i-star"></i><?php echo number_format($m['rate'], 1); ?></span><?php endif; ?>
      <span class="mc-play"><span><i class="ico i-play invert"></i></span></span>
    </div>
    <div class="mc-info">
      <div class="mc-title"><?php echo h($m['title']); ?></div>
      <div class="mc-sub"><span class="mc-type"><?php echo $typeTxt; ?></span><span class="dot"></span><span><?php echo h($m['year']); ?></span></div>
    </div>
  </a>
  <?php endforeach; ?>
</div>
<?php echo pager_html($page, $totalPages); ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
