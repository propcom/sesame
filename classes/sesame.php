<?php

namespace Sesame;

class Sesame
{
	protected $_user;
	protected $_driver;
	protected static $_instance = [];

	public static function _init()
	{
		\Config::load('sesame', true);
	}

	/**
	 * Return an instance of Sesame.
	 *
	 * The given parameter is first used as a configuration setting in sesame.drivers, and then as a class name.
	 *
	 * @param	string	$class_or_config	(Optional) A class name or config item name. Defaults to 'default'.
	 * @return 	Sesame	Instance of called class.
	 */
	public static function instance($class_or_config = null)
	{
		$class_or_config = $class_or_config ?: 'default';

		$driver = \Config::get('sesame.drivers.' . $class_or_config);
		if (! $driver)
		{
			$driver = $class_or_config;
		}

		if ($instance = \Arr::get(static::$_instance, $driver)) {
			return $instance;
		}

		if (! class_exists($driver) && ! \Autoloader::load($driver))
		{
			throw new \ConfigException('Tried to load login driver ' . $driver . ' but it was not found');
		}

		return static::$_instance[$driver] = new static($driver);
	}

	/**
	 * Convenience to get the logged-in user from the default driver.
	 *
	 * @return	User	Instance of your user object
	 */
	public static function get_user()
	{
		return static::instance()->user();
	}


	/**
	 * Create an instance of Sesame using the provided login driver.
	 *
	 * @param	$driver	string	Class to use as a login driver. Assumed to exist.
	 */
	public function __construct($driver)
	{
		// No need to make this a protected function; why so restrict the user?
		$this->_driver = $driver;
	}

	/**
	 * Return the currently logged-in user, or false if not logged in.
	 *
	 * Fetches User object from your driver if it has not yet done so.
	 *
	 * @return	User|false	Your user object or false for no user.
	 */
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

	/**
	 * Tell Sesame the user has logged in.
	 *
	 * Its parameter is passed to your driver's retrieve_user method, and so you should pass in exactly enough
	 * information to uniquely identify a user.
	 *
	 * If the user object has a set_login_time method, this is called with the current time.
	 *
	 * @param	mixed	$user_data	Data to pass to your driver to retrieve the user later.
	 * @return	User	The user from your driver
	 */
	public function user_ok($user_data)
	{
		$user = $this->backdoor($user_data);

		if (method_exists($user, 'set_last_login'))
		{
			$user->set_last_login(time());
		}

		return $user;
	}

	/**
	 * Logs in silently.
	 *
	 * Tell Sesame to log this user in, but in a way that doesn't trigger any of the side-effects normally associated
	 * with logging in a user, such as setting login time. Use sparingly. Accepts the same information as user_ok does -
	 * user_ok calls this function and then does the post-login stuff.
	 *
	 * @param	mixed	$user_data	Data to pass to your driver to retrieve the user later.
	 * @return	User	The user from your driver
	 */
	public function backdoor($user_data)
	{
		\Session::set($this->_session_key(), $user_data);

		$driver = $this->_driver;
		$user = $driver::retrieve_user($user_data);

		$this->_user = $user;
		return $user;
	}

	/**
	 * Tries to log a user in.
	 *
	 * Defers to the driver this instance was created with. Does no checking that a user is already logged in.
	 *
	 * @return	User|null	The result of user_ok if your driver responds immediately. null otherwise.
	 */
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

	/**
	 * Log the user out, unsetting the session.
	 */
	public function logout()
	{
		$this->_user = null;
		\Session::delete($this->_session_key());
	}

	/**
	 * Pass on the creation of a user to make_user on your driver.
	 *
	 * This is just convenience so you don't have to know what specific driver class it is. Failure is up to your driver
	 * but is generally assumed to be an exception.
	 *
	 * @param	mixed	$user_data	The data that your driver class needs.
	 * @return	User	User thus created.
	 */
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
