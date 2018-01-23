<?php

namespace Sesame;

/**
 * Util functions for Sesame drivers, user models et cetera
 **/
class Util
{
	public static function _init()
	{
		\Config::load('sesame', true);
	}

	private static function pbkdf2( $p, $s, $c, $kl, $a = 'sha256' )
	{
		$hl = strlen(hash($a, null, true)); # Hash length
		$kb = ceil($kl / $hl);              # Key blocks to compute
		$dk = '';                           # Derived key

		# Create key
		for ( $block = 1; $block <= $kb; $block ++ )
		{
			# Initial hash for this block
			$ib = $b = hash_hmac($a, $s . pack('N', $block), $p, true);

			# Perform block iterations
			for ( $i = 1; $i < $c; $i ++ )
			{
				# XOR each iterate
				$ib ^= ($b = hash_hmac($a, $b, $p, true));
			}
			$dk .= $ib; # Append iterated block
		}

		# Return derived key of correct length
		return substr($dk, 0, $kl);
	}

	/**
	 * Use the value of sesame.hash_fn, possibly with sesame.salt, to hash the password.
	 */
	public static function hash($password)
	{
		$hash_fn = \Config::get('sesame.password.hash_fn');

		if (! $hash_fn)
		{
			throw new \ConfigException('sesame.password.hash_fn was not defined');
		}

		if ($hash_fn == 'plaintext')
		{
			return $password;
		}
		elseif ($hash_fn == 'default' || $hash_fn == 'pbkdf2')
		{
			$h = new \PHPSecLib\Crypt\Hash();

			if (! $salt = \Config::get('sesame.password.salt'))
			{
				throw new \ConfigException('sesame.password.salt must be defined for the ' . $hash_fn . ' hash function');
			}

			return self::pbkdf2($password, $salt, \Config::get('sesame.iterations', 10000), 32);
		}

		throw new \ConfigException('Unknown hash type ' . $hash_fn);
		/* TODO
		switch ($hash_fn)
		{
			case 'AES':
				return (new \PHPSecLib\Crypt_AES())->encrypt($password);
			case 'DES':
				return (new \PHPSecLib\Crypt_DES())->encrypt($password);
			case 'md5-96': case 'sha1-96': case 'md2':
            case 'md5': case 'sha1': case 'sha256':
            case 'sha384': case 'sha512':
				$h = new \PHPSecLib\Crypt_Hash();

				if (! $key = \Config::get('sesame.hash_key'))
				{
					throw new \ConfigException('sesame.hash_key must be defined for the ' . $hash_fn . ' hash function');
				}

				$h->set_key($key);
				return $h->hash($password);
			case 'pbkdf2':

		}
		*/
	}
}
