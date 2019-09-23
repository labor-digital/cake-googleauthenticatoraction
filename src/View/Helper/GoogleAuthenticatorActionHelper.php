<?php
namespace GoogleAuthenticatorAction\View\Helper;

class GoogleAuthenticatorActionHelper extends \Cake\View\Helper
{
	
	public $helpers = ["Form"];
	
	
	public function verificationCodeControl(array $options = [])
	{
		$restrictedAction = $this->getView()->get('_isRestrictedAction');
		if(empty($restrictedAction))
			return "";
		
		$options['autocomplete'] = "off";
		
		return $this->Form->control('_verificationCode', $options);
	}
	
}