<?php

class ExternalServicesModule extends AApiModule
{
	public $oApiManager = null;
	
	protected $aSettingsMap = array(
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
		$this->AddEntry('external-services', 'ExternalServicesEntry');
		$this->includeTemplate('BasicAuthClient_LoginView', 'Login-After', 'templates/SignInButtonsView.html');
	}
	
	public function ExternalServicesEntry()
	{
		$mResult = false;
		$this->broadcastEvent(
			'ExternalServicesAction', 
			array(
				'service' => $this->oHttp->GetQuery('external-services', ''),
				'result' => &$mResult
			)
		);
		
		if (false !== $mResult && is_array($mResult))
		{
			$oUser = null;
			$sExternalServicesRedirect = 'login';

			$oAccount = new \COAuthAccount($this->GetName(), array());
			$oAccount->Type = $mResult['type'];
			$oAccount->AccessToken = isset($mResult['access_token']) ? $mResult['access_token'] : '';
			$oAccount->RefreshToken = isset($mResult['refresh_token']) ? $mResult['refresh_token'] : '';
			$oAccount->IdSocial = $mResult['id'];
			$oAccount->Name = $mResult['name'];
			$oAccount->Email = $mResult['email'];

			if ($sExternalServicesRedirect === 'login')
			{
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
					$this->broadcastEvent('CreateAccount', array(
						array(
							'UserName' => $mResult['name']
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
	 * @param \CUser $oUser User settings are obtained for.
	 * 
	 * @return array
	 */
	public function GetAppData($oUser = null)
	{
		if ($oUser && $oUser->Role === 0)
		{
			$aServices = array();
			$this->broadcastEvent('GetServicesSettings', array(&$aServices));
			return array(
				'Services' => $aServices
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
}
