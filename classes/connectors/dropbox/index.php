<?php

class CExternalServicesConnectorDropbox extends CExternalServicesConnector
{
	public static $ConnectorName = 'dropbox';
			
	public function GetSupportedScopes()
	{
		return array('auth', 'filestorage');
	}
	
	public function CreateClient($oTenant)
	{
		$sRedirectUrl = rtrim(\MailSo\Base\Http::SingletonInstance()->GetFullUrl(), '\\/ ').'/?external-services='.self::$ConnectorName;
		if (!strpos($sRedirectUrl, '://localhost'))
		{
			$sRedirectUrl = str_replace('http:', 'https:', $sRedirectUrl);
		}

		$oClient = new \oauth_client_class;
		$oClient->debug = self::$Debug;
		$oClient->debug_http = self::$Debug;
		$oClient->server = 'Dropbox2';
		$oClient->redirect_uri = $sRedirectUrl;
		$oClient->client_id = $this->GetConnectorId();
		$oClient->client_secret = $this->GetConnectorSecret();
		$oClient->configuration_file = $this->oModule->GetPath() .'/classes/OAuthClient/'.$oClient->configuration_file;
		
		return $oClient;
	}
	
	public function Init($oTenant = null)
	{
		parent::Init($oTenant);

		$bResult = false;
		$oUser = null;

		$oClient = self::CreateClient($oTenant);
				
		if($oClient)
		{
			if(($success = $oClient->Initialize()))
			{
				if(($success = $oClient->Process()))
				{
					if(strlen($oClient->access_token))
					{
						$success = $oClient->CallAPI(
							'https://api.dropbox.com/1/account/info', 
							'GET', array(), array('FailOnAccessError'=>true), $oUser);
					}
				}
				$success = $oClient->Finalize($success);
			}

			if($oClient->exit)
			{
				$bResult = false;
				exit;
			}

			if($success && $oUser)
			{
				// if you need re-ask user for permission
				//$oClient->ResetAccessToken();

				$aSocial = array(
					'type' => self::$ConnectorName,
					'id' => $oUser->uid,
					'name' => $oUser->display_name,
					'email' => isset($oUser->email) ? $oUser->email : '',
					'access_token' => $oClient->access_token,
					'scopes' => self::$Scopes
						
				);

				\CApi::Log('social_user_' . self::$ConnectorName);
				\CApi::LogObject($oUser);
				$bResult = $aSocial;
			}
			else
			{
				$bResult = false;
				$oClient->ResetAccessToken();
				self::_socialError($oClient->error, self::$ConnectorName);
			}
		}
		
		return $bResult;
	}
}