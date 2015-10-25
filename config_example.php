<?php
require_once("easyConfig.php");

/*
	Example
	------------
	
	This is an example to explain some concepts in this example. A Customer have only one User, but can have some Purchases. A Purchase has some Products, using relation PurchaseProduct
	
   +---------------+
   |PurchaseProduct|        +-------+
   |- id           |        |Product|
   |- product_id   | ...... |- id   |
 ..|- purchase_id  |        +-------+
 | |- quantity     |
 | +---------------+
 |
 |
 | +-------------+
 | |Purchase     |              +---------+
 ..|- id         |              |Customer |                 +----+    +----------+
   |- customer_id|--------------|- id     |                 |User|    | UserInfo |
   +-------------+              |- user_id|---------------- |- id|----| - user_id|
                                +---------+                 +----+	  +----------+


	// Get all rows from the "customers" extending information to User, userinfo and purchase
	GET http://api.example.com/customers/?extends=User/UserInfo,Purchase

	// Get a single row from the "customers" table (where "123" is the ID)
	GET http://api.example.com/customers/123
	GET http://api.example.com/customers/123/
	GET http://api.example.com/customers(123) //OData compatibility

	// Get all rows from the "customers" table where the "country" field matches "Australia" (`LIKE`)
	GET http://api.example.com/customers/country/Australia/

	// Get 50 rows from the "customers" table
	GET http://api.example.com/customers/?limit=50

	# Get 50 rows from the "customers" table ordered by the "date" field
	GET http://api.example.com/customers/?limit=50&by=date&order=desc

	# Create a new row in the "customers" table where the POST data corresponds to the database fields
	POST http://api.example.com/customers/

	# Update customer "123" in the "customers" table where the PUT data corresponds to the database fields
	PUT http://api.example.com/customers/123/

	# Delete customer "123" from the "customers" table
	DELETE http://api.example.com/customers/123/
	
	Modifiers
	-------------------
	- extends : allow to get a tree of relation objects in one call
	- limit : specify max elements to return
	- order : specify witch order list must returned
	- by : (use with order) specify what field is used to order 
*/

/*
	Configure DB access
	Example MYSQL: mysql://user:pass@localhost/dbname/	
	
    SQLite: $dsn = 'sqlite://./path/to/database.sqlite';
    MySQL: $dsn = 'mysql://[user[:pass]@]host[:port]/db/;
    PostgreSQL: $dsn = 'pgsql://[user[:pass]@]host[:port]/db/;
    
    With bad configuration any operation returns:
    
    {
	    "error": 
	    {
	        "code": 503,
	        "status": "Service Unavailable"
	    }
	
	}
*/
$dsn = 'mysql://myUser:myPassword@localhost/myDBname/';

/*
	Allow only access from a list of clients (OPTIONAL)	
*/
$clients = [];

/*
	Define path where API is configured. (OPTIONAL, by default '')	
	For instance, if you have a 'api' folder in your website path you should access using this url: http://mydomain.com/api
	so you must configure $prefix using $prefix = '/api'; 
*/
$prefix = '/api'; 

/*
	Allows to connect from any origin. (OPTIONAL, by default true)
	By default is true
*/
$allowAnyOrigin=true;

/*
	Allows OPTIONS method (OPTIONAL, by default true)
*/
$enableOptionsRequest=true;

/*
	ALIASES (OPTIONAL)
	
	Define table aliases. An table alias can get different GET,POST,PUT and DELETE conditions and can be used in all following operations
	
	ArrestDBConfig::alias($alias,$table);		
*/
ArrestDBConfig::alias("CategoryVisible","Category");//This is an example, remove it

/*
	RELATIONS (OPTIONAL)
	
	Create relations for use extends in GET queries. This allow to get objects an related objects.
	
	Use:
	
	ArrestDBConfig::relation($table,$name,$config)
	
	- $table: the table name (equal to table name) witch contains relation
	- $name: the variable where relation is loaded
	
	To prepare a config you can use prepareRelationObject and prepareRelationList functions
	
	Objects (one to one, * to one), prepareRelationObject($foreignTable,$key,$foreingKey="id")
	
	- $foreignTable: Related table name (equal to table name)
	- $key: Table identifier key of relation
	- $foreingKey: Foreign table indentifier key of relation, by default "id"
	
	List (one to *), prepareRelationList($foreignTable,$foreingKey,$key="id")
	
	- $foreignTable: Related table name (equal to table name)
	- $foreingKey: Foreign table indentifier key of relation
	- $key: Table identifier key of relation, by default "id"
	
	Example
	------------
	
	- with objects: Each product has only one category
	ArrestDBConfig::relation("Product","Category",ArrestDBConfig::prepareRelationObject("Category","Category_id"));
	
	- with lists: Each category has a list of products.
	ArrestDBConfig::relation("Category","Products",ArrestDBConfig::prepareRelationList("Product","Category_id"));

*/
ArrestDBConfig::relation("Category","Products",ArrestDBConfig::prepareRelationList("Product","Category_id")); //This is an example, remove it

/*
	Folowing functions apply specificly to a table, alias or function, a list of tables, aliases, or functions using one REST method (GET, POST, PUT, DELETE, and GET_INTERNAL) or list of methods. To define this exists filters.
	
	A filter is an associative array with two keys: table and method. Table, to define a table, alias or function or list of tables, aliases or functions, and method, to define a method o list of tables. If you don't specify one of them it means all of them
	
	Examples
	-----------
	
	//Apply to table Category with all methods
	[
		"table"=>"Category"
	]
	
	//Apply to tables Category and User with all methods
	[
		"table"=>["Category","User"]
	]
	
	//Apply to tables Category and User with method GET
	[
		"table"=>"Category",
		"method"=>"GET"
	]
	
	//Apply for all tables with methods PUT and DELETE
	[
		"method"=>["PUT","DELETE"]
	]
	
	//Apply function SendMail()
	[
		"table"=>"SendMail()"
	]
	
	
	Methods
	------------
	
	- GET : Get one or all elements from api call
	- GET_INTERNAL : Get one element internally when uses extends system
	- POST : Create an element
	- PUT : Modify an element
	- DELETE : Delete an element
*/

/*
	RESPONSES
	--------------
	All responses are in the JSON format. A `GET` response from the `customers` table might look like this:

	[
	    {
	        "id": "114",
	        "customerName": "Australian Collectors, Co.",
	        "contactLastName": "Ferguson",
	        "contactFirstName": "Peter",
	        "phone": "123456",
	        "addressLine1": "636 St Kilda Road",
	        "addressLine2": "Level 3",
	        "city": "Melbourne",
	        "state": "Victoria",
	        "postalCode": "3004",
	        "country": "Australia",
	        "salesRepEmployeeNumber": "1611",
	        "creditLimit": "117300"
	    },
	]
	
	Successful `POST` responses will look like:
	
	{
	    "success": {
	        "code": 201,
	        "status": "Created"
	    }
	}
	
	Successful `PUT` and `DELETE` responses will look like:
	
	{
	    "success": {
	        "code": 200,
	        "status": "OK"
	    }
	}
	
	Errors are expressed in the format:
	
	{
	    "error": {
	        "code": 400,
	        "status": "Bad Request"
	    }
	}
	
	The following codes and message are avaiable:
	
	* `200` OK
	* `201` Created
	* `204` No Content
	* `400` Bad Request
	* `403` Forbidden
	* `404` Not Found
	* `409` Conflict
	* `503` Service Unavailable	
*/

/*
	Process
	--------------
		
		                                     _______
	                               __..--''''       `''''----...__
	                          _.--'                               `--.._
	                      _,-'                                          `--._
	                    -'                           +-----------+           '
	      +----+     +-----+      +------------+     |Get objects|       +-------+
	 .....|Auth|-----|Allow|------|Modify Query|-----|  from DB  |-------|Extends|
	      +----+     +-----+      +------------+     +-----------+       +-------+
	        |no         |no                                                  |finish
	        |           |                                                    |
	     .-----.     .-----.                                           +------------+
	     |error|     |error|                              result ------|Post process|
	     | 403 |     | 403 |                                           +------------+
	     `-----'     `-----'	


	1. First Query is checked by auth, if it's allowed continues, other ways returns error.
	2. Query is checked by allow (GET and GET_INTERNAL allready are different here).
	3. Query can be modified by ModifyQuery
	4. System get objects from DB using prepared Query
	5. System check if its necesary to extend information, If its the case renew the loop for all extended objects using GET_INTERNAL instead of GET
	6. PostProcess can filter and manipulate the information to return. 

*/

/*	
	AUTH (OPTIONAL)
	
	Check if authorization is required to access to a table or function. Return true if is authorized. By default all is authorized
	
	ArrestDBConfig::auth($filter,$function)
	- $filter: define the filter
	- $function: define callback function(returns true or false), function($method,$table,$id){return true}
	
	You can define a list of auth methods, when system match with one, all afther that are not checked.
	
	Returns a boolean. If it returns true, api continues execution, if it returns false, a Forbidden (403) is returned.
	
	Example
	------------
	- Query Category table is allways authorized
	- Other tables and operations are restringed and require HTTP authorization that is checked on DB User Table
*/
ArrestDBConfig::auth(
	[
		"table"=>"Category",
		"method"=>"GET"
	],
	function($method,$table,$id){
		return true;
	});

ArrestDBConfig::auth(
	[],
	function($method,$table,$id){
		global $user;
		
		if (!isset($_SERVER['PHP_AUTH_USER'])||!isset($_SERVER['PHP_AUTH_PW'])) {
		    header('WWW-Authenticate: Basic realm="My Realm"');
		    header('HTTP/1.0 401 Unauthorized');
		    echo 'Invalid Auth';
		    exit;
		} else {
			//Prepare params
		    $user=$_SERVER['PHP_AUTH_USER'];
		    $pass=sha1($_SERVER['PHP_AUTH_PW']);
	
			//Prepare query
			$query=ArrestDB::PrepareQueryGET([
			    "TABLE"=>"User",
			    "WHERE"=>["email='$user'","password='$pass'"]
			]);
	
			//Execute query
			$result=ArrestDB::Query($query);
	
			//Check if thereis one result
			if (count($result)==0){
				header('WWW-Authenticate: Basic realm="My Realm"');
			    header('HTTP/1.0 401 Unauthorized');
			    echo 'Invalid Auth';
			    exit;
			}
			
			//Set global user
			$user=$result[0];
			
			return true;
		}
	});
	

/*
	ALLOW (OPTIONAL)
	
	It's similar to AUTH but it's used when is checked out if it's allowed to execute a method over a table or function. Return true if is allowed. By default all is allowed
	
	ArrestDBConfig::allow($filter,$function)
	- $filter: define the filter
	- $function: define callback function(returns true or false), function($method,$table,$id){return true}
	
	If you define a list of allow methods, when system match with one, all afther that are not checked

	Example
	------------
	- UserInfo is only accesible by extends (GET_INTERNAL), and internal operations
	- Method delete is forbiden
*/
ArrestDBConfig::allow(
	[
		"table"=>"UserInfo",
		"method"=>["GET","POST","PUT","DELETE"]
	],
	function ($method,$table,$id){
		return false;
	});


ArrestDBConfig::allow(
	[
		"method"=>"DELETE"
	],
	function ($method,$table,$id){
		return false;
	});
	
	
/*
	MODIFY QUERY (OPTIONAL)
	
	Modify a query before execute for instance adding more conditions
	
	In GET, GET_INTERNAL methods you can modify
	- SELECT atributes (string)
	- WHERE conditions (array)
	- TABLE name
	- ORDER BY
	- LIMIT
	- OFFSET
	
	In POST and PUT methods you can modify
	- VALUES (array)
	
	function ($method,$table,$id,$query)
	- $method
	- $table
	- $id
	- $query: query values
	
	Example
	------------
	- Query only non deleted tables
	- Ofuscate password (with md5) when User is created
*/

ArrestDBConfig::modifyQuery(
	[
		"method"=>["GET","GET_INTERNAL"]
	],
	function ($method,$table,$id,$query){
		$query["WHERE"][]="deleted=0";
		return $query;
	});
	
ArrestDBConfig::modifyQuery(
	[
		"table"=>"User",
		"method"=>"POST"
	],
	function ($method,$table,$id,$query){
		$query["VALUES"]["password"]=md5($query["VALUES"]["password"]);
		$query["VALUES"]["createdByApi"]=1;
		return $query;
	});

	
/*
	POST PROCESS (Optional)
	
	It's called after an operation, and it allows to modify result or do any operation before send to caller
	
	function($method,$table,$id,$data)
	- $method
	- $table
	- $id: In POST case, $id is the id of created object.
	- $data: Data to return. Data can be an array or object (as array). See the following example to understand how to act in each case.

	Example
	------------
	- Remove password on User table in queries before return the data
	- Create a new UserInfo when User is created. Param Name is required
*/

//In this case remove password on User table before return the data
ArrestDBConfig::postProcess(
	[
		"table"=>"User",
		"method"=>["GET","GET_INTERNAL"],
	],
	function($method,$table,$id,$data){
		if (isset($data[0]))
			foreach ($data as $k=>$item)
				unset($item["password"]);
		else
			unset($data["password"]);
					
		return $data;
	});

//In this case when a new UserInfo is created when User is created
ArrestDBConfig::postProcess(
	[
		"method"=>"POST",
		"table"=>"User"
	],
	function($method,$table,$id,$data){
		if (isset($_GET["Name"])){
			$name=$_GET["Name"];
			ArrestDB::query("INSERT INTO UserInfo(Name,User_id) VALUES ({$name},{$id})");
		}
		
		return $data;
	});
	

/**
	CALL function (optional)
	
	Allows to call a function to do complex operations. All functions use POST method. Remember this when you'll call it.
	
	function ($func,$data)
	- $func: function name
	- $data: values in $_POST variable
	
	Example
	------------
	- version() api function returns string "Beta 1"
	- sendMsg() api function returns result of calling to method sendMsg
*/

ArrestDBConfig::fnc("version",
	function ($func,$data){
		return "Beta 1";
	});
ArrestDBConfig::fnc("sendMsg",
	function ($func,$data){
		return sendMsg($data);
	});
