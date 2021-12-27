'use strict';

var
	Ajax = require('%PathToCoreWebclientModule%/js/Ajax.js'),

	Settings = require('modules/%ModuleName%/js/Settings.js')
;

Ajax.registerAbortRequestHandler('%ModuleName%', function (oRequest, oOpenedRequest) {
	switch (oRequest.Method) {
		case 'GetSettings':
			return oOpenedRequest.Method === 'GetSettings';
		case 'GetRocketChatSettings':
			return oOpenedRequest.Method === 'GetRocketChatSettings';
	}
	
	return false;
});

module.exports = {
	send: function (sMethod, oParameters, fResponseHandler, oContext, sServerModuleName) {
		Ajax.send(
			sServerModuleName ? sServerModuleName : Settings.ServerModuleName,
			sMethod,
			oParameters,
			fResponseHandler,
			oContext
		);
	}
};
