'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),
	
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
	
	App = require('%PathToCoreWebclientModule%/js/App.js')
;

module.exports = {
	ServerModuleName: 'OAuthIntegratorWebclient',
	HashModuleName: 'oauth-integrator',
	
	AuthModuleName: 'StandardAuth',
	OnlyPasswordForAccountCreate: true,
	userAccountLogin: ko.observable(''),
	userAccountsCount: ko.observable(0),
	
	EOAuthIntegratorError: {},
	
	Services: [],
	
	/**
	 * Initializes settings from AppData object section of this module.
	 * 
	 * @param {Object} oAppDataSection Object contained module settings.
	 */
	init: function (oAppDataSection)
	{
		if (oAppDataSection)
		{
			this.AuthModuleName = Types.pString(oAppDataSection.AuthModuleName);
			this.OnlyPasswordForAccountCreate = !!oAppDataSection.OnlyPasswordForAccountCreate;
			this.Services = _.isArray(oAppDataSection.Services) ? oAppDataSection.Services : [];
			this.EOAuthIntegratorError = oAppDataSection.EOAuthIntegratorError ? oAppDataSection.EOAuthIntegratorError : {};
		}
		App.registerUserAccountsCount(this.userAccountsCount);
	},
	
	/**
	 * Sets user auth account login.
	 * 
	 * @param {string} sUserAccountLogin
	 */
	setUserAccountLogin: function (sUserAccountLogin)
	{
		this.userAccountLogin(Types.pString(sUserAccountLogin));
	},
	
	/**
	 * Updates settings that is edited by administrator.
	 * 
	 * @param {object} oServices Object with services settings.
	 */
	updateAdmin: function (oServices)
	{
		_.each(this.Services, function (oService) {
			var oNewService = oServices[oService.Name];
			if (oNewService)
			{
				oService.EnableModule = oNewService.EnableModule;
				oService.Id = oNewService.Id;
				oService.Secret = oNewService.Secret;
			}
		});
	}
};
