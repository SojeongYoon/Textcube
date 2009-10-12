<?php
/// Copyright (c) 2004-2009, Needlworks / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)

// for Global Cache
$__gCacheBlogSettings = array();

function getBlogidByName($name) {
	global $database;
	$query = DBModel::getInstance();
	$query->reset('BlogSettings');
	$query->setQualifier('name','equals', 'name',true);
	$query->setQualifier('value', 'equals', $name, true);
	return $query->getCell('blogid');
	return false;
}

function getBlogidBySecondaryDomain($domain) {
	global $database;
	return POD::queryCell("SELECT blogid FROM {$database['prefix']}BlogSettings WHERE name = 'secondaryDomain' AND (value = '$domain' OR  value = '" . (substr($domain, 0, 4) == 'www.' ? substr($domain, 4) : 'www.' . $domain) . "')");
}

function Setting::getBlogSettingGlobals($blogid) {
	return Setting::getBlogSettingsGlobal($blogid);
}

function getSkinSettings($blogid, $forceReload = false) {
	return Setting::getSkinSettings($blogid, $forceReload);
}

function getDefaultURL($blogid) {
	global $database, $service;
	$blog = Setting::getBlogSettingsGlobal( $blogid );
	switch ($service['type']) {
		case 'domain':
			if ($blog['defaultDomain'] && $blog['secondaryDomain'])
				return ('http://' . $blog['secondaryDomain'] . (isset($service['port']) ? ':' . $service['port'] : '') . $service['path']);
			else
				return ('http://' . $blog['name'] . '.' . $service['domain'] . (isset($service['port']) ? ':' . $service['port'] : '') . $service['path']);
		case 'path':
			return ('http://' . $service['domain'] . (isset($service['port']) ? ':' . $service['port'] : '') . $service['path'] . '/' . $blog['name']);
		case 'single':
		default:
			return ('http://' . $service['domain'] . (isset($service['port']) ? ':' . $service['port'] : '') . $service['path']);
	}
}

function getBlogURL($name = null, $domain = null, $path = null, $type = null) {
	global $service, $blog;
	if ($type === null)
		$type = $service['type'];
	if ($path === null)
		$path = $service['path'];
	if ($domain === null)
		$domain = $service['domain'] . (isset($service['port']) ? ":{$service['port']}" : '');
	if ($name === null)
		$name = $blog['name'];
	switch ($type) {
		case 'domain':
			return "http://$name.$domain$path";
		case 'path':
			return "http://$domain$path/$name";
		case 'single':default:
			return "http://$domain$path".getFancyURLpostfix();
	}
}

function getBlogURLById($id = null, $domain = null, $path = null, $type = null) {
	$name = getBlogName($id);
	return getBlogURL($name, $domain, $path, $type);
}

function getFancyURLpostfix() {
	global $service;
	switch($service['fancyURL']) {
		case 0: return '/index.php?';
		case 1: return '/?';
		case 2:default: return '';
	}
}

function getBlogURLRule($domain = null, $path = null, $type = null) {
	global $service, $blog;
	if ($type === null)
		$type = $service['type'];
	if ($path === null)
		$path = $service['path'];
	if ($domain === null)
		$domain = $service['domain'] . (isset($service['port']) ? ":{$service['port']}" : '');
	switch ($type) {
		case 'domain':
			return array('http://', ".$domain$path");
		case 'path':
			return array("http://$domain$path/", '');
		case 'single':default:
			return array("http://$domain$path", '');
	}
}

function getHelpURL($path) {
	global $blog;
	return 'http://help.tattertools.com/'.$blog['language'].'/Textcube/Menu/'.$path;
}

function writeHtaccess($contents) {
	if (!is_writable(ROOT . "/.htaccess"))
		return ROOT . _f('파일 쓰기 권한이 없습니다. 웹서버가 %1 파일의 쓰기 권한을 가지고 있는지 확인하세요.','.htaccess');
	$handler = fopen(ROOT . "/.htaccess", 'w');
	if (fwrite($handler, $contents) === false) {
		fclose($handler);
		return _t('실패했습니다.');
	} else {
		fclose($handler);
		@chmod(ROOT . "/.htaccess", 0666);
		return true;
	}
}

function writeConfigFile($settings) {
	$writer = new OutputWriter;
	
	global $database, $service;
	
	$config = array();
	$contents = "<?php".CRLF."ini_set('display_errors', 'off');".CRLF;
	// Database information. It is not allow to modify.
	$config['server'] = $database['server'];
	$config['database'] = $database['database'];
	$config['username'] = $database['username'];
	$config['password'] = $database['password'];
	$config['prefix'] = $database['prefix'];
	
	foreach($config as $item => $value) {
		$contents .= "\$database['".$item."'] = '".$value."';".CRLF;
	}
	$config = array();
	$config['type'] = $service['type'];
	$config['domain'] = $service['domain'];
	$config['path'] = $service['path'];
	foreach($config as $item => $value) {
		$contents .= "\$service['".$item."'] = '".$value."';".CRLF;
	}
	
	// Service-specific information.
	foreach($settings as $item => $value) {
		if($item == 'serviceURL') {
			$contents .= "\$serviceURL = '".$value."';".CRLF;
		} else if($value === true || $value === false || is_numeric($value)){
			if($value === true) $value = 'true';
			else if($value === false) $value = 'false';
			$contents .= "\$service['".$item."'] = ".$value.";".CRLF;
		} else {
			$contents .= "\$service['".$item."'] = '".$value."';".CRLF;
		}
	}
	$contents .= "?>".CRLF;
	if (!is_writable(ROOT . "/config.php"))
		return _f('파일 쓰기 권한이 없습니다. 웹서버가 %1 파일의 쓰기 권한을 가지고 있는지 확인하세요.','config.php');
	$writer->openFile(ROOT . "/config.php");
	if ($writer->write($contents) === false) {
		$writer->close();
		return _t('실패했습니다.');
	}
	$writer->close();
	return true;
}

function getDefaultHtaccess($jsPrint = false) {
	$rootURL = substr($_SERVER['PHP_SELF'], 0, strlen($_SERVER['PHP_SELF']) - 12);
	$source = '#<IfModule mod_url.c>
#CheckURL Off
#</IfModule>
#SetEnv PRELOAD_CONFIG 1
RewriteEngine On
RewriteBase '.$rootURL.'/
RewriteRule ^(thumbnail)/([0-9]+/.+)$ cache/$1/$2 [L]
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^(cache)+/+(.+[^/])\.(cache|xml|txt|log)$ - [NC,F,L]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^(.+[^/])$ $1/ [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*)$ rewrite.php [L,QSA]';
	if($jsPrint == true) $source = str_replace(array("'","\n"),array("\'",'\n'),$source);
	return $source;
}
?>
