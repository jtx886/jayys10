<?php
/**
 * Jay影视 - 观看历史上报（累计播放秒数 / 播放位置记录）
 * 每 15 秒上报一次增量秒数，退出页面时 sendBeacon 兜底
 */
require_once dirname(__DIR__) . '/includes/init.php';
$U = current_user();
if (!$U) json_out(array('code' => 401, 'msg' => '未登录'), 401);

$tmdbId = intval(post('tmdb_id'));
$type = post('type') === 'tv' ? 'tv' : 'movie';
$title = post('title');
$poster = post('poster');
$season = max(1, intval(post('season', 1)));
$episode = max(1, intval(post('episode', 1)));
$sec = intval(post('sec', 0));

if ($tmdbId <= 0 || $title === '') json_out(array('code' => 1, 'msg' => '参数错误'));

db_exec(
    "INSERT INTO watch_history (user_id,tmdb_id,media_type,title,poster,season,episode,position,updated_at)
     VALUES (?,?,?,?,?,?,?,?,NOW())
     ON DUPLICATE KEY UPDATE
       position = position + VALUES(position),
       media_type = VALUES(media_type),
       title = VALUES(title), poster = VALUES(poster),
       season = VALUES(season), episode = VALUES(episode), updated_at = NOW()",
    array($U['id'], $tmdbId, $type, $title, $poster, $season, $episode, max(0, $sec))
);
json_out(array('code' => 0));
