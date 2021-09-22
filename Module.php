<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\RocketChatWebclient;

use Aurora\Api;
use Aurora\Modules\Core\Module as CoreModule;
use Aurora\System\Enums\UserRole;
use Aurora\System\Exceptions\ApiException;
use Aurora\System\Utils;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
	protected $sChatUrl= "";
	
	protected $sAdminUser = "";
	
	protected $sAdminPass = "";
	
	/**
	 * @var \GuzzleHttp\Client
	 */
	protected $client = null;
	
	protected $adminAccount = null;

	public function init() 
	{
		$this->sChatUrl =  $this->getConfig('ChatUrl', '');
		$this->sAdminUser =  $this->getConfig('AdminUsername', '');
		$this->sAdminPass =  $this->getConfig('AdminPassword', '');

		$this->client = new Client([
			'base_uri' => $this->sChatUrl,
			'verify' => false
		]);

//		$this->AddEntry('chat', 'EntryChat');
		$this->AddEntry('chat-direct', 'EntryChatDirect');

		$this->subscribeEvent('Login::after', array($this, 'onAfterLogin'), 10);
		$this->subscribeEvent('Core::Logout::before', array($this, 'onBeforeLogout'));
		$this->subscribeEvent('Core::DeleteUser::before', array($this, 'onBeforeDeleteUser'));
	}

	/**
	 * Obtains list of module settings for authenticated user.
	 * 
	 * @return array
	 */
	public function GetSettings($TenantId = null)
	{
		Api::checkUserRoleIsAtLeast(UserRole::NormalUser);

		$sChatUrl = '';
		$sAdminUsername = '';
		$iUnreadCounterIntervalInSeconds = 15;

		$oSettings = $this->GetModuleSettings();
		if (!empty($TenantId))
		{
			Api::checkUserRoleIsAtLeast(UserRole::TenantAdmin);
			$oTenant = Api::getTenantById($TenantId);

			if ($oTenant)
			{
				$sChatUrl = $oSettings->GetTenantValue($oTenant->Name, 'ChatUrl', $sChatUrl);		
				$sAdminUsername = $oSettings->GetTenantValue($oTenant->Name, 'AdminUsername', $sAdminUsername);
				$iUnreadCounterIntervalInSeconds = $oSettings->GetTenantValue($oTenant->Name, 'UnreadCounterIntervalInSeconds', $iUnreadCounterIntervalInSeconds);
			}
		}
		else
		{
			$sChatUrl = $oSettings->GetValue('ChatUrl', $sChatUrl);		
			$sAdminUsername = $oSettings->GetValue('AdminUsername', $sAdminUsername);
			$iUnreadCounterIntervalInSeconds = $oSettings->GetValue('UnreadCounterIntervalInSeconds', $iUnreadCounterIntervalInSeconds);		
		}
		
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		if ($oUser instanceof \Aurora\Modules\Core\Models\User)
		{
			if ($oUser->isNormalOrTenant())
			{
				$aChatAuthData = $this->initChat();
				return [
					'ChatUrl' => $sChatUrl,
					'ChatAuthToken' => $aChatAuthData ? $aChatAuthData['authToken'] : '',
					'UnreadCounterIntervalInSeconds' => $iUnreadCounterIntervalInSeconds,
				];
			}
			else if ($oUser->Role === \Aurora\System\Enums\UserRole::SuperAdmin)
			{
				return [
					'ChatUrl' => $sChatUrl,
					'AdminUsername' => $sAdminUsername,
					'UnreadCounterIntervalInSeconds' => $iUnreadCounterIntervalInSeconds,
				];
			}
		}

		return [];
	}

	/**
	 * Updates settings of the Chat Module.
	 * 
	 * @param boolean $EnableModule indicates if user turned on Chat Module.
	 * @return boolean
	 */
	public function UpdateSettings($ChatUrl, $AdminUsername, $AdminPassword = null)
	{
		$oSettings = $this->GetModuleSettings();
		if (!empty($TenantId)) {
			Api::checkUserRoleIsAtLeast(UserRole::TenantAdmin);
			$oTenant = Api::getTenantById($TenantId);

			if ($oTenant) {
				$oSettings->SetTenantValue($oTenant->Name, 'ChatUrl', $ChatUrl);		
				$oSettings->SetTenantValue($oTenant->Name, 'AdminUsername', $AdminUsername);
				if (isset($AdminPassword)) {
					$oSettings->SetTenantValue($oTenant->Name, 'AdminPassword', $AdminPassword);
				}
		
				return $oSettings->SaveTenantSettings($oTenant->Name);
			}
		}
		else {
			Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);

			$oSettings->SetValue('ChatUrl', $ChatUrl);		
			$oSettings->SetValue('AdminUsername', $AdminUsername);
			if (isset($AdminPassword)) {
				$oSettings->SetValue('AdminPassword', $AdminPassword);
			}

			return $oSettings->Save();
		}
	}

	public function EntryChatDirect()
	{
		try {
			Api::checkUserRoleIsAtLeast(UserRole::NormalUser);
			
			$sEmail = $this->oHttp->GetQuery('chat-direct');
			$sDirect = $this->GetLoginForEmail($sEmail);

			if ($sDirect) {
				$this->showChat('direct/' . $sDirect . '?layout=embedded');
			} else {
				$this->showChat();
			}
		} catch (ApiException $oEx) {
			$this->showChat();
		}
	}

	public function EntryChat()
	{
		$this->showChat();
	}

	protected function showChat($sUrl = '')
	{
		$aUser = $this->initChat();
		$sResult = \file_get_contents($this->GetPath().'/templates/Chat.html');
		if (\is_string($sResult)) {
			echo strtr($sResult, [
				'{{TOKEN}}' => $aUser ? $aUser['authToken'] : '',
				'{{URL}}' => $this->sChatUrl . $sUrl
			]);
		}
	}

	protected function getUserNameFromEmail($sEmail)
	{
		$mResult = false;

		$aEmailParts = explode("@", $sEmail); 
		if (isset($aEmailParts[1])) {
			$aDomainParts = explode(".", $aEmailParts[1]);
		}

		if (isset($aEmailParts[0])) {
			$mResult = $aEmailParts[0];
			if (isset($aDomainParts[0])) {
				$mResult .= ".". $aDomainParts[0];
			}
		}
		
		return $mResult;
	}

	protected function getAdminAccount()
	{
		if (!isset($this->adminAccount)) {
			try {
				$res = $this->client->post('api/v1/login', [
					'form_params' => [
						'user' => $this->sAdminUser, 
						'password' => $this->sAdminPass
					],
					'http_errors' => false
				]);
				if ($res->getStatusCode() === 200) {
					$this->adminAccount = \json_decode($res->getBody());
				}		
			}
			catch (ConnectException $oException) {}
		}

		return $this->adminAccount;
	}

	protected function getUserInfo($sEmail)
	{
		$mResult = false;

		$oAdmin = $this->getAdminAccount();
		if ($oAdmin) {
			try
			{
				$res = $this->client->get('api/v1/users.info', [
					'query' => [
						'username' => $this->getUserNameFromEmail($sEmail)
					],
					'headers' => [
						"X-Auth-Token" => $oAdmin->data->authToken, 
						"X-User-Id" => $oAdmin->data->userId,
					],
					'http_errors' => false
				]);
				if ($res->getStatusCode() === 200) {
					$mResult = \json_decode($res->getBody());
				}
			}
			catch (ConnectException $oException) {}
		}

		return $mResult;
	}

	protected function getCurrentUserInfo()
	{
		$mResult = false;

		$oUser = Api::getAuthenticatedUser();
		if ($oUser) {
			$mResult = $this->getUserInfo($oUser->PublicId);
		}

		return $mResult;
	}

	protected function createUser($sEmail)
	{
		$mResult = false;

		$oAdmin = $this->getAdminAccount();
		if ($oAdmin) {
			$oAccount = CoreModule::Decorator()->GetAccountUsedToAuthorize($sEmail);
			if ($oAccount) {
				$sEmail = $oAccount->getLogin();
				$sPassword = $oAccount->getPassword();
				$sLogin = $this->getUserNameFromEmail($sEmail);
				$sName = $oAccount->FriendlyName !== '' ? $oAccount->FriendlyName : $sLogin; 
				try {
					$res = $this->client->post('api/v1/users.create', [
						'form_params' => [
							'email' => $sEmail, 
							'name' => $sName, 
							'password' => $sPassword, 
							'username' => $sLogin
						],
						'headers' => [
							"X-Auth-Token" => $oAdmin->data->authToken, 
							"X-User-Id" => $oAdmin->data->userId,
						],
						'http_errors' => false
					]);
					if ($res->getStatusCode() === 200) {
						$mResult = \json_decode($res->getBody());
					}
				}
				catch (ConnectException $oException) {}
			}
		}
		return $mResult;
	}

	protected function createCurrentUser()
	{
		$mResult = false;
		
		$oUser = Api::getAuthenticatedUser();

		if ($oUser)	{
			$mResult = $this->createUser($oUser->PublicId);
		}

		return $mResult;
	}

	protected function loginCurrentUser()
	{
		$mResult = false;
		
		$oUser = Api::getAuthenticatedUser();
		if ($oUser) {
			$oAccount = CoreModule::Decorator()->GetAccountUsedToAuthorize($oUser->PublicId);
			if ($oAccount) {
				try {
					$res = $this->client->post('api/v1/login', [
						'form_params' => [
							'user' => $this->getUserNameFromEmail($oAccount->getLogin()), 
							'password' => $oAccount->getPassword()
						],
						'http_errors' => false
					]);
					if ($res->getStatusCode() === 200) {
						$mResult = \json_decode($res->getBody());
						$sLang = '';
						if (isset($mResult->data->me->settings->preferences->language)) {
							$sLang = $mResult->data->me->settings->preferences->language;
						}
						$sUserLang = Utils::ConvertLanguageNameToShort($oUser->Language);
						if ($sUserLang !== $sLang) {
							$this->updateLanguage($mResult->data->userId, $mResult->data->authToken, $sUserLang);
						}
					}
				}
				catch (ConnectException $oException) {}
			}
		}

		return $mResult;
	}

	protected function logout()
	{
		$mResult = false;

		$oUser = Api::getAuthenticatedUser();
		if ($oUser) {
			$oAccount = CoreModule::Decorator()->GetAccountUsedToAuthorize($oUser->PublicId);
			if ($oAccount) {
				try {
					$res = $this->client->post('api/v1/logout', [
						'form_params' => [
							'user' => $this->getUserNameFromEmail($oAccount->getLogin()), 
							'password' => $oAccount->getPassword()
						],
						'http_errors' => false
					]);
					if ($res->getStatusCode() === 200) {
						$mResult = \json_decode($res->getBody());
					}
				}
				catch (ConnectException $oException) {}
			}
		}

		return $mResult;
	}

	protected function updateLanguage($sUserId, $sToken, $sLang)
	{
		$this->client->post('api/v1/users.setPreferences', [
			'form_params' => [
				'userId' => $sUserId, 
				'data' => [
					"language" => $sLang
				]
			],
			'headers' => [
				"X-Auth-Token" => $sToken, 
				"X-User-Id" => $sUserId
			],
			'http_errors' => false
		]);
	}

	protected function updateUserPassword($userInfo)
	{
		$mResult = false;
		$oUser = Api::getAuthenticatedUser();
		if ($oUser) {
			$oAccount = CoreModule::Decorator()->GetAccountUsedToAuthorize($oUser->PublicId);
			$oAdmin = $this->getAdminAccount();
			if ($oAccount && $oAdmin) {
				$res = $this->client->post('api/v1/users.update', [
					'form_params' => [
						'userId' => $userInfo->user->_id, 
						'data' => [
							"password" => $oAccount->getPassword()
						]
					],
					'headers' => [
						"X-Auth-Token" => $oAdmin->data->authToken, 
						"X-User-Id" => $oAdmin->data->userId,
						"X-2fa-code" => hash("sha256", $this->sAdminPass),
						"X-2fa-method" => "password"
					],
					'http_errors' => false
				]);
				$mResult = ($res->getStatusCode() === 200);
			}
		}

		return $mResult;
	}

	protected function initChat()
	{
		$mResult = false;
		$oUser = Api::getAuthenticatedUser();
		if ($oUser) {
			$sAuthToken = $_COOKIE['RocketChatAuthToken'];
			$sUserId = $_COOKIE['RocketChatUserId'];
			if ($sAuthToken !== null && $sUserId !== null) {
				$sAuthToken = Utils::DecryptValue($sAuthToken);
				try
				{
					$res = $this->client->get('api/v1/me', [
						'headers' => [
							"X-Auth-Token" => $sAuthToken, 
							"X-User-Id" => $sUserId,
						],
						'http_errors' => false
					]);
					if ($res->getStatusCode() === 200) {
						$body = \json_decode($res->getBody(), true);
						$sLang = '';
						if (isset($body->settings->preferences->language)) {
							$sLang = $body->settings->preferences->language;
						}
						$sUserLang = Utils::ConvertLanguageNameToShort($oUser->Language);
						if ($sUserLang !== $sLang) {
							$this->updateLanguage($sUserId, $sAuthToken, $sUserLang);
						}

						$mResult = [
							'authToken' => $sAuthToken,
							'userId' => $sUserId
						];
					}
				}
				catch (ConnectException $oException) {}
			}
			if (!$mResult) {
				$currentUserInfo = $this->getCurrentUserInfo();
				if ($currentUserInfo) {
					$mResult = $this->loginCurrentUser();
					if (!$mResult) {
						if ($this->updateUserPassword($currentUserInfo)) {
							$mResult = $this->loginCurrentUser();
						}
					}
				} elseif ($this->createCurrentUser() !== false) {
					$mResult = $this->loginCurrentUser();
				}

				if ($mResult && isset($mResult->data)) {
					$iAuthTokenCookieExpireTime = (int) \Aurora\System\Api::GetModule('Core')->getConfig('AuthTokenCookieExpireTime', 30);
					@\setcookie('RocketChatAuthToken', Utils::EncryptValue($mResult->data->authToken),
							\strtotime('+' . $iAuthTokenCookieExpireTime . ' days'), \Aurora\System\Api::getCookiePath(),
							null, \Aurora\System\Api::getCookieSecure());
					@\setcookie('RocketChatUserId', $mResult->data->userId,
							\strtotime('+' . $iAuthTokenCookieExpireTime . ' days'), \Aurora\System\Api::getCookiePath(),
							null, \Aurora\System\Api::getCookieSecure());
					$oUser->save();
					$mResult = [
						'authToken' => $mResult->data->authToken,
						'userId' => $mResult->data->userId
					];
				}
			}
		}

		return $mResult;
	}

	public function GetLoginForCurrentUser()
	{
		$mResult = false;

		$oUser = Api::getAuthenticatedUser();
		if ($oUser) {
			$oAccount = CoreModule::Decorator()->GetAccountUsedToAuthorize($oUser->PublicId);
			if ($oAccount) {
				$mResult = $this->getUserNameFromEmail($oAccount->getLogin());
			}
		}

		return $mResult;
	}

	public function GetLoginForEmail($Email)
	{
		$mResult = false;
		$oUserInfo = $this->getUserInfo($Email);
		if (!$oUserInfo) {
			$oUserInfo = $this->createUser($Email);
		}
		if ($oUserInfo && $oUserInfo->success) {
			$mResult = $oUserInfo->user->username;
		}

		return $mResult;
	}

	public function GetUnreadCounter()
	{
		$mResult = 0;
		$aCurUser = $this->initChat();
		if ($aCurUser) {
			try
			{
				$res = $this->client->get('api/v1/subscriptions.get', [
					'headers' => [
						"X-Auth-Token" => $aCurUser['authToken'], 
						"X-User-Id" => $aCurUser['userId'],
					],
					'http_errors' => false
				]);
				if ($res->getStatusCode() === 200) {
					$aResponse = \json_decode($res->getBody(), true);
					if (is_array($aResponse['update'])) {
						foreach ($aResponse['update'] as $update) {
							$mResult += $update['unread'];
						}
					}
				}
			}
			catch (ConnectException $oException) {}
		}

		return $mResult;
	}

	public function onAfterLogin(&$aArgs, &$mResult)
	{
		if (!$this->getCurrentUserInfo()) {
			$this->createCurrentUser();
		}
	}

	public function onBeforeLogout(&$aArgs, &$mResult)
	{
		// RocketChatAuthToken and RocketChatUserId are removed on frontend
		// because it doesn't wait Logout request to be executed
		// so the cookies won't be passed on frontend
	}

	public function onBeforeDeleteUser(&$aArgs, &$mResult)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(UserRole::NormalUser);

		$oAuthenticatedUser = Api::getAuthenticatedUser();
		if ($oAuthenticatedUser && ($oAuthenticatedUser->Role === UserRole::SuperAdmin || 
			($oAuthenticatedUser->Role === UserRole::NormalUser && $oAuthenticatedUser->Id === (int) $aArgs['UserId']))) {
			$oAdmin = $this->getAdminAccount();
			if ($oAdmin) {
				try {
					$res = $this->client->post('api/v1/users.delete', [
						'form_params' => [
							'username' => $this->getUserNameFromEmail(Api::getUserPublicIdById($aArgs['UserId']))
						],
						'headers' => [
							"X-Auth-Token" => $oAdmin->data->authToken, 
							"X-User-Id" => $oAdmin->data->userId,
						],
						'http_errors' => false
					]);
					if ($res->getStatusCode() === 200) {
						$mResult = true;
					}
				}
				catch (ConnectException $oException) {}
			}
		}
	}
}
