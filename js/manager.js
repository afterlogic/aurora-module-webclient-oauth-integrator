'use strict';

module.exports = function (oAppData, iUserRole, bPublic) {
	var
		_ = require('underscore'),
		ko = require('knockout'),
		
		Ajax = require('%PathToCoreWebclientModule%/js/Ajax.js'),
		App = require('%PathToCoreWebclientModule%/js/App.js'),
		
		Settings = require('modules/%ModuleName%/js/Settings.js'),
		oSettings = _.extend({}, oAppData[Settings.ServerModuleName] || {}, oAppData['%ModuleName%'] || {}),
		
		bPowerUser = iUserRole === Enums.UserRole.NormalUser,
		bAnonymUser = iUserRole === Enums.UserRole.Anonymous
	;

	Settings.init(oSettings);
	
	if (bAnonymUser)
	{
		return {
			start: function (ModulesManager) {
				Settings.oauthServices = ko.observableArray([]);
				
				var fInitialize = function (oParams) {
					if ('CLoginView' === oParams.Name || 'CRegisterView' === oParams.Name)
					{
						oParams.View.externalAuthClick = function (sSocialName) {
							window.location.href = '?oauth=' + sSocialName;
						};

						oParams.View.oauthServices = Settings.oauthServices;
					}
				};
				
				Ajax.send(Settings.ServerModuleName, 'GetServices', null, function (oResponse) {
					Settings.oauthServices(oResponse.Result);
				}, this);

				App.subscribeEvent('StandardLoginFormWebclient::ConstructView::after', fInitialize);
				App.subscribeEvent('StandardRegisterFormWebclient::ConstructView::after', fInitialize);
			}
		};
	}
	
	if (bPowerUser)
	{
		return {
			start: function (ModulesManager) {
				Ajax.send(Settings.AuthModuleName, 'GetUserAccountLogin', null, function (oResponse) {
					if (oResponse.Result)
					{
						Settings.setUserAccountLogin(oResponse.Result);
					}
				}, this);
			},
			getCreateLoginPasswordView: function () {
				return require('modules/%ModuleName%/js/views/CreateLoginPasswordView.js');
			}
		};
	}
	
	return {};
};
