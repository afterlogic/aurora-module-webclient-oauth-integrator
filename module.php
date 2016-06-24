<?php

class ExternalServicesModule extends AApiModule
{
	public $oApiSocialManager = null;
	
	public function init() 
	{
		parent::init();
		$this->oApiSocialManager = $this->GetManager('social');
	}
}
