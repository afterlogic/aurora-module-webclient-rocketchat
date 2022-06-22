'use strict';

var
	_ = require('underscore'),
	
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
	Ajax = require('%PathToCoreWebclientModule%/js/Ajax.js')
;

module.exports = {
	ServerModuleName: '%ModuleName%',
	HashModuleName: 'chat',

	ChatUrl: '',
	ChatAuthToken: '',
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

		Ajax.send(this.ServerModuleName,'InitChat', {}, function(oResponse) {
			if(oResponse.Result) {
				this.ChatAuthToken = oResponse.Result['authToken'];
			} else {
				this.ChatAuthToken = '';
			}
		}, this);
	}
};
