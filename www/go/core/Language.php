<?php

namespace go\core;

use Exception;
use go\core\cache\None;
use go\core\fs\File;
use go\core\jmap\Request;
use go\core\model\Module;
use go\core\model\User;
use go\modules\community\addressbook\model\Address;

class Language {
	/**
	 *
	 * @var string eg "en-US".
	 */
	private $isoCode;
	private $data = [];

	/**
	 * Replace {product_name} with Group-Office or $config['product_name']
	 *
	 * Exporting language disables this.
	 *
	 * @var bool
	 */
	private $replaceProductName = true;


	public function initExport() {
		$this->replaceProductName = false;
		$this->data = [];
		go()->setCache(new None());
	}


	
	/**
	 * Get ISO code with underscore separator for region
	 * 
	 * @return string eg. "en" or "en_UK"
	 */
	public function getIsoCode(): string
	{

		if(isset($_GET['SET_LANGUAGE']) && $this->hasLanguage($_GET['SET_LANGUAGE'])) {
			if(!headers_sent())
				setcookie('GO_LANGUAGE', $_GET['SET_LANGUAGE'], time() + (10 * 365 * 24 * 60 * 60));
			return $_GET['SET_LANGUAGE'];
		}

		if(!isset($this->isoCode)) {
			if(isset($_COOKIE['GO_LANGUAGE'])) {
				$this->isoCode = $_COOKIE['GO_LANGUAGE'];
			} else {
				$this->isoCode = go()->getAuthState() && go()->getAuthState()->isAuthenticated() ? go()->getAuthState()->getUser(['language'])->language : $this->getBrowserLanguage();
				if(!headers_sent())
					setcookie('GO_LANGUAGE', $this->isoCode, time() + (10 * 365 * 24 * 60 * 60));
			}
		}
		return $this->isoCode;
	}

	/**
	 * Set new language
	 *
	 * @param string|null $isoCode when not given it's detected from the browser
	 * @return string|false Old language setting
	 */
	public function setLanguage($isoCode = null) {
		$old = $this->getIsoCode();
		if(!isset($isoCode)) {
			$isoCode = $this->getBrowserLanguage();
		}
		
		if(!$this->hasLanguage($isoCode)) {
			go()->debug("Invalid language given ".$isoCode);
			return false;
		}
		
		if($isoCode != $this->isoCode) {
			$this->isoCode = $isoCode;
			$this->data = [];
		}

		return $old;
		
	}
	
	private function getBrowserLanguage(){

		
		$browserLanguages= Request::get()->getAcceptLanguages();
		foreach($browserLanguages as $lang){
			$lang = str_replace('-','_',explode(';', $lang)[0]);
			if($this->hasLanguage($lang)){
				return $lang;
			}
			
			if(($pos = strpos($lang, "_"))) {
				$lang = substr($lang, 0, $pos);
				if($this->hasLanguage($lang)){
					return $lang;
				}
			}
		}
		
		return go()->getSettings()->language; // from settings if we cant determine
	}

	private $af;
	public function getAddressFormat($isoCode) {

		if(!isset($this->af)) {
			require(\go\core\Environment::get()->getInstallFolder() . '/language/addressformats.php');
			$this->af = $af;
		}

		return isset($this->af[$isoCode]) ? $this->af[$isoCode] : $this->af['default'];
	}

	private static $defaultCountryIso;

	private static function isDefaultCountry($countryCode) {
		if(!isset(self::$defaultCountryIso)) {
			$user = go()->getAuthState() ? go()->getAuthState()->getUser(['timezone']) : null;
			if(!$user) {
				$user = User::findById(1, ['timezone']);
			}
			self::$defaultCountryIso = $user->getCountry();
//			$countries = go()->t('countries');
//			self::$defaultCountryText = $countries[self::$defaultCountryIso] ?? "";
		}

		return self::$defaultCountryIso == $countryCode;
	}


	/**
	 * Format an address
	 *
	 * @param $countryCode
	 * @param $address array with street, street2, city, zipCode and state
	 * @return string
	 */
	public function formatAddress($countryCode, $address)
	{
		if (empty($address['street']) && empty($address['city']) && empty($address['state'])) {
			return "";
		}
		$format = go()->getLanguage()->getAddressFormat($countryCode);

		$format = str_replace('{address}', $address['street'], $format);
		$format = str_replace('{address_no}', $address['street2'], $format);
		$format = str_replace('{city}', $address['city'], $format);
		$format = str_replace('{zip}', $address['zipCode'], $format);
		$format = str_replace('{state}', $address['state'], $format);

		if (self::isDefaultCountry($countryCode)) {
			$format = str_replace('{country}', "", $format);
		}else{
			$countries = go()->t('countries');
			$country = $countries[$countryCode] ?? "";
			$format = str_replace('{country}', $country, $format);
		}

		return preg_replace("/\n+/", "\n", $format);
	}

	/**
	 * Translates a language variable name into the local language.
	 * 
	 * @param String $str String to translate
	 * @param String $module Name of the module to find the translation
	 * @param String $package Only applies if module is set to 'base'
	 */
	public function t($str, $package = 'core', $module = 'core') {

		$this->loadSection($package, $module);
		
		//fallback on core lang
		if(!isset($this->data[$package]) || !isset($this->data[$package][$module]) || ($package != "core" && $module != "core" && !isset($this->data[$package][$module][$str]))) {
			return $this->t($str);
		}
		
		return $this->data[$package][$module][$str] ?? $this->replaceBrand($str);
	}
	
	public function translationExists($str, $package = 'core', $module = 'core') {
		$this->loadSection($package, $module);
		
		return isset($this->data[$package]) && isset($this->data[$package][$module]) && isset($this->data[$package][$module][$str]);
	}


	private function loadSection($package = 'core', $module = 'core') {
		if (!isset($this->data[$package])) {
			$this->data[$package] = [];
		} 
		
		$isoCode = $this->getIsoCode();

		if(!isset($this->data[$package][$module])) {

			$cacheKey = $isoCode .'-'.$package.'-'.$module;

			$this->data[$package][$module] = go()->getCache()->get($cacheKey);
			if($this->data[$package][$module] !== null) {
				return;
			}
			
			$langData = new util\ArrayObject();
			
			//Get english default
			$file = $this->findLangFile('en',$package, $module);
			if ($file->exists()) {
				$langData = new util\ArrayObject($this->loadFile($file));
			} else {
				go()->warn('No default(en) language file for module "'.$package.'/'.$module.'" defined.');
			}

			//overwirte english with actual language
			if ($isoCode != 'en') {
				$file = $this->findLangFile($isoCode, $package, $module);
				if ($file->exists()) {
					$langData->mergeRecursive($this->loadFile($file));
				}
			}

			$file = $this->findLangOverride($isoCode, $package, $module);
			if ($file && $file->exists()) {
				$langData->mergeRecursive($this->loadFile($file));
			}

			foreach ($langData as $key => $translation) {
				
				if(!is_string($translation)) {
					continue;
				}
							
				//branding
				$langData[$key]  = $this->replaceBrand($langData[$key]);
			}

			$this->data[$package][$module] = $langData->getArray();
			go()->getCache()->set($cacheKey, $this->data[$package][$module]);
		}
	}

	private function replaceBrand($str) {
		$productName = $this->replaceProductName ? go()->getConfig()['product_name'] : "{product_name}";
		return str_replace(
			[
				"{product_name}",
				"GroupOffice",
				"Group-Office",
				"Group Office"
			],
			[
				$productName,
				$productName,
				$productName,
				$productName

			], $str);
	}
	
	private function loadFile($file) {
		
		try {
			$langData = require($file);
		} catch(\ParseError $e) {
			ErrorHandler::logException($e);
			$langData = [];
		}
		if(!is_array($langData)){
			throw new \Exception("Invalid language file  " . $file);
		}
		
		return $langData;
	}
	
	public function hasLanguage($lang) {
		return $this->findLangFile($lang, 'core', 'core')->exists();
	}

	/**
	 * 
	 * @param string $lang
	 * @param string $module
	 * @param string $basesection
	 * @return File
	 * @throws Exception
	 */
	private function findLangFile($lang, $package, $module) {
		
		if($package == "legacy") {
			$folder = Environment::get()->getInstallFolder()->getFolder('modules/' . $module .'/language');
		} else if($package == "core" && $module == "core") {
			$folder = Environment::get()->getInstallFolder()->getFolder('go/core/language');
		} else
		{
			$folder = Environment::get()->getInstallFolder()->getFolder('go/modules/' . $package . '/' . $module .'/language');
		}

		return $folder->getFile($lang . '.php');
	}

	/**
	 * 
	 * @param string $lang
	 * @param string $module
	 * @param string $basesection
	 * @return File
	 */
	private function findLangOverride($lang, $package, $module) {

		if(Installer::isInProgress()) {
			return false;
		}
		$admin = User::findById(1, ['homeDir']);

		$folder = go()->getDataFolder()->getFolder($admin->homeDir. '/language/' . $package . '/' .$module);
		
		return $folder->getFile($lang . '.php');
	}

	

	/**
	 * Get all supported languages.
	 * 
	 * @return array array('en'=>'English');
	 */
	public function getLanguages() {
		$languages = go()->getCache()->get('languages');
		if($languages !== null) {
			return $languages;
		}

		require(Environment::get()->getInstallFolder() . '/language/languages.php');
		asort($languages);

		go()->getCache()->set('languages', $languages);

		return $languages;
	}

	/**
	 * Get all countries
	 * 
	 * @return array array('nl'=>'The Netherlands');
	 */
	public function getCountries() {
		$this->loadSection('core', 'countries');
		asort($this->data['core']['countries']);
		return $this->data['core']['countries'];
	}

	
	
	
	public function getAllLanguage(){
		$modules = Module::find();
		foreach($modules as $module) {
			$this->loadSection($module->package  ?? "legacy", $module->name);
		}
		
		return $this->data;
	}
}
