<?php

declare(strict_types=1);

/**
 * Provides a single, simplified interface for [http://php.net/outcontrol output buffering] to prevent nested buffering
 * issues and provide a more logical API
 *
 * @copyright  Copyright (c) 2008-2010 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 *
 * @version    1.0.0b3
 * @changes    1.0.0b3  Added a check to ensure the zlib extension is installd when doing gzipped buffering [wb,
 * 2010-05-20]
 * @changes    1.0.0b2  Added the `$gzip` parameter to ::start() [wb, 2010-05-19]
 * @changes    1.0.0b   The initial implementation [wb, 2008-03-16]
 */
class Buffer
{
	/**
	 * If output capturing is currently active
	 *
	 * @var boolean
	 */
	private static bool $capturing = false;

	/**
	 * If output buffering has been started
	 *
	 * @var boolean
	 */
	private static bool $started = false;


	/**
	 * Erases the output buffer
	 *
	 * @return void
	 */
	public static function erase(): void
	{
		if (!self::$started) {
			throw new ProgrammerException(
				'The output buffer can not be erased since output buffering has not been started'
			);
		}
		if (self::$capturing) {
			throw new ProgrammerException(
				'Output capturing is currently active and it must be stopped before the buffer can be erased'
			);
		}
		ob_clean();
	}


	/**
	 * Returns the contents of output buffer
	 *
	 * @return string  The contents of the output buffer
	 */
	public static function get(): string
	{
		if (!self::$started) {
			throw new ProgrammerException(
				'The output buffer can not be retrieved because it has not been started'
			);
		}
		if (self::$capturing) {
			throw new ProgrammerException(
				'Output capturing is currently active and it must be stopped before the buffer can be retrieved'
			);
		}
		$contents = ob_get_contents();
		return $contents === false ? '' : $contents;
	}


	/**
	 * Checks if buffering has been started
	 *
	 * @return boolean  If buffering has been started
	 */
	public static function isStarted(): bool
	{
		return self::$started;
	}


	/**
	 * Replaces a value in the output buffer
	 *
	 * @param  string $find     The string to find
	 * @param  string $replace  The string to replace
	 * @return void
	 */
	public static function replace(string $find, string $replace): void
	{
		if (!self::$started) {
			throw new ProgrammerException(
				'A replacement can not be made since output buffering has not been started'
			);
		}
		if (self::$capturing) {
			throw new ProgrammerException(
				'Output capturing is currently active and it must be stopped before a replacement can be made'
			);
		}

		// ob_get_clean() actually turns off output buffering, so we do it the long way
		$contents = ob_get_contents();
		ob_clean();
		if ($contents === false) {
			$contents = '';
		}

		echo str_replace($find, $replace, $contents);
	}


	/**
	 * Resets the configuration and buffer of the class
	 *
	 * @internal
	 *
	 * @return void
	 */
	public static function reset(): void
	{
		if (self::$capturing) {
			self::stopCapture();
		}
		if (self::$started) {
			self::erase();
			self::stop();
		}
	}


	/**
	 * Starts output buffering
	 *
	 * @param  boolean $gzip  If the buffered output should be gzipped using [http://php.net/ob_gzhandler
	 * `ob_gzhandler()`]
	 * @return void
	 */
	public static function start(bool $gzip = false): void
	{
		if (self::$started) {
			throw new ProgrammerException(
				'Output buffering has already been started'
			);
		}
		if (self::$capturing) {
			throw new ProgrammerException(
				'Output capturing is currently active and it must be stopped before the buffering can be started'
			);
		}
		if ($gzip && !extension_loaded('zlib')) {
			throw new EnvironmentException(
				'The PHP %s extension is required for gzipped buffering, however is does not appear to be loaded',
				'zlib'
			);
		}
		ob_start($gzip ? 'ob_gzhandler' : null);
		self::$started = true;
	}


	/**
	 * Starts capturing output, should be used with ::stopCapture() to grab output from code that does not offer an
	 * option of returning a value instead of outputting it
	 *
	 * @return void
	 */
	public static function startCapture(): void
	{
		if (self::$capturing) {
			throw new ProgrammerException(
				'Output capturing has already been started'
			);
		}
		ob_start();
		self::$capturing = true;
	}


	/**
	 * Stops output buffering, flushing everything to the browser
	 *
	 * @return void
	 */
	public static function stop(): void
	{
		if (!self::$started) {
			throw new ProgrammerException(
				'Output buffering can not be stopped since it has not been started'
			);
		}
		if (self::$capturing) {
			throw new ProgrammerException(
				'Output capturing is currently active and it must be stopped before buffering can be stopped'
			);
		}

		// Only flush if there is content to push out, otherwise
		// we might prevent headers from being sent
		if (ob_get_contents()) {
			ob_end_flush();
		} else {
			ob_end_clean();
		}

		self::$started = false;
	}


	/**
	 * Stops capturing output, returning what was captured
	 *
	 * @return string  The captured output
	 */
	public static function stopCapture(): string
	{
		if (!self::$capturing) {
			throw new ProgrammerException(
				'Output capturing can not be stopped since it has not been started'
			);
		}
		self::$capturing = false;
		$contents = ob_get_clean();
		return $contents === false ? '' : $contents;
	}


	/**
	 * Forces use as a static class
	 *
	 * @return Buffer
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
