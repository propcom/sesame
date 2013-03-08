<?php

namespace Sesame;

class Driver_Default
{
	public static function login()
	{
		\Session::set('auth.requested_uri', \Request::string());
		\Response::redirect('/login');
	}

	public static function retrieve_user($user_id)
	{
		return Model_User::find($user_id);
	}

	public static function make_user($user_data)
	{
		return Model_User::signup($user_data);
	}
}
