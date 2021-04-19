<?php
/**
 * @copyright (c) 2019, Intermesh BV http://www.intermesh.nl
 * @author Michael de Hart <mdhart@intermesh.nl>
 * @license http://www.gnu.org/licenses/agpl-3.0.html AGPLv3
 */

namespace go\modules\community\tasks\model;

use go\core\acl\model\AclOwnerEntity;
use go\core\db\Criteria;
use go\core\orm\Query;
use GO\Projects2\Model\ProjectEntity;

/**
 * Tasklist model
 */
class Tasklist extends AclOwnerEntity
{

	const List = 1;
	const Board = 2;
	const Project = 3;

	const Roles = [
		self::List => 'list',
		self::Board => 'board',
		self::Project => 'project'
	];

	/** @var int */
	public $id;

	/** @var string */
	public $name;

	/** @var string What kind of list: 'list', 'board' */
	protected $role;

	public function getRole() {
		return self::Roles[$this->role];
	}

	public function setRole($value) {
		$key = array_search($value, self::Roles, true);
		if($key === false) {
			$this->setValidationError('role', 10, 'Incorrect role value for tasklist');
		} else
			$this->role = $key;
	}

	/** @var string if a longer description then name s needed */
	public $description;

	/** @var int */
	public $createdBy;

	/** @var int */
	public $ownerId;

	/** @var int */
	public $aclId;

	/** @var int */
	public $version = 1;

	public $groups = [];

	protected static function defineMapping()
	{
		return parent::defineMapping()
			->addTable("tasks_tasklist", "tasklist")
			->addUserTable('tasks_tasklist_user', "ut", ['id' => 'tasklistId'])
			->addArray('groups', TasklistGroup::class, ['id' => 'tasklistId'], ['orderBy'=>'sortOrder']);
//			->addHasOne('project', ProjectEntity::class, ['id' => 'tasklist_id'] ); // TODO Query object?
	}

	protected static function defineFilters() {
		return parent::defineFilters()
			->add('projectId', function(Criteria $criteria, $value, Query $query, array $filter){
				if(!empty($value)) {
					$query->join('pr2_project_tasklist', 'prt', 'prt.tasklist_id = tasklist.id')
						->groupBy(['tasklist.id'])
						->where(['prt.project_id' => $value]);
				}
			});
	}

	protected function internalSave()
	{
		if ($this->isNew() && $this->role == self::Board) {
			if (empty($this->groups)) {

				$this->setValue('groups', [
					'#1' => ['name' => go()->t('Todo')],
					'#2' => ['name' => go()->t('Finished'), 'sortOrder' => 1, 'progressChange' => Progress::Completed]
				]);
			}
		}
		return parent::internalSave();
	}

}