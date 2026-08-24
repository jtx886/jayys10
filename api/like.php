<?php
/**
 * Jay影视 - 反馈点赞（切换）
 */
require_once dirname(__DIR__) . '/includes/init.php';
if (!is_post()) json_out(array('code' => 405, 'msg' => '请求方式错误'));
if (!csrf_check()) json_out(array('code' => 403, 'msg' => '会话已过期，请刷新页面'));
$U = current_user();
if (!$U) json_out(array('code' => 401, 'msg' => '请先登录'), 401);

$fid = intval(post('feedback_id'));
if ($fid <= 0 || !db_one("SELECT id FROM feedbacks WHERE id=?", array($fid))) {
    json_out(array('code' => 1, 'msg' => '反馈不存在'));
}

$exist = db_one("SELECT id FROM feedback_likes WHERE feedback_id=? AND user_id=?", array($fid, $U['id']));
if ($exist) {
    db_exec("DELETE FROM feedback_likes WHERE id=?", array($exist['id']));
    $liked = false;
} else {
    db_exec("INSERT INTO feedback_likes (feedback_id,user_id) VALUES (?,?)", array($fid, $U['id']));
    $liked = true;
}
$count = intval(db_val("SELECT COUNT(*) FROM feedback_likes WHERE feedback_id=?", array($fid)));
json_out(array('code' => 0, 'data' => array('liked' => $liked, 'count' => $count)));
