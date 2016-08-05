'use strict';

var
	ko = require('knockout'),
	
	App = require('modules/CoreClient/js/App.js'),
	
	Ajax = require('modules/CoreClient/js/Ajax.js'),
	
	Settings = require('modules/%ModuleName%/js/Settings.js')
;

/**
 * @constructor
 */
function CCreateLoginPasswordView()
{
	this.visibleSetPasswordForm = ko.observable(Settings.OnlyPasswordForAccountCreate);
	this.password = ko.observable('');
}

CCreateLoginPasswordView.prototype.ViewTemplate = '%ModuleName%_CreateLoginPasswordView';

/**
 * Opens settings tab that can create account to authenticate.
 */
CCreateLoginPasswordView.prototype.setPassword = function ()
{
//	App.broadcastEvent('OpenAuthAccountSettingTab');
};

CCreateLoginPasswordView.prototype.onRoute = function ()
{
	Ajax.send();
};

module.exports = new CCreateLoginPasswordView();
