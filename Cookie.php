<?php
declare(strict_types=1);
/**
 * Provides a consistent cookie API, HTTPOnly compatibility with older PHP versions and default parameters
 *
 * @copyright  Copyright (c) 2008-2009 Will Bond, others
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @author     Nick Trew [nt]
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 * @link       http://flourishlib.com/Cookie
 *
 * @version    1.0.0b4
 * @changes    1.0.0b4  Added a `function_exists()` guard around `get_magic_quotes_gpc()` for PHP 8.0+ compatibility [wb, 2026-07-10]
 * @changes    1.0.0b3  Added the ::delete() method [nt+wb, 2009-09-30]
 * @changes    1.0.0b2  Updated for new Core API [wb, 2009-02-16]
 * @changes    1.0.0b   The initial implementation [wb, 2008-09-01]
 */
class Cookie
{
	/**
	 * The default domain to set for cookies
	 *
	 * @var string|null
	 */
	static private ?string $default_domain = null;

	/**
	 * The default expiration date to set for cookies
	 *
	 * @var string|integer|null
	 */
	static private int|string|null $default_expires = null;

	/**
	 * If cookies should default to being http-only
	 *
	 * @var boolean
	 */
	static private bool $default_httponly = false;

	/**
	 * The default path to set for cookies
	 *
	 * @var string|null
	 */
	static private ?string $default_path = null;

	/**
	 * If cookies should default to being secure-only
	 *
	 * @var boolean
	 */
	static private bool $default_secure = false;


	/**
	 * Deletes a cookie - uses default parameters set by the other set methods of this class
	 *
	 * @param  string       $name    The cookie name to delete
	 * @param  string|null  $path    The path of the cookie to delete
	 * @param  string|null  $domain  The domain of the cookie to delete
	 * @param  boolean|null $secure  If the cookie is a secure-only cookie
	 * @return void
	 */
	static public function delete(string $name, ?string $path = null, ?string $domain = null, ?bool $secure = null): void
	{
		self::set($name, '', time() - 86400, $path, $domain, $secure);
	}


	/**
	 * Gets a cookie value from `$_COOKIE`, while allowing a default value to be provided
	 *
	 * @param  string $name           The name of the cookie to retrieve
	 * @param  mixed  $default_value  If there is no cookie with the name provided, return this value instead
	 * @return mixed  The value
	 */
	static public function get(string $name, mixed $default_value = null): mixed
	{
		if (isset($_COOKIE[$name])) {
			$cookie_val = $_COOKIE[$name];
			if (is_array($cookie_val)) {
				$value = $cookie_val;
			} else {
				if (is_scalar($cookie_val)) {
					$value = UTF8::clean((string)$cookie_val);
				} else {
					$value = '';
				}
			}
			return $value;
		}
		return $default_value;
	}


	/**
	 * Resets the configuration of the class
	 *
	 * @internal
	 *
	 * @return void
	 */
	static public function reset(): void
	{
		self::$default_domain   = null;
		self::$default_expires  = null;
		self::$default_httponly = false;
		self::$default_path     = null;
		self::$default_secure   = false;
	}


	/**
	 * Sets a cookie to be sent back to the browser - uses default parameters set by the other set methods of this class
	 *
	 * The following methods allow for setting default parameters for this method:
	 *
	 *  - ::setDefaultExpires():  Sets the default for the `$expires` parameter
	 *  - ::setDefaultPath():     Sets the default for the `$path` parameter
	 *  - ::setDefaultDomain():   Sets the default for the `$domain` parameter
	 *  - ::setDefaultSecure():   Sets the default for the `$secure` parameter
	 *  - ::setDefaultHTTPOnly(): Sets the default for the `$httponly` parameter
	 *
	 * @param  string                $name      The name of the cookie to set
	 * @param  mixed                 $value     The value of the cookie to set
	 * @param  string|integer|null   $expires   A relative string to be interpreted by [http://php.net/strtotime strtotime()] or an integer unix timestamp
	 * @param  string|null           $path      The path this cookie applies to
	 * @param  string|null           $domain    The domain this cookie applies to
	 * @param  boolean|null          $secure    If the cookie should only be transmitted over a secure connection
	 * @param  boolean|null          $httponly  If the cookie should only be readable by HTTP connection, not javascript
	 * @return void
	 */
	static public function set(string $name, mixed $value, int|string|null $expires = null, ?string $path = null, ?string $domain = null, ?bool $secure = null, ?bool $httponly = null): void
	{
		if ($expires === null && self::$default_expires !== null) {
			$expires = self::$default_expires;
		}

		if ($path === null && self::$default_path !== null) {
			$path = self::$default_path;
		}

		if ($domain === null && self::$default_domain !== null) {
			$domain = self::$default_domain;
		}

		if ($secure === null) {
			$secure = self::$default_secure;
		}

		if ($httponly === null) {
			$httponly = self::$default_httponly;
		}

		if ($expires && !is_numeric($expires)) {
			$expires = strtotime($expires);
		}

		$expires_val = $expires;
		if (is_string($expires_val)) {
			if (is_numeric($expires_val)) {
				$expires_val = intval($expires_val);
			} else {
				$ts = strtotime($expires_val);
				$expires_val = $ts;
			}
		} elseif (is_int($expires_val)) {
			// keep as is
		} else {
			$expires_val = 0;
		}

		$value_str = is_scalar($value) ? (string)$value : '';

		if (strlen($value_str) && $httponly) {
			setcookie($name, $value_str, (int)$expires_val, $path ?? '', $domain ?? '', (bool)$secure, true);
			return;
		}

		setcookie($name, $value_str, (int)$expires_val, $path ?? '', $domain ?? '', (bool)$secure);
	}


	/**
	 * Sets the default domain to use for cookies
	 *
	 * This value will be used when the `$domain` parameter of the ::set()
	 * method is not specified or is set to `NULL`.
	 *
	 * @param  string|null $domain  The default domain to use for cookies
	 * @return void
	 */
	static public function setDefaultDomain(?string $domain): void
	{
		self::$default_domain = $domain;
	}


	/**
	 * Sets the default expiration date to use for cookies
	 *
	 * This value will be used when the `$expires` parameter of the ::set()
	 * method is not specified or is set to `NULL`.
	 *
	 * @param  string|integer|null $expires  The default expiration date to use for cookies
	 * @return void
	 */
	static public function setDefaultExpires(int|string|null $expires): void
	{
		self::$default_expires = $expires;
	}


	/**
	 * Sets the default httponly flag to use for cookies
	 *
	 * This value will be used when the `$httponly` parameter of the ::set()
	 * method is not specified or is set to `NULL`.
	 *
	 * @param  boolean $httponly  The default httponly flag to use for cookies
	 * @return void
	 */
	static public function setDefaultHTTPOnly(bool $httponly): void
	{
		self::$default_httponly = $httponly;
	}


	/**
	 * Sets the default path to use for cookies
	 *
	 * This value will be used when the `$path` parameter of the ::set()
	 * method is not specified or is set to `NULL`.
	 *
	 * @param  string|null $path  The default path to use for cookies
	 * @return void
	 */
	static public function setDefaultPath(?string $path): void
	{
		self::$default_path = $path;
	}


	/**
	 * Sets the default secure flag to use for cookies
	 *
	 * This value will be used when the `$secure` parameter of the ::set()
	 * method is not specified or is set to `NULL`.
	 *
	 * @param  boolean $secure  The default secure flag to use for cookies
	 * @return void
	 */
	static public function setDefaultSecure(bool $secure): void
	{
		self::$default_secure = $secure;
	}


	/**
	 * Forces use as a static class
	 *
	 * @return Cookie
	 */
	private function __construct() { }
}



/**
 * Copyright (c) 2008-2009 Will Bond <will@flourishlib.com>, others
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
