<?
define('ROOT', '../../../../..');
$IV = array(
	'POST' => array(
		'id' => array('id'),
		'name' => array('string'),
		'rss' => array('url', 'default' => ''),
		'url' => array('url')
	)
);
require ROOT . '/lib/includeForOwner.php';
requireStrictRoute();
respondResultPage(updateLink($owner, $_POST));
?>