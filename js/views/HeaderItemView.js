'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),
	Ajax = require('modules/%ModuleName%/js/Ajax.js'),
	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
	WindowOpener = require('%PathToCoreWebclientModule%/js/WindowOpener.js'),	
	CAbstractHeaderItemView = require('%PathToCoreWebclientModule%/js/views/CHeaderItemView.js'),
	Settings = require('modules/%ModuleName%/js/Settings.js')
;

function CHeaderItemView()
{
	CAbstractHeaderItemView.call(this, TextUtils.i18n('%MODULENAME%/ACTION_SHOW_CHAT'));

	this.iAutoCheckMailTimer = -1;
	this.unseenCount = ko.observable(0);

	this.mainHref = ko.computed(function () {
		return this.hash();
	}, this);

	this.getUnreadCounter();
}

CHeaderItemView.prototype.getUnreadCounter = function () {
	Ajax.send('GetUnreadCounter', {}, function(oResponse) {
		this.unseenCount(oResponse.Result);
		this.setAutocheckTimer();
	}, this);
};

CHeaderItemView.prototype.setAutocheckTimer = function ()
{
	clearTimeout(this.iAutoCheckMailTimer);

	if (true/*UserSettings.AutoRefreshIntervalMinutes > 0*/)
	{
		this.iAutoCheckMailTimer = setTimeout(_.bind(function () {
			this.getUnreadCounter();
		}, this), Settings.UnreadCounterIntervalInSeconds * 1000);
	}
};

CHeaderItemView.prototype.onChatClick = function (data, event)
{
	WindowOpener.open('?chat', 'Chat');
};

_.extendOwn(CHeaderItemView.prototype, CAbstractHeaderItemView.prototype);

CHeaderItemView.prototype.ViewTemplate = '%ModuleName%_HeaderItemView';

var HeaderItemView = new CHeaderItemView();

HeaderItemView.allowChangeTitle(true);

module.exports = HeaderItemView;
