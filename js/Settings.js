'use strict';

var
	_ = require('underscore'),
	
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js')
;

module.exports = {
	ServerModuleName: '%ModuleName%',
	HashModuleName: 'chat',

	ChatUrl: '',
	ChatAuthToken: '',
	UnreadCounterIntervalInSeconds: 15,

	AdminUsername: '',

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
			this.ChatAuthToken = Types.pString(oAppDataSection.ChatAuthToken);
			this.UnreadCounterIntervalInSeconds = Types.pInt(oAppDataSection.UnreadCounterIntervalInSeconds, this.UnreadCounterIntervalInSeconds);

			this.AdminUsername = Types.pString(oAppDataSection.AdminUsername);
		}
	},

	updateAdmin: function (parameters)
	{
		this.ChatUrl = Types.pString(parameters.ChatUrl);
		this.AdminUsername = Types.pString(parameters.AdminUsername);
	}
};
