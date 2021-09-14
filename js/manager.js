'use strict';

module.exports = function (oAppData) {
	var
		ko = require('knockout'),

		App = require('%PathToCoreWebclientModule%/js/App.js'),

		Ajax = require('modules/%ModuleName%/js/Ajax.js'),
		
		TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),

		Settings = require('modules/%ModuleName%/js/Settings.js'),
		
		WindowOpener = require('%PathToCoreWebclientModule%/js/WindowOpener.js'),
		
		HeaderItemView = null
	;
	
	Settings.init(oAppData);
	
	var sAppHash = Settings.AppName ? TextUtils.getUrlFriendlyName(Settings.AppName) : Settings.HashModuleName; 
	
	if (App.isUserNormalOrTenant())
	{
		var result = {
			/**
			 * Returns list of functions that are return module screens.
			 * 
			 * @returns {Object}
			 */
			getScreens: function ()
			{
				var oScreens = {};

				oScreens[Settings.HashModuleName] = function () {
					return require('modules/%ModuleName%/js/views/MainView.js');
				};
				
				return oScreens;
			}
		};
		if (!App.isNewTab())
		{
			result.start = function (ModulesManager) {
				ModulesManager.run('SettingsWebclient', 'registerSettingsTab', [function () { return require('modules/%ModuleName%/js/views/RocketChatSettingsPaneView.js'); }, Settings.HashModuleName, TextUtils.i18n('%MODULENAME%/LABEL_SETTINGS_TAB')]);

				App.subscribeEvent('Logout', function () {
						var $chatIframe = $('#rocketchat_iframe').contents().find('#chatIframe');
						if ($chatIframe && $chatIframe.get(0)) {
							$chatIframe.get(0).contentWindow.postMessage({
								externalCommand: 'logout'
							}, '*');
						}
				});

				App.subscribeEvent('ContactsWebclient::AddCustomCommand', function (oParams) {
					oParams.Callback({
						'Text': TextUtils.i18n('%MODULENAME%/ACTION_CHAT_WITH_CONTACT'),
						'CssClass': 'chat',
						'Handler': function () {
							var
								iScreenWidth = window.screen.width,
								iWidth = 360,
								iLeft = Math.ceil((iScreenWidth - iWidth) / 2),
						
								iScreenHeight = window.screen.height,
								iHeight = 600,
								iTop = Math.ceil((iScreenHeight - iHeight) / 2)
							;
					
							WindowOpener.open('?chat-direct=' + this.email(), 'Chat', false, ',width=' + iWidth + ',height=' + iHeight + ',top=' + iTop + ',left=' + iLeft);
						},
						'Visible': ko.computed(function () { 
							return oParams.Contact.team() && !oParams.Contact.itsMe()
						})
					});
				});
			};
			/**
			 * Returns object of header item view of the module.
			 * 
			 * @returns {Object}
			 */
			result.getHeaderItem = function () {
				if (HeaderItemView === null)
				{
					HeaderItemView = require('modules/%ModuleName%/js/views/HeaderItemView.js');
				}

				return {
					item: HeaderItemView,
					name: sAppHash
				};
			};
			
			// result.getHeaderItem = function ()
			// {
			// 	var 
			// 		CHeaderItemView = require('%PathToCoreWebclientModule%/js/views/CHeaderItemView.js'),
			// 		oHeaderEntry = 	{};
			// 	;

			// 	if (HeaderItemView === null)
			// 	{
			// 		HeaderItemView = new CHeaderItemView(Settings.AppName || TextUtils.i18n('%MODULENAME%/LABEL_SETTINGS_TAB'));
			// 	}
			// 	oHeaderEntry = {
			// 		item: HeaderItemView,
			// 		name: sAppHash
			// 	};
				
			// 	return oHeaderEntry;
			// }
		}

		return result;
	}
	
	return null;
};
