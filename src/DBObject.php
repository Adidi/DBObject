<?php


class DBObject{

  //singleton:
  private static $instances = [];
  private static $conf = null;

  private $sqli = null;
  private $sql = null;

  private $isStatement = false;
  private $stmtResult = null;
  private $stmtAffectedRows = null;

  /**
   * get configuration data for connecting to db
   * @return mixed
   */
  private static function getConf(){
    if(!self::$conf){
      self::$conf = require('db.conf.php');
    }

    return self::$conf;
  }

  /**
   * singleton - get class instance via conf key
   * @param string $confKey
   * @return mixed
   * @throws Exception
   */
  public static function getInstance($confKey = 'master'){
    $conf = self::getConf();

    if(!isset($conf[$confKey])){
      throw new \Exception('There is no such key:' . $confKey);
    }

    if(!isset(self::$instances[$confKey])) {
      self::$instances[$confKey] = new self($conf[$confKey]);
    }

    return self::$instances[$confKey];
  }

  private function __construct($confDB){

    $this->sqli = new \mysqli($confDB['server'],
                              $confDB['user'],
                              $confDB['password'],
                              $confDB['name']);


    $this->sqli->set_charset('utf8');

    //always use the default timezone of utc
    //and if we want to change it for speicific query we will
    //call it before that query individually
    $this->setTimeZoneOffset(0);
  }

  /**
   * set timezone for the current query sessions - every timestamp field will be affected by that timezone
   * in the final results set
   * @param $mins - minutes of the time difference (usually comes from client side in minutes)
   */
  public function setTimeZoneOffset($mins){
    $sgn = ($mins < 0 ? 1 : -1); //convert the sign opposite (it's comes from the client usually!)
    $mins = abs($mins);
    $hrs = floor($mins / 60);
    $mins -= $hrs * 60;
    $offset = sprintf('%+d:%02d', $hrs*$sgn, $mins);

    $sql = "SET time_zone ='$offset';";
    $result = $this->execute($sql);
  }

  /**
   * get param and resurns the native mysqli bind param sign:
   * i - integer
   * d - double
   * s - string
   * b - blob
   *
   * @param mixed $param
   * @return null|string
   */
  private function getBindParam($param){
    $bind_param = null;
    if(is_int($param)){
      $bind_param = 'i';
    }
    else if(is_double($param)){
      $bind_param = 'd';
    }
    else if(is_string($param)){
      $bind_param = 's';
    }
    else{
      $bind_param = 'b';
    }

    return $bind_param;
  }

  /**
   * main function that gets the sql and params
   * the escape is native by the db(mysql)
   *
   * @param mixed $sql
   * @param mixed $params
   * @return mysqli_result
   */
  private function setStatement($sql,$params){
    $this->isStatement = true;

    $this->stmtResult = $this->stmtAffectedRows = null;

    $stmt = $this->sqli->prepare($sql);

    //check if params is single value so make it an array
    if(gettype($params) !== 'array'){
      $params = [$params];
    }

    //since php 5.3 bind_param array must be by ref!!
    $refs = [];
    $bind_str = '';
    foreach($params as $key => $value){
      $bind_str .= $this->getBindParam($value);
      $refs[$key] = &$params[$key];
    }

    //add bind str to begining of the array:
    array_unshift($refs,$bind_str);
    call_user_func_array([$stmt,'bind_param'],$refs);

    $this->stmtResult = $stmt->execute();
    $this->stmtAffectedRows = $stmt->affected_rows;

    //works only on mysqlnd
    $result = $stmt->get_result();
    $stmt->close();

    return $result;
  }

  /**
   * set query with no params
   * @param $sql
   * @return mysqli_result
   */
  private function setQuery($sql){
    $this->isStatement = false;
    return $this->sqli->query($sql);
  }

  /**
   * get mysqli result according to the sql and params
   * if there are params use statement otherwise use regular mysqli result
   * @param $sql
   * @param null $params
   * @return mysqli_result
   */
  private function getResult($sql,$params = null){
    $this->sql = $sql;
    if($params){
      $result = $this->setStatement($sql,$params);
    }
    else{
      $result = $this->setQuery($sql);
    }

    return $result;
  }

  /**
   * get rows according to the sql given
   * @param $sql
   * @param null $params
   * @return array
   */
  public function getRows($sql,$params = null){
    $result = $this->getResult($sql,$params);
    $rows = [];
    while ($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }

    $result->close();

    return $rows;
  }

  /**
   * get map according to the sql given, the keys of the map will be the values
   * for the $fieldKey given
   * @param $sql
   * @param $fieldKey
   * @param null $params
   * @return array
   */
  public function getMap($sql,$fieldKey,$params = null){
    $result = $this->getResult($sql,$params);
    $map = [];
    while ($row = $result->fetch_assoc()) {
      $map[$row[$fieldKey]] = $row;
    }
    $result->close();
    return $map;
  }

  /**
   * get array values - the result will take the first field in the query
   * and return the values of it as an array
   * @param $sql
   * @param null $params
   * @return array
   */
  public function getArrayValues($sql,$params = null){
    $result = $this->getResult($sql,$params);
    $arr = [];
    while ($row = $result->fetch_row()) {
      $arr[] = $row[0];
    }

    $result->close();

    return $arr;
  }

  public function getPaging($sql,$offset,$limit,$params = null){

    $sqlCount = preg_replace('/^\s*SELECT.+FROM(.*)$/is','SELECT COUNT(*) FROM $1', $sql);

    $count = $this->getValue($sqlCount,$params);

    $sql .= " LIMIT $offset, $limit ";

    $rows = $this->getRows($sql,$params);

    //check last result
    $page = (int)(ceil($offset/$limit) + 1);

    $last = false;
    if($page*$limit >= $count){
      $last = true;
    }

    return [
      'count' => $count,
      'rows' => $rows,
      'last' => $last,
      'page' => $page
    ];
  }

  /**
   * get single row per query
   * @param $sql
   * @param null $params
   * @return array
   */
  public function getRow($sql,$params = null){
    $result = $this->getResult($sql,$params);
    $row = $result->fetch_assoc();
    $result->close();
    return $row;
  }

  /**
   * get single value per query
   * @param $sql
   * @param null $params
   * @return null
   */
  public function getValue($sql,$params = null){
    $result = $this->getResult($sql,$params);
    $row = $result->fetch_row();
    $value = null;
    if($row){
      $value = $row[0];
    }
    $result->close();

    return $value;
  }

  /**
   * execute crud queries
   * @param $sql
   * @param null $params
   * @return bool
   */
  public function execute($sql,$params = null){
    $this->getResult($sql,$params);
    //in execute query return the affected rows as true or false
    //cause in update/delete the result always true!
    $result = $this->getNumAffectedRows() > 0 ? true : false;
    return $result;
  }

  /**
   * get last mysqli error
   * @return string
   */
  public function getError(){
    return $this->sqli->error;
  }

  /**
   * @return int|null 0: no records updated, -1: error
   */
  public function getNumAffectedRows(){
    return $this->isStatement ? $this->stmtAffectedRows : $this->sqli->affected_rows;
  }

  /**
   * get last insert id
   * @return mixed
   */
  public function getInsertId(){
    return $this->sqli->insert_id;
  }


  public function __destruct(){
    $this->close();
  }

  /**
   * close the connection
   */
  public function close(){
    $this->sqli->close();
  }
}