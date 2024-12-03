'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),

	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
	Utils = require('%PathToCoreWebclientModule%/js/utils/Common.js'),
	Ajax = require('%PathToCoreWebclientModule%/js/Ajax.js'),

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
	CAbstractScreenView.call(this, '%ModuleName%')
	
	this.initialized = ko.observable(false)
	this.sChatUrl = Settings.ChatUrl
	this.iframeDom = ko.observable(null)
	this.iframeLoaded = ko.observable(false)
	this.chatToken = ko.observable('')

	ko.computed(function () {
		if (this.iframeDom() && this.chatToken() && this.iframeLoaded()) {
			this.init()
		}
	}, this)

	Ajax.send(Settings.ServerModuleName,'InitChat', {}, function(oResponse) {
		if(oResponse.Result && oResponse.Result['authToken']) {
			this.chatToken(oResponse.Result['authToken'])
		}
	}, this);

	window.addEventListener('message', function(oEvent) {
		if (oEvent && oEvent.data) {
			if(oEvent.data.eventName === 'startup') {
				this.iframeLoaded(true)
			}
			if(oEvent.data.eventName === 'notification') {
				this.showNotification(oEvent.data.data.notification)
			}

			if (oEvent.data.eventName === 'unread-changed') {
				const HeaderItemView = require('modules/%ModuleName%/js/views/HeaderItemView.js')
				HeaderItemView.unseenCount(Types.pInt(oEvent.data.data))
			}
		}
	}.bind(this))
}

_.extendOwn(CMainView.prototype, CAbstractScreenView.prototype)

CMainView.prototype.ViewTemplate = '%ModuleName%_MainView'
CMainView.prototype.ViewConstructorName = 'CMainView'

CMainView.prototype.init = function () {
	if (!this.initialized()) {
		var 
			iframe = document.getElementById('rocketchat_iframe'),
			self = this
		;
		iframe.contentWindow.postMessage({
			externalCommand: 'login-with-token',
			token: self.chatToken()
		}, '*')

		setTimeout(() => {
			iframe.contentWindow.postMessage({
				externalCommand: 'set-aurora-theme',
				theme: UserSettings.Theme
			}, '*');
			self.initialized(true)
		}, 2000)
	}
}

CMainView.prototype.showNotification = function (oNotification) {
	const
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

	Utils.desktopNotify(oParameters)
}

module.exports = new CMainView()
