<?php 

class QueryBuilderBase{

    public $baseQuery;

    public $fields;

    public $object;

    public $conditions;

    public $orderBy;

    public $groupBy;



    public function __construct($objectName){

        $this->object = $objectName;

        $this->baseQuery = $query;
    }

    public function setFields($fields) {

        $this->fields = $fields;
    }


    public function buildConditions(){

        return self::_buildConditions($this->conditions);
    }


        // Build using recursion
    public static function _buildConditions($item) {

        $op = $item["op"];
        $name = $item["fieldname"];

    
        if($op == "AND" || $op == "OR"){
    
            return "(" . implode(" $op ", array_map("self::_buildConditions", $item["conditions"])) . ")";
        }

        if($item["value"] === False || !empty($item["value"])) {

            $value = $item["value"];

            $value = is_bool($value) ? ($value ? "True" : "False") : $value;
    
            $formattedValue = sprintf($item["syntax"], $value);
        }
    
        return "$name $op $formattedValue";
    }


    public function setQuery($query) {

        $this->baseQuery = $query;
    }

    public function setOrderBy($orderBy) {

        $this->orderBy = $orderBy;
    }

    public function setGroupBy($groupBy){

        $this->groupBy = $groupBy;
    }


    public function addCondition($condition) {

        if(is_array($condition)) $this->conditions["conditions"][] = $condition;

        if(is_string($condition)) {

            if(empty($this->conditions["extra"])){

                $this->conditions["extra"] = array();
            }

            $this->conditions["extra"][] = $condition;
        }
    }

    public function setConditions($fields, $values = null, $removeEmpty = True){

        $this->conditions = empty($values) ? $fields : self::mergeValues($fields, $values, $removeEmpty);
    }

    
    public function getConditions() {
        return $this->conditions;
    }


    public static function mergeValues($fields, $values = null, $removeEmpty = True) {

        if(is_null($values)) return $fields;

        $conditions = $fields["conditions"];

        if($removeEmpty){

            $filtered = array_filter($conditions, function($con) use ($values){

                $key = $con["fieldname"];

                $value = $values[$key];

                return ($value !== "" && $value !== null);
            });
            
        } else {

            $filtered = $conditions;
        }

        $merged = array_map(function($con) use ($values){

            $key = $con["fieldname"];
            $value = $values[$key];

            $con["value"] = $value;

            return $con;
        },$filtered);

        return array("op" => $fields["op"], "conditions" => $merged);
    }


    public function compile() {
        $cond = false;

        

        $sql = "SELECT " . implode(", ", $this->fields) . " FROM $this->object";
        
        if(!empty($this->conditions["conditions"])) {
            $cond = true;
            $sql .= " WHERE {$this->buildConditions()}";
        }

        if(!empty($this->conditions["extra"])) {
            $sql .= (!$cond ? " WHERE " : " AND ");
            $sql .= implode(" ", $this->conditions["extra"]);
        }
        
        if(!empty($this->groupBy)) $sql .= " GROUP BY " . $this->groupBy;
        
        if(!empty($this->orderBy)) $sql .= " ORDER BY $this->orderBy";

        return $sql;
    }

}



/* #region deprecate */


/* #endregion */