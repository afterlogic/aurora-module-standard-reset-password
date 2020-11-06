'use strict';


module.exports = function (oAppData) {
	var
		_ = require('underscore'),

		TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),

		App = require('%PathToCoreWebclientModule%/js/App.js'),
		
		Settings = require('modules/%ModuleName%/js/Settings.js'),
		
		bAnonymousUser = App.getUserRole() === Enums.UserRole.Anonymous,
		bSuperAdmin = App.getUserRole() === Enums.UserRole.SuperAdmin
	;
	
	Settings.init(oAppData);
	
	if (!App.isPublic() && bAnonymousUser)
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
				start: function (ModulesManager)
				{
					App.subscribeEvent('AnonymousUserForm::PopulateBeforeButtonsControllers', _.bind(function (oParams) {
						console.log('oParams', oParams);
						if (_.isFunction(oParams.RegisterBeforeButtonsController) && (oParams.ModuleName === 'StandardLoginFormWebclient' || oParams.ModuleName === 'MailLoginFormWebclient'))
						{
							oParams.RegisterBeforeButtonsController(require('modules/%ModuleName%/js/views/ForgotPasswordController.js'));
						}
					}, this));
				},
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
	else if (bSuperAdmin)
	{
		Settings.init(oAppData);

		return {
			start: function (ModulesManager) {
				ModulesManager.run('AdminPanelWebclient', 'registerAdminPanelTab', [
					function(resolve) {
						require.ensure(
							['modules/%ModuleName%/js/views/AdminSettingsView.js'],
							function() {
								resolve(require('modules/%ModuleName%/js/views/AdminSettingsView.js'));
							},
							'admin-bundle'
						);
					},
					Settings.HashModuleName,
					TextUtils.i18n('%MODULENAME%/LABEL_SETTINGS_TAB')
				]);
			}
		};
	}

	return null;
};
