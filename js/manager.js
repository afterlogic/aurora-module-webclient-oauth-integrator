'use strict';

module.exports = function (oAppData, iUserRole, bPublic) {
	var
		_ = require('underscore'),
		ko = require('knockout'),
		
		TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
				
		Ajax = require('%PathToCoreWebclientModule%/js/Ajax.js'),
		App = require('%PathToCoreWebclientModule%/js/App.js'),
		
		Settings = require('modules/%ModuleName%/js/Settings.js'),
		oSettings = _.extend({}, oAppData[Settings.ServerModuleName] || {}, oAppData['%ModuleName%'] || {}),
		
		bAdminUser = iUserRole === Enums.UserRole.SuperAdmin,
		bPowerUser = iUserRole === Enums.UserRole.PowerUser,
		bAnonymUser = iUserRole === Enums.UserRole.Anonymous
	;

	Settings.init(oSettings);
	
	if (bAnonymUser)
	{
		return {
			start: function (ModulesManager) {
				App.subscribeEvent('StandardLoginFormWebclient::ConstructView::after', function (oParams) {
					if ('CLoginView' === oParams.Name)
					{
						oParams.View.externalAuthClick = function (sSocialName) {
							window.location.href = '?external-services=' + sSocialName;
						};

						oParams.View.externalServices = ko.observableArray([]);
						Ajax.send('OAuthIntegratorWebclient', 'GetServices', null, function (oResponse) {
							oParams.View.externalServices(oResponse.Result);
						}, this);
					}
				});
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
	
	if (bAdminUser)
	{
		return {
			start: function (ModulesManager) {
				ModulesManager.run('AdminPanelWebclient', 'registerAdminPanelTab', [
					function () { return require('modules/%ModuleName%/js/views/AdminSettingsView.js'); },
					Settings.HashModuleName,
					TextUtils.i18n('%MODULENAME%/LABEL_ES_SETTINGS_TAB')
				]);
			}
		};
	}
	
	return {};
};
