<?php

namespace Sesame;

\Package::load('sesame');
\Package::load('orm');

class Model_User extends \Orm\Model {
	// Sesame doesn't call this; the controller does. That's why we can be specific that this model needs a username
	// and password - obviously not something that Sesame can assume to be the case.
	// The result is passed to Sesame if true.
	public static function authenticate($username, $password)
	{
		$user = static::query()
			->where('username', $username)
			->where('password', static::_hash($password))
			->get_one();

		if (! $user)
		{
			return false;
		}

		return $user;
	}

	// This is called from the driver when Sesame asks it to create a user.
	public static function signup($user_data)
	{
		$user = static::forge();
		$user->username = $user_data['username'];
		$user->password = static::_hash($user_data['password']);
		$user->save();

		return $user;
	}

	protected static function _hash($password)
	{
		return base64_encode(Util::hash($password));
	}

	protected static $_table_name = "sesame__users";

	protected static $_properties = array(
		'id' => [
			'data_type' => 'int',
			'label' => 'ID',
		],
		'username' => [
			'data_type' => 'varchar',
			'label' => 'username',
		],
		'password' => [
			'data_type' => 'varchar',
			'label' => 'password',
		],
	);
}
