<?php

include "config.php";
include "arrestDB.php";

if (!isset($clients))
	$clients = [];

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* ArrestDB 1.17 (github.com/ilausuch/ArrestDB/)
* Copyright (c) 2014 Alix Axel <alix.axel@gmail.com>
* Changes since 2015, Ivan Lausuch <ilausuch@gmail.com>
* Changes since 2015, Finn Malte Hinrichsen <fmh@refugeehelper.net>
**/

// Allow from any origin
if (isset($allowAnyOrigin) && $allowAnyOrigin && isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');    // cache for 1 day
}

// Access-Control headers are received during OPTIONS requests
if (isset($enableOptionsRequest) && $enableOptionsRequest && $_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE");

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers:        {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

    exit(0);
}

if (strcmp(PHP_SAPI, 'cli') === 0)
{
	exit('ArrestDB should not be run from CLI.' . PHP_EOL);
}

if ((empty($clients) !== true) && (in_array($_SERVER['REMOTE_ADDR'], (array) $clients) !== true))
{
	exit(ArrestDB::Reply(ArrestDB::$HTTP[403]));
}

else if (ArrestDB::Query($dsn) === false)
{
	exit(ArrestDB::Reply(ArrestDB::$HTTP[503]));
}

if (array_key_exists('_method', $_GET) === true)
{
	$_SERVER['REQUEST_METHOD'] = strtoupper(trim($_GET['_method']));
}

else if (array_key_exists('HTTP_X_HTTP_METHOD_OVERRIDE', $_SERVER) === true)
{
	$_SERVER['REQUEST_METHOD'] = strtoupper(trim($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']));
}

ArrestDB::Serve('GET', '/(#any)/(#any)/(#any)', function ($table, $field, $id)
{
	if (function_exists("ArrestDB_auth") && !ArrestDB_auth("GET",$table,""))
		exit(ArrestDB::Reply(ArrestDB::$HTTP[403]));

	if (function_exists("ArrestDB_tableAlias"))
		$tableBase=ArrestDB_tableAlias($table);
	else
		$tableBase=$table;
	
	if (function_exists("ArrestDB_obfuscate_id"))
		if ($id!=null && $id!="")
			$id=ArrestDB_obfuscate_id($tableBase,$id,true);
	
	if (function_exists("ArrestDB_allow"))
		if (!ArrestDB_allow("GET",$table,$id))
			return ArrestDB::Reply(ArrestDB::$HTTP[403]);
			
	$query = [];
	$query["SELECT"]="*";
	$query["TABLE"]=$tableBase;
	
	$query["WHERE"]=[
		sprintf('"%s" %s ?', $field, (ctype_digit($id) === true) ? '=' : 'LIKE')
	];
	

	if (isset($_GET['by']) === true){
		if (isset($_GET['order']) !== true)
			$_GET['order'] = 'ASC';

		$query["ORDER BY"]=$_GET['by']." ".$_GET['order'];
	}

	if (isset($_GET['limit']) === true){
		$query["LIMIT"]=$_GET['limit'];
		
		if (isset($_GET['offset']) === true)
			$query["OFFSET"]=$_GET['offset'];
	}
	
	if (function_exists("ArrestDB_modify_query"))
		$query=ArrestDB_modify_query("GET",$table,$id,$query);
		
	$query=ArrestDB::PrepareQueryGET($query);
	
	$result = ArrestDB::Query($query, $id);
	
	if ($result === false)
		return ArrestDB::Reply(ArrestDB::$HTTP[404]);

	else if (empty($result) === true)
		//return ArrestDB::Reply(ArrestDB::$HTTP[204]);
		return ArrestDB::Reply($result);
		
	if (isset($result[0]))
		foreach ($result as $k=>$object)
			$result[$k]["__table"]=$tableBase;
	else
		$result["__table"]=$table;
	
	if (isset($_GET['extends']) === true || isset($_GET['$extends']) === true){
		if (isset($_GET['extends']))
			$extends=$_GET['extends'];
		
		if (isset($_GET['$extends']))
			$extends=$_GET['$extends'];
			
		$extends=explode(",", $extends);

		try{
			$result=ArrestDB::Extend($result,$extends);
		}catch(Exception $e){
			$result = ArrestDB::$HTTP[$e->getCode()];
			$result["error"]["detail"]=$e->getMessage();
			return ArrestDB::Reply($result);
		}
	}
	
	if (function_exists("ArrestDB_postProcess"))
		$result=ArrestDB_postProcess('GET',$table,$id,$result);
	
	$result=ArrestDB::ObfuscateId($result);
		
	return ArrestDB::Reply($result);
});



ArrestDB::Serve('GET', ['/(#any)/(#num)','/(#any)/','/(#any)'],function ($table, $id = null){
	if (function_exists("ArrestDB_auth") && !ArrestDB_auth("GET",$table,$id))
		exit(ArrestDB::Reply(ArrestDB::$HTTP[403]));

	if (preg_match("/(?P<table>[^\(]+)\((?P<id>[^\)]+)\)/",$table,$matches)){
		$table=$matches["table"];
		$id=$matches["id"];
	}
	
	if (function_exists("ArrestDB_tableAlias"))
		$tableBase=ArrestDB_tableAlias($table);
	else
		$tableBase=$table;
		
		
	if (function_exists("ArrestDB_obfuscate_id"))
		if ($id!=null && $id!="")
			$id=ArrestDB_obfuscate_id($tableBase,$id,true);
		
	if (function_exists("ArrestDB_allow"))
		if (!ArrestDB_allow("GET",$table,$id))
			return ArrestDB::Reply(ArrestDB::$HTTP[403]);
	

	$query = [];
	$query["SELECT"]="*";
	$query["TABLE"]=$tableBase;
	$query["WHERE"]=[];
	
	if (isset($id) === true){
		$query["WHERE"][]='"'.ArrestDB::TableKeyName($tableBase).'"=?';
		$query["LIMIT"]=1;
	}
	else{
		if (isset($_GET['by']) === true){
			if (isset($_GET['order']) !== true)
				$_GET['order'] = 'ASC';

			$query["ORDER BY"]=$_GET['by']." ".$_GET['order'];
		}

		if (isset($_GET['limit']) === true){
			$query["LIMIT"]=$_GET['limit'];
			
			if (isset($_GET['offset']) === true)
				$query["OFFSET"]=$_GET['offset'];
		}
	}
	
	if (function_exists("ArrestDB_modify_query"))
		$query=ArrestDB_modify_query("GET",$table,$id,$query);

	$query=ArrestDB::PrepareQueryGET($query);
	$result = (isset($id) === true) ? ArrestDB::Query($query, $id) : ArrestDB::Query($query);


	if ($result === false)
		return ArrestDB::Reply(ArrestDB::$HTTP[404]);
		
	else if (empty($result) === true)
		//return ArrestDB::Reply(ArrestDB::$HTTP[204]);
		return ArrestDB::Reply($result);
		
	else if (isset($id) === true)
		$result = array_shift($result);
	
	
	if (isset($result[0]))
		foreach ($result as $k=>$object)
			$result[$k]["__table"]=$tableBase;
	else
		$result["__table"]=$tableBase;
	
	if (isset($_GET['extends']) === true || isset($_GET['$extends']) === true){
		if (isset($_GET['extends']))
			$extends=$_GET['extends'];
		
		if (isset($_GET['$extends']))
			$extends=$_GET['$extends'];
			
		$extends=explode(",", $extends);
		try{
			$result=ArrestDB::Extend($result,$extends);
		}catch(Exception $e){
			$result = ArrestDB::$HTTP[$e->getCode()];
			$result["error"]["detail"]=$e->getMessage();
			return ArrestDB::Reply($result);
		}
	}
	
	if (function_exists("ArrestDB_postProcess"))
		$result=ArrestDB_postProcess("GET",$table,$id,$result);
	
	$result=ArrestDB::ObfuscateId($result);
		
	return ArrestDB::Reply($result);
});


ArrestDB::Serve('DELETE', '/(#any)/(#num)', function ($table, $id)
{
	if (function_exists("ArrestDB_auth") && !ArrestDB_auth("DELETE",$table,$id))
		exit(ArrestDB::Reply(ArrestDB::$HTTP[403]));
	
	if (preg_match("/(?P<table>[^\(]+)\((?P<id>[^\)]+)\)/",$table,$matches)){
		$table=$matches["table"];
		$id=$matches["id"];
	}
	
	if (function_exists("ArrestDB_obfuscate_id"))
		if ($id!=null && $id!="")
			$id=ArrestDB_obfuscate_id($table,$id,true);
		
	if (function_exists("ArrestDB_allow"))
		if (!ArrestDB_allow("DELETE",$table,$id)){
			$result = ArrestDB::$HTTP[403];
			return ArrestDB::Reply($result);
		}
				
	$query = array
	(
		sprintf('DELETE FROM "%s" WHERE "%s" = ?', $table, ArrestDB::TableKeyName($table)),
	);

	$query = sprintf('%s;', implode(' ', $query));
	$result = ArrestDB::Query($query, $id);

	if ($result === false)
	{
		$result = ArrestDB::$HTTP[404];
	}

	else if (empty($result) === true)
	{
		$result = ArrestDB::$HTTP[204];
	}

	else
	{
		$result = ArrestDB::$HTTP[200];
	}

	return ArrestDB::Reply($result);
});

if (in_array($http = strtoupper($_SERVER['REQUEST_METHOD']), ['POST', 'PUT']) === true)
{
	if (preg_match('~^\x78[\x01\x5E\x9C\xDA]~', $data = file_get_contents('php://input')) > 0)
	{
		$data = gzuncompress($data);
	}

	if ((array_key_exists('CONTENT_TYPE', $_SERVER) === true) && (empty($data) !== true))
	{
		if (strncasecmp($_SERVER['CONTENT_TYPE'], 'application/json', 16) === 0)
		{
			$GLOBALS['_' . $http] = json_decode($data, true);
		}

		else if ((strncasecmp($_SERVER['CONTENT_TYPE'], 'application/x-www-form-urlencoded', 33) === 0) && (strncasecmp($_SERVER['REQUEST_METHOD'], 'PUT', 3) === 0))
		{
			parse_str($data, $GLOBALS['_' . $http]);
		}
	}

	if ((isset($GLOBALS['_' . $http]) !== true) || (is_array($GLOBALS['_' . $http]) !== true))
	{
		$GLOBALS['_' . $http] = [];
	}

	unset($data);
}

ArrestDB::Serve('POST', '/(#any)', function ($table){
	
	
	if (function_exists("ArrestDB_auth") && !ArrestDB_auth("POST",$table,""))
		exit(ArrestDB::Reply(ArrestDB::$HTTP[403]));
				
	if (function_exists("ArrestDB_allow"))
		if (!ArrestDB_allow("POST",$table,0)){
			$result = ArrestDB::$HTTP[403];
			return ArrestDB::Reply($result);
		}
		
	if (substr($table,-2)=="()"){
		$fnc=substr($table,0,-2);
		if (function_exists("ArrestDB_function")){
			$res=ArrestDB_function($fnc,$_POST);
			if ($res===false){
				return json_encode(ArrestDB::Reply(ArrestDB::$HTTP[400]));
			}
			else{
				$result = ArrestDB::$HTTP[200];
				$result["success"]["data"]=$res;
				
				return json_encode($result);
			}
		}
		else
			return json_encode(ArrestDB::Reply(ArrestDB::$HTTP[403]));
	}
		
	if (empty($_POST) === true)
	{
		$result = ArrestDB::$HTTP[204];
	}

	else if (is_array($_POST) === true)
	{
		$queries = [];

		if (count($_POST) == count($_POST, COUNT_RECURSIVE))
		{
			$_POST = [$_POST];
		}

		foreach ($_POST as $row)
		{
			$query = [];
			$query["TABLE"]=$table;
			$query["VALUES"]=[];
			
			foreach ($row as $key => $value)
				$query["VALUES"][$key]=$value;
			
			if (function_exists("ArrestDB_modify_query"))
				$query=ArrestDB_modify_query("POST",$table,0,$query);
			
			$query=ArrestDB::PrepareQueryPOST($query);
			
			$queries[]=$query;
		}
		
		if (count($queries) > 1)
		{
			ArrestDB::Query()->beginTransaction();

			while (is_null($query = array_shift($queries)) !== true)
			{
				if (($result = ArrestDB::Query($query[0], $query[1])) === false)
				{
					ArrestDB::Query()->rollBack(); break;
				}
			}

			if (($result !== false) && (ArrestDB::Query()->inTransaction() === true))
			{
				$result = ArrestDB::Query()->commit();
			}
		}

		else if (is_null($query = array_shift($queries)) !== true)
		{
			$result = ArrestDB::Query($query[0], $query[1]);
		}
		
		$ids=$result;
		
		if ($result === false)
		{
			$result = ArrestDB::$HTTP[409];
		}

		else
		{
			$result = ArrestDB::$HTTP[201];
			$result["success"]["Ids"]=$ids;
			if (function_exists(ArrestDB_postProcess))
				ArrestDB_postProcess("POST",$table,$ids);
		}
	}

	return ArrestDB::Reply($result);
});

ArrestDB::Serve('PUT', '/(#any)/(#num)', function ($table, $id)
{
	if (function_exists("ArrestDB_auth") && !ArrestDB_auth("PUT",$table,$id))
		exit(ArrestDB::Reply(ArrestDB::$HTTP[403]));
	
	if (preg_match("/(?P<table>[^\(]+)\((?P<id>[^\)]+)\)/",$table,$matches)){
		$table=$matches["table"];
		$id=$matches["id"];
	}
	
	if (function_exists("ArrestDB_obfuscate_id"))
		if ($id!=null && $id!="")
			$id=ArrestDB_obfuscate_id($table,$id,true);
				
	if (function_exists("ArrestDB_allow"))
		if (!ArrestDB_allow("PUT",$table,$id)){
			$result = ArrestDB::$HTTP[403];
			return ArrestDB::Reply($result);
		}
		
	if (function_exists("ArrestDB_tableAlias"))
		$table=ArrestDB_tableAlias($table);
			
	if (empty($GLOBALS['_PUT']) === true)
	{
		$result = ArrestDB::$HTTP[204];
	}

	else if (is_array($GLOBALS['_PUT']) === true)
	{
		$query = [];
		$query["TABLE"]=$table;
		$query["VALUES"]=[];
		
		foreach ($GLOBALS["_PUT"] as $key => $value)
			$query["VALUES"][$key]=$value;
		
		if (function_exists("ArrestDB_modify_query"))
			$query=ArrestDB_modify_query("PUT",$table,$id,$query);
	
		$data = [];
		foreach ($query['VALUES'] as $key => $value){
			$data[$key] = sprintf('"%s" = ?', $key);
		}
		
		$query2 = array(
			sprintf('UPDATE "%s" SET %s WHERE "%s" = ?', $query["TABLE"], implode(', ', $data), ArrestDB::TableKeyName($query["TABLE"])),
		);
		
		$query2= sprintf('%s;', implode(' ', $query2));
		$result = ArrestDB::Query($query2, $query['VALUES'], $id);
		if ($result === false)
			$result = ArrestDB::$HTTP[409];
		else{
			$result = ArrestDB::$HTTP[200];
			if (function_exists(ArrestDB_postProcess))
				ArrestDB_postProcess("PUT",$table,$id);	
		}
	}

	return ArrestDB::Reply($result);
});

exit(ArrestDB::Reply(ArrestDB::$HTTP[400]));

