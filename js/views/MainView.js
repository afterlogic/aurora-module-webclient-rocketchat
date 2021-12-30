'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),

	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
	Utils = require('%PathToCoreWebclientModule%/js/utils/Common.js'),

	CAbstractScreenView = require('%PathToCoreWebclientModule%/js/views/CAbstractScreenView.js'),
	Routing = require('%PathToCoreWebclientModule%/js/Routing.js'),
	UserSettings = require('%PathToCoreWebclientModule%/js/Settings.js'),

	Settings = require('modules/%ModuleName%/js/Settings.js')
;

/**
 * View that is used as screen of the module. Inherits from CAbstractScreenView that has showing and hiding methods.
 * 
 * @constructor
 */
function CMainView()
{
	CAbstractScreenView.call(this, '%ModuleName%');
	
	this.sChatUrl = Settings.ChatUrl;

	this.iframeDom = ko.observable(null);
	this.avaDom = ko.observable(null);
}

_.extendOwn(CMainView.prototype, CAbstractScreenView.prototype);

CMainView.prototype.ViewTemplate = '%ModuleName%_MainView';
CMainView.prototype.ViewConstructorName = 'CMainView';

CMainView.prototype.setAuroraThemeToRocketChat = function (oIframe) {
	function _setTheme() {
		oIframe.contentWindow.postMessage({
			externalCommand: 'set-aurora-theme',
			theme: UserSettings.Theme
		}, '*');
	};
	setTimeout(_setTheme, 500); // to apply the theme more immediate if possible
	setTimeout(_setTheme, 1000); // this will most likely work first
	setTimeout(_setTheme, 2000); // to be sure the theme will be applied
};

CMainView.prototype.onLoad = function () {
	if (this.iframeDom() && this.iframeDom().length > 0) {
		this.iframeDom()[0].contentWindow.postMessage({
			externalCommand: 'login-with-token',
			token: Settings.ChatAuthToken
		}, '*');

		this.setAuroraThemeToRocketChat(this.iframeDom()[0]);

		window.addEventListener('message', function(oEvent) {
			if (oEvent && oEvent.data && oEvent.data.eventName === 'notification') {
				this.showNotification(oEvent.data.data.notification);
			}
			if (oEvent && oEvent.data && oEvent.data.eventName === 'unread-changed') {
				var HeaderItemView = require('modules/%ModuleName%/js/views/HeaderItemView.js');
				HeaderItemView.unseenCount(Types.pInt(oEvent.data.data));
			}
		}.bind(this));
	}
};

CMainView.prototype.showNotification = function (oNotification) {
	var
		oParameters = {
			action: 'show',
			icon: this.sChatUrl + 'avatar/' + oNotification.payload.sender.username + '?size=50&format=png',
			title: oNotification.title,
			body: oNotification.text,
			callback: function () {
				window.focus();
				if (!this.shown()) {
					Routing.setHash([Settings.HashModuleName]);
				}
				var sPath = '';
				switch (oNotification.payload.type) {
					case 'c':
						sPath = '/channel/' + oNotification.payload.name;
						break;
					case 'd':
						sPath = '/direct/' + oNotification.payload.rid;
						break;
					case 'p':
						sPath = '/group/' + oNotification.payload.name;
						break;
				}
				if (sPath) {
					this.iframeDom()[0].contentWindow.postMessage({
						externalCommand: 'go',
						path: sPath
					}, '*');
				}
			}.bind(this)
		}
	;

	Utils.desktopNotify(oParameters);
};

module.exports = new CMainView();
