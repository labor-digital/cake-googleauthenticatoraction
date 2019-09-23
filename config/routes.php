<?php
use Cake\Routing\Router;

Router::plugin(
    'GoogleAuthenticatorAction',
    ['path' => '/gaa'],
    function ($routes) {
    	$routes->connect('/verify', ['plugin' => "GoogleAuthenticatorAction", 'controller' => "Users", 'action' => "verify"]);
    }
);