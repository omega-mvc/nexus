<?php

declare(strict_types=1);

/**
 * An exception caused by a data not matching a rule or set of rules
 *
 * @copyright  Copyright (c) 2007-2010 Will Bond, others
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @author     Will Bond, iMarc LLC [wb-imarc] <will@imarc.net>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 * @link       http://flourishlib.com/ValidationException
 *
 * @version    1.0.0b4
 * @changes    1.0.0b4  Added support for nested error arrays [wb-imarc, 2010-10-03]
 * @changes    1.0.0b3  Added ::removeFieldNames() [wb, 2010-05-26]
 * @changes    1.0.0b2  Added a custom ::__construct() to handle arrays of messages [wb, 2009-09-17]
 * @changes    1.0.0b   The initial implementation [wb, 2007-06-14]
 *
 * @phpstan-type MessageText string|array{name: string, errors: array<array-key, string>}
 * @phpstan-type Messages    array<array-key, MessageText>
 */
class ValidationException extends ExpectedException
{
	/**
	 * The formatting string to use for field names
	 *
	 * @var string
	 */
	protected static string $field_format = '%s: ';


	/**
	 * Accepts a field name and formats it based on the formatting string set via ::setFieldFormat()
	 *
	 * @param string $field  The name of the field to format
	 * @return string  The formatted field name
	 */
	public static function formatField(string $field): string
	{
		return sprintf(self::$field_format, $field);
	}


	/**
	 * Removes the field name from the front of a single validation message
	 *
	 * @param  string $message  The message to remove the field name from
	 * @return string  The message without the field name
	 */
	public static function removeFieldName(string $message): string
	{
		$token_field   = self::formatField('__TOKEN__');
		$replace_regex = '#^' . str_replace('__TOKEN__', '(.*?)', preg_quote($token_field, '#')) . '#';

		return preg_replace($replace_regex, '', $message) ?? $message;
	}


	/**
	 * Removes the field names from normal validation messages, leaving just the message part
	 *
	 * Each key is preserved untouched: normal string messages get the field
	 * name prefix stripped, while nested ORM message structures (`name` and
	 * `errors` keys) have their errors stripped recursively.
	 *
	 * @param  Messages $messages  The messages to remove the field names from
	 * @return Messages  The messages without field names
	 */
	public static function removeFieldNames(array $messages): array
	{
		foreach ($messages as $column => $message) {
			if (is_array($message)) {
				foreach ($message['errors'] as $error_key => $error) {
					$message['errors'][$error_key] = self::removeFieldName($error);
				}
				$messages[$column] = $message;
			} else {
				$messages[$column] = self::removeFieldName($message);
			}
		}

		return $messages;
	}


	/**
	* Set the format to be applied to all field names used in ValidationExceptions
	*
	* The format should contain exactly one `%s`
	* [http://php.net/sprintf sprintf()] conversion specification, which will
	* be replaced with the field name. Any literal `%` characters should be
	* written as `%%`.
	*
	* The default format is just `%s: `, which simply inserts a `:` and space
	* after the field name.
	*
	* @param string $format  A string to format the field name with - `%s` will be replaced with the field name
	* @return void
	*/
	public static function setFieldFormat(string $format): void
	{
		if (substr_count(str_replace('%%', '', $format), '%') != 1 || strpos($format, '%s') === false) {
			throw new ProgrammerException(
				'The format, %s, has more or less than exactly one %%s sprintf() conversion specification',
				$format
			);
		}
		self::$field_format = $format;
	}


	/**
	 * Sets the message for the exception, allowing for custom formatting beyond FlourishException
	 *
	 * If this method receives exactly two parameters, a string and an array,
	 * the string will be used as a message in a HTML `<p>` tag and the array
	 * will be turned into an unorder list `<ul>` tag with each element in the
	 * array being an `<li>` tag. It is possible to pass an optional exception
	 * code as a third parameter.
	 *
	 * The following PHP:
	 *
	 * {{{
	 * #!php
	 * throw new ValidationException(
	 *     'The following problems were found:',
	 *     array(
	 *         'Please provide your name',
	 *         'Please provide your email address'
	 *     )
	 * );
	 * }}}
	 *
	 * Would create the message:
	 *
	 * {{{
	 * #!text/html
	 * <p>The following problems were found:</p>
	 * <ul>
	 *     <li>Please provide your name</li>
	 *     <li>Please provide your email address</li>
	 * </ul>
	 * }}}
	 *
	 * If the parameters are anything else, they will be passed to
	 * FlourishException::__construct().
	 *
	 * @param  string $message       The beginning message for the exception. This will be placed in a `<p>` tag.
	 * @return FlourishException
	 */
	public function __construct(string $message = '', mixed ...$args)
	{
		if ((count($args) == 1 || count($args) == 2) && is_array($args[0])) {
			$message = sprintf(
				"<p>%1\$s</p>\n<ul>\n<li>%2\$s</li>\n</ul>",
				self::compose($message),
				join("</li>\n<li>", $this->formatErrorArray($args[0]))
			);

			// This escapes % signs since FlourishException is going to look for sprintf formatting codes
			$message = str_replace('%', '%%', $message);

			// This grabs the exception code if one is defined
			$args = array_slice($args, 1);
		}

		parent::__construct($message, ...$args);
	}


	/**
	 * Takes an error array that may or may not be nested and returns a HTML string representation
	 *
	 * @param  array<array-key, mixed> $errors  An array of (possibly nested) child record errors
	 * @return list<string>  A list of string error messages
	 */
	private function formatErrorArray(array $errors): array
	{
		$new_errors = array();
		foreach ($errors as $error) {
			if (is_string($error)) {
				$new_errors[] = $error;
			} elseif (is_array($error)) {
				$name   = (isset($error['name']) && is_string($error['name'])) ? $error['name'] : '';
				$nested = (isset($error['errors']) && is_array($error['errors'])) ? $error['errors'] : array();
				$new_errors[] = sprintf(
					"<span>%1\$s</span>\n<ul>\n<li>%2\$s</li>\n</ul>",
					$name,
					join("</li>\n<li>", $this->formatErrorArray($nested))
				);
			}
		}
		return $new_errors;
	}
}



/**
 * Copyright (c) 2007-2010 Will Bond <will@flourishlib.com>, others
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
