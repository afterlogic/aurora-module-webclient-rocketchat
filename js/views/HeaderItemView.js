'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),

	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),

	Ajax = require('modules/%ModuleName%/js/Ajax.js'),
	CAbstractHeaderItemView = require('%PathToCoreWebclientModule%/js/views/CHeaderItemView.js'),
	WindowOpener = require('%PathToCoreWebclientModule%/js/WindowOpener.js')
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
	Ajax.send('GetUnreadCounter', {}, function (oResponse) {
		this.unseenCount(Types.pInt(oResponse.Result));
	}, this);
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
