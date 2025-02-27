<?php
namespace go\core\db;

use go\core\App;
use go\core\data\Model;
use InvalidArgumentException;
use PDO;

/**
 * Class that fetches database column information for the ActiveRecord.
 * It detects the length, type, default and required attribute etc.
 *
 * @copyright (c) 2014, Intermesh BV http://www.intermesh.nl
 * @author Merijn Schering <mschering@intermesh.nl>
 * @license http://www.gnu.org/licenses/agpl-3.0.html AGPLv3
 */
class Table {

	private static $cache = [];

	private $name;
	protected $columns;
	protected $indexes;

	private $pk = [];

	/**
	 * @var Connection
	 */
	protected $conn;

	protected $dsn;

	/**
	 * Get a table instance
	 *
	 * @param string $name
	 * @param Connection|null $conn
	 * @return self
	 */
	public static function getInstance(string $name, Connection $conn = null): Table
	{
		
		if(!isset($conn)) {
			$conn = go()->getDbConnection();
		}

		$cacheKey = $conn->getDsn() . '-' . $name;
		if(!isset(self::$cache[$cacheKey])) {
			self::$cache[$cacheKey] = new Table($name, $conn);
		}
		
		return self::$cache[$cacheKey];	
	}

	public static function destroyInstance($name, Connection $conn = null) {
		if(!isset($conn)) {
			$conn = go()->getDbConnection();
		}

		$cacheKey = $conn->getDsn() . '-' . $name;
		if(isset(self::$cache[$cacheKey])) {
			self::$cache[$cacheKey]->clearCache();
			unset(self::$cache[$cacheKey]);
		}

		App::get()->getCache()->delete('dbColumns_' . $name);
		
	}
	
	public static function destroyInstances() {
		foreach(self::$cache as $i) {
			$i->clearCache();
		}
		self::$cache = [];
	}


	/**
	 * @throws InvalidArgumentException
	 */
	public function __construct(string $name, Connection $conn) {
		$this->name = $name;
		$this->conn = $conn;
		$this->dsn = $conn->getDsn();
		$this->init();

	}	
	
	/**
	 * Gets the name of the table
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	private function getCacheKey(): string
	{
		return 'dbColumns_' . $this->dsn . '_' . $this->name;
	}

	/**
	 * Clear the columns cache
	 */
	private function clearCache() {
		go()->getCache()->delete($this->getCacheKey());
	}

	private function init() {
		
		if (isset($this->columns)) {
			return;
		}
		
		$cacheKey = $this->getCacheKey();

		if (($cache = App::get()->getCache()->get($cacheKey))) {
			$this->columns = $cache['columns'];
			$this->pk = $cache['pk'];
			$this->indexes = $cache['indexes'] ?? null;
			$this->conn = null;
			return;
		}	
		
		$this->columns = [];

		$sql = "SHOW FULL COLUMNS FROM `" . $this->name . "`;";
		
		$stmt = $this->conn->query($sql);
		while ($field = $stmt->fetch()) {
			$this->columns[$field['Field']] = $this->createColumn($field);
		}

		$this->processIndexes($this->name);

		$this->conn = null;

		App::get()->getCache()->set($cacheKey, ['columns' => $this->columns, 'pk' => $this->pk, 'indexes' => $this->indexes]);

	}
	
	/**
	 * A column name may not have the name of a Record property name.
	 * 
	 * @param string $fieldName
	 * @throws InvalidArgumentException
	 */
	private function checkReservedName(string $fieldName) {
		if(strpos($fieldName, '@') !== false) {
			throw new InvalidArgumentException("The @ char is reserved for framework usage.");
		}
		
		if(property_exists(Model::class, $fieldName)) {
			throw new InvalidArgumentException("The name '$fieldName' is reserved. Please choose another column name.");
		}
	}

	private function createColumn($field): Column
	{
		
		$this->checkReservedName($field['Field']);
		
		if($field['Default'] == "NULL") {
			$field['Default'] = null;
		}
			
		$c = new Column();
		$c->table = $this;
		$c->name = $field['Field'];
		$c->pdoType = PDO::PARAM_STR;
		$c->required = false;
		$c->default = $field['Default'];
		$c->comment = $field['Comment'];
		$c->nullAllowed = strtoupper($field['Null']) == 'YES';
		$c->autoIncrement = strpos($field['Extra'], 'auto_increment') !== false;
		$c->trimInput = false;
		$c->dataType = strtoupper($field['Type']);

		//remove "unsigned" or any other extra info that might be there.
		$field['Type'] = explode(" ", $field['Type'])[0];

		preg_match('/(.*)\(([1-9].*)\)/', $field['Type'], $matches);
		if ($matches) {
			$c->length  = intval($matches[2]);
			$c->dbType = strtolower($matches[1]);			
		} else {
			$c->dbType = strtolower(preg_replace("/\(.*\)$/", "", $field['Type']));
			$c->length = null;
		}
		
		if($c->default == 'CURRENT_TIMESTAMP') {
			throw new InvalidArgumentException("Please don't use CURRENT_TIMESTAMP as default mysql value. It's only supported in MySQL 5.6+");
		}
		
		switch ($c->dbType) {
			case 'int':
			case 'tinyint':
			case 'smallint':
			case 'bigint':
				if ($c->length == 1 && $c->dbType == 'tinyint') {
					//$c->pdoType = PDO::PARAM_BOOL; MySQL native doesn't understand PARAM_BOOL. Doesn't work with ATTR_EMULATE_PREPARES = false.
					$c->pdoType = PDO::PARAM_INT;
					$c->default = !isset($field['Default']) ? null : (bool) $c->default;
				} else {
					$c->pdoType = PDO::PARAM_INT;
					$c->default = $c->autoIncrement || !isset($field['Default']) ? null : intval($c->default);
				}

				break;

			case 'float':
			case 'double':
			case 'decimal':
				$c->pdoType = PDO::PARAM_STR;
				$c->length = 0;
				$c->default = $c->default == null ? null : floatval($c->default);
				break;
				
			case 'varbinary':
			case 'binary':
				$c->pdoType = PDO::PARAM_LOB;
				break;
			
			case 'text':
				$c->length = 65535;
				$c->trimInput = true;
				break;
			case 'longtext':
				$c->length = 4294967295;
				$c->trimInput = true;
				break;
			case 'mediumtext':
				$c->length = 16777215;
				$c->trimInput = true;
				break;

			case 'tinytext':
				$c->length = 255;
				$c->trimInput = true;
				break;
			
			default:				
				$c->trimInput = true;
				break;			
		}

		$c->required = is_null($c->default) && $field['Null'] == 'NO' && strpos($field['Extra'], 'auto_increment') === false;

		if ($field['Field'] == 'createdAt' || $field['Field'] == 'modifiedAt' || $field['Field'] == 'createdBy' || $field['Field'] == 'modifiedBy') {
			//don't validate because they will be set by the Record
			$c->required = false;
		}

		return $c;
	}

	private function processIndexes($tableName) {
		$query = "SHOW INDEXES FROM `" . $tableName . "`";

		$unique = [];

		//group keys;
		// ['keyName' => ['col1', 'col2']];

		$stmt = $this->conn->query($query);
		while ($index = $stmt->fetch()) {

			$this->indexes[strtolower($index['Key_name'])] = $index;

			if ($index['Key_name'] === 'PRIMARY') {

				$this->columns[$index['Column_name']]->primary = true;
				$this->pk[] = $index['Column_name'];
				//don't validate uniqueness on primary key
				continue;
			}

			if ($index['Non_unique'] == 0) {
				if (!isset($unique[$index['Key_name']])) {
					$unique[$index['Key_name']] = [];
				}

				$unique[$index['Key_name']][] = $index['Column_name'];
			}
		}

		foreach ($unique as $cols) {
			foreach ($cols as $colName) {
				$this->columns[$colName]->unique = $cols;
			}
		}
	}


	/**
	 * Get index information by name
	 * 
	 * @link https://dev.mysql.com/doc/refman/8.0/en/show-index.html
	 * @return array|null
	 * @param string $name
	 */
	public function getIndex($name) : ?array {
		return $this->indexes[strtolower($name)] ?? null;
	}

	/**
	 * Check if table has an index by the given name
	 *
	 * @param string $name
	 * @return bool
	 */
	public function hasIndex(string $name): bool
	{
		return isset($this->indexes[strtolower($name)]);
	}


	
	/**
	 * Get all column names
	 * 
	 * @return string[]
	 */
	public function getColumnNames(): array
	{
		return array_keys($this->getColumns());
	}
	
	
	/**
	 * Check if column exists
	 * 
	 * @param string $name
	 * @return boolean
	 */
	public function hasColumn(string $name): bool
	{
		return isset($this->columns[$name]);
	}
	
	/**
	 * Get a column
	 * 
	 * @param string $name
	 * @return Column
	 */
	public function getColumn(string $name): ?Column
	{
		if(!isset($this->columns[$name])) {
			return null;
		}
		
		return $this->columns[$name];
	}
	
	/**
	 * Get the columns of the table
	 * 
	 * The keys of the array are the column names.
	 * 
	 * 
	 * @return Column[]
	 */
	public function getColumns(): array
	{
		return $this->columns;
	}	
	
	/**
	 * Get the auto incrementing column
	 * 
	 * @return Column|boolean
	 */
	public function getAutoIncrementColumn() {
		foreach($this->getColumns() as $col) {
			if($col->autoIncrement) {
				return $col;
			}
		}
		
		return false;
	}
	
	/**
	 * The primary key columns
	 * 
	 * This value is auto detected from the database. 
	 *
	 * @return string[] eg. ['id']
	 */
	public function getPrimaryKey(): array
	{
		return $this->pk;
	}

	// Only works from php 7.4 and up
//	public function __serialize()
//	{
//		if($this->conn != go()->getDbConnection()) {
//			throw new Exception("Can't serialize tables with custom database connection");
//		}
//
//		return [
//			'name' => $this->name,
//			'columns' => $this->columns,
//			'indexes' => $this->indexes,
//			'pk' => $this->pk
//		];
//
//	}
//
//	public function __unserialize($data)
//	{
//		$this->conn = go()->getDbConnection();
//
//		$this->name = $data['name'];
//		$this->columns = $data['columns'];
//		$this->indexes = $data['indexes'];
//		$this->pk = $data['pk'];
//
//	}

}
