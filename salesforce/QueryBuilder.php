<?php

namespace Salesforce;

class QueryBuilder extends \QueryBuilderBase{

    function __construct($objectName){

        $this->object = $objectName;

    }

    
}