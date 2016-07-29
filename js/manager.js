'use strict';

module.exports = function (oAppData, iUserRole, bPublic) {
	var
		ko = require('knockout'),
				
		Ajax = require('modules/CoreClient/js/Ajax.js'),
		App = require('modules/CoreClient/js/App.js')
	;
	
	return {
		start: function (ModulesManager) {
			App.subscribeEvent('BasicAuthClient::ConstructView::after', function (oParams) {
				if ('CLoginView' === oParams.Name)
				{
					oParams.View.externalAuthClick = function (sSocialName) {
						window.location.href = '?external-services=' + sSocialName;
					};
					
					oParams.View.externalServices = ko.observableArray([]);
					Ajax.send('ExternalServices', 'GetServices', null, function (oResponse) {
						oParams.View.externalServices(oResponse.Result);
					}, this);
				}
			});
		}
	};
};
