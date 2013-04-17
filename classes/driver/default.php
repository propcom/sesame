<?php

namespace Sesame;

class Driver_Default
{
	public static function login()
	{
		\Session::set('sesame.original_uri', \Uri::string());
		\Response::redirect('/login');
	}

	public static function retrieve_user($user_id)
	{
		return Model_User::query(['username' => $user_id])->get_one();
	}

	public static function make_user($user_data)
	{
		return Model_User::signup($user_data);
	}
}
