'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),
	$ = require('jquery'),
	Ajax = require('modules/%ModuleName%/js/Ajax.js'),

	UrlUtils = require('%PathToCoreWebclientModule%/js/utils/Url.js'),
	
	CAbstractScreenView = require('%PathToCoreWebclientModule%/js/views/CAbstractScreenView.js'),
	
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
	this.sFrameUrl = Settings.chatUrl();
}

_.extendOwn(CMainView.prototype, CAbstractScreenView.prototype);

CMainView.prototype.ViewTemplate = '%ModuleName%_MainView';
CMainView.prototype.ViewConstructorName = 'CMainView';

CMainView.prototype.initChat = function ()
{
	Ajax.send('InitChat', {}, this.onInitChatResponse, this);
}

CMainView.prototype.onInitChatResponse = function (oResponse, oParameters)
{
	if (oResponse.Result) {
		$('#rocketchat_iframe').on("load", function () {
			window.addEventListener('message', function(e) {
				console.log(e.data.eventName); // event name
				console.log(e.data.data); // event data
			});
			$(this).get(0).contentWindow.postMessage({
				externalCommand: 'login-with-token',
				token: oResponse.Result.authToken
			}, '*');

			var direct = UrlUtils.getRequestParam('direct');
			if (direct) {
				$(this).get(0).contentWindow.postMessage({
					externalCommand: 'go',
					path: '/direct/' + direct + '?layout=embedded'
				}, '*');
			}
		});
	}
}

CMainView.prototype.onShow = function ()
{
	this.initChat();
}

module.exports = new CMainView();