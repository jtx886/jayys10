<?php
/**
 * Jay影视 - 收藏切换
 */
require_once dirname(__DIR__) . '/includes/init.php';
if (!is_post()) json_out(array('code' => 405, 'msg' => '请求方式错误'));
if (!csrf_check()) json_out(array('code' => 403, 'msg' => '会话已过期，请刷新页面'));
$U = current_user();
if (!$U) json_out(array('code' => 401, 'msg' => '请先登录'), 401);

$tmdbId = intval(post('tmdb_id'));
$type = post('type') === 'tv' ? 'tv' : 'movie';
$title = post('title');
$poster = post('poster');
if ($tmdbId <= 0 || $title === '') json_out(array('code' => 1, 'msg' => '参数错误'));

$exist = db_one("SELECT id FROM favorites WHERE user_id=? AND tmdb_id=? AND media_type=?", array($U['id'], $tmdbId, $type));
if ($exist) {
    db_exec("DELETE FROM favorites WHERE id=?", array($exist['id']));
    json_out(array('code' => 0, 'data' => 'removed'));
}
db_exec("INSERT INTO favorites (user_id,tmdb_id,media_type,title,poster) VALUES (?,?,?,?,?)",
    array($U['id'], $tmdbId, $type, $title, $poster));
json_out(array('code' => 0, 'data' => 'added'));
