'use strict';

var
	_ = require('underscore'),
	$ = require('jquery'),
	ko = require('knockout'),
	
	AddressUtils = require('%PathToCoreWebclientModule%/js/utils/Address.js'),
	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
	UrlUtils = require('%PathToCoreWebclientModule%/js/utils/Url.js'),
	Utils = require('%PathToCoreWebclientModule%/js/utils/Common.js'),
	
	Api = require('%PathToCoreWebclientModule%/js/Api.js'),
	App = require('%PathToCoreWebclientModule%/js/App.js'),
	Browser = require('%PathToCoreWebclientModule%/js/Browser.js'),
	Screens = require('%PathToCoreWebclientModule%/js/Screens.js'),
	
	CAbstractScreenView = require('%PathToCoreWebclientModule%/js/views/CAbstractScreenView.js'),

	Popups = require('%PathToCoreWebclientModule%/js/Popups.js'),
	AlertPopup = require('%PathToCoreWebclientModule%/js/popups/AlertPopup.js'),
	
	Ajax = require('%PathToCoreWebclientModule%/js/Ajax.js'),
	Settings = require('modules/%ModuleName%/js/Settings.js'),
	
	$html = $('html')
;

/**
 * @constructor
 */
function CResetPasswordFormView()
{
	CAbstractScreenView.call(this, '%ModuleName%');
	
	this.sCustomLogoUrl = Settings.CustomLogoUrl;
	this.sInfoText = Settings.InfoText;
	this.sBottomInfoHtmlText = Settings.BottomInfoHtmlText;
	
	this.email = ko.observable('');
	this.enableEmailEdit = ko.observable(true);
	this.password = ko.observable('');
	this.confirmPassword = ko.observable('');
	
	this.emailFocus = ko.observable(false);
	this.passwordFocus = ko.observable(false);
	this.confirmPasswordFocus = ko.observable(false);
	
	this.step = ko.observable(1);
	this.recoverThroughEmailText = ko.observable('');
	this.sendRecoveryEmailText = ko.observable('');

	this.loading = ko.observable(false);

	this.canTryResetPassword = ko.computed(function () {
		return !this.loading();
	}, this);

	this.ResetPasswordButtonText = ko.computed(function () {
		return this.loading() ? TextUtils.i18n('COREWEBCLIENT/ACTION_ResetPassword_IN_PROGRESS') : TextUtils.i18n('COREWEBCLIENT/ACTION_ResetPassword');
	}, this);

	this.continueCommand = Utils.createCommand(this, this.continue, this.canTryResetPassword);
	this.resetPasswordCommand = Utils.createCommand(this, this.ResetPassword, this.canTryResetPassword);

	this.shake = ko.observable(false).extend({'autoResetToFalse': 800});
	
	this.welcomeText = ko.observable('');
	App.subscribeEvent('ShowWelcomeResetPasswordText', _.bind(function (oParams) {
		this.welcomeText(oParams.WelcomeText);
		this.email(oParams.UserName);
		this.enableEmailEdit(false);
	}, this));
	
	App.broadcastEvent('%ModuleName%::ConstructView::after', {'Name': this.ViewConstructorName, 'View': this});
}

_.extendOwn(CResetPasswordFormView.prototype, CAbstractScreenView.prototype);

CResetPasswordFormView.prototype.ViewTemplate = '%ModuleName%_ResetPasswordFormView';
CResetPasswordFormView.prototype.ViewConstructorName = 'CResetPasswordFormView';

CResetPasswordFormView.prototype.onBind = function ()
{
	$html.addClass('non-adjustable-valign');
};

/**
 * Focuses email input after view showing.
 */
CResetPasswordFormView.prototype.onShow = function ()
{
	_.delay(_.bind(function(){
		if (this.email() === '')
		{
			this.emailFocus(true);
		}
	},this), 1);
};

CResetPasswordFormView.prototype.continue = function ()
{
	var sEmail = $.trim(this.email());
	if (sEmail === '')
	{
		this.emailFocus(true);
		this.shake(true);
	}
	else if (!AddressUtils.isCorrectEmail(sEmail))
	{
		Popups.showPopup(AlertPopup, [TextUtils.i18n('%MODULENAME%/ERROR_INCORRECT_EMAIL')]);
	}
	else
	{
		this.step(2);
		Ajax.send('%ModuleName%', 'GetRecoveryEmail', { UserPublicId: this.email()}, function (oResponse, oRequest) {
			if (Types.isNonEmptyString(oResponse && oResponse.Result))
			{
				this.recoverThroughEmailText(TextUtils.i18n('%MODULENAME%/ACTION_EMAIL_RECOVER', {
					'EMAIL': oResponse && oResponse.Result
				}));
				this.sendRecoveryEmailText(TextUtils.i18n('%MODULENAME%/INFO_CHECK_EMAIL', {
					'EMAIL': oResponse && oResponse.Result
				}));
			}
			else
			{
				Popups.showPopup(AlertPopup, [TextUtils.i18n('%MODULENAME%/ERROR_RECOVERY_EMAIL_NOT_FOUND')]);
			}
		}, this);
	}
};

CResetPasswordFormView.prototype.backToStep1 = function ()
{
	this.recoverThroughEmailText('');
	this.sendRecoveryEmailText('');
	this.step(1);
	this.emailFocus(true);
};

CResetPasswordFormView.prototype.sendRecoveryEmail = function ()
{
	this.step(3);
};

/**
 * 
 * @param {string} sEmail
 * @param {string} sPassword
 * @param {string} sConfirmPassword
 * @returns {Boolean}
 */
CResetPasswordFormView.prototype.validateForm = function (sEmail, sPassword, sConfirmPassword)
{
	if (sEmail === '')
	{
		this.emailFocus(true);
		this.shake(true);
		return false;
	}
	if (sPassword === '')
	{
		this.passwordFocus(true);
		this.shake(true);
		return false;
	}
	if (sPassword !== '' && sPassword !== sConfirmPassword)
	{
		this.confirmPasswordFocus(true);
		this.shake(true);
		Screens.showError(TextUtils.i18n('COREWEBCLIENT/ERROR_PASSWORDS_DO_NOT_MATCH'));
		return false;
	}
	return true;
};

/**
 * Checks Email input value and sends ResetPassword request to server.
 */
CResetPasswordFormView.prototype.ResetPassword = function ()
{
	if (!this.loading())
	{
		var
			sEmail = $.trim(this.email()),
			sPassword = $.trim(this.password()),
			sConfirmPassword = $.trim(this.confirmPassword()),
			oParameters = {
				'Email': sEmail,
				'Password': sPassword
			}
		;
		if (this.validateForm(sEmail, sPassword, sConfirmPassword))
		{
			this.loading(true);
			Ajax.send('%ModuleName%', 'ResetPassword', oParameters, this.onResetPasswordResponse, this);
		}
	}
};

/**
 * Receives data from the server. Shows error and shakes form if server has returned false-result.
 * Otherwise clears search-string if it don't contain "reset-pass", "invite-auth" and "oauth" parameters and reloads page.
 * 
 * @param {Object} oResponse Data obtained from the server.
 * @param {Object} oRequest Data has been transferred to the server.
 */
CResetPasswordFormView.prototype.onResetPasswordResponse = function (oResponse, oRequest)
{
	if (false === oResponse.Result)
	{
		this.loading(false);
		this.shake(true);
		
		Api.showErrorByCode(oResponse, TextUtils.i18n('COREWEBCLIENT/ERROR_REGISTRATION_FAILED'));
	}
	else
	{
		App.setAuthToken(oResponse.Result.AuthToken);
		
		if (window.location.search !== '' &&
			UrlUtils.getRequestParam('reset-pass') === null &&
			UrlUtils.getRequestParam('invite-auth') === null &&
			UrlUtils.getRequestParam('oauth') === null)
		{
			UrlUtils.clearAndReloadLocation(Browser.ie8AndBelow, true);
		}
		else
		{
			UrlUtils.clearAndReloadLocation(Browser.ie8AndBelow, false);
		}
	}
};

module.exports = new CResetPasswordFormView();
