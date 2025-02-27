<?php

namespace go\modules\community\notes\controller;

use Exception;
use go\core\jmap\Entity;
use go\core\jmap\EntityController;
use go\core\jmap\exception\InvalidArguments;
use go\core\jmap\exception\StateMismatch;
use go\core\jmap\Response as ResponseAlias;
use go\core\model\Acl;
use go\core\model\Permission;
use go\core\util\Crypt;
use go\modules\community\notes\model;


class Note extends EntityController {
	
	/**
	 * The class name of the entity this controller is for.
	 * 
	 * @return string
	 */
	protected function entityClass(): string
	{
		return model\Note::class;
	}

	/**
	 * @throws Exception
	 */
	public function decrypt($params) {
//		$note = $this->getEntity($params['id']);
		
		$descrypted = Crypt::decrypt($params['data'], $params['password']);
		
		if(!$descrypted) {
			throw new Exception("Invalid password");
		}
		
		ResponseAlias::get()->addResponse([
				'content' => $descrypted
		]);
	}


	/**
	 * Handles the Foo entity's Foo/query command
	 *
	 * @param array $params
	 * @return array
	 * @throws InvalidArguments
	 * @see https://jmap.io/spec-core.html#/query
	 */
	public function query(array $params): array
	{
		return $this->defaultQuery($params);
	}

	/**
	 * Handles the Foo entity's Foo/get command
	 *
	 * @param array $params
	 * @return array
	 * @throws Exception
	 * @see https://jmap.io/spec-core.html#/get
	 */
	public function get(array $params): array
	{
		return $this->defaultGet($params);
	}

	/**
	 * Handles the Foo entity's Foo/set command
	 *
	 * @see https://jmap.io/spec-core.html#/set
	 * @param array $params
	 * @return array
	 * @throws InvalidArguments
	 * @throws StateMismatch
	 */
	public function set(array $params): array
	{
		return $this->defaultSet($params);
	}

	protected function canDestroy(Entity $entity): bool
	{
		if($entity->createdBy === go()->getUserId()) {
			return true; // Anyone should be able to remove their own notes, regardless of people allowed to change noteBOOKs
		}
		if(!$this->rights->mayChangeNoteBooks) {
			return false;
		}
		return parent::canDestroy($entity);
	}


	/**
	 * Handles the Foo entity's Foo/changes command
	 *
	 * @param array $params
	 * @return array
	 * @throws InvalidArguments
	 * @see https://jmap.io/spec-core.html#/changes
	 */
	public function changes(array $params): array
	{
		return $this->defaultChanges($params);
	}


	/**
	 * Handles export
	 *
	 * @param array $params
	 * @return array
	 * @throws InvalidArguments
	 */
	public function export(array $params): array
	{
		return $this->defaultExport($params);
	}

	
	/**
	 * Handles import
	 *
	 * @param array $params
	 * @return array
	 * @throws Exception
	 */
	public function import(array $params): array
	{
		return $this->defaultImport($params);
	}


	/**
	 * Returns a mapping object the client can use for importing data
	 *
	 * @param array $params
	 * @return array
	 * @throws Exception
	 */
	public function importCSVMapping(array $params): array
	{
		return $this->defaultImportCSVMapping($params);
	}

	/**
	 * Returns columns that can be exported
	 *
	 * @param array $params
	 * @return array
	 */
	public function exportColumns(array $params): array
	{
		return $this->defaultExportColumns($params);
	}
}
