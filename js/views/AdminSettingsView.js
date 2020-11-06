'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),
	
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),

	ModulesManager = require('%PathToCoreWebclientModule%/js/ModulesManager.js'),

	CAbstractSettingsFormView = ModulesManager.run('AdminPanelWebclient', 'getAbstractSettingsFormViewClass'),

	Settings = require('modules/%ModuleName%/js/Settings.js')
;

/**
* @constructor
*/
function CAdminSettingsView()
{
	CAbstractSettingsFormView.call(this, '%ModuleName%', 'UpdateAdminSettings');

	this.sFakePass = '      ';
	
	/* Editable fields */
	this.recoveryLinkLifetimeMinutes = ko.observable(Settings.RecoveryLinkLifetimeMinutes);
	this.notificationEmail = ko.observable(Settings.NotificationEmail);
	this.notificationType = ko.observable(Settings.NotificationType);
	this.notificationHost = ko.observable(Settings.NotificationHost);
	this.notificationPort = ko.observable(Settings.NotificationPort);
	this.notificationUseSsl = ko.observable(Settings.NotificationUseSsl);
	this.notificationUseAuth = ko.observable(Settings.NotificationUseAuth);
	this.notificationLogin = ko.observable(Settings.NotificationLogin);
	this.notificationPassword = ko.observable(Settings.HasNotificationPassword ? this.sFakePass : '');
	/*-- Editable fields */

	this.notificationUseSsl.subscribe(function () {
		var iPort = Types.pInt(this.notificationPort());
		if (this.notificationUseSsl())
		{
			if (iPort === 25)
			{
				this.notificationPort(465);
			}
		}
		else
		{
			if (iPort === 465)
			{
				this.notificationPort(25);
			}
		}
	}, this);
}

_.extendOwn(CAdminSettingsView.prototype, CAbstractSettingsFormView.prototype);

CAdminSettingsView.prototype.ViewTemplate = '%ModuleName%_AdminSettingsView';

CAdminSettingsView.prototype.getCurrentValues = function ()
{
	var aValues = [
		this.recoveryLinkLifetimeMinutes(),
		this.notificationEmail(),
		this.notificationType()
	];

	if (this.notificationType() === 'smtp')
	{
		aValues.push(this.notificationHost());
		aValues.push(this.notificationPort());
		aValues.push(this.notificationUseSsl());
		aValues.push(this.notificationUseAuth());
		if (this.notificationUseAuth())
		{
			aValues.push(this.notificationLogin());
			aValues.push(this.notificationPassword());
		}
	}

	return aValues;
};

CAdminSettingsView.prototype.revertGlobalValues = function ()
{
	this.recoveryLinkLifetimeMinutes(Settings.RecoveryLinkLifetimeMinutes);
	this.notificationEmail(Settings.NotificationEmail);
	this.notificationType(Settings.NotificationType);
	this.notificationHost(Settings.NotificationHost);
	this.notificationPort(Settings.NotificationPort);
	this.notificationUseSsl(Settings.NotificationUseSsl);
	this.notificationUseAuth(Settings.NotificationUseAuth);
	this.notificationLogin(Settings.NotificationLogin);
	this.notificationPassword(Settings.HasNotificationPassword ? this.sFakePass : '');
};

CAdminSettingsView.prototype.getParametersForSave = function ()
{
	var oParameters = {
		'RecoveryLinkLifetimeMinutes': Types.pInt(this.recoveryLinkLifetimeMinutes()),
		'NotificationEmail': this.notificationEmail(),
		'NotificationType': this.notificationType()
	};

	if (this.notificationType() === 'smtp')
	{
		oParameters['NotificationHost'] = this.notificationHost();
		oParameters['NotificationPort'] = Types.pInt(this.notificationPort());
		oParameters['NotificationUseSsl'] = this.notificationUseSsl();
		oParameters['NotificationUseAuth'] = this.notificationUseAuth();
		if (this.notificationUseAuth())
		{
			oParameters['NotificationLogin'] = this.notificationLogin();
			oParameters['NotificationPassword'] = this.notificationPassword() === this.sFakePass ? '' : this.notificationPassword();
		}
	}

	return oParameters;
};

/**
 * @param {Object} oParameters
 */
CAdminSettingsView.prototype.applySavedValues = function (oParameters)
{
	Settings.updateSuperAdmin(oParameters);
};

CAdminSettingsView.prototype.setAccessLevel = function (sEntityType, iEntityId)
{
	this.visible(sEntityType === '');
};

module.exports = new CAdminSettingsView();
