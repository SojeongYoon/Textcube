<?
define('ROOT', '../../../../..');
$IV = array(
	'POST' => array(
		'entriesOnRecent' => array('int'),
		'commentsOnRecent' => array('int'),
		'commentsOnGuestbook' => array('int'),
		'tagsOnTagbox' => array('int'),
		'tagboxAlign' => array('int'),
		'trackbacksOnRecent' => array('int'),
		'showListOnCategory' => array('int'),
		'showListOnArchive' => array('int'),
		'expandComment' => array('int'),
		'expandTrackback' => array('int'),
		'recentNoticeLength' => array('int'),
		'recentEntryLength' => array('int'),
		'recentCommentLength' => array('int'),
		'recentTrackbackLength' => array('int'),
		'linkLength' => array('int'),
		'entriesOnPage' => array('int')
	)
);
require ROOT . '/lib/includeForOwner.php';
if (setSkinSetting($owner, $_POST)) {
	printRespond(array('error' => 0));
} else {
	printRespond(array('error' => 1, 'msg' => mysql_error()));
}
?>