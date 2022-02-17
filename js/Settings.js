'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),
	
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
	Screens = require('%PathToCoreWebclientModule%/js/Screens.js'),
	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js')
;

module.exports = {
	ServerModuleName: '%ModuleName%',
	HashModuleName: 'chat',

	ChatUrl: '',
	ChatAuthToken: '',
	Registered: false,
	SuggestedUserName: '',
	AutocreateChatAccountOnFirstLogin: false,
	ShowRecommendationToUseChat: false,
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
			this.Registered = Types.pBool(oAppDataSection.Registered);
			this.AutocreateChatAccountOnFirstLogin = Types.pBool(oAppDataSection.AutocreateChatAccountOnFirstLogin);
			this.ShowRecommendationToUseChat = Types.pBool(oAppDataSection.ShowRecommendationToUseChat);
			this.SuggestedUserName = Types.pString(oAppDataSection.SuggestedUserName);
			this.UnreadCounterIntervalInSeconds = Types.pInt(oAppDataSection.UnreadCounterIntervalInSeconds, this.UnreadCounterIntervalInSeconds);
			this.AdminUsername = Types.pString(oAppDataSection.AdminUsername);
			this.registered = ko.observable(this.Registered);

			if (!this.registered() && !this.AutocreateChatAccountOnFirstLogin && this.ShowRecommendationToUseChat) {
					
				setTimeout(function () {
					Screens.showReport(TextUtils.i18n('%MODULENAME%/RECOMENDATION_TO_USE_CHAT'), 0);

					$('.report_panel.report a').on('click', function () {
						Screens.hideReport();
					});
				}, 100);
			}

		}
	},

	updateAdmin: function (parameters)
	{
		this.ChatUrl = Types.pString(parameters.ChatUrl);
		this.AdminUsername = Types.pString(parameters.AdminUsername);
	}
};
