'use strict';

const
	ko = require('knockout'),

	ContenteditableUtils = require('%PathToCoreWebclientModule%/js/utils/Contenteditable.js'),
	Utils = require('%PathToCoreWebclientModule%/js/utils/Common.js'),

	Settings = require('modules/%ModuleName%/js/Settings.js')
;

function CAddMeetingLinkToEventView()
{
	this.meetingLinkUrl = Settings.MeetingLinkUrl.replace(/[^\/]$/, '$&/'); //adding '/' at the end if it's missing
	this.allowAddMeetingLink = Settings.AllowAddMeetingLinkToEvent && Settings.MeetingLinkUrl.length !== 0;
	this.focusedDom = ko.observable(null);
}

CAddMeetingLinkToEventView.prototype.ViewTemplate = '%ModuleName%_AddMeetingLinkToEventView';

CAddMeetingLinkToEventView.prototype.init = function (view)
{
	this.isEditable = view.descriptionView.isEditable;

	this.descriptionDom = view.descriptionView.dataDom;
	this.descriptionHtml = view.descriptionView.dataHtml;
	this.descriptionFocus = view.descriptionView.dataFocus;
	this.descriptionFocus.subscribe(function () {
		if (this.descriptionFocus()) {
			this.focusedDom(this.descriptionDom());
		} else {
			this.savedRanges = ContenteditableUtils.getSelectionRanges();
			setTimeout(function () {
				if (!this.descriptionFocus() && !this.locationFocus()) {
					this.focusedDom(null);
				}
			}.bind(this), 100);
		}
	}, this);

	this.locationDom = view.locationView.dataDom;
	this.locationHtml = view.locationView.dataHtml;
	this.locationFocus = view.locationView.dataFocus;
	this.locationFocus.subscribe(function () {
		if (this.locationFocus()) {
			this.focusedDom(this.locationDom());
		} else {
			setTimeout(function () {
				if (!this.descriptionFocus() && !this.locationFocus()) {
					this.focusedDom(null);
				}
			}.bind(this), 100);
		}
	}, this);
};

CAddMeetingLinkToEventView.prototype.addMeetingLink = function ()
{
	const
		meetingLinkUrl = this.meetingLinkUrl,
		meetingId = Utils.getRandomHash(32),
		url = `${meetingLinkUrl}${meetingId}`,
		html = `<a href="${url}">${url}</a>`
	;
	if (this.focusedDom()) {
		// insert where the cursor is
		ContenteditableUtils.setSelectionRanges(this.savedRanges, true);
		this.focusedDom().focus();
		window.document.execCommand('insertHTML', false, html);
	} else {
		// insert at the end
		ContenteditableUtils.setCursorAtTheEnd(this.locationDom()[0]);
		window.document.execCommand('insertHTML', false, html);
	}
	if (this.descriptionFocus()) {
		this.descriptionHtml(this.descriptionDom().html());
	} else if (this.locationFocus()) {
		this.locationHtml(this.locationDom().html());
	}
//	if (this.descriptionDom && document.createRange && window.getSelection) {
//		console.log('this.descriptionDom', this.descriptionDom());
//		const
//			range = document.createRange(),
//			selection = window.getSelection()
//		;
//
//		range.selectNodeContents(this.descriptionDom());
//		selection.removeAllRanges();
//		selection.addRange(range);
//
//		window.document.execCommand('unlink');
//		this.currLink = null;
//		this.hideLinkPopup();
//	}
};

module.exports = new CAddMeetingLinkToEventView();
