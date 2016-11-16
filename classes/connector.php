<?php
/**
 *
 * @package Classes
 * @subpackage AuthIntegrator
 */
class COAuthIntegratorConnector
{
	protected $Name = 'connector';
	public static $Debug = false;
	public static $Scopes = array();
	
	public $oModule = null;

	public function __construct($oModule) 
	{
		$this->oModule = $oModule;
	}
	
	public function Init($sId, $sSecret, $sScope = '') 
	{
	}
}