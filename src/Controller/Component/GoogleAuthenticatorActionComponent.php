<?php
namespace GoogleAuthenticatorAction\Controller\Component;

use Cake\Controller\Component;
use Cake\Event\Event;
use Cake\Network\Exception\InternalErrorException;
use Cake\Core\Configure;
use CakeDC\Users\Controller\Traits\CustomUsersTableTrait;

class GoogleAuthenticatorActionComponent extends Component
{
	
	use CustomUsersTableTrait;
	
	protected $_controller;
	protected $_request;
	protected $_session;
	protected $_restrictedActions;
	
	public $components = ['CakeDC/Users.GoogleAuthenticator'];

	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \Cake\Controller\Component::initialize()
	 */
	public function initialize(array $config)
	{
		parent::initialize($config);
		$this->setConfig($config);
		
		$this->_controller = $this->getController();
		$this->_request = $this->_controller->request;
		$this->_response = $this->_controller->response;
		$this->_session = $this->_request->getSession();
	}
	
	/**
	 * 
	 * @param string|array $action
	 * @throws InternalErrorException
	 */
	public function restrict($action)
	{
		if(!Configure::read('Users.GoogleAuthenticator.login'))
			throw new InternalErrorException(__d('GoogleAuthenticatorAction', 'Please enable Google Authenticator first.'));
		
		if(empty($action))
			return;
		
		if(!is_string($action) && !is_array($action))
			return;
		
		if(!is_array($action))
			$action = [$action];
		
		$this->setConfig('restrictedActions', $action, true);
	}
	
	/**
	 * 
	 * @param unknown $key
	 * @param unknown $value
	 * @param boolean $merge
	 */
	public function setConfig($key, $value = null, $merge = true)
	{
		parent::setConfig($key, $value, $merge);
		
		if($key != "restrictedActions")
			return;
		
		$restrictedActionsTemp = $this->getConfig('restrictedActions');
		if(empty($restrictedActionsTemp) || !is_array($restrictedActionsTemp))
			$restrictedActionsTemp = [];
		
		$restrictedActions = [];
		foreach($restrictedActionsTemp as $idx => $val)
		{
			if(is_numeric($idx))
			{
				$restrictedActions[$val] = ['get' => true];
				continue;
			}
			
			if(!is_array($val))
				$val = [$val];
			
			$methods = [];
			foreach($val as $valIdx => $valVal)
			{
				if(is_numeric($valIdx))
				{
					$method = $valVal;
					$methodSetting = true;
				}
				else
				{
					$method = $valIdx;
					$methodSetting = $valVal;
				}
				
				if(array_search($method, ['get', 'post', 'put']) === false)
					continue;

				$lMethodSetting = true;
				if(is_array($methodSetting))
				{
					$lMethodSetting = [];
					foreach($methodSetting as $methodSettingIdx => $methodSettingVal)
						if(is_numeric($methodSettingIdx))
							$lMethodSetting[$methodSettingVal] = true;
						else
							$lMethodSetting[$methodSettingIdx] = $methodSettingVal;
				}
				$methods[$method] = $lMethodSetting;
			}
			
			if(empty($methods))
				$methods = ['get'];
			
			$restrictedActions[$idx] = $methods;
		}
		
		parent::setConfig($key, $restrictedActions, false);
		$this->_restrictedActions = $this->getConfig('restrictedActions');
	}
	
	/**
	 * 
	 * @param Event $event
	 * @return void|void|\Cake\Http\Response|NULL
	 */
	public function startup(Event $event)
	{
		if(empty($this->_restrictedActions[$this->_request->getParam('action')]))
			return;
		
		$this->_controller->set("_isRestrictedAction", true);
		
		if($this->_request->is('get'))
			return $this->_startupGet();
		else if($this->_request->is(['post', 'put']))
			return $this->_startupPostPut();
	}
	
	/**
	 * 
	 * @return void|\Cake\Http\Response|NULL
	 */
	protected function _startupGet()
	{
		if(empty($this->_restrictedActions[$this->_request->getParam('action')]['get']))
			return;
		
		$settings = is_array($this->_restrictedActions[$this->_request->getParam('action')]['get']) ? $this->_restrictedActions[$this->_request->getParam('action')]['get'] : [];
		
		if($this->_session->check('GoogleAuthenticatorAction.getVerified'))
		{
			$this->_session->delete('GoogleAuthenticatorAction.method');
			$this->_session->delete('GoogleAuthenticatorAction.getVerified');
			return;
		}
		else
		{
			$this->_session->write('GoogleAuthenticatorAction.method', "get");
			$this->_session->write('GoogleAuthenticatorAction.getRedirectUrl', $this->_request->getRequestTarget());
			$this->_session->write('GoogleAuthenticatorAction.getActionName', !empty($settings['name']) ? $settings['name'] : $this->_request->getParam('controller').":".$this->_request->getParam('action'));
			return $this->_controller->redirect(['plugin' => "GoogleAuthenticatorAction", 'controller' => "Users", 'action' => "verify"]);
		}
	}
	
	/**
	 * 
	 * @throws \Exception
	 * @return void|\Cake\Http\Response|NULL
	 */
	protected function _startupPostPut()
	{
		if(empty($this->_restrictedActions[$this->_request->getParam('action')]['post']) && empty($this->_restrictedActions[$this->_request->getParam('action')]['put']))
			return;
		
		$code = $this->_request->getData('_verificationCode');
		$settings = !empty($this->_restrictedActions[$this->_request->getParam('action')]['post']) && is_array($this->_restrictedActions[$this->_request->getParam('action')]['post']) ?
			$this->_restrictedActions[$this->_request->getParam('action')]['post'] :
			(!empty($this->_restrictedActions[$this->_request->getParam('action')]['put']) && is_array($this->_restrictedActions[$this->_request->getParam('action')]['put']) ?
				$this->_restrictedActions[$this->_request->getParam('action')]['put'] :
				[]
			)
		;
		
		if(!empty($settings['noInlineCode']) && empty($code))
		{
			$this->_session->write('GoogleAuthenticatorAction.method', "post");
			$this->_session->write('GoogleAuthenticatorAction.postRedirectUrl', $this->_request->getRequestTarget());
			$this->_session->write('GoogleAuthenticatorAction.postActionName', !empty($settings['name']) ? $settings['name'] : $this->_request->getParam('controller').":".$this->_request->getParam('action'));
			return $this->_controller->redirect(['plugin' => "GoogleAuthenticatorAction", 'controller' => "Users", 'action' => "verify"]);
		}
		
		if(empty($code))
			throw new \Exception();
		
		$userEntity = $this->getUsersTable()->get($this->_controller->Auth->user('id'));
		if(empty($userEntity->secret_verified) || empty($userEntity->secret))
			throw new \Exception(__d('GoogleAuthenticatorAction', 'Something went wrong. Either you´re not logged in or don´t have a valid secret'));
		
		if(!$this->GoogleAuthenticator->verifyCode($userEntity->secret, $code))
			throw new \Exception();
		
		$this->_session->delete('GoogleAuthenticatorAction.method');
		$this->_session->delete('GoogleAuthenticatorAction.postRedirectUrl');
		$this->_session->delete('GoogleAuthenticatorAction.postActionName');
	}
	
}