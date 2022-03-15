<?php

namespace Mysql;

class DbHelper {

    public static function getDistinctFieldValues($tableName, $field) {

		$result = Database::query("SELECT DISTINCT $field FROM $tableName ORDER BY $field");

		$records = $result->getIterator();

		$values = array();

		foreach($records as $record) {

			$values []= trim($record[$field]);

		}

		return $values;
	}





    public static function parse($query) {

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

            $parts["where"] = $whereParts;

            // Is the query filtering on the primary key?
            $isPrimaryKey = in_array($primaryKey, $whereParts);
        }

        return $parts;

        /*
        $isLimit1 = $parts["limit"] == 1;
        
        // Needs to be title case.
        $table = ucwords($parts["from"]);

        return ($isPrimaryKey || $isLimit1) ? $customObjects[0] : $customObjects;
        */
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

}