<?php /** @noinspection PhpUndefinedFieldInspection */

namespace go\modules\community\ldapauthenticator;

use Closure;
use Exception;
use go\core\auth\DomainProvider;
use go\core\db\Query;
use go\core;
use go\core\model\Module as CoreModelModule;
use go\modules\community\addressbook\model\Contact;
use go\modules\community\ldapauthenticator\model\Authenticator;
use go\core\model\Module as CoreModule;
use go\core\ldap\Record;
use go\core\model\User;
use go\core\fs\Blob;

class Module extends core\Module implements DomainProvider {

	public function getAuthor(): string
	{
		return "Intermesh BV";
	}

	/**
	 * @throws Exception
	 */
	protected function afterInstall(CoreModule $model): bool
	{
		
		if(!Authenticator::register()) {
			return false;
		}
		
		return parent::afterInstall($model);
	}

	public static function getDomainNames(): array
	{
		return (new Query)
						->selectSingleValue('name')
						->from('ldapauth_server_domain')
						->all();
	}

	public static function mappedValues(Record $record): array {
		$cfg = go()->getConfig();
		if(empty($cfg['ldapMapping'])) {
			$cfg['ldapMapping'] = [
				'enabled' => function($record) {
					//return $record->ou[0] != 'Delivering Crew';
					return true;
				},
				'diskQuota' => function($record) {
					//return 1024 * 1024 * 1024;
					return null;
				},
				'email' => 'mail',

//				Example function to look for a preferred domain
//				'email' => function($record) {
//					// Look for email address with preferred domain
//					foreach($record->mail as $email) {
//						if(stristr($email, '@example.com')) {
//							return $email;
//						}
//					}
//
//					//If not found return first.
//					return $record->mail[0] ?? null;
//				},

				'recoveryEmail' => 'mail',
				'displayName' => 'cn',
				'firstName' => 'givenname',
				'lastName' => 'sn',
				'initials' => 'initials',

				'jobTitle' => 'title',
				'department' => 'department',
				'notes' => 'info',

//				'addressType' => function($record) {
//					return \go\modules\community\addressbook\model\Address::TYPE_WORK;
//				},
				'street' => 'street',
				'zipCode' => 'postalCode',
				'city' => 'l',
				'state' => 's',
//				'countryCode' => function($record) {
//					return "NL";
//				},

				'homePhone' => 'homePhone',
				'mobile' => 'mobile',
				'workFax' => 'facsimiletelephonenumber',
				'workPhone' => 'telephonenumber',

				'organization' => 'organizationname',

//				// example for contact custom fields
//				'contactCustomFields' => [
//					'field' => 'employeeType'
//				],
//
//				// example for user custom fields
//				'userCustomFields' => [
//					'field' => 'ou'
//				]

//				'homeDir' => function($record) {
//					//relative path from group-office file_storage_path
//					return "ldap_homes/" . $record->uid[0] . "/files";
//				}
				];
		}
		$mapping = self::map($cfg['ldapMapping'], $record);

//
//		go()->debug("Record:");
//		go()->debug($record->getAttributes());
//
//		go()->debug("Mapping: ");
//		go()->debug($mapping);

		return $mapping;
	}

	private static function map(array $mapping, Record $record) : array {
		foreach($mapping as $local => $ldap) {
			if(is_array($ldap)) {
				$mapping[$local] = self::map($ldap, $record);
			} else if($ldap instanceof Closure) {
				$mapping[$local] = $ldap($record);
			} else if(isset($record->{$ldap})) {
				$mapping[$local] = $record->{$ldap}[0];
			} else {
				unset($mapping[$local]);
			}
		}
		return $mapping;
	}


	/**
	 * @throws Exception
	 */
	public static function ldapRecordToUser($username, Record $record, User $user): User
	{
		go()->debug("cn: " . $record->cn[0] ?? "NULL");

		$user->username = mb_strtolower($username);

		if(!empty($record->jpegPhoto[0])) {
			$blob = Blob::fromString($record->jpegPhoto[0]);
			$blob->type = 'image/jpeg';
			$blob->name = $username . '.jpg';
			if(!$blob->save()) {
				throw new Exception("Could not save blob");
			}
			$user->avatarId = $blob->id;
		}

		$user->displayName = $record->cn[0];		

		$values = self::mappedValues($record);

		if(isset($values['diskQuota'])) $user->disk_quota = $values['diskQuota'];
		if(isset($values['recoveryEmail'])) $user->recoveryEmail = mb_strtolower($values['recoveryEmail']);
		if(isset($values['enabled'])) $user->enabled = $values['enabled'];
		if(isset($values['email'])) $user->email = mb_strtolower($values['email']);
		if(isset($values['displayName'])) $user->displayName = $values['name'] = $values['displayName'];
		if(isset($values['homeDir'])) $user->homeDir = $values['homeDir'];

		if(!isset($user->recoveryEmail)) {
			$user->recoveryEmail = $user->email;
		}

		if(CoreModelModule::isInstalled('community', 'addressbook')) {

			$phoneNbs = [];
			if(isset($values['homePhone'])){
				$phoneNbs[] = ['number' => $values['homePhone'], 'type' => 'work'];
			}
			if(isset($values['workPhone'])){
				$phoneNbs[] = ['number' => $values['workPhone'], 'type' => 'work'];
			}
			if(isset($values['mobile'])){
				$phoneNbs[] = ['number' => $values['mobile'], 'type' => 'mobile'];
			}
			if(isset($values['workFax'])) {
				$phoneNbs[] = ['number' => $values['workFax'], 'type' => 'workfax'];
			}

			if(!empty($phoneNbs)) {
				$values['phoneNumbers'] = $phoneNbs;
			}

			if(isset($values['email'])) {
				$values['emailAddresses'] = [['email' => $values['email']]];
			}

			$addrAttrs = [];
			if(!empty($values['street'])) $addrAttrs['street'] = $values['street'];
			if(!empty($values['street2'])) $addrAttrs['street2'] = $values['street2'];
			if(!empty($values['zipCode'])) $addrAttrs['zipCode'] = $values['zipCode'];
			if(!empty($values['city'])) $addrAttrs['city'] = $values['city'];
			if(!empty($values['state'])) $addrAttrs['state'] = $values['state'];
			if(!empty($values['country'])) $addrAttrs['country'] = $values['country'];
			if(!empty($values['countryCode'])) $addrAttrs['countryCode'] = $values['countryCode'];
			if(!empty($values['type'])) $addrAttrs['type'] = $values['addressType'];
			if(!empty($addrAttrs)) {
				$values['addresses'] = [$addrAttrs];
			}

			if(!empty($values['organization'])) {

				$org = Contact::find(['id'])->where([
					'name' => $values['organization'],
					'isOrganization' => true]
				)->single();
				if(!$org) {
					$org = new Contact();
					$org->name = $values['organization'];
					$org->isOrganization = true;
					$org->addressBookId = go()->getSettings()->userAddressBook()->id;
					if(!$org->save())  {
						throw new Exception("Could not save organization");
					}
				}

				$values['organizationIds'] = [$org->id];
			} else{
				$values['organizationIds'] = [];
			}

			if(isset($values['userCustomFields'])) {
				$user->getCustomFields()->setValues($values['userCustomFields']);
			}

			if(isset($values['contactCustomFields'])) {
				$values['customFields'] = $values['contactCustomFields'];
			}

			//strip out unsupported properies.
			$props = Contact::getApiProperties();
			$values = array_intersect_key($values, $props);

			if(!empty($values)) {
				if(isset($blob)) {
					$values['photoBlobId'] = $blob->id;
				}
				$user->setProfile($values);
			}
		}

		return $user;
	}

}
