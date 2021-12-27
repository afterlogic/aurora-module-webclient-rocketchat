'use strict';

const
	_ = require('underscore'),
	ko = require('knockout'),

	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
	Utils = require('%PathToCoreWebclientModule%/js/utils/Common.js'),

	AlertPopup = require('%PathToCoreWebclientModule%/js/popups/AlertPopup.js'),
	Api = require('%PathToCoreWebclientModule%/js/Api.js'),
	App = require('%PathToCoreWebclientModule%/js/App.js'),
	Popups = require('%PathToCoreWebclientModule%/js/Popups.js'),
	Screens = require('%PathToCoreWebclientModule%/js/Screens.js'),

	ModulesManager = require('%PathToCoreWebclientModule%/js/ModulesManager.js'),
	CAbstractSettingsFormView = ModulesManager.run('AdminPanelWebclient', 'getAbstractSettingsFormViewClass'),

	Ajax = require('modules/%ModuleName%/js/Ajax.js'),
	Settings = require('modules/%ModuleName%/js/Settings.js'),

	FAKE_PASS = '      '
;

/**
 * @constructor
 */
function CRocketChatSettingsPaneAdminView()
{
	CAbstractSettingsFormView.call(this, '%ModuleName%');
	
	this.entityId = null;
	this.tenantData = {};

	this.chatUrl = ko.observable(Settings.ChatUrl);
	this.adminUsername = ko.observable(Settings.AdminUsername);
	this.adminPassword = ko.observable(FAKE_PASS);

	this.configsRequestIsInProgress = ko.observable(false);
	this.configsAreCorrect = ko.observable(true);

	this.applyRequiredChangesInProgress = ko.observable(false);
	this.applyRequiredChangesCommand = Utils.createCommand(this, this.applyRequiredChanges, function () {
		return !this.configsRequestIsInProgress() && !this.configsAreCorrect() && !this.applyRequiredChangesInProgress();
	}.bind(this));

	this.applyTextChangesInProgress = ko.observable(false);
	this.applyTextChangesCommand = Utils.createCommand(this, this.applyTextChanges, function () {
		return !this.applyTextChangesInProgress();
	}.bind(this));

	this.applyCssChangesInProgress = ko.observable(false);
	this.applyCssChangesCommand = Utils.createCommand(this, this.applyCssChanges, function () {
		return !this.applyCssChangesInProgress();
	}.bind(this));
}

_.extendOwn(CRocketChatSettingsPaneAdminView.prototype, CAbstractSettingsFormView.prototype);

/**
 * Name of template that will be bound to this JS-object.
 */
CRocketChatSettingsPaneAdminView.prototype.ViewTemplate = '%ModuleName%_RocketChatSettingsPaneAdminView';

CRocketChatSettingsPaneAdminView.prototype.onRouteChild = function ()
{
	this.getModuleSettings();
	this.getRocketChatSettings();
};

CRocketChatSettingsPaneAdminView.prototype.hasUnsavedChanges = function ()
{
	return this.getCurrentState() !== this.sSavedState;
};

CRocketChatSettingsPaneAdminView.prototype.getCurrentValues = function ()
{
	return [
		this.chatUrl(),
		this.adminUsername(),
		this.adminPassword()
	];
};

CRocketChatSettingsPaneAdminView.prototype.revertGlobalValues = function ()
{
	if (this.entityId) {
		this.chatUrl(Types.pString(this.tenantData.ChatUrl));
		this.adminUsername(Types.pString(this.tenantData.AdminUsername));
	} else {
		this.chatUrl(Settings.ChatUrl);
		this.adminUsername(Settings.AdminUsername);
	}
	this.adminPassword(FAKE_PASS);
};

CRocketChatSettingsPaneAdminView.prototype.getParametersForSave = function ()
{
	const parameters = {
		ChatUrl: this.chatUrl(),
		AdminUsername: this.adminUsername()
	};
	if (FAKE_PASS !== this.adminPassword()) {
		parameters.AdminPassword = this.adminPassword();
	}
	if (this.entityId) {
		parameters.TenantId = this.entityId;
	}
	return parameters;
};

CRocketChatSettingsPaneAdminView.prototype.applySavedValues = function (parameters)
{
	if (!this.entityId) {
		Settings.updateAdmin(parameters);
	}
	this.getRocketChatSettings();
};

CRocketChatSettingsPaneAdminView.prototype.setAccessLevel = function (entityType, entityId)
{
	this.visible(entityType === '' || entityType === 'Tenant');
	this.entityId = entityId;
};

CRocketChatSettingsPaneAdminView.prototype.getModuleSettings = function () {
	const parameters = this.entityId ? { TenantId: this.entityId } : {};
	Ajax.send('GetSettings', parameters, function (response) {
		this.tenantData = response.Result || {};
		this.revertGlobalValues();
		this.updateSavedState();
	}, this);
};

CRocketChatSettingsPaneAdminView.prototype.getRocketChatSettings = function () {
	this.configsRequestIsInProgress(true);
	const parameters = this.entityId ? { TenantId: this.entityId } : {};
	Ajax.send('GetRocketChatSettings', parameters, function (response) {
		this.configsRequestIsInProgress(false);
		const result = response.Result;
		if (result) {
			const
				accountsPasswordResetDisabled = result.Accounts_PasswordReset === '0' || result.Accounts_PasswordReset === false,
				iframeRestrictAccessDisabled = result.Iframe_Restrict_Access === '0' || result.Iframe_Restrict_Access === false,
				iframeIntegrationSendEnabled = result.Iframe_Integration_send_enable === '1' || result.Iframe_Integration_send_enable === true,
				iframeIntegrationReceiveEnabled = result.Iframe_Integration_receive_enable === '1' || result.Iframe_Integration_receive_enable === true,
				apiEnableRateLimiterDisabled = result.API_Enable_Rate_Limiter === '0' || result.API_Enable_Rate_Limiter === false,
				configsAreCorrect = accountsPasswordResetDisabled && iframeRestrictAccessDisabled && iframeIntegrationSendEnabled
					&& iframeIntegrationReceiveEnabled && apiEnableRateLimiterDisabled
			;
			this.configsAreCorrect(configsAreCorrect);
		}
	}, this);
};

CRocketChatSettingsPaneAdminView.prototype.applyRequiredChanges = function () {
	if (this.applyRequiredChangesInProgress()) {
		return;
	}

	if (this.hasUnsavedChanges()) {
		Popups.showPopup(AlertPopup, [TextUtils.i18n('%MODULENAME%/WARNING_SAVE_BEFORE_APPLY')]);
		return;
	}

	this.applyRequiredChangesInProgress(true);
	const parameters = this.entityId ? { TenantId: this.entityId } : {};
	Ajax.send('ApplyRocketChatRequiredChanges', parameters, function (response) {
		this.applyRequiredChangesInProgress(false);
		const result = response.Result;
		if (result === true) {
			this.configsAreCorrect(true);
			Screens.showReport(TextUtils.i18n('%MODULENAME%/REPORT_APPLY_CONFIGS_SUCCESS'));
		} else {
			Api.showErrorByCode(response, TextUtils.i18n('%MODULENAME%/ERROR_APPLY_CONFIGS'));
		}
	}, this);
};

CRocketChatSettingsPaneAdminView.prototype.applyTextChanges = function () {
	if (this.applyTextChangesInProgress()) {
		return;
	}

	if (this.hasUnsavedChanges()) {
		Popups.showPopup(AlertPopup, [TextUtils.i18n('%MODULENAME%/WARNING_SAVE_BEFORE_APPLY')]);
		return;
	}

	this.applyTextChangesInProgress(true);
	const parameters = this.entityId ? { TenantId: this.entityId } : {};
	Ajax.send('ApplyRocketChatTextChanges', parameters, function (response) {
		this.applyTextChangesInProgress(false);
		const result = response.Result;
		if (result === true) {
			Screens.showReport(TextUtils.i18n('%MODULENAME%/REPORT_APPLY_CONFIGS_SUCCESS'));
		} else {
			Api.showErrorByCode(response, TextUtils.i18n('%MODULENAME%/ERROR_APPLY_CONFIGS'));
		}
	}, this);
};

CRocketChatSettingsPaneAdminView.prototype.applyCssChanges = function () {
	if (this.applyCssChangesInProgress()) {
		return;
	}

	if (this.hasUnsavedChanges()) {
		Popups.showPopup(AlertPopup, [TextUtils.i18n('%MODULENAME%/WARNING_SAVE_BEFORE_APPLY')]);
		return;
	}

	this.applyCssChangesInProgress(true);
	const parameters = this.entityId ? { TenantId: this.entityId } : {};
	Ajax.send('ApplyRocketChatCssChanges', parameters, function (response) {
		this.applyCssChangesInProgress(false);
		const result = response.Result;
		if (result === true) {
			Screens.showReport(TextUtils.i18n('%MODULENAME%/REPORT_APPLY_CONFIGS_SUCCESS'));
		} else {
			Api.showErrorByCode(response, TextUtils.i18n('%MODULENAME%/ERROR_APPLY_CONFIGS'));
		}
	}, this);
};

module.exports = new CRocketChatSettingsPaneAdminView();
