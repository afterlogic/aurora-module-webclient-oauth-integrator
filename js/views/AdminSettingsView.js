'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),
	
	ModulesManager = require('modules/CoreClient/js/ModulesManager.js'),
	CAbstractSettingsFormView = ModulesManager.run('SettingsClient', 'getAbstractSettingsFormViewClass'),
	
	Settings = require('modules/%ModuleName%/js/Settings.js')
;

/**
* @constructor
*/
function CAdminSettingsView()
{
	CAbstractSettingsFormView.call(this, Settings.ServerModuleName);
	
	/* Editable fields */
	this.services = ko.observable(this.getConvertedServices());
	/*-- Editable fields */
}

_.extendOwn(CAdminSettingsView.prototype, CAbstractSettingsFormView.prototype);

CAdminSettingsView.prototype.ViewTemplate = '%ModuleName%_AdminSettingsView';

/**
 * Returns services settings with knockout fields.
 * 
 * @returns {Array}
 */
CAdminSettingsView.prototype.getConvertedServices = function()
{
	var aConvertedServices = [];
	
	_.each(Settings.Services, function (oService) {
		aConvertedServices.push({
			name: oService.Name,
			displayName: oService.DisplayName,
			enable: ko.observable(!!oService.EnableModule),
			id: ko.observable(oService.Id),
			secret: ko.observable(oService.Secret)
		});
	});
	
	return aConvertedServices;
};

CAdminSettingsView.prototype.getCurrentValues = function()
{
	var aValues = [];
	
	_.each(this.services(), function (oService) {
		aValues.push(oService.enable());
		aValues.push(oService.id());
		aValues.push(oService.secret());
	});
	
	return aValues;
};

CAdminSettingsView.prototype.revertGlobalValues = function()
{
	this.services(this.getConvertedServices());
};

CAdminSettingsView.prototype.getParametersForSave = function ()
{
	var oParameters = {};
	
	_.each(this.services(), function (oService) {
		oParameters[oService.name] = {
			'EnableModule': oService.enable(),
			'Id': oService.id(),
			'Secret': oService.secret()
		};
	});
	
	return {
		'Services': oParameters
	};
};

CAdminSettingsView.prototype.applySavedValues = function (oParameters)
{
	Settings.updateAdmin(oParameters.Services);
};

CAdminSettingsView.prototype.setAccessLevel = function (sEntityType, iEntityId)
{
	this.visible(sEntityType === '');
};

module.exports = new CAdminSettingsView();
