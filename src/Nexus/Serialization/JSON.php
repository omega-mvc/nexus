<?php

declare(strict_types=1);

/**
 * Provides encoding and decoding for JSON
 *
 * This class is a thin wrapper around the
 * [http://php.net/json json extension], which is always available as of
 * PHP 8.0 (the extension has been core since PHP 5.2).
 *
 * @copyright  Copyright (c) 2008-2010 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 * @link       http://flourishlib.com/JSON
 *
 * @version    1.0.0b6
 * @changes    1.0.0b6  Removed `e` flag from preg_replace() calls [wb, 2010-06-08]
 * @changes    1.0.0b5  Added the ::output() method [wb, 2010-03-15]
 * @changes    1.0.0b4  Fixed a bug with ::decode() where JSON objects could lose all but the first key: value pair [wb, 2009-05-06]
 * @changes    1.0.0b3  Updated the class to be consistent with PHP 5.2.9+ for encoding and decoding invalid data [wb, 2009-05-04]
 * @changes    1.0.0b2  Changed @ error suppression operator to `error_reporting()` calls [wb, 2009-01-26]
 * @changes    1.0.0b   The initial implementation [wb, 2008-07-12]
 */
class JSON
{
	/**
	 * Decodes a JSON string into native PHP data types
	 *
	 * If the string is not a valid JSON string, `NULL` will be returned.
	 *
	 * @param  string  $json   The JSON string to decode
	 * @param  boolean $assoc  If this is TRUE, JSON objects will be represented as an assocative array instead of a `stdClass` object
	 * @return mixed  A PHP equivalent of the JSON string
	 */
	public static function decode(string $json, bool $assoc = false): mixed
	{
		$json = trim($json);

		if ($json === '') {
			return null;
		}

		return json_decode($json, $assoc);
	}


	/**
	 * Encodes a PHP value into a JSON string
	 *
	 * @param  mixed  $value   The PHP value to encode
	 * @return string  The JSON string that is equivalent to the PHP value
	 */
	public static function encode(mixed $value): string
	{
		if (is_resource($value)) {
			return 'null';
		}

		$json = json_encode($value);

		if ($json === false) {
			throw new UnexpectedException('Unable to encode value as JSON: ' . json_last_error_msg());
		}

		return $json;
	}


	/**
	 * Sets the proper `Content-Type` header and outputs the value, encoded as JSON
	 *
	 * @param  mixed $value  The PHP value to output as JSON
	 * @return void
	 */
	public static function output(mixed $value): void
	{
		self::sendHeader();
		echo self::encode($value);
	}


	/**
	 * Sets the proper `Content-Type` header for UTF-8 encoded JSON
	 *
	 * @return void
	 */
	public static function sendHeader(): void
	{
		header('Content-Type: application/json; charset=utf-8');
	}


	/**
	 * Forces use as a static class
	 *
	 * @return JSON
	 */
	private function __construct()
    {
    }
}



/**
 * Copyright (c) 2008-2010 Will Bond <will@flourishlib.com>
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
