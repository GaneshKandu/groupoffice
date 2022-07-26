<?php
/*
 * @copyright (c) 2016, Intermesh BV http://www.intermesh.nl
 * @author Michael de Hart <mdhart@intermesh.nl>
 * @license http://www.gnu.org/licenses/agpl-3.0.html AGPLv3
 */

namespace go\modules\community\calendar\model;

use go\core\acl\model\AclItemEntity;
use go\core\orm\CustomFieldsTrait;
use go\core\orm\Mapping;
use go\core\orm\SearchableTrait;
use go\core\util\UUID;
use go\core\util\DateTime;

/**
 * This serves as an in between record for the event that is in a calendar.
 * It merged Attendee with Event
 * @property Event $event Event object for this calendar event
 * @property Calendar $calendar The calendar this is in
 */
class CalendarEvent extends AclItemEntity {

	use CustomFieldsTrait;
	use SearchableTrait;

	/* Status */
	const Confirmed = 'confirmed'; // default
	const Cancelled = 'cancelled';
	const Tentative = 'tentative';

	/* Privacy */
	const Public = 'public';
	const Private = 'private';
	const Secret = 'secret';
	// Properties shown to others when 'privacy' is set to private
	const PrivateProperties = ['created', 'due', 'duration', 'estimatedDuration', 'freeBusyStatus', 'privacy',
		'recurrenceOverrides', 'sequence', 'showWithoutTime', 'start', 'timeZone', 'timeZones', 'uid','updated'];

	const EventProperties = ['uid','prodId', 'sequence','title','description','locale', 'showWithoutTime', 'start','timeZone','duration','priority','privacy','status', 'recurrenceRule'];

	const UserProperties = ['keywords', 'color', 'freeBusyStatus', 'useDefaultAlerts', 'alerts', 'calendarId'];
	const IgnoredPropertiesInException = ['uid', 'organizerEmail', 'allDay','recurrence', 'links'];

	public $calendarId;
	public $groupId;
	public $eventId;

	public $responseStatus;
	public $role;
	public $email;


	/**
	 * A unique identifier for the object.
	 * @var string
	 */
	public $uid;

	/**
	 * This is an revision number that is increased by 1 every time the organizer
	 * makes a significant change
	 * @var int
	 */
	public $sequence = 0;

	/**
	 * Time is ignored for this event when true
	 * @var bool
	 */
	public $allDay = false;

	/**
	 * The start time of the event
	 * @var DateTime
	 */
	protected $startAt;

	/**
	 * The duration of the event (or the occurence)
	 * (optional, default: PT0S)
	 * @var \DateInterval
	 */
	protected $duration;

	/**
	 * The title
	 * @var string
	 */
	protected $title;

	/**
	 * free text that would describe the event
	 * @var string
	 */
	public $description;

	/**
	 * The location where the event takes place
	 * @var string
	 */
	public $location;

	/**
	 * Status of event (confirmed, canceled, tentative)
	 * @var int
	 */
	public $status = self::Confirmed;

	/**
	 * auto tagging to give the event some flair. See Resource folder
	 * @var string[bool]
	 */
	public $keywords;

	/**
	 * Public, Private, Secret
	 * @var int
	 */
	public $visibility = self::Public;

	/**
	 * Is event Transparent or Opaque
	 * @var boolean
	 */
	public $busy = true;

	/**
	 * The exception object that is applied to this instance
	 * @var DateTime
	 */
	public $recurrenceId = null;

	protected static function defineMapping(): Mapping {
		return (new Mapping(static::class))
			->addTable('calendar_event', "eventdata", null, self::EventProperties)
			->addUserTable('calendar_participant', 'eventuser', ['id' => 'userId'],self::UserProperties)
			->addHasOne('recurrenceRule', RecurrenceRule::class, ['id' => 'eventId'])
			->addMap('participants', Participant::class, ['id' => 'eventId'])
			->addMap('alerts', Alert::class, ['id' => 'eventId']);
			//->addMap('locations', Location::class, ['id' => 'eventId']);
	}

//	public static function find($query = null) {
//		if($query instanceof Query) {
//			$query->andWhere('calendarId IS NOT NULL');
//			$query->joinRelation('event', 't.*, event.allDay, event.startAt, event.endAt, event.location, event.title', 'LEFT');
////			$query->select('t.*, event.allDay, event.startAt, event.endAt, event.location, event.title')
////					->join(Event::tableName(), 'event', 't.eventId = event.id', 'LEFT');
//		}
//		return parent::find($query);
//	}

	static public function findByUID($uid) {
		return self::find(['uid'=>$uid])->single();
	}
	
	static public function findRecurring(DateTime $start, DateTime $end, $query = null) {
		if($query === null) {
			$query = new Query();
		}
		$query->joinRelation('recurrenceRule')->andWhere('frequency IS NOT NULL');
		$events = self::find($query);
		$allOccurrences = [];
		foreach($events as $calEvent) {
			$rule = $calEvent->recurrenceRule;
			$rule->forAttendee($calEvent);
			$allOccurrences += $rule->getOccurences($start, $end);
		}
		return $allOccurrences;
	}

	public function getTitle() {
		return $this->title;
	}

	/**
	 * Set the tag property when the title contains a certain word
	 * @todo function is not called when the attributes of events are set relational
	 * @param string $value Title of event
	 */
	public function setTitle($value) {
		$tags = require(dirname(__FILE__) . '/../Resources/tags/nl.php'); //<-- @todo: use users language
		$this->tag = null;
		foreach($tags as $tag => $possibleMatches) {
			foreach($possibleMatches as $possibleMatch) {
				if (stripos($value, $possibleMatch) !== false) {
					$this->tag = $tag;
					break 2;
				}
			}
		}
		$this->title = $value;
	}

	public function getRecurrenceId() {
		return !empty($this->instance) ? $this->instance->recurrenceId : null;
	}



	public function getStartAt() {
		if(!empty($this->instance)) {
			return $this->instance->startAt;
		}
		return $this->startAt;
	}

	public function getEndAt() {
		if(!empty($this->endAt)) {
			if(is_string($this->endAt)) {
				$this->endAt = new DateTime($this->endAt);
			}
			return $this->endAt;
		}
		$endAt = clone $this->getStartAt();
		$endAt->add($this->duration);
		return $endAt;
	}

	/**
	 * @todo analyze rowCount performance
	 * @return bool
	 */
	public function hasAlarms() {
		return $this->alarms->getRowCount() > 0;
	}

	public function getIsOrganizer() {
		return $this->email === $this->organizerEmail;
	}

	/**
	 * This function can only be called on a recurring series. After that the object
	 * represents an instance of the series. This instance is loaded
	 * @param Datetime $recurrenceId
	 */
	public function addRecurrenceId(DateTime $recurrenceId, $view = false) {
		
		$instance = EventInstance::find(['eventId'=>$this->event->id, 'recurrenceId'=>$recurrenceId])->single();
		if(empty($instance)) { // new override (should not save if not changed)
			$instance = $this->createInstance($recurrenceId);
		} elseif($view && $instance->isPatched()) {
			$this->event = $instance->patch();
		}
		$this->instance = $instance;
		return $this->instance;
	}

//	public function setInstance(EventInstance $instance) {
//		$this->instance = $instance;
//	}

	public function addAlarms($alarms) {
		$alarms = (array)$alarms;

		foreach($alarms as $alarm) {
			$alarm->addTo($this);
		}
	}

	protected function internalSave() : bool {

		if(empty($this->uid)) {
			$this->uid = UUID::v4();
		}

		if($this->isRecurring()) {

			if(isset($this->instance) && !$this->fromHere)
			{
				$this->instance->applyPatch($this);
				return $this->instance->save();

			} else if(!$this->isFirstInSeries()) {
				return $this->saveNewSeries();
			}
			return parent::internalSave();
		} else {
			return parent::internalSave();
		}

		 // save none recurring or complete series
	}

	public function saveFromHere() {
		$this->fromHere = true;
		$success = $this->save();
		$this->fromHere = false;
		return $success;
	}
	
	protected function internaDelete($hard) {
		if($this->isRecurring()) {
			if(isset($this->instance) && !$this->fromHere) {
				$this->instance->applyException(); // EXDATE
				return $this->instance->save();
			}
			else if(!$this->isFirstInSeries()) {
				$this->recurrenceRule->stopBefore($this->getRecurrenceId());
				return $this->recurrenceRule->save();
			}
		}

		//return parent::internaDelete($hard); // delete none recurring or complete series
	}

	public function deleteFromHere() {
		$this->fromHere = true;
		$success = $this->delete();
		$this->fromHere = false;
		return $success;
	}

	protected function isFirstInSeries() {
		if($this->isNew() || empty($this->instance)) {
			return true;
		}
		$startAt = $this->isModified('startAt') ? $this->getOldValue('startAt') : $this->startAt;
		return ($this->instance->recurrenceId == $startAt);
	}

	/**
	 * Set the until time of this recurrence rule and create a new event
	 * with the same recurring rule starting from The occurrence start time
	 * @todo: if startAt in new series changes the moved exceptions need the same diff
	 */
	private function saveNewSeries() {
		$calendar = Calendar::findById($this->calendarId);
		$newSeries = $calendar->newEvent();
		$newSeries->eventId = null;
		$newSeries->event = $this->event->cloneMe();
		$newSeries->event->uid = UUID::v4();
		$newSeries->event->setStartAt($this->instance->recurrenceId);
		$newSeries->event->setEndAt($this->instance->getEndAt());
		$rrule = $this->recurrenceRule->toArray();
		$newSeries->event->setValues(['recurrenceRule'=>$rrule]);
		$success = $newSeries->save();

		// Reattach instances to new series
		foreach($this->instances as $instance) {
			if($instance->recurrenceId < $this->getRecurrenceId()) {
				continue;
			}
			$instance->eventId = $newSeries->id;
			$success = $success && $instance->save();
		}

		return $success && $this->deleteFromHere();

	}

	/**
	 * When the recurrenceId of an event was set it represents a single instance
	 * of a recurring event. When editing we create an exception
	 * @return bool
	 */
	public function isInstance() {
		return !empty($this->recurrenceId);
	}

	/**
	 * Returns true when more attendees then just yourself
	 * @return boolean
	 */
	public function hasAttendees() {
		$count = 0;
		foreach($this->attendees as $attendee) {
			$count++;
			if($count > 1) // more then just yourself
				return true;
		}
		return false;
	}

	public function isRecurring() {
		return (!empty($this->recurrenceRule)); // && !empty($this->recurrenceRule->frequency));
	}



	protected static function aclEntityClass(): string
	{
		return Calendar::class;
	}

	protected static function aclEntityKeys(): array
	{
		return ['calendarId' => 'id'];
	}

	protected function getSearchDescription(): string
	{
		$calendar = Calendar::findById($this->calendarId, ['name'], true);

		return $calendar->name .': '. $this->startAt . ' - '  . $this->endAt;
	}
}