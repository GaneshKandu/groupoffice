<?php
namespace go\modules\community\addressbook;

use Faker\Generator;
use go\core\http\Response;
use go\core;
use go\core\model;
use go\core\orm\Mapping;
use go\core\orm\Property;
use go\core\webclient\CSP;
use go\modules\community\addressbook\convert\VCard;
use go\modules\community\addressbook\model\Address;
use go\modules\community\addressbook\model\Contact;
use go\modules\community\addressbook\model\EmailAddress;
use go\modules\community\addressbook\model\PhoneNumber;
use go\modules\community\addressbook\model\UserSettings;
use go\modules\community\addressbook\model\AddressBookPortletBirthday;
use go\core\model\Link;
use go\core\model\User;
use go\modules\community\addressbook\model\AddressBook;
use go\core\model\Group;
use go\core\model\Acl;
use go\core\model\Module as GoModule;
use GO\Files\Model\Folder;
use go\modules\community\addressbook\model\Settings;
use Faker;

/**						
 * @copyright (c) 2018, Intermesh BV http://www.intermesh.nl
 * @author Merijn Schering <mschering@intermesh.nl>
 * @license http://www.gnu.org/licenses/agpl-3.0.html AGPLv3
 * 
 * @todo 
 * Merge
 * Deduplicate
 * 
 */
class Module extends core\Module {
							
	public function getAuthor(): string
	{
		return "Intermesh BV <info@intermesh.nl>";
	}

	protected function rights(): array
	{
		return [
			'mayChangeAddressbooks', // allows AddressBook/set (hide ui elements that use this)
			'mayExportContacts', // Allows users to export contacts
		];
	}

	public function autoInstall(): bool
	{
		return true;
	}


	public function defineListeners() {
		parent::defineListeners();
		
		Link::on(Link::EVENT_BEFORE_DELETE, Contact::class, 'onLinkDelete');
		Link::on(Link::EVENT_SAVE, Contact::class, 'onLinkSave');
		User::on(Property::EVENT_MAPPING, static::class, 'onMap');
		User::on(User::EVENT_BEFORE_DELETE, static::class, 'onUserDelete');
		User::on(User::EVENT_BEFORE_SAVE, static::class, 'onUserBeforeSave');
	}
	
	public function downloadVCard($contactId) {
		$contact = Contact::findById($contactId);
		if(!$contact->getPermissionLevel()) {
			throw new core\exception\Forbidden();
		}
		
		$c = new VCard();
		
		$vcard =  $c->export($contact);		
		
		Response::get()
						->setHeader('Content-Type', 'text/vcard;charset=utf-8')
						->setHeader('Content-Disposition', 'attachment; filename="'.$contact->name.'.vcf"')
						->setHeader("Content-Length", strlen($vcard))
						->sendHeaders();
		
		echo $vcard;
	}


	public static function onMap(Mapping $mapping)
	{
		$mapping->addHasOne('addressBookSettings', UserSettings::class, ['id' => 'userId'], true);
		$mapping->addScalar('birthdayPortletAddressBooks', "addressbook_portlet_birthday", ['id' => 'userId']);
	}

	public static function onUserDelete(core\db\Query $query) {
		AddressBook::delete(['createdBy' => $query]);
	}

	public static function onUserBeforeSave(User $user)
	{
		if (!$user->isNew() && $user->isModified('displayName')) {
			$oldName = $user->getOldValue('displayName');
			$ab = AddressBook::find()->where(['createdBy' => $user->id, 'name' => $oldName])->single();
			if ($ab) {
				$ab->name = $user->displayName;
				$ab->save();
			}
		}
	}
	protected function beforeInstall(\go\core\model\Module $model): bool
	{
		// Share module with Internal group
		$model->permissions[Group::ID_INTERNAL] = (new \go\core\model\Permission($model))
			->setRights(['mayRead' => true]);

		return parent::beforeInstall($model); // TODO: Change the autogenerated stub
	}


	protected function afterInstall(\go\core\model\Module $model): bool
	{
		// create Shared address book
		$addressBook = new AddressBook();
		$addressBook->name = go()->t("Shared");
		$addressBook->setAcl([
			Group::ID_INTERNAL => Acl::LEVEL_DELETE
		]);
		$addressBook->save();


		static::checkRootFolder();

		return parent::afterInstall($model);
	}

	public function getSettings(): ?\go\core\Settings
	{
		return Settings::get();
	}

	/**
	 * Create and check permission on the "addressbook" root folder.
	 */
	public static function checkRootFolder() {

		if(!GoModule::isInstalled('legacy', 'files')) {
			return false;
		}

		$roAclId = Acl::getReadOnlyAclId();
		$folder = Folder::model()->findByPath('addressbook', true, ['acl_id' => $roAclId]);
		if($folder->acl_id != $roAclId) {
			$folder->acl_id = $roAclId;
			$folder->save(true);
		}

		return $folder;
	}

	public function checkDatabase()
	{
		static::checkRootFolder();
		parent::checkDatabase(); // TODO: Change the autogenerated stub
	}


	public function demo(Generator $faker) {



		$addressBook = AddressBook::find()->single();

		for ($n = 0; $n < 10; $n++) {
			echo ".";
			$company = new Contact();
//			$blob = core\fs\Blob::fromTmp(new core\fs\File($faker->image));
//			$company->photoBlobId = $blob->id;
			$company->isOrganization = true;
			$company->addressBookId = $addressBook->id;
			$company->name = $faker->company;
			$company->jobTitle = $faker->bs;

			$count = $faker->numberBetween(0, 3);
			for($i = 0; $i < $count; $i++) {
				$company->phoneNumbers[$i] = (new PhoneNumber($company))->setValues(['number' => $faker->phoneNumber, 'type' => PhoneNumber::TYPE_MOBILE]);
			}
			$count = $faker->numberBetween(0, 3);
			for($i = 0; $i < $count; $i++) {
				$company->emailAddresses[$i] = (new EmailAddress($company))->setValues(['email' => $faker->email, 'type' => EmailAddress::TYPE_HOME]);
			}

			$company->addresses[0] = $a = new Address($company);

			$a->street = $faker->streetName;
			$a->street2 = $faker->streetAddress;
			$a->city = $faker->city;
			$a->zipCode = $faker->postcode;
			$a->state = $faker->state;
			$a->country = $faker->country;

			$company->notes = $faker->realtext;
			if(!$company->save()) {
				var_dump($company->getValidationErrors());
				exit();
			}

			$contact = new Contact();
//			$blob = core\fs\Blob::fromTmp(new core\fs\File($faker->image(null, 640, 480, 'people')));
//			$company->photoBlobId = $blob->id;

			$contact->addressBookId = $addressBook->id;
			$contact->firstName = $faker->firstName;
			$contact->lastName = $faker->lastName;

			$count = $faker->numberBetween(0, 3);
			for($i = 0; $i < $count; $i++) {
				$contact->phoneNumbers[$i] = (new PhoneNumber())->setValues(['number' => $faker->phoneNumber, 'type' => PhoneNumber::TYPE_MOBILE]);
			}
			$count = $faker->numberBetween(0, 3);
			for($i = 0; $i < $count; $i++) {
				$contact->emailAddresses[$i] = (new EmailAddress())->setValues(['email' => $faker->email, 'type' => EmailAddress::TYPE_HOME]);
			}

			$contact->addresses[0] = $a = new Address($contact);

			$a->street = $faker->streetName;
			$a->street2 = $faker->streetAddress;
			$a->city = $faker->city;
			$a->zipCode = $faker->postcode;
			$a->state = $faker->state;
			$a->country = $faker->country;

			$contact->notes = $faker->realtext;

			if(!$contact->save()) {
				var_dump($company->getValidationErrors());
				exit();
			}

			if(core\model\Module::isInstalled("community", "comments")) {
				\go\modules\community\comments\Module::demoComments($faker, $contact);
				\go\modules\community\comments\Module::demoComments($faker, $company);
			}

			Link::demo($faker, $contact);
			Link::demo($faker, $company);


			Link::create($contact, $company, null, true);
		}
	}
}