'use strict';

var
	_ = require('underscore')
;

module.exports = {
	ServerModuleName: 'ExternalServices',
	HashModuleName: 'externalServices',
	
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
			this.Services = _.isArray(oAppDataSection.Services) ? oAppDataSection.Services : [];
		}
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
