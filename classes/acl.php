<?php

namespace Sesame;

/**
 * Access control list
 *
 * This class contains a quantity of static methods to set up and later interrogate access control rules.
 *
 * See README.md for info.
 */
class ACL
{
	protected static $_rules;

	/**
	 * Check the currently logged-in user's access, using the default driver to find the user.
	 *
	 * @param	string	$url	Path to test
	 * @return	boolean	True if user has access
	 */
	public static function check_access($url)
	{
		return static::check_user_access(\Sesame::instance()->user(), $url);
	}

	/**
	 * Check the user's access to $url based on the current set of rules.
	 *
	 * @param	Object	$user	Any object with a has_permission method (if you want to use that behaviour).
	 * @param	string	$url	Path to test.
	 * @return	boolean	True if user has access.
	 */
	public static function check_user_access($user, $url)
	{
		$url or $url = '/';
		$url[0] == '/' or $url = '/' . $url;

		$uri = new \Uri($url);

		// These rules always apply.
		$rules = [ '/' => static::$_rules['__rules__'] ];
		$cur_rule = static::$_rules;

		foreach ($uri->segments() ?: [] as $segment)
		{
			if (! isset($cur_rule[$segment]))
			{
				break;
			}
			else
			{
				$rules[$segment] = $cur_rule[$segment]['__rules__'];
				$cur_rule = $cur_rule[$segment];
			}
		}

		// Now we have an associative array whose order is the same as the segments of the URI.
		// Go backwards from most specific to least specific and match the rules.
		// Rule creation should catch contradictions.
		foreach (array_reverse($rules) as $segment => $rules)
		{
			// If rules are closures, run them with the user. Otherwise assume they are permissions.
			if (isset($rules['allow_if']))
			{
				// Allow if any
				foreach ($rules['allow_if'] as $rule)
				{
					if ($rule instanceof \Closure and $rule($user))
					{
						return true;
					}

					if ($user->has_permission($rule))
					{
						return true;
					}
				}

				return false;
			}
			if (isset($rules['deny_unless']))
			{
				// Deny unless all
				$ok = true;

				foreach ($rules['deny_unless'] as $rule)
				{
					if ($rule instanceof \Closure)
					{
						$ok = $ok && $rule($user);
					}
					else
					{
						$ok = $ok && $user->has_permission($rule);
					}
					if (! $ok)
					{
						break;
					}
				}

				return $ok;
			}

			// I don't envisage deny_if and allow_unless being used as much.
			if (isset($rules['allow_unless']))
			{
				// Allow unless all
				$deny = true;
				foreach ($rules['allow_unless'] as $rule)
				{
					if ($rule instanceof \Closure)
					{
						$deny = $deny && $rule($user);
					}
					else
					{
						$deny = $deny && $user->has_permission($rule);
					}

					if (! $deny)
					{
						break;
					}
				}

				return ! $deny;
			}
			if (isset($rules['deny_if']))
			{
				// Deny if any
				foreach ($rules['deny_if'] as $rule)
				{
					if ($rule instanceof \Closure and $rule($user))
					{
						return false;
					}

					if ($user->has_permission($rule))
					{
						return false;
					}
				}

				return true;
			}
		}

		// Nothing matched - not even root!
		return false;
	}

	/**
	 * Allow access to $url if any of $rules is true
	 *
	 * @param	string	$url	Absolute path to control
	 * @param	array	$rules	Array of rules to test.
	 */
	public static function allow_if($url, $rules)
	{
		static::_set($url, $rules, 'allow_if');
	}

	/**
	 * Deny access to $url if any of $rules is true
	 *
	 * @param	string	$url	Absolute path to control
	 * @param	array	$rules	Array of rules to test.
	 */
	public static function deny_if($url, $rules)
	{
		static::_set($url, $rules, 'deny_if');
	}

	/**
	 * Allow access to $url iff all of $rules are true
	 *
	 * @param	string	$url	Absolute path to control
	 * @param	array	$rules	Array of rules to test.
	 */
	public static function allow_unless($url, $rules)
	{
		static::_set($url, $rules, 'allow_unless');
	}

	/**
	 * Deny access to $url iff all of $rules are true
	 *
	 * @param	string	$url	Absolute path to control
	 * @param	array	$rules	Array of rules to test.
	 */
	public static function deny_unless($url, $rules)
	{
		static::_set($url, $rules, 'deny_unless');
	}

	protected static function _set($url, $rules, $rule)
	{
		$uri = new \Uri($url);
		$segments = $uri->segments() ?: [];

		static::$_rules = static::$_rules ?: [];

		$segments[] = '__rules__';
		$key = implode('.', $segments);

		$existing = \Arr::get(static::$_rules, $key) ?: [];

		if ($existing)
		{
			if (isset($existing[$rule]))
			{
				$existing[$rule] = array_merge($existing[$rule], $rules);
			}
			else
			{
				throw new ACLRuleException("Cannot set $rule rules on $url; other rules exist");
			}
		}
		else
		{
			$existing[$rule] = $rules;
		}

		\Arr::set(static::$_rules, $key, $existing);
	}
}
