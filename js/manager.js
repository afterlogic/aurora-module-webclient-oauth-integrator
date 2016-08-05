'use strict';

module.exports = function (oAppData, iUserRole, bPublic) {
	var
		_ = require('underscore'),
		ko = require('knockout'),
		
		TextUtils = require('modules/CoreClient/js/utils/Text.js'),
				
		Ajax = require('modules/CoreClient/js/Ajax.js'),
		App = require('modules/CoreClient/js/App.js'),
		
		Settings = require('modules/%ModuleName%/js/Settings.js'),
		oSettings = _.extend({}, oAppData[Settings.ServerModuleName] || {}, oAppData['%ModuleName%'] || {}),
		
		bAdminUser = iUserRole === Enums.UserRole.SuperAdmin,
		bPowerUser = iUserRole === Enums.UserRole.PowerUser
	;

	Settings.init(oSettings);
	
	return {
		start: function (ModulesManager) {
			App.subscribeEvent('StandardLoginForm::ConstructView::after', function (oParams) {
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
			
			if (bAdminUser)
			{
				ModulesManager.run('AdminPanelClient', 'registerAdminPanelTab', [
					function () { return require('modules/%ModuleName%/js/views/AdminSettingsView.js'); },
					Settings.HashModuleName,
					TextUtils.i18n('%MODULENAME%/LABEL_ES_SETTINGS_TAB')
				]);
			}
		},
		getCreateLoginPasswordView: function () {
			if (bPowerUser)
			{
				return require('modules/%ModuleName%/js/views/CreateLoginPasswordView.js');
			}
		}
	};
};
