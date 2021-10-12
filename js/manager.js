'use strict';

module.exports = function (oAppData) {
	var
		ko = require('knockout'),

		App = require('%PathToCoreWebclientModule%/js/App.js'),

		TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),

		Settings = require('modules/%ModuleName%/js/Settings.js'),
		
		WindowOpener = require('%PathToCoreWebclientModule%/js/WindowOpener.js'),
		
		oOpenedWindows = [],
		
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
				// init screen so the module could interact with chat in iframe
				var Screens = require('%PathToCoreWebclientModule%/js/Screens.js');
				Screens.initHiddenView(Settings.HashModuleName);

				if (Settings.ChatUrl !== '') {
					ModulesManager.run('SettingsWebclient', 'registerSettingsTab', [function () { return require('modules/%ModuleName%/js/views/RocketChatSettingsPaneView.js'); }, Settings.HashModuleName, TextUtils.i18n('%MODULENAME%/LABEL_SETTINGS_TAB')]);
				}
				
				App.subscribeEvent('Logout', function () {
					$.removeCookie('RocketChatAuthToken');
					$.removeCookie('RocketChatUserId');
					if ($('#rocketchat_iframe')) {
						$('#rocketchat_iframe').hide();
						$('#rocketchat_iframe').get(0).contentWindow.postMessage({
							externalCommand: 'logout'
						}, '*');
					}
				});

				if (Settings.ChatUrl !== '') {
					App.subscribeEvent('ContactsWebclient::AddCustomCommand', function (oParams) {
						oParams.Callback({
							'Text': TextUtils.i18n('%MODULENAME%/ACTION_CHAT_WITH_CONTACT'),
							'CssClass': 'chat',
							'Handler': function () {
								var oWin = oOpenedWindows[this.uuid()];
								if (oWin && !oWin.closed)
								{
									oWin.focus();
								}
								else
								{
									var
										iScreenWidth = window.screen.width,
										iWidth = 360,
										iLeft = Math.ceil((iScreenWidth - iWidth) / 2),

										iScreenHeight = window.screen.height,
										iHeight = 600,
										iTop = Math.ceil((iScreenHeight - iHeight) / 2),

										sUrl = '?chat-direct=' + this.uuid() + '&' + new Date().getTime(),
										sName = 'Chat',
										sSize = ',width=' + iWidth + ',height=' + iHeight + ',top=' + iTop + ',left=' + iLeft
									;
									oWin = WindowOpener.open(sUrl, sName, false, sSize);
									if (oWin)
									{
										oOpenedWindows[this.uuid()] = oWin;
									}
								}
							},
							'Visible': ko.computed(function () { 
								return oParams.Contact.team() && !oParams.Contact.itsMe();
							})
						});
					});
				}
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
				if (Settings.ChatUrl !== '') {
					return {
						item: HeaderItemView,
						name: sAppHash
					};
				}
			};
		}

		return result;
	}
	
	return null;
};
