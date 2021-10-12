<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\RocketChatWebclient\Managers\RocketChatSettings;

use GuzzleHttp\Exception\ConnectException;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2021, Afterlogic Corp.
 *
 * @package RocketChatWebclient
 */
class Manager extends \Aurora\System\Managers\AbstractManager
{
	/**
	 * Initializes manager property.
	 *
	 * @param \Aurora\System\Module\AbstractModule $oModule
	 *
	 * @return void
	 */
	public function __construct(\Aurora\System\Module\AbstractModule $oModule = null)
	{
		parent::__construct($oModule);
	}

	public function get($oClient, $aAdminHeaders)
	{
		return [
			// configs
			'Accounts_PasswordReset' => $this->getSetting('Accounts_PasswordReset', $oClient, $aAdminHeaders),
			'Iframe_Restrict_Access' => $this->getSetting('Iframe_Restrict_Access', $oClient, $aAdminHeaders),
			'Iframe_Integration_send_enable' => $this->getSetting('Iframe_Integration_send_enable', $oClient, $aAdminHeaders),
			'Iframe_Integration_receive_enable' => $this->getSetting('Iframe_Integration_receive_enable', $oClient, $aAdminHeaders),
			'API_Enable_Rate_Limiter' => $this->getSetting('API_Enable_Rate_Limiter', $oClient, $aAdminHeaders),

			// texts
			'Site_Name' => $this->getSetting('Site_Name', $oClient, $aAdminHeaders),
			'Layout_Home_Body' => $this->getSetting('Layout_Home_Body', $oClient, $aAdminHeaders),

			// CSS
			'theme-custom-css' => $this->getSetting('theme-custom-css', $oClient, $aAdminHeaders),
			'Custom_Script_Logged_In' => $this->getSetting('Custom_Script_Logged_In', $oClient, $aAdminHeaders),
		];
	}
	
	public function setConfigs($oClient, $aAdminHeaders)
	{
		$bResult = true;
		
		$bResult = $bResult && $this->setSetting('Accounts_PasswordReset', false, $oClient, $aAdminHeaders);
		$bResult = $bResult && $this->setSetting('Iframe_Restrict_Access', false, $oClient, $aAdminHeaders);
		$bResult = $bResult && $this->setSetting('Iframe_Integration_send_enable', true, $oClient, $aAdminHeaders);
		$bResult = $bResult && $this->setSetting('Iframe_Integration_receive_enable', true, $oClient, $aAdminHeaders);
		$bResult = $bResult && $this->setSetting('API_Enable_Rate_Limiter', false, $oClient, $aAdminHeaders);

		return $bResult;
	}
	
	public function setTexts($oClient, $aAdminHeaders)
	{
		$sLayoutHomeBody = '';
		$filename = __DIR__ . '/../../templates/chat.default.home-body.html';
		if (file_exists($filename)) {
			$sLayoutHomeBody = file_get_contents($filename);
		}
		$bLayoutHomeSetSuccess = $this->setSetting('Layout_Home_Body', $sLayoutHomeBody, $oClient, $aAdminHeaders);

		$sSiteName = \Aurora\System\Api::GetModule('Core')->getConfig('SiteName');
		$bSiteNameSetSuccess = $this->setSetting('Site_Name', $sSiteName, $oClient, $aAdminHeaders);
		
		return $bLayoutHomeSetSuccess && $bSiteNameSetSuccess;
	}
	
	public function setCss($oClient, $aAdminHeaders)
	{
		$sThemeCustomCss = '';
		$filename = __DIR__ . '/../../styles/chat.default.css';
		if (file_exists($filename)) {
			$sThemeCustomCss = file_get_contents($filename);
		}
		$bThemeCustomCssSetSuccess = $this->setSetting('theme-custom-css', $sThemeCustomCss, $oClient, $aAdminHeaders);

		$sCustomScriptLoggedIn = '';
		$filename = __DIR__ . '/../../js/chat.default.logged-in.js';
		if (file_exists($filename)) {
			$sCustomScriptLoggedIn = file_get_contents($filename);
		}
		$bCustomScriptLoggedInSetSuccess = $this->setSetting('Custom_Script_Logged_In', $sCustomScriptLoggedIn, $oClient, $aAdminHeaders);

		return $bThemeCustomCssSetSuccess && $bCustomScriptLoggedInSetSuccess;
	}
	
	private function setSetting($sName, $mValue, $oClient, $aAdminHeaders)
	{
		try {
			$oRes = $oClient->post('settings/' . $sName, [
				'json' => [
					'value' => $mValue,
				],
				'headers' => $aAdminHeaders,
				'http_errors' => false
			]);
			$aBody = \json_decode($oRes->getBody(), true);
			if ($oRes->getStatusCode() === 200 && $aBody['success'] === true) {
				return true;
			} else {
				\Aurora\System\Api::Log('Cannot set ' . $sName . ' setting. StatusCode: ' . $oRes->getStatusCode() . '. Response is below.');
				\Aurora\System\Api::Log($aBody);
			}
		} catch (ConnectException $oException) {
			\Aurora\System\Api::Log('Cannot set ' . $sName . ' setting. Exception is below.');
			\Aurora\System\Api::Log($oException);
		}

		return false;
	}

	private function getSetting($sName, $oClient, $aAdminHeaders)
	{
		try {
			$res = $oClient->get('settings/' . $sName, [
				'headers' => $aAdminHeaders,
				'http_errors' => false
			]);
			$aBody = \json_decode($res->getBody(), true);
			if ($res->getStatusCode() === 200 && $aBody['success'] === true && isset($aBody['_id']) && $aBody['_id'] === $sName) {
				return $aBody['value'];
			} else {
				\Aurora\System\Api::Log('Cannot get ' . $sName . ' setting. StatusCode: ' . $res->getStatusCode() . '. Response is below.');
				\Aurora\System\Api::Log($aBody);
			}
		} catch (ConnectException $oException) {
			\Aurora\System\Api::Log('Cannot get ' . $sName . ' setting. Exception is below.');
			\Aurora\System\Api::Log($oException);
		}

		return null;
	}
}
