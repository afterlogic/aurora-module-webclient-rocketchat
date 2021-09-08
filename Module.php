<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\RocketChatWebclient;

use Aurora\Api;
use Aurora\Modules\Core\Module as CoreModule;
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
		$this->InitChat();

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
	public function GetSettings()
	{
		return [
			'ChatUrl' => $this->sChatUrl 
		];
	}

	/**
	 * Updates settings of the Chat Module.
	 * 
	 * @param boolean $EnableModule indicates if user turned on Chat Module.
	 * @return boolean
	 */
	public function UpdateSettings($EnableModule)
	{
		return true;
	}

	public function EntryChatDirect()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$sEmail = $this->oHttp->GetQuery('chat-direct');
		$sDirect = $this->GetLoginForEmail($sEmail);

		if ($sDirect) {
			$this->showChat($this->sChatUrl . 'direct/' . $sDirect . '?layout=embedded');
		}
	}

	public function EntryChat()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$this->showChat($this->sChatUrl);
	}

	protected function showChat($sUrl)
	{
		$oUser = $this->InitChat();
		if ($oUser) {
			$sResult = \file_get_contents($this->GetPath().'/templates/Chat.html');
			if (\is_string($sResult)) {
				echo strtr($sResult, [
					'{{TOKEN}}' => $oUser->authToken,
					'{{URL}}' => $sUrl
				]);
			}
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
		$mResult = $this->loginCurrentUser();
		
		if (!$mResult) {
			if ($this->createCurrentUser() !== false) {
				$mResult = $this->loginCurrentUser();
			}
		}
		if ($mResult && isset($mResult->data)) {
			$mResult = $mResult->data;
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
