<?php

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
		$this->setNonAuthorizedMethods(array('GetServices'));
		$this->AddEntry('external-services', 'OAuthIntegratorEntry');
		$this->includeTemplate('StandardLoginFormWebclient_LoginView', 'Login-After', 'templates/SignInButtonsView.html');
	}
	
	public function OAuthIntegratorEntry()
	{
		$mResult = false;
		$this->broadcastEvent(
			'OAuthIntegratorAction', 
			array(
				'service' => $this->oHttp->GetQuery('external-services', ''),
				'result' => &$mResult
			)
		);
		
		if (false !== $mResult && is_array($mResult))
		{
			$oUser = null;
			$sOAuthIntegratorRedirect = 'login';
			if (isset($_COOKIE["external-services-redirect"]))
			{
				$sOAuthIntegratorRedirect = $_COOKIE["external-services-redirect"];
				@setcookie('external-services-redirect', null);
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
				$oAccountOld->setScope('auth');
				$oAccount->Scopes = $oAccountOld->Scopes;
				$oAccount->iId = $oAccountOld->iId;
				$oAccount->IdUser = $oAccountOld->IdUser;
				$this->oManager->updateAccount($oAccount);

				$oUser = \CApi::GetModuleDecorator('Core')->GetUser($oAccount->IdUser);
			}
			else
			{
				if (isset($_COOKIE['AuthToken']))
				{
					$iUserId = \CApi::getAuthenticatedUserId($_COOKIE['AuthToken']);
				}
				$this->broadcastEvent('CreateAccount', array(
					array(
						'UserName' => $mResult['name'],
						'UserId' => $iUserId
					),
					'result' => &$oUser
				));

				if ($oUser instanceOf \CUser)
				{
					$oAccount->IdUser = $oUser->iId;
					$oAccount->setScopes($mResult['scopes']);
					$this->oManager->createAccount($oAccount);
				}
			}

			if ($sOAuthIntegratorRedirect === 'login')
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
				}
				\CApi::Location2('./');
			}
			else
			{
				$sResult = $mResult !== false ? 'true' : 'false';
				$sErrorMessage = '';
				echo 
				"<script>"
					. "if (typeof(window.opener.".$mResult['type']."SettingsViewModelCallback) !== 'undefined') {"
					.		"window.opener.".$mResult['type']."SettingsViewModelCallback(".$sResult . ", '".$sErrorMessage."');"
					.		"window.close();"
					. "}"
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
		$oUser = \CApi::getAuthenticatedUser();
		if ($oUser && $oUser->Role === 0)
		{
			$aServices = array();
			$this->broadcastEvent('GetServicesSettings', array(&$aServices));
			return array(
				'Services' => $aServices,
			);
		}
		
		if ($oUser && $oUser->Role === 1)
		{
			return array(
				'AuthModuleName' => $this->getConfig('AuthModuleName'),
				'OnlyPasswordForAccountCreate' => $this->getConfig('OnlyPasswordForAccountCreate'),
			);
		}
		
		return null;
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
	 * Get all external accounts.
	 * 
	 * @return array
	 */
	public function GetAccount($Type)
	{
		return $this->oManager->getAccount(
			\CApi::getAuthenticatedUserId(), 
			$Type
		);
	}	
	
	/**
	 * Get all external accounts.
	 * 
	 * @return array
	 */
	public function DeleteAccount($Type)
	{
		return $this->oManager->deleteAccount(
			\CApi::getAuthenticatedUserId(), 
			$Type
		);
	}		
}
