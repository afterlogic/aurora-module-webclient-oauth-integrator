<?php

class ExternalServicesModule extends AApiModule
{
	public $oApiSocialManager = null;
	
	protected $aSettingsMap = array(
		'Services' => array(array(), 'array')
	);
	
	public function init() 
	{
		parent::init();

		$this->incClass('social');
		$this->incClass('connector');
		$this->incClass('OAuthClient/http');
		$this->incClass('OAuthClient/oauth_client');
		
		$this->oApiSocialManager = $this->GetManager('social');
		$this->includeTemplate('BasicAuthClient_LoginView', 'Login-After', 'templates/login.html');
		$this->AddEntry('external-services', 'ExternalServicesEntry');
	}
	
	public function ExternalServicesEntry()
	{
		$sConnector = $this->oHttp->GetQuery('external-services', '');
		$sTenantHash = $this->oHttp->GetQuery('hash', '');
		
		$mResult = false;
		$oConnector = $this->GetConnector($sConnector);
		if ($oConnector)
		{
			$oTenant = $this->GetTenantFromCookieOrHash($sTenantHash);
			$mResult = $oConnector->Init($oTenant);
		}
		if (false !== $mResult && is_array($mResult))
		{
			$this->Process($mResult);
		}
	}
	
	public function GetConnector($sConnector)
	{
		$oConnector = false;
		if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'classes/connectors' . DIRECTORY_SEPARATOR .  strtolower($sConnector) . DIRECTORY_SEPARATOR . 'index.php'))
		{
			require_once __DIR__ . DIRECTORY_SEPARATOR .'classes/connectors/' . strtolower($sConnector) . '/index.php';
			if (method_exists("CExternalServicesConnector" . $sConnector , 'CreateInstance'))
			{
				$oConnector = call_user_func('\CExternalServicesConnector' . $sConnector . '::CreateInstance', $this);
			}			
		}
		
		return $oConnector;
		
	}	
	
	public function GetAppData($oUser = null)
	{
		$sTenantHash = null;
		@setcookie('p7tenantHash', $sTenantHash);
		$oTenant = $this->GetTenantFromCookieOrHash($sTenantHash);
		$aAppData = array();

		if ($oTenant)
		{
			foreach ($oTenant->getSocials() as $oSocial)
			{
				$aAppData[$oSocial->SocialName]['Allow'] = $oSocial->SocialAllow;
				$aAppData[$oSocial->SocialName]['Id'] = $oSocial->SocialId;
				$aAppData[$oSocial->SocialName]['Scopes'] = $oSocial->SocialScopes;
			}
		}
		
		return $aAppData;
	}

	public function GetTenantHashFromCookie()
	{
		return isset($_COOKIE['p7tenantHash']) ? $_COOKIE['p7tenantHash'] : '';
	}
	
	public function GetTenantFromCookieOrHash($sTenantHash = '')
	{
		$oTenant = null;
		$sTenantHash = $sTenantHash ? $sTenantHash : $this->GetTenantHashFromCookie();
		$oApiTenantsManager = /* @var $oApiTenantsManager \CApiTenantsManager */ \CApi::Manager('tenants');
		if ($oApiTenantsManager)
		{
			if ($sTenantHash)
			{
				$oTenant = $oApiTenantsManager->getTenantByHash($sTenantHash);
			}
			else
			{
				$oAccount /* @var $oAccount \CAccount */ = \api_Utils::GetDefaultAccount();
				if ($oAccount && 0 < $oAccount->IdTenant)
				{
					$oTenant = $oApiTenantsManager->getTenantById($oAccount->IdTenant);
				}
				else
				{
					$oTenant = $oApiTenantsManager->getDefaultGlobalTenant();
				}
			}
		}
		return $oTenant;
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

		$oSocial = new \CSocialAccount();
		$oSocial->TypeStr = $mResult['type'];
		$oSocial->AccessToken = isset($mResult['access_token']) ? $mResult['access_token'] : '';
		$oSocial->RefreshToken = isset($mResult['refresh_token']) ? $mResult['refresh_token'] : '';
		$oSocial->IdSocial = $mResult['id'];
		$oSocial->Name = $mResult['name'];
		$oSocial->Email = $mResult['email'];

		if ($sExternalServicesRedirect === 'login')
		{
			self::SetValuesToCookie($mResult);

			$oSocialOld = $this->oApiSocialManager->getSocialById($oSocial->IdSocial, $oSocial->TypeStr);
			if ($oSocialOld)
			{
				$oSocialOld->setScope('auth');
				$oSocial->Scopes = $oSocialOld->Scopes;
				$this->oApiSocialManager->updateSocial($oSocial);
				
				$oCoreDecorator = \CApi::GetModuleDecorator('Core');
				$oUser = $oCoreDecorator->GetUser($oSocialOld->IdUser);
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
					$oSocial->IdUser = $oUser->iId;
					$oSocial->setScopes($mResult['scopes']);
					$this->oApiSocialManager->createSocial($oSocial);
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

	public static function SetValuesToCookie($aValues)
	{
		@setcookie("p7social", \CApi::EncodeKeyValues($aValues));
	}
	
	public static function ClearValuesFromCookie()
	{
		@setcookie("p7social", null);
	}
	
	public function GetSocialAccounts()
	{
		$mResult['Result'] = false;
		$oTenant = null;
		$oAccount /* @var $oAccount \CAccount */ = \api_Utils::GetDefaultAccount();
		$oApiTenants = /* @var $oApiTenants \CApiSocialManager */ \CApi::Manager('tenants');
		
		if ($oAccount && $oApiTenants)
		{
			$oTenant = (0 < $oAccount->IdTenant) ? $oApiTenants->getTenantById($oAccount->IdTenant) :
				$oApiTenants->getDefaultGlobalTenant();
		}
		if ($oTenant)
		{
			$oApiSocial /* @var $oApiSocial \CApiSocialManager */ = \CApi::Manager('social');
			$mResult['Result'] = $oApiSocial->getSocials($oAccount->IdAccount);
		}
		return $mResult;
	}
}
