<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\StandardResetPasswordWebclient;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
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
			
			if (!$mHash)
			{
				$mHash = $oMin->CreateMin(
					$sMinId,
					array(
						'UserId' => $iUserId
					)
				);
			}
			else
			{
				if (isset($mHash['__hash__']))
				{
					$mHash = $mHash['__hash__'];
				}
				else
				{
					$mHash = '';
				}
			}
		}
		
		return $mHash;
	}

	protected function getAccountByEmail($Email)
    {
		$oAccount = null;
		$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserByPublicId($Email);
		if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
		{
			$bPrevState = \Aurora\Api::skipCheckUserRole(true);
			$oAccount = \Aurora\Modules\Mail\Module::Decorator()->GetAccountByEmail($Email, $oUser->EntityId);
			\Aurora\Api::skipCheckUserRole($bPrevState);
		}
		return $oAccount;
	}

	protected function sendResetPasswordNotification($Email, $Hash)
    {
		$oModuleManager = \Aurora\System\Api::GetModuleManager();
		$sSiteName = $oModuleManager->getModuleConfigValue('Core', 'SiteName');

        $sBody = \file_get_contents($this->GetPath().'/templates/mail/ResetPassword.html');
        $oMail = new \PHPMailer();

        if (\is_string($sBody))
        {
            $sBody = \strtr($sBody, array(
                '{{RESET_PASSWORD_URL}}' => \rtrim($this->oHttp->GetFullUrl(), '\\/ ') . '/index.php#reset-password/' . $Hash,
                '{{SITE_NAME}}' => $sSiteName
            ));

            $sBody = preg_replace_callback(
                "/[\w\-]*\.png/Uim",
                function ($matches) use ($oMail) {
                    $sResult = $matches[0];

                    if (\file_exists($this->GetPath().'/templates/'.$matches[0]))
                    {
                        $sContentId = \preg_replace("/\.\w*/", "", $matches[0]);

                        $oMail->AddEmbeddedImage($this->GetPath().'/templates/'.$matches[0], $sContentId);
                        $sResult = "cid:".$sContentId;
                    }

                    return $sResult;
                },
                $sBody
            );
        }

        $sSubject = 'Reset your password';
        $sFrom = $this->getConfig('NotificationEmail', '');
		$oSendAccount = $this->getAccountByEmail($sFrom);

//        $sType ='smtp';// $this->getConfig('NotificationType', 'mail');
//        if (\strtolower($sType) === 'mail')
//        {
//            $oMail->isMail();
//        }
//        else if (\strtolower($sType) === 'smtp')
//        {
	        $oSendServer = $oSendAccount->getServer();
			$oMail->isSMTP();
            $oMail->Host = $oSendServer->OutgoingServer;//$this->getConfig('NotificationHost', '');
            $oMail->Port = $oSendServer->OutgoingPort;
			switch ($oSendServer->SmtpAuthType)
			{
				case \Aurora\Modules\Mail\Enums\SmtpAuthType::NoAuthentication:
					$oMail->SMTPAuth = false; //(bool) $this->getConfig('NotificationUseAuth', false);
					break;
				case \Aurora\Modules\Mail\Enums\SmtpAuthType::UseSpecifiedCredentials:
					$oMail->SMTPAuth = true; //(bool) $this->getConfig('NotificationUseAuth', false);
					$oMail->Username = $oSendServer->SmtpLogin;// $this->getConfig('NotificationLogin', '');
					$oMail->Password = $oSendServer->SmtpPassword;// $this->getConfig('NotificationPassword', '');
					break;
				case \Aurora\Modules\Mail\Enums\SmtpAuthType::UseUserCredentials:
					$oMail->SMTPAuth = true; //(bool) $this->getConfig('NotificationUseAuth', false);
					$oMail->Username = $oSendAccount->IncomingLogin;// $this->getConfig('NotificationLogin', '');
					$oMail->Password = $oSendAccount->getPassword();// $this->getConfig('NotificationPassword', '');
					break;
			}
            $oMail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
//        }

        $oMail->setFrom($sFrom);
        $oMail->addAddress($Email);
        $oMail->addReplyTo($sFrom, $sSiteName);

        $oMail->Subject = $sSubject;
        $oMail->Body = $sBody;
        $oMail->isHTML(true); // Set email format to HTML

        try {
            $mResult = $oMail->send();
//			var_dump($mResult);
        } catch (\Exception $oEx){
            throw new \Exception('Failed to send notification. Reason: ' . $oEx->getMessage());
        }

        return $mResult;
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
		if ($oMin)
		{
			$mHash = $oMin->GetMinByHash($sHash);
			if (isset($mHash['__hash__'], $mHash['UserId']))
			{
				$iUserId = $mHash['UserId'];
				$bPrevState = \Aurora\Api::skipCheckUserRole(true);
				$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUser($iUserId);
				\Aurora\Api::skipCheckUserRole($bPrevState);
			}
		}
		return $oUser;
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
			'ServerModuleName' => $this->getConfig('ServerModuleName', 'StandardResetPasswordWebclient'),
			'HashModuleName' => $this->getConfig('HashModuleName', 'login'),
			'CustomLogoUrl' => $this->getConfig('CustomLogoUrl', ''),
			'BottomInfoHtmlText' => $this->getConfig('BottomInfoHtmlText', ''),
		];
		
		$oAuthenticatedUser = \Aurora\System\Api::getAuthenticatedUser();
		if ($oAuthenticatedUser instanceof \Aurora\Modules\Core\Classes\User && $oAuthenticatedUser->isNormalOrTenant())
		{
			$aSettings['RecoveryEmail'] = $oAuthenticatedUser->{self::GetName() . '::RecoveryEmail'};
		}
		
		return $aSettings;
	}
	
	/**
	 * Updates per user settings.
	 * @param string $RecoveryEmail
	 * @return boolean
	 */
	public function UpdateSettings($RecoveryEmail = null)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$oAuthenticatedUser = \Aurora\System\Api::getAuthenticatedUser();
		if ($oAuthenticatedUser instanceof \Aurora\Modules\Core\Classes\User && $oAuthenticatedUser->isNormalOrTenant())
		{
			if ($RecoveryEmail !== null)
			{
				$oAuthenticatedUser->{self::GetName().'::RecoveryEmail'} = $RecoveryEmail;
			}
			$oCoreDecorator = \Aurora\Modules\Core\Module::Decorator();
			return $oCoreDecorator->UpdateUserObject($oAuthenticatedUser);
		}

		return false;
	}
	
	public function GetRecoveryEmail($UserPublicId)
	{
		$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserByPublicId($UserPublicId);
		$sRecoveryEmail = $oUser->{self::GetName().'::RecoveryEmail'};
		$aRecoveryEmailParts = explode('@', $sRecoveryEmail);
		$iPartsCount = count($aRecoveryEmailParts);
		$sResult = '';
		if ($iPartsCount > 0)
		{
			$sResult = substr($aRecoveryEmailParts[0], 0, 3) . '***';
		}
		if ($iPartsCount > 1)
		{
			$sResult .= '@' . $aRecoveryEmailParts[$iPartsCount - 1];
		}
		return $sResult;
	}
	
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
            $mResult = $this->сhangePassword($oAccount, $NewPassword);
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
