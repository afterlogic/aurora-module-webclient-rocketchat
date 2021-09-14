<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\RocketChatWebclient;

use Aurora\Api;
use Aurora\Modules\Core\Module as CoreModule;
use Aurora\System\Utils;
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

		$this->client = new \GuzzleHttp\Client([
			'base_uri' => $this->sChatUrl,
			'verify' => false
		]);

		$this->AddEntry('chat', 'EntryChat');
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
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);

		$sChatUrl = '';
		$sAdminUsername = '';
		$iUnreadCounterIntervalInSeconds = 15;

		$oSettings = $this->GetModuleSettings();
		if (!empty($TenantId))
		{
			\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);
			$oTenant = \Aurora\System\Api::getTenantById($TenantId);

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
		
		return [
			'ChatUrl' => $sChatUrl,
			'AdminUsername' => $sAdminUsername,
			'UnreadCounterIntervalInSeconds' => $iUnreadCounterIntervalInSeconds,
		];
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
			\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);
			$oTenant = \Aurora\System\Api::getTenantById($TenantId);

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
			\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::SuperAdmin);

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
		$sEmail = $this->oHttp->GetQuery('chat-direct');
		$sDirect = $this->GetLoginForEmail($sEmail);

		if ($sDirect) {
			$this->showChat('direct/' . $sDirect . '?layout=embedded');
		} else {
			$this->showChat();
		}
	}

	public function EntryChat()
	{
		$this->showChat();
	}

	protected function showChat($sUrl = '')
	{
		$aUser = $this->InitChat();
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
						$sUserLang = \Aurora\System\Utils::ConvertLanguageNameToShort($oUser->Language);
						if ($sUserLang !== $sLang) {
							$res = $this->client->post('api/v1/users.setPreferences', [
								'form_params' => [
									'userId' => $mResult->data->userId, 
									'data' => [
										"language" => $sUserLang
									]
								],
								'headers' => [
									"X-Auth-Token" => $mResult->data->authToken, 
									"X-User-Id" => $mResult->data->userId
								],
								'http_errors' => false
							]);
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

	public function InitChat()
	{
		$mResult = false;
		$oUser = Api::getAuthenticatedUser();
		if ($oUser) {
			$sAuthToken = $oUser->getExtendedProp($this->GetName() . '::AuthToken', null);
			$sUserId = $oUser->getExtendedProp($this->GetName() . '::UserId', null);
			if ($sAuthToken !== null && $sUserId !== null) {
				try
				{
					$res = $this->client->get('api/v1/me', [
						'headers' => [
							"X-Auth-Token" => Utils::DecryptValue($sAuthToken), 
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
						$sUserLang = \Aurora\System\Utils::ConvertLanguageNameToShort($oUser->Language);
						if ($sUserLang !== $sLang) {
							$res = $this->client->post('api/v1/users.setPreferences', [
								'form_params' => [
									'userId' => $sUserId, 
									'data' => [
										"language" => $sUserLang
									]
								],
								'headers' => [
									"X-Auth-Token" => Utils::DecryptValue($sAuthToken), 
									"X-User-Id" => $sUserId
								],
								'http_errors' => false
							]);
						}

						$mResult = [
							'authToken' => Utils::DecryptValue($sAuthToken),
							'userId' => $sUserId
						];
					}
				}
				catch (ConnectException $oException) {}
			}
			if (!$mResult) {
				$mResult = $this->loginCurrentUser();
		
				if (!$mResult) {
					if ($this->createCurrentUser() !== false) {
						$mResult = $this->loginCurrentUser();
					}
				}

				if ($mResult && isset($mResult->data)) {

					$oUser->setExtendedProp($this->GetName() . '::AuthToken', Utils::EncryptValue($mResult->data->authToken));
					$oUser->setExtendedProp($this->GetName() . '::UserId', $mResult->data->userId);
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
		$aCurUser = $this->InitChat();
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
	}

	public function onBeforeDeleteUser(&$aArgs, &$mResult)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$oAuthenticatedUser = Api::getAuthenticatedUser();
		if ($oAuthenticatedUser && ($oAuthenticatedUser->Role === \Aurora\System\Enums\UserRole::SuperAdmin || 
			($oAuthenticatedUser->Role === \Aurora\System\Enums\UserRole::NormalUser && $oAuthenticatedUser->Id === (int) $aArgs['UserId']))) {
			$oAdmin = $this->getAdminAccount();
			if ($oAdmin) {
				try {
					$res = $this->client->post('api/v1/users.delete', [
						'form_params' => [
							'username' => $this->getUserNameFromEmail(\Aurora\Api::getUserPublicIdById($aArgs['UserId']))
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
