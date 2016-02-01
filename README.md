# DBObject
Super simple database connector using php and mysqli extension for most every day  databsas use .

Requirements
------------
- php version >= 5.4
- mysqli extension 
- mysqlnd extension

Examples
------------
#### Configuration (**db.conf.php**):
The class uses singleton to maintain one instance connection per session, it loads the configuration  from **db.conf.php** where you can there define connections for your various databases:
```php
<?php
return [
    'master' => [
      'server' => 'localhost',
      'name' => 'db.name',
      'user' => 'db.user',
      'password' => 'db.password'
    ],
    'slave' => [
      'server' => 'localhost',
      'name' => 'db.name',
      'user' => 'db.user',
      'password' => 'db.password'
    ]
];
```
#### Initialization
**getInstance($confKey = 'master')** static function take the configuration key as the first argument (default is "master")
```php
require('DBObject.php');

$db = DBObject::getInstance();

//to initialize different database:
$dbSlave = DBObject::getInstance('slave');
```
#### getRows($sql,$params = null)
get all rows from the database - each row is associative array with the fields of the table as the keys:
```php
$db = DBObject::getInstance();

$sql = "SELECT 
        user_id,
        user_name
        FROM users ";

$rows = $db->getRows($sql);
foreach($rows as $row){
  echo($row['user_id']);
  echo($row['user_name']);
}
```
with parameters, every parameter should written in the query as "?" char 
and the value sent as the second argument of each function:
```php
$db = DBObject::getInstance();

$sql = "SELECT 
        user_id,
        user_name
        FROM users 
        WHERE user_name LIKE ? 
        AND user_id > ? ";

$rows = $db->getRows($sql,['%str%',10]);
//if there is only one parameter you can send it as value instead of array.
//$rows = $db->getRows($sql,10);
foreach($rows as $row){
  echo($row['user_id']);
  echo($row['user_name']);
}
```
#### getMap($sql,$fieldKey,$params = null)
get rows as a map that each key is the value of the $fieldKey parameter of the function for easy access:
```php
$db = DBObject::getInstance();

$sql = "SELECT 
        user_id,
        user_name
        FROM users 
        WHERE 
        user_name LIKE ? 
        AND user_id > ? ";
        
$map = $db->getMap($sql,'user_id',['%str%',10]);
foreach($map as $key=>$row){
  //$key == $row['user_id']
  echo($row['user_name']);
}
```
#### getArrayValues($sql,$params = null)
get the results set as an array of values, good for queries with only one field in the select section:
```php
$db = DBObject::getInstance();

$sql = "SELECT 
        user_id
        FROM users ";

$arr = $db->getArrayValues($sql);
foreach($arr as $user_id){
  echo($user_id);
}
```
#### getPaging($sql,$offset,$limit,$params = null)
get pagination data for the specific query given the offset and limit for the query
```php
$db = DBObject::getInstance();

$sql = "SELECT 
        user_id,
        user_name
        FROM users 
        WHERE 
        user_name LIKE ? 
        AND user_id > ? ";
        
$data = $db->getPaging($sql,0,10,['%str%',10]);
//data contains:
$data['count'] // the total count of the query 
$data['rows'] // the result set 
$data['last'] // boolean if this is the last group (good for the pagination calculation)
$data['page'] // the current numeric page of the group (good for the pagination calculation)
```
#### getRow($sql,$params = null) 
for signle row query (where user_id = 4)
#### getValue($sql,$params = null)
for single value query
```php
$db = DBObject::getInstance();

$sql = "SELECT COUNT(*) FROM  users";

$count = $db->getValue($sql);
```
#### execute($sql,$params = null)
execute CRUD queries:
```php
$db = DBObject::getInstance();

$sql = "INSERT INTO users(user_name,user_email)
        VALUES(?,?)";

//$result contain true for success, false for failure        
$result = $db->execute($sql,['adidi','adidi@adidi.com']);
//get the auto increment user_id from mysql
$userId = $db->getInsertId();
```
#### getError()
get last mysqli error
#### getNumAffectedRows()
get the number of acctected rows after CRUD queries
#### getInsertId()
get the last mysql insert auto incremented id after insert query
#### setTimeZoneOffset($mins)
set timezone for the current query sessions - every timestamp field will be affected by that timezone
in the final results set - good for localization
**$mins** is the number difference in minutes (this is usually what you get from client side) - you can easily convert it to hours of course when needed.
```php
$db = DBObject::getInstance();
$db->setTimeZoneOffset(120);

$sql = "SELECT user_name,last_logged_in FROM users WHERE  user_id = ? ";
$row = $db->getRow($sql,10);

//giving that last_logged_in is type timestamp, the value of $row['last_logged_im'] will be +02:00 from UTC timezone.
```
