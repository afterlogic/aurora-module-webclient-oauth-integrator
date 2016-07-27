'use strict';

module.exports = function (oAppData, iUserRole, bPublic) {
	var 
		App = require('modules/CoreClient/js/App.js')
	;

	return {
		start: function (ModulesManager) {
			App.subscribeEvent('BasicAuthClient::ConstructView::after', function (oParams) {
				if ('CWrapLoginView' === oParams.Name)
				{
					oParams.View.externalAuthClick = function (sSocialName) {
						window.location.href = '?external-services=' + sSocialName;
					};
				}
			});
		}
	};
};
