<?php

namespace Mysql;

use \DbException;


class Database {

    private $connection;
    private $host;
    private $user;
    private $password;
    private $name;

    function __construct($credentials = null){

        if(!empty($credentials)){

            $this->host = $credentials["host"];
            $this->user = $credentials["user"];
            $this->password = $credentials["password"];
            $this->name = $credentials["name"];

        } else {

            $this->host = defined("DB_HOST") ? DB_HOST : null;
            $this->user = defined("DB_USER") ? DB_USER : null;
            $this->password = defined("DB_PASS") ? DB_PASS : null;
            $this->name = defined("DB_NAME") ? DB_NAME : null;
        }

        $this->connect();
    }



    function connect(){

        $this->connection = new \Mysqli($this->host, $this->user, $this->password, $this->name);

        if ($this->connection->connect_error) die("Connection failed: " . $this->connection->connect_error);
    }

    function insert($sql){

        $result = $this->connection->query($sql);
        if($result !== true) throw new DbException("Error inserting data.  " . $this->connection->error);

        $count = mysqli_affected_rows($this->connection);
        if($count == 0) throw new DbException("There were ". $count . " rows inserted.");

        $id = mysqli_insert_id($this->connection);
        if($id === null || $id == 0 || $id == "") throw new DbException("The given id cannot be null or equal to 0 or an empty string");

        return new DbInsertResult($result,$id,$count,$this->connection->error);
    }

    function update($sql){

        $result = $this->connection->query($sql);
        if($result !== true) throw new DbException("Error updating data.  " . $this->connection->error);

        return new DbUpdateResult($result,$count,$this->connection->error);
    }
    
    public function delete($sql){

        $result = $this->connection->query($sql);
        if($result !== true) throw new DbException("Error deleting data.  " . $this->connection->error);

        $count = mysqli_affected_rows($this->connection);
        if($count == 0) throw new DbException("There were ". $count . " rows deleted.");

        return new DbDeleteResult($result,$count,$this->connection->error);
    }

    function select($sql){

        $result = $this->connection->query($sql);
        if(!$result) throw new DbException($this->connection->error);

        return new DbSelectResult($result);
    }
    
    public static function query($sql, $type = "select", $credentials){

        $db = new Database($credentials);

        switch($type) {
            case "select":
                return $db->select($sql);
                break;
            case "insert":
                return $db->insert($sql);
                break;
            case "update":
                return $db->update($sql);
                break;
            case "delete":
                return $db->delete($sql);
                break;
        }      
    }
    
    function close(){
        $this->connection->close();
    }
    
    public static function getSelectList($field, $table) {
            $dbResults = MysqlDatabase::query("SELECT DISTINCT {$field} FROM {$table} ORDER BY {$field}");
            $parsedResults = array();
            foreach($dbResults as $result) {
                    $parsedResults[] = $result[$field];
            }
            return $parsedResults;
    }
}

// THESE GLOBAL FUNCTIONS ARE OUTSIDE OF THE DATABASE CLASS!


function select($query, $credentials = null) {

    $customObjects = array();

    $isPrimaryKey = false;

	$tokens = explode(" ", $query);

    // Probably shouldn't filter out double spaces in where clauses.
	$sql = implode(" ", array_filter($tokens));
    $primaryKey = "id";

    // The sql string needs to be lowercase, so that we can access indexes in the $parts array and perform other operations.(ex. get the table name);
    // It has nothing to do with the actual query being passed to the Database::query() method.  T
	$sqlCopy = strtolower($sql);
	
	
	$parts = array("select" => null,"from" => null,"where" => null, "group by" => null, "order by" => null,"limit" => null);
	$parts = array_reverse($parts, true);
	

	foreach($parts as $sqlkey => &$value) {
		
		$keywords = explode($sqlkey,$sqlCopy);
		$hasIt = count($keywords) > 1;
		
		$value = $hasIt ? trim($keywords[1]) : null;
		$sqlCopy = $keywords[0];
	}

    if(!empty($parts["where"])){
        
        $where = $parts["where"];

        $whereParts = explode("=", $where);

        $whereParts = array_map(function($part){
            return trim($part);
        }, $whereParts);

        // Is the query filtering on the primary key?
        $isPrimaryKey = in_array($primaryKey, $whereParts);
    }


    $isLimit1 = $parts["limit"] == 1;
	
    // Needs to be title case.
	$table = ucwords($parts["from"]);

    $result = Database::query($query, "select", $credentials);

    $records = $result->getIterator();

    if(!class_exists($table)) {

        return ($isPrimaryKey || $isLimit1) ? $records[0] : $records;
    }


    foreach($records as $record){

        $customObjects[] = $table::from_array_or_standard_object($record);
    }

    return ($isPrimaryKey || $isLimit1) ? $customObjects[0] : $customObjects;
}





//Global insert function that calls the insert method of the MysqlDatabase class.
function insert($objs = array(), $isSalesforce = false){

    $objs = !is_array($objs) ? [$objs] : $objs;

    $invalid = array_filter($objs, function($obj){return $obj->id !== null;});

    if(count($invalid) > 0){
        throw new DbException("Object Id must be null");
    }

    if($isSalesforce){

        $force = new Salesforce();
		return $force->createRecords($sObjectName, $records);
    }
		
    $sample = $objs[0];

    $columns = getObjectFields($sample);

    $values = getObjectValues($objs);
    
    $tableName = strtolower(get_class($objs[0]));

    //use the querybuilder to build insert statement
    $builder = new QueryBuilder($tableName);
    $builder->setType("insert");
    $builder->setTable($tableName);
    $builder->setColumns($columns);
    $builder->setValues($values);
    $sql = $builder->compile();


    $db = new Database();
    $insertResult = $db->insert($sql);
    $counter = 0;

    //give each insertResult an id to save the status of the insert for each object and save it in the application state. 
    foreach($insertResult as $autoId){
        $objs[$counter++]->id = $autoId;

    }

    return $insertResult;
   
}


// Needs work.
function update($objs = array()){

    $objs = !is_array($objs) ? [$objs] : $objs;

    $tableName = strtolower(get_class($objs[0]));

    $columns = getObjectFields($objs[0], True);

    // Remove the Id column
    unset($columns[0]);

    $rows = getObjectValues($objs, True);

    $sqlStatements = array();
    foreach($rows as $row){

        $id = $row["id"];

        unset($row["id"]);

        $builder = new QueryBuilder($tableName);
        $builder->setType("update");
        $builder->setTable($tableName);
        $builder->setColumns($columns);
        $builder->setValues(array($row));
        $sql = $builder->compile();
    
        $sql .= " WHERE id = '$id'"; 

        $sqlStatements[] = $sql;
    }

    $results = array();
    $db = new Database();
    foreach($sqlStatements as $sql) {

        $results[] = $db->update($sql);
    }

    return $results;
}

function getObjectFields($obj, $isUpdate = False){

    if($obj === null){

        throw new DbException("Given object cannot be null");
    }

    $fields = get_object_vars($obj);

    unset($fields["meta"]);

    if($isUpdate == True){

        $fields = array_filter($fields, function($field){

            return $field != null && $field != "";
        });
    }

    return array_keys($fields);
}

function getObjectValues($obj, $isUpdate = False){

    $values = array_map("get_object_vars",$obj);

    if($isUpdate == True){

        $filtered = array();
        foreach($values as $value){
    
            $filtered[] = array_filter($value, function($v){
    
                return $v != null && $v != "";
            });
        }
    
        return $filtered;
    }

    return $values;
}