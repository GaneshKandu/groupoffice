<?php
namespace go\core\data\convert;

use Exception;
use go\core\ErrorHandler;
use go\core\fs\Blob;
use go\core\fs\File;
use go\core\jmap\EntityController;
use go\core\orm\Entity;
use go\core\orm\EntityType;
use go\core\orm\Query;

/**
 * Abstract converter class
 * 
 * Used for converting entities into other formats.
 * 
 * Converters must be put in a "convert" folder / namespace to work with the
 * \go\core\jmap\EntityController::export() function
 * 
 * 
 * @example Client javascript
 * 
 * ```
 * onExport: function () {
 * 		
 * 		var win = window.open("about:blank");
 * 		
 * 		var promise = go.Jmap.request({
 * 			method: "Contact/query",
 * 			params: Ext.apply(this.grid.store.baseParams, this.grid.store.lastOptions.params, {limit: 0, start: 0}),
 * 			callback: function (options, success, response) {
 * 			}
 * 		});
 * 		
 * 		go.Jmap.request({
 * 			method: "Contact/export",
 * 			params: {
 * 				converter: "JSON",
 * 				"#ids": {
 * 					resultOf: promise.callId,
 * 					path: "/ids"
 * 				}
 * 			},
 * 			callback: function (options, success, response) {
 * 				win.location = go.Jmap.downloadUrl(response.blobId);
 * 			}
 * 		});
 * 	}
 * ```
 * 
 * 
 * @see EntityController::export()
 */
abstract class AbstractConverter {




	/**
	 * The index number of the import
	 *
	 * @var int
	 */
	protected $index;

	/**
	 * Extra parameters sent by the client for importing
	 *
	 * @var array
	 */
	protected $clientParams;

	/**
	 * The class name of the entity we're importing
	 * @var class-string<Entity>
	 */
	protected $entityClass;

	/**
	 * The extension provided by the client.
	 *
	 * @var string
	 */
	protected $extension;

	/**
	 * AbstractConverter constructor.
	 * @param string $extension eg. "csv"
	 * @param string $entityClass The entity class model. eg. go\modules\community\addressbook\model\Contact
	 */
	public function __construct(string $extension, string $entityClass) {
		$this->extension = strtolower($extension);
		$this->entityClass = $entityClass;
		$this->init();
	}
	
	protected function init() {
		
	}

	/**
	 * Return list of supported file extensions in lower case!
	 * eg. ['csv'];
	 *
	 * @return string[]
	 */
	abstract public static function supportedExtensions(): array;

	/**
	 * Check if this converter supports the given extension
	 * @param string $extension eg. "csv"
	 * @return bool
	 */
	public static function supportsExtension(string $extension): bool
	{
		return in_array(strtolower($extension), static::supportedExtensions());
	}
	
	/**
	 * The name of the convertor
	 * 
	 * @return string eg, JSON or CSV
	 */
	public function getName(): string
	{
		$classParts = explode("\\", static::class);
		return array_pop($classParts);
	}
	
	/**
	 * Get the file name extention
	 * 
	 * @return string eg. "csv"
	 */
	public function getFileExtension(): string
	{
		return $this->extension;
	}


  /**
   * Read file and import them into Group-Office
   *
   * @param File $file the source file
   * @param array $params Extra import parameters. By default this can only hold 'values' which is a key value array that will be set on each model.
   * @return array ['count', 'errors', 'success']
   * @throws Exception
   */
	public final function importFile(File $file, array $params = array()): array
	{
		$response = ['count' => 0, 'errors' => [], 'success' => true];

		$this->clientParams = $params;

		$this->initImport($file);

		$this->index = 0;
		
		while($this->nextImportRecord()) {

			try {

				$entity = $this->importEntity();
				
				//ignore when false is returned. This is not an error. But intentional. Like CSV skipping a blank line for example.
				if($entity === false) {
					$this->index++;
					continue;
				}			

				$entity->save();

				//push changes after each user
				EntityType::push();

				if($entity->hasValidationErrors()) {
					$response['errors'][] = "Item ". $this->index . ": ". var_export($entity->getValidationErrors(), true);
				} elseif($this->afterSave($entity)) {
					$response['count']++;
				} else{
					$response['errors'][] = "Item ". $this->index . ": Import afterSave returned false";
				}				
			}
			catch(Exception $e) {
				ErrorHandler::logException($e);
				$response['errors'][] = "Item ". $this->index . ": ".$e->getMessage();
			}

			$this->index++;
		}

		$this->finishImport();
		
		return $response;
	}

	/**
	 * Setup file reader
	 *
	 * @param File $file
	 */
	abstract protected function initImport(File $file): void;

	/**
	 * Reads next record from file. Returns true on success or false when done.
	 *
	 * @return bool
	 */
	abstract protected function nextImportRecord(): bool;

	protected function finishImport(): void
	{

	}


	/**
	 * Handle's the import. 
	 * 
	 * It must read from the $fp file pointer and return the entity object. The entity is not saved yet.
	 * 
	 * When false is returned the result will be ignored. For example when you want to skip a CSV line because it's empty.
	 *
	 * @return Entity|false
	 */
	abstract protected function importEntity();


	/** start of export */


	/**
	 * Export entities to a blob
	 *
	 * @param Query|array $entities
	 * @param array $params
	 * @return Blob
	 */
	public final function exportToBlob(Query $entities, array $params = []): Blob
	{

		$this->clientParams = $params;
		$this->entitiesQuery = $entities;
		$this->initExport();
		//	$total = $entities->getIterator()->rowCount();

		$this->index = 0;
		foreach($entities as $entity) {
			$this->exportEntity($entity);
			$this->index++;
		}

		return $this->finishExport();

	}

  /**
   * @var Query
   */
  private $entitiesQuery;

  /**
   * The query used for exporting entities
   *
   * @return Query
   */
	protected function getEntitiesQuery(): Query
	{
	  return $this->entitiesQuery;
  }

	/**
	 * Initialize the import. For example create temporary file and open it.
	 *
	 * @return void
	 */
	abstract protected function initExport(): void;

	protected function afterSave(Entity $entity): bool
	{
		return true;
	}

	/**
	 * Export the given entity
	 *
	 * @param Entity $entity
	 * @return void
	 */
	abstract protected function exportEntity(Entity $entity): void;

	/**
	 * Finish the export retuning a Blob with the data
	 *
	 * @return Blob
	 */
	abstract protected function finishExport(): Blob;

}