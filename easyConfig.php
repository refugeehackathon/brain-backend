<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* EasyConfig for modified ArrestDB
* 2015, Ivan Lausuch <ilausuch@gmail.com>
**/

$relations=[];

/**
	Filter fields (all are optionals):
		- method (string or array) : what method match [GET, GET_INTERNAL (in extends use), POST, PUT, DELETE
		- no_method (string or array) : inverted method filter
		
		- table (string or array) : what table must match
		- no_table (string or array) : inverted table filter
		
		- id_defined ('yes' or 'no') : if table identifier is defined or not in http query
		
		- id (string or array) :  if table identifier is in list
		- no_id (string or array) : inverted table filter
		
	Query filter:
		- TABLE
		- WHERE
*/
class ArrestDBConfig{
	/**
		Define a relation table.
		Params:
			- $table : Table that contains the relation
			- $name : Name of relation, should be the foreign table name
			- $config : Relation config. Use prepareRelationObject or prepareRelationList to define the config
	*/
	public static function relation($table,$name,$config){
		global $relations;
		
		if (!isset($relations[$table]))
			$relations[$table]=[];
			
		$relations[$table][$name]=$config;
	}
	
	/**
		Prepare a relation 1 to 1
		Params:
			- $key : primary table key
			- $foreignTable : foreign table name
			- $foreignKey :	foreign table identifier name ('id' by default)
	*/
	public static function prepareRelationObject($foreignTable,$key,$foreingKey="id",$keytype="numeric"){
		return ["type"=>"object","ftable"=>$foreignTable,"key"=>$key,"fkey"=>$foreingKey,$keytype];
	}
	
	/**
		Prepare a relation 1 to n
		Params:
			- $key : primary table key
			- $foreignTable : foreign table name
			- $foreignKey :	foreign table identifier name ('id' by default)
	*/
	public static function prepareRelationList($foreignTable,$foreingKey,$key="id",$keytype="numeric"){
		return ["type"=>"array","ftable"=>$foreignTable,"key"=>$key,"fkey"=>$foreingKey,$keytype];
	}
	
	/**
		Define a table alias.
		An alias sould be used to do different preprocess and postprocess actions	
	*/
	public static function alias($alias,$table){
		self::$registryAlias[]=array("table"=>$table,"alias"=>$alias);
	}

	/**
		Preprocess that modify query
		Function: function($method,$table,$id,$query){return $query;}
		Function must return $query variable
	*/
	public static function modifyQuery($filter,$function){
		self::$registryModifyQuery[]=array("filter"=>$filter,"function"=>$function);
	}
	
	/**
		Post process when you have a result (object or array)
		Function: function($method,$table,$id,$data){return $data}
		Function must return $data variable	
	*/
	public static function postProcess($filter,$function){
		self::$registryPostProcess[]=array("filter"=>$filter,"function"=>$function);
	}
	
	/**
		Check auth previous execution
		Function (returns true or false): function ($method,$table,$id){return true;}
	*/
	public static function auth($filter,$function){
		self::$registryAuth[]=array("filter"=>$filter,"function"=>$function);
	}
	
	/**
		Check if is allowed acces to a table
		Function (returns true or false): function ($method,$table,$id){return true;}
	*/
	public static function allow($filter,$function){
		self::$registryAllow[]=array("filter"=>$filter,"function"=>$function);
	}
	
	/**
		Add method to api
		Function (returns any object, array, string or boolean): function ($func,$data){return true;}
	*/
	public static function fnc($name,$function){
		self::$registryFunctions[]=array("name"=>$name,"function"=>$function);
	}
	
	/**
		Ofuscate ID function if is needed (pendent)	
	*/
	/*
	public static function ofuscateId($filter,$function){
		self::$registryOfuscate[]=array("filter"=>$filter,"function"=>$function);
	}
	*/
	
	/**
		Return the identifier key name if is distinct to "id"	
	*/
	public static function keyname($table,$name){
		self::$registryKeyName[$table]=$name;
	}
	
		
	/**
		_internal
		execute modifyQuery
	*/
	public static function execute_modifyQuery($method,$table,$id,$query){
		foreach (self::$registryModifyQuery as $reg)
			if (self::checkFilter($method,$table,$id,["query"=>$query],$reg["filter"]))
				$query=call_user_func_array($reg["function"],[$method,$table,$id,$query]);
		
		return $query;
	}
	
	/**
		_internal
		execute postProcess
	*/
	public static function execute_postProcess($method,$table,$id,$data){
		foreach (self::$registryPostProcess as $reg)
			if (self::checkFilter($method,$table,$id,["data"=>$data],$reg["filter"]))
				$data=call_user_func_array($reg["function"],[$method,$table,$id,$data]);
		
		return $data;
	}
	
	/**
		_internal
		execute auth
	*/
	public static function execute_auth($method,$table,$id){
		foreach (self::$registryAuth as $reg){
			if (self::checkFilter($method,$table,$id,[],$reg["filter"]))
				if (call_user_func_array($reg["function"],[$method,$table,$id]))
					return true;
				else	
					return false;
		}
		
		return true;
	}
	
	/**
		_internal
		execute allow
	*/
	public static function execute_allow($method,$table,$id){
		foreach (self::$registryAllow as $reg){
			if (self::checkFilter($method,$table,$id,[],$reg["filter"]))
				if (!call_user_func_array($reg["function"],[$method,$table,$id]))
					return false;
		}
		
		return true;
	}
	
	/**
		_internal
		execute alias
	*/
	public static function execute_alias($table){
		foreach (self::$registryAlias as $reg)
			if ($reg["alias"]==$table)
				return $reg["table"];
		
		return $table;
	}
	
	/**
		_internal
		execute method
	*/
	public static function execute_fnc($name,$data){
		foreach (self::$registryFunctions as $reg)
			if ($reg["name"]==$name)
				return call_user_func_array($reg["function"],[$name,$data]);	
					
		return false;
	}
	
	/**
		_internal
		ofuscate id (pendent)
	*/
	/*
	public static function execute_ofusacteId($table,$id,$reverse){
		foreach (self::$registryOfuscate as $reg)
			if (self::checkFilter("",$table,$id,[],$reg["filter"]))
				return call_user_func_array($reg["function"],[$table,$id,$reverse]);
		
		return $id;
	}
	*/
	
	/**
		_internal
		get table key name identifier
	*/
	public static function execute_keyname($table){
		if (isset(self::$registryKeyName[$table]))
			return self::$registryKeyName[$table];
		
		return "id";
	}
	
	
	/**
		_internal
		check if item is in list
	*/
	private static function checkFilterInList($value,$list){
		if (!is_array($list))
			$list=[$list];
			
		foreach ($list as $m){
			if ($m==$value)
				return true;
		}
		
		return false;
	}
	
	/**
		_internal
		check if item is not in list
	*/
	private static function checkFilterNotInList($value,$list){
		if (!is_array($list))
			$list=[$list];
			
		foreach ($list as $m){
			if ($m==$value)
				return false;
		}
		
		return true;
	}
	
	/**
		_internal
		check a filter
	*/
	private static function checkFilter($method,$table,$id,$extra,$filterConfig){
		if ($filterConfig==null)
			return true;
			
		foreach($filterConfig as $filter=>$filterv){
			switch($filter){
				case "method":
					if (!self::checkFilterInList($method,$filterv))
						return false;
				break;
				case "no_method":
					if (!self::checkFilterNotInList($method,$filterv))
						return false;
				break;
				case "table":
					if (!self::checkFilterInList($table,$filterv))
						return false;
				break;
				case "no_table":
					if (!self::checkFilterNotInList($table,$filterv))
						return false;
				break;
				case "id_defined":
					if ($filterv=="yes")
						if ($id==null || $id=="")
							return false;
							
					if ($filterv=="no")
						if ($id!=null && $id!="")
							return false;
				break;
				case "id":
					if (!self::checkFilterInList($id,$filterv))
						return false;
				break;
				case "no_id":
					if (!self::checkFilterNotInList($id,$filterv))
						return false;
				break;
			}
		}
		return true;
	}
	
	private static $registryModifyQuery=[];	
	private static $registryPostProcess=[];
	private static $registryAuth=[];
	private static $registryAllow=[];
	private static $registryAlias=[];
	private static $registryFunctions=[];
	private static $registryOfuscate=[];
	private static $registryKeyName=[];
};

/**
	Modified ArrestDB callbacks	
*/
if (!function_exists("ArrestDB_modify_query")){
	function ArrestDB_modify_query($method,$table,$id,$query){
		return ArrestDBConfig::execute_modifyQuery($method,$table,$id,$query);
	}
}

if (!function_exists("ArrestDB_postProcess")){
	function ArrestDB_postProcess($method,$table,$id,$data){
		return ArrestDBConfig::execute_postProcess($method,$table,$id,$data);
	}
}

if (!function_exists("ArrestDB_auth")){
	function ArrestDB_auth($method,$table,$id){
		return ArrestDBConfig::execute_auth($method,$table,$id);
	}
}

if (!function_exists("ArrestDB_allow")){
	function ArrestDB_allow($method,$table,$id){
		return ArrestDBConfig::execute_allow($method,$table,$id);
	}
}

if (!function_exists("ArrestDB_tableAlias")){
	function ArrestDB_tableAlias($table){
		return ArrestDBConfig::execute_alias($table);
	}
}

if (!function_exists("ArrestDB_function")){
	function ArrestDB_function($func,$data){
		return ArrestDBConfig::execute_fnc($func,$data);
	}
}

if (!function_exists("ArrestDB_table_keyName")){
	function ArrestDB_table_keyName($table){
		return ArrestDBConfig::execute_keyname($table);
	}
}

/* (pendent)
if (!function_exists("ArrestDB_obfuscate_id")){
	function ArrestDB_obfuscate_id($table,$id,$reverse){
		return ArrestDBConfig::execute_ofusacteId($table,$id,$reverse);
	}
}
*/

