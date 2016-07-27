<?php

/* -AFTERLOGIC LICENSE HEADER- */

/**
 * @package Social
 * @subpackage Storages
 */
class CApiExternalServicesSocialDbStorage extends CApiExternalServicesSocialStorage
{
	/**
	 * @var CDbStorage $oConnection
	 */
	protected $oConnection;

	/**
	 * @var CApiDomainsCommandCreator
	 */
	protected $oCommandCreator;

	/**
	 * @param CApiGlobalManager &$oManager
	 */
	public function __construct(AApiManager &$oManager)
	{
		parent::__construct('db', $oManager);

		$this->oConnection =& $oManager->GetConnection();
		$this->oCommandCreator =& $oManager->GetCommandCreator(
			$this, array(
				EDbType::MySQL => 'CApiExternalServicesSocialCommandCreatorMySQL',
				EDbType::PostgreSQL => 'CApiExternalServicesSocialCommandCreatorPostgreSQL'
			)
		);
	}
	
	/**
	 * @param string $sSql
	 *
	 * @return CSocialAccount
	 */
	protected function getSocialBySql($sSql)
	{
		$oSocial = null;
		if ($this->oConnection->Execute($sSql))
		{
			$oRow = $this->oConnection->GetNextRecord();
			if ($oRow)
			{
				$oSocial = new CSocialAccount();
				$oSocial->InitByDbRow($oRow);
			}
			$this->oConnection->FreeResult();
		}

		$this->throwDbExceptionIfExist();
		return $oSocial;
	}	
	
	/**
	 * @param string $sIdSocial
	 * @param string $sType
	 *
	 * @return CSocialAccount
	 */
	public function getSocialById($sIdSocial, $sType)
	{
		return $this->getSocialBySql($this->oCommandCreator->getSocialById($sIdSocial, $sType));
	}	
	
	/**
	 * @param int $iIdAccount
	 * @param string $sType
	 *
	 * @return CSocialAccount
	 */
	public function getSocial($iIdAccount, $sType)
	{
		return $this->getSocialBySql($this->oCommandCreator->getSocial((int) $iIdAccount, $sType));
	}	
	
	/**
	 * @param int $iIdAccount
	 *
	 * @return array
	 */
	public function getSocials($iIdAccount)
	{
		$aSocials = array();
		if ($this->oConnection->Execute($this->oCommandCreator->getSocials((int) $iIdAccount)))
		{
			$oRow = null;
			while (false !== ($oRow = $this->oConnection->GetNextRecord()))
			{
				$oSocial = new \CSocialAccount();
				$oSocial->InitByDbRow($oRow);
				$aSocials[] = $oSocial;
			}
		}

		$this->throwDbExceptionIfExist();
		return $aSocials;
	}		
	
	/**
	 * @param CSocialAccount &$oSocial
	 *
	 * @return bool
	 */
	public function createSocial(\CSocialAccount &$oSocial)
	{
		$bResult = false;
		if ($this->oConnection->Execute($this->oCommandCreator->createSocial($oSocial)))
		{
			$oSocial->Id = $this->oConnection->GetLastInsertId('awm_social', 'id');
			$bResult = true;
		}

		$this->throwDbExceptionIfExist();
		return $bResult;
	}

	/**
	 * @param CSocialAccount &$oSocial
	 *
	 * @return bool
	 */
	public function updateSocial(\CSocialAccount &$oSocial)
	{
		$bResult = $this->oConnection->Execute($this->oCommandCreator->updateSocial($oSocial));
		$this->throwDbExceptionIfExist();
		return $bResult;
	}
	
	/**
	 * @param int $iIdAccount
	 * @param string $sType
	 *
	 * @return bool
	 */
	public function deleteSocial($iIdAccount, $sType)
	{
		$bResult = $this->oConnection->Execute($this->oCommandCreator->deleteSocial($iIdAccount, $sType));
		$this->throwDbExceptionIfExist();
		return $bResult;
	}
	
	/**
	 * @param int $iIdAccount
	 *
	 * @return bool
	 */
	public function deleteSocialByAccountId($iIdAccount)
	{
		$bResult = $this->oConnection->Execute($this->oCommandCreator->deleteSocialByAccountId($iIdAccount));
		$this->throwDbExceptionIfExist();
		return $bResult;
	}
	
	/**
	 * @param string $sEmail
	 *
	 * @return bool
	 */
	public function deleteSocialsByEmail($sEmail)
	{
		$bResult = $this->oConnection->Execute($this->oCommandCreator->deleteSocialsByEmail($sEmail));
		$this->throwDbExceptionIfExist();
		return $bResult;
	}	

	/**
	 * @param CSocialAccount &$oSocial
	 *
	 * @return bool
	 */
	public function isSocialExists(CSocialAccount $oSocial)
	{
		$bResult = false;
		if ($this->oConnection->Execute($this->oCommandCreator->isSocialExists($oSocial->IdUser, $oSocial->TypeStr)))
		{
			$oRow = $this->oConnection->GetNextRecord();
			if ($oRow)
			{
				$bResult = 0 < (int) $oRow->social_count;
			}

			$this->oConnection->FreeResult();
		}
		$this->throwDbExceptionIfExist();
		return $bResult;
	}	

}