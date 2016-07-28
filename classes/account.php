<?php

/* -AFTERLOGIC LICENSE HEADER- */

/**
 * @property int $Id
 * @property int $IdUser
 * @property string $IdSocial
 * @property string $Type
 * @property string $Name
 * @property string $Email
 * @property string $AccessToken
 * @property string $RefreshToken
 * @property string $Scopes
 * @property bool $Disabled
 *
 * @package Social
 * @subpackage Classes
 */
class COAuthAccount extends AEntity
{
	public function __construct($sModule, $oParams)
	{
		parent::__construct(get_class($this), $sModule);

		$this->__USE_TRIM_IN_STRINGS__ = true;

		$this->setStaticMap(array(
			'IdUser'		=> array('int', 0),
			'IdSocial'		=> array('string', ''),
			'Type'			=> array('string', ''),
			'Name'			=> array('string', ''),
			'Email'			=> array('string', ''),
			'AccessToken'	=> array('string', ''),
			'RefreshToken'	=> array('string', ''),
			'Scopes'		=> array('string', ''),
			'Disabled'		=> array('bool', false)
		));
	}
	
	public static function createInstance($sModule = 'ExternalServices', $oParams = array())
	{
		return new COAuthAccount($sModule, $oParams);
	}
	
	public function getScopesAsArray()
	{
		$aResult = array();
		if (!$this->Disabled)
		{
			$aResult = array_map(function($sValue) {
					return strtolower($sValue);
				}, explode(' ', $this->Scopes)	
			);	
		}
		
		return $aResult;
	}
	
	/**
	 * @param string $sScope
	 *
	 * @return bool
	 */
	public function issetScope($sScope)
	{
		return /*'' === $this->Scopes || */false !== strpos(strtolower($this->Scopes), strtolower($sScope));
	}	
	
	/**
	 * @param string $sScope
	 */
	public function setScope($sScope)
	{
		$aScopes = $this->getScopesAsArray();
		if (!array_search($sScope, array_unique($aScopes)))
		{
			$aScopes[] = $sScope;
			$this->Scopes = implode(' ', array_unique($aScopes));
		}
	}	
	
	/**
	 * @param array $aScopes
	 */
	public function setScopes($aScopes)
	{
		$this->Scopes = implode(' ', array_unique(array_merge($aScopes, $this->getScopesAsArray())));
	}	

	/**
	 * @param string $sScope
	 */
	public function unsetScope($sScope)
	{
		$aScopes = array_map(function($sValue) {
				return strtolower($sValue);
			}, explode(' ', $this->Scopes)	
		);
		$mResult = array_search($sScope, $aScopes);
		if ($mResult !== false)
		{
			unset($aScopes[$mResult]);
			$this->Scopes = implode(' ', $aScopes);
		}
	}	
	
	public function toResponseArray()
	{
		return $this->toArray();
	}
}
