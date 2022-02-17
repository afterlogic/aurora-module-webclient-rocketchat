'use strict';

var
	_ = require('underscore'),
	$ = require('jquery'),
	ko = require('knockout'),
	App = require('%PathToCoreWebclientModule%/js/App.js'),
	Ajax = require('modules/%ModuleName%/js/Ajax.js'),
	
	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
	
	Settings = require('modules/%ModuleName%/js/Settings.js'),
	UserSettings = require('%PathToCoreWebclientModule%/js/Settings.js'),
	Popups = require('%PathToCoreWebclientModule%/js/Popups.js'),
	CreateChatUserPopup = require('modules/%ModuleName%/js/popups/CreateChatUserPopup.js'),
	MainView = require('modules/%ModuleName%/js/views/MainView.js')
;

/**
 * @constructor
 */
function CRocketChatSettingsPaneView()
{
	this.sAppName = Settings.AppName || TextUtils.i18n('%MODULENAME%/LABEL_SETTINGS_TAB');

	this.server = Settings.ChatUrl;
	
	this.bDemo = UserSettings.IsDemo;

	this.sDownloadLink = 'https://rocket.chat/install/#Apps';

	// this.sLogin = ko.observable('');
	// this.getLoginForCurrentUser();
	// this.credentialsHintText = ko.computed(function () {
	// 	return TextUtils.i18n('%MODULENAME%/INFO_CREDENTIALS', {'LOGIN': this.sLogin()});
	// }, this);

	this.credentialsHintText = App.mobileCredentialsHintText;

	this.registered = ko.computed(function () {
		return Settings.registered() || Settings.AutocreateChatAccountOnFirstLogin;
	});
}

CRocketChatSettingsPaneView.prototype.getLoginForCurrentUser = function () {
	Ajax.send('GetLoginForCurrentUser', {}, function(oResponse) {
		this.sLogin(oResponse.Result);
	}, this);
}

CRocketChatSettingsPaneView.prototype.createUser = function () {
	Popups.showPopup(CreateChatUserPopup, [Settings.SuggestedUserName, MainView]);
}

/**
 * Name of template that will be bound to this JS-object.
 */
CRocketChatSettingsPaneView.prototype.ViewTemplate = '%ModuleName%_RocketChatSettingsPaneView';

module.exports = new CRocketChatSettingsPaneView();
