<?php
namespace go\modules\community\addressbook\model;

use Exception;
use go\core\acl\model\AclItemEntity;
use go\core\db\Column;
use go\core\db\Criteria;
use go\core\db\Query as Query2;
use go\core\orm\CustomFieldsTrait;
use go\core\orm\Query;
use go\core\orm\SearchableTrait;
use go\core\util\DateTime;
use go\core\validate\ErrorCode;
use go\modules\community\addressbook\convert\VCard;
use go\core\model\Link;
use function GO;
						
/**
 * Contact model
 *
 * @copyright (c) 2018, Intermesh BV http://www.intermesh.nl
 * @author Merijn Schering <mschering@intermesh.nl>
 * @license http://www.gnu.org/licenses/agpl-3.0.html AGPLv3
 */

class Contact extends AclItemEntity {
	
	use CustomFieldsTrait;
	
	use SearchableTrait;
	
	/**
	 * 
	 * @var int
	 */							
	public $id;

	/**
	 * 
	 * @var int
	 */							
	public $addressBookId;
	
	/**
	 * If this contact belongs to a user then this is set to the user ID.
	 * 
	 * @var int 
	 */
	public $goUserId;

	/**
	 * 
	 * @var int
	 */							
	public $createdBy;
	
	/**
	 *
	 * @var int 
	 */
	public $modifiedBy;

	/**
	 * 
	 * @var \IFW\Util\DateTime
	 */							
	public $createdAt;

	/**
	 * 
	 * @var \IFW\Util\DateTime
	 */							
	public $modifiedAt;

	/**
	 * Prefixes like 'Sir'
	 * @var string
	 */							
	public $prefixes = '';

	/**
	 * 
	 * @var string
	 */							
	public $firstName = '';

	/**
	 * 
	 * @var string
	 */							
	public $middleName = '';

	/**
	 * 
	 * @var string
	 */							
	public $lastName = '';

	/**
	 * Suffixes like 'Msc.'
	 * @var string
	 */							
	public $suffixes = '';

	/**
	 * M for Male, F for Female or null for unknown
	 * @var string
	 */							
	public $gender;

	/**
	 * 
	 * @var string
	 */							
	public $notes;

	/**
	 * 
	 * @var bool
	 */							
	public $isOrganization = false;
	
	/**
	 * The job title
	 * 
	 * @var string 
	 */
	public $jobTitle;

	/**
	 * name field for companies and contacts. It should be the display name of first, middle and last name
	 * @var string
	 */							
	public $name;

	/**
	 * 
	 * @var string
	 */							
	public $IBAN = '';

	/**
	 * Company trade registration number
	 * @var string
	 */							
	public $registrationNumber = '';

	/**
	 * 
	 * @var string
	 */							
	public $vatNo;
	
	/**
	 * Don't charge VAT in sender country
	 * 
	 * @var boolean
	 */							
	public $vatReverseCharge = false;

	/**
	 * 
	 * @var string
	 */							
	public $debtorNumber;

	/**
	 * 
	 * @var string
	 */							
	public $photoBlobId;

	/**
	 * 
	 * @var string
	 */							
	public $language;
	
	/**
	 *
	 * @var int
	 */
	public $filesFolderId;
	
	/**
	 *
	 * @var EmailAddress[]
	 */
	public $emailAddresses = [];
	
	/**
	 *
	 * @var PhoneNumber[]
	 */
	public $phoneNumbers = [];
	
	/**
	 *
	 * @var Date[];
	 */
	public $dates = [];
	
	/**
	 *
	 * @var Url[]
	 */
	public $urls = [];	
	
	/**
	 *
	 * @var ContactOrganization[]
	 */
	public $employees = [];
	
	
	/**
	 *
	 * @var Address[]
	 */
	public $addresses = [];	
	
	/**
	 *
	 * @var ContactGroup[] 
	 */
	public $groups = [];
	
	
	/**
	 * Starred by the current user or not.
	 * 
	 * @var boolean
	 */
	public $starred = false;
	
	
	/**
	 * Universal unique identifier.
	 * 
	 * Either set by sync clients or generated by group-office "<id>@<hostname>"
	 * 
	 * @var string 
	 */
	public $uid;
	
	/**
	 * Blob ID of the last generated vcard
	 * 
	 * @var string 
	 */
	public $vcardBlobId;	
	
	/**
	 * CardDAV uri for the contact
	 * 
	 * @var string
	 */
	public $uri;
	
	
	protected static function aclEntityClass(): string {
		return AddressBook::class;
	}

	protected static function aclEntityKeys(): array {
		return ['addressBookId' => 'id'];
	}
	
	protected static function defineMapping() {
		return parent::defineMapping()
						->addTable("addressbook_contact", 'c')
						->addUserTable("addressbook_contact_star", "s", ['id' => 'contactId'])
						->addRelation('dates', Date::class, ['id' => 'contactId'])
						->addRelation('phoneNumbers', PhoneNumber::class, ['id' => 'contactId'])
						->addRelation('emailAddresses', EmailAddress::class, ['id' => 'contactId'])
						->addRelation('addresses', Address::class, ['id' => 'contactId'])
						->addRelation('urls', Url::class, ['id' => 'contactId'])
						->addRelation('groups', ContactGroup::class, ['id' => 'contactId']);
						
	}
	
	public function setNameFromParts() {
		$this->name = $this->firstName;
		if(!empty($this->middleName)) {
			$this->name .= " ".$this->middleName;
		}
		if(!empty($this->lastName)) {
			$this->name .= " ".$this->lastName;
		}
		
		$this->name = trim($this->name);
	}
	
	/**
	 * Find contact for user ID.
	 * 
	 * A contact can optionally be connected to a user. It's not guaranteed that
	 * the contact is present.
	 * 
	 * @param int $userId
	 * @return static
	 */
	public static function findForUser($userId) {
		if(empty($userId)) {
			return false;
		}
		return static::find()->where('goUserId', '=', $userId)->single();
	}
	
	/**
	 * Find contact by e-mail address
	 * 
	 * @param string $email
	 * @return Query
	 */
	public static function findByEmail($email) {
		return static::find()
						->join("addressbook_email_address", "e", "e.contactId = c.id")
						->groupBy(['c.id'])
						->where('e.email', '=', $email);
	}
	
	
	/**
	 * Find contact by e-mail address
	 * 
	 * @param string $email
	 * @return Query
	 */
	public static function findByPhone($email) {
		return static::find()
						->join("addressbook_phone_number", "e", "e.contactId = c.id")
						->groupBy(['c.id'])
						->where('e.email', '=', $email);
	}
	
	protected static function defineFilters() {

		return parent::defineFilters()
										->add("addressBookId", function(Criteria $criteria, $value) {
											$criteria->andWhere('addressBookId', '=', $value);
										})
										->add("groupId", function(Criteria $criteria, $value, Query $query) {
											$query->join('addressbook_contact_group', 'g', 'g.contactId = c.id');
											
											$criteria->andWhere('g.groupId', '=', $value);
										})
										->add("isOrganization", function(Criteria $criteria, $value) {
											$criteria->andWhere('isOrganization', '=', $value);
										})
										->add("hasEmailAddresses", function(Criteria $criteria, $value, Query $query) {
											$query->join('addressbook_email_address', 'e', 'e.contactId = c.id', "LEFT")
											->groupBy(['c.id'])
											->having('count(e.id) '.($value ? '>' : '=').' 0');
										})
										->addText("email", function(Criteria $criteria, $comparator, $value, Query $query) {
											$query->join('addressbook_email_address', 'e', 'e.contactId = c.id', "INNER");
											
											$criteria->where('e.email', $comparator, $value);
										})
										->addText("name", function(Criteria $criteria, $comparator, $value) {											
											$criteria->where('name', $comparator, $value);
										})
										->addText("country", function(Criteria $criteria, $comparator, $value, Query $query) {
											if(!$query->isJoined('addressbook_address')) {
												$query->join('addressbook_address', 'adr', 'adr.contactId = c.id', "INNER");
											}
											
											$criteria->where('adr.country', $comparator, $value);
										})
										->addText("city", function(Criteria $criteria, $comparator, $value, Query $query) {
											if(!$query->isJoined('addressbook_address')) {
												$query->join('addressbook_address', 'adr', 'adr.contactId = c.id', "INNER");
											}
											
											$criteria->where('adr.city', $comparator, $value);
										})
										->addNumber("age", function(Criteria $criteria, $comparator, $value, Query $query) {
											
											if(!$query->isJoined('addressbook_date')) {
												$query->join('addressbook_date', 'date', 'date.contactId = c.id', "INNER");
											}
											
											$criteria->where('date.type', '=', Date::TYPE_BIRTHDAY);					
											$tag = ':age'.uniqid();
											$criteria->andWhere('TIMESTAMPDIFF(YEAR,date.date, CURDATE()) ' . $comparator . $tag)->bind($tag, $value);
											
										})
										->addDate("birthday", function(Criteria $criteria, $comparator, $value, Query $query) {
											if(!$query->isJoined('addressbook_date')) {
												$query->join('addressbook_date', 'date', 'date.contactId = c.id', "INNER");
											}
											
											$tag = ':bday'.uniqid();
											$criteria->where('date.type', '=', Date::TYPE_BIRTHDAY)
																->andWhere('DATE_ADD(date.date, 
																		INTERVAL YEAR(CURDATE())-YEAR(date.date)
																						 + IF(DAYOFYEAR(CURDATE()) > DAYOFYEAR(date.date),1,0)
																		YEAR)  
																' . $comparator . $tag)->bind($tag, $value->format(Column::DATE_FORMAT));
										});
										
	}
	
	public static function converters() {
		$arr = parent::converters();
		$arr['text/vcard'] = VCard::class;		
		return $arr;
	}

	protected static function searchColumns() {
		return ['name'];
	}
	
	private function generateUid() {
		
		$url = trim(GO()->getSettings()->URL, '/');
		$uid = substr($url, strpos($url, '://') + 3);
		$uid = str_replace('/', '-', $uid );
		return $this->id . '@' . $uid;
		
	}
	
	protected function internalSave() {
		if(!parent::internalSave()) {
			return false;
		}
		
		if(!isset($this->uid)) {
			//We need the auto increment ID for the UID so we need to save again if this is a new contact
			$this->uid = $this->generateUid();
			if(!isset($this->uri)) {
				$this->uri = $this->uid . '.vcf';
			}
			if(!GO()->getDbConnection()
							->update('addressbook_contact', 
											['uid' => $this->uid, 'uri' => $this->uri], 
											['id' => $this->id])
							->execute()) {
				return false;
			}
		}		
		
		return $this->saveOriganizationIds();
		
	}
	
	protected function internalValidate() {		
		
		if(empty($this->name)) {
			$this->setNameFromParts();
		}		
		
		if($this->isModified('addressBookId') || $this->isModified('groups')) {
			//verify groups and address book match
			
			foreach($this->groups as $group) {
				$group = Group::findById($group->groupId);
				if($group->addressBookId != $this->addressBookId) {
					$this->setValidationError('groups', ErrorCode::INVALID_INPUT, "The contact groups must match with the addressBookId. Group ID: ".$group->id." belongs to ".$group->addressBookId." and the contact belongs to ". $this->addressBookId);
				}
			}
		}
		
		return parent::internalValidate();
	}
	
	private $organizationIds;
	private $setOrganizationIds;
	
	public function getOrganizationIds() {
		
		if($this->isNew()) {
			$this->organizationIds = [];
		}
		
		if(!isset($this->organizationIds)) {
			
			$query = GO()->getDbConnection()->selectSingleValue('toId')->from('core_link', 'l')
							->join('addressbook_contact', 'c','c.id=l.toId and l.toEntityTypeId = '.self::getType()->getId())
							->where('fromId', '=', $this->id)
							->andWhere('fromEntityTypeId', '=', self::getType()->getId())
							->andWhere('c.isOrganization', '=', true);
			
			$this->organizationIds = array_map("intval", $query->all());
		}
		
		
		return $this->organizationIds;
	}
	
	public function setOrganizationIds($ids) {		
		$this->setOrganizationIds = $ids;				
	}
	
	private function saveOriganizationIds(){
		if(!isset($this->setOrganizationIds)) {
			return true;
		}
		$current = $this->getOrganizationIds();
		
		$remove = array_diff($current, $this->setOrganizationIds);
		if(count($remove)) {
			Link::deleteLinkWithIds($remove, Contact::getType()->getId(), $this->id, Contact::getType()->getId());
		}
		
		$add = array_diff($this->setOrganizationIds, $current);
		
		foreach($add as $orgId) {
			$org = self::findById($orgId);
			if(!Link::create($this, $org)) {
				throw new Exception("Failed to link organization: ". $orgId);
			}
		}
		return true;
	}

	protected function getSearchDescription() {
		$addressBook = AddressBook::findById($this->addressBookId);
		
		$orgStr = "";
		
		if(!$this->isOrganization) {
			$organizationIds = $this->getOrganizationIds();
			
			if(!empty($organizationIds)) {
				$orgStr = ' - '.implode(', ', Contact::find()->selectSingleValue('name')->where(['id' => $organizationIds])->all());
			}
		}
		return $addressBook->name . $orgStr;
	}

	protected function getSearchName() {
		return $this->name;
	}

	protected function getSearchFilter() {
		return $this->isOrganization ? 'isOrganization' : 'isContact';
	}
	
	/**
	 * Because we've implemented the getter method "getOrganizationIds" the contact 
	 * modSeq must be incremented when a link between two contacts is deleted or 
	 * created.
	 * 
	 * @param Link $link
	 */
	public static function onLinkSaveOrDelete(Link $link) {
		if($link->getToEntity() !== "Contact" || $link->getFromEntity() !== "Contact") {
			return;
		}
		
		$to = Contact::findById($link->toId);
		$from = Contact::findById($link->fromId);
		
		//Save contact as link to organizations affect the search entities too.
		if(!$to->isOrganization) {
			$to->saveSearch();
			Contact::getType()->change($to);
		}
		
		if(!$from->isOrganization) {
			$from->saveSearch();
			Contact::getType()->change($from);
		}
		
//		$ids = [$link->toId, $link->fromId];
//		
//		//Update modifiedAt dates for Z-Push and carddav
//		GO()->getDbConnection()
//						->update(
//										'addressbook_contact',
//										['modifiedAt' => new DateTime()], 
//										['id' => $ids]
//										)->execute();	
//		
//		Contact::getType()->changes(
//					(new Query2)
//					->select('c.id AS entityId, a.aclId, "0" AS destroyed')
//					->from('addressbook_contact', 'c')
//					->join('addressbook_addressbook', 'a', 'a.id = c.addressBookId')					
//					->where('c.id', 'IN', $ids)
//					);
		
	}
	
	
	/**
	 * Find URL by type
	 * 
	 * @param string $type
	 * @param boolean $returnAny
	 * @return EmailAddress|boolean
	 */
	public function findUrlByType($type, $returnAny = true) {
		return $this->findPropByType("urls", $type, $returnAny);
	}

	
	/**
	 * Find email address by type
	 * 
	 * @param string $type
	 * @param boolean $returnAny
	 * @return EmailAddress|boolean
	 */
	public function findEmailByType($type, $returnAny = true) {
		return $this->findPropByType("emailAddresses", $type, $returnAny);
	}
	
	/**
	 * Find phoneNumber by type
	 * 
	 * @param string $type
	 * @param boolean $returnAny
	 * @return PhoneNumbers|boolean
	 */
	public function findPhoneNumberByType($type, $returnAny = true) {
		return $this->findPropByType("phoneNumbers", $type, $returnAny);
	}
	
	/**
	 * Find street address by type
	 * 
	 * @param string $type
	 * @param boolean $returnAny
	 * @return Address|boolean
	 */
	public function findAddressByType($type, $returnAny = true) {
		return $this->findPropByType("addresses", $type, $returnAny);
	}
	
	/**
	 * Find date by type
	 * 
	 * @param string $type
	 * @param boolean $returnAny
	 * @return Date|boolean
	 */
	public function findDateByType($type, $returnAny = true) {
		return $this->findPropByType("dates", $type, $returnAny);
	}
	
	private function findPropByType($propName, $type, $returnAny) {
		foreach($this->$propName as $prop) {
			if($prop->type === $type) {
				return $prop;
			}
		}
		
		if(!$returnAny) {
			return false;
		}
		
		return $this->$propName[0] ?? false;
	}
}