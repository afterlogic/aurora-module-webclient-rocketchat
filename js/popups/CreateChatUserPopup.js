'use strict';

var
	_ = require('underscore'),
	$ = require('jquery'),
	ko = require('knockout'),
	
	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
	CAbstractPopup = require('%PathToCoreWebclientModule%/js/popups/CAbstractPopup.js'),
	Api = require('%PathToCoreWebclientModule%/js/Api.js'),
	Screens = require('%PathToCoreWebclientModule%/js/Screens.js'),
	Ajax = require('modules/%ModuleName%/js/Ajax.js')
;

/**
 * @constructor
 */
function CCreateChatUserPopup()
{
	CAbstractPopup.call(this);
	
	this.login = ko.observable('');
	this.mainView = null;
	this.loading = ko.observable(false);
}

_.extendOwn(CCreateChatUserPopup.prototype, CAbstractPopup.prototype);

CCreateChatUserPopup.prototype.PopupTemplate = '%ModuleName%_CreateChatUserPopup';

/**
 * @param {string} sLogin
 */
 CCreateChatUserPopup.prototype.onOpen = function (sLogin, mainView)
{
	if (this.login() !== sLogin)
	{
		this.login(sLogin);
	}
	if (this.mainView !== mainView)
	{
		this.mainView = mainView;
	}
};

CCreateChatUserPopup.prototype.onCreateClick = function ()
{
	if (!this.loading())
	{
		var
			oParameters = {
				'Login': this.login()
			}
		;

		this.loading(true);
		Ajax.send('CreateAndLoginCurrentUser', oParameters, this.onCreateResponse, this);
	}
};


CCreateChatUserPopup.prototype.cancelPopup = function ()
{
	this.loading(false);
	this.closePopup();
};

/**
 * @param {Object} oResponse
 * @param {Object} oRequest
 */
 CCreateChatUserPopup.prototype.onCreateResponse = function (oResponse, oRequest)
{
	this.loading(false);

	if (!oResponse.Result)
	{
		Api.showErrorByCode(oResponse, TextUtils.i18n('%MODULENAME%/ERROR_CREATE_USER'));
	}
	else
	{
		Screens.showReport(TextUtils.i18n('%MODULENAME%/USER_SUCCESSFULLY_CREATED'));
		if (oResponse.Result.authToken) {
			this.mainView.initChat(oResponse.Result.authToken);
			this.closePopup();
			this.mainView.showIframe();
		}
	}
};

module.exports = new CCreateChatUserPopup();
