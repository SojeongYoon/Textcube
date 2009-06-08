<?php
/// Copyright (c) 2004-2009, Needlworks / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/doc/LICENSE, /doc/COPYRIGHT)

// DBQuery version 1.8 for MySQL

global $cachedResult;
global $fileCachedResult;
global $__gEscapeTag;
global $__dbProperties;
global $__gLastQueryType;
$cachedResult = $__dbProperties = array();
$__gEscapeTag = null;

class DBQuery {	
	/*@static@*/
	public static function bind($database) {
		global $__dbProperties;
		// Connects DB and set environment variables
		// $database array should contain 'server','username','password'.
		if(!isset($database) || empty($database)) return false;
		$handle = @mysql_connect($database['server'].(isset($database['port']) ? ':'.$database['port'] : ''), $database['username'], $database['password']);
		if(!$handle) return false;
		$handle = @mysql_select_db($database['database']);
		if(!$handle) return false;

		if (DBQuery::query('SET CHARACTER SET utf8'))
			$__dbProperties['charset'] = 'utf8';
		else
			$__dbProperties['charset'] = 'default';
		@DBQuery::query('SET SESSION collation_connection = \'utf8_general_ci\'');
		return true;
	}
	
	public static function unbind() {
		mysql_close();
		return true;
	}

	public static function charset() {
		global $__dbProperties;
		if (array_key_exists('charset', $__dbProperties)) return $__dbProperties['charset'];
		else return null;
	}
	public static function dbms() {
		return 'MySQL';
	}

	public static function version($mode = 'server') {
		global $__dbProperties;
		if (array_key_exists('version', $__dbProperties)) return $__dbProperties['version'];
		else {
			$__dbProperties['version'] = DBQuery::queryCell("SHOW VARIABLES LIKE 'version'");
			return $__dbProperties['version'];
		}
	}
	
	public static function tableList($condition = null) {
		global $__dbProperties;
		if (!array_key_exists('tableList', $__dbProperties)) { 
			$tableData = DBQuery::queryAll('SHOW TABLES');
			$__dbProperties['tableList'] = array();
			foreach($tableData as $tbl) {
				array_push($__dbProperties['tableList'], $tbl[0]);
			}
		}
		$result = array();
		if(!is_null($condition)) {
			foreach($__dbProperties['tableList'] as $item) {
				if(strpos($item, $condition) === 0) {
					array_push($result, $item);
				}
			}
			return $result;
		} else {
			return $__dbProperties['tableList'];
		}
	}

	public static function setTimezone($time) {
		return DBQuery::query('SET time_zone = \'' . Timezone::getCanonical() . '\'');
	}

	/*@static@*/
	public static function queryExistence($query) {
		if ($result = DBQuery::query($query)) {
			if (mysql_num_rows($result) > 0) {
				mysql_free_result($result);
				return true;
			}
			mysql_free_result($result);
		}
		return false;
	}
	
	/*@static@*/
	public static function queryCount($query) {
		global $__gLastQueryType;
		$count = 0;
		$query = trim($query);
		if ($result = DBQuery::query($query)) {
			$operation = strtolower(substr($query, 0,6));
			$__gLastQueryType = $operation;
			switch ($operation) {
				case 'select':
					$count = mysql_num_rows($result);
					mysql_free_result($result);
					break;
				case 'insert':
				case 'update':
				case 'delete':
				case 'replac':
				default:
					$count = mysql_affected_rows();
					//mysql_free_result();
					break;
			}
		}
		return $count;
	}

	/*@static@*/
	public static function queryCell($query, $field = 0, $useCache=true) {
		$type = 'both';
		if (is_numeric($field)) {
			$type = 'num';
		} else {
			$type = 'assoc';
		}

		if( $useCache ) {
			$result = DBQuery::queryAllWithCache($query, $type);
		} else {
			$result = DBQuery::queryAllWithoutCache($query, $type);
		}
		if( empty($result) ) {
			return null;
		}
		return $result[0][$field];
	}
	
	/*@static@*/
	public static function queryRow($query, $type = 'both', $useCache=true) {
		if( $useCache ) {
			$result = DBQuery::queryAllWithCache($query, $type, 1);
		} else {
			$result = DBQuery::queryAllWithoutCache($query, $type, 1);
		}
		if( empty($result) ) {
			return null;
		}
		return $result[0];
	}
	
	/*@static@*/
	public static function queryColumn($query, $useCache=true) {
		global $cachedResult;
		$cacheKey = "{$query}_queryColumn";
		if( $useCache && isset( $cachedResult[$cacheKey] ) ) {
			if(function_exists( '__tcSqlLogBegin' ) ) {
				__tcSqlLogBegin($query);
				__tcSqlLogEnd(null,1);
			}
			$cachedResult[$cacheKey][0]++;
			return $cachedResult[$cacheKey][1];
		}

		$column = null;
		if ($result = DBQuery::query($query)) {
			$column = array();
			while ($row = mysql_fetch_row($result))
				array_push($column, $row[0]);
			mysql_free_result($result);
		}

		if( $useCache ) {
			$cachedResult[$cacheKey] = array( 1, $column );
		}
		return $column;
	}
	
	/*@static@*/
	public static function queryAll ($query, $type = 'both', $count = -1) {
		return DBQuery::queryAllWithCache($query, $type, $count);
		//return DBQuery::queryAllWithoutCache($query, $type, $count);  // Your choice. :)
	}

	public static function queryAllWithoutCache($query, $type = 'both', $count = -1) {
		$all = array();
		$realtype = DBQuery::__queryType($type);
		if ($result = DBQuery::query($query)) {
			while ( ($count-- !=0) && $row = mysql_fetch_array($result, $realtype))
				array_push($all, $row);
			mysql_free_result($result);
			return $all;
		}
		return null;
	}
	
	public static function queryAllWithCache($query, $type = 'both', $count = -1) {
		global $cachedResult;
		$cacheKey = "{$query}_{$type}_{$count}";
		if( isset( $cachedResult[$cacheKey] ) ) {
			if( function_exists( '__tcSqlLogBegin' ) ) {
				__tcSqlLogBegin($query);
				__tcSqlLogEnd(null,1);
			}
			$cachedResult[$cacheKey][0]++;
			return $cachedResult[$cacheKey][1];
		}
		$all = DBQuery::queryAllWithoutCache($query,$type,$count);
		$cachedResult[$cacheKey] = array( 1, $all );
		return $all;
	}
	
	/*@static@*/
	public static function execute($query) {
		return DBQuery::query($query) ? true : false;
	}

	/*@static@*/
	public static function multiQuery() {
		$result = false;
		foreach (func_get_args() as $query) {
			if (is_array($query)) {
				foreach ($query as $subquery)
					if (($result = DBQuery::query($subquery)) === false)
						return false;
			} else if (($result = DBQuery::query($query)) === false)
				return false;
		}
		return $result;
	}

	/*@static@*/
	public static function query($query) {
		global $__gLastQueryType;
		if( function_exists( '__tcSqlLogBegin' ) ) {
			__tcSqlLogBegin($query);
			$result = mysql_query($query);
			__tcSqlLogEnd($result,0);
		} else {
			$result = mysql_query($query);
		}
		$__gLastQueryType = strtolower(substr($query, 0,6));
		if( stristr($query, 'update ') ||
			stristr($query, 'insert ') ||
			stristr($query, 'delete ') ||
			stristr($query, 'replace ') ) {
			DBQuery::clearCache();
		}
		return $result;
	}
	
	public static function insertId() {
		return mysql_insert_id();
	}
	
	public static function escapeString($string, $link = null){
		global $__gEscapeTag;
		if(is_null($__gEscapeTag)) {
			if ( function_exists('mysql_real_escape_string') && (mysql_real_escape_string('ㅋ') == 'ㅋ')) {
				$__gEscapeTag = 'real';
			} else {
				$__gEscapeTag = 'none';
			}
		}
		if($__gEscapeTag == 'real') {
			return is_null($link) ? mysql_real_escape_string($string) : mysql_real_escape_string($string, $link);
		} else {
			return mysql_escape_string($string);
		}
	}
	
	public static function clearCache() {
		global $cachedResult;
		$cachedResult = array();
		if( function_exists( '__tcSqlLogBegin' ) ) {
			__tcSqlLogBegin("Cache cleared");
			__tcSqlLogEnd(null,2);
		}
	}

	public static function cacheLoad() {
		global $fileCachedResult;
	}
	public static function cacheSave() {
		global $fileCachedResult;
	}
	
	/* Raw public static functions (to easier adoptation) */
	/*@static@*/
	public static function num_rows($handle = null) {
		global $__gLastQueryType;
		switch($__gLastQueryType) {
			case 'select':
				return mysql_num_rows($handle);
				break;
			default:
				return mysql_affected_rows($handle);
				break;
		}
		return null;
	}
	/*@static@*/
	public static function free($handle = null) {
		mysql_free_result($handle);
	}
	
	/*@static@*/
	public static function fetch($handle = null, $type = 'assoc') {
		if($type == 'array') return mysql_fetch_array($handle); // Can I use mysql_fetch_row instead?
		else if ($type == 'row') return mysql_fetch_row($handle);
		else return mysql_fetch_assoc($handle);
	}
	
	/*@static@*/
	public static function error($err = null) {
		if($err === null) return mysql_error();
		else return mysql_error($err);
	}
	
	/*@static@*/
	public static function stat($stat = null) {
		if($stat === null) return mysql_stat();
		else return mysql_stat($stat);
	}
	
	/*@static@*/
	public static function __queryType($type) {
		switch(strtolower($type)) {
			case 'num':
				return MYSQL_NUM;
			case 'assoc':
				return MYSQL_ASSOC;				
			case 'both':
			default:
				return MYSQL_BOTH;
		}
	}
}

DBQuery::cacheLoad();
register_shutdown_function( array('DBQuery','cacheSave') );

?>