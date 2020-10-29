<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\StandardResetPassword;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2020, Afterlogic Corp.
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractWebclientModule
{
	/***** private functions *****/
	/**
	 * Initializes Module.
	 * 
	 * @ignore
	 */
	public function init() 
	{
		$this->extendObject(
			'Aurora\Modules\Core\Classes\User', 
			array(
				'RecoveryEmail' => array('string', ''),
				'PasswordResetHash' => array('string', ''),
				'ConfirmRecoveryEmailHash' => array('string', ''),
			)
		);
		
		$this->aErrors = [
			Enums\ErrorCodes::WrongPassword => $this->i18N('ERROR_WRONG_PASSWORD'),
		];
		
		$this->AddEntry('confirm-recovery-email', 'EntryConfirmRecoveryEmail');
	}

	public function EntryConfirmRecoveryEmail()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);
		$sHash = (string) \Aurora\System\Router::getItemByIndex(1, '');
		$oModuleManager = \Aurora\System\Api::GetModuleManager();
		$sSiteName = $oModuleManager->getModuleConfigValue('Core', 'SiteName');
		$sTheme = $oModuleManager->getModuleConfigValue('CoreWebclient', 'Theme');
		$oUser = $this->getUserByHash($sHash, 'confirm-recovery-email');
		$ConfirmRecoveryEmailHeading = '';
		$ConfirmRecoveryEmailInfo = '';
		if ($oUser instanceof \Aurora\Modules\Core\Classes\User && $sHash === $oUser->{self::GetName().'::ConfirmRecoveryEmailHash'})
		{
			$ConfirmRecoveryEmailHeading = $this->i18N('HEADING_CONFIRM_EMAIL_RECOVERY_HASH');
			$ConfirmRecoveryEmailInfo = \strtr($this->i18N('INFO_CONFIRM_EMAIL_RECOVERY_HASH'), [
				'%SITE_NAME%' => $sSiteName,
				'%RECOVERY_EMAIL%' => $oUser->{self::GetName().'::RecoveryEmail'},
			]);
			$oMin = \Aurora\Modules\Min\Module::Decorator();
            if ($oMin)
			{
                $oMin->DeleteMinByHash($sHash);
            }
			$oUser->{self::GetName().'::ConfirmRecoveryEmailHash'} = '';
			$oCoreDecorator = \Aurora\Modules\Core\Module::Decorator();
			$oCoreDecorator->UpdateUserObject($oUser);
		}
		else
		{
			$ConfirmRecoveryEmailHeading = $this->i18N('HEADING_CONFIRM_EMAIL_RECOVERY_HASH');
			$ConfirmRecoveryEmailInfo = $this->i18N('ERROR_CONFIRM_EMAIL_RECOVERY_HASH');
		}
		$sConfirmRecoveryEmailTemplate = \file_get_contents($this->GetPath() . '/templates/EntryConfirmRecoveryEmail.html');

		\Aurora\System\Managers\Response::HtmlOutputHeaders();
		return \strtr($sConfirmRecoveryEmailTemplate, array(
			'{{SiteName}}' => $sSiteName . ' - ' . $ConfirmRecoveryEmailHeading,
			'{{Theme}}' => $sTheme,
			'{{ConfirmRecoveryEmailHeading}}' => $ConfirmRecoveryEmailHeading,
			'{{ConfirmRecoveryEmailInfo}}' => $ConfirmRecoveryEmailInfo,
			'{{ActionOpenApp}}' => \strtr($this->i18N('ACTION_OPEN_SITENAME'), ['%SITE_NAME%' => $sSiteName]),
			'{{OpenAppUrl}}' => $this->oHttp->GetFullUrl(),
		));
	}

	protected function getMinId($iUserId, $sType, $sSalt = '')
	{
		return \implode('|', array(self::GetName(), $iUserId, \md5($iUserId), $sType, $sSalt));
	}
	
	protected function generateHash($iUserId, $sType, $sSalt = '')
	{
		$mHash = '';
		$oMin = \Aurora\Modules\Min\Module::Decorator();
		if ($oMin)
		{
			$sMinId = $this->getMinId($iUserId, $sType, $sSalt);
			$mHash = $oMin->GetMinByID($sMinId);

			if ($mHash)
			{
				$mHash = $oMin->DeleteMinByID($sMinId);
			}

			$iRecoveryLinkLifetimeMinutes = $this->getConfig('RecoveryLinkLifetimeMinutes', 0);
			$iExpiresSeconds = time() + $iRecoveryLinkLifetimeMinutes * 60;
			$mHash = $oMin->CreateMin(
				$sMinId,
				array(
					'UserId' => $iUserId,
					'Type' => $sType,
					'Expires' => $iExpiresSeconds,
				)
			);
		}

		return $mHash;
	}

	/**
	 * Creates reset password notification body.
	 * @param string $sHash
	 * @param string $sSiteName
	 * @return string
	 */
	protected function getResetPasswordNotificationBody($sHash, $sSiteName)
    {
        $sBody = \file_get_contents($this->GetPath().'/templates/mail/ResetPassword.html');

        if (\is_string($sBody))
        {
            $sBody = \strtr($sBody, array(
                '{{RESET_PASSWORD_URL}}' => \rtrim($this->oHttp->GetFullUrl(), '\\/ ') . '/#reset-password/' . $sHash,
                '{{SITE_NAME}}' => $sSiteName
            ));
        }

		return $sBody;
	}

	protected function getSmtpConfig()
    {
		return [
			'Host' => $this->getConfig('NotificationHost', ''),
			'Port' => $this->getConfig('NotificationPort', 25),
			'SMTPAuth' => (bool) $this->getConfig('NotificationUseAuth', false),
			'Username' => $this->getConfig('NotificationLogin', ''),
			'Password' => $this->getConfig('NotificationPassword', ''),
		];
	}
	
	protected function getAccountByEmail($sEmail)
    {
		$oAccount = null;
		$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserByPublicId($sEmail);
		if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
		{
			$bPrevState = \Aurora\Api::skipCheckUserRole(true);
			$oAccount = \Aurora\Modules\Mail\Module::Decorator()->GetAccountByEmail($sEmail, $oUser->EntityId);
			\Aurora\Api::skipCheckUserRole($bPrevState);
		}
		return $oAccount;
	}

	protected function getAccountConfig($sEmail)
    {
		$oSendAccount = $this->getAccountByEmail($sEmail);
		$oSendServer = $oSendAccount->getServer();
		$aConfig = [
			'Host' => $oSendServer->OutgoingServer,
			'Port' => $oSendServer->OutgoingPort,
			'SMTPAuth' => false,
			'Username' => '',
			'Password' => '',
		];
		switch ($oSendServer->SmtpAuthType)
		{
			case \Aurora\Modules\Mail\Enums\SmtpAuthType::NoAuthentication:
				break;
			case \Aurora\Modules\Mail\Enums\SmtpAuthType::UseSpecifiedCredentials:
				$aConfig['SMTPAuth'] = true;
				$aConfig['Username'] = $oSendServer->SmtpLogin;
				$aConfig['Password'] = $oSendServer->SmtpPassword;
				break;
			case \Aurora\Modules\Mail\Enums\SmtpAuthType::UseUserCredentials:
				$aConfig['SMTPAuth'] = true;
				$aConfig['Username'] = $oSendAccount->IncomingLogin;
				$aConfig['Password'] = $oSendAccount->getPassword();
				break;
		}
		return $aConfig;
	}
	
	/**
	 * Sends notification email.
	 * @param string $sRecipientEmail
	 * @param string $sSubject
	 * @param string $sBody
	 * @param bool $bIsHtmlBody
	 * @param string $sSiteName
	 * @return bool
	 * @throws \Exception
	 */
	protected function sendNotification($sRecipientEmail, $sSubject, $sBody, $bIsHtmlBody, $sSiteName)
    {
        $oMail = new \PHPMailer();

        $sFrom = $this->getConfig('NotificationEmail', '');
        $sType = \strtolower($this->getConfig('NotificationType', 'mail'));
		switch ($sType)
		{
			case 'mail':
				$oMail->isMail();
				break;
			case 'smtp':
			case 'account':
				$oMail->isSMTP();
				$aConfig = $sType === 'smtp' ? $this->getSmtpConfig() : $this->getAccountConfig($sFrom);
				$oMail->Host = $aConfig['Host'];
				$oMail->Port = $aConfig['Port'];
				$oMail->SMTPAuth = $aConfig['SMTPAuth'];
				$oMail->Username = $aConfig['Username'];
				$oMail->Password = $aConfig['Password'];
				$oMail->SMTPOptions = array(
					'ssl' => array(
						'verify_peer' => false,
						'verify_peer_name' => false,
						'allow_self_signed' => true
					)
				);
				break;
		}

        $oMail->setFrom($sFrom);
        $oMail->addAddress($sRecipientEmail);
        $oMail->addReplyTo($sFrom, $sSiteName);

        $oMail->Subject = $sSubject;
        $oMail->Body = $sBody;
        $oMail->isHTML($bIsHtmlBody);

		$bResult = false;
        try
		{
            $bResult = $oMail->send();
        }
		catch (\Exception $oEx)
		{
			\Aurora\System\Api::LogException($oEx);
        }
		if (!$bResult && !empty($oMail->ErrorInfo))
		{
			\Aurora\System\Api::Log("Message could not be sent. Mailer Error: {$oMail->ErrorInfo}");
		}
		return $bResult;
	}
	
	protected function sendResetPasswordNotification($sRecipientEmail, $sHash)
    {
		$oModuleManager = \Aurora\System\Api::GetModuleManager();
		$sSiteName = $oModuleManager->getModuleConfigValue('Core', 'SiteName');
		
        $sBody = \file_get_contents($this->GetPath().'/templates/mail/ResetPassword.html');
        if (\is_string($sBody))
        {
            $sBody = \strtr($sBody, array(
                '{{RESET_PASSWORD_URL}}' => \rtrim($this->oHttp->GetFullUrl(), '\\/ ') . '/#reset-password/' . $sHash,
                '{{SITE_NAME}}' => $sSiteName,
            ));
        }
		$bIsHtmlBody = true;
		$sSubject = $this->i18N('LABEL_RESET_PASSWORD_SUBJECT');
		return $this->sendNotification($sRecipientEmail, $sSubject, $sBody, $bIsHtmlBody, $sSiteName);
	}
	
	protected function sendAddedRecoveryEmailNotification($sRecipientEmail, $sHash)
    {
		$oModuleManager = \Aurora\System\Api::GetModuleManager();
		$sSiteName = $oModuleManager->getModuleConfigValue('Core', 'SiteName');
		
        $sBody = \file_get_contents($this->GetPath().'/templates/mail/AddedRecoveryEmail.html');
        if (\is_string($sBody))
        {
			$sGreeting = $this->i18N('LABEL_CONFIRM_EMAIL_GREETING');
			$sMessage = \strtr($this->i18N('LABEL_CONFIRM_EMAIL_MESSAGE'), [
				'%RECOVERY_EMAIL%' => $sRecipientEmail,
				'%SITE_NAME%' => $sSiteName,
				'%RESET_PASSWORD_URL%' => \rtrim($this->oHttp->GetFullUrl(), '\\/ ') . '?/confirm-recovery-email/' . $sHash,
			]);
			$sSignature = \strtr($this->i18N('LABEL_CONFIRM_EMAIL_SIGNATURE'), ['%SITE_NAME%' => $sSiteName]);
            $sBody = \strtr($sBody, array(
				'{{GREETING}}' => $sGreeting,
                '{{MESSAGE}}' => $sMessage,
                '{{SIGNATURE}}' => $sSignature,
            ));
        }
		$bIsHtmlBody = true;
		$sSubject = \strtr($this->i18N('LABEL_CONFIRM_EMAIL_SUBJECT'), ['%RECOVERY_EMAIL%' => $sRecipientEmail]);
		$this->sendNotification($sRecipientEmail, $sSubject, $sBody, $bIsHtmlBody, $sSiteName);
	}

	/**
	 * Returns user with identifier obtained from the hash.
	 * @param string $sHash
	 * @param string $sType
	 * @param string $bAdd5Min
	 * @return \Aurora\Modules\Core\Classes\User
	 */
	protected function getUserByHash($sHash, $sType, $bAdd5Min = false)
	{
		$oUser = null;
		$oMin = \Aurora\Modules\Min\Module::Decorator();
		$mHash = $oMin ? $oMin->GetMinByHash($sHash) : null;
		if (!empty($mHash) && isset($mHash['__hash__'], $mHash['UserId'], $mHash['Type']) && $mHash['Type'] === $sType)
		{
			$iRecoveryLinkLifetimeMinutes = $this->getConfig('RecoveryLinkLifetimeMinutes', 0);
			$bRecoveryLinkAlive = ($iRecoveryLinkLifetimeMinutes === 0);
			if (!$bRecoveryLinkAlive)
			{
				$iExpiresSeconds = $mHash['Expires'];
				if ($bAdd5Min)
				{
					$iExpiresSeconds += 5 * 60;
				}
				$iDiffMinutes = (time() - $iExpiresSeconds) / 60;
				if ($iDiffMinutes < $iRecoveryLinkLifetimeMinutes)
				{
					$bRecoveryLinkAlive = true;
				}
				else
				{
					if ($sType === 'reset-password')
					{
						throw new \Exception($this->i18N('ERROR_RESET_PASSWORD_HASH_OUTDATED'));
					}
					else
					{
						throw new \Exception($this->i18N('ERROR_CONFIRM_EMAIL_RECOVERY_HASH_OUTDATED'));
					}
				}
			}
			if ($bRecoveryLinkAlive)
			{
				$iUserId = $mHash['UserId'];
				$bPrevState = \Aurora\Api::skipCheckUserRole(true);
				$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUser($iUserId);
				\Aurora\Api::skipCheckUserRole($bPrevState);
			}
		}
		return $oUser;
	}
	
	protected function getCoveredRecoveryEmail($oUser)
	{
		$sResult = '';

		if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
		{
			$sRecoveryEmail = $oUser->{self::GetName().'::RecoveryEmail'};
			if (!empty($sRecoveryEmail))
			{
				$aRecoveryEmailParts = explode('@', $sRecoveryEmail);
				$iPartsCount = count($aRecoveryEmailParts);
				if ($iPartsCount > 0)
				{
					$sResult = substr($aRecoveryEmailParts[0], 0, 3) . '***';
				}
				if ($iPartsCount > 1)
				{
					$sResult .= '@' . $aRecoveryEmailParts[$iPartsCount - 1];
				}
			}
		}

		return $sResult;
	}
	/***** private functions *****/

	/***** public functions might be called with web API *****/
	/**
	 * Obtains list of module settings for authenticated user.
	 * 
	 * @return array
	 */
	public function GetSettings()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);
		
		$aSettings = [
			'HashModuleName' => $this->getConfig('HashModuleName', 'login'),
			'CustomLogoUrl' => $this->getConfig('CustomLogoUrl', ''),
			'BottomInfoHtmlText' => $this->getConfig('BottomInfoHtmlText', ''),
		];

		$oAuthenticatedUser = \Aurora\System\Api::getAuthenticatedUser();
		if ($oAuthenticatedUser instanceof \Aurora\Modules\Core\Classes\User && $oAuthenticatedUser->isNormalOrTenant())
		{
			$aSettings['RecoveryEmail'] = $this->getCoveredRecoveryEmail($oAuthenticatedUser);
			$aSettings['RecoveryEmailConfirmed'] = empty($oAuthenticatedUser->{self::GetName().'::ConfirmRecoveryEmailHash'});
		}
		
		return $aSettings;
	}
	
	/**
	 * Updates per user settings.
	 * @param string $RecoveryEmail
	 * @param string $Password
	 * @return boolean|string
	 * @throws \Aurora\System\Exceptions\ApiException
	 * @throws \Aurora\Modules\StandardResetPassword\Exceptions\Exception
	 */
	public function UpdateSettings($RecoveryEmail = null, $Password = null)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		
		if ($RecoveryEmail === null || $Password === null)
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}

		$oAuthenticatedUser = \Aurora\System\Api::getAuthenticatedUser();
		if ($oAuthenticatedUser instanceof \Aurora\Modules\Core\Classes\User && $oAuthenticatedUser->isNormalOrTenant())
		{
			$oAccount = \Aurora\Modules\Mail\Module::Decorator()->GetAccountByEmail($oAuthenticatedUser->PublicId, $oAuthenticatedUser->EntityId);
			$sAccountPassword = $oAccount ? $oAccount->getPassword() : null;
			if ($Password === null || $sAccountPassword !== $Password)
			{
				throw new \Aurora\Modules\StandardResetPassword\Exceptions\Exception(Enums\ErrorCodes::WrongPassword);
			}
			$sConfirmRecoveryEmailHash = !empty($RecoveryEmail) ? $this->generateHash($oAuthenticatedUser->EntityId, 'confirm-recovery-email', __FUNCTION__) : '';
			$oAuthenticatedUser->{self::GetName().'::ConfirmRecoveryEmailHash'} = $sConfirmRecoveryEmailHash;
			$oAuthenticatedUser->{self::GetName().'::RecoveryEmail'} = $RecoveryEmail;
			$oCoreDecorator = \Aurora\Modules\Core\Module::Decorator();
			if ($oCoreDecorator->UpdateUserObject($oAuthenticatedUser))
			{
				$this->sendAddedRecoveryEmailNotification($RecoveryEmail, $sConfirmRecoveryEmailHash);
				return $this->getCoveredRecoveryEmail($oAuthenticatedUser);
			}
			else
			{
				return false;
			}
		}

		throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::AccessDenied);
	}
	
	public function GetRecoveryEmail($UserPublicId)
	{
		$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserByPublicId($UserPublicId);
		return $this->getCoveredRecoveryEmail($oUser);
	}
	
	/**
	 * Creates a recovery link and sends it to recovery email of the user with specified public ID.
	 * @param string $UserPublicId
	 * @return boolean
	 * @throws \Exception
	 */
	public function SendRecoveryEmail($UserPublicId)
	{
		$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserByPublicId($UserPublicId);
		if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
		{
			$bPrevState = \Aurora\Api::skipCheckUserRole(true);
			$sPasswordResetHash = $this->generateHash($oUser->EntityId, 'reset-password', __FUNCTION__);
			$oUser->{self::GetName().'::PasswordResetHash'} = $sPasswordResetHash;
			\Aurora\Modules\Core\Module::Decorator()->UpdateUserObject($oUser);
			\Aurora\Api::skipCheckUserRole($bPrevState);

			$sRecoveryEmail = $oUser->{self::GetName().'::RecoveryEmail'};
			if  (!empty($sRecoveryEmail))
			{
				return $this->sendResetPasswordNotification($sRecoveryEmail, $sPasswordResetHash);
			}
		}
		
		throw new \Exception('Recovery email is not found for specified email');
	}
	
	/**
	 * Returns public id of user obtained from the hash.
	 *
	 * @param string $Hash Hash with information about user.
	 * @return string
	 */
	public function GetUserPublicId($Hash)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);
		
		$oUser = $this->getUserByHash($Hash, 'reset-password');

		if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
		{
			return $oUser->PublicId;
		}
		return '';
	}
	
    public function ChangePassword($Hash, $NewPassword)
    {
		$bPrevState =  \Aurora\System\Api::skipCheckUserRole(true);

        $oMail = \Aurora\Modules\Mail\Module::Decorator();
        $oMin = \Aurora\Modules\Min\Module::Decorator();

        $oUser = $this->getUserByHash($Hash, 'reset-password', true);

        $mResult = false;
        $oAccount = null;
        if (!empty($oMail) && !empty($oUser))
		{
            $aAccounts = $oMail->GetAccounts($oUser->EntityId);
            $oAccount = reset($aAccounts);
        }


        if (!empty($oUser) && !empty($oAccount) && !empty($NewPassword))
        {
			$aArgs = [
				'Account' => $oAccount,
				'CurrentPassword' => '',
				'SkipCurrentPasswordCheck' => true,
				'NewPassword' => $NewPassword
			];
			$aResponse = [
				'AccountPasswordChanged' => false
			];

			\Aurora\System\Api::GetModule('Core')->broadcastEvent(
				'StandardResetPassword::ChangeAccountPassword',
				$aArgs,
				$aResponse
			);
            $mResult = $aResponse['AccountPasswordChanged'];
            if ($mResult && !empty($oMin) && !empty($Hash))
			{
                $oMin->DeleteMinByHash($Hash);
            }
        }
        else
        {
            throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
        }

		\Aurora\System\Api::skipCheckUserRole($bPrevState);
        return $mResult;
    }
	/***** public functions might be called with web API *****/
}
