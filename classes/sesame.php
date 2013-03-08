<?php

namespace Sesame;

class Sesame
{
	protected $_user;
	protected $_driver;

	public static function _init()
	{
		\Config::load('sesame', true);
	}

	public static function instance($class_or_config = null)
	{
		$class_or_config = $class_or_config ?: 'default';

		$driver = \Config::get('sesame.drivers.' . $class_or_config);
		if (! $driver)
		{
			$driver = $class_or_config;
		}

		if (! \Autoloader::load($driver))
		{
			throw new \ConfigException('Tried to load login driver ' . $driver . ' but it was not found');
		}

		return new static($driver);
	}

	public static function get_user()
	{
		return static::instance()->user();
	}


	/**
	 * Create an instance of Sesame using the provided login driver.
	 *
	 * I see no reason to make this protected. instance() is only a helper.
	 *
	 * @param	$driver	string	Class to use as a login driver. Assumed to exist.
	 */
	public function __construct($driver)
	{
		$this->_driver = $driver;
	}

	public function user()
	{
		if ($this->_user)
		{
			return $this->_user;
		}

		if ($user_data = \Session::get($this->_session_key()))
		{
			return $this->user_ok($user_data);
		}

		return false;
	}

	public function user_ok($user_data)
	{
		\Session::set($this->_session_key(), $user_data);

		$driver = $this->_driver;
		$user = $driver::retrieve_user($user_data);

		$this->_user = $user;
		return $user;
	}

	public function login()
	{
		$driver = $this->_driver;

		$user_data = $driver::login();

		// Not an error to return nothing; the consuming code may call user_ok manually.
		if ($user_data)
		{
			return $this->user_ok($user_data);
		}
	}

	public function logout()
	{
		$this->_user = null;
		\Session::delete($this->_session_key());
	}

	public function signup($user_data)
	{
		$driver = $this->_driver;
		return $driver::make_user($user_data);
	}

	protected function _session_key()
	{
		return 'sesame.' . $this->_driver . '.user_data';
	}
}
