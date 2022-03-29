<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\RocketChatWebclient;

use Aurora\Api;
use Aurora\Modules\Contacts\Module as ContactsModule;
use Aurora\Modules\Core\Module as CoreModule;
use Aurora\System\Enums\UserRole;
use Aurora\System\Exceptions\ApiException;
use Aurora\System\Utils;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use Illuminate\Support\Str;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2021, Afterlogic Corp.
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
	public $oRocketChatSettingsManager = null;
	
	protected $sChatUrl= "";
	
	protected $sAdminUser = "";
	
	protected $sAdminPass = "";

	protected $sDemoPass = "demo";
	
	/**
	 * @var \GuzzleHttp\Client
	 */
	protected $client = null;
	
	protected $adminAccount = null;

	protected $stack = null;

	protected function initConfig()
	{
		$oSettings = $this->GetModuleSettings();
		$this->sChatUrl = $oSettings->GetValue('ChatUrl', '');		
		$this->sAdminUser = $oSettings->GetValue('AdminUsername', '');
		$this->sAdminPass = $oSettings->GetValue('AdminPassword', '');
		Api::AddSecret($this->sAdminPass );

		if (!empty($this->sChatUrl) && !empty($this->sAdminUser)) {
			$this->client = new Client([
				'base_uri' => \rtrim($this->sChatUrl, '/') . '/api/v1/',
				'verify' => false,
				'handler' => $this->stack
			]);
		}
	}

	protected function isDemoUser($sEmail)
	{
		$oDemoModePlugin = Api::GetModule('DemoModePlugin');
		return $oDemoModePlugin && $oDemoModePlugin->CheckDemoUser($sEmail);
	}

	protected function initLogging()
	{
		if ($this->getConfig('EnableLogging', false)) {
			$stack = HandlerStack::create();
			collect([
				'REQUEST: {method} - {uri} - HTTP/{version} - {req_headers} - {req_body}',
				'RESPONSE: {code} - {res_body}',
			])->each(function ($messageFormat) use ($stack) {
				// We'll use unshift instead of push, to add the middleware to the bottom of the stack, not the top
				$oLogger = new Logger('rocketchat-log');
				$oLogger->pushProcessor(function ($record) {
					$record['message'] = str_replace(Api::$aSecretWords, '*****', $record['message']);
					$record['message'] = preg_replace('/(X-Auth-Token|X-2fa-code):(.+?\s)/i', '$1: ***** ', $record['message']);
					$record['message'] = preg_replace('/("bcrypt"):(.*?\})/i', '$1:"*****"}', $record['message']);
					$record['message'] = preg_replace('/("authToken"):(.*?,)/i', '$1:"*****",', $record['message']);
					return $record;
				});
				$stack->unshift(
					Middleware::log(
						$oLogger->pushHandler(
							new RotatingFileHandler(Api::GetLogFileDir() . 'rocketchat-log.txt')
						),
						new MessageFormatter($messageFormat)
					)
				);
			});
			$this->stack = $stack;
		}
	}

	public function init() 
	{
		$this->initLogging();

		$this->oRocketChatSettingsManager = new Managers\RocketChatSettings\Manager($this);

		$this->initConfig();

		$this->AddEntry('chat', 'EntryChat');
		$this->AddEntry('chat-direct', 'EntryChatDirect');

		$this->subscribeEvent('Login::after', array($this, 'onAfterLogin'), 10);
		$this->subscribeEvent('Core::Logout::before', array($this, 'onBeforeLogout'));
		$this->subscribeEvent('Core::DeleteUser::before', array($this, 'onBeforeDeleteUser'));
	}

	protected function getClient($iTenantId = null)
	{
		$mResult = null;
		$oSettings = $this->GetModuleSettings();
		$sChatUrl = '';
		if (isset($iTenantId)) {
			$oTenant = Api::getTenantById($iTenantId);
			if ($oTenant) {
				$sChatUrl = $oSettings->GetTenantValue($oTenant->Name, 'ChatUrl', '');
			}
		} else {
			$sChatUrl = $oSettings->GetValue('ChatUrl', '');
			if (isset($this->client)) {
				return $this->client;
			}
		}
		if (!empty($sChatUrl)) {

			$mResult = new Client([
				'base_uri' => \rtrim($sChatUrl, '/') . '/api/v1/',
				'verify' => false,
				'handler' => $this->stack
			]);
		}

		return $mResult;
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

		$oSettings = $this->GetModuleSettings();
		if (!empty($TenantId))
		{
			Api::checkUserRoleIsAtLeast(UserRole::TenantAdmin);
			$oTenant = Api::getTenantById($TenantId);

			if ($oTenant)
			{
				$sChatUrl = $oSettings->GetTenantValue($oTenant->Name, 'ChatUrl', $sChatUrl);		
				$sAdminUsername = $oSettings->GetTenantValue($oTenant->Name, 'AdminUsername', $sAdminUsername);
			}
		}
		else
		{
			$sChatUrl = $oSettings->GetValue('ChatUrl', $sChatUrl);		
			$sAdminUsername = $oSettings->GetValue('AdminUsername', $sAdminUsername);
		}
		
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		if ($oUser instanceof \Aurora\Modules\Core\Models\User)
		{
			if ($oUser->isNormalOrTenant())
			{
				$aChatAuthData = $this->initChat();
				$mResult = [
					'ChatUrl' => $sChatUrl,
					'ChatAuthToken' => $aChatAuthData ? $aChatAuthData['authToken'] : ''
				];

				return $mResult;
			}
			else if ($oUser->Role === UserRole::SuperAdmin)
			{
				return [
					'ChatUrl' => $sChatUrl,
					'AdminUsername' => $sAdminUsername
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
	public function UpdateSettings($TenantId, $ChatUrl, $AdminUsername, $AdminPassword = null)
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

	public function GetRocketChatSettings($TenantId = null)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);

		return $this->oRocketChatSettingsManager->get(
			$this->getClient($TenantId), 
			$this->getAdminHeaders($TenantId)
		);
	}

	public function ApplyRocketChatRequiredChanges($TenantId = null)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);

		return $this->oRocketChatSettingsManager->setConfigs(
			$this->getClient($TenantId), 
			$this->getAdminHeaders($TenantId)
		);
	}

	public function ApplyRocketChatTextChanges($TenantId = null)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);

		return $this->oRocketChatSettingsManager->setTexts(
			$this->getClient($TenantId), 
			$this->getAdminHeaders($TenantId)
		);
	}

	public function ApplyRocketChatCssChanges($TenantId = null)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);

		return $this->oRocketChatSettingsManager->setCss(
			$this->getClient($TenantId), 
			$this->getAdminHeaders($TenantId)
		);
	}

	public function EntryChatDirect()
	{
		try {
			Api::checkUserRoleIsAtLeast(UserRole::NormalUser);
			
			$sContactUuid = $this->oHttp->GetQuery('chat-direct');
			$oCurrentUser = Api::getAuthenticatedUser();
			$oContact = ContactsModule::Decorator()->GetContact($sContactUuid, $oCurrentUser->Id);
			$oUser = $oContact ? Api::getUserById($oContact->IdUser) : null;
			if ($oCurrentUser && $oUser && $oCurrentUser->IdTenant === $oUser->IdTenant) {
				$sDirect = $this->GetLoginForEmail($oUser->PublicId);

				if ($sDirect) {
					$this->showChat('direct/' . $sDirect . '?layout=embedded');
				} else {
					$this->showError('User not found');
				}
			}
			else {
				$this->showError('User not found');
			}
		} catch (ApiException $oEx) {
			$this->showError('User not found');
		}
	}

	public function EntryChat()
	{
		$oIntegrator = \Aurora\System\Managers\Integrator::getInstance();
		$this->showChat('', $oIntegrator->buildHeadersLink());
	}

	protected function showChat($sUrl = '', $sIntegratorLinks = '')
	{
		$aUser = $this->initChat();
		$sResult = \file_get_contents($this->GetPath().'/templates/Chat.html');
		if (\is_string($sResult)) {
			echo strtr($sResult, [
				'{{TOKEN}}' => $aUser ? $aUser['authToken'] : '',
				'{{URL}}' => $this->sChatUrl . $sUrl,
				'{{IntegratorLinks}}' => $sIntegratorLinks,
			]);
		}
	}

	protected function showError($sMessage= '')
	{
		echo $sMessage;
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

	protected function getAdminCredentials($TenantId = null)
	{
		$mResult = false;
		if (isset($TenantId)) {
			$oSettings = $this->GetModuleSettings();
			$oTenant = Api::getTenantById($TenantId);
			if ($oTenant) {
				$mResult['AdminUser'] = $oSettings->GetTenantValue($oTenant->Name, 'AdminUsername', '');
				$mResult['AdminPass'] = $oSettings->GetTenantValue($oTenant->Name, 'AdminPassword', '');
			}
		} else {
			$mResult['AdminUser'] = $this->sAdminUser;
			$mResult['AdminPass'] = $this->sAdminPass;
		}
		if (isset($mResult['AdminPass'])) {
			Api::AddSecret($mResult['AdminPass']);
		}

		return $mResult;
	}

	protected function getAdminAccount($TenantId = null)
	{
		$mResult = false;
	
		if (!isset($TenantId) && isset($this->adminAccount)) {
			return $this->adminAccount;
		}

		$aAdminCreds = $this->getAdminCredentials($TenantId);
		$client = $this->getClient($TenantId);
		try {
			if ($client && $aAdminCreds) {
				$res = $client->post('login', [
					'form_params' => [
						'user' => $aAdminCreds['AdminUser'], 
						'password' => $aAdminCreds['AdminPass']
					],
					'http_errors' => false
				]);
				if ($res->getStatusCode() === 200) {
					$mResult = \json_decode($res->getBody());
					if (!isset($TenantId)) {
						$this->adminAccount = $mResult;
					}
				}		
			}
		}
		catch (ConnectException $oException) {}

		return $mResult;
	}

	protected function getAdminHeaders($TenantId = null)
	{
		$oAdmin = $this->getAdminAccount($TenantId);
		$aAdminCreds = $this->getAdminCredentials($TenantId);
		if ($oAdmin && $aAdminCreds)
		{
			return [
				'X-Auth-Token' => $oAdmin->data->authToken, 
				'X-User-Id' => $oAdmin->data->userId,
				'X-2fa-code' => hash('sha256', $aAdminCreds['AdminPass']),
				'X-2fa-method' => 'password'
			];
		}
		return [];
	}

	protected function getUserInfo($sEmail)
	{
		$mResult = false;
		$sUserName = $this->getUserNameFromEmail($sEmail);
		try {
			if ($this->client) {
				$res = $this->client->get('users.info', [
					'query' => [
						'username' => $this->getUserNameFromEmail($sEmail)
					],
					'headers' => $this->getAdminHeaders(),
					'http_errors' => false
				]);
				if ($res->getStatusCode() === 200) {
					$mResult = \json_decode($res->getBody());
				}
			}
		} catch (ConnectException $oException) {
			\Aurora\System\Api::Log('Cannot get ' . $sUserName . ' user info. Exception is below.');
			\Aurora\System\Api::LogException($oException);
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

		$oAccount = CoreModule::Decorator()->GetAccountUsedToAuthorize($sEmail);
		if ($oAccount && $this->client) {
			if (!$this->isDemoUser($sEmail)) {
				$sPassword = $oAccount->getPassword();
			} else {
				$sPassword = $this->sDemoPass;
			}

			Api::AddSecret($sPassword);
			
			$sLogin = $this->getUserNameFromEmail($sEmail);
			$sName = isset($oAccount->FriendlyName) && $oAccount->FriendlyName !== '' ? $oAccount->FriendlyName : $sLogin; 
			try {
				$res = $this->client->post('users.create', [
					'json' => [
						'email' => $sEmail, 
						'name' => $sName, 
						'password' => $sPassword, 
						'username' => $sLogin
					],
					'headers' => $this->getAdminHeaders(),
					'http_errors' => false
				]);
				if ($res->getStatusCode() === 200) {
					$mResult = \json_decode($res->getBody());
				}
			} catch (ConnectException $oException) {
				\Aurora\System\Api::Log('Cannot create ' . $sEmail . ' user. Exception is below.');
				\Aurora\System\Api::LogException($oException);
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
			if ($oAccount && $this->client) {
				if (!$this->isDemoUser($oUser->PublicId)) {
					$sPassword = $oAccount->getPassword();
				} else {
					$sPassword = $this->sDemoPass;
				}
				Api::AddSecret($sPassword);
				try {
					$res = $this->client->post('login', [
						'form_params' => [
						'user' => $this->getUserNameFromEmail($oUser->PublicId), 
							'password' => $sPassword
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

			if ($this->client) {
				try {
					$sAuthToken = isset($_COOKIE['RocketChatAuthToken']) ? $_COOKIE['RocketChatAuthToken'] : null;
					$sUserId = isset($_COOKIE['RocketChatUserId']) ? $_COOKIE['RocketChatUserId'] : null;
					if ($sAuthToken !== null && $sUserId !== null) {
						$res = $this->client->post('logout', [
							'headers' => [
								"X-Auth-Token" => $sAuthToken, 
								"X-User-Id" => $sUserId,
							],
							'http_errors' => false
						]);
						if ($res->getStatusCode() === 200) {
							$mResult = \json_decode($res->getBody());
						}
					}
				}
				catch (ConnectException $oException) {
					Api::LogException($oException);
				}
			}

		return $mResult;
	}

	protected function updateLanguage($sUserId, $sToken, $sLang)
	{
		if ($this->client) {
			$this->client->post('users.setPreferences', [
				'json' => [
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
	}

	protected function updateUserPassword($userInfo)
	{
		$mResult = false;
		$oUser = Api::getAuthenticatedUser();
		if ($oUser) {
			$oAccount = CoreModule::Decorator()->GetAccountUsedToAuthorize($oUser->PublicId);
			if ($oAccount && $this->client) {
				Api::AddSecret($oAccount->getPassword());
				$res = $this->client->post('users.update', [
					'json' => [
						'userId' => $userInfo->user->_id, 
						'data' => [
							'password' => $oAccount->getPassword()
						]
					],
					'headers' => $this->getAdminHeaders(),
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
		if ($oUser && $this->client) {
			$sAuthToken = isset($_COOKIE['RocketChatAuthToken']) ? $_COOKIE['RocketChatAuthToken'] : null;
			$sUserId = isset($_COOKIE['RocketChatUserId']) ? $_COOKIE['RocketChatUserId'] : null;
			if ($sAuthToken !== null && $sUserId !== null) {
				$sAuthToken = Utils::DecryptValue($sAuthToken);
				Api::AddSecret($sAuthToken);
				try
				{
					$res = $this->client->get('me', [
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
					if (!$mResult && !$this->isDemoUser($oUser->PublicId)) {
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
			$mResult = $this->getUserNameFromEmail($oUser->PublicId);
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
		if ($aCurUser && $this->client) {
			try
			{
				$res = $this->client->get('subscriptions.get', [
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
		$client = null;
		$adminHeaders = null;
		$oAuthenticatedUser = Api::getAuthenticatedUser();
		if ($oAuthenticatedUser && $oAuthenticatedUser->isNormalOrTenant() &&
				$oAuthenticatedUser->Id === (int) $aArgs['UserId']
		) {
			// Access is granted
			$client = $this->client;
			$adminHeaders = $this->getAdminHeaders();
		} else {
			$oUser = Api::getUserById((int) $aArgs['UserId']);
			if ($oUser) {

				if ($oAuthenticatedUser->Role === UserRole::TenantAdmin && $oUser->IdTenant === $oAuthenticatedUser->IdTenant) {
					\Aurora\System\Api::checkUserRoleIsAtLeast(UserRole::TenantAdmin);
				} else if ($oAuthenticatedUser->Role === UserRole::SuperAdmin){
					\Aurora\System\Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);
				}

				$client = $this->getClient($oUser->IdTenant);
				$adminHeaders = $this->getAdminHeaders($oUser->IdTenant);
			}
		}

		$sUserName = $this->getUserNameFromEmail(Api::getUserPublicIdById($aArgs['UserId']));
		try {
			if ($client) {
				$oRes = $client->post('users.delete', [
					'json' => [
						'username' => $sUserName
					],
					'headers' => $adminHeaders,
					'http_errors' => false,
					'timeout' => 1
				]);
				if ($oRes->getStatusCode() === 200) {
					$mResult = true;
				}
			}
		}
		catch (ConnectException $oException) {
			\Aurora\System\Api::Log('Cannot delete ' . $sUserName . ' user from RocketChat. Exception is below.');
			\Aurora\System\Api::LogException($oException);
		}
	}
}
