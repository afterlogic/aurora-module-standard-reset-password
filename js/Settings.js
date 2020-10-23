'use strict';

var
	_ = require('underscore'),
	
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js')
;

module.exports = {
	ServerModuleName: '%ModuleName%',
	HashModuleName: 'reset-password',
	
	BottomInfoHtmlText: '',
	CustomLogoUrl: '',
	InfoText: '',
	RecoveryEmail: '',
	
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
			this.ServerModuleName = Types.pString(oAppDataSection.ServerModuleName, this.ServerModuleName);
			this.HashModuleName = Types.pString(oAppDataSection.HashModuleName, this.HashModuleName);
			
			this.BottomInfoHtmlText = Types.pString(oAppDataSection.BottomInfoHtmlText, this.BottomInfoHtmlText);
			this.CustomLogoUrl = Types.pString(oAppDataSection.CustomLogoUrl, this.CustomLogoUrl);
			this.InfoText = Types.pString(oAppDataSection.InfoText, this.InfoText);
			this.RecoveryEmail = Types.pString(oAppDataSection.RecoveryEmail, this.RecoveryEmail);
		}
	},
	
	update: function (sRecoveryEmail) {
		this.RecoveryEmail = Types.pString(sRecoveryEmail, this.RecoveryEmail);
	}
};
