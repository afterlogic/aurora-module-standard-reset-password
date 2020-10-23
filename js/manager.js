'use strict';


module.exports = function (oAppData) {
	var
		TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),

		App = require('%PathToCoreWebclientModule%/js/App.js'),
		
		Settings = require('modules/%ModuleName%/js/Settings.js'),
		
		bAnonimUser = App.getUserRole() === Enums.UserRole.Anonymous
	;
	
	Settings.init(oAppData);
	
	if (!App.isPublic() && bAnonimUser)
	{
		if (App.isMobile())
		{
			return {
				/**
				 * Returns reset password view screen as is.
				 */
				getResetPasswordScreenView: function () {
					return require('modules/%ModuleName%/js/views/ResetPasswordFormView.js');
				},
				
				getHashModuleName: function () {
					return Settings.HashModuleName;
				}
			};
		}
		else
		{
			return {
				getScreens: function () {
					var oScreens = {};
					oScreens[Settings.HashModuleName] = function () {
						return require('modules/%ModuleName%/js/views/ResetPasswordFormView.js');
					};
					return oScreens;
				}
			};
		}
	}
	else if (App.isUserNormalOrTenant())
	{
		return {
			start: function (ModulesManager) {
				ModulesManager.run('SettingsWebclient', 'registerSettingsTab', [
					function () { return require('modules/%ModuleName%/js/views/ResetPasswordSettingsFormView.js'); }, 
					Settings.HashModuleName, 
					TextUtils.i18n('%MODULENAME%/LABEL_SETTINGS_TAB')
				]);
			}
		};
	}
	
	return null;
};
