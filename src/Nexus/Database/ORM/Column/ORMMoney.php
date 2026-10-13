<?php

declare(strict_types=1);

/**
 * Provides money functionality for ActiveRecord classes
 *
 * @copyright  Copyright (c) 2008-2011 Will Bond, others
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @author     Dan Collins, iMarc LLC [dc-imarc] <dan@imarc.net>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 * @link       http://flourishlib.com/ORMMoney
 *
 * @version    1.0.0b11
 * @changes    1.0.0b11  Fixed the generation of validation messages when a non-monetary value is supplied [wb, 2011-05-17]
 * @changes    1.0.0b10  Updated code to work with the new ORM API [wb, 2010-08-06]
 * @changes    1.0.0b9   Added the `$remove_zero_fraction` parameter to prepare methods [wb, 2010-06-09]
 * @changes    1.0.0b8   Changed validation messages array to use column name keys [wb, 2010-05-26]
 * @changes    1.0.0b7   Fixed the `set` methods to return the record object in order to be consistent with all other `set` methods [wb, 2010-03-15]
 * @changes    1.0.0b6   Fixed duplicate validation messages and ProgrammerException object being thrown when NULL is set [dc-imarc+wb, 2010-03-03]
 * @changes    1.0.0b5   Updated code for the new ORMDatabase and ORMSchema APIs [wb, 2009-10-28]
 * @changes    1.0.0b4   Updated to use new ORM::registerInspectCallback() method [wb, 2009-07-13]
 * @changes    1.0.0b3   Updated code to use new ValidationException::formatField() method [wb, 2009-06-04]
 * @changes    1.0.0b2   Fixed bugs with objectifying money columns [wb, 2008-11-24]
 * @changes    1.0.0b    The initial implementation [wb, 2008-09-05]
 */
class ORMMoney
{
	/**
	 * Columns that store currency information for a money column, keyed by class then currency column
	 *
	 * @var array<string, array<string, string>>
	 */
	private static array $currency_columns = array();

	/**
	 * Columns that should be formatted as money, keyed by class then money column
	 *
	 * The value is the currency column name, or `FALSE` if there is no currency column
	 *
	 * @var array<string, array<string, string|false>>
	 */
	private static array $money_columns = array();


	/**
	 * Composes text using Text if loaded
	 *
	 * @param  string  $message    The message to compose
	 * @return string  The composed and possible translated message
	 */
	private static function compose(string $message): string
	{
		$args = array_slice(func_get_args(), 1);

		$args = array_map(
			static function (mixed $arg): bool|float|int|string|null {
				if ($arg === null || is_scalar($arg)) {
					return $arg;
				}

				return get_debug_type($arg);
			},
			$args
		);

		if (class_exists('Text', false)) {
			return call_user_func_array(
				array('Text', 'compose'),
				array($message, $args)
			);
		} else {
			return vsprintf($message, $args);
		}
	}


	/**
	 * Sets a column to be formatted as an Money object
	 *
	 * @param  mixed  $class            The class name or instance of the class to set the column format
	 * @param  string $column           The column to format as an Money object
	 * @param  string $currency_column  If specified, this column will store the currency of the Money object
	 * @return void
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	public static function configureMoneyColumn(mixed $class, string $column, ?string $currency_column = null): void
	{
		$class     = ORM::getClass($class);
		$table     = ORM::tablize($class);
		$schema    = ORMSchema::retrieve($class);
		$data_type = $schema->getColumnInfo($table, $column, 'type');

		$valid_data_types = array('float');
		if (!in_array($data_type, $valid_data_types)) {
			throw new ProgrammerException(
				'The column specified, %1$s, is a %2$s column. Must be %3$s to be set as a money column.',
				$column,
				$data_type,
				join(', ', $valid_data_types)
			);
		}

		if ($currency_column !== null) {
			$currency_column_data_type = $schema->getColumnInfo($table, $currency_column, 'type');
			$valid_currency_column_data_types = array('varchar', 'char', 'text');
			if (!in_array($currency_column_data_type, $valid_currency_column_data_types)) {
				throw new ProgrammerException(
					'The currency column specified, %1$s, is a %2$s column. Must be %3$s to be set as a currency column.',
					$currency_column,
					$currency_column_data_type,
					join(', ', $valid_currency_column_data_types)
				);
			}
		}

		$camelized_column = Grammar::camelize($column, true);

		ORM::registerActiveRecordMethod(
			$class,
			'encode' . $camelized_column,
			array(self::class, 'encodeMoneyColumn')
		);

		ORM::registerActiveRecordMethod(
			$class,
			'prepare' . $camelized_column,
			array(self::class, 'prepareMoneyColumn')
		);

		if (!ORM::checkHookCallback($class, 'post::validate()', array(self::class, 'validateMoneyColumns'))) {
			ORM::registerHookCallback($class, 'post::validate()', array(self::class, 'validateMoneyColumns'));
		}

		ORM::registerReflectCallback($class, array(self::class, 'reflect'));
		ORM::registerInspectCallback($class, $column, array(self::class, 'inspect'));

		$value = false;

		if ($currency_column) {
			$value = $currency_column;

			if (empty(self::$currency_columns[$class])) {
				self::$currency_columns[$class] = array();
			}
			self::$currency_columns[$class][$currency_column] = $column;

			if (!ORM::checkHookCallback($class, 'post::loadFromResult()', array(self::class, 'makeMoneyObjects'))) {
				ORM::registerHookCallback($class, 'post::loadFromResult()', array(self::class, 'makeMoneyObjects'));
			}

			if (!ORM::checkHookCallback($class, 'pre::validate()', array(self::class, 'makeMoneyObjects'))) {
				ORM::registerHookCallback($class, 'pre::validate()', array(self::class, 'makeMoneyObjects'));
			}

			ORM::registerActiveRecordMethod(
				$class,
				'set' . $camelized_column,
				array(self::class, 'setMoneyColumn')
			);

			ORM::registerActiveRecordMethod(
				$class,
				'set' . Grammar::camelize($currency_column, true),
				array(self::class, 'setCurrencyColumn')
			);
		} else {
			ORM::registerObjectifyCallback($class, $column, array(self::class, 'objectifyMoney'));
		}

		if (empty(self::$money_columns[$class])) {
			self::$money_columns[$class] = array();
		}

		self::$money_columns[$class][$column] = $value;
	}


	/**
	 * Encodes a money column by calling Money::__toString()
	 *
	 * @internal
	 *
	 * @param  ActiveRecord          $object            The ActiveRecord instance
	 * @param  array<array-key, mixed>  &$values           The current values
	 * @param  array<array-key, mixed>  &$old_values       The old values
	 * @param  array<array-key, mixed>  &$related_records  Any records related to this record
	 * @param  array<array-key, mixed>  &$cache            The cache array for the record
	 * @param  string                $method_name       The method that was called
	 * @param  array<array-key, mixed>     $parameters        The parameters passed to the method
	 * @return string  The encoded monetary value
	 */
	public static function encodeMoneyColumn(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache, string $method_name, array $parameters): string
	{
		list ($action, $subject) = ORM::parseMethod($method_name);

		$column = Grammar::underscorize($subject);
		$value  = $values[$column];

		if ($value instanceof Money) {
			$value = $value->__toString();
		}

		if (!is_string($value)) {
			if (!is_scalar($value)) {
				throw new ProgrammerException(
					'The value of the %s column can not be encoded since it is not a scalar value',
					$column
				);
			}
			$value = (string) $value;
		}

		return HTML::prepare($value);
	}


	/**
	 * Adds metadata about features added by this class
	 *
	 * @internal
	 *
	 * @param  string                $class      The class being inspected
	 * @param  string                $column     The column being inspected
	 * @param  array<array-key, mixed>  &$metadata  The array of metadata about a column
	 * @return void
	 */
	public static function inspect(string $class, string $column, mixed &$metadata): void
	{
		unset($metadata['auto_increment']);
		$metadata['feature'] = 'money';
	}


	/**
	 * Makes Money objects for all money columns in the object that also have a currency column
	 *
	 * @internal
	 *
	 * @param  ActiveRecord          $object            The ActiveRecord instance
	 * @param  array<array-key, mixed>  &$values           The current values
	 * @param  array<array-key, mixed>  &$old_values       The old values
	 * @param  array<array-key, mixed>  &$related_records  Any records related to this record
	 * @param  array<array-key, mixed>  &$cache            The cache array for the record
	 * @return void
	 */
	public static function makeMoneyObjects(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache): void
	{
		$class = get_class($object);

		if (!isset(self::$currency_columns[$class])) {
			return;
		}

		foreach (self::$currency_columns[$class] as $currency_column => $value_column) {
			self::objectifyMoneyWithCurrency($values, $old_values, $value_column, $currency_column);
		}
	}


	/**
	 * Turns a monetary value into an Money object
	 *
	 * @internal
	 *
	 * @param  string $class   The class this value is for
	 * @param  string $column  The column the value is in
	 * @param  mixed  $value   The value
	 * @return mixed  The Money object or raw value
	 */
	public static function objectifyMoney(string $class, string $column, mixed $value): mixed
	{
		if (is_object($value)) {
			if (!$value instanceof Money && !$value instanceof Stringable) {
				return $value;
			}
			$string_value = (string) $value;
		} elseif (is_string($value) || is_numeric($value)) {
			$string_value = (string) $value;
		} else {
			return $value;
		}

		if (!strlen(trim($string_value))) {
			return $value;
		}

		try {
			return new Money($string_value);

		// If there was some error creating the money object, just return the raw value
		} catch (ExpectedException $e) {
			return $value;
		}
	}


	/**
	 * Turns a monetary value into an Money object with a currency specified by another column
	 *
	 * @internal
	 *
	 * @param  array<array-key, mixed>  &$values          The current values
	 * @param  array<array-key, mixed>  &$old_values      The old values
	 * @param  string                $value_column     The column holding the value
	 * @param  string                $currency_column  The column holding the currency code
	 * @return void
	 */
	public static function objectifyMoneyWithCurrency(mixed &$values, mixed &$old_values, string $value_column, string $currency_column): void
	{
		$raw_value = $values[$value_column];

		if (is_object($raw_value)) {
			if (!$raw_value instanceof Money && !$raw_value instanceof Stringable) {
				return;
			}
			$string_value = (string) $raw_value;
		} elseif (is_string($raw_value) || is_numeric($raw_value)) {
			$string_value = (string) $raw_value;
		} else {
			return;
		}

		if (!strlen(trim($string_value))) {
			return;
		}

		try {
			$currency = $values[$currency_column];
			if (!$currency && $currency !== '0' && $currency !== 0) {
				$currency = null;
			} elseif (!is_string($currency)) {
				$currency = is_scalar($currency) ? (string) $currency : null;
			}

			$value = new Money($string_value, $currency);

			if (ActiveRecord::hasOld($old_values, $currency_column) && !ActiveRecord::hasOld($old_values, $value_column)) {
				ActiveRecord::assign($values, $old_values, $value_column, $value);
			} else {
				$values[$value_column] = $value;
			}

			if ($values[$currency_column] === null) {
				ActiveRecord::assign($values, $old_values, $currency_column, $value->getCurrency());
			}

		// If there was some error creating the money object, we just leave all values alone
		} catch (ExpectedException $e) {
        }
	}


	/**
	 * Prepares a money column by calling Money::format()
	 *
	 * @internal
	 *
	 * @param  ActiveRecord          $object            The ActiveRecord instance
	 * @param  array<array-key, mixed>  &$values           The current values
	 * @param  array<array-key, mixed>  &$old_values       The old values
	 * @param  array<array-key, mixed>  &$related_records  Any records related to this record
	 * @param  array<array-key, mixed>  &$cache            The cache array for the record
	 * @param  string                $method_name       The method that was called
	 * @param  array<array-key, mixed>     $parameters        The parameters passed to the method
	 * @return string  The formatted monetary value
	 */
	public static function prepareMoneyColumn(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache, string $method_name, array $parameters): string
	{
		list ($action, $subject) = ORM::parseMethod($method_name);

		$column = Grammar::underscorize($subject);
		if (empty($values[$column])) {
			$empty_value = $values[$column];

			return is_scalar($empty_value) ? (string) $empty_value : '';
		}
		$value = $values[$column];

		$remove_zero_fraction = false;
		if (count($parameters)) {
			$remove_zero_fraction = (bool) $parameters[0];
		}

		if ($value instanceof Money) {
			$value = $value->format($remove_zero_fraction);
		}

		if (!is_string($value)) {
			if (!is_scalar($value)) {
				throw new ProgrammerException(
					'The value of the %s column can not be prepared since it is not a scalar value',
					$column
				);
			}
			$value = (string) $value;
		}

		return HTML::prepare($value);
	}


	/**
	 * Adjusts the ActiveRecord::reflect() signatures of columns that have been configured in this class
	 *
	 * @internal
	 *
	 * @param  string                 $class                 The class to reflect
	 * @param  array<array-key, mixed>  &$signatures           The associative array of `{method name} => {signature}`
	 * @param  boolean                $include_doc_comments  If doc comments should be included with the signature
	 * @return void
	 */
	public static function reflect(string $class, mixed &$signatures, bool $include_doc_comments): void
	{
		if (!isset(self::$money_columns[$class])) {
			return;
		}

		foreach (self::$money_columns[$class] as $column => $enabled) {
			$camelized_column = Grammar::camelize($column, true);

			// Get and set methods
			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Gets the current value of " . $column . "\n";
				$signature .= " * \n";
				$signature .= " * @return Money  The current value\n";
				$signature .= " */\n";
			}
			$get_method = 'get' . $camelized_column;
			$signature .= 'public function ' . $get_method . '()';

			$signatures[$get_method] = $signature;


			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Sets the value for " . $column . "\n";
				$signature .= " * \n";
				$signature .= " * @param  Money|string|integer \$" . $column . "  The new value - a string or integer will be converted to the default currency (if defined)\n";
				$signature .= " * @return ActiveRecord  The record object, to allow for method chaining\n";
				$signature .= " */\n";
			}
			$set_method = 'set' . $camelized_column;
			$signature .= 'public function ' . $set_method . '($' . $column . ')';

			$signatures[$set_method] = $signature;

			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Encodes the value of " . $column . " for output into an HTML form\n";
				$signature .= " * \n";
				$signature .= " * If the value is an Money object, the ->__toString() method will be called\n";
				$signature .= " * resulting in the value minus the currency symbol and thousands separators\n";
				$signature .= " * \n";
				$signature .= " * @return string  The HTML form-ready value\n";
				$signature .= " */\n";
			}
			$encode_method = 'encode' . $camelized_column;
			$signature .= 'public function ' . $encode_method . '()';

			$signatures[$encode_method] = $signature;

			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Prepares the value of " . $column . " for output into HTML\n";
				$signature .= " * \n";
				$signature .= " * If the value is an Money object, the ->format() method will be called\n";
				$signature .= " * resulting in the value including the currency symbol and thousands separators\n";
				$signature .= " * \n";
				$signature .= " * @param  boolean \$remove_zero_fraction  If a fraction of all zeros should be removed\n";
				$signature .= " * @return string  The HTML-ready value\n";
				$signature .= " */\n";
			}
			$prepare_method = 'prepare' . $camelized_column;
			$signature .= 'public function ' . $prepare_method . '($remove_zero_fraction=FALSE)';

			$signatures[$prepare_method] = $signature;
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
		self::$currency_columns = array();
		self::$money_columns    = array();
	}


	/**
	 * Sets the currency column and then tries to objectify the related money column
	 *
	 * @internal
	 *
	 * @param  ActiveRecord          $object            The ActiveRecord instance
	 * @param  array<array-key, mixed>  &$values           The current values
	 * @param  array<array-key, mixed>  &$old_values       The old values
	 * @param  array<array-key, mixed>  &$related_records  Any records related to this record
	 * @param  array<array-key, mixed>  &$cache            The cache array for the record
	 * @param  string                $method_name       The method that was called
	 * @param  array<array-key, mixed>     $parameters        The parameters passed to the method
	 * @return ActiveRecord  The record object, to allow for method chaining
	 */
	public static function setCurrencyColumn(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache, string $method_name, array $parameters): ActiveRecord
	{
		list ($action, $subject) = ORM::parseMethod($method_name);

		$column = Grammar::underscorize($subject);
		$class  = get_class($object);

		if (count($parameters) < 1) {
			throw new ProgrammerException(
				'The method, %s(), requires at least one parameter',
				$method_name
			);
		}

		ActiveRecord::assign($values, $old_values, $column, $parameters[0]);

		// See if we can make an Money object out of the values
		self::objectifyMoneyWithCurrency(
			$values,
			$old_values,
			self::$currency_columns[$class][$column],
			$column
		);

		return $object;
	}


	/**
	 * Sets the money column and then tries to objectify it with an related currency column
	 *
	 * @internal
	 *
	 * @param  ActiveRecord          $object            The ActiveRecord instance
	 * @param  array<array-key, mixed>  &$values           The current values
	 * @param  array<array-key, mixed>  &$old_values       The old values
	 * @param  array<array-key, mixed>  &$related_records  Any records related to this record
	 * @param  array<array-key, mixed>  &$cache            The cache array for the record
	 * @param  string                $method_name       The method that was called
	 * @param  array<array-key, mixed>     $parameters        The parameters passed to the method
	 * @return ActiveRecord  The record object, to allow for method chaining
	 */
	public static function setMoneyColumn(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache, string $method_name, array $parameters): ActiveRecord
	{
		list ($action, $subject) = ORM::parseMethod($method_name);

		$column = Grammar::underscorize($subject);
		$class  = get_class($object);

		if (count($parameters) < 1) {
			throw new ProgrammerException(
				'The method, %s(), requires at least one parameter',
				$method_name
			);
		}

		$value = $parameters[0];

		ActiveRecord::assign($values, $old_values, $column, $value);

		$currency_column = self::$money_columns[$class][$column] ?? false;

		if (!is_string($currency_column)) {
			throw new ProgrammerException(
				'The %s column is not configured with a currency column',
				$column
			);
		}

		// See if we can make an Money object out of the values
		self::objectifyMoneyWithCurrency($values, $old_values, $column, $currency_column);

		if ($currency_column) {
			if ($value instanceof Money) {
				ActiveRecord::assign($values, $old_values, $currency_column, $value->getCurrency());
			}
		}

		return $object;
	}


	/**
	 * Validates all money columns
	 *
	 * @internal
	 *
	 * @param  ActiveRecord          $object                The ActiveRecord instance
	 * @param  array<array-key, mixed>  &$values               The current values
	 * @param  array<array-key, mixed>  &$old_values           The old values
	 * @param  array<array-key, mixed>  &$related_records      Any records related to this record
	 * @param  array<array-key, mixed>  &$cache                The cache array for the record
	 * @param  array<array-key, mixed>  &$validation_messages  An array of ordered validation messages
	 * @return void
	 */
	public static function validateMoneyColumns(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache, mixed &$validation_messages): void
	{
		$class = get_class($object);

		if (empty(self::$money_columns[$class])) {
			return;
		}

		foreach (self::$money_columns[$class] as $column => $currency_column) {
			if ($values[$column] instanceof Money || $values[$column] === null) {
				continue;
			}

			// Remove any previous validation warnings
			unset($validation_messages[$column]);

			if ($currency_column && !in_array($values[$currency_column], Money::getCurrencies())) {
				$validation_messages[$currency_column] = self::compose(
					'%sThe currency specified is invalid',
					ValidationException::formatField(ORM::getColumnName($class, $currency_column))
				);
			} else {
				$validation_messages[$column] = self::compose(
					'%sPlease enter a monetary value',
					ValidationException::formatField(ORM::getColumnName($class, $column))
				);
			}
		}
	}


	/**
	 * Forces use as a static class
	 *
	 * @return ORMMoney
	 */
	private function __construct()
    {
    }
}



/**
 * Copyright (c) 2008-2011 Will Bond <will@flourishlib.com>, others
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
