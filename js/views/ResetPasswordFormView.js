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
	Routing = require('%PathToCoreWebclientModule%/js/Routing.js'),
	Screens = require('%PathToCoreWebclientModule%/js/Screens.js'),
	UserSettings = require('%PathToCoreWebclientModule%/js/Settings.js'),
	
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
	this.sBottomInfoHtmlText = Settings.BottomInfoHtmlText;
	
	this.email = ko.observable('');
	this.emailFocus = ko.observable(false);

	this.newPassword = ko.observable('');
	this.newPasswordFocus = ko.observable(false);

	this.confirmPassword = ko.observable('');
	this.confirmPasswordFocus = ko.observable(false);
	
	this.step = ko.observable(1);
	this.headingText = ko.computed(function () {
		if (this.step() === 0 || this.step() === 1 || this.step() === 2)
		{
			return TextUtils.i18n('%MODULENAME%/HEADING_RESET_PASSWORD');
		}
		if (this.step() === 3)
		{
			return TextUtils.i18n('%MODULENAME%/HEADING_CHECK_EMAIL');
		}
		return '';
	}, this);
	this.resetPasswordHashInfo = ko.observable('');
	this.recoverThroughEmailText = ko.observable('');
	this.sendRecoveryEmailText = ko.observable('');

	this.gettingRecoveryEmail = ko.observable(false);
	this.continueCommand = Utils.createCommand(this, this.continue, function () { return !this.gettingRecoveryEmail(); });

	this.sendingRecoveryEmail = ko.observable(false);
	this.sendRecoveryEmailCommand = Utils.createCommand(this, this.sendRecoveryEmail, function () { return !this.sendingRecoveryEmail(); });
	
	this.changingPassword = ko.observable(false);
	this.changePasswordCommand = Utils.createCommand(this, this.changePassword, function () { return !this.changingPassword(); });
	
	this.shake = ko.observable(false).extend({'autoResetToFalse': 800});
	
	App.broadcastEvent('%ModuleName%::ConstructView::after', {'Name': this.ViewConstructorName, 'View': this});
}

_.extendOwn(CResetPasswordFormView.prototype, CAbstractScreenView.prototype);

CResetPasswordFormView.prototype.ViewTemplate = '%ModuleName%_ResetPasswordFormView';
CResetPasswordFormView.prototype.ViewConstructorName = 'CResetPasswordFormView';

CResetPasswordFormView.prototype.onBind = function ()
{
	$html.addClass('non-adjustable-valign');
};

CResetPasswordFormView.prototype.getResetPasswordHash = function () {
	var aHashArray = Routing.getCurrentHashArray();
	if (aHashArray.length >= 2 && aHashArray[0] === Settings.HashModuleName)
	{
		return aHashArray[1];
	}
	return '';
},
/**
 * Focuses email input after view showing.
 */
CResetPasswordFormView.prototype.onShow = function ()
{
	var sResetPasswordHash = this.getResetPasswordHash();
	console.log('sResetPasswordHash', sResetPasswordHash);
	if (Types.isNonEmptyString(sResetPasswordHash))
	{
		this.step(0);
		Ajax.send(Settings.ServerModuleName, 'GetUserPublicId', { 'Hash': sResetPasswordHash }, function (oResponse) {
			if (oResponse.Result)
			{
				this.resetPasswordHashInfo(TextUtils.i18n('%MODULENAME%/INFO_WELCOME', {'USERNAME': oResponse.Result, 'SITE_NAME': UserSettings.SiteName}));
			}
			else
			{
				this.resetPasswordHashInfo(TextUtils.i18n('%MODULENAME%/ERROR_WELCOME', {'USERNAME': oResponse.Result, 'SITE_NAME': UserSettings.SiteName}));
			}
		}, this);
	}
	else
	{
		_.delay(_.bind(function(){
			if (this.email() === '')
			{
				this.emailFocus(true);
			}
		},this), 1);
	}
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
		this.gettingRecoveryEmail(true);
		Ajax.send('%ModuleName%', 'GetRecoveryEmail', { UserPublicId: this.email() }, function (oResponse, oRequest) {
			this.gettingRecoveryEmail(false);
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
	this.sendingRecoveryEmail(true);
	Ajax.send('%ModuleName%', 'SendRecoveryEmail', { UserPublicId: this.email() }, function (oResponse, oRequest) {
		this.sendingRecoveryEmail(false);
		if (oResponse && oResponse.Result)
		{
			this.step(3);
		}
	}, this);
};

CResetPasswordFormView.prototype.changePassword = function ()
{
	this.changingPassword(true);
	Ajax.send('%ModuleName%', 'ChangePassword', { 'Hash': this.getResetPasswordHash(), 'NewPassword': this.newPassword() }, function (oResponse, oRequest) {
		this.changingPassword(false);
		console.log(oResponse);
		if (oResponse && oResponse.Result)
		{
		}
	}, this);
}

module.exports = new CResetPasswordFormView();
