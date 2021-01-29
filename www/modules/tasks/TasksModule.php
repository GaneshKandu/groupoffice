<?php

namespace GO\Tasks;

use go\core\db\Criteria;
use go\core\jmap\Entity;
use go\core\model\Link;
use go\core\model\User;
use go\core\orm\Filters;
use go\core\orm\Mapping;
use go\core\orm\Property;
use go\core\orm\Query;
use GO\Tasks\Model\Task;
use GO\Tasks\Model\Tasklist;

class TasksModule extends \GO\Base\Module {
	
	public function defineListeners() {

		User::on(Property::EVENT_MAPPING, static::class, 'onMap');
		Link::on(Entity::EVENT_FILTER, static::class, 'onLinkFilter');
		User::on(User::EVENT_BEFORE_SAVE, static::class, 'onUserBeforeSave');
	}
	
	public static function initListeners() {		
		\GO\Base\Model\User::model()->addListener('delete', "GO\Tasks\TasksModule", "deleteUser"); // TODO: remove and put relation in the database		
	}

	public static function onMap(Mapping $mapping) {
		$mapping->addHasOne('taskSettings', \GO\Tasks\Model\UserSettings::class, ['id' => 'user_id'], true);
	}

	public static function onLinkFilter(Filters $filters) {
		$filters->add('completedTasks', function(Criteria $criteria, $value, Query $query, array $filter){
			$query->join('ta_tasks', 'task', 'search.entityId = task.id');
			$criteria
				->where('search.entityTypeId', '=', Task::model()->modelTypeId())
				->andWhere('task.completion_time','>', 0);
		});

		$filters->add('incompleteTasks', function(Criteria $criteria, $value, Query $query, array $filter){
			$query->join('ta_tasks', 'task', 'search.entityId = task.id');
			$criteria
				->where('search.entityTypeId', '=', Task::model()->modelTypeId())
				->andWhere('task.completion_time','=', 0);
		});
		return true;
	}

	public static function onUserBeforeSave(User $user)
	{
		if (!$user->isNew() && $user->isModified('displayName')) {
			$nb = TaskList::model()->findByPk($user->taskSettings->default_tasklist_id);
			if ($nb) {
				$nb->name = $user->displayName;
				$nb->save();
			}
		}
	}
	
	public function autoInstall() {
		return true;
	}
	
	public static function submitSettings($settingsController, &$params, &$response, $user) {
		
		$settings = Model\Settings::model()->getDefault($user);		
		if($settings->remind = isset($params['remind'])) {
			$settings->reminder_days = $params['reminder_days'];
			$settings->reminder_time = $params['reminder_time'];
		}
		
		$settings->default_tasklist_id=$params['default_tasklist_id'];

		$settings->save();
		
		return parent::submitSettings($settingsController, $params, $response, $user);
	}
	
	public static function loadSettings($settingsController, &$params, &$response, $user) {
		
		$settings = Model\Settings::model()->getDefault($user);
		$response['data']=array_merge($response['data'], $settings->getAttributes());
		
		$tasklist = $settings->tasklist;
		
		if($tasklist) {
			$response['data']['default_tasklist_id']=$tasklist->id;
			$response['remoteComboTexts']['default_tasklist_id']=$tasklist->name;
		}
				
		//$response = Controller\Task::reminderSecondsToForm($response);
		
		return parent::loadSettings($settingsController, $params, $response, $user);
	}
	
	public static function deleteUser($user) {
		
		Model\PortletTasklist::model()->deleteByAttribute('user_id', $user->id);
	}
	
}
