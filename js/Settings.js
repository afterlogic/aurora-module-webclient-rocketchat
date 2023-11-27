'use strict';

var
	_ = require('underscore'),
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js')
;

module.exports = {
	ServerModuleName: '%ModuleName%',
	HashModuleName: 'chat',

	ChatUrl: '',
	AllowAddMeetingLinkToEvent: false,
	MeetingLinkUrl: '',

	/**
	 * Initializes settings from AppData object sections.
	 * 
	 * @param {Object} oAppData Object contained modules settings.
	 */
	init: function (oAppData)
	{
		var oAppDataSection = oAppData['%ModuleName%'];
		
		if (!_.isEmpty(oAppDataSection))
		{
			this.ChatUrl = Types.pString(oAppDataSection.ChatUrl);
			this.AllowAddMeetingLinkToEvent = Types.pBool(oAppDataSection.AllowAddMeetingLinkToEvent);
			this.MeetingLinkUrl = Types.pString(oAppDataSection.MeetingLinkUrl);
		}
	}
};
