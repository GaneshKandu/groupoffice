<?php
/**
 * @copyright (c) 2019, Intermesh BV http://www.intermesh.nl
 * @author Merijn Schering <mschering@intermesh.nl>
 * @license http://www.gnu.org/licenses/agpl-3.0.html AGPLv3
 */
namespace go\modules\community\tasks\model;
						
use go\core\db\Criteria;
use go\core\jmap\Entity;

/**
 * Category model
 */
class Category extends Entity {
	
	/** @var int */
	public $id;

	/** @var string */
	public $name;

	/** @var int could be NULL for global categories */
	public $ownerId;

	protected static function defineMapping() {
		return parent::defineMapping()
			->addTable("tasks_category", "category");
	}

	public function internalValidate()
	{
		if($this->isNew() && !$this->isModified('ownerId')) {
			$this->ownerId = go()->getUserId();
		}
		if ($this->ownerId !== go()->getUserId() && !\go\core\model\Module::findByName('community', 'tasks')->hasPermissionLevel(50))
			$this->setValidationError('ownerId', go()->t("You need manage permission to create global categories"));
		return parent::internalValidate();
	}

	public static function getClientName() {
		return "TaskCategory";
	}

	protected static function textFilterColumns() {
		return ['name'];
	}

	protected static function defineFilters()
	{
		return parent::defineFilters()
			->add('ownerId', function(Criteria $criteria, $value) {
				$criteria->where('ownerId', '=', $value)->orWhere('ownerId', 'IS', null);
			});
	}

}