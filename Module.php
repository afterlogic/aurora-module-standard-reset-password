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
			)
		);
		
		$this->aErrors = [
			Enums\ErrorCodes::WrongPassword => $this->i18N('ERROR_WRONG_PASSWORD'),
		];
	}

	protected function getMinId($iUserId, $sSalt = '')
	{
		return \implode('|', array(self::GetName(), $iUserId, \md5($iUserId), $sSalt));
	}
	
	protected function generateHash($iUserId, $sSalt = '')
	{
		$mHash = '';
		$oMin = \Aurora\Modules\Min\Module::Decorator();
		if ($oMin)
		{
			$sMinId = $this->getMinId($iUserId, $sSalt);
			$mHash = $oMin->GetMinByID($sMinId);

			if ($mHash)
			{
				$mHash = $oMin->DeleteMinByID($sMinId);
			}

			$mHash = $oMin->CreateMin(
				$sMinId,
				array(
					'UserId' => $iUserId
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
                '{{RESET_PASSWORD_URL}}' => \rtrim($this->oHttp->GetFullUrl(), '\\/ ') . '/index.php#reset-password/' . $sHash,
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
	 * Sends notification email with recovery link.
	 * @param string $Email
	 * @param string $Hash
	 * @return bool
	 * @throws \Exception
	 */
	protected function sendResetPasswordNotification($Email, $Hash)
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

		$oModuleManager = \Aurora\System\Api::GetModuleManager();
		$sSiteName = $oModuleManager->getModuleConfigValue('Core', 'SiteName');
        $oMail->setFrom($sFrom);
        $oMail->addAddress($Email);
        $oMail->addReplyTo($sFrom, $sSiteName);

        $oMail->Subject = 'Reset your password';
        $oMail->Body = $this->getResetPasswordNotificationBody($Hash, $sSiteName);
        $oMail->isHTML(true); // Set email format to HTML

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

	/**
	 * Returns user with identifier obtained from the hash.
	 *
	 * @param string $sHash
	 * @return \Aurora\Modules\Core\Classes\User
	 */
	protected function getUserByHash($sHash)
	{
		$oUser = null;
		$oMin = \Aurora\Modules\Min\Module::Decorator();
		$mHash = $oMin ? $oMin->GetMinByHash($sHash) : null;
		if (!empty($mHash) && isset($mHash['__hash__'], $mHash['UserId']))
		{
			$iRecoveryLinkLifetimeMinutes = $this->getConfig('RecoveryLinkLifetimeMinutes', 0);
			$bRecoveryLinkAlive = ($iRecoveryLinkLifetimeMinutes === 0);
			if (!$bRecoveryLinkAlive)
			{
				$iDiffMinutes = (time() - $mHash['__time__']) / 60;
				if ($iDiffMinutes < $iRecoveryLinkLifetimeMinutes)
				{
					$bRecoveryLinkAlive = true;
				}
				else
				{
					throw new \Exception('Recovery link is outdated');
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
		
		$oAuthenticatedUser = \Aurora\System\Api::getAuthenticatedUser();
		$aSettings = [
			'HashModuleName' => $this->getConfig('HashModuleName', 'login'),
			'CustomLogoUrl' => $this->getConfig('CustomLogoUrl', ''),
			'BottomInfoHtmlText' => $this->getConfig('BottomInfoHtmlText', ''),
			'RecoveryEmail' => $this->getCoveredRecoveryEmail($oAuthenticatedUser),
		];
		
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
			$oAuthenticatedUser->{self::GetName().'::RecoveryEmail'} = $RecoveryEmail;
			$oCoreDecorator = \Aurora\Modules\Core\Module::Decorator();
			if ($oCoreDecorator->UpdateUserObject($oAuthenticatedUser))
			{
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
			$sPasswordResetHash = $this->generateHash($oUser->EntityId, __FUNCTION__);
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
		
		$oUser = $this->getUserByHash($Hash);

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

        $oUser = $this->getUserByHash($Hash);

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
