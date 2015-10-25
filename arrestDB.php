<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* ArrestDB 1.17 (github.com/ilausuch/ArrestDB/)
* Copyright (c) 2014 Alix Axel <alix.axel@gmail.com>
* Changes since 2015, Ivan Lausuch <ilausuch@gmail.com>
**/

class ArrestDB
{
	public static $HTTP = [
		200 => [
			'success' => [
				'code' => 200,
				'status' => 'OK',
			],
		],
		201 => [
			'success' => [
				'code' => 201,
				'status' => 'Created',
			],
		],
		204 => [
			'error' => [
				'code' => 204,
				'status' => 'No Content',
			],
		],
		400 => [
			'error' => [
				'code' => 400,
				'status' => 'Bad Request',
			],
		],
		403 => [
			'error' => [
				'code' => 403,
				'status' => 'Forbidden',
			],
		],
		404 => [
			'error' => [
				'code' => 404,
				'status' => 'Not Found',
			],
		],
		409 => [
			'error' => [
				'code' => 409,
				'status' => 'Conflict',
			],
		],
		503 => [
			'error' => [
				'code' => 503,
				'status' => 'Service Unavailable',
			],
		],
	];

	public static function Query($query = null)
	{
		static $db = null;
		static $result = [];

		try
		{
			if (isset($db, $query) === true)
			{
				if (strncasecmp($db->getAttribute(\PDO::ATTR_DRIVER_NAME), 'mysql', 5) === 0)
				{
					$query = strtr($query, '"', '`');
				}

				if (empty($result[$hash = crc32($query)]) === true)
				{
					$result[$hash] = $db->prepare($query);
				}

				$data = array_slice(func_get_args(), 1);

				if (count($data, COUNT_RECURSIVE) > count($data))
				{
					$data = iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveArrayIterator($data)), false);
				}

				if ($result[$hash]->execute($data) === true)
				{
					$sequence = null;

					if ((strncmp($db->getAttribute(\PDO::ATTR_DRIVER_NAME), 'pgsql', 5) === 0) && (sscanf($query, 'INSERT INTO %s', $sequence) > 0))
					{
						$sequence = sprintf('%s_id_seq', trim($sequence, '"'));
					}

					switch (strstr($query, ' ', true))
					{
						case 'INSERT':
						case 'REPLACE':
							return $db->lastInsertId($sequence);

						case 'UPDATE':
						case 'DELETE':
							return $result[$hash]->rowCount();

						case 'SELECT':
						case 'EXPLAIN':
						case 'PRAGMA':
						case 'SHOW':
							return $result[$hash]->fetchAll();
					}

					return true;
				}

				return false;
			}

			else if (isset($query) === true)
			{
				$options = array
				(
					\PDO::ATTR_CASE => \PDO::CASE_NATURAL,
					\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
					\PDO::ATTR_EMULATE_PREPARES => false,
					\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
					\PDO::ATTR_ORACLE_NULLS => \PDO::NULL_NATURAL,
					\PDO::ATTR_STRINGIFY_FETCHES => false,
				);

				if (preg_match('~^sqlite://([[:print:]]++)$~i', $query, $dsn) > 0)
				{
					$options += array
					(
						\PDO::ATTR_TIMEOUT => 3,
					);

					$db = new \PDO(sprintf('sqlite:%s', $dsn[1]), null, null, $options);
					$pragmas = array
					(
						'automatic_index' => 'ON',
						'cache_size' => '8192',
						'foreign_keys' => 'ON',
						'journal_size_limit' => '67110000',
						'locking_mode' => 'NORMAL',
						'page_size' => '4096',
						'recursive_triggers' => 'ON',
						'secure_delete' => 'ON',
						'synchronous' => 'NORMAL',
						'temp_store' => 'MEMORY',
						'journal_mode' => 'WAL',
						'wal_autocheckpoint' => '4096',
					);

					if (strncasecmp(PHP_OS, 'WIN', 3) !== 0)
					{
						$memory = 131072;

						if (($page = intval(shell_exec('getconf PAGESIZE'))) > 0)
						{
							$pragmas['page_size'] = $page;
						}

						if (is_readable('/proc/meminfo') === true)
						{
							if (is_resource($handle = fopen('/proc/meminfo', 'rb')) === true)
							{
								while (($line = fgets($handle, 1024)) !== false)
								{
									if (sscanf($line, 'MemTotal: %d kB', $memory) == 1)
									{
										$memory = round($memory / 131072) * 131072; break;
									}
								}

								fclose($handle);
							}
						}

						$pragmas['cache_size'] = intval($memory * 0.25 / ($pragmas['page_size'] / 1024));
						$pragmas['wal_autocheckpoint'] = $pragmas['cache_size'] / 2;
					}

					foreach ($pragmas as $key => $value)
					{
						$db->exec(sprintf('PRAGMA %s=%s;', $key, $value));
					}
				}

				else if (preg_match('~^(mysql|pgsql)://(?:(.+?)(?::(.+?))?@)?([^/:@]++)(?::(\d++))?/(\w++)/?$~i', $query, $dsn) > 0)
				{
					if (strncasecmp($query, 'mysql', 5) === 0)
					{
						$options += array
						(
							\PDO::ATTR_AUTOCOMMIT => true,
							\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES "utf8" COLLATE "utf8_general_ci", time_zone = "+00:00";',
							\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
						);
					}
					
					$db = new \PDO(sprintf('%s:host=%s;port=%s;dbname=%s', $dsn[1], $dsn[4], $dsn[5], $dsn[6]), $dsn[2], $dsn[3], $options);
				}
			}
		}

		catch (\Exception $exception)
		{
			return false;
		}

		return (isset($db) === true) ? $db : false;
	}

	public static function Reply($data)
	{
		if (isset($data["error"]))
			http_response_code($data["error"]["code"]);	

		$bitmask = 0;
		$options = ['UNESCAPED_SLASHES', 'UNESCAPED_UNICODE'];

		if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) === true)
		{
			$options[] = 'PRETTY_PRINT';
		}

		foreach ($options as $option)
		{
			$bitmask |= (defined('JSON_' . $option) === true) ? constant('JSON_' . $option) : 0;
		}

		if (($result = json_encode($data, $bitmask)) !== false)
		{
			$callback = null;

			if (array_key_exists('callback', $_GET) === true)
			{
				$callback = trim(preg_replace('~[^[:alnum:]\[\]_.]~', '', $_GET['callback']));

				if (empty($callback) !== true)
				{
					$result = sprintf('%s(%s);', $callback, $result);
				}
			}

			if (headers_sent() !== true)
			{
				header(sprintf('Content-Type: application/%s; charset=utf-8', (empty($callback) === true) ? 'json' : 'javascript'));
			}
		}
		
		return $result;
	}

	public static function Serve($on = null, $route = null, $callback = null)
	{
		static $root = null;
		global $prefix;
		
		if (!isset($prefix))
			$prefix="";

		if (isset($_SERVER['REQUEST_METHOD']) !== true)
		{
			$_SERVER['REQUEST_METHOD'] = 'CLI';
		}
		
		
		if ((empty($on) === true) || (strcasecmp($_SERVER['REQUEST_METHOD'], $on) === 0))
		{
			if (is_null($root) === true)
			{
				if (isset($_SERVER["SERVER_SOFTWARE"]) && substr($_SERVER["SERVER_SOFTWARE"],0,strlen("nginx"))=="nginx"){
					$root=substr($_SERVER["REQUEST_URI"],strlen($prefix));
				}
				else{
					$path=substr($_SERVER['PHP_SELF'], strlen($_SERVER['SCRIPT_NAME']));
					$path=substr($path,strlen($prefix));
					$root = preg_replace('~/++~', '/',  $path. '/');
				}
			}
			
			$e=explode("?",$root);
			$root=$e[0];

			
			if (is_array($route))
				$routeList=$route;
			else
				$routeList=[$route];
				
			
			foreach($routeList as $route)
				if (preg_match('~^' . str_replace(['#any', '#num'], ['[^/]++', '[^/]++'], $route) . '~i', $root, $parts) > 0)
				{
					return (empty($callback) === true) ? true : exit(call_user_func_array($callback, array_slice($parts, 1)));
				}
		}
		
		return false;
	}
	
	public static function Extend($data,$extends){
		if (isset($data[0])){
			$result=array();
			foreach ($data as $object)
				$result[]=ArrestDB::Extend($object,$extends);
				
			return $result;
		}
		else{
			$object=$data;
			foreach ($extends as $extend){
				$path=explode("/",$extend);
				ArrestDB::ExtendComplete($object,$path);
			}
			
			return $object;	
		}
	}
	
	public static function ExtendComplete(&$object,$path){
		global $relations;
		
		if ($relations==null)
			throw new Exception("Relations not defined in config",400);
			
				
		$first=$path[0];
		
		if (!isset($object[$first])){
			if (!isset($object["__table"]))
				return;
			
			if (!isset($relations[$object["__table"]]))
				throw new Exception("{$object["__table"]} not defined in relations",400);
			
			if (!isset($relations[$object["__table"]][$first]))
				throw new Exception("{$first} not defined in relations of {$object["__table"]}",400);
			
			$relation=$relations[$object["__table"]][$first];
			
			if(!isset($relation["type"])||!isset($relation["ftable"]))
				throw new Exception("Invalid configuration in {$first} of {$object["__table"]}. Requisites (type,ftable)",400);
	
			if (!isset($relation["key"]))
				$relation["key"]=ArrestDB::TableKeyName($object["__table"]);
				
			if (!isset($relation["fkey"]))
				$relation["fkey"]=ArrestDB::TableKeyName($relation["ftable"]);
			
			$id=$object[$relation["key"]];
				
			if (function_exists("ArrestDB_allow")){
				if ($relation["type"]=="object"){
					if (!ArrestDB_allow("GET_INTERNAL",$relation["ftable"],$id))
						throw new Exception("Cannot load {$relation["ftable"]} with identifier $id",403);
				}else
					if (!ArrestDB_allow("GET_INTERNAL",$relation["ftable"],""))
						throw new Exception("Cannot load {$relation["ftable"]} with identifier $id",403);
			}
			
			$query = [];
			$query["SELECT"]="*";
			
			if (function_exists("ArrestDB_tableAlias"))
				$query["TABLE"]=ArrestDB_tableAlias($relation["ftable"]);
			else
				$query["TABLE"]=$relation["ftable"];
			
			if (!isset($relation["keytype"]))
				$relation["keytype"]="numeric";
			
			if ($relation["keytype"]=="string")
				$query["WHERE"]=["{$relation["fkey"]}='{$id}'"];
			else
				$query["WHERE"]=["{$relation["fkey"]}={$id}"];

			if (function_exists("ArrestDB_modify_query"))
				$query=ArrestDB_modify_query("GET_INTERNAL",$relation["ftable"],$id,$query);
				
			$query=ArrestDB::PrepareQueryGET($query);
			
			$result=ArrestDB::Query($query);
			
			if ($result === false){
				$result = ArrestDB::$HTTP[404];
				return $result;
			}
			
			if (function_exists("ArrestDB_postProcess"))
				$result=ArrestDB_postProcess("GET_INTERNAL",$relation["ftable"],$id,$result);
			
			foreach ($result as $k=>$item)
				$result[$k]["__table"]=$relation["ftable"];
				
				
			$path2=$path;
			array_shift($path2);
	
			if (count($path2)>0)
				foreach ($result as $k=>$item)
					ArrestDB::ExtendComplete($result[$k],$path2);
			
			if ($relation["type"]=="object"){
				if (count($result)==0)
					return null;
					
				if ($result!=null)
					$result=$result[0];
			}
			else{
				if ($result==null)
					$result=[];
			}
			
			$object[$path[0]]=$result;
		}
		else{
			/*
			if (isset($object[$first][0]))
				$result=$object[$first];
			else
				$result=[$object[$first]];
			*/
			
			$path2=$path;
			array_shift($path2);
			$result=&$object[$first];

			if (count($path2)>0){
				if (isset($result[0])){
					foreach ($result as $k=>$item)
						ArrestDB::ExtendComplete($result[$k],$path2);
				}else{
					ArrestDB::ExtendComplete($result,$path2);
				}
			}
				
			//$object[$path[0]]=$result;
		}
		
		
			
	}
	
	public static function PrepareQueryGET($query,$useQuotes=true){
		
		if (isset($query["SELECT"]))
			$result= "SELECT {$query["SELECT"]} ";
		else
			$result= "SELECT * ";
			
		if (!isset($query["TABLE"]))
			die("TABLE is required in query array");
			
		if ($useQuotes)
			$result.="FROM \"{$query["TABLE"]}\" ";
		else
			$result.="FROM {$query["TABLE"]} ";

		if (isset($query["WHERE"])){
			if (is_array($query)){
				if (count($query["WHERE"])>0){
					$result.=" WHERE {$query["WHERE"][0]} ";
					
					unset($query["WHERE"][0]);
					foreach ($query["WHERE"] as $w)
						$result.=" AND {$w} ";
				}
			}
			else
				$result.=" WHERE {$query["WHERE"]} ";
		}
		
		if (isset($query["ORDER BY"]))
			$result.=" ORDER BY {$query["ORDER BY"]} ";
		
		if (isset($query["LIMIT"])){
			$result.=" LIMIT {$query["LIMIT"]} ";
			if (isset($query["OFFSET"]))
				$result.=" OFFSET {$query["OFFSET"]}";
		}
		
		return $result;
	}
	
	public static function PrepareQueryPOST($query){
		$keys=[];
		$values=[];
		$questions=[];
		
		foreach($query["VALUES"] as $k=>$v){
			$keys[]="\"$k\"";
			$values[]="$v";
			$questions[]="?";
		}
			
		$keys=implode(', ', $keys);
		
		return [
			"INSERT INTO \"{$query["TABLE"]}\" ($keys) VALUES (".implode(', ',$questions).")",
			$values
			];
	}
	
	public static function PrepareQueryPUT($query,$id){
		$res="UPDATE \"{$query["TABLE"]}\" SET ";
		
		if (count($query["VALUES"])>0){
			foreach($query["VALUES"] as $k=>$v)
				$res.="\"{$k}\"=\"{$v}\",";
		
			$res=substr($res, 0, -1);
		}
		
		$res.=" WHERE ".ArrestDB::TableKeyName($query["TABLE"])."=\"{$id}\"";
		
		return $res;
	}
	
	public static function ObfuscateId($data){
		if (function_exists("ArrestDB_obfuscate_id")){
			if (isset($data[0])){
				foreach($data as $k=>$object)
					$data[$k]=ArrestDB::ObfuscateId($object);
				
				return $data;
			}
			else{
				$data[ArrestDB::TableKeyName($data["__table"])]=ArrestDB_obfuscate_id(
						$data["__table"],
						$data[ArrestDB::TableKeyName($data["__table"])],
						false);
					
				foreach($data as $k=>$value)
					if (is_array($value))
						$data[$k]=ArrestDB::ObfuscateId($value);
						
				return $data;
			}
		}else
			return $data;
	}
	
	public static function getQuery($query,$extends=null,$id=""){
		$table=$query["TABLE"];
		$query=ArrestDB::PrepareQueryGET($query,false);
		$result=ArrestDB::Query($query);
		
		if ($result===false || count($result)==0)
			return null;
		else{
			if (isset($result[0]))
				foreach ($result as $k=>$object)
					$result[$k]["__table"]=$table;
			else
				$result["__table"]=$table;
		}
		
		if (isset($extends) === true){
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
		
		return ArrestDB::ObfuscateId($result);
	}
	
	
	public static function getObject($table,$id,$extends=null){
		$res=ArrestDB::getQuery([
		    "TABLE"=>$table,
		    "WHERE"=>[ArrestDB::TableKeyName($table)."='$id'"]
		],$extends,$id);
		
		if ($res!=null)
			return $res[0];
		else
			return null;
	}
	
	public static function getAll($table,$extends=null){
		return ArrestDB::getQuery(["TABLE"=>$table],$extends);
	}
	
	public static function TableKeyName($table){
		if (function_exists("ArrestDB_table_keyName"))
			return ArrestDB_table_keyName($table);
		else
			return "id";
	}
}
