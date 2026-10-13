<?php

declare(strict_types=1);

/**
 * Provides additional date/time functionality for ActiveRecord classes
 *
 * @copyright  Copyright (c) 2008-2010 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 * @link       http://flourishlib.com/ORMDate
 *
 * @version    1.0.0b9
 * @changes    1.0.0b9  Updated code to work with the new ORM API [wb, 2010-08-06]
 * @changes    1.0.0b8  Changed validation messages array to use column name keys [wb, 2010-05-26]
 * @changes    1.0.0b7  Fixed the `set` methods to return the record object in order to be consistent with all other `set` methods [wb, 2010-03-15]
 * @changes    1.0.0b6  Fixed an issue with calling a non-existent method on Timestamp instances [wb, 2009-11-03]
 * @changes    1.0.0b5  Updated code for the new ORMDatabase and ORMSchema APIs [wb, 2009-10-28]
 * @changes    1.0.0b4  Fixed setting up the inspect callback in ::configureTimezoneColumn() [wb, 2009-10-11]
 * @changes    1.0.0b3  Updated to use new ORM::registerInspectCallback() method [wb, 2009-07-13]
 * @changes    1.0.0b2  Updated code to use new ValidationException::formatField() method [wb, 2009-06-04]
 * @changes    1.0.0b   The initial implementation [wb, 2008-09-05]
 */
class ORMDate
{
	/**
	 * Columns that should be filled with the date created for new objects
	 *
	 * @var array<string, array<string, bool>>
	 */
	private static array $date_created_columns = array();

	/**
	 * Columns that should be filled with the date updated
	 *
	 * @var array<string, array<string, bool>>
	 */
	private static array $date_updated_columns = array();

	/**
	 * Columns that store timezone information for timestamp columns
	 *
	 * @var array<string, array<string, string>>
	 */
	private static array $timezone_columns = array();

	/**
	 * Timestamp columns that have a corresponding timezone column
	 *
	 * @var array<string, array<string, string>>
	 */
	private static array $timestamp_columns = array();


	/**
	 * Composes text using Text if loaded
	 *
	 * @param  string  $message    The message to compose
	 * @return string  The composed and possible translated message
	 */
	private static function compose(string $message): string
	{
		$args = array_slice(func_get_args(), 1);
		$args = array_map(static fn(mixed $arg): bool|float|int|string|null => ($arg === null || is_scalar($arg)) ? $arg : get_debug_type($arg), $args);

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
	 * Sets a column to be a date created column
	 *
	 * When a new record is stored in the database, date created columns will
	 * be filled with the timestamp of the store operation.
	 *
	 * @param  mixed  $class   The class name or instance of the class
	 * @param  string $column  The column to set as a date created column
	 * @return void
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	public static function configureDateCreatedColumn(mixed $class, string $column): void
	{
		$class     = ORM::getClass($class);
		$table     = ORM::tablize($class);
		$schema    = ORMSchema::retrieve($class);
		$data_type = $schema->getColumnInfo($table, $column, 'type');

		$valid_data_types = array('date', 'time', 'timestamp');
		if (!in_array($data_type, $valid_data_types)) {
			throw new ProgrammerException(
				'The column specified, %1$s, is a %2$s column. Must be one of %3$s to be set as a date created column.',
				$column,
				$data_type,
				join(', ', $valid_data_types)
			);
		}

		ORM::registerHookCallback($class, 'pre::validate()', array(self::class, 'setDateCreated'));

		ORM::registerInspectCallback($class, $column, array(self::class, 'inspect'));

		if (empty(self::$date_created_columns[$class])) {
			self::$date_created_columns[$class] = array();
		}

		self::$date_created_columns[$class][$column] = true;
	}


	/**
	 * Sets a column to be a date updated column
	 *
	 * Whenever a record is stored in the database, a date updated column will
	 * be set to the timestamp of the operation.
	 *
	 * @param  mixed  $class   The class name or instance of the class
	 * @param  string $column  The column to set as a date updated column
	 * @return void
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	public static function configureDateUpdatedColumn(mixed $class, string $column): void
	{
		$class     = ORM::getClass($class);
		$table     = ORM::tablize($class);
		$schema    = ORMSchema::retrieve($class);
		$data_type = $schema->getColumnInfo($table, $column, 'type');

		$valid_data_types = array('date', 'time', 'timestamp');
		if (!in_array($data_type, $valid_data_types)) {
			throw new ProgrammerException(
				'The column specified, %1$s, is a %2$s column. Must be one of %3$s to be set as a date updated column.',
				$column,
				$data_type,
				join(', ', $valid_data_types)
			);
		}

		ORM::registerHookCallback($class, 'pre::validate()', array(self::class, 'setDateUpdated'));

		ORM::registerInspectCallback($class, $column, array(self::class, 'inspect'));

		if (empty(self::$date_updated_columns[$class])) {
			self::$date_updated_columns[$class] = array();
		}

		self::$date_updated_columns[$class][$column] = true;
	}


	/**
	 * Sets a timestamp column to store the timezone in another column
	 *
	 * Since not all databases support timezone information in timestamp
	 * columns, this method allows storing the timezone in another columns.
	 * When the timestamp and timezone are retrieved from the database, they
	 * will be automatically combined together into an Timestamp object.
	 *
	 * @param  mixed  $class             The class name or instance of the class to set the column format
	 * @param  string $timestamp_column  The timestamp column to store the timezone for
	 * @param  string $timezone_column   The column to store the timezone in
	 * @return void
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	public static function configureTimezoneColumn(mixed $class, string $timestamp_column, string $timezone_column): void
	{
		$class               = ORM::getClass($class);
		$table               = ORM::tablize($class);
		$schema              = ORMSchema::retrieve($class);
		$timestamp_data_type = $schema->getColumnInfo($table, $timestamp_column, 'type');

		if ($timestamp_data_type != 'timestamp') {
			throw new ProgrammerException(
				'The timestamp column specified, %1$s, is a %2$s column. Must be a %3$s to have a related timezone column.',
				$timestamp_column,
				$timestamp_data_type,
				'timestamp'
			);
		}

		$timezone_column_data_type = $schema->getColumnInfo($table, $timezone_column, 'type');
		$valid_timezone_column_data_types = array('varchar', 'char', 'text');
		if (!in_array($timezone_column_data_type, $valid_timezone_column_data_types)) {
			throw new ProgrammerException(
				'The timezone column specified, %1$s, is a %2$s column. Must be %3$s to be set as a timezone column.',
				$timezone_column,
				$timezone_column_data_type,
				join(', ', $valid_timezone_column_data_types)
			);
		}

		if (!ORM::checkHookCallback($class, 'post::validate()', array(self::class, 'validateTimezoneColumns'))) {
			ORM::registerHookCallback($class, 'post::validate()', array(self::class, 'validateTimezoneColumns'));
		}

		if (!ORM::checkHookCallback($class, 'post::loadFromResult()', array(self::class, 'makeTimestampObjects'))) {
			ORM::registerHookCallback($class, 'post::loadFromResult()', array(self::class, 'makeTimestampObjects'));
		}

		if (!ORM::checkHookCallback($class, 'pre::validate()', array(self::class, 'makeTimestampObjects'))) {
			ORM::registerHookCallback($class, 'pre::validate()', array(self::class, 'makeTimestampObjects'));
		}

		ORM::registerInspectCallback($class, $timezone_column, array(self::class, 'inspect'));

		ORM::registerActiveRecordMethod(
			$class,
			'set' . Grammar::camelize($timestamp_column, true),
			array(self::class, 'setTimestampColumn')
		);

		ORM::registerActiveRecordMethod(
			$class,
			'set' . Grammar::camelize($timezone_column, true),
			array(self::class, 'setTimezoneColumn')
		);

		if (empty(self::$timestamp_columns[$class])) {
			self::$timestamp_columns[$class] = array();
		}
		self::$timestamp_columns[$class][$timestamp_column] = $timezone_column;

		if (empty(self::$timezone_columns[$class])) {
			self::$timezone_columns[$class] = array();
		}
		self::$timezone_columns[$class][$timezone_column] = $timestamp_column;
	}


	/**
	 * Adds metadata about features added by this class
	 *
	 * @internal
	 *
	 * @param  string $class      The class being inspected
	 * @param  string $column     The column being inspected
	 * @param  array<array-key, mixed>  &$metadata  The array of metadata about a column
	 * @return void
	 */
	public static function inspect(string $class, string $column, mixed &$metadata): void
	{
		if (!empty(self::$date_created_columns[$class][$column])) {
			$metadata['feature'] = 'date created';
		}

		if (!empty(self::$date_updated_columns[$class][$column])) {
			$metadata['feature'] = 'date updated';
		}

		if (!empty(self::$timezone_columns[$class][$column])) {
			$metadata['feature'] = 'timezone';
		}
	}


	/**
	 * Creates Timestamp objects for every timestamp/timezone combination in the object
	 *
	 * @internal
	 *
	 * @param  ActiveRecord $object            The ActiveRecord instance
	 * @param  array<array-key, mixed>  &$values           The current values
	 * @param  array<array-key, mixed>  &$old_values       The old values
	 * @param  array<array-key, mixed>  &$related_records  Any records related to this record
	 * @param  array<array-key, mixed>  &$cache            The cache array for the record
	 * @return void
	 */
	public static function makeTimestampObjects(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache): void
	{
		$class = get_class($object);

		if (!isset(self::$timezone_columns[$class])) {
			return;
		}

		foreach (self::$timezone_columns[$class] as $timezone_column => $timestamp_column) {
			self::objectifyTimestampWithTimezone($values, $old_values, $timestamp_column, $timezone_column);
		}
	}


	/**
	 * Turns a timestamp value into an Timestamp object with a timezone specified by another column
	 *
	 * @internal
	 *
	 * @param  array<array-key, mixed>  &$values           The current values
	 * @param  array<array-key, mixed>  &$old_values       The old values
	 * @param  string $timestamp_column  The column holding the timestamp
	 * @param  string $timezone_column   The column holding the timezone
	 * @return void
	 */
	public static function objectifyTimestampWithTimezone(mixed &$values, mixed &$old_values, string $timestamp_column, string $timezone_column): void
	{
		$value = $values[$timestamp_column];
		if (is_object($value)) {
			if (!$value instanceof Stringable) {
				return;
			}
			$value = (string) $value;
		} elseif (is_float($value)) {
			$value = (string) $value;
		} elseif (!is_string($value) && !is_int($value)) {
			return;
		}

		if (!strlen(trim((string) $value))) {
			return;
		}

		try {
			$timezone = $values[$timezone_column];
			if (!$timezone && $timezone !== '0' && $timezone !== 0) {
				$timezone = null;
			} elseif (!is_string($timezone)) {
				$timezone = is_scalar($timezone) ? (string) $timezone : null;
			}

			$value = new Timestamp($value, $timezone);

			if (ActiveRecord::hasOld($old_values, $timezone_column) && !ActiveRecord::hasOld($old_values, $timestamp_column)) {
				ActiveRecord::assign($values, $old_values, $timestamp_column, $value);
			} else {
				$values[$timestamp_column] = $value;
			}

			if ($values[$timezone_column] === null) {
				ActiveRecord::assign($values, $old_values, $timezone_column, $value->format('e'));
			}

		// If there was some error creating the timestamp object, we just leave all values alone
		} catch (ExpectedException $e) {
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
		self::$date_created_columns = array();
		self::$date_updated_columns = array();
		self::$timezone_columns     = array();
		self::$timestamp_columns    = array();
	}


	/**
	 * Sets the appropriate column values to the date the object was created (for new records)
	 *
	 * @internal
	 *
	 * @param  ActiveRecord $object            The ActiveRecord instance
	 * @param  array<array-key, mixed>  &$values           The current values
	 * @param  array<array-key, mixed>  &$old_values       The old values
	 * @param  array<array-key, mixed>  &$related_records  Any records related to this record
	 * @param  array<array-key, mixed>  &$cache            The cache array for the record
	 * @return void
	 * @throws ValidationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 */
	public static function setDateCreated(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache): void
	{
		if ($object->exists()) {
			return;
		}

		$class = get_class($object);

		foreach (self::$date_created_columns[$class] as $column => $enabled) {
			ActiveRecord::assign(
				$values,
				$old_values,
				$column,
				ORM::objectify($class, $column, date('Y-m-d H:i:s'))
			);
			// If the column has a corresponding timezone column, set that too
			if (isset(self::$timestamp_columns[$class][$column])) {
				ActiveRecord::assign(
					$values,
					$old_values,
					self::$timestamp_columns[$class][$column],
					Timestamp::getDefaultTimezone()
				);
			}
		}
	}


	/**
	 * Sets the appropriate column values to the date the object was updated
	 *
	 * @internal
	 *
	 * @param  ActiveRecord $object            The ActiveRecord instance
	 * @param  array<array-key, mixed>  &$values           The current values
	 * @param  array<array-key, mixed>  &$old_values       The old values
	 * @param  array<array-key, mixed>  &$related_records  Any records related to this record
	 * @param  array<array-key, mixed>  &$cache            The cache array for the record
	 * @return void
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	public static function setDateUpdated(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache): void
	{
		$class = get_class($object);

		foreach (self::$date_updated_columns[$class] as $column => $enabled) {
			ActiveRecord::assign(
				$values,
				$old_values,
				$column,
				ORM::objectify($class, $column, date('Y-m-d H:i:s'))
			);
			// If the column has a corresponding timezone column, set that too
			if (isset(self::$timestamp_columns[$class][$column])) {
				ActiveRecord::assign(
					$values,
					$old_values,
					self::$timestamp_columns[$class][$column],
					Timestamp::getDefaultTimezone()
				);
			}
		}
	}


	/**
	 * Sets the timestamp column and then tries to objectify it with an related timezone column
	 *
	 * @internal
	 *
	 * @param  ActiveRecord $object            The ActiveRecord instance
	 * @param  array<array-key, mixed>  &$values           The current values
	 * @param  array<array-key, mixed>  &$old_values       The old values
	 * @param  array<array-key, mixed>  &$related_records  Any records related to this record
	 * @param  array<array-key, mixed>  &$cache            The cache array for the record
	 * @param  string        $method_name       The method that was called
	 * @param  array<array-key, mixed>  $parameters        The parameters passed to the method
	 * @return ActiveRecord  The record object, to allow for method chaining
	 */
	public static function setTimestampColumn(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache, string $method_name, array $parameters): ActiveRecord
	{
		list ($action, $subject) = ORM::parseMethod($method_name);

		$column = Grammar::underscorize($subject);
		$class  = get_class($object);

		if (!isset($parameters[0])) {
			throw new ProgrammerException(
				'The method, %s(), requires at least one parameter',
				$method_name
			);
		}

		$value = $parameters[0];

		ActiveRecord::assign($values, $old_values, $column, $value);

		$timezone_column = self::$timestamp_columns[$class][$column];

		// See if we can make an Timestamp object out of the values
		self::objectifyTimestampWithTimezone($values, $old_values, $column, $timezone_column);

		if ($value instanceof Timestamp) {
			ActiveRecord::assign($values, $old_values, $timezone_column, $value->format('e'));
		}

		return $object;
	}


	/**
	 * Sets the timezone column and then tries to objectify the related timestamp column
	 *
	 * @internal
	 *
	 * @param  ActiveRecord $object            The ActiveRecord instance
	 * @param  array<array-key, mixed>  &$values           The current values
	 * @param  array<array-key, mixed>  &$old_values       The old values
	 * @param  array<array-key, mixed>  &$related_records  Any records related to this record
	 * @param  array<array-key, mixed>  &$cache            The cache array for the record
	 * @param  string        $method_name       The method that was called
	 * @param  array<array-key, mixed>  $parameters        The parameters passed to the method
	 * @return ActiveRecord  The record object, to allow for method chaining
	 */
	public static function setTimezoneColumn(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache, string $method_name, array $parameters): ActiveRecord
	{
		list ($action, $subject) = ORM::parseMethod($method_name);

		$column = Grammar::underscorize($subject);
		$class  = get_class($object);

		if (!isset($parameters[0])) {
			throw new ProgrammerException(
				'The method, %s(), requires at least one parameter',
				$method_name
			);
		}

		ActiveRecord::assign($values, $old_values, $column, $parameters[0]);

		// See if we can make an Timestamp object out of the values
		self::objectifyTimestampWithTimezone(
			$values,
			$old_values,
			self::$timezone_columns[$class][$column],
			$column
		);

		return $object;
	}


	/**
	 * Validates all timestamp/timezone columns
	 *
	 * @internal
	 *
	 * @param  ActiveRecord $object                The ActiveRecord instance
	 * @param  array<array-key, mixed>  &$values               The current values
	 * @param  array<array-key, mixed>  &$old_values           The old values
	 * @param  array<array-key, mixed>  &$related_records      Any records related to this record
	 * @param  array<array-key, mixed>  &$cache                The cache array for the record
	 * @param  array<array-key, mixed>  &$validation_messages  An array of ordered validation messages
	 * @return void
	 */
	public static function validateTimezoneColumns(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache, mixed &$validation_messages): void
	{
		$class = get_class($object);

		if (empty(self::$timezone_columns[$class])) {
			return;
		}

		foreach (self::$timezone_columns[$class] as $timezone_column => $timestamp_column) {
			if ($values[$timestamp_column] instanceof Timestamp || $values[$timestamp_column] === null) {
				continue;
			}
			$timezone = $values[$timezone_column];
			if (!is_string($timezone) || !Timestamp::isValidTimezone($timezone)) {
				$validation_messages[$timezone_column] = self::compose(
					'%sThe timezone specified is invalid',
					ValidationException::formatField(ORM::getColumnName($class, $timezone_column))
				);
			} else {
				$validation_messages[$timestamp_column] = self::compose(
					'%sPlease enter a date/time',
					ValidationException::formatField(ORM::getColumnName($class, $timestamp_column))
				);
			}
		}
	}


	/**
	 * Forces use as a static class
	 *
	 * @return ORMDate
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
