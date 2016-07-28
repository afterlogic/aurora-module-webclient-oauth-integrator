<?php

class ExternalServicesModule extends AApiModule
{
	public $oApiManager = null;
	
	protected $aSettingsMap = array(
		'Services' => array(array(), 'array')
	);
	
	public function init() 
	{
		$this->incClass('account');
		$this->incClass('connector');
		$this->incClass('OAuthClient/http');
		$this->incClass('OAuthClient/oauth_client');
		
		$this->oManager = $this->GetManager('account');
		$this->AddEntry('external-services', 'ExternalServicesEntry');
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
			$this->Process($mResult);
		}
	}

	private function Process($mResult)
	{
		$oUser = null;
		$sExternalServicesRedirect = 'login';
		$sError = '';
		$sErrorMessage = '';
		if (isset($_COOKIE["external-services-redirect"]))
		{
			$sExternalServicesRedirect = $_COOKIE["external-services-redirect"];
			@setcookie('external-services-redirect', null);
		}

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
				$this->oManager->updateAccount($oAccount);
				
				$oUser = \CApi::GetModuleDecorator('Core')->GetUser($oAccountOld->IdUser);
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
				$sAuthToken = \CApi::UserSession()->Set(
					array(
						'token' => 'auth',
						'sign-me' => true,
						'id' => $oUser->iId,
						'time' => time() + 60 * 60 * 24 * 30
					)						
				);
				
				@setcookie(\System\Service::AUTH_TOKEN_KEY, $sAuthToken);
			}
			\CApi::Location2('./' . $sError);
		}
	}
}
