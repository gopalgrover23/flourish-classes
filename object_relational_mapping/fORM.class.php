<?php
/**
 * Dynamically handles many centralized object-relational mapping tasks
 * 
 * @copyright  Copyright (c) 2007 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fORM
 * 
 * @uses  fCore
 * @uses  fORMSchema
 * @uses  fProgrammerException
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-08-04]
 */
class fORM
{
	/**
	 * Maps objects via their primary key
	 * 
	 * @var array 
	 */
	static private $identity_map = array();
	
	/**
	 * Custom mappings for table <-> class
	 * 
	 * @var array 
	 */
	static private $table_class_map = array();
	
	/**
	 * Custom record names for fActiveRecord classes
	 * 
	 * @var array 
	 */
	static private $record_names = array();
	
	/**
	 * Custom column names for columns in fActiveRecord classes
	 * 
	 * @var array 
	 */
	static private $column_names = array();
	
	/**
	 * An array of flags indicating if the features have been set for a class
	 * 
	 * @var array 
	 */
	static private $features_set = array();
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return fORM
	 */
	private function __construct() { }
	
	
	/**
	 * Will dynamically create an fActiveRecord-based class for a database table. Should be called from __autoload
	 * 
	 * @param  string $class_name   The name of the class to create
	 * @return void
	 */
	static public function createActiveRecordClass($class_name)
	{
		if (class_exists($class_name, FALSE)) {
			return;	
		}
		$tables = fORMSchema::getInstance()->getTables();
		$table_name = self::tablize($class_name);
		if (in_array($table_name, $tables)) {
			eval('class ' . $class_name . ' extends fActiveRecord { };');
			return;	
		}
		fCore::toss('fProgrammerException', 'The class specified does not correspond to a database table');
	}
	
	
	/**
	 * Checks to see if an object has been saved to the identity map
	 * 
	 * @param  mixed  $class             The name of the class, or an instance of it
	 * @param  array  $primary_key_data  The primary key(s) for the instance
	 * @return mixed  Will return FALSE if no match, or the instance of the object if a match occurs
	 */
	static public function checkIdentityMap($class, $primary_key_data)
	{
		$class = self::getClassName($class);
		if (!isset(self::$identity_map[$class])) {
			return FALSE;   
		}
		$hash_key = self::createPrimaryKeyHash($primary_key_data);
		if (!isset(self::$identity_map[$class][$hash_key])) {
			return FALSE;   
		}
		return self::$identity_map[$class][$hash_key];
	}
	
	
	/**
	 * Saves an object to the identity map
	 * 
	 * @param  mixed  $object            An instance of an fActiveRecord class
	 * @param  array  $primary_key_data  The primary key(s) for the instance
	 * @return void
	 */
	static public function saveToIdentityMap($object, $primary_key_data)
	{              
		$class = self::getClassName($object);
		if (!isset(self::$identity_map[$class])) {
			self::$identity_map[$class] = array();   
		}
		$hash_key = self::createPrimaryKeyHash($primary_key_data);
		self::$identity_map[$class][$hash_key] = $object;
	}
	
	
	/**
	 * Turns a primary key array into a hash key using md5
	 * 
	 * @param  array  $primary_key_data  The primary key data to hash
	 * @return string  An md5 of the sorted, serialized primary key data
	 */
	static private function createPrimaryKeyHash($primary_key_data)
	{
		sort($primary_key_data);
		foreach ($primary_key_data as $primary_key => $data) {
			$primary_key_data[$primary_key] = (string) $data;   
		}
		return md5(serialize($primary_key_data));
	}
	
	
	/**
	 * Allows overriding of default (humanize-d class name) record names
	 * 
	 * @param  mixed  $class        The name of the class, or an instance of it
	 * @param  string $record_name  The human version of the record
	 * @return void
	 */
	static public function overrideRecordName($class, $record_name)
	{
		self::$record_names[self::getClassName($class)] = $record_name;
	}
	
	
	/**
	 * Returns the record name for a class. The default record name is a humanize-d version of the class name.
	 * 
	 * @param  mixed $class   The class/class name to get the record name of
	 * @return string  The record name for the class specified
	 */
	static public function getRecordName($class)
	{
		$class = self::getClassName($class);
		if (!isset(self::$record_names[$class])) {
			self::$record_names[$class] = fInflection::humanize($class);	
		}
		return self::$record_names[$class];
	}
	
	
	/**
	 * Allows overriding of default (humanize-d column) column names
	 * 
	 * @param  mixed  $table        The table the column is located in, or an instance of the fActiveRecord class
	 * @param  string $column       The database column
	 * @param  string $column_name  The name for the column
	 * @return void
	 */
	static public function overrideColumnName($table, $column, $column_name)
	{
		if (is_object($table)) {
			$table = self::tablize($table);	
		}
		if (!isset(self::$column_names[$table])) {
			self::$column_names[$table] = array();	
		}
		self::$column_names[$table][$column] = $column_name;
	}
	

	/**
	 * Returns the column name. The default column name is a humanize-d version of the column.
	 * 
	 * @param  string $table   The table the column is located in
	 * @param  string $column  The database column
	 * @return string  The column name for the column specified
	 */
	static public function getColumnName($table, $column)
	{
		if (!isset(self::$column_names[$table])) {
			self::$column_names[$table] = array();	
		}
		if (!isset(self::$column_names[$table][$column])) {
			self::$column_names[$table][$column] = fInflection::humanize($column);	
		}
		return self::$column_names[$table][$column];
	}
	
	
	/**
	 * Allows non-standard (plural, underscore notation table name <-> singular, upper-camel case class name) table to class mapping
	 * 
	 * @param  string $table_name  The name of the database table
	 * @param  string $class_name  The name of the class
	 * @return void
	 */
	static public function addCustomTableClassMapping($table_name, $class_name)
	{
		self::$table_class_map[$table_name] = $class_name;
	} 
	
	
	/**
	 * Takes a class name (or class) and turns it into a table name. Uses custom mapping if set.
	 * 
	 * @param  mixed $class   The name of the class or the class to extract the name from
	 * @return string  The table name
	 */
	static public function tablize($class)
	{
		$class = self::getClassName($class);
		if (!$table_name = array_search($class, self::$table_class_map)) {
			$table_name = fInflection::underscorize(fInflection::pluralize($class));
			self::$table_class_map[$table_name] = $class;		
		}
		return $table_name;
	}
	
	
	/**
	 * Takes a table name and turns it into a class name. Uses custom mapping if set.
	 * 
	 * @param  string $table_name   The table name
	 * @return string  The class name
	 */
	static public function classize($table_name)
	{
		if (!isset(self::$table_class_map[$table_name])) {
			self::$table_class_map[$table_name] = fInflection::camelize(fInflection::singularize($table_name), TRUE);		
		}
		return self::$table_class_map[$table_name];
	}

	
	/**
	 * Sets a flag indicating features have been set for a class
	 *
	 * @param  mixed $class    The class/class name the features have been set for
	 * @return void 
	 */
	static public function flagFeaturesSet($class)
	{
		self::$features_set[self::getClassName($class)] = TRUE;		
	}
	
	
	/**
	 * Checks for the feature set flag on the specified class
	 *
	 * @param  mixed $class    The class/class name to check
	 * @return void 
	 */
	static public function checkFeaturesSet($class)
	{
		return !empty(self::$features_set[self::getClassName($class)]);		
	}
	
	
	/**
	 * Takes a class name or class and returns the class name
	 *
	 * @param  mixed $class    The class to get the name of
	 * @return string  The class name 
	 */
	static private function getClassName($class)
	{
		if (is_object($class)) { return get_class($class); }
		return $class;		
	}
}
		 
		 
/**
 * Copyright (c) 2007 William Bond <will@flourishlib.com>
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
?>