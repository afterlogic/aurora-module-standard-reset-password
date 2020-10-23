'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),
	
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
	
	ModulesManager = require('%PathToCoreWebclientModule%/js/ModulesManager.js'),
	CAbstractSettingsFormView = ModulesManager.run('SettingsWebclient', 'getAbstractSettingsFormViewClass'),
	
	Settings = require('modules/%ModuleName%/js/Settings.js')
;

/**
 * @constructor
 */
function CResetPasswordSettingsFormView()
{
	CAbstractSettingsFormView.call(this, Settings.ServerModuleName);
	
	this.recoveryEmail = ko.observable(Settings.RecoveryEmail);
}

_.extendOwn(CResetPasswordSettingsFormView.prototype, CAbstractSettingsFormView.prototype);

CResetPasswordSettingsFormView.prototype.ViewTemplate = '%ModuleName%_ResetPasswordSettingsFormView';

CResetPasswordSettingsFormView.prototype.getCurrentValues = function ()
{
	return [
		this.recoveryEmail()
	];
};

CResetPasswordSettingsFormView.prototype.revertTeamValues = function ()
{
	this.recoveryEmail(Settings.RecoveryEmail);
};

CResetPasswordSettingsFormView.prototype.getParametersForSave = function ()
{
	return {
		'RecoveryEmail': this.recoveryEmail()
	};
};

CResetPasswordSettingsFormView.prototype.applySavedValues = function (oParameters)
{
	Settings.update(oParameters.RecoveryEmail);
};

module.exports = new CResetPasswordSettingsFormView();
