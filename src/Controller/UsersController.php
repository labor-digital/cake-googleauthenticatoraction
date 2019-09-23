<?php
namespace GoogleAuthenticatorAction\Controller;

use Cake\Event\Event;
use Cake\Network\Exception\BadRequestException;
use CakeDC\Users\Controller\Traits\CustomUsersTableTrait;
use Cake\Network\Exception\InternalErrorException;

/**
 * @property \CakeDC\Users\Controller\Component\GoogleAuthenticatorComponent $GoogleAuthenticator
 */
class UsersController extends \App\Controller\AppController
{
	
	use CustomUsersTableTrait;
	
	public $components = ['CakeDC/Users.GoogleAuthenticator'];
	
	
	public function beforeFilter(Event $event)
	{
		if($this->Auth->user())
			$this->Auth->allow('verify');
		
		return parent::beforeFilter($event);
	}
	
	public function verify()
	{
		$method = $this->request->getSession()->read('GoogleAuthenticatorAction.method');
		if(empty($method) || array_search($method, ['get', 'post', 'put']) === false)
			throw new InternalErrorException();
		
		if(!$this->request->is('post'))
		{
			$this->set('_url', $method == "post" ? $this->request->getSession()->read('GoogleAuthenticatorAction.postRedirectUrl') : ['plugin' => "GoogleAuthenticatorAction", 'controller' => "Users", 'action' => "verify"]);
			$this->set('_name', $method == "post" ? $this->request->getSession()->read('GoogleAuthenticatorAction.postActionName') : $this->request->getSession()->read('GoogleAuthenticatorAction.getActionName'));
			
			return $this->render();
		}
		
		$data = $this->request->getData();
		
		if(!isset($data['_verificationCode']))
			throw new BadRequestException(__d('GoogleAuthenticatorAction', 'Please provide the Verification code at the index `_verificationCode`.'));
		
		if(empty($data['_verificationCode']))
		{
			$this->Flash->error(__d('GoogleAuthenticatorAction', 'Verification code is invalid. Try again'), 'default', [], 'auth');
			return $this->render();
		}
		
		$code = $data['_verificationCode'];
		$userEntity = $this->getUsersTable()->get($this->Auth->user('id'));
		
		if(empty($userEntity->secret_verified) || empty($userEntity->secret))
			throw new InternalErrorException(__d('GoogleAuthenticatorAction', 'Something went wrong. Either you´re not logged in or don´t have a valid secret'));
		
		if(!$this->GoogleAuthenticator->verifyCode($userEntity->secret, $code))
		{
			$this->Flash->error(__d('GoogleAuthenticatorAction', 'Verification code is invalid. Try again'), 'default', [], 'auth');
			return $this->render();
		}
		
		$getRedirectUrl = $this->request->getSession()->read('GoogleAuthenticatorAction.getRedirectUrl');
		if(empty($getRedirectUrl))
			throw new InternalErrorException(__d('GoogleAuthenticatorAction', 'Something went wrong. There is no redirect-url in the session'));
		
		$this->request->getSession()->write('GoogleAuthenticatorAction.getVerified', true);
		$this->request->getSession()->delete('GoogleAuthenticatorAction.getRedirectUrl');
		$this->request->getSession()->delete('GoogleAuthenticatorAction.getActionName');
		
		return $this->redirect($getRedirectUrl);
	}
	
}