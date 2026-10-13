<?php

declare(strict_types=1);

/**
 * Provides functionality to retrieve and manipulate URL information
 *
 * This class uses `$_SERVER['REQUEST_URI']` for all operations, meaning that
 * the original URL entered by the user will be used, or that any rewrites
 * will **not** be reflected by this class.
 *
 * @copyright  Copyright (c) 2007-2022 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 * @link       http://flourishlib.com/URL
 *
 * @version    1.0.0b14
 * @changes    1.0.0b14  Added `isset()` guards around `$_SERVER` access and a `function_exists()` guard around `get_magic_quotes_gpc()` for PHP 8.0+ compatibility, fixed NULL being passed to `strlen()` in ::makeFriendly() [wb, 2026-07-10]
 * @changes    1.0.0b12  ::getDomain() will handle https:// proxies to http:// when the proxied server runs on port 80 [wb, 2022-02-16]
 * @changes    1.0.0b11  ::getDomain() will look for X_FORWARDED_PROTO header to handle servers with a TLS terminator in front [wb, 2016-11-01]
 * @changes    1.0.0b10  Fixed some method signatures [wb, 2011-08-24]
 * @changes    1.0.0b9   Fixed ::redirect() to handle no parameters properly [wb, 2011-06-13]
 * @changes    1.0.0b8   Added the `$delimiter` parameter to ::makeFriendly() [wb, 2011-06-03]
 * @changes    1.0.0b7   Fixed ::redirect() to be able to handle unqualified and relative paths [wb, 2011-03-02]
 * @changes    1.0.0b6   Added the `$max_length` parameter to ::makeFriendly() [wb, 2010-09-19]
 * @changes    1.0.0b5   Updated ::redirect() to not require a URL, using the current URL as the default [wb, 2009-07-29]
 * @changes    1.0.0b4   ::getDomain() now includes the port number if non-standard [wb, 2009-05-02]
 * @changes    1.0.0b3   ::makeFriendly() now changes _-_ to - and multiple _ to a single _ [wb, 2009-03-24]
 * @changes    1.0.0b2   Fixed ::makeFriendly() so that _ doesn't appear at the beginning of URLs [wb, 2009-03-22]
 * @changes    1.0.0b    The initial implementation [wb, 2007-06-14]
 */
class URL
{
	/**
	 * Returns the requested URL, does no include the domain name or query string
	 *
	 * This will return the original URL requested by the user - ignores all
	 * rewrites.
	 *
	 * @return string  The requested URL without the query string
	 */
	public static function get(): string
	{
		$uri = $_SERVER['REQUEST_URI'] ?? '';
		if (is_string($uri)) {
			$result = preg_replace('#\?.*$#D', '', $uri);
			return is_string($result) ? $result : $uri;
		}
		return '';
	}


	/**
	 * Returns the current domain name, with protcol prefix. Port will be included if not 80 for HTTP or 443 for HTTPS.
	 *
	 * @return string  The current domain name, prefixed by `http://` or `https://`
	 */
	public static function getDomain(): string
	{
		$headers = getallheaders();
		if (!empty($headers) && isset($headers['X_FORWARDED_PROTO']) && $headers['X_FORWARDED_PROTO'] == 'https') {
			$secure = true;
			$port = 443;
		} else {
			$secure = isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == '1');
			$port = (int) self::stringify($_SERVER['SERVER_PORT'] ?? '');
			if ($secure && $port == 80 && isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] == 'http') {
				$port = 443;
			}
		}
		$serverName = self::stringify($_SERVER['SERVER_NAME'] ?? '');
		if ($secure) {
			$portPart = '';
			if ($port && $port != 443) {
				$portPart = ':' . $port;
			}
			return 'https://' . $serverName . $portPart;
		} else {
			$portPart = '';
			if ($port && $port != 80) {
				$portPart = ':' . $port;
			}
			return 'http://' . $serverName . $portPart;
		}
	}


	/**
	 * Returns the current query string, does not include parameters added by rewrites
	 *
	 * @return string  The query string
	 */
	public static function getQueryString(): string
	{
		$uri = self::stringify($_SERVER['REQUEST_URI'] ?? '');
		$result = preg_replace('#^[^?]*\??#', '', $uri);
		return is_string($result) ? $result : '';
	}


	/**
	 * Returns the current URL including query string, but without domain name - does not include query string parameters from rewrites
	 *
	 * @return string  The URL with query string
	 */
	public static function getWithQueryString(): string
	{
		return self::stringify($_SERVER['REQUEST_URI'] ?? '');
	}


	/**
	 * Changes a string into a URL-friendly string
	 *
	 * @param  string   $string      The string to convert
	 * @param  integer  $max_length  The maximum length of the friendly URL
	 * @param  string   $delimiter   The delimiter to use between words, defaults to `_`
	 * @param  string    $string
	 * @param  string    $delimiter
	 * @return string  The URL-friendly version of the string
	 */
	public static function makeFriendly(string $string, ?int $max_length = null, ?string $delimiter = null): string
	{
		$string = HTML::decode(UTF8::ascii($string));
		$string = strtolower(trim($string));
		$string = str_replace("'", '', $string);

		if ($delimiter === null || $delimiter === '') {
			$delimiter = '_';
		}

		$delimiter_replacement = strtr($delimiter, array('\\' => '\\\\', '$' => '\\$'));
		$delimiter_regex       = preg_quote($delimiter, '#');

		$string = preg_replace('#[^a-z0-9\-_]+#', $delimiter_replacement, $string) ?? '';
		$string = preg_replace('#' . $delimiter_regex . '{2,}#', $delimiter_replacement, $string) ?? $string;
		$string = preg_replace('#_-_#', '-', $string) ?? $string;
		$string = preg_replace('#(^' . $delimiter_regex . '+|' . $delimiter_regex . '+$)#D', '', $string) ?? $string;

		$length = strlen($string);
		if ($max_length && $length > $max_length) {
			$last_pos = strrpos($string, $delimiter, ($length - $max_length - 1) * -1);
			if ($last_pos === false) {
				$last_pos = $max_length;
			}
			if ($last_pos < ceil($max_length / 2)) {
				$last_pos = $max_length;
			}
			$string = substr($string, 0, $last_pos);
		}

		return $string;
	}


	/**
	 * Redirects to the URL specified, without requiring a full-qualified URL
	 *
	 *  - If the URL starts with `/`, it is treated as an absolute path on the current site
	 *  - If the URL starts with `http://` or `https://`, it is treated as a fully-qualified URL
	 *  - If the URL starts with anything else, including a `?`, it is appended to the current URL
	 *  - If the URL is ommitted, it is treated as the current URL
	 *
	 * @param  string $url  The url to redirect to
	 * @return void
	 */
	public static function redirect(?string $url = null): void
	{
		$url = $url ?? '';

		if (strpos($url, '/') === 0) {
			$url = self::getDomain() . $url;
		} elseif (!preg_match('#^https?://#i', $url)) {
			$prefix = self::getDomain() . self::get();

			if (strlen($url)) {
				// All URLs that have more than the query string need to
				// be appended to the current directory name
				if ($url[0] != '?') {
					$prefix = preg_replace('#(?<=/)[^/]+$#D', '', $prefix) ?? $prefix;
				}

				// Clean up ./ relative URLS
				if (substr($url, 0, 2) == './') {
					$url = substr($url, 2);
				}

				// Resolve ../ relative paths as far as possible
				while (substr($url, 0, 3) == '../') {
					if ($prefix == self::getDomain() . '/') {
                        break;
                    }
					$prefix = preg_replace('#(?<=/)[^/]+/?$#D', '', $prefix) ?? $prefix;
					$url    = substr($url, 3);
				}
			}

			$url = $prefix . $url;
		}

		// Strip the ? if there are no query string parameters
		if (substr($url, -1) == '?') {
			$url = substr($url, 0, -1);
		}

		header('Location: ' . $url);
		exit($url);
	}


	/**
	 * Removes one or more parameters from the query string
	 *
	 * This method uses the query string from the original URL and will not
	 * contain any parameters that are from rewrites.
	 *
	 * @param  string $parameter  A parameter to remove from the query string
	 * @return string  The query string with the parameter(s) specified removed, first character is `?`
	 */
	public static function removeFromQueryString(string $parameter): string
	{
		$parameters = func_get_args();

		parse_str(self::getQueryString(), $qs_array);

		foreach ($parameters as $parameter) {
			if (is_int($parameter) || is_string($parameter)) {
				unset($qs_array[$parameter]);
			}
		}

		return '?' . http_build_query($qs_array, '', '&');
	}


	/**
	 * Replaces a value in the query string
	 *
	 * This method uses the query string from the original URL and will not
	 * contain any parameters that are from rewrites.
	 *
	 * @param  array<int, mixed>|null|string  $parameter  The query string parameter
	 * @param  array<int, mixed>|bool|float|int|null|string  $value  The value to set the parameter to
	 * @return string  The full query string with the parameter replaced, first char is `?`
	 */
	public static function replaceInQueryString(mixed $parameter, mixed $value): string
	{
		parse_str(self::getQueryString(), $qs_array);

		if (!is_array($parameter)) {
			$parameter = ($parameter === null) ? array() : array($parameter);
		}
		if (!is_array($value)) {
			$value = ($value === null) ? array() : array($value);
		}

		if (sizeof($parameter) != sizeof($value)) {
			throw new ProgrammerException(
				"There are a different number of parameters and values.\nParameters:\n%1\$s\nValues\n%2\$s",
				$parameter,
				$value
			);
		}

		for ($i = 0; $i < sizeof($parameter); $i++) {
			$key_value = $parameter[$i];
			if (is_bool($key_value) || is_float($key_value)) {
				$key_value = (int) $key_value;
			} elseif ($key_value === null) {
				$key_value = '';
			}
			if (is_int($key_value) || is_string($key_value)) {
				$qs_array[$key_value] = $value[$i];
			}
		}

		return '?' . http_build_query($qs_array, '', '&');
	}


	/**
	 * Converts a mixed `$_SERVER` value to a string for use in URL parts
	 *
	 * @param  mixed $value  The value to stringify
	 * @return string  The stringified value, or an empty string when not convertable
	 */
	private static function stringify(mixed $value): string
	{
		if (is_string($value)) {
			return $value;
		}
		if (is_scalar($value) || $value instanceof Stringable) {
			return (string) $value;
		}
		return '';
	}


	/**
	 * Forces use as a static class
	 *
	 * @return URL
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
