'use strict';

var
	_ = require('underscore'),
	$ = require('jquery'),
	ko = require('knockout'),
	App = require('%PathToCoreWebclientModule%/js/App.js'),
	
	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
	
	Settings = require('modules/%ModuleName%/js/Settings.js'),
	UserSettings = require('%PathToCoreWebclientModule%/js/Settings.js')	
;

/**
 * @constructor
 */
function CRocketChatSettingsPaneView()
{
	this.sAppName = Settings.AppName || TextUtils.i18n('%MODULENAME%/LABEL_SETTINGS_TAB');

	this.server = Settings.chatUrl();
	
	this.bDemo = UserSettings.IsDemo;;

	this.sDownloadLink = 'https://rocket.chat/install/#download-rocket';

	this.credentialsHintText = App.mobileCredentialsHintText;
}

/**
 * Name of template that will be bound to this JS-object.
 */
 CRocketChatSettingsPaneView.prototype.ViewTemplate = '%ModuleName%_RocketChatSettingsPaneView';

module.exports = new CRocketChatSettingsPaneView();
