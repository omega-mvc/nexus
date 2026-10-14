<?php

declare(strict_types=1);

/**
 * Provides internationlization support for strings
 *
 * @copyright  Copyright (c) 2008-2009 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 *
 * @version    1.0.0b2
 * @changes    1.0.0b2  Updated ::compose() to more handle `$components` passed as an array [wb, 2009-02-05]
 * @changes    1.0.0b   The initial implementation [wb, 2008-11-12]
 */
class Text
{
	/**
	 * Callbacks for when messages are composed
	 *
	 * @var array
	 */
	/**
	 * @var array<string, list<callable(mixed):mixed|array<int|string,mixed>>>
	 */
	private static array $compose_callbacks = array(
		'pre'  => array(),
		'post' => array()
	);


	/**
	 * Performs an [http://php.net/sprintf sprintf()] on a string and provides a hook for modifications such as
	 * internationalization
	 *
	 * @param  string  $message    A message to compose
	 * @param  mixed   ...$components  A string or number to insert into the message
	 * @return string  The composed message
	 */
	public static function compose(string $message, mixed ...$components): string
	{
		if (!empty(self::$compose_callbacks['pre'])) {
			foreach (self::$compose_callbacks['pre'] as $callback) {
				if (!is_callable($callback)) {
					continue;
				}
				$result = call_user_func($callback, $message);
				if (is_string($result)) {
					$message = $result;
				}
			}
		}

		$components_array = $components;

		// Handles components passed as an array
		if (count($components_array) == 1 && is_array($components_array[0])) {
			$components_array = $components_array[0];
		}

		/** @var array<int|string,mixed> $components_array */
		$processed = array();
		foreach ($components_array as $key => $component) {
			if ($component instanceof \Stringable) {
				$processed[$key] = $component->__toString();
			} elseif (is_scalar($component) || is_null($component)) {
				$processed[$key] = $component;
			} elseif (is_array($component)) {
				// Arrays cannot be used in sprintf() interpolation
				$processed[$key] = 'Array';
			} elseif (is_object($component)) {
				$processed[$key] = get_class($component);
			} elseif (is_resource($component)) {
				$processed[$key] = 'Resource';
			} else {
				$processed[$key] = '';
			}
		}

		/** @var array<int|string,string|int|float|bool|null> $processed */
		$message = vsprintf($message, $processed);

		if (!empty(self::$compose_callbacks['post'])) {
			foreach (self::$compose_callbacks['post'] as $callback) {
				if (!is_callable($callback)) {
					continue;
				}
				$result = call_user_func($callback, $message);
				if (is_string($result)) {
					$message = $result;
				}
			}
		}

		return $message;
	}


	/**
	 * Adds a callback for when a message is created using ::compose()
	 *
	 * The primary purpose of these callbacks is for internationalization of
	 * error messaging in Flourish. The callback should accept a single
	 * parameter, the message being composed and should return the message
	 * with any modifications.
	 *
	 * The timing parameter controls if the callback happens before or after
	 * the actual composition takes place, which is simply a call to
	 * [http://php.net/sprintf sprintf()]. Thus the message passed `'pre'`
	 * will always be exactly the same, while the message `'post'` will include
	 * the interpolated variables. Because of this, most of the time the `'pre'`
	 * timing should be chosen.
	 *
	 * @param  string   $timing    When the callback should be executed - `'pre'` or `'post'` performing the actual
	 * composition
	 * @param  callable(mixed...):mixed $callback  The callback
	 * @return void
	 */
	/**
	 * @param callable(mixed):mixed|array<mixed>|string|null $callback
	 * @param string|callable(mixed):mixed|array<mixed>|null $timing
	 */
	public static function registerComposeCallback(mixed $callback = null, mixed $timing = 'pre'): void
	{
		if ($callback === null && func_num_args() === 2) {
			$callback = $timing;
			$timing = 'pre';
		} elseif (
			func_num_args() === 2 && !is_callable($callback) && (is_callable($timing) || is_array(
				$timing
			) || (is_string(
				$timing
			) && strpos(
				$timing,
				'::'
			) !== false))
		) {
			$temp = $callback;
			$callback = $timing;
			$timing = is_string($temp) ? $temp : 'pre';
		}

		$valid_timings = array('pre', 'post');
		if (!is_string($timing) || !in_array($timing, $valid_timings, true)) {
			throw new ProgrammerException(
				'The timing specified, %1$s, is not a valid timing. Must be one of: %2$s.',
				is_scalar($timing) ? (string)$timing : '',
				join(', ', $valid_timings)
			);
		}

		/** @var mixed $cb */
		$cb = $callback;
		if (is_string($callback) && strpos($callback, '::') !== false) {
			$parts = explode('::', $callback);
			$cb = $parts;
		} elseif (is_array($callback)) {
			$cb = $callback;
		}

		if (is_callable($cb) || is_array($cb)) {
			self::$compose_callbacks[$timing][] = $cb;
		}
	}


	/**
	 * Resets the configuration of the class
	 *
	 * @internal
	 *
	 * @return void
	 */
	public static function reset(): void
	{
		self::$compose_callbacks = array(
			'pre'  => array(),
			'post' => array()
		);
	}


	/**
	 * Forces use as a static class
	 *
	 * @return Text
	 */
	private function __construct()
	{
	}
}



/**
 * Copyright (c) 2008-2009 Will Bond <will@flourishlib.com>
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
