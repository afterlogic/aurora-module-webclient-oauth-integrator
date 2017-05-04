<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\OAuthIntegratorWebclient;

/**
 * @internal
 * @package Modules
 */
class EOAuthIntegratorError extends \AbstractEnumeration
{
	const ServiceNotAllowed = 1;
	const AccountNotAllowedToLogIn = 2;
	const AccountAlreadyConnected = 3;
	
	protected $aConsts = array(
		'ServiceNotAllowed' => self::ServiceNotAllowed,
		'AccountNotAllowedToLogIn' => self::AccountNotAllowedToLogIn,
		'AccountAlreadyConnected' => self::AccountAlreadyConnected,
	);
}

/**
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractWebclientModule
{
	public $oManager = null;
	
	/***** private functions *****/
	/**
	 * Initializes module.
	 * 
	 * @ignore
	 */
	public function init()
	{
		$this->incClasses(
			array(
				'OAuthClient/http',
				'OAuthClient/oauth_client',
				'account',
				'connector'
			)
		);
		
		$this->oManager = $this->GetManager('account');
		$this->AddEntry('oauth', 'OAuthIntegratorEntry');
		$this->includeTemplate('StandardLoginFormWebclient_LoginView', 'Login-After', 'templates/SignInButtonsView.html', $this->GetName());
		$this->includeTemplate('StandardRegisterFormWebclient_RegisterView', 'Register-After', 'templates/SignInButtonsView.html', $this->GetName());
		$this->subscribeEvent('Core::AfterDeleteUser', array($this, 'onAfterDeleteUser'));
		$this->subscribeEvent('Core::GetAccounts', array($this, 'onGetAccounts'));
	}
	
	/**
	 * Deletes all oauth accounts which are owened by the specified user.
	 * 
	 * @ignore
	 * @param int $iUserId User identifier.
	 */
	public function onAfterDeleteUser($aArgs, &$iUserId)
	{
		$this->oManager->deleteAccountByUserId($iUserId);
	}
	
	/**
	 * 
	 * @param array $aArgs
	 * @param array $aResult
	 */
	public function onGetAccounts($aArgs, &$aResult)
	{
		$aUserInfo = \Aurora\System\Api::getAuthenticatedUserInfo($aArgs['AuthToken']);
		if (isset($aUserInfo['userId']))
		{
			$iUserId = $aUserInfo['userId'];
			$mAccounts = $this->oManager->getAccounts($iUserId);
			if (\is_array($mAccounts))
			{
				foreach ($mAccounts as $oAccount) {
					$aResult[] = array(
						'Id' => $oAccount->EntityId,
						'UUID' => $oAccount->UUID,
						'Type' => $oAccount->getName(),
						'Email' => $oAccount->Email
					);
				}
			}
		}
	}		
	/***** private functions *****/
	
	/***** public functions *****/
	/**
	 * @ignore
	 */
	public function OAuthIntegratorEntry()
	{
		$mResult = false;
		$aArgs = array(
			'Service' => $this->oHttp->GetQuery('oauth', '')
		);
		$this->broadcastEvent(
			'OAuthIntegratorAction',
			$aArgs,
			$mResult
		);
		
		if (false !== $mResult && \is_array($mResult))
		{
			$oCoreModuleDecorator = \Aurora\System\Api::GetModuleDecorator('Core');
			$iAuthUserId = isset($_COOKIE['AuthToken']) ? \Aurora\System\Api::getAuthenticatedUserId($_COOKIE['AuthToken']) : null;
			
			$oUser = null;
			$sOAuthIntegratorRedirect = 'login';
			if (isset($_COOKIE["oauth-redirect"]))
			{
				$sOAuthIntegratorRedirect = $_COOKIE["oauth-redirect"];
				@\setcookie('oauth-redirect', null);
			}
			
			$oOAuthAccount = new \COAuthAccount($this->GetName());
			$oOAuthAccount->Type = $mResult['type'];
			$oOAuthAccount->AccessToken = isset($mResult['access_token']) ? $mResult['access_token'] : '';
			$oOAuthAccount->RefreshToken = isset($mResult['refresh_token']) ? $mResult['refresh_token'] : '';
			$oOAuthAccount->IdSocial = $mResult['id'];
			$oOAuthAccount->Name = $mResult['name'];
			$oOAuthAccount->Email = $mResult['email'];
			
			$oAccountOld = $this->oManager->getAccountById($oOAuthAccount->IdSocial, $oOAuthAccount->Type);
			if ($oAccountOld)
			{
				if ($sOAuthIntegratorRedirect === 'register')
				{
					\Aurora\System\Api::Location2(
						'./?error=' . EOAuthIntegratorError::AccountAlreadyConnected . '&module=' . $this->GetName()
					);
				}
				
				if (!$oAccountOld->issetScope('auth') && $sOAuthIntegratorRedirect !== 'connect')
				{
					\Aurora\System\Api::Location2(
						'./?error=' . EOAuthIntegratorError::AccountNotAllowedToLogIn . '&module=' . $this->GetName()
					);
				}
				
				$oOAuthAccount->setScopes($mResult['scopes']);
				$oOAuthAccount->EntityId = $oAccountOld->EntityId;
				$oOAuthAccount->IdUser = $oAccountOld->IdUser;
				$this->oManager->updateAccount($oOAuthAccount);
				
				$oUser = $oCoreModuleDecorator->GetUser($oOAuthAccount->IdUser);
			}
			else
			{
				if ($iAuthUserId)
				{
					$aArgs = array(
						'UserName' => $mResult['name'],
						'UserId' => $iAuthUserId
					);
					$this->broadcastEvent(
						'CreateAccount::before', 
						$aArgs,
						$oUser
					);
				}
				
				$aArgs = array();
				$this->broadcastEvent(
					'CreateOAuthAccount', 
					$aArgs,
					$oUser
				);
				
				if (!($oUser instanceOf \CUser)  && 
						($sOAuthIntegratorRedirect === 'register' || $this->getConfig('AllowNewUsersRegister', false)))
				{
					\Aurora\System\Api::skipCheckUserRole(true);
					
					try
					{
						$iUserId = $oCoreModuleDecorator->CreateUser(0, $oOAuthAccount->Email);
						if ($iUserId)
						{
							$oUser = $oCoreModuleDecorator->GetUser($iUserId);
						}
					}
					catch (\Aurora\System\Exceptions\ApiException $oException)
					{
						if ($oException->getCode() === \Aurora\System\Notifications::UserAlreadyExists)
						{
							\Aurora\System\Api::Location2(
								'./?error=' . EOAuthIntegratorError::AccountAlreadyConnected . '&module=' . $this->GetName()
							);
						}
					}
					
					\Aurora\System\Api::skipCheckUserRole(false);
				}
				
				if ($oUser instanceOf \CUser)
				{
					$oOAuthAccount->IdUser = $oUser->EntityId;
					$oOAuthAccount->setScopes($mResult['scopes']);
					$this->oManager->createAccount($oOAuthAccount);
				}
			}
			
			if ($sOAuthIntegratorRedirect === 'login' || $sOAuthIntegratorRedirect === 'register')
			{
				if ($oUser)
				{
					@\setcookie(
						\Aurora\System\Application::AUTH_TOKEN_KEY,
						\Aurora\System\Api::UserSession()->Set(
							array(
								'token' => 'auth',
								'sign-me' => true,
								'id' => $oUser->EntityId,
								'time' => \time() + 60 * 60 * 24 * 30,
								'account' => $oOAuthAccount->EntityId
							)
						)
					);
					\Aurora\System\Api::Location2('./');
				}
				else
				{
					\Aurora\System\Api::Location2(
						'./?error=' . EOAuthIntegratorError::AccountNotAllowedToLogIn . '&module=' . $this->GetName()
					);
				}
			}
			else
			{
				$sResult = $mResult !== false ? 'true' : 'false';
				$sErrorCode = '';
				
				if ($oUser && $iAuthUserId && $oUser->EntityId !== $iAuthUserId)
				{
					$sResult = 'false';
					$sErrorCode = EOAuthIntegratorError::AccountAlreadyConnected;
				}
				
				echo
				"<script>"
					.	" try {"
					.		"if (typeof(window.opener.".$mResult['type']."ConnectCallback) !== 'undefined') {"
					.			"window.opener.".$mResult['type']."ConnectCallback(".$sResult . ", '".$sErrorCode."','".$this->GetName()."');"
					.		"}"
					.	" }"
					.	" finally  {"
					.		"window.close();"
					.	" }"
				. "</script>";
				exit;
			}
		}
	}
	
	/**
	 * Returns oauth account with specified type.
	 * 
	 * @param string $Type Type of oauth account.
	 * @return \COAuthAccount
	 */
	public function GetAccount($Type)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		return $this->oManager->getAccount(
			\Aurora\System\Api::getAuthenticatedUserId(),
			$Type
		);
	}
	
	/**
	 * Updates oauth acount.
	 * 
	 * @param \COAuthAccount $oAccount Oauth account.
	 * @return boolean
	 */
	public function UpdateAccount(\COAuthAccount $oAccount)
	{
		return $this->oManager->updateAccount($oAccount);
	}
	/***** public functions *****/
	
	/***** public functions might be called with web API *****/
	/**
	 * Returns all oauth services names.
	 * 
	 * @return array
	 */
	public function GetServices()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
	}
	
	/**
	 * Returns all oauth services settings for authenticated user.
	 * 
	 * @return array
	 */
	public function GetSettings()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$aSettings = array(
			'EOAuthIntegratorError' => (new EOAuthIntegratorError)->getMap(),
		);
		
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		if (!empty($oUser) && $oUser->Role === \EUserRole::SuperAdmin)
		{
			$aArgs = array();
			$aServices = array();
			$this->broadcastEvent(
				'GetServicesSettings', 
				$aArgs,
				$aServices
			);
			$aSettings['Services'] = $aServices;
		}
		
		if (!empty($oUser) && $oUser->Role === \EUserRole::NormalUser)
		{
			$aSettings['AuthModuleName'] = $this->getConfig('AuthModuleName');
			$aSettings['OnlyPasswordForAccountCreate'] = $this->getConfig('OnlyPasswordForAccountCreate');
		}
		
		return $aSettings;
	}
	
	/**
	 * Updates all oauth services settings.
	 * 
	 * @param array $Services Array with services settings passed by reference.
	 * 
	 * @return boolean
	 */
	public function UpdateSettings($Services)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::TenantAdmin);
		
		$aArgs = array(
			'Services' => $Services
		);
		$this->broadcastEvent(
			'UpdateServicesSettings', 
			$aArgs
		);
		
		return true;
	}
	
	/**
	 * Get all oauth accounts.
	 * 
	 * @return array
	 */
	public function GetAccounts()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$UserId = \Aurora\System\Api::getAuthenticatedUserId();
		$aResult = array();
		$mAccounts = $this->oManager->getAccounts($UserId);
		if (\is_array($mAccounts))
		{
			foreach ($mAccounts as $oAccount) {
				$aResult[] = array(
					'Id' => $oAccount->EntityId,
					'UUID' => $oAccount->UUID,
					'Type' => $oAccount->Type,
					'Email' => $oAccount->Email,
					'Name' => $oAccount->Name,
				);
			}
		}
		return $aResult;
	}
	
	/**
	 * Deletes oauth account with specified type.
	 * 
	 * @param string $Type Type of oauth account.
	 * @return boolean
	 */
	public function DeleteAccount($Type)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::Customer);
		
		return $this->oManager->deleteAccount(
			\Aurora\System\Api::getAuthenticatedUserId(),
			$Type
		);
	}
	/***** public functions might be called with web API *****/
}
