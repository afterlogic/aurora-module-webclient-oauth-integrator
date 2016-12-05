'use strict';

module.exports = function (oAppData) {
	var
		_ = require('underscore'),
		$ = require('jquery'),
		ko = require('knockout'),
		
		TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
		Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
		
		Ajax = require('%PathToCoreWebclientModule%/js/Ajax.js'),
		App = require('%PathToCoreWebclientModule%/js/App.js'),
		
		Settings = require('modules/%ModuleName%/js/Settings.js'),
		oSettings = _.extend({}, oAppData[Settings.ServerModuleName] || {}, oAppData['%ModuleName%'] || {}),
		
		bNormalUser = App.getUserRole() === Enums.UserRole.NormalUser,
		bAnonymUser = App.getUserRole() === Enums.UserRole.Anonymous,
		
		fGetErrorMessageByCode = function (oError) {
			switch (oError.ErrorCode)
			{
				case Settings.EOAuthIntegratorError.ServiceNotAllowed: return TextUtils.i18n('%MODULENAME%/ERROR_SERVICE_NOT_ALLOWED');
				case Settings.EOAuthIntegratorError.AccountNotAllowedToLogIn: return TextUtils.i18n('%MODULENAME%/ERROR_ACCOUNT_NOT_ALLOWED');
				case Settings.EOAuthIntegratorError.AccountAlreadyConnected: return TextUtils.i18n('%MODULENAME%/ERROR_ACCOUNT_ALREADY_CONNECTED');
			}
			return '';
		}

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
							$.cookie('oauth-redirect', 'CLoginView' === oParams.Name ? 'login' : 'register');
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
			},
			getErrorMessageByCode: fGetErrorMessageByCode
		};
	}
	
	if (bNormalUser)
	{
		return {
			start: function (ModulesManager) {
				var fGetAccounts = function () {
					Ajax.send(Settings.ServerModuleName, 'GetAccounts', null, function (oResponse) {
						Settings.userAccountsCount(_.isArray(oResponse.Result) ? oResponse.Result.length : 0);
					});
				};
				App.subscribeEvent('OAuthAccountChange::after', function () {
					fGetAccounts();
				});
				fGetAccounts();
				App.subscribeEvent('ReceiveAjaxResponse::after', function (oParams) {
					if (oParams.Request.Module === 'StandardAuth' && oParams.Request.Method === 'GetUserAccounts')
					{
						if (Types.isNonEmptyArray(oParams.Response.Result))
						{
							Settings.setUserAccountLogin(oParams.Response.Result[0].login);
						}
					}
				});
			},
			getCreateLoginPasswordView: function () {
				return require('modules/%ModuleName%/js/views/CreateLoginPasswordView.js');
			},
			getErrorMessageByCode: fGetErrorMessageByCode
		};
	}
	
	return null;
};
