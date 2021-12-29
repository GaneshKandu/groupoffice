<?php

namespace go\modules\community\googleoauth2\controller;


use go\core\Controller;
use go\modules\community\email\model\Account;
use go\modules\community\googleoauth2\model;
use League\OAuth2\Client\Provider\Google;

final class Oauth2Account extends Controller
{

	public function entityClass()
	{
		return model\Oauth2Account::class;
	}

	public function callback()
	{
		die('woef');
	}


	public function test()
	{
		die('1 2 4');
	}

	/**
	 * Authenticate using google Oauth settings for current account ID
	 *
	 * @param array $params
	 * @throws \GO\Base\Exception\MissingParameter
	 * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException
	 */
	public function auth(array $params)
	{
		$accountId = isset($params['accountId']) ? $params['accountId'] : null;
		if (!$accountId) {
			throw new \GO\Base\Exception\MissingParameter(go()->t("Missing parameter"));
		}

		$acct = Account::findById($accountId);
		$acctSettings = $acct->googleOauth2;

		// TODO? Session

		$provider = new Google([
			'clientId' => $acctSettings->clientId,
			'clientSecret' => $acctSettings->clientSecret,
			'redirectUri' => 'http://localhost:8000/api/jmap.php' // TODO?
		]);

		if (!empty($_GET['error'])) {

			// Got an error, probably user denied access
			exit('Got error: ' . htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'));

		} elseif (empty($_GET['code'])) {

			// If we don't have an authorization code then get one
			$authUrl = $provider->getAuthorizationUrl();

			$_SESSION['oauth2state'] = $provider->getState();
			$r = \go\core\http\Response::get();
			$r->setHeader('Location', $authUrl);
			$r->sendHeaders();
			exit;

		} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {

			// State is invalid, possible CSRF attack in progress
			unset($_SESSION['oauth2state']);
			exit('Invalid state');

		} else {

			// Try to get an access token (using the authorization code grant)
			$token = $provider->getAccessToken('authorization_code', [
				'code' => $_GET['code']
			]);

			// Optional: Now you have a token you can look up a users profile data
			try {

				// We got an access token, let's now get the owner details
				$ownerDetails = $provider->getResourceOwner($token);

				// Use these details to create a new profile
				printf('Hello %s!', $ownerDetails->getFirstName());

			} catch (\Exception $e) {

				// Failed to get user details
				exit('Something went wrong: ' . $e->getMessage());

			}

			// Use this to interact with an API on the users behalf
			echo $token->getToken();

			// Use this to get a new access token if the old one expires
			echo $token->getRefreshToken();

			// Unix timestamp at which the access token expires
			echo $token->getExpires();
		}
	}
}
