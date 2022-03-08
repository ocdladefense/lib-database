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

}