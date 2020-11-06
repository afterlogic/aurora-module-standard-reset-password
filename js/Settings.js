'use strict';

var
	_ = require('underscore'),
	
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js')
;

module.exports = {
	HashModuleName: 'reset-password',
	
	BottomInfoHtmlText: '',
	CustomLogoUrl: '',
	RecoveryEmail: '',
	RecoveryEmailConfirmed: false,

	// settings available only to superadmin
	RecoveryLinkLifetimeMinutes: 15,
	NotificationEmail: '',
	NotificationType: '',
	NotificationHost: '',
	NotificationPort: 25,
	NotificationUseSsl: false,
	NotificationUseAuth: false,
	NotificationLogin: '',
	HasNotificationPassword: false,
	
	/**
	 * Initializes settings from AppData object sections.
	 * 
	 * @param {Object} oAppData Object contained modules settings.
	 */
	init: function (oAppData)
	{
		var oAppDataSection = oAppData['%ModuleName%'];
		
		if (!_.isEmpty(oAppDataSection))
		{
			this.HashModuleName = Types.pString(oAppDataSection.HashModuleName, this.HashModuleName);
			
			this.BottomInfoHtmlText = Types.pString(oAppDataSection.BottomInfoHtmlText, this.BottomInfoHtmlText);
			this.CustomLogoUrl = Types.pString(oAppDataSection.CustomLogoUrl, this.CustomLogoUrl);
			this.RecoveryEmail = Types.pString(oAppDataSection.RecoveryEmail, this.RecoveryEmail);
			this.RecoveryEmailConfirmed = Types.pBool(oAppDataSection.RecoveryEmailConfirmed, this.RecoveryEmailConfirmed);

			// settings available only to superadmin
			this.RecoveryLinkLifetimeMinutes = Types.pInt(oAppDataSection.RecoveryLinkLifetimeMinutes, this.RecoveryLinkLifetimeMinutes);
			this.NotificationEmail = Types.pString(oAppDataSection.NotificationEmail, this.NotificationEmail);
			this.NotificationType = Types.pString(oAppDataSection.NotificationType, this.NotificationType);
			this.NotificationHost = Types.pString(oAppDataSection.NotificationHost, this.NotificationHost);
			this.NotificationPort = Types.pInt(oAppDataSection.NotificationPort, this.NotificationPort);
			this.NotificationUseSsl = Types.pBool(oAppDataSection.NotificationUseSsl, this.NotificationUseSsl);
			this.NotificationUseAuth = Types.pBool(oAppDataSection.NotificationUseAuth, this.NotificationUseAuth);
			this.NotificationLogin = Types.pString(oAppDataSection.NotificationLogin, this.NotificationLogin);
			this.HasNotificationPassword = Types.pBool(oAppDataSection.HasNotificationPassword, this.HasNotificationPassword);
		}
	},
	
	update: function (sRecoveryEmail)
	{
		this.RecoveryEmail = Types.pString(sRecoveryEmail, this.RecoveryEmail);
		this.RecoveryEmailConfirmed = this.RecoveryEmail === '';
	},

	updateSuperAdmin: function (oParameters)
	{
		this.RecoveryLinkLifetimeMinutes = oParameters.RecoveryLinkLifetimeMinutes;
		this.NotificationEmail = oParameters.NotificationEmail;
		this.NotificationType = oParameters.NotificationType;
		if (this.NotificationType === 'smtp')
		{
			this.NotificationHost = oParameters.NotificationHost;
			this.NotificationPort = oParameters.NotificationPort;
			this.NotificationUseSsl = oParameters.NotificationUseSsl;
			if (this.NotificationUseSsl)
			{
				this.NotificationUseAuth = oParameters.NotificationUseAuth;
				this.HasNotificationPassword = Types.isNonEmptyString(oParameters.NotificationPassword);
			}
		}
	}
};
