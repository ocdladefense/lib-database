<?php

namespace Mysql;


class DbSelectResult extends DbResult implements \IDbResult, \IteratorAggregate {


    private $result;
    
    
    private $rows = [];
    
    
    private $done = false;


    public function __construct($mysqliResult){
        $this->result = $mysqliResult;
    }


    private function read() {
        if($this->result->num_rows > 0){
            while($row = $this->result->fetch_assoc()){
                $this->rows[] = $row;
            }
        }

        $this->done = true;
    }



    public function getIterator() {
        if(!$this->done) {
            $this->read();
        }

        return new \ArrayObject($this->rows);
    }

    
    public function getValues($fieldName) {
        if(!$this->done) {
            $this->read();
        }
        return array_map(function($row) use($fieldName){
            return $row[$fieldName];
        }, $this->rows);
    }

	
    public function each($func) {
    
        $arr = array();
        foreach($this as $result) {
            $arr[] = $func($result);
        }
        
        return $arr;
    }

    public function hasError(){}
    public function getError(){}
}

