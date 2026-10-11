<?php

declare(strict_types=1);

/**
 * Provides session-based messaging for page-to-page communication
 *
 * @copyright  Copyright (c) 2007-2016 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @author     Jeff Turcotte [jt] <jeff@imarc.net>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 * @link       http://flourishlib.com/Messaging
 *
 * @version    1.0.0b9
 * @changes    1.0.0b9  Messages print in a p tag inside of a div if plain content [wb, 2016-10-29]
 * @changes    1.0.0b8  [BREAK] Now messages always print as divs. Also added class option [jt, 2013-04-09]
 * @changes    1.0.0b7  Fixed a small PHPDoc error [wb, 2010-03-15]
 * @changes    1.0.0b6  Updated class to use new Session API [wb, 2009-10-23]
 * @changes    1.0.0b5  Made the `$recipient` parameter optional for all methods [wb, 2009-07-08]
 * @changes    1.0.0b4  Added support for `'*'` and arrays of names to ::check() [wb, 2009-06-02]
 * @changes    1.0.0b3  Updated class to use new Session API [wb, 2009-05-08]
 * @changes    1.0.0b2  Changed ::show() to accept more than one message name, or * for all messages [wb, 2009-01-12]
 * @changes    1.0.0b   The initial implementation [wb, 2008-03-05]
 */
class Messaging
{
	/**
	 * The class to apply to all messages along with the specified class
	 *
	 * @var string
	 */
	protected static string $class = '';


	/**
	 * Checks to see if a message exists of the name specified for the recipient specified
	 *
	 * @param  string $name       The name or array of names of the message(s) to check for, or `'*'` to check for any
	 * @param  string $recipient  The intended recipient
	 * @return boolean  If a message of the name and recipient specified exists
	 */
	public static function check(string $name, ?string $recipient = null): bool
	{
		if ($recipient === null) {
			$recipient = '{default}';
		}

		// Check all messages if * is specified
		if ($name == '*') {
			Session::open();
			$prefix = __CLASS__ . '::' . $recipient . '::';
			$keys   = array_keys($_SESSION);
			foreach ($keys as $key) {
				if (!is_string($key)) {
					continue;
				}
				if (strpos($key, $prefix) === 0) {
					return true;
				}
			}
			return false;
		}

		return Session::get(__CLASS__ . '::' . $recipient . '::' . $name, null) !== null;
	}


	/**
	 * Creates a message that is stored in the session and retrieved by another page
	 *
	 * @param  string $name       A name for the message
	 * @param  string $recipient  The intended recipient - this may be ommitted
	 * @param  string $message    The message to send
	 * @param  string  $name
	 * @param  string  $message
	 * @return void
	 */
	public static function create(string $name, string $recipient, ?string $message = null): void
	{
		// This allows for the $recipient parameter to be optional
		if ($message === null) {
			$message   = $recipient;
			$recipient = '{default}';
		}

		Session::set(__CLASS__ . '::' . $recipient . '::' . $name, $message);
	}


	/**
	 * Resets the data of the class
	 *
	 * @internal
	 *
	 * @return void
	 */
	public static function reset(): void
	{
		Session::clear(__CLASS__ . '::');
	}


	/**
	 * Retrieves and removes a message from the session
	 *
	 * @param  string $name       The name of the message to retrieve
	 * @param  string $recipient  The intended recipient
	 * @return string  The message contents
	 */
	public static function retrieve(string $name, ?string $recipient = null): string
	{
		if ($recipient === null) {
			$recipient = '{default}';
		}

		$key     = __CLASS__ . '::' . $recipient . '::' . $name;
		$message = Session::get($key, null);
		Session::delete($key);
		return is_string($message) ? $message : '';
	}


	/**
	 * Configures a class to be present in all messages when no custom css class is given
	 *
	 * @param  string $class  A class for all messages
	 * @return void
	 */
	public static function setClass(string $class): void
	{
		self::$class = $class;
	}


	/**
	 * Retrieves a message, removes it from the session and prints it - will not print if no content
	 *
	 * The message will be printed in a `p` tag if it does not contain
	 * any block level HTML, otherwise it will be printed in a `div` tag.
	 *
	 * @param  mixed  $name       The name or array of names of the message(s) to show, or `'*'` to show all
	 * @param  string $recipient  The intended recipient
	 * @param  string $css_class  Overrides using the `$name` as the CSS class when displaying the message - only used if a single `$name` is specified
	 * @return boolean  If one or more messages was shown
	 */
	public static function show(mixed $name, ?string $recipient = null, ?string $css_class = null): bool
	{
		if ($recipient === null) {
			$recipient = '{default}';
		}

		// Find all messages if * is specified
		if (is_string($name) && $name == '*') {
			Session::open();
			$prefix = __CLASS__ . '::' . $recipient . '::';
			$keys   = array_keys($_SESSION);
			$name   = array();
			foreach ($keys as $key) {
				if (!is_string($key)) {
					continue;
				}
				if (strpos($key, $prefix) === 0) {
					$name[] = substr($key, strlen($prefix));
				}
			}
		}

		// Handle showing multiple messages
		if (is_array($name)) {
			$shown = false;
			$names = $name;

			foreach ($names as $name) {
				if (!is_string($name)) {
					continue;
				}
				$class = trim(self::$class . ' ' . $name);
				$class = ($css_class === null) ? $class : $css_class;

				$content = self::retrieve($name, $recipient);
				if ($content !== '' && !HTML::containsBlockLevelHTML($content)) {
					$content = '<p>' . $content . '</p>';
				}
				$shown = HTML::show($content, $class, true) || $shown;
			}
			return $shown;
		}

		// Handle a single message
		if (!is_string($name)) {
			if (!is_scalar($name)) {
				return false;
			}
			$name = (string) $name;
		}
		$class = self::$class . ' ' . $name;
		$class = ($css_class === null) ? $class : $css_class;
		$content = self::retrieve($name, $recipient);
		if ($content !== '' && !HTML::containsBlockLevelHTML($content)) {
			$content = '<p>' . $content . '</p>';
		}
		return HTML::show($content, $class, true);
	}


	/**
	 * Forces use as a static class
	 *
	 * @return Messaging
	 */
	private function __construct()
    {
    }
}



/**
 * Copyright (c) 2007-2016 Will Bond <will@flourishlib.com>
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
