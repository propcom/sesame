<?php

namespace Sesame;

class Controller_Sesame extends \Controller_Template
{
	public function before()
	{
		\Config::load('sesame',true);
		$this->template = \View::forge(\Config::get('sesame.template'));
	}

	// This handles /sesame/login if you load the sesame module. Note that there is only one point at which Sesame is
	// actually called.
	public function action_login()
	{
		// The Login_Default class in the sesame package sets this when Auth calls login()
		$redirect = \Session::get('sesame.original_uri');

		// The Model_User in this module requires a username and password to auth.
		$fieldset = \Fieldset::forge('sesame_login');

		$fieldset->add('username', 'Username', [
			'type' => 'text',
			'value' => \Input::post('username')
		], [
			'required',
		]);

		$fieldset->add('password', 'Password', [
			'type' => 'password',
		], [
			'required',
		]);

		$fieldset->add('submit', '', [
			'type' => 'submit',
			'value' => 'Log in',
		]);

		$fieldset->add('redirect', '', [
			'type' => 'hidden',
			'value' => $redirect
		]);

		$errors = [];
		if (\Input::method() == 'POST')
		{
			if (! $errors = $fieldset->error()) {
				// We don't use config to determine this model: all of this is far too simple to be worth making so
				// flexible. The Login_Default driver also uses Model_User in retrieve_user. That driver is the only
				// thing that actually goes into config.
				$user = Model_User::authenticate(\Input::post('username'), \Input::post('password'));

				if ($user)
				{
					\Sesame::instance()->user_ok($user->username);
					\Response::redirect(\Input::post('redirect'));
				}
			}
		}

		$this->template->content = \View::forge('login', [
			'fieldset' => $fieldset,
			'errors' => $errors,
			'failed' => \Input::method() == 'POST'
		]);
	}
}
