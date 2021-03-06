<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\StandardResetPassword;

use Aurora\Modules\Core\Models\User;

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

		$oUser = null;
		try
		{
			$oUser = $this->getUserByHash($sHash, 'confirm-recovery-email');
		}
		catch (\Exception $oEx)
		{
			\Aurora\System\Api::LogException($oEx);
		}
		$ConfirmRecoveryEmailHeading = '';
		$ConfirmRecoveryEmailInfo = '';
		if ($oUser instanceof User && $sHash === $oUser->{self::GetName().'::ConfirmRecoveryEmailHash'})
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
			$oUser->setExtendedProp(self::GetName().'::ConfirmRecoveryEmailHash', '');
			$oCoreDecorator = \Aurora\Modules\Core\Module::Decorator();
			$oCoreDecorator->UpdateUserObject($oUser);
		}
		else
		{
			$ConfirmRecoveryEmailHeading = $this->i18N('HEADING_CONFIRM_EMAIL_RECOVERY_HASH');
			$ConfirmRecoveryEmailInfo = $this->i18N('ERROR_LINK_NOT_VALID');
		}
		$sConfirmRecoveryEmailTemplate = \file_get_contents($this->GetPath() . '/templates/EntryConfirmRecoveryEmail.html');

		\Aurora\Modules\CoreWebclient\Module::Decorator()->SetHtmlOutputHeaders();
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

	protected function getSmtpConfig()
    {
		return [
			'Host' => $this->getConfig('NotificationHost', ''),
			'Port' => $this->getConfig('NotificationPort', 25),
			'UseSsl' => $this->getConfig('NotificationUseSsl', false),
			'SMTPAuth' => (bool) $this->getConfig('NotificationUseAuth', false),
			'Username' => $this->getConfig('NotificationLogin', ''),
			'Password' => \Aurora\System\Utils::DecryptValue($this->getConfig('NotificationPassword', '')),
		];
	}
	
	protected function getAccountByEmail($sEmail)
    {
		$oAccount = null;
		$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserByPublicId($sEmail);
		if ($oUser instanceof User)
		{
			$bPrevState = \Aurora\Api::skipCheckUserRole(true);
			$oAccount = \Aurora\Modules\Mail\Module::Decorator()->GetAccountByEmail($sEmail, $oUser->Id);
			\Aurora\Api::skipCheckUserRole($bPrevState);
		}
		return $oAccount;
	}

	protected function getAccountConfig($sEmail)
    {
		$aConfig = [
			'Host' => '',
			'Port' => '',
			'UseSsl' => false,
			'SMTPAuth' => false,
			'Username' => '',
			'Password' => '',
		];
		$oSendAccount = $this->getAccountByEmail($sEmail);
		$oSendServer = $oSendAccount ? $oSendAccount->getServer() : null;
		if ($oSendServer)
		{
			$aConfig['Host'] = $oSendServer->OutgoingServer;
			$aConfig['Port'] = $oSendServer->OutgoingPort;
			switch ($oSendServer->SmtpAuthType)
			{
				case \Aurora\Modules\Mail\Enums\SmtpAuthType::NoAuthentication:
					break;
				case \Aurora\Modules\Mail\Enums\SmtpAuthType::UseSpecifiedCredentials:
					$aConfig['UseSsl'] = $oSendServer->OutgoingUseSsl;
					$aConfig['SMTPAuth'] = true;
					$aConfig['Username'] = $oSendServer->SmtpLogin;
					$aConfig['Password'] = $oSendServer->SmtpPassword;
					break;
				case \Aurora\Modules\Mail\Enums\SmtpAuthType::UseUserCredentials:
					$aConfig['UseSsl'] = $oSendServer->OutgoingUseSsl;
					$aConfig['SMTPAuth'] = true;
					$aConfig['Username'] = $oSendAccount->IncomingLogin;
					$aConfig['Password'] = $oSendAccount->getPassword();
					break;
			}
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
	protected function sendMessage($sRecipientEmail, $sSubject, $sBody, $bIsHtmlBody, $sSiteName)
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
				if ($aConfig['UseSsl'])
				{
					$oMail->SMTPSecure = 'ssl';
				}
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
			throw new \Exception($oEx->getMessage());
		}
		if (!$bResult && !empty($oMail->ErrorInfo))
		{
			\Aurora\System\Api::Log("Message could not be sent. Mailer Error: {$oMail->ErrorInfo}");
			throw new \Exception($oMail->ErrorInfo);
		}
		return $bResult;
	}
	
	/**
	 * Sends password reset message.
	 * @param string $sRecipientEmail
	 * @param string $sHash
	 * @return boolean
	 */
	protected function sendPasswordResetMessage($sRecipientEmail, $sHash)
    {
		$oModuleManager = \Aurora\System\Api::GetModuleManager();
		$sSiteName = $oModuleManager->getModuleConfigValue('Core', 'SiteName');
		
        $sBody = \file_get_contents($this->GetPath().'/templates/mail/Message.html');
        if (\is_string($sBody))
        {
			$sGreeting = $this->i18N('LABEL_MESSAGE_GREETING');
			$sMessage = \strtr($this->i18N('LABEL_RESET_PASSWORD_MESSAGE'), [
				'%SITE_NAME%' => $sSiteName,
				'%RESET_PASSWORD_URL%' => \rtrim($this->oHttp->GetFullUrl(), '\\/ ') . '/#reset-password/' . $sHash,
			]);
			$sSignature = \strtr($this->i18N('LABEL_MESSAGE_SIGNATURE'), ['%SITE_NAME%' => $sSiteName]);
            $sBody = \strtr($sBody, array(
				'{{GREETING}}' => $sGreeting,
                '{{MESSAGE}}' => $sMessage,
                '{{SIGNATURE}}' => $sSignature,
            ));
        }
		$bIsHtmlBody = true;
		$sSubject = $this->i18N('LABEL_RESET_PASSWORD_SUBJECT');
		return $this->sendMessage($sRecipientEmail, $sSubject, $sBody, $bIsHtmlBody, $sSiteName);
	}
	
	/**
	 * Sends recovery email confirmation message.
	 * @param string $sRecipientEmail
	 * @param string $sHash
	 * @return bool
	 */
	protected function sendRecoveryEmailConfirmationMessage($sRecipientEmail, $sHash)
    {
		$oModuleManager = \Aurora\System\Api::GetModuleManager();
		$sSiteName = $oModuleManager->getModuleConfigValue('Core', 'SiteName');
		
        $sBody = \file_get_contents($this->GetPath().'/templates/mail/Message.html');
        if (\is_string($sBody))
        {
			$sGreeting = $this->i18N('LABEL_MESSAGE_GREETING');
			$sMessage = \strtr($this->i18N('LABEL_CONFIRM_EMAIL_MESSAGE'), [
				'%RECOVERY_EMAIL%' => $sRecipientEmail,
				'%SITE_NAME%' => $sSiteName,
				'%RESET_PASSWORD_URL%' => \rtrim($this->oHttp->GetFullUrl(), '\\/ ') . '?/confirm-recovery-email/' . $sHash,
			]);
			$sSignature = \strtr($this->i18N('LABEL_MESSAGE_SIGNATURE'), ['%SITE_NAME%' => $sSiteName]);
            $sBody = \strtr($sBody, array(
				'{{GREETING}}' => $sGreeting,
                '{{MESSAGE}}' => $sMessage,
                '{{SIGNATURE}}' => $sSignature,
            ));
        }
		$bIsHtmlBody = true;
		$sSubject = \strtr($this->i18N('LABEL_CONFIRM_EMAIL_SUBJECT'), ['%RECOVERY_EMAIL%' => $sRecipientEmail]);
		return $this->sendMessage($sRecipientEmail, $sSubject, $sBody, $bIsHtmlBody, $sSiteName);
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
				if ($iExpiresSeconds > time())
				{
					$bRecoveryLinkAlive = true;
				}
				else
				{
					throw new \Exception($this->i18N('ERROR_LINK_NOT_VALID'));
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
	
	/**
	 * Get recovery email address partly replaced with stars.
	 * @param \Aurora\Modules\Core\Models\User $oUser
	 * @return string
	 */
	protected function getStarredRecoveryEmail($oUser)
	{
		$sResult = '';

		if ($oUser instanceof User)
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
		if ($oAuthenticatedUser instanceof User)
        {
            if ($oAuthenticatedUser->isNormalOrTenant())
            {
                $aSettings['RecoveryEmail'] = $this->getStarredRecoveryEmail($oAuthenticatedUser);
                $aSettings['RecoveryEmailConfirmed'] = empty($oAuthenticatedUser->{self::GetName().'::ConfirmRecoveryEmailHash'});
            }
            if ($oAuthenticatedUser->Role === \Aurora\System\Enums\UserRole::SuperAdmin)
            {
                $aSettings['RecoveryLinkLifetimeMinutes'] = $this->getConfig('RecoveryLinkLifetimeMinutes', 15);
                $aSettings['NotificationEmail'] = $this->getConfig('NotificationEmail', '');
                $aSettings['NotificationType'] = $this->getConfig('NotificationType', '');
                $aSettings['NotificationHost'] = $this->getConfig('NotificationHost', '');
                $aSettings['NotificationPort'] = $this->getConfig('NotificationPort', 25);
                $aSettings['NotificationUseSsl'] = $this->getConfig('NotificationUseSsl', false);
                $aSettings['NotificationUseAuth'] = $this->getConfig('NotificationUseAuth', false);
                $aSettings['NotificationLogin'] = $this->getConfig('NotificationLogin', '');
                $aSettings['HasNotificationPassword'] = !empty($this->getConfig('NotificationPassword', ''));
            }
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
		if ($oAuthenticatedUser instanceof User && $oAuthenticatedUser->isNormalOrTenant())
		{
			$oAccount = \Aurora\Modules\Mail\Module::Decorator()->GetAccountByEmail($oAuthenticatedUser->PublicId, $oAuthenticatedUser->Id);
			$sAccountPassword = $oAccount ? $oAccount->getPassword() : null;
			if ($Password === null || $sAccountPassword !== $Password)
			{
				throw new \Aurora\Modules\StandardResetPassword\Exceptions\Exception(Enums\ErrorCodes::WrongPassword);
			}
			
			$sPrevRecoveryEmail = $oAuthenticatedUser->{self::GetName().'::RecoveryEmail'};
			$sPrevConfirmRecoveryEmail = $oAuthenticatedUser->{self::GetName().'::ConfirmRecoveryEmail'};
			$sConfirmRecoveryEmailHash = !empty($RecoveryEmail) ? $this->generateHash($oAuthenticatedUser->Id, 'confirm-recovery-email', __FUNCTION__) : '';
			$oAuthenticatedUser->setExtendedProp(self::GetName().'::ConfirmRecoveryEmailHash',  $sConfirmRecoveryEmailHash);
			$oAuthenticatedUser->setExtendedProp(self::GetName().'::RecoveryEmail', $RecoveryEmail);
			if (\Aurora\Modules\Core\Module::Decorator()->UpdateUserObject($oAuthenticatedUser))
			{
				$bResult = true;
				$oSentEx = null;
				try
				{
					// Send message to confirm recovery email if it's not empty.
					if (!empty($RecoveryEmail))
					{
						$bResult = $this->sendRecoveryEmailConfirmationMessage($RecoveryEmail, $sConfirmRecoveryEmailHash);
					}
				}
				catch (\Exception $oEx)
				{
					$bResult = false;
					$oSentEx = $oEx;
				}
				if (!$bResult)
				{
					$oAuthenticatedUser->setExtendedProp(self::GetName().'::ConfirmRecoveryEmailHash', $sPrevConfirmRecoveryEmail);
					$oAuthenticatedUser->setExtendedProp(self::GetName().'::RecoveryEmail', $sPrevRecoveryEmail);
					\Aurora\Modules\Core\Module::Decorator()->UpdateUserObject($oAuthenticatedUser);
				}
				if ($oSentEx !== null)
				{
					throw $oSentEx;
				}
				return $bResult ? $this->getStarredRecoveryEmail($oAuthenticatedUser) : false;
			}
			else
			{
				return false;
			}
		}

		throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::AccessDenied);
  }
  
  /**
	 * Updates per user settings.
	 * @param string $RecoveryEmail
	 * @param string $Password
	 * @return boolean|string
	 * @throws \Aurora\System\Exceptions\ApiException
	 * @throws \Aurora\Modules\StandardResetPassword\Exceptions\Exception
	 */
	public function UpdateAdminSettings($RecoveryLinkLifetimeMinutes, $NotificationEmail, $NotificationType, $NotificationHost = null,
                                        $NotificationPort = null, $NotificationUseSsl = null, $NotificationUseAuth = null,
                                        $NotificationLogin = null, $NotificationPassword = null)
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::SuperAdmin);

        $this->setConfig('RecoveryLinkLifetimeMinutes', $RecoveryLinkLifetimeMinutes);
        $this->setConfig('NotificationEmail', $NotificationEmail);
        $this->setConfig('NotificationType', $NotificationType);
        if ($NotificationType === 'smtp')
        {
            $this->setConfig('NotificationHost', $NotificationHost);
            $this->setConfig('NotificationPort', $NotificationPort);
            $this->setConfig('NotificationUseSsl', $NotificationUseSsl);
            $this->setConfig('NotificationUseAuth', $NotificationUseAuth);
            if ($NotificationUseAuth)
            {
                $this->setConfig('NotificationLogin', $NotificationLogin);
                $this->setConfig('NotificationPassword', \Aurora\System\Utils::EncryptValue($NotificationPassword));
            }
        }
        return $this->saveModuleConfig();
    }
	
	public function SetRecoveryEmail($UserPublicId = null, $RecoveryEmail = null, $SkipEmailConfirmation = false)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::SuperAdmin);

		if ($UserPublicId === null || $RecoveryEmail === null)
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}

		$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserByPublicId($UserPublicId);

		if ($oUser instanceof User && $oUser->isNormalOrTenant())
		{
			$sPrevRecoveryEmail = $oUser->{self::GetName().'::RecoveryEmail'};
			$sPrevConfirmRecoveryEmail = $oUser->{self::GetName().'::ConfirmRecoveryEmail'};
			$sConfirmRecoveryEmailHash = !empty($RecoveryEmail) ? $this->generateHash($oUser->Id, 'confirm-recovery-email', __FUNCTION__) : '';
			$oUser->setExtendedProp(self::GetName().'::ConfirmRecoveryEmailHash', !$SkipEmailConfirmation ? !$sConfirmRecoveryEmailHash : '');
			$oUser->setExtendedProp(self::GetName().'::RecoveryEmail', $RecoveryEmail);
			if (\Aurora\Modules\Core\Module::Decorator()->UpdateUserObject($oUser))
			{
				$bResult = true;
				
				if (!$SkipEmailConfirmation) {
					$oSentEx = null;
					try
					{
						// Send message to confirm recovery email if it's not empty.
						if (!empty($RecoveryEmail))
						{
							$bResult = $this->sendRecoveryEmailConfirmationMessage($RecoveryEmail, $sConfirmRecoveryEmailHash);
						}
					}
					catch (\Exception $oEx)
					{
						$bResult = false;
						$oSentEx = $oEx;
					}
					if (!$bResult)
					{
						$oUser->setExtendedProp(self::GetName().'::ConfirmRecoveryEmailHash', $sPrevConfirmRecoveryEmail);
						$oUser->setExtendedProp(self::GetName().'::RecoveryEmail', $sPrevRecoveryEmail);
						\Aurora\Modules\Core\Module::Decorator()->UpdateUserObject($oUser);
					}
					if ($oSentEx !== null)
					{
						throw $oSentEx;
					}
				}
				return $bResult ? $this->getStarredRecoveryEmail($oUser) : false;
			}
			else
			{
				return false;
			}
		}

		throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::AccessDenied);
	}
	
	/**
	 * Get recovery email address partly replaced with stars.
	 * @param string $UserPublicId
	 * @return string
	 */
	public function GetStarredRecoveryEmailAddress($UserPublicId)
	{
		$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserByPublicId($UserPublicId);
		$sRecoveryEmail = $this->getStarredRecoveryEmail($oUser);
		$sConfirmRecoveryEmailHash = $oUser->{self::GetName().'::ConfirmRecoveryEmailHash'};
		if  (!empty($sConfirmRecoveryEmailHash)) // email is not confirmed
		{
			$sRecoveryEmail = '';
		}
		return $sRecoveryEmail;
	}
	
	/**
	 * Creates a recovery link and sends it to recovery email of the user with specified public ID.
	 * @param string $UserPublicId
	 * @return boolean
	 * @throws \Exception
	 */
	public function SendPasswordResetEmail($UserPublicId)
	{
		$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserByPublicId($UserPublicId);
		if ($oUser instanceof User)
		{
			$bPrevState = \Aurora\Api::skipCheckUserRole(true);
			$sPasswordResetHash = $this->generateHash($oUser->Id, 'reset-password', __FUNCTION__);
			$oUser->setExtendedProp(self::GetName().'::PasswordResetHash', $sPasswordResetHash);
			\Aurora\Modules\Core\Module::Decorator()->UpdateUserObject($oUser);
			\Aurora\Api::skipCheckUserRole($bPrevState);

			$sRecoveryEmail = $oUser->{self::GetName().'::RecoveryEmail'};
			$sConfirmRecoveryEmailHash = $oUser->{self::GetName().'::ConfirmRecoveryEmailHash'};
			if  (!empty($sRecoveryEmail) && empty($sConfirmRecoveryEmailHash))
			{
				return $this->sendPasswordResetMessage($sRecoveryEmail, $sPasswordResetHash);
			}
		}
		
		throw new \Exception($this->i18N('ERROR_RECOVERY_EMAIL_NOT_FOUND'));
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

		if ($oUser instanceof User)
		{
			return $oUser->PublicId;
		}
		return '';
	}
	
	/**
	 * Changes password if hash is valid.
	 * @param string $Hash
	 * @param string $NewPassword
	 * @return boolean
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
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
            $aAccounts = $oMail->GetAccounts($oUser->Id);
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
				\Aurora\System\Api::UserSession()->DeleteAllUserSessions($oUser->Id);
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
