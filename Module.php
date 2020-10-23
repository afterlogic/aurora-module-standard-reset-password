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
			)
		);
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
			'InfoText' => $this->getConfig('InfoText', ''),
			'BottomInfoHtmlText' => $this->getConfig('BottomInfoHtmlText', ''),
		];
		
		$oAuthenticatedUser = \Aurora\System\Api::getAuthenticatedUser();
		if ($oAuthenticatedUser instanceof \Aurora\Modules\Core\Classes\User && $oAuthenticatedUser->isNormalOrTenant())
		{
			$aSettings['RecoveryEmail'] = $oAuthenticatedUser->{$this->GetName() . '::RecoveryEmail'};
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
	
	/**
	 * Broadcasts Reset event to other modules to log in the system with specified parameters.
	 * 
	 * @param string $Name New name for user.
	 * @param string $Login Login for authentication.
	 * @param string $Password Password for authentication.
	 * @param int $UserId Identifier of user which will contain new account.
	 * @return array
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function Reset($Login, $Password, $UserId)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);

		if (empty($UserId))
		{
			$bPrevState = \Aurora\System\Api::skipCheckUserRole(true);

			$UserId = \Aurora\Modules\Core\Module::Decorator()->CreateUser(0, $Login);
			
			\Aurora\System\Api::skipCheckUserRole($bPrevState);
		}

		if (empty($UserId))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}

		$mResult = false;

		$refArgs = array (
			'Login' => $Login,
			'Password' => $Password,
			'UserId' => $UserId,
		);
		$this->broadcastEvent(
			'Reset', 
			$refArgs,
			$mResult
		);

		if (!empty($mResult))
		{
			$oLoginDecorator = \Aurora\Modules\StandardLoginFormWebclient\Module::Decorator();
			$mResult = $oLoginDecorator->Login($Login, $Password);
			\Aurora\System\Api::getAuthenticatedUserId($mResult['AuthToken']);
		}

		return $mResult;
	}
	/***** public functions might be called with web API *****/
}
