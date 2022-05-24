<?php

namespace Salesforce;

define("SQL_EMPTY", "");
define("SQL_SPACE", " ");
define("SQL_AND", "AND");
define("SQL_OR", "OR");
define("SQL_WHERE", "WHERE");
define("SQL_SELECT", "SELECT");
define("SQL_FROM", "FROM");
define("SQL_LIMIT", "LIMIT");




class QueryBuilder extends \QueryBuilderBase{

    function __construct($objectName){

        $this->object = $objectName;
    }

    
    // NOTE: We eventually need to solve for unary operators like
    // IS NULL, IS NOT NULL and Functions. 
    public static function objectToSqlCondition($obj) {   
        
        list($field,$op,$value) = [$obj->field, $obj->op, $obj->value];
        $format = null;

        if(is_bool($value)) { // Rewrite bools.
            $format = $value ? "true" : "false";
        } else if(is_integer($value)) { // No quotes for numbers.
            $format = "%s";
        } else { 
            $format = "'%s'";
        }
            
        $sql = sprintf($format,$value);
        return implode(SQL_EMPTY,[$field,$op,$sql]);
    }


    public static function toWhere($conditions) {

        $tmp = array_map("self::objectToSqlCondition",$conditions);
        return SQL_WHERE . SQL_SPACE . implode(SQL_SPACE . SQL_AND . SQL_SPACE, $tmp);
    }

}