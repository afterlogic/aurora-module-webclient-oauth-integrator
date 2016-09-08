<?php

class EOAuthIntegratorError extends AEnumeration
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

class OAuthIntegratorWebclientModule extends AApiModule
{
	public $oManager = null;
	
	protected $aSettingsMap = array(
		'AuthModuleName' => array('StandardAuth', 'string'),
		'OnlyPasswordForAccountCreate' => array(true, 'bool'),
		'Services' => array(array(), 'array')
	);
	
	public function init() 
	{
		$this->incClasses(array(
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
	}
	
	public function OAuthIntegratorEntry()
	{
		$mResult = false;
		$this->broadcastEvent(
			'OAuthIntegratorAction', 
			array(
				'service' => $this->oHttp->GetQuery('oauth', ''),
				'result' => &$mResult
			)
		);
		
		if (false !== $mResult && is_array($mResult))
		{
			$iAuthUserId = isset($_COOKIE['AuthToken']) ? \CApi::getAuthenticatedUserId($_COOKIE['AuthToken']) : null;
			
			$oUser = null;
			$sOAuthIntegratorRedirect = 'login';
			if (isset($_COOKIE["oauth-redirect"]))
			{
				$sOAuthIntegratorRedirect = $_COOKIE["oauth-redirect"];
				@setcookie('oauth-redirect', null);
			}

			$oAccount = new \COAuthAccount($this->GetName(), array());
			$oAccount->Type = $mResult['type'];
			$oAccount->AccessToken = isset($mResult['access_token']) ? $mResult['access_token'] : '';
			$oAccount->RefreshToken = isset($mResult['refresh_token']) ? $mResult['refresh_token'] : '';
			$oAccount->IdSocial = $mResult['id'];
			$oAccount->Name = $mResult['name'];
			$oAccount->Email = $mResult['email'];

			$oAccountOld = $this->oManager->getAccountById($oAccount->IdSocial, $oAccount->Type);
			if ($oAccountOld)
			{
				if ($sOAuthIntegratorRedirect == 'register')
				{
					\CApi::Location2('./?error=' . EOAuthIntegratorError::AccountAlreadyConnected . '&module=' . $this->GetName());
				}
				
				$oAccountOld->setScope('auth');
				$oAccount->Scopes = $oAccountOld->Scopes;
				$oAccount->iId = $oAccountOld->iId;
				$oAccount->IdUser = $oAccountOld->IdUser;
				$this->oManager->updateAccount($oAccount);

				$oUser = \CApi::GetModuleDecorator('Core')->GetUser($oAccount->IdUser);
			}
			else
			{
				if ($iAuthUserId)
				{
					$this->broadcastEvent('CreateAccount', array(
						array(
							'UserName' => $mResult['name'],
							'UserId' => $iAuthUserId
						),
						'result' => &$oUser
					));
				}

				$this->broadcastEvent('CreateOAuthAccount', array(
					'result' => &$oUser
				));

				if ($oUser instanceOf \CUser)
				{
					$oAccount->IdUser = $oUser->iId;
					$oAccount->setScopes($mResult['scopes']);
					$this->oManager->createAccount($oAccount);
				}
			}

			if ($sOAuthIntegratorRedirect === 'login' || $sOAuthIntegratorRedirect === 'register')
			{
				if ($oUser)
				{
					@setcookie(
						System\Service::AUTH_TOKEN_KEY, 
						\CApi::UserSession()->Set(
							array(
								'token' => 'auth',
								'sign-me' => true,
								'id' => $oUser->iId,
								'time' => time() + 60 * 60 * 24 * 30
							)
						)
					);
					\CApi::Location2('./');
				}
				else
				{
					\CApi::Location2('./?error=' . EOAuthIntegratorError::AccountNotAllowedToLogIn . '&module=' . $this->GetName());
				}
			}
			else
			{
				$sResult = $mResult !== false ? 'true' : 'false';
				$sErrorCode = '';

				if ($oUser && $iAuthUserId && $oUser->iId !== $iAuthUserId)
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
	 * Returns all external services names.
	 * 
	 * @return array
	 */
	public function GetServices()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$aServices = array();
		$this->broadcastEvent('GetServices', array(&$aServices));
		return $aServices;
	}
	
	/**
	 * Returns all external services settings.
	 * 
	 * @return array
	 */
	public function GetAppData()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$aSettings = array(
			'EOAuthIntegratorError' => (new \EOAuthIntegratorError)->getMap(),
		);
		
		$oUser = \CApi::getAuthenticatedUser();
		if (!empty($oUser) && $oUser->Role === \EUserRole::SuperAdmin)
		{
			$aServices = array();
			$this->broadcastEvent('GetServicesSettings', array(&$aServices));
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
	 * Updates all external services settings.
	 * 
	 * @param array $Services Array with services settings passed by reference.
	 * 
	 * @return boolean
	 */
	public function UpdateSettings($Services)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::TenantAdmin);
		
		$this->broadcastEvent('UpdateServicesSettings', array($Services));
		
		return true;
	}
	
	/**
	 * Get all external accounts.
	 * 
	 * @return array
	 */
	public function GetAccounts()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$UserId = \CApi::getAuthenticatedUserId();
		$aResult = array();
		$mAccounts = $this->oManager->getAccounts($UserId);
		if (is_array($mAccounts))
		{
			foreach ($mAccounts as $oAccount) {
				$aResult[] = array(
					'Id' => $oAccount->iObjectId,
					'Type' => $oAccount->Type,
					'Email' => $oAccount->Email,
					'Name' => $oAccount->Name,
				);
			}
		}
		return $aResult;
	}
	
	/**
	 * Returns oauth account with specified type.
	 * @param string $Type Type of oauth account.
	 * @return \COAuthAccount
	 */
	public function GetAccount($Type)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		return $this->oManager->getAccount(
			\CApi::getAuthenticatedUserId(), 
			$Type
		);
	}	
	
	/**
	 * Deletes oauth account with specified type.
	 * @param string $Type Type of oauth account.
	 * @return boolean
	 */
	public function DeleteAccount($Type)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Customer);
		
		return $this->oManager->deleteAccount(
			\CApi::getAuthenticatedUserId(), 
			$Type
		);
	}		
	
	public function UpdateAccount(\COAuthAccount $oAccount)
	{
		return $this->oManager->updateAccount($oAccount);
	}
	
	/**
	 * Deletes all oauth accounts which are owened by the specified user.
	 * 
	 * @param int $iUserId User Identificator.
	 */	
	public function onAfterDeleteUser($iUserId)
	{
		$this->oManager->deleteAccountByUserId($iUserId);
	}
}
