<?php

namespace go\modules\community\calendar\model;

use go\core\orm\Mapping;
use go\core\orm\Property;

/**
 * Class Participant
 *
 * This is unused, originally written for the task module but we decided to take
 * a simpler approach for task participants.
 */
class Participant extends Property
{
	/* ParticipationStatus */
	const NeedsAction = 'needs-action'; // not responded
	const Tentative = 'tentative'; // Maybe
	const Accepted = 'accepted'; // Yes
	const Declined = 'declined'; // No
	const Delegated = 'delegated'; // Someone else is going in my place (not supported yet)

	/* Kinds */
	const Individual = 'individual';
	const Group = 'group';
	const Location = 'location';
	const Resource = 'resource';

	/* Roles */
	const ValidRoles = [
		'owner', // REQ-PARTICIPANT + ORGANIZER
		'attendee', // REQ-PARTICIPANT
		'optional', // OPT-PARTICIPANT
		'informational', // NON-PARTICIPANT
		'chair', // CHAIR
		'contact'
	];

	/** @var string display name of participant */
	public $name;

	/** @var string email address for the participant */
	public $email;

	/** @var string description with for example information about there role or how best to contact them. */
	public $description;

	/**
	* @var string[string] method => uri
	* method can either be 'imip' or 'other'
	* future methods may be specified
	* eg. ['imap'=>'mailto:michael@example.com']
	*/
	public $sendTo;

	/** @var string What kind of entity this participant is: 'individuel', 'group', 'location', 'resource */
	public $kind;

	/** @var string[bool] 'owner','attendee','optional','informational','chair','contact' */
	public $roles;

	/** @var string An id from the CalendarObject its `locations` array Where this participant is expected to be attending */
	//public $locationId;

	/** @var string language tag the best describes the participant's preferred language */
	//public $language;

	/** @var string 'needs-action', 'accepted', 'declined', 'tentative', 'delegated' */
	public $participationStatus = self::NeedsAction;

	/** @var string a note from the participant to explain there participation status */
	public $participationComment;

	/** @var bool true if organizer is expecting the participant to notify them of their participation status. */
	public $expectReply;

	/** @var string is the client, server or none responsible for sending imip invites */
	//public $scheduleAgent = 'server';

	/** @var uint The sequence number of the last response from the participant.  */
	public $scheduleSequence = 0;

	/** @var string[] A list of status codes, returned from the precessing of the most recent scheduling messages */
	//public $scheduleStatus = [];

	/** @var DateTime The timestamp for the most recent response from this participant. */
	public $scheduleUpdated;

	/** @var string The participant id of the participant who invited this one, if known */
	public $invitedBy;

	/** @var string[bool] participantIds A set of participant ids that this participant has delegated their participation to. */
	//public $delegatedTo;

	/** @var string[bool] participantIds A set of participant ids that this participant is acting as a delegate for */
	//public $delegatedFrom;

	/** @var string[bool] participantIds A set of group participants that were invited to this calendar
	object, which caused this participant to be invited due to their
	membership in the group(s). */
	//public $memberOf;

	protected static function defineMapping(): Mapping
	{
	  return parent::defineMapping()->addTable("calendar_participant", "participant");
	}

	public function getRoles() {
		$owner = $this->calendarId == $this->owner->calendarId;
		return [];
	}
}