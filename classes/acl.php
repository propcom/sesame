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

	protected static $_fallthrough = false;

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

		foreach ($uri->get_segments() ?: [] as $segment)
		{
			if (! isset($cur_rule[$segment]))
			{
				break;
			}
			else
			{
				$rules[$segment] = \Arr::get($cur_rule[$segment], '__rules__', []);
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
					if ($rule === true)
					{
						return true;
					}

					if ($rule === false)
					{
						return false;
					}

					if ($rule instanceof \Closure and $rule($user))
					{
						return true;
					}

					return static::_check_user_permission($user, $rule);
				}

				return false;
			}
			if (isset($rules['deny_unless']))
			{
				// Deny unless all
				$ok = true;

				foreach ($rules['deny_unless'] as $rule)
				{
					if ($rule === true)
					{ /* no op but there's an if later. */ }

					if ($rule === false)
					{
						return false;
					}

					if ($rule instanceof \Closure)
					{
						$ok = $ok && $rule($user);
					}
					else
					{
						$ok = $ok && static::_check_user_permission($user, $rule);
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
					if ($rule === true)
					{ /* no op but there's an if later. */ }

					if ($rule === false)
					{
						// Allow unless all of these are true -> allow on exactly false
						return true;
					}
					if ($rule instanceof \Closure)
					{
						$deny = $deny && $rule($user);
					}
					else
					{
						$deny = $deny && static::_check_user_permission($user, $rule);
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
					if ($rule === true)
					{
						return false;
					}

					if ($rule === false)
					{
						return true;
					}

					if ($rule instanceof \Closure and $rule($user))
					{
						return false;
					}

					return static::_check_user_permission($user, $rule);
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
	public static function allow_if($url, $rules, $fallthrough=null)
	{
		static::_set($url, $rules, 'allow_if', $fallthrough);
	}

	/**
	 * Deny access to $url if any of $rules is true
	 *
	 * @param	string	$url	Absolute path to control
	 * @param	array	$rules	Array of rules to test.
	 */
	public static function deny_if($url, $rules, $fallthrough=null)
	{
		static::_set($url, $rules, 'deny_if', $fallthrough);
	}

	/**
	 * Allow access to $url iff all of $rules are true
	 *
	 * @param	string	$url	Absolute path to control
	 * @param	array	$rules	Array of rules to test.
	 */
	public static function allow_unless($url, $rules, $fallthrough=null)
	{
		static::_set($url, $rules, 'allow_unless', $fallthrough);
	}

	/**
	 * Deny access to $url iff all of $rules are true
	 *
	 * @param	string	$url	Absolute path to control
	 * @param	array	$rules	Array of rules to test.
	 */
	public static function deny_unless($url, $rules, $fallthrough=null)
	{
		static::_set($url, $rules, 'deny_unless', $fallthrough);
	}

	/**
	 * Fallthrough defaults to false; change it here.
	 *
	 * @param	bool	$fallthrough	Fallthrough yay or fallthrough nay
	 */
	public static function fallthrough($fallthrough)
	{
		static::$_fallthrough = (bool) $fallthrough;
	}

	protected static function _set($url, $rules, $rule, $fallthrough=null)
	{
		$url[0] == '/' or $url = "/$url";
		$uri = new \Uri($url);
		$segments = $uri->get_segments() ?: [];

		// Check this before defaulting fallthrough - shouldn't punish them for not specifying
		if (! $segments && $fallthrough)
		{
			throw new ACLRuleException("Fallthrough on root path doesn't make sense");
		}

		is_null($fallthrough) and $fallthrough = static::$_fallthrough;

		if ($fallthrough && $segments)
		{
			$parent = $segments;
			array_pop($parent);
			$parent_path = '/' . implode('/', $parent);
			array_push($rules, function($user) use ($parent_path) {
				return \ACL::check_user_access($user, $parent_path);
			});
		}

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

	protected static function _check_user_permission($user, $permission)
	{
		if ($permission[0] == '~')
		{
			return ! $user->has_permission(substr($permission,1));
		}
		else
		{
			return $user->has_permission($permission);
		}
	}
}

class ACLRuleException extends \Exception {}
