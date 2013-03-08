<?php

namespace Fuel\Tasks;

class Create_User
{
	public function run($driver, $username, $password = null)
	{
		\Package::load('sesame');
		\Module::load('sesame');
		if (! $password) {
			$password = $username;
			$username = $driver;
			$driver = 'default';
		}

		if (! $user = \Sesame\Sesame::instance()->signup([
			'username' => $username,
			'password' => $password,
		]))
		{
			throw new Exception("Could not create user but couldn't say why");
		}

		\Cli::write(\Cli::color("User $username created successfully", 'green'));
	}
}
