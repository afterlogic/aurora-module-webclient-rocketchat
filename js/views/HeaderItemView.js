'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),
	Ajax = require('modules/%ModuleName%/js/Ajax.js'),
	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
	
	CAbstractHeaderItemView = require('%PathToCoreWebclientModule%/js/views/CHeaderItemView.js')
;

function CHeaderItemView()
{
	CAbstractHeaderItemView.call(this, TextUtils.i18n('%MODULENAME%/ACTION_SHOW_CHAT'));
	this.iAutoCheckMailTimer = -1;
	this.unseenCount = ko.observable(0);
	
	this.getUnreadCounter();
}

CHeaderItemView.prototype.getUnreadCounter = function () {
	Ajax.send('GetUnreadCounter', {}, function(oResponse) {
		this.unseenCount(oResponse.Result);
		this.setAutocheckTimer();
	}, this);
}

CHeaderItemView.prototype.setAutocheckTimer = function ()
{
	clearTimeout(this.iAutoCheckMailTimer);

	if (true/*UserSettings.AutoRefreshIntervalMinutes > 0*/)
	{
		this.iAutoCheckMailTimer = setTimeout(_.bind(function () {
			this.getUnreadCounter();
		}, this), 15 * 1000);
	}
};

_.extendOwn(CHeaderItemView.prototype, CAbstractHeaderItemView.prototype);

var HeaderItemView = new CHeaderItemView();

HeaderItemView.allowChangeTitle(true);

module.exports = HeaderItemView;
