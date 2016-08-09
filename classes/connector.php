<?php

class COAuthIntegratorConnector
{
	public static $ConnectorName = 'connector';
	public static $Debug = true;
	public static $Scopes = array();
	
	public $oModule = null;

	public function __construct($oModule) 
	{
		$this->oModule = $oModule;
	}
	
	public function Init() 
	{
		self::$Scopes = isset($_COOKIE['external-services-scopes']) ? 
			explode('|', $_COOKIE['external-services-scopes']) : array();
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