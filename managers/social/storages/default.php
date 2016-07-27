<?php

/* -AFTERLOGIC LICENSE HEADER- */

/**
 * @package Social
 * @subpackage Storages
 */
class CApiExternalServicesSocialStorage extends AApiManagerStorage
{
	/**
	 * @param CApiGlobalManager &$oManager
	 */
	public function __construct($sStorageName, AApiManager &$oManager)
	{
		parent::__construct('social', $sStorageName, $oManager);
	}
	
	/**
	 * @param int $iIdAccount
	 *
	 * @return array
	 */
	public function getSocials($iIdAccount)
	{
	
	}	
	
	/**
	 * @param int $iIdAccount
	 * @param int $iType
	 * @return string
	 */
	public function getSocial($iIdAccount, $iType)
	{
	
	}
	
	/**
	 * @param string $sIdSocial
	 * @param int $iType
	 *
	 * @return \CSocialAccount
	 */
	public function getSocialById($sIdSocial, $iType)
	{
		
	}	

	/**
	 * @param CSocialAccount &$oSocial
	 *
	 * @return bool
	 */
	public function createSocial(CSocialAccount &$oSocial)
	{
		
	}

	/**
	 * @param CSocialAccount &$oSocial
	 *
	 * @return bool
	 */
	public function updateSocial(CSocialAccount &$oSocial)
	{

	}
	
	/**
	 * @param int $iIdAccount
	 * @param int $iType
	 *
	 * @return bool
	 */
	public function deleteSocial($iIdAccount, $iType)
	{
		
	}
	
	public function deleteSocialByAccountId($iIdAccount)
	{

	}	

	/**
	 * @param string $sEmail
	 *
	 * @return bool
	 */
	public function deleteSocialsByEmail($sEmail)
	{
	
	}	
	
	/**
	 * @param CSocialAccount &$oSocial
	 *
	 * @return bool
	 */
	public function isSocialExists(CSocialAccount $oSocial)
	{
		
	}	
}

