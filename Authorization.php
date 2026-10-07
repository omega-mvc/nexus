<?php
declare(strict_types=1);
/**
 * Allows defining and checking user authentication via ACLs, authorization levels or a simple logged in/not logged in scheme
 *
 * @copyright  Copyright (c) 2007-2011 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 * @link       http://flourishlib.com/Authorization
 *
 * @version    1.0.0b6
 * @changes    1.0.0b6  Fixed ::checkIP() to not trigger a notice when `$_SERVER['REMOTE_ADDR']` is not set [wb, 2011-05-10]
 * @changes    1.0.0b5  Added ::getLoginPage() [wb, 2010-03-09]
 * @changes    1.0.0b4  Updated class to use new Session API [wb, 2009-10-23]
 * @changes    1.0.0b3  Updated class to use new Session API [wb, 2009-05-08]
 * @changes    1.0.0b2  Fixed a bug with using named IP ranges in ::checkIP() [wb, 2009-01-10]
 * @changes    1.0.0b   The initial implementation [wb, 2007-06-14]
 */
class Authorization
{
	/**
	 * The valid auth levels
	 *
	 * @var array
	 */
	static private ?array $levels = NULL;

	/**
	 * The login page
	 *
	 * @var string
	 */
	static private ?string $login_page = NULL;

	/**
	 * Named IP ranges
	 *
	 * @var array
	 */
	static private array $named_ip_ranges = array();


	/**
	 * Adds a named IP address or range, or array of addresses and/or ranges
	 *
	 * This method allows ::checkIP() to be called with a name instead of the
	 * actual IPs.
	 *
	 * @param  string $name       The name to give the IP addresses/ranges
	 * @param  mixed  $ip_ranges  This can be string (or array of strings) of the IPs or IP ranges to restrict to - please see ::checkIP() for format details
	 * @return void
	 */
	static public function addNamedIPRange(string $name, mixed $ip_ranges): void
	{
		self::$named_ip_ranges[$name] = $ip_ranges;
	}


	/**
	 * Checks to see if the logged in user meets the requirements of the ACL specified
	 *
	 * @param  string $resource    The resource we are checking permissions for
	 * @param  string $permission  The permission to require from the user
	 * @return boolean  If the user has the required permissions
	 */
	static public function checkACL(string $resource, string $permission): bool
	{
		if (self::getUserACLs() === NULL) {
			return FALSE;
		}

		$acls = self::getUserACLs();

		if (!isset($acls[$resource]) && !isset($acls['*'])) {
			return FALSE;
		}

		if (isset($acls[$resource])) {
			if (in_array($permission, $acls[$resource]) || in_array('*', $acls[$resource])) {
				return TRUE;
			}
		}

		if (isset($acls['*'])) {
			if (in_array($permission, $acls['*']) || in_array('*', $acls['*'])) {
				return TRUE;
			}
		}

		return FALSE;
	}


	/**
	 * Checks to see if the logged in user has the specified auth level
	 *
	 * @param  string $level  The level to check against the logged in user's level
	 * @return boolean  If the user has the required auth level
	 */
	static public function checkAuthLevel(string $level): bool
	{
		if (self::getUserAuthLevel()) {

			self::validateAuthLevel(self::getUserAuthLevel());
			self::validateAuthLevel($level);

			$user_number = self::$levels[self::getUserAuthLevel()];
			$required_number = self::$levels[$level];

			if ($user_number >= $required_number) {
				return TRUE;
			}
		}

		return FALSE;
	}


	/**
	 * Checks to see if the user is from the IPs or IP ranges specified
	 *
	 * The `$ip_ranges` parameter can be either a single string, or an array of
	 * strings, each of which should be in one of the following formats:
	 *
	 *  - A single IP address:
	 *   - 192.168.1.1
	 *   - 208.77.188.166
	 *  - A CIDR range
	 *   - 192.168.1.0/24
	 *   - 208.77.188.160/28
	 *  - An IP/subnet mask combination
	 *   - 192.168.1.0/255.255.255.0
	 *   - 208.77.188.160/255.255.255.240
	 *
	 * @param  mixed $ip_ranges  A string (or array of strings) of the IPs or IP ranges to restrict to - see method description for details
	 * @return boolean  If the user is coming from (one of) the IPs or ranges specified
	 */
	static public function checkIP(mixed $ip_ranges): bool
	{
		// Check to see if a named IP range was specified
		if (is_string($ip_ranges) && isset(self::$named_ip_ranges[$ip_ranges])) {
			$ip_ranges = self::$named_ip_ranges[$ip_ranges];
		}

		if (!isset($_SERVER['REMOTE_ADDR'])) {
			return FALSE;
		}

		// Get the remote IP and remove any IPv6 to IPv4 mapping
		$user_ip      = str_replace('::ffff:', '', $_SERVER['REMOTE_ADDR']);
		$user_ip_long = ip2long($user_ip);

		settype($ip_ranges, 'array');

		foreach ($ip_ranges as $ip_range) {

			if (strpos($ip_range, '/') === FALSE) {
				$ip_range .= '/32';
			}

			list($range_ip, $range_mask) = explode('/', $ip_range);

			if (strlen($range_mask) < 3) {
				$mask_long = pow(2, 32) - pow(2, 32 - $range_mask);
			} else {
				$mask_long = ip2long($range_mask);
			}

			$range_ip_long = ip2long($range_ip);

			if (($range_ip_long & $mask_long) != $range_ip_long) {
				$proper_range_ip = long2ip($range_ip_long & $mask_long);
				throw new ProgrammerException(
					'The range base IP address specified, %1$s, is invalid for the CIDR range or subnet mask provided (%2$s). The proper IP is %3$s.',
					$range_ip,
					'/' . $range_mask,
					$proper_range_ip
				);
			}

			if (($user_ip_long & $mask_long) == $range_ip_long) {
				return TRUE;
			}
		}

		return FALSE;
	}


	/**
	 * Checks to see if the user has an auth level or ACLs defined
	 *
	 * @return boolean  If the user is logged in
	 */
	static public function checkLoggedIn(): bool
	{
		if (Session::get(__CLASS__ . '::user_auth_level', NULL) !== NULL ||
			Session::get(__CLASS__ . '::user_acls', NULL) !== NULL ||
			Session::get(__CLASS__ . '::user_token', NULL) !== NULL) {
			return TRUE;
		}
		return FALSE;
	}


	/**
	 * Destroys the user's auth level and/or ACLs
	 *
	 * @return void
	 */
	static public function destroyUserInfo(): void
	{
		Session::delete(__CLASS__ . '::user_auth_level');
		Session::delete(__CLASS__ . '::user_acls');
		Session::delete(__CLASS__ . '::user_token');
		Session::delete(__CLASS__ . '::requested_url');
	}


	/**
	 * Returns the login page set via ::setLoginPage()
	 *
	 * @return string  The login page users are redirected to if they don't have the required authorization
	 */
	static public function getLoginPage(): string
	{
		return self::$login_page;
	}

	/**
	 * Returns the URL requested before the user was redirected to the login page
	 *
	 * @param  boolean $clear        If the requested url should be cleared from the session after it is retrieved
	 * @param  string  $default_url  The default URL to return if the user was not redirected
	 * @return string  The URL that was requested before they were redirected to the login page
	 */
	static public function getRequestedURL(bool $clear, ?string $default_url=NULL): string
	{
		$requested_url = Session::get(__CLASS__ . '::requested_url', $default_url);
		if ($clear) {
			Session::delete(__CLASS__ . '::requested_url');
		}
		return $requested_url;
	}


	/**
	 * Gets the ACLs for the logged in user
	 *
	 * @return array  The logged in user's ACLs
	 */
	static public function getUserACLs(): array
	{
		return Session::get(__CLASS__ . '::user_acls', NULL);
	}


	/**
	 * Gets the authorization level for the logged in user
	 *
	 * @return string  The logged in user's auth level
	 */
	static public function getUserAuthLevel(): string
	{
		return Session::get(__CLASS__ . '::user_auth_level', NULL);
	}


	/**
	 * Gets the value that was set as the user token, `NULL` if no token has been set
	 *
	 * @return mixed  The user token that had been set, `NULL` if none
	 */
	static public function getUserToken(): mixed
	{
		return Session::get(__CLASS__ . '::user_token', NULL);
	}


	/**
	 * Redirects the user to the login page
	 *
	 * @return void
	 */
	static private function redirect(): void
	{
		self::setRequestedURL(URL::getWithQueryString());
		URL::redirect(self::$login_page);
	}


	/**
	 * Redirect the user to the login page if they do not have the permissions required
	 *
	 * @param  string $resource    The resource we are checking permissions for
	 * @param  string $permission  The permission to require from the user
	 * @return void
	 */
	static public function requireACL(string $resource, string $permission): void
	{
		self::validateLoginPage();

		if (self::checkACL($resource, $permission)) {
			return;
		}

		self::redirect();
	}


	/**
	 * Redirect the user to the login page if they do not have the auth level required
	 *
	 * @param  string $level  The level to check against the logged in user's level
	 * @return void
	 */
	static public function requireAuthLevel(string $level): void
	{
		self::validateLoginPage();

		if (self::checkAuthLevel($level)) {
			return;
		}

		self::redirect();
	}


	/**
	 * Redirect the user to the login page if they do not have an auth level or ACLs
	 *
	 * @return void
	 */
	static public function requireLoggedIn(): void
	{
		self::validateLoginPage();

		if (self::checkLoggedIn()) {
			return;
		}

		self::redirect();
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
		self::$level           = NULL;
		self::$login_page      = NULL;
		self::$named_ip_ranges = array();
	}


	/**
	 * Sets the authorization levels to use for level checking
	 *
	 * @param  array $levels  An associative array of `(string) {level} => (int) {value}`, for each level
	 * @return void
	 */
	static public function setAuthLevels(array $levels): void
	{
		self::$levels = $levels;
	}


	/**
	 * Sets the login page to redirect users to
	 *
	 * @param  string $url  The URL of the login page
	 * @return void
	 */
	static public function setLoginPage(string $url): void
	{
		self::$login_page = $url;
	}


	/**
	 * Sets the restricted URL requested by the user
	 *
	 * @param  string  $url  The URL to save as the requested URL
	 * @return void
	 */
	static public function setRequestedURL(string $url): void
	{
		Session::set(__CLASS__ . '::requested_url', $url);
	}


	/**
	 * Sets the ACLs for the logged in user.
	 *
	 * Array should be formatted like:
	 *
	 * {{{
	 * array (
	 *     (string) {resource name} => array(
	 *         (mixed) {permission}, ...
	 *     ), ...
	 * )
	 * }}}
	 *
	 * The resource name or the permission may be the single character `'*'`
	 * which acts as a wildcard.
	 *
	 * @param  array $acls  The logged in user's ACLs - see method description for format
	 * @return void
	 */
	static public function setUserACLs(array $acls): void
	{
		Session::set(__CLASS__ . '::user_acls', $acls);
		Session::regenerateID();
	}


	/**
	 * Sets the authorization level for the logged in user
	 *
	 * @param  string $level  The logged in user's auth level
	 * @return void
	 */
	static public function setUserAuthLevel(string $level): void
	{
		self::validateAuthLevel($level);
		Session::set(__CLASS__ . '::user_auth_level', $level);
		Session::regenerateID();
	}


	/**
	 * Sets some piece of information to use to identify the current user
	 *
	 * @param  mixed $token  The user's token. This could be a user id, an email address, a user object, etc.
	 * @return void
	 */
	static public function setUserToken(mixed $token): void
	{
		Session::set(__CLASS__ . '::user_token', $token);
		Session::regenerateID();
	}


	/**
	 * Makes sure auth levels have been set, and that the specified auth level is valid
	 *
	 * @param  string $level  The level to validate
	 * @return void
	 */
	static private function validateAuthLevel(?string $level=NULL): void
	{
		if (self::$levels === NULL) {
			throw new ProgrammerException(
				'No authorization levels have been set, please call %s',
				__CLASS__ . '::setAuthLevels()'
			);
		}
		if ($level !== NULL && !isset(self::$levels[$level])) {
			throw new ProgrammerException(
				'The authorization level specified, %1$s, is invalid. Must be one of: %2$s.',
				$level,
				join(', ', array_keys(self::$levels))
			);
		}
	}


	/**
	 * Makes sure a login page has been defined
	 *
	 * @return void
	 */
	static private function validateLoginPage(): void
	{
		if (self::$login_page === NULL) {
			throw new ProgrammerException(
				'No login page has been set, please call %s',
				__CLASS__ . '::setLoginPage()'
			);
		}
	}


	/**
	 * Forces use as a static class
	 *
	 * @return Authorization
	 */
	private function __construct() { }
}



/**
 * Copyright (c) 2007-2011 Will Bond <will@flourishlib.com>
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
