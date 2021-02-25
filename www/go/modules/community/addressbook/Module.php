<?php
namespace go\modules\community\addressbook;

use go\core\http\Response;
use go\core;
use go\core\orm\Mapping;
use go\core\orm\Property;
use go\core\webclient\CSP;
use go\modules\community\addressbook\convert\VCard;
use go\modules\community\addressbook\model\Contact;
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
							
	public function getAuthor() {
		return "Intermesh BV <info@intermesh.nl>";
	}

	public function autoInstall()
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

	protected function afterInstall(\go\core\model\Module $model)
	{
		$addressBook = new AddressBook();
		$addressBook->name = go()->t("Shared");
		$addressBook->setAcl([
			Group::ID_INTERNAL => Acl::LEVEL_DELETE
		]);
		$addressBook->save();

		if(!$model->findAcl()
						->addGroup(Group::ID_INTERNAL)
						->save()) {
			return false;
		}

		static::checkRootFolder();

		return parent::afterInstall($model);
	}

	public function getSettings()
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

		$roAcl = Acl::getReadOnlyAcl();
		$folder = Folder::model()->findByPath('addressbook', true, ['acl_id' => $roAcl->id]);
		if($folder->acl_id != $roAcl->id) {
			$folder->acl_id = $roAcl->id;
			$folder->save(true);
		}

		return $folder;
	}

	public function checkDatabase()
	{
		static::checkRootFolder();
		parent::checkDatabase(); // TODO: Change the autogenerated stub
	}

}