<?php

class CExternalServicesConnector
{
	public static $ConnectorName = 'connector';
	public static $Debug = true;
	public static $Scopes = array();
	
	public $oModule = null;

	public static function CreateInstance($oModule)
	{
		return new static($oModule);
	}
	
	public function __construct($oModule) 
	{
		$this->oModule = $oModule;
	}
	
	public function GetConnectorId()
	{
		$sResult = '';
		$aServicesConfig = $this->oModule->getConfig('Services');
		if (isset($aServicesConfig[static::$ConnectorName]) && 
				isset($aServicesConfig[static::$ConnectorName]['Id']))
		{
			$sResult = $aServicesConfig[static::$ConnectorName]['Id'];
		}
		
		return $sResult;
	}
	
	public function GetConnectorSecret()
	{
		$sResult = '';
		$aServicesConfig = $this->oModule->getConfig('Services');
		if (isset($aServicesConfig[static::$ConnectorName]) && 
				isset($aServicesConfig[static::$ConnectorName]['Secret']))
		{
			$sResult = $aServicesConfig[static::$ConnectorName]['Secret'];
		}
		
		return $sResult;
	}
	
	public function Init($oTenant = null) 
	{
		self::$Scopes = isset($_COOKIE['external-services-scopes']) ? 
			explode('|', $_COOKIE['external-services-scopes']) : array();
	}

	public function HasApiKey() 
	{
		return false;
	}

	public function GetSupportedScopes() 
	{
		return array();
	}

	protected function _socialError($oClientError, $sSocialName)
	{
		\CApi::Log($sSocialName, ' error');
		\CApi::LogObject($oClientError);
	}
}