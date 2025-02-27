<?php

namespace go\core;

use Exception;
use go\core\data\Model;
use go\core\db\Query;
use go\core\exception\Forbidden;

/**
 * Settings model 
 * 
 * Any module can implement getSettings() and return a model that extends this
 * abstract class to store settings. All properties are automatically saved and
 * loaded from the "core_setting" table.
 * 
 * @see Module::getSettings()
 */
abstract class Settings extends Model {

  private static $instance = [];

	/**
	 * 
	 * @return static
	 * @noinspection PhpMissingReturnTypeInspection
	 */
	public static function get()
	{
    $cls = static::class;

	  if(!isset(self::$instance[$cls])) {
      $instance = static::dbIsReady() ? go()->getCache()->get($cls) : null;
      if ($instance) {
        self::$instance[$cls] = $instance;
        return $instance;
      }

      $instance = new static;

		  if(static::dbIsReady()) {
			  go()->getCache()->set($cls, $instance);
		  }
      self::$instance[$cls] = $instance;
    }

		return self::$instance[$cls];
	}

	public static function flushCache() {
		self::$instance = [];
	}

	/**
	 * @throws Exception
	 */
	protected function getModuleId() : int{
		$moduleId = (new Query)
			->selectSingleValue('id')
			->from('core_module')
			->where([
					'name' => $this->getModuleName(), 
					'package' => $this->getModulePackageName()])
			->execute()
			->fetch();
		
		if(!$moduleId) {
			throw new Exception ("Could not find module " .  $this->getModuleName() . "/" . $this->getModulePackageName());
		}
		
		return $moduleId;
	}

	/**
	 * Get module name
	 *
	 * @return string
	 */
	protected function getModuleName(): string
	{
		return explode("\\", static::class)[3];
	}

	/**
	 * Get the module package name
	 * Is nullable for backwards compatibility with old framework
	 * @return string|null
	 */
	protected function getModulePackageName(): ?string
	{
		return explode("\\", static::class)[2];
	}
	
	private $oldData;


	private static function dbIsReady(): bool
	{
		$ready = go()->getCache()->get('has_table_core_setting');
		if($ready) {
			return true;
		}

		try {
			$ready = go()->getDatabase()->hasTable('core_setting');
			if($ready) {
				go()->getCache()->set('has_table_core_setting', true);
			}
			return $ready;
		}catch(Exception $e) {
			go()->debug($e);
		}

		return false;
	}

	/**
	 * Constructor
	 *
	 * @throws Exception
	 */
	protected function __construct() {

		$props = array_keys($this->getSettingProperties());	
		
		$record = array_filter($this->loadFromConfigFile(), function($key) use ($props) { return in_array($key, $props);}, ARRAY_FILTER_USE_KEY);
		$this->readOnlyKeys = array_keys($record);
		
		$this->setValues($record);


		if(static::dbIsReady()) {
			$selectProps = array_diff($props, $this->readOnlyKeys);

			if (!empty($selectProps)) {
				$stmt = (new Query)
					->select('name, value')
					->from('core_setting')
					->where([
						'moduleId' => $this->getModuleId(),
						'name' => $selectProps
					])
					->execute();

				while ($record = $stmt->fetch()) {
					$this->{$record['name']} = $record['value'];
				}
			}

		}
		
		$this->oldData = (array) $this;
	}
	
	private function loadFromConfigFile() {
		$config = go()->getConfig();
		
		$pkgName = $this->getModulePackageName();
		
		
		if(!isset($config[$pkgName])) {
			return [];
		}
		
		if($pkgName == "core") {
			$c = $config[$pkgName];
		} else
		{
			$modName = $this->getModuleName();

			if(!isset($config[$pkgName][$modName])) {
				return [];
			}
			$c = $config[$pkgName][$modName];
		}
		
		return $c;		
	}
	
	
	private $readOnlyKeys;

	/** @noinspection PhpUnused */
	public function getReadOnlyKeys(): array
	{
		return $this->readOnlyKeys;
	}

	private function getSettingProperties(): array
	{
		return array_filter(get_object_vars($this), function($key) {
			return $key !== 'oldData' && $key !== 'readOnlyKeys';
		}, ARRAY_FILTER_USE_KEY);
	}

	protected function isModified($name): bool
	{
		return (!array_key_exists($name, $this->oldData) && isset($this->$name)) || (isset($this->$name) && $this->$name != $this->oldData[$name]);
	}

	/**
	 * @throws Forbidden
	 * @throws Exception
	 */
	public function save(): bool
	{
		foreach($this->getSettingProperties() as $name => $value) {
			if(!array_key_exists($name, $this->oldData) || $value != $this->oldData[$name]) {
				if(in_array($name, $this->readOnlyKeys)) {
					throw new Forbidden(static::class . ':' . $name . " can't be changed because it's defined in the configuration file on the server.");
				}
				
				$this->update($name, $value);
			}
		}

		$this->oldData = (array) $this;

		go()->getCache()->set(static::class, $this);
		
		return true;
	}

	/**
	 * @throws Exception
	 */
	private function update(string $name, $value) {
		
		$moduleId = $this->getModuleId();

		if(!$moduleId) {
			throw new Exception("Could not find module for settings model ". static::class);
		}
		
		if (!App::get()->getDbConnection()->replace('core_setting', [
								'moduleId' => $moduleId,
								'name' => $name,
								'value' => $value
						])->execute()) {
			throw new Exception("Failed to set setting!");
		}
	}
}
