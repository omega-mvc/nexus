<?php

declare(strict_types=1);

/**
 * An [http://en.wikipedia.org/wiki/Active_record_pattern active record pattern] base class
 *
 * This class uses ORMSchema to inspect your database and provides an
 * OO interface to a single database table. The class dynamically handles
 * method calls for getting, setting and other operations on columns. It also
 * dynamically handles retrieving and storing related records.
 *
 * @copyright  Copyright (c) 2007-2016 Will Bond, others
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @author     Will Bond, iMarc LLC [wb-imarc] <will@imarc.net>
 * @author     Jeff Turcotte [jt] <jeff.turcotte@gmail.com>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 * @link       http://flourishlib.com/ActiveRecord
 *
 * @version    1.0.0b85
 * @changes    1.0.0b85  Updated ::reflect() to use ReflectionParameter::getType() on PHP 8.0+ since ::getClass() and ::isArray() were removed in PHP 8.1 [wb, 2026-07-10]
 * @changes    1.0.0b84  Fixed a typo in an ProgrammerException [wb, 2016-10-15]
 * @changes    1.0.0b83  Added the `$recursive` parameter to ::populate() [wb, 2011-09-16]
 * @changes    1.0.0b82  Added support for registering methods for __callStatic() [jt, 2011-07-25]
 * @changes    1.0.0b81  Fixed a bug with updating a record that contains only an auto-incrementing primary key [wb, 2011-09-06]
 * @changes    1.0.0b80  Added support to ::checkCondition() for the `^~` and `$~` operators [wb, 2011-06-20]
 * @changes    1.0.0b79  Fixed some bugs in handling relationships between PHP 5.3 namespaced classes [wb, 2011-05-26]
 * @changes    1.0.0b78  Backwards Compatibility Break - ::reflect() now returns an associative array instead of a string [wb, 2011-05-10]
 * @changes    1.0.0b77  Fixed ::inspect() to not throw an ProgrammerException when a valid element has a `NULL` value [wb, 2011-05-10]
 * @changes    1.0.0b76  Added ::clearIdentityMap() [wb, 2011-05-09]
 * @changes    1.0.0b75  Fixed a bug where child records of a record with a non-auto-incrementing primary key would not be saved properly for a new record [wb, 2010-12-06]
 * @changes    1.0.0b74  Updated ::populate() to use the `binary` type for Request::get() [wb, 2010-11-30]
 * @changes    1.0.0b73  Backwards Compatibility Break - changed column set methods to treat strings of all whitespace the same as empty string and convert them to `NULL` [wb, 2010-11-29]
 * @changes    1.0.0b72  Added the new `comment` element to the reflection signature for `inspect` methods [wb, 2010-11-28]
 * @changes    1.0.0b71  Updated class to use ORM::getRelatedClass() [wb, 2010-11-24]
 * @changes    1.0.0b70  Added support for PHP 5.3 namespaced ActiveRecord classes [wb, 2010-11-11]
 * @changes    1.0.0b69  Backwards Compatibility Break - changed ::validate() to return a nested array of validation messages when there are validation errors on child records [wb-imarc+wb, 2010-10-03]
 * @changes    1.0.0b68  Added hooks to ::replicate() [wb, 2010-09-07]
 * @changes    1.0.0b67  Updated code to work with the new ORM API [wb, 2010-08-06]
 * @changes    1.0.0b66  Fixed a bug with ::store() and non-primary key auto-incrementing columns [wb, 2010-07-05]
 * @changes    1.0.0b65  Fixed bugs with ::inspect() making some `min_value` and `max_value` elements available for non-numeric types, fixed ::reflect() to list the `min_value` and `max_value` elements [wb, 2010-06-08]
 * @changes    1.0.0b64  BackwardsCompatibilityBreak - changed ::validate()'s returned messages array to have field name keys - added the option to ::validate() to remove field names from messages [wb, 2010-05-26]
 * @changes    1.0.0b63  Changed how is_subclass_of() is used to work around a bug in 5.2.x [wb, 2010-04-06]
 * @changes    1.0.0b62  Fixed a bug that could cause infinite recursion starting in v1.0.0b60 [wb, 2010-04-02]
 * @changes    1.0.0b61  Fixed issues with handling `populate` actions when working with mapped classes [wb, 2010-03-31]
 * @changes    1.0.0b60  Fixed issues with handling `associate` and `has` actions when working with mapped classes, added ::validateClass() [wb, 2010-03-30]
 * @changes    1.0.0b59  Changed an extended UTF-8 arrow character into the correct `->` [wb, 2010-03-29]
 * @changes    1.0.0b58  Fixed ::reflect() to specify the value returned from `set` methods [wb, 2010-03-15]
 * @changes    1.0.0b57  Added the `post::loadFromIdentityMap()` hook and fixed ::__construct() to always call the `post::__construct()` hook [wb, 2010-03-14]
 * @changes    1.0.0b56  Fixed `$force_cascade` in ::delete() to work even when the restricted relationship is once-removed through an unrestricted relationship [wb, 2010-03-09]
 * @changes    1.0.0b55  Fixed ::load() to that related records are cleared, requiring them to be loaded from the database [wb, 2010-03-04]
 * @changes    1.0.0b54  Fixed detection of route name for one-to-one relationships in ::delete() [wb, 2010-03-03]
 * @changes    1.0.0b53  Fixed a bug where related records with a primary key that contained a foreign key with an on update cascade clause would be deleted when changing the value of the column referenced by the foreign key [wb, 2009-12-17]
 * @changes    1.0.0b52  Backwards Compatibility Break - Added the $force_cascade parameter to ::delete() and ::store() - enabled calling ::prepare() and ::encode() for non-column get methods, added `::has{RelatedRecords}()` methods [wb, 2009-12-16]
 * @changes    1.0.0b51  Made ::changed() properly recognize that a blank string and NULL are equivalent due to the way that ::set() casts values [wb, 2009-11-14]
 * @changes    1.0.0b50  Fixed a bug with trying to load by a multi-column primary key where one of the columns was not specified [wb, 2009-11-13]
 * @changes    1.0.0b49  Fixed a bug affecting where conditions with columns that are not null but have a default value [wb, 2009-11-03]
 * @changes    1.0.0b48  Updated code for the new ORMDatabase and ORMSchema APIs [wb, 2009-10-28]
 * @changes    1.0.0b47  Changed `::associate{RelatedRecords}()`, `::link{RelatedRecords}()` and `::populate{RelatedRecords}()` to allow for method chaining [wb, 2009-10-22]
 * @changes    1.0.0b46  Changed SQL statements to use value placeholders and identifier escaping [wb, 2009-10-22]
 * @changes    1.0.0b45  Added support for `!~`, `&~`, `><` and OR comparisons to ::checkConditions(), made object handling in ::checkConditions() more robust [wb, 2009-09-21]
 * @changes    1.0.0b44  Updated code for new ValidationException API [wb, 2009-09-18]
 * @changes    1.0.0b43  Updated code for new RecordSet API [wb, 2009-09-16]
 * @changes    1.0.0b42  Corrected a grammar bug in ::hash() [wb, 2009-09-09]
 * @changes    1.0.0b41  Fixed a bug in the last version that would cause issues with classes containing a custom class to table mapping [wb, 2009-09-01]
 * @changes    1.0.0b40  Added a check to the configuration part of ::__construct() to ensure modelled tables have primary keys [wb, 2009-08-26]
 * @changes    1.0.0b39  Changed `set{ColumnName}()` methods to return the record for method chaining, fixed a bug with loading by multi-column unique constraints, fixed a bug with ::load() [wb, 2009-08-26]
 * @changes    1.0.0b38  Updated ::changed() to do a strict comparison when at least one value is NULL [wb, 2009-08-17]
 * @changes    1.0.0b37  Changed ::__construct() to allow any Iterator object instead of just Result [wb, 2009-08-12]
 * @changes    1.0.0b36  Fixed a bug with setting NULL values from v1.0.0b33 [wb, 2009-08-10]
 * @changes    1.0.0b35  Fixed a bug with unescaping data in ::loadFromResult() from v1.0.0b33 [wb, 2009-08-10]
 * @changes    1.0.0b34  Added the ability to compare ActiveRecord objects in ::checkConditions() [wb, 2009-08-07]
 * @changes    1.0.0b33  Performance enhancements to ::__call() and ::__construct() [wb, 2009-08-07]
 * @changes    1.0.0b32  Changed ::delete() to remove auto-incrementing primary keys after the post::delete() hook [wb, 2009-07-29]
 * @changes    1.0.0b31  Fixed a bug with loading a record by a multi-column primary key, fixed one-to-one relationship API [wb, 2009-07-21]
 * @changes    1.0.0b30  Updated ::reflect() for new ORM::callReflectCallbacks() API [wb, 2009-07-13]
 * @changes    1.0.0b29  Updated to use new ORM::callInspectCallbacks() method [wb, 2009-07-13]
 * @changes    1.0.0b28  Fixed a bug where records would break the identity map at the end of ::store() [wb, 2009-07-09]
 * @changes    1.0.0b27  Changed ::hash() from a protected method to a static public/internal method that requires the class name for non-ActiveRecord values [wb, 2009-07-09]
 * @changes    1.0.0b26  Added ::checkConditions() from RecordSet [wb, 2009-07-08]
 * @changes    1.0.0b25  Updated ::validate() to use new ORMValidation API, including new message search/replace functionality [wb, 2009-07-01]
 * @changes    1.0.0b24  Changed ::validate() to remove duplicate validation messages [wb, 2009-06-30]
 * @changes    1.0.0b23  Updated code for new ORMValidation::validateRelated() API [wb, 2009-06-26]
 * @changes    1.0.0b22  Added support for the $formatting parameter to encode methods on char, text and varchar columns [wb, 2009-06-19]
 * @changes    1.0.0b21  Performance tweaks and updates for ORM and ORMRelated API changes [wb, 2009-06-15]
 * @changes    1.0.0b20  Changed replacement values in preg_replace() calls to be properly escaped [wb, 2009-06-11]
 * @changes    1.0.0b19  Added `list{RelatedRecords}()` methods, updated code for new ORMRelated API [wb, 2009-06-02]
 * @changes    1.0.0b18  Changed ::store() to use new ORMRelated::store() method [wb, 2009-06-02]
 * @changes    1.0.0b17  Added some missing parameter information to ::reflect() [wb, 2009-06-01]
 * @changes    1.0.0b16  Fixed bugs in ::__clone() and ::replicate() related to recursive relationships [wb-imarc, 2009-05-20]
 * @changes    1.0.0b15  Fixed an incorrect variable reference in ::store() [wb, 2009-05-06]
 * @changes    1.0.0b14  ::store() no longer tries to get an auto-incrementing ID from the database if a value was set [wb, 2009-05-02]
 * @changes    1.0.0b13  ::delete(), ::load(), ::populate() and ::store() now return the record to allow for method chaining [wb, 2009-03-23]
 * @changes    1.0.0b12  ::set() now removes commas from integers and floats to prevent validation issues [wb, 2009-03-22]
 * @changes    1.0.0b11  ::encode() no longer adds commas to floats [wb, 2009-03-22]
 * @changes    1.0.0b10  ::__wakeup() no longer registers the record as the definitive copy in the identity map [wb, 2009-03-22]
 * @changes    1.0.0b9   Changed ::__construct() to populate database default values when a non-existing record is instantiated [wb, 2009-01-12]
 * @changes    1.0.0b8   Fixed ::exists() to properly detect cases when an existing record has one or more NULL values in the primary key [wb, 2009-01-11]
 * @changes    1.0.0b7   Fixed ::__construct() to not trigger the post::__construct() hook when force-configured [wb, 2008-12-30]
 * @changes    1.0.0b6   ::__construct() now accepts an associative array matching any unique key or primary key, fixed the post::__construct() hook to be called once for each record [wb, 2008-12-26]
 * @changes    1.0.0b5   Fixed ::replicate() to use plural record names for related records [wb, 2008-12-12]
 * @changes    1.0.0b4   Added ::replicate() to allow cloning along with related records [wb, 2008-12-12]
 * @changes    1.0.0b3   Changed ::__clone() to clone objects contains in the values and cache arrays [wb, 2008-12-11]
 * @changes    1.0.0b2   Added the ::__clone() method to properly duplicate a record [wb, 2008-12-04]
 * @changes    1.0.0b    The initial implementation [wb, 2007-08-04]
 *
 * @phpstan-import-type RelatedRecords from ORMRelated
 * @phpstan-import-type RelatedValues  from ORMRelated
 */
abstract class ActiveRecord
{


	/**
	 * Caches callbacks for methods
	 *
	 * @var array<string, array<string, callable(mixed ...): mixed|string|false>>
	 */
	protected static array $callback_cache = array();

	/**
	 * An array of flags indicating a class has been configured
	 *
	 * @var array<string, bool>
	 */
	protected static array $configured = array();


	/**
	 * Maps objects via their primary key
	 *
	 * @var array<string, array<string, ActiveRecord>>
	 */
	protected static array $identity_map = array();


	/**
	 * Caches method name parsings
	 *
	 * @var array<string, array{action: string, subject: string}>
	 */
	protected static array $method_name_cache = array();


	/**
	 * Keeps track of the recursive call level of replication so we can clear the map
	 *
	 * @var integer
	 */
	protected static int $replicate_level = 0;


	/**
	 * Keeps a list of records that have been replicated
	 *
	 * @var array<string, array<string, ActiveRecord>>
	 */
	protected static array $replicate_map = array();


	/**
	 * Caches callbacks for static methods
	 *
	 * @var array<string, array<string, callable(mixed ...): mixed|string|false>>
	 **/
	protected static array $static_callback_cache = array();


	/**
	 * Contains a list of what columns in each class need to be unescaped and what data type they are
	 *
	 * @var array<string, array<string, string>>
	 */
	protected static array $unescape_map = array();


	/**
	 * Handles dynamically registered static method callbacks
	 *
	 * Static method callbacks registered through ORM::registerActiveRecordStaticMethod()
	 * will be delegated via this method. Both this and ORM::registerActiveRecordStaticMethod
	 * are available to PHP 5.3+ only.
	 *
	 * @throws ProgrammerException  When the method cannot be found
	 * @param  string $method_name  The name of the method called
	 * @param  array<int, mixed> $parameters  The parameters passed
	 * @return mixed  The value returned by the method called
	 */
	public static function __callStatic(mixed $method_name, mixed $parameters)
	{
		$class = get_called_class();

		self::forceConfigure($class);

		if (!isset(self::$static_callback_cache[$class][$method_name])) {
			if (!isset(self::$static_callback_cache[$class])) {
				self::$static_callback_cache[$class] = array();
			}
			$callback = ORM::getActiveRecordStaticMethod($class, $method_name);
			self::$static_callback_cache[$class][$method_name] = is_callable($callback) ? $callback : false;
		}

		if ($callback = self::$static_callback_cache[$class][$method_name]) {
			if (is_callable($callback)) {
				return call_user_func_array(
					$callback,
					array(
						$class,
						$method_name,
						$parameters
					)
				);
			}
		}

		// Error handler
		throw new ProgrammerException(
			'Unknown static method, %s(), called',
			$method_name
		);
	}


	/**
	 * Sets a value to the `$values` array, preserving the old value in `$old_values`
	 *
	 * @internal
	 *
	 * @param  array<mixed> &$values      The current values
	 * @param  array<mixed> &$old_values  The old values
	 * @param  string $column       The column to set
	 * @param  mixed  $value        The value to set
	 * @return void
	 */
	public static function assign(mixed &$values, mixed &$old_values, string $column, mixed $value): void
	{
		$old = $old_values[$column] ?? array();
		if (!is_array($old)) {
			$old = array();
		}
		$old[]                 = $values[$column];
		$old_values[$column]   = $old;
		$values[$column]       = $value;
	}


	/**
	 * Checks to see if a value has changed
	 *
	 * @internal
	 *
	 * @param  array<mixed> &$values      The current values
	 * @param  array<mixed> &$old_values  The old values
	 * @param  string $column       The column to check
	 * @return boolean  If the value for the column specified has changed
	 */
	public static function changed(mixed &$values, mixed &$old_values, string $column): bool
	{
		if (!isset($old_values[$column])) {
			return false;
		}

		$oldest       = $old_values[$column];
		$oldest_value = is_array($oldest) ? $oldest[0] : null;
		$new_value    = $values[$column];

		// We do a strict comparison when one of the values is NULL since
		// NULL is almost always meant to be distinct from 0, FALSE, etc.
		// However, since we cast blank strings to NULL in ::set() but a blank
		// string could come out of the database, we consider them to be
		// equivalent, so we don't do a strict comparison
		if (($oldest_value === null && $new_value !== '') || ($new_value === null && $oldest_value !== '')) {
			return $oldest_value !== $new_value;
		}

		return $oldest_value != $new_value;
	}


	/**
	 * Ensures a class extends ActiveRecord
	 *
	 * @internal
	 *
	 * @param  string $class  The class to check
	 * @return boolean  If the class is an ActiveRecord descendant
	 */
	public static function checkClass(string $class): bool
	{
		if (isset(self::$configured[$class])) {
			return true;
		}

		if (!$class || !class_exists($class) || !($class == 'ActiveRecord' || is_subclass_of($class, 'ActiveRecord'))) {
			return false;
		}
		return true;
	}


	/**
	 * Checks to see if a record matches a condition
	 *
	 * @internal
	 *
	 * @param  string $operator  The record to check
	 * @param  mixed  $value     The value to compare to
	 * @param  mixed $result     The result of the method call(s)
	 * @return boolean  If the comparison was successful
	 * @throws ValidationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 */
	private static function checkCondition(string $operator, mixed $value, mixed $result): bool
	{
		$value_is_array = is_array($value);
		$value_array    = $value_is_array ? $value : array($value);
		foreach ($value_array as $i => $_value) {
			if (is_object($_value)) {
				if ($_value instanceof ActiveRecord) {
					continue;
				}
				if (method_exists($_value, '__toString')) {
					$value_array[$i] = $_value->__toString();
				}
			}
		}
		$value = $value_is_array ? $value_array : $value_array[0];

		$result_is_array = is_array($result);
		$result_array    = $result_is_array ? $result : array($result);
		foreach ($result_array as $i => $_result) {
			if (is_object($_result)) {
				if ($_result instanceof ActiveRecord) {
					continue;
				}
				if (method_exists($_result, '__toString')) {
					$result_array[$i] = $_result->__toString();
				}
			}
		}
		$result = $result_is_array ? $result_array : $result_array[0];

		if ($operator == '~' && !is_array($value) && is_array($result) && is_string($value)) {
			$value = ORMDatabase::parseSearchTerms($value, true);
		}

		if (in_array($operator, array('~', '&~', '!~', '^~', '$~'))) {
			settype($value, 'array');
			settype($result, 'array');
		}

		$value_array  = is_array($value) ? $value : array($value);
		$result_array = is_array($result) ? $result : array($result);

		switch ($operator) {
			case '&~':
				foreach ($value_array as $_value) {
					if (UTF8::ipos(self::toString($result_array[0] ?? ''), self::toString($_value)) === false) {
						return false;
					}
				}
				break;

			case '~':
				// Handles fuzzy search on multiple method calls
				if (count($result_array) > 1) {
					foreach ($value_array as $_value) {
						$found = false;
						foreach ($result_array as $_result) {
							if (UTF8::ipos(self::toString($_result), self::toString($_value)) !== false) {
								$found = true;
							}
						}
						if (!$found) {
							return false;
						}
					}
					break;
				}

				// No break exists since a ~ on a single method call acts
				// similar to the other LIKE operators

			case '!~':
			case '^~':
			case '$~':
				if ($operator == '$~') {
					$result_len = UTF8::len(self::toString($result_array[0] ?? ''));
				}

				foreach ($value_array as $_value) {
					$pos = UTF8::ipos(self::toString($result_array[0] ?? ''), self::toString($_value));
					if ($operator == '^~' && $pos === 0) {
						return true;
					} elseif ($operator == '$~' && $pos === $result_len - UTF8::len(self::toString($_value))) {
						return true;
					} elseif ($pos !== false) {
						return $operator != '!~';
					}
				}

				if ($operator != '!~') {
					return false;
				}
				break;

			case '=':
				if ($value instanceof ActiveRecord && $result instanceof ActiveRecord) {
					if (get_class($value) != get_class($result) || !$value->exists() || !$result->exists() || self::hash($value) != self::hash($result)) {
						return false;
					}
				} elseif (is_array($value) && !in_array($result, $value)) {
					return false;
				} elseif (!is_array($value) && $result != $value) {
					return false;
				}
				break;

			case '!':
				if ($value instanceof ActiveRecord && $result instanceof ActiveRecord) {
					if (get_class($value) == get_class($result) && $value->exists() && $result->exists() && self::hash($value) == self::hash($result)) {
						return false;
					}
				} elseif (is_array($value) && in_array($result, $value)) {
					return false;
				} elseif (!is_array($value) && $result == $value) {
					return false;
				}
				break;

			case '<':
				if ($result >= $value) {
					return false;
				}
				break;

			case '<=':
				if ($result > $value) {
					return false;
				}
				break;

			case '>':
				if ($result <= $value) {
					return false;
				}
				break;

			case '>=':
				if ($result < $value) {
					return false;
				}
				break;
		}

		return true;
	}


	/**
	 * Checks to see if a record matches all of the conditions
	 *
	 * @internal
	 *
	 * @param  ActiveRecord $record      The record to check
	 * @param  array<string, mixed> $conditions  The conditions to check - see RecordSet::filter() for format details
	 * @return boolean  If the record meets all conditions
	 * @throws ValidationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 */
	public static function checkConditions(ActiveRecord $record, array $conditions): bool
	{
		foreach ($conditions as $method => $value) {
			// Split the operator off of the end of the method name
			if (in_array(substr($method, -2), array('<=', '>=', '!=', '<>', '!~', '&~', '^~', '$~', '><'))) {
				$operator = strtr(
					substr($method, -2),
					array(
						'<>' => '!',
						'!=' => '!'
					)
				);
				$method   = substr($method, 0, -2);
			} else {
				$operator = substr($method, -1);
				$method   = substr($method, 0, -1);
			}

			if (preg_match('#(?<!\|)\|(?!\|)#', $method)) {
				$methods   = explode('|', $method);
				$values    = is_array($value) ? $value : array($value);
				$operators = array();

				foreach ($methods as &$_method) {
					if (in_array(substr($_method, -2), array('<=', '>=', '!=', '<>', '!~', '&~', '^~', '$~', '><'))) {
						$operators[] = strtr(
							substr($_method, -2),
							array(
								'<>' => '!',
								'!=' => '!'
							)
						);
						$_method     = substr($_method, 0, -2);
					} elseif (!ctype_alnum(substr($_method, -1))) {
						$operators[] = substr($_method, -1);
						$_method     = substr($_method, 0, -1);
					}
				}
				$operators[] = $operator;


				if (sizeof($operators) == 1) {
					// Handle fuzzy searches
					if ($operator == '~') {
						$results = array();
						foreach ($methods as $method) {
							$results[] = $record->$method();
						}
						if (!self::checkCondition($operator, $value, $results)) {
							return false;
						}

					// Handle intersection
					} elseif ($operator == '><') {
						if (sizeof($methods) != 2 || sizeof($values) != 2) {
							throw new ProgrammerException(
								'The intersection operator, %s, requires exactly two methods and two values',
								$operator
							);
						}

						$results    = array();
						$results[0] = $record->{$methods[0]}();
						$results[1] = $record->{$methods[1]}();

						if ($results[1] === null && $values[1] === null) {
							if (!self::checkCondition('=', $values[0], $results[0])) {
								return false;
							}
						} else {
							$starts_between_values = false;
							$overlaps_value_1      = false;

							if ($values[1] !== null) {
								$start_lt_value_1      = self::checkCondition('<', $values[0], $results[0]);
								$start_gt_value_2      = self::checkCondition('>', $values[1], $results[0]);
								$starts_between_values = !$start_lt_value_1 && !$start_gt_value_2;
							}
							if ($results[1] !== null) {
								$start_gt_value_1 = self::checkCondition('>', $values[0], $results[0]);
								$end_lt_value_1   = self::checkCondition('<', $values[0], $results[1]);
								$overlaps_value_1 = !$start_gt_value_1 && !$end_lt_value_1;
							}

							if (!$starts_between_values && !$overlaps_value_1) {
								return false;
							}
						}
					} else {
						throw new ProgrammerException(
							'An invalid comparison operator, %s, was specified for multiple columns',
							$operator
						);
					}

				// Handle OR conditions
				} else {
					if (sizeof($methods) != sizeof($values)) {
						throw new ProgrammerException(
							'When performing an %1$s comparison there must be an equal number of methods and values, however there are not',
							'OR',
							sizeof($methods),
							sizeof($values)
						);
					}

					if (sizeof($methods) != sizeof($operators)) {
						throw new ProgrammerException(
							'When performing an %s comparison there must be a comparison operator for each column, however one or more is missing',
							'OR'
						);
					}

					$results    = array();
					$iterations = sizeof($methods);
					for ($i = 0; $i < $iterations; $i++) {
						$results[] = self::checkCondition($operators[$i], $values[$i], $record->{$methods[$i]}());
					}

					if (!array_filter($results)) {
						return false;
					}
				}

			// Single method comparisons
			} else {
				$result = $record->$method();
				if (!self::checkCondition($operator, $value, $result)) {
					return false;
				}
			}
		}

		return true;
	}


	/**
	 * Clears the identity map
	 *
	 * @return void
	 */
	public static function clearIdentityMap(): void
	{
		self::$identity_map = array();
	}


	/**
	 * Composes text using Text if loaded
	 *
	 * @param  string  $message    The message to compose
	 * @return string  The composed and possible translated message
	 */
	protected static function compose(string $message): string
	{
		$args = array_slice(func_get_args(), 1);

		if (class_exists('Text', false)) {
			return call_user_func_array(
				array('Text', 'compose'),
				array($message, $args)
			);
		} else {
			$safe_args = array();
			foreach ($args as $arg) {
				if (is_scalar($arg) || $arg === null) {
					$safe_args[] = $arg;
				} elseif ($arg instanceof Stringable) {
					$safe_args[] = $arg->__toString();
				} else {
					$safe_args[] = get_debug_type($arg);
				}
			}
			return vsprintf($message, $safe_args);
		}
	}


	/**
	 * Takes information from a method call and determines the subject, route and if subject was plural
	 *
	 * @param string $class    The class the method was called on
	 * @param string $subject  An underscore_notation subject - either a singular or plural class name
	 * @param string|NULL $route  The route to the subject
	 * @return array{0: string, 1: string, 2: bool}  An array with the structure: array(0 => $subject, 1 => $route, 2 => $plural)
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 * @throws ValidationException
	 */
	private static function determineSubject(string $class, string $subject, ?string $route): array
	{
		$schema  = ORMSchema::retrieve($class);
		$table   = ORM::tablize($class);
		$type    = '*-to-many';
		$plural  = false;

		// one-to-many relationships need to use plural forms
		$singular_form = Grammar::singularize($subject, true);
		if ($singular_form && ORM::isClassMappedToTable($singular_form)) {
			$subject = $singular_form;
			$plural  = true;
		} elseif (!ORM::isClassMappedToTable($subject) && in_array(Grammar::underscorize($subject), $schema->getTables())) {
			$singular_subject = Grammar::singularize($subject);
			if ($singular_subject !== false) {
				$subject = $singular_subject;
			}
			$plural  = true;
		}

		$related_table = ORM::tablize($subject);
		$one_to_one    = ORMSchema::isOneToOne($schema, $table, $related_table, $route);
		if ($one_to_one) {
			$type = 'one-to-one';
		}
		if (($one_to_one && $plural) || (!$plural && !$one_to_one)) {
			throw new ProgrammerException(
				'The table %1$s is not in a %2$s relationship with the table %3$s',
				$table,
				$type,
				$related_table
			);
		}

		$route = ORMSchema::getRouteName($schema, $table, $related_table, $route, $type);

		return array($subject, $route, $plural);
	}


	/**
	 * Ensures that ::configure() has been called for the class
	 *
	 * @internal
	 *
	 * @param  string $class  The class to configure
	 * @return void
	 */
	public static function forceConfigure(string $class): void
	{
		if (isset(self::$configured[$class])) {
			return;
		}
		new $class();
	}


	/**
	 * Takes a row of data or a primary key and makes a hash from the primary key
	 *
	 * @internal
	 *
	 * @param  ActiveRecord|array<array-key, mixed>|bool|float|int|string $record   An ActiveRecord object, an array of the records data, an array of primary key data or a scalar primary key value
	 * @param  string                         $class    The class name, if $record isn't an ActiveRecord
	 * @return string|NULL  A hash of the record's primary key value or NULL if the record doesn't exist yet
	 * @throws ValidationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 */
	public static function hash(mixed $record, ?string $class = null): mixed
	{
		if ($record instanceof ActiveRecord && !$record->exists()) {
			return null;
		}

		if ($class === null) {
			if (!$record instanceof ActiveRecord) {
				throw new ProgrammerException(
					'The class of the record must be provided if the record specified is not an instance of ActiveRecord'
				);
			}
			$class = get_class($record);
		}

		$schema     = ORMSchema::retrieve($class);
		$table      = ORM::tablize($class);
		$pk_columns = $schema->getKeys($table, 'primary');

		// Build an array of just the primary key data
		$pk_data = array();
		foreach ($pk_columns as $pk_column) {
			if ($record instanceof ActiveRecord) {
				$value = (self::hasOld($record->old_values, $pk_column)) ? self::retrieveOld($record->old_values, $pk_column) : $record->values[$pk_column];
			} elseif (is_array($record)) {
				$value = $record[$pk_column];
			} else {
				$value = $record;
			}

			$pk_data[$pk_column] = ORM::scalarize(
				$class,
				$pk_column,
				$value
			);

			if (is_numeric($pk_data[$pk_column]) || (is_object($pk_data[$pk_column]) && is_callable(array($pk_data[$pk_column], '__toString')))) {
				$pk_data[$pk_column] = (string) $pk_data[$pk_column];
			}
		}

		return md5(serialize($pk_data));
	}


	/**
	 * Checks to see if an old value exists for a column
	 *
	 * @internal
	 *
	 * @param  array<mixed> &$old_values  The old values
	 * @param  string $column       The column to set
	 * @return boolean  If an old value for that column exists
	 */
	public static function hasOld(mixed &$old_values, string $column): bool
	{
		return array_key_exists($column, $old_values);
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
		self::$callback_cache    = array();
		self::$configured        = array();
		self::$identity_map      = array();
		self::$method_name_cache = array();
		self::$unescape_map      = array();
	}


	/**
	 * Retrieves the oldest value for a column or all old values
	 *
	 * @internal
	 *
	 * @param  array<mixed> &$old_values  The old values
	 * @param  string  $column       The column to get
	 * @param  mixed   $default      The default value to return if no value exists
	 * @param  boolean $return_all   Return the array of all old values for this column instead of just the oldest
	 * @return mixed  The old value for the column
	 */
	public static function retrieveOld(mixed &$old_values, string $column, mixed $default = null, bool $return_all = false): mixed
	{
		if (!isset($old_values[$column])) {
			return $default;
		}

		$old = $old_values[$column];

		if ($return_all === true) {
			return $old;
		}

		return is_array($old) ? $old[0] : $default;
	}


	/**
	 * Ensures a class extends ActiveRecord
	 *
	 * @internal
	 *
	 * @param  string $class  The class to verify
	 * @return boolean|null  If the class is a valid ActiveRecord descendant
	 *
	 * @phpstan-assert class-string<ActiveRecord> $class
	 */
	public static function validateClass(string $class): mixed
	{
		if (isset(self::$configured[$class])) {
			return true;
		}

		if (!self::checkClass($class)) {
			throw new ProgrammerException(
				'The class specified, %1$s, does not appear to be a valid %2$s class',
				$class,
				'ActiveRecord'
			);
		}

		return null;
	}


	/**
	 * Returns the column info for a table with string keys and array values
	 *
	 * This wraps Schema::getColumnInfo(), which returns `mixed`, and verifies
	 * the shape so the rest of the class can rely on the array structure.
	 *
	 * @param  Schema $schema  The schema object to get the column info from
	 * @param  string $table   The table to get the column info for
	 * @return array<string, array<mixed>>  The info for each column
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	private static function getColumnInfo(Schema $schema, string $table): array
	{
		$info   = $schema->getColumnInfo($table);
		$result = array();

		if (!is_array($info)) {
			return $result;
		}

		foreach ($info as $column => $column_info) {
			if (is_string($column) && is_array($column_info)) {
				$result[$column] = $column_info;
			}
		}

		return $result;
	}


	/**
	 * Returns the info array for a single column
	 *
	 * @param  Schema $schema  The schema object to get the column info from
	 * @param  string $table   The table the column is part of
	 * @param  string $column  The column to get the info for
	 * @return array<mixed>  The info for the column
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	private static function getColumnInfoForColumn(Schema $schema, string $table, string $column): array
	{
		$info = $schema->getColumnInfo($table, $column);

		return is_array($info) ? $info : array();
	}


	/**
	 * Returns a single element of the info for a column as a string
	 *
	 * @param  Schema $schema   The schema object to get the column info from
	 * @param  string $table    The table the column is part of
	 * @param  string $column   The column to get the info for
	 * @param  string $element  The element to return
	 * @return string  The element value
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	private static function getColumnInfoString(Schema $schema, string $table, string $column, string $element): string
	{
		$value = $schema->getColumnInfo($table, $column, $element);

		return is_string($value) ? $value : '';
	}


	/**
	 * Returns a single element of the info for a column as an integer
	 *
	 * @param  Schema $schema   The schema object to get the column info from
	 * @param  string $table    The table the column is part of
	 * @param  string $column   The column to get the info for
	 * @param  string $element  The element to return
	 * @return int  The element value
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	private static function getColumnInfoInt(Schema $schema, string $table, string $column, string $element): int
	{
		$value = $schema->getColumnInfo($table, $column, $element);

		return is_numeric($value) ? (int) $value : 0;
	}


	/**
	 * Converts a value to a string for use in string comparisons
	 *
	 * @param  mixed $value  The value to convert
	 * @return string  The string representation of the value
	 */
	private static function toString(mixed $value): string
	{
		if (is_string($value)) {
			return $value;
		}
		if ($value instanceof Stringable) {
			return (string) $value;
		}
		if (is_scalar($value)) {
			return (string) $value;
		}

		return '';
	}


	/**
	 * Returns a string value from a relationship definition
	 *
	 * @param  array<string, mixed>  $relationship  The relationship definition
	 * @param  string                $key           The relationship value to get
	 * @return string  The string value for the key specified
	 */
	private static function relationshipString(array $relationship, string $key): string
	{
		$value = $relationship[$key] ?? null;

		if (!is_string($value)) {
			throw new ProgrammerException(
				'The relationship between %1$s and %2$s is missing a string value for %3$s',
				$relationship['table'] ?? 'unknown',
				$relationship['related_table'] ?? 'unknown',
				$key
			);
		}

		return $value;
	}


	/**
	 * Runs a single query and returns the Result object
	 *
	 * @param  Database                $db      The database to run the query on
	 * @param  array<array-key, mixed> $params  The SQL statement and the values to escape into it
	 * @return Result  The result of the query
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 */
	private static function runQuery(Database $db, array $params): Result
	{
		$result = call_user_func_array($db->translatedQuery(...), $params);

		if (!$result instanceof Result) {
			throw new ProgrammerException(
				'A single query result was expected, %1$s given',
				get_debug_type($result)
			);
		}

		return $result;
	}


	/**
	 * Coerces a dynamically passed argument into a route string
	 *
	 * Routes are always table names, so any non-string value is treated as
	 * an unspecified route.
	 *
	 * @param  mixed $value  The value passed for a route
	 * @return string|NULL  The route string, or NULL if not a string
	 */
	private static function routeValue(mixed $value): ?string
	{
		return is_string($value) ? $value : null;
	}


	/**
	 * Coerces a dynamically passed argument into an integer
	 *
	 * @param  mixed $value  The value passed for an integer argument
	 * @return int  The integer value
	 */
	private static function intValue(mixed $value): int
	{
		if (is_int($value)) {
			return $value;
		}
		if (is_numeric($value)) {
			return (int) $value;
		}

		return 0;
	}


	/**
	 * Coerces a dynamically passed argument into a set of records
	 *
	 * @param  mixed $value  The value passed for a set of records
	 * @return array<mixed>|RecordSet  The records
	 */
	private static function recordsValue(mixed $value): array|RecordSet
	{
		if ($value instanceof RecordSet || is_array($value)) {
			return $value;
		}

		return array($value);
	}


	/**
	 * Coerces a dynamically passed argument into a record value
	 *
	 * @param  mixed $value  The value passed for a record
	 * @return ActiveRecord|array<mixed>|int|string  The record value
	 */
	private static function recordValue(mixed $value): ActiveRecord|array|int|string
	{
		if ($value instanceof ActiveRecord || is_array($value) || is_int($value) || is_string($value)) {
			return $value;
		}

		if (is_scalar($value) || $value === null) {
			return (string) $value;
		}

		return array();
	}


	/**
	 * Narrows a value passed as a record set
	 *
	 * @param  mixed  $value  The value passed
	 * @param  string $class  The record class the set contains
	 * @return RecordSet  The record set
	 * @throws NotFoundException
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 */
	private static function recordSetValue(mixed $value, string $class): RecordSet
	{
		if ($value instanceof RecordSet) {
			return $value;
		}
		if (is_array($value)) {
			return RecordSet::buildFromArray($class, $value);
		}

		return RecordSet::buildFromArray($class, array($value));
	}


	/**
	 * A data store for caching data related to a record, the structure of this is completely up to the developer using it
	 *
	 * @var array<mixed>
	 */
	protected mixed $cache = array();

	/**
	 * The old values for this record
	 *
	 * Column names are the keys, but a column key will only be present if a
	 * value has changed. The value associated with each key is an array of
	 * old values with the first entry being the oldest value. The static
	 * methods ::assign(), ::changed(), ::hasOld() and ::retrieveOld() are the
	 * best way to interact with this array.
	 *
	 * @var array<mixed>
	 */
	protected mixed $old_values = array();

	/**
	 * Records that are related to the current record via some relationship
	 *
	 * This array is used to cache related records so that a database query
	 * is not required each time related records are accessed. The ORMRelated
	 * class handles most of the interaction with this array.
	 *
	 * @var RelatedRecords
	 */
	protected mixed $related_records = array();

	/**
	 * The values for this record
	 *
	 * This array always contains every column in the database table as a key
	 * with the value being the current value.
	 *
	 * @var RelatedValues
	 */
	protected mixed $values = array();


	/**
	 * Handles all method calls for columns, related records and hook callbacks
	 *
	 * Dynamically handles `get`, `set`, `prepare`, `encode` and `inspect`
	 * methods for each column in this record. Method names are in the form
	 * `verbColumName()`.
	 *
	 * This method also handles `associate`, `build`, `count`, `has`, and `link`
	 * verbs for records in many-to-many relationships; `build`, `count`, `has`
	 * and `populate` verbs for all related records in one-to-many relationships
	 * and `create`, `has` and `populate` verbs for all related records in
	 * one-to-one relationships, and the `create` verb for all related records
	 * in many-to-one relationships.
	 *
	 * Method callbacks registered through ORM::registerActiveRecordMethod()
	 * will be delegated via this method.
	 *
	 * @param  string $method_name  The name of the method called
	 * @param  array<int, mixed> $parameters  The parameters passed
	 * @return mixed  The value returned by the method called
	 * @throws ValidationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 */
	public function __call(mixed $method_name, mixed $parameters)
	{
		$class = get_class($this);

		if (!isset(self::$callback_cache[$class][$method_name])) {
			if (!isset(self::$callback_cache[$class])) {
				self::$callback_cache[$class] = array();
			}
			$callback = ORM::getActiveRecordMethod($class, $method_name);
			self::$callback_cache[$class][$method_name] = is_callable($callback) ? $callback : false;
		}

		if ($callback = self::$callback_cache[$class][$method_name]) {
			if (is_callable($callback)) {
				return call_user_func_array(
					$callback,
					array(
						$this,
						&$this->values,
						&$this->old_values,
						&$this->related_records,
						&$this->cache,
						$method_name,
						$parameters
					)
				);
			}
		}

		if (!isset(self::$method_name_cache[$method_name])) {
			list ($action, $subject) = ORM::parseMethod($method_name);
			if (in_array($action, array('get', 'encode', 'prepare', 'inspect', 'set'))) {
				$subject = Grammar::underscorize($subject);
			} else {
				if (in_array($action, array('build', 'count', 'inject', 'link', 'list', 'tally'))) {
					$singular_subject = Grammar::singularize($subject);
					if ($singular_subject !== false) {
						$subject = $singular_subject;
					}
				}
				$subject = ORM::getRelatedClass($class, $subject);
			}
			self::$method_name_cache[$method_name] = array(
				'action'  => $action,
				'subject' => $subject
			);
		} else {
			$action  = self::$method_name_cache[$method_name]['action'];
			$subject = self::$method_name_cache[$method_name]['subject'];
		}

		switch ($action) {
			// Value methods
			case 'get':
				return $this->get($subject);

			case 'encode':
				if (isset($parameters[0])) {
					return $this->encode($subject, $parameters[0]);
				}
				return $this->encode($subject);

			case 'prepare':
				if (isset($parameters[0])) {
					return $this->prepare($subject, $parameters[0]);
				}
				return $this->prepare($subject);

			case 'inspect':
				if (isset($parameters[0])) {
					return $this->inspect($subject, self::toString($parameters[0]));
				}
				return $this->inspect($subject);

			case 'set':
				if (sizeof($parameters) < 1) {
					throw new ProgrammerException(
						'The method, %s(), requires at least one parameter',
						$method_name
					);
				}
				return $this->set($subject, $parameters[0]);

			// Related data methods
			case 'associate':
				if (sizeof($parameters) < 1) {
					throw new ProgrammerException(
						'The method, %s(), requires at least one parameter',
						$method_name
					);
				}

				$records = self::recordValue($parameters[0]);
				$route   = isset($parameters[1]) ? self::routeValue($parameters[1]) : null;

				list ($subject, $route, $plural) = self::determineSubject($class, $subject, $route);

				if ($plural) {
					ORMRelated::associateRecords($class, $this->related_records, $subject, self::recordsValue($records), $route);
				} else {
					ORMRelated::associateRecord($class, $this->related_records, $subject, $records, $route);
				}
				return $this;

			case 'build':
				if (isset($parameters[0])) {
					return ORMRelated::buildRecords($class, $this->values, $this->related_records, $subject, self::routeValue($parameters[0]));
				}
				return ORMRelated::buildRecords($class, $this->values, $this->related_records, $subject);

			case 'count':
				if (isset($parameters[0])) {
					return ORMRelated::countRecords($class, $this->values, $this->related_records, $subject, self::routeValue($parameters[0]));
				}
				return ORMRelated::countRecords($class, $this->values, $this->related_records, $subject);

			case 'create':
				if (isset($parameters[0])) {
					return ORMRelated::createRecord($class, $this->values, $this->related_records, $subject, self::routeValue($parameters[0]));
				}
				return ORMRelated::createRecord($class, $this->values, $this->related_records, $subject);

			case 'has':
				$route = isset($parameters[0]) ? self::routeValue($parameters[0]) : null;

				list ($subject, $route, ) = self::determineSubject($class, $subject, $route);

				return ORMRelated::hasRecords($class, $this->values, $this->related_records, $subject, $route);

			case 'inject':
				if (sizeof($parameters) < 1) {
					throw new ProgrammerException(
						'The method, %s(), requires at least one parameter',
						$method_name
					);
				}

				if (isset($parameters[1])) {
					ORMRelated::setRecordSet($class, $this->related_records, $subject, self::recordSetValue($parameters[0], $subject), self::routeValue($parameters[1]));
				} else {
					ORMRelated::setRecordSet($class, $this->related_records, $subject, self::recordSetValue($parameters[0], $subject));
				}
				return null;

			case 'link':
				if (isset($parameters[0])) {
					ORMRelated::linkRecords($class, $this->related_records, $subject, self::routeValue($parameters[0]));
				} else {
					ORMRelated::linkRecords($class, $this->related_records, $subject);
				}
				return $this;

			case 'list':
				if (isset($parameters[0])) {
					return ORMRelated::getPrimaryKeys($class, $this->values, $this->related_records, $subject, self::routeValue($parameters[0]));
				}
				return ORMRelated::getPrimaryKeys($class, $this->values, $this->related_records, $subject);

			case 'populate':
				$route = isset($parameters[0]) ? $parameters[0] : null;
				$recursive = false;
				if (is_bool($route)) {
					$recursive = $route;
					$route = isset($parameters[1]) ? $parameters[1] : null;
				}
				$route = self::routeValue($route);

				list ($subject, $route, ) = self::determineSubject($class, $subject, $route);

				ORMRelated::populateRecords($class, $this->related_records, $subject, $route, $recursive);
				return $this;

			case 'tally':
				if (sizeof($parameters) < 1) {
					throw new ProgrammerException(
						'The method, %s(), requires at least one parameter',
						$method_name
					);
				}

				if (isset($parameters[1])) {
					ORMRelated::setCount($class, $this->related_records, $subject, self::intValue($parameters[0]), self::routeValue($parameters[1]));
				} else {
					ORMRelated::setCount($class, $this->related_records, $subject, self::intValue($parameters[0]));
				}
				return null;

			// Error handler
			default:
				throw new ProgrammerException(
					'Unknown method, %s(), called',
					$method_name
				);
		}
	}


	/**
	 * Creates a clone of a record
	 *
	 * If the record has an auto incrementing primary key, the primary key will
	 * be erased in the clone. If the primary key is not auto incrementing,
	 * the primary key will be left as-is in the clone. In either situation the
	 * clone will return `FALSE` from the ::exists() method until ::store() is
	 * called.
	 *
	 * @internal
	 *
	 * @return ActiveRecord
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	public function __clone()
	{
		$class = get_class($this);

		// Copy values and cache, making sure objects are cloned to prevent reference issues
		$temp_values  = $this->values;
		$new_values   = array();
		$this->values =& $new_values;
		foreach ($temp_values as $column => $value) {
			if (!is_string($column)) {
				continue;
			}
			$this->values[$column] = ORM::replicate($class, $column, $value);
		}

		$temp_cache  = $this->cache;
		$new_cache   = array();
		$this->cache =& $new_cache;
		foreach ($temp_cache as $key => $value) {
			if (is_object($value)) {
				$this->cache[$key] = clone $value;
			} else {
				$this->cache[$key] = $value;
			}
		}

		// Related records are purged
		$new_related_records   = array();
		$this->related_records =& $new_related_records;

		// Old values are changed to look like the record is non-existant
		$new_old_values   = array();
		$this->old_values =& $new_old_values;

		foreach (array_keys($this->values) as $key) {
			$this->old_values[$key] = array(null);
		}

		// If we have a single auto incrementing primary key, remove the value
		$schema     = ORMSchema::retrieve($class);
		$table      = ORM::tablize($class);
		$pk_columns = $schema->getKeys($table, 'primary');

		if (sizeof($pk_columns) == 1 && $schema->getColumnInfo($table, $pk_columns[0], 'auto_increment')) {
			$this->values[$pk_columns[0]] = null;
			unset($this->old_values[$pk_columns[0]]);
		}
	}


	/**
	 * Creates a new record or loads one from the database - if a primary key or unique key is provided the record will be loaded
	 *
	 * @throws NotFoundException  When the record specified by `$key` can not be found in the database
	 *
	 * @param  mixed $key  The primary key or unique key value(s) - single column primary keys will accept a scalar value, all others must be an associative array of `(string) {column} => (mixed) {value}`
	 * @return ActiveRecord
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 */
	public function __construct(mixed $key = null)
	{
		$class  = get_class($this);
		$schema = ORMSchema::retrieve($class);

		// If the features of this class haven't been set yet, do it
		if (!isset(self::$configured[$class])) {
			self::$configured[$class] = true;
			$this->configure();

			$table = ORM::tablize($class);
			if (!$schema->getKeys($table, 'primary')) {
				throw new ProgrammerException(
					'The database table %1$s (being modelled by the class %2$s) does not appear to have a primary key defined. %3$s and %4$s will not work properly without a primary key.',
					$table,
					$class,
					'ActiveRecord',
					'RecordSet'
				);
			}

			// If the configuration was forced, prevent the post::__construct() hook from
			// being triggered since it is not really a real record instantiation
			$trace = array_slice(debug_backtrace(), 0, 2);

			$is_forced = sizeof($trace) == 2
				&& $trace[1]['function'] == 'forceConfigure'
				&& isset($trace[1]['class'], $trace[1]['type'])
				&& $trace[1]['type'] == '::'
				&& in_array($trace[1]['class'], array('ActiveRecord', $class));

			if ($is_forced) {
				return;
			}
		}

		if (!isset(self::$callback_cache[$class]['__construct'])) {
			if (!isset(self::$callback_cache[$class])) {
				self::$callback_cache[$class] = array();
			}
			$callback = ORM::getActiveRecordMethod($class, '__construct');
			self::$callback_cache[$class]['__construct'] = $callback ? $callback : false;
		}
		if ($callback = self::$callback_cache[$class]['__construct']) {
			$this->__call('__construct', array());
		}

		// Handle loading by a result object passed via the RecordSet class
		if ($key instanceof Iterator) {
			$this->loadFromResult($key);

		// Handle loading an object from the database
		} elseif ($key !== null) {
			$table      = ORM::tablize($class);
			$pk_columns = $schema->getKeys($table, 'primary');

			// If the primary key does not look properly formatted, check to see if it is a UNIQUE key
			$is_unique_key = false;
			if (is_array($key) && (sizeof($pk_columns) == 1 || array_diff(array_keys($key), $pk_columns))) {
				$unique_keys = $schema->getKeys($table, 'unique');
				$key_keys    = array_keys($key);
				foreach ($unique_keys as $unique_key) {
					if (!array_diff($key_keys, $unique_key)) {
						$is_unique_key = true;
					}
				}
			}

			$wrong_keys = is_array($key) && (count($key) != count($pk_columns) || array_diff(array_keys($key), $pk_columns));
			$wrong_type = !is_array($key) && (sizeof($pk_columns) != 1 || !is_scalar($key));

			// If we didn't find a UNIQUE key and primary key doesn't look right we fail
			if (!$is_unique_key && ($wrong_keys || $wrong_type)) {
				throw new ProgrammerException(
					'An invalidly formatted primary or unique key was passed to this %s object',
					ORM::getRecordName($class)
				);
			}

			if ($is_unique_key) {
				if (!is_array($key)) {
					throw new ProgrammerException(
						'An invalidly formatted unique key was passed to this %s object',
						ORM::getRecordName($class)
					);
				}

				$result = $this->fetchResultFromUniqueKey($key);
				$this->loadFromResult($result);
			} else {
				$hash = self::hash($key, $class);
				if (!is_string($hash)) {
					throw new ProgrammerException(
						'Could not determine the hash of the %s being loaded',
						ORM::getRecordName($class)
					);
				}

				$row = is_array($key) ? $key : array($pk_columns[0] => $key);
				if (!$this->loadFromIdentityMap($row, $hash)) {
					// Assign the primary key values for loading
					if (is_array($key)) {
						foreach ($pk_columns as $pk_column) {
							$this->values[$pk_column] = $key[$pk_column];
						}
					} else {
						$this->values[$pk_columns[0]] = $key;
					}

					$this->load();
				}
			}

		// Create an empty array for new objects
		} else {
			$column_info = self::getColumnInfo($schema, ORM::tablize($class));
			foreach ($column_info as $column => $info) {
				$this->values[$column] = null;
				if ($info['default'] !== null) {
					self::assign(
						$this->values,
						$this->old_values,
						$column,
						ORM::objectify($class, $column, $info['default'])
					);
				}
			}
		}

		ORM::callHookCallbacks(
			$this,
			'post::__construct()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->cache
		);
	}


	/**
	 * All requests that hit this method should be requests for callbacks
	 *
	 * @internal
	 *
	 * @param  string $method  The method to create a callback for
	 * @return array{0: ActiveRecord, 1: string}  The callback for the method requested
	 */
	public function __get(string $method)
	{
		return array($this, $method);
	}


	/**
	 * Configure itself when coming out of the session. Records from the session are NOT hooked into the identity map.
	 *
	 * @internal
	 *
	 * @return void
	 */
	public function __wakeup()
	{
		$class = get_class($this);

		if (!isset(self::$configured[$class])) {
			$this->configure();
			self::$configured[$class] = true;
		}
	}


	/**
	 * Allows the programmer to set features for the class
	 *
	 * This method is only called once per page load for each class.
	 *
	 * @return void
	 */
	protected function configure(): void
	{
	}


	/**
	 * Creates the Database::translatedQuery() insert statement params
	 *
	 * @return array<int, mixed>  The parameters for an Database::translatedQuery() SQL insert statement
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	protected function constructInsertParams(): array
	{
		$columns = array();
		$values  = array();

		$column_placeholders = array();
		$value_placeholders  = array();

		$class       = get_class($this);
		$schema      = ORMSchema::retrieve($class);
		$table       = ORM::tablize($class);
		$column_info = self::getColumnInfo($schema, $table);
		foreach ($column_info as $column => $info) {
			if ($schema->getColumnInfo($table, $column, 'auto_increment') && $schema->getColumnInfo($table, $column, 'not_null') && $this->values[$column] === null) {
				continue;
			}

			$value = ORM::scalarize($class, $column, $this->values[$column]);
			if ($value === null && $info['not_null'] && $info['default'] !== null) {
				$value = $info['default'];
			}

			$columns[] = $column;
			$values[]  = $value;

			$column_placeholders[] = '%r';
			$placeholder = $info['placeholder'] ?? null;
			$value_placeholders[]  = is_string($placeholder) ? $placeholder : '';
		}

		$sql    = 'INSERT INTO %r (' . join(', ', $column_placeholders) . ') VALUES (' . join(', ', $value_placeholders) . ')';
		$params = array($sql, $table);
		$params = array_merge($params, $columns);
		$params = array_merge($params, $values);

		return $params;
	}


	/**
	 * Creates the Database::translatedQuery() update statement params
	 *
	 * @return array<array-key, mixed>  The parameters for an Database::translatedQuery() SQL update statement
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	protected function constructUpdateParams(): array
	{
		$class       = get_class($this);
		$schema      = ORMSchema::retrieve($class);

		$table       = ORM::tablize($class);
		$column_info = self::getColumnInfo($schema, $table);

		$assignments = array();
		$params      = array($table);

		foreach ($column_info as $column => $info) {
			if ($info['auto_increment'] && !ActiveRecord::changed($this->values, $this->old_values, $column) && count($column_info) > 1) {
				continue;
			}

			$assignments[] = '%r = ' . (is_string($info['placeholder'] ?? null) ? $info['placeholder'] : '');

			$value = ORM::scalarize($class, $column, $this->values[$column]);
			if ($value === null && $info['not_null'] && $info['default'] !== null) {
				$value = $info['default'];
			}

			$params[] = $column;
			$params[] = $value;
		}

		$sql = 'UPDATE %r SET ' . join(', ', $assignments) . ' WHERE ';
		array_unshift($params, $sql);

		return ORMDatabase::addPrimaryKeyWhereParams($schema, $params, $table, $table, $this->values, $this->old_values);
	}


	/**
	 * Deletes a record from the database, but does not destroy the object
	 *
	 * This method will start a database transaction if one is not already active.
	 *
	 * @param  boolean $force_cascade  When TRUE, this will cause all child objects to be deleted, even if the ON DELETE clause is RESTRICT or NO ACTION
	 * @return ActiveRecord|null  The record object, to allow for method chaining
	 * @throws ValidationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 */
	public function delete(bool $force_cascade = false): mixed
	{
		// This flag prevents recursive relationships, such as one-to-one
		// relationships, from creating infinite loops
		if (!empty($this->cache['ActiveRecord::delete()::being_deleted'])) {
			return null;
		}

		$class = get_class($this);

		if (ORM::getActiveRecordMethod($class, 'delete')) {
			$registered_delete = $this->__call('delete', array());
			return $registered_delete instanceof ActiveRecord ? $registered_delete : $this;
		}

		if (!$this->exists()) {
			throw new ProgrammerException(
				'This %s object does not yet exist in the database, and thus can not be deleted',
				ORM::getRecordName($class)
			);
		}

		$db     = ORMDatabase::retrieve($class, 'write');
		$schema = ORMSchema::retrieve($class);

		ORM::callHookCallbacks(
			$this,
			'pre::delete()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->cache
		);

		$table = ORM::tablize($class);

		$inside_db_transaction = $db->isInsideTransaction();

		try {
			if (!$inside_db_transaction) {
				$db->translatedQuery('BEGIN');
			}

			ORM::callHookCallbacks(
				$this,
				'post-begin::delete()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->cache
			);

			// Check to ensure no foreign dependencies prevent deletion
			$one_to_one_relationships   = $schema->getRelationships($table, 'one-to-one');
			$one_to_many_relationships  = $schema->getRelationships($table, 'one-to-many');
			$many_to_many_relationships = $schema->getRelationships($table, 'many-to-many');

			$relationships = array_merge($one_to_one_relationships, $one_to_many_relationships, $many_to_many_relationships);
			$records_sets_to_delete = array();

			$restriction_messages = array();

			$this->cache['ActiveRecord::delete()::being_deleted'] = true;

			foreach ($relationships as $relationship) {
				// Figure out how to check for related records
				if (isset($relationship['join_table'])) {
					$type = 'many-to-many';
				} else {
					$type = in_array($relationship, $one_to_one_relationships) ? 'one-to-one' : 'one-to-many';
				}
				$route = ORMSchema::getRouteNameFromRelationship($type, $relationship);

				$related_class = ORM::classize(self::relationshipString($relationship, 'related_table'));

				if ($type == 'one-to-one') {
					$method         = 'create' . $related_class;
					$related_record = $this->$method($route);
					if (!$related_record instanceof ActiveRecord || !$related_record->exists()) {
						continue;
					}
				} else {
					$method     = 'build' . Grammar::pluralize($related_class);
					$record_set = $this->$method($route);
					if (!$record_set instanceof RecordSet || !$record_set->count()) {
						continue;
					}

					if ($type == 'one-to-many' && self::relationshipString($relationship, 'on_delete') == 'cascade') {
						$records_sets_to_delete[] = $record_set;
					}
				}

				// If we are focing the cascade we have to delete child records and join table entries before this record
				if ($force_cascade) {
					if ($type == 'one-to-one') {
						$related_record->delete($force_cascade);

					// For one-to-many we explicitly delete all of the records
					} elseif ($type == 'one-to-many') {
						foreach ($record_set as $record) {
							if ($record->exists()) {
								$record->delete($force_cascade);
							}
						}

					// For many-to-many relationships we explicitly delete the join table entries
					} elseif ($type == 'many-to-many') {
						$join_table  = self::relationshipString($relationship, 'join_table');
						$join_column = self::relationshipString($relationship, 'join_column');
						$join_column_placeholder = $schema->getColumnInfo($join_table, $join_column, 'placeholder');
						$column_get_method       = 'get' . Grammar::camelize(self::relationshipString($relationship, 'column'), true);

						$sql = $db->escape(
							'DELETE FROM %r WHERE %r = ',
							$join_table,
							$join_column
						);
						$sql = is_string($sql) ? $sql : '';
						$sql = $sql . (is_string($join_column_placeholder) ? $join_column_placeholder : '');

						self::runQuery($db, array($sql, $this->$column_get_method()));
					}

				// Otherwise we have a restriction and we can to create a nice error message for the user
				} elseif ($relationship['on_delete'] == 'restrict' || $relationship['on_delete'] == 'no_action') {
					$related_class_name  = ORM::classize(self::relationshipString($relationship, 'related_table'));
					$related_class_name  = ORM::getRelatedClass($class, $related_class_name);
					$related_record_name = ORM::getRecordName($related_class_name);

					if ($type == 'one-to-one') {
						$restriction_messages[] = self::compose("A %s references it", $related_record_name);
					} else {
						$related_record_name = Grammar::pluralize($related_record_name);
						$restriction_messages[] = self::compose("One or more %s references it", $related_record_name);
					}
				}
			}

			if ($restriction_messages) {
				throw new ValidationException(
					self::compose('This %s can not be deleted because:', ORM::getRecordName($class)),
					$restriction_messages
				);
			}


			// Delete this record
			$params = array('DELETE FROM %r WHERE ', $table);
			$params = ORMDatabase::addPrimaryKeyWhereParams($schema, $params, $table, $table, $this->values, $this->old_values);

			$result = self::runQuery($db, $params);


			// Delete related records to ensure any PHP-level cleanup is done
			foreach ($records_sets_to_delete as $record_set) {
				foreach ($record_set as $record) {
					if ($record->exists()) {
						$record->delete($force_cascade);
					}
				}
			}

			unset($this->cache['ActiveRecord::delete()::being_deleted']);

			ORM::callHookCallbacks(
				$this,
				'pre-commit::delete()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->cache
			);

			if (!$inside_db_transaction) {
				$db->translatedQuery('COMMIT');
			}

			ORM::callHookCallbacks(
				$this,
				'post-commit::delete()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->cache
			);
		} catch (FlourishException $e) {
			if (!$inside_db_transaction) {
				$db->translatedQuery('ROLLBACK');
			}

			ORM::callHookCallbacks(
				$this,
				'post-rollback::delete()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->cache
			);

			// Check to see if the validation exception came from a related record, and fix the message
			if ($e instanceof ValidationException) {
				$message = $e->getMessage();
				$search  = self::compose('This %s can not be deleted because:', ORM::getRecordName($class));
				if (stripos($message, $search) === false) {
					$regex       = self::compose('This %s can not be deleted because:', '__');
					$regex_parts = explode('__', $regex);
					$regex       = '#(' . preg_quote($regex_parts[0], '#') . ').*?(' . preg_quote($regex_parts[0], '#') . ')#';

					$message = preg_replace($regex, '\1' . strtr(ORM::getRecordName($class), array('\\' => '\\\\', '$' => '\\$')) . '\2', $message);

					$find          = self::compose("One or more %s references it", '__');
					$find_parts    = explode('__', $find);
					$find_regex    = '#' . preg_quote($find_parts[0], '#') . '(.*?)' . preg_quote($find_parts[1], '#') . '#';

					$replace       = self::compose("One or more %s indirectly references it", '__');
					$replace_parts = explode('__', $replace);
					$replace_regex = strtr($replace_parts[0], array('\\' => '\\\\', '$' => '\\$')) . '\1' . strtr($replace_parts[1], array('\\' => '\\\\', '$' => '\\$'));

					$message = preg_replace($find_regex, $replace_regex, $regex);
					throw new ValidationException(is_string($message) ? $message : '');
				}
			}

			throw $e;
		}

		ORM::callHookCallbacks(
			$this,
			'post::delete()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->cache
		);

		// If we just deleted an object that has an auto-incrementing primary key,
		// lets delete that value from the object since it is no longer valid
		$pk_columns  = $schema->getKeys($table, 'primary');
		if (sizeof($pk_columns) == 1 && $schema->getColumnInfo($table, $pk_columns[0], 'auto_increment')) {
			$this->values[$pk_columns[0]] = null;
			unset($this->old_values[$pk_columns[0]]);
		}

		return $this;
	}


	/**
	 * Retrieves a value from the record and prepares it for output into an HTML form element.
	 *
	 * Below are the transformations performed:
	 *
	 *  - **varchar, char, text**: will run through HTML::encode(), if `TRUE` is passed the text will be run through HTML::convertNewLinks() and HTML::makeLinks()
	 *  - **float**: takes 1 parameter to specify the number of decimal places
	 *  - **date, time, timestamp**: `format()` will be called on the Date/Time/Timestamp object with the 1 parameter specified
	 *  - **objects**: the object will be converted to a string by `__toString()` or a `(string)` cast and then will be run through HTML::encode()
	 *  - **all other data types**: the value will be run through HTML::encode()
	 *
	 * @param  string $column      The name of the column to retrieve
	 * @param  mixed  $formatting  The formatting string
	 * @return string  The encoded value for the column specified
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	protected function encode(string $column, mixed $formatting = null): string
	{
		$column_exists = array_key_exists($column, $this->values);
		$method_name   = 'get' . Grammar::camelize($column, true);
		$method_exists = method_exists($this, $method_name);

		if (!$column_exists && !$method_exists) {
			throw new ProgrammerException(
				'The column specified, %s, does not exist',
				$column
			);
		}

		$class        = get_class($this);
		$schema       = ORMSchema::retrieve($class);
		$table        = ORM::tablize($class);

		if ($column_exists) {
			$column_type = self::getColumnInfoString($schema, $table, $column, 'type');

			// Ensure the programmer is calling the function properly
			if ($column_type == 'blob') {
				throw new ProgrammerException(
					'The column specified, %s, does not support forming because it is a blob column',
					$column
				);
			}

			if ($formatting !== null && in_array($column_type, array('boolean', 'integer'))) {
				throw new ProgrammerException(
					'The column specified, %s, does not support any formatting options',
					$column
				);
			}

		// If the column doesn't exist, we are just pulling the
		// value from a get method, so treat it as text
		} else {
			$column_type = 'text';
		}

		// Grab the value for empty value checking
		$value = $this->$method_name();

		// Date/time objects
		if (in_array($column_type, array('date', 'time', 'timestamp')) && ($value instanceof Date || $value instanceof Time || $value instanceof Timestamp)) {
			if ($formatting === null) {
				throw new ProgrammerException(
					'The column specified, %s, requires one formatting parameter, a valid date() formatting string',
					$column
				);
			}
			$value = $value->format(is_string($formatting) ? $formatting : '');
		}

		// Other objects
		if (is_object($value) && is_callable(array($value, '__toString'))) {
			$value = $value->__toString();
		} elseif (is_object($value)) {
			$value = self::toString($value);
		}

		// Make sure we don't mangle a non-float value
		if ($column_type == 'float' && is_numeric($value)) {
			// If the user passed in a formatting value, use it
			if ($formatting !== null && is_numeric($formatting)) {
				$decimal_places = (int) $formatting;

			// If the column has a pre-defined number of decimal places, use that
			} else {
				$decimal_places = self::getColumnInfoInt($schema, $table, $column, 'decimal_places');
			}

			return number_format((float) $value, $decimal_places, '.', '');
		}

		// Turn line-breaks into breaks for text fields and add links
		if ($formatting === true && in_array($column_type, array('varchar', 'char', 'text'))) {
			return HTML::makeLinks(HTML::convertNewlines(HTML::encode(self::toString($value))));
		}

		// Anything that has gotten to here is a string value or is not the proper data type for the column that contains it
		return HTML::encode(self::toString($value));
	}


	/**
	 * Checks to see if the record exists in the database
	 *
	 * @return boolean  If the record exists in the database
	 * @throws ValidationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 */
	public function exists(): bool
	{
		$class = get_class($this);

		if (ORM::getActiveRecordMethod($class, 'exists')) {
			return (bool) $this->__call('exists', array());
		}

		$schema     = ORMSchema::retrieve($class);
		$table      = ORM::tablize($class);
		$pk_columns = $schema->getKeys($table, 'primary');
		$exists     = false;

		foreach ($pk_columns as $pk_column) {
			$has_old = self::hasOld($this->old_values, $pk_column);
			if (($has_old && self::retrieveOld($this->old_values, $pk_column) !== null) || (!$has_old && $this->values[$pk_column] !== null)) {
				$exists = true;
			}
		}

		return $exists;
	}


	/**
	 * Loads a record from the database based on a UNIQUE key
	 *
	 * @throws NotFoundException
	 *
	 * @param  array<int|string, mixed> $values  The UNIQUE key values to try and load with
	 * @return Result  The result of the query loading the record
	 */
	protected function fetchResultFromUniqueKey(array $values): Result
	{
		$class = get_class($this);

		$db     = ORMDatabase::retrieve($class, 'read');
		$schema = ORMSchema::retrieve($class);

		try {
			if ($values === array_combine(array_keys($values), array_fill(0, sizeof($values), null))) {
				throw new ExpectedException('The values specified for the unique key are all NULL');
			}

			$table  = ORM::tablize($class);
			$params = array('SELECT * FROM %r WHERE ', $table);

			$column_info = self::getColumnInfo($schema, $table);

			$conditions = array();
			foreach ($values as $column => $value) {
				$column = (string) $column;

				// This makes sure the query performs the way an insert will
				if ($value === null && $column_info[$column]['not_null'] && $column_info[$column]['default'] !== null) {
					$value = $column_info[$column]['default'];
				}

				$conditions[] = ORMDatabase::makeCondition($schema, $table, $column, '=', $value);
				$params[] = $column;
				$params[] = $value;
			}

			$params[0] = (is_string($params[0]) ? $params[0] : '') . join(' AND ', $conditions);

			$result = self::runQuery($db, $params);
			$result->tossIfNoRows();
		} catch (ExpectedException $e) {
			throw new NotFoundException(
				'The %s requested could not be found',
				ORM::getRecordName($class)
			);
		}

		return $result;
	}


	/**
	 * Retrieves a value from the record
	 *
	 * @param  string $column  The name of the column to retrieve
	 * @return mixed  The value for the column specified
	 */
	protected function get(string $column): mixed
	{
		if (!isset($this->values[$column]) && !array_key_exists($column, $this->values)) {
			throw new ProgrammerException(
				'The column specified, %s, does not exist',
				$column
			);
		}
		return $this->values[$column];
	}


	/**
	 * Retrieves information about a column
	 *
	 * @param  string $column   The name of the column to inspect
	 * @param  string $element  The metadata element to retrieve
	 * @return mixed  The metadata array for the column, or the metadata element specified
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	protected function inspect(string $column, ?string $element = null): mixed
	{
		if (!array_key_exists($column, $this->values)) {
			throw new ProgrammerException(
				'The column specified, %s, does not exist',
				$column
			);
		}

		$class  = get_class($this);
		$table  = ORM::tablize($class);
		$schema = ORMSchema::retrieve($class);
		$info   = self::getColumnInfoForColumn($schema, $table, $column);

		if (!in_array($info['type'], array('varchar', 'char', 'text'))) {
			unset($info['valid_values']);
			unset($info['max_length']);
		}

		if ($info['type'] != 'float') {
			unset($info['decimal_places']);
		}

		if ($info['type'] != 'integer') {
			unset($info['auto_increment']);
		}

		if (!in_array($info['type'], array('integer', 'float'))) {
			unset($info['min_value']);
			unset($info['max_value']);
		}

		$info['feature'] = null;

		ORM::callInspectCallbacks(get_class($this), $column, $info);

		if ($element) {
			if (!isset($info[$element]) && !array_key_exists($element, $info)) {
				throw new ProgrammerException(
					'The element specified, %1$s, is invalid. Must be one of: %2$s.',
					$element,
					join(', ', array_keys($info))
				);
			}
			return $info[$element];
		}

		return $info;
	}


	/**
	 * Loads a record from the database
	 *
	 * @throws NotFoundException  When the record could not be found in the database
	 *
	 * @return ActiveRecord  The record object, to allow for method chaining
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 */
	public function load(): ActiveRecord
	{
		$class  = get_class($this);
		$db     = ORMDatabase::retrieve($class, 'read');
		$schema = ORMSchema::retrieve($class);

		if (ORM::getActiveRecordMethod($class, 'load')) {
			$loaded = $this->__call('load', array());
			return $loaded instanceof ActiveRecord ? $loaded : $this;
		}

		try {
			$table = ORM::tablize($class);
			$params = array('SELECT * FROM %r WHERE ', $table);
			$params = ORMDatabase::addPrimaryKeyWhereParams($schema, $params, $table, $table, $this->values, $this->old_values);

			$result = self::runQuery($db, $params);
			$result->tossIfNoRows();
		} catch (ExpectedException $e) {
			throw new NotFoundException(
				'The %s requested could not be found',
				ORM::getRecordName($class)
			);
		}

		$this->loadFromResult($result, true);

		// Clears the cached related records so they get pulled from the database
		$this->related_records = array();

		return $this;
	}


	/**
	 * Loads a record from the database directly from a result object
	 *
	 * @param  Iterator $result               The result object to use for loading the current object
	 * @param  boolean  $ignore_identity_map  If the identity map should be ignored and the values loaded no matter what
	 * @return boolean  If the record was loaded from the identity map
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws ValidationException
	 * @throws NotFoundException
	 * @throws AuthorizationException
	 */
	protected function loadFromResult(Iterator $result, bool $ignore_identity_map = false): bool
	{
		$class  = get_class($this);
		$table  = ORM::tablize($class);
		$row    = $result->current();

		if (!is_array($row)) {
			throw new ProgrammerException(
				'The result object did not contain a row of data to load'
			);
		}

		$db     = ORMDatabase::retrieve($class, 'read');
		$schema = ORMSchema::retrieve($class);

		if (!isset(self::$unescape_map[$class])) {
			self::$unescape_map[$class] = array();
			$column_info                = self::getColumnInfo($schema, $table);

			foreach ($column_info as $column => $info) {
				if (in_array($info['type'], array('blob', 'boolean', 'date', 'time', 'timestamp')) && is_string($info['type'])) {
					self::$unescape_map[$class][$column] = $info['type'];
				}
			}
		}

		$pk_columns = $schema->getKeys($table, 'primary');
		foreach ($pk_columns as $column) {
			$value = isset($row[$column]) ? $row[$column] : null;
			if ($value !== null && isset(self::$unescape_map[$class][$column])) {
				$value = $db->unescape(self::$unescape_map[$class][$column], $value);
			}

			$this->values[$column] = ORM::objectify($class, $column, $value);
			unset($row[$column]);
		}

		$hash = self::hash($this->values, $class);
		if (!is_string($hash)) {
			throw new ProgrammerException(
				'Could not determine the hash of the record being loaded'
			);
		}
		if (!$ignore_identity_map && $this->loadFromIdentityMap($this->values, $hash)) {
			return true;
		}

		foreach ($row as $column => $value) {
			if (!is_string($column)) {
				continue;
			}
			if ($value !== null && isset(self::$unescape_map[$class][$column])) {
				$value = $db->unescape(self::$unescape_map[$class][$column], $value);
			}

			$this->values[$column] = ORM::objectify($class, $column, $value);
		}

		// Save this object to the identity map
		if (!isset(self::$identity_map[$class])) {
			self::$identity_map[$class] = array();
		}
		self::$identity_map[$class][$hash] = $this;

		ORM::callHookCallbacks(
			$this,
			'post::loadFromResult()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->cache
		);

		return false;
	}


	/**
	 * Tries to load the object (via references to class vars) from the ORM identity map
	 *
	 * @param  array<int|string, mixed>  $row   The data source for the primary key values
	 * @param  string $hash  The unique hash for this record
	 * @return boolean  If the load was successful
	 */
	protected function loadFromIdentityMap(array $row, string $hash): bool
	{
		$class = get_class($this);

		if (!isset(self::$identity_map[$class])) {
			return false;
		}

		if (!isset(self::$identity_map[$class][$hash])) {
			return false;
		}

		$object = self::$identity_map[$class][$hash];

		// If we got a result back, it is the object we are creating
		$this->cache           = &$object->cache;
		$this->values          = &$object->values;
		$this->old_values      = &$object->old_values;
		$this->related_records = &$object->related_records;

		ORM::callHookCallbacks(
			$this,
			'post::loadFromIdentityMap()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->cache
		);

		return true;
	}


	/**
	 * Sets the values for this record by getting values from the request through the Request class
	 *
	 * @param  boolean $recursive  If all one-to-many tables and one-to-one relationships should be populated
	 * @return ActiveRecord  The record object, to allow for method chaining
	 * @throws ValidationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 */
	public function populate(mixed $recursive = false): mixed
	{
		$class = get_class($this);

		if (ORM::getActiveRecordMethod($class, 'populate')) {
			$result = $this->__call('populate', array());
			if (!$result instanceof ActiveRecord) {
				throw new ProgrammerException(
					'The %s::populate() method must return an instance of ActiveRecord, %s returned',
					$class,
					get_debug_type($result)
				);
			}
			return $result;
		}

		ORM::callHookCallbacks(
			$this,
			'pre::populate()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->cache
		);

		$schema = ORMSchema::retrieve($class);
		$table  = ORM::tablize($class);

		$column_info = self::getColumnInfo($schema, $table);
		foreach ($column_info as $column => $info) {
			if (Request::check($column)) {
				$method = 'set' . Grammar::camelize($column, true);
				$cast_to = ($info['type'] == 'blob') ? 'binary' : null;
				$this->$method(Request::get($column, $cast_to));
			}
		}

		ORM::callHookCallbacks(
			$this,
			'post::populate()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->cache
		);

		if ($recursive) {
			$one_to_many_relationships = $schema->getRelationships($table, 'one-to-many');
			foreach ($one_to_many_relationships as $relationship) {
				$route_name = ORMSchema::getRouteNameFromRelationship('one-to-many', $relationship);
				$related_class = ORM::classize(self::relationshipString($relationship, 'related_table'));
				$method = 'populate' . Grammar::pluralize($related_class);
				$this->$method(true, $route_name);
			}

			$one_to_one_relationships = $schema->getRelationships($table, 'one-to-one');
			foreach ($one_to_one_relationships as $relationship) {
				$route_name = ORMSchema::getRouteNameFromRelationship('one-to-one', $relationship);
				$related_class = ORM::classize(self::relationshipString($relationship, 'related_table'));
				$this->__call('populate' . $related_class, array(true, $route_name));
			}
		}

		return $this;
	}


	/**
	 * Retrieves a value from the record and prepares it for output into html.
	 *
	 * Below are the transformations performed:
	 *
	 *  - **varchar, char, text**: will run through HTML::prepare(), if `TRUE` is passed the text will be run through HTML::convertNewLinks() and HTML::makeLinks()
	 *  - **boolean**: will return `'Yes'` or `'No'`
	 *  - **integer**: will add thousands/millions/etc. separators
	 *  - **float**: will add thousands/millions/etc. separators and takes 1 parameter to specify the number of decimal places
	 *  - **date, time, timestamp**: `format()` will be called on the Date/Time/Timestamp object with the 1 parameter specified
	 *  - **objects**: the object will be converted to a string by `__toString()` or a `(string)` cast and then will be run through HTML::prepare()
	 *
	 * @param  string $column      The name of the column to retrieve
	 * @param  mixed  $formatting  The formatting parameter, if applicable
	 * @return string  The formatted value for the column specified
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	protected function prepare(string $column, mixed $formatting = null): string
	{
		$column_exists = array_key_exists($column, $this->values);
		$method_name   = 'get' . Grammar::camelize($column, true);
		$method_exists = method_exists($this, $method_name);

		if (!$column_exists && !$method_exists) {
			throw new ProgrammerException(
				'The column specified, %s, does not exist',
				$column
			);
		}

		if ($column_exists) {
			$class  = get_class($this);
			$table  = ORM::tablize($class);
			$schema = ORMSchema::retrieve($class);

			$column_info = self::getColumnInfoForColumn($schema, $table, $column);
			$column_type = $column_info['type'];

			// Ensure the programmer is calling the function properly
			if ($column_type == 'blob') {
				throw new ProgrammerException(
					'The column specified, %s, can not be prepared because it is a blob column',
					$column
				);
			}

			if ($formatting !== null && in_array($column_type, array('integer', 'boolean'))) {
				throw new ProgrammerException(
					'The column specified, %s, does not support any formatting options',
					$column
				);
			}

		// If the column doesn't exist, we are just pulling the
		// value from a get method, so treat it as text
		} else {
			$column_type = 'text';
			$column_info = array();
		}

		// Grab the value for empty value checking
		$value = $this->$method_name();

		// Date/time objects
		if (in_array($column_type, array('date', 'time', 'timestamp')) && ($value instanceof Date || $value instanceof Time || $value instanceof Timestamp)) {
			if ($formatting === null) {
				throw new ProgrammerException(
					'The column specified, %s, requires one formatting parameter, a valid date() formatting string',
					$column
				);
			}
			return $value->format(is_string($formatting) ? $formatting : '');
		}

		// Other objects
		if (is_object($value) && is_callable(array($value, '__toString'))) {
			$value = $value->__toString();
		} elseif (is_object($value)) {
			$value = self::toString($value);
		}

		// Ensure the value matches the data type specified to prevent mangling
		if ($column_type == 'boolean' && is_bool($value)) {
			return ($value) ? 'Yes' : 'No';
		}

		if ($column_type == 'integer' && is_numeric($value)) {
			return number_format((float) $value, 0, '', ',');
		}

		if ($column_type == 'float' && is_numeric($value)) {
			// If the user passed in a formatting value, use it
			if ($formatting !== null && is_numeric($formatting)) {
				$decimal_places = (int) $formatting;

			// If the column has a pre-defined number of decimal places, use that
			} elseif (isset($column_info['decimal_places']) && is_numeric($column_info['decimal_places'])) {
				$decimal_places = (int) $column_info['decimal_places'];

			// This figures out how many decimal places are part of the current value
			} else {
				$value_parts    = explode('.', (string) $value);
				$decimal_places = (!isset($value_parts[1])) ? 0 : strlen($value_parts[1]);
			}

			return number_format((float) $value, $decimal_places, '.', ',');
		}

		// Turn line-breaks into breaks for text fields and add links
		if ($formatting === true && in_array($column_type, array('varchar', 'char', 'text'))) {
			return HTML::makeLinks(HTML::convertNewlines(HTML::prepare(self::toString($value))));
		}

		// Anything that has gotten to here is a string value, or is not the
		// proper data type for the column, so we just make sure it is marked
		// up properly for display in HTML
		return HTML::prepare(self::toString($value));
	}


	/**
	 * Generates the method signatures for all methods (including dynamic ones)
	 *
	 * @param  boolean $include_doc_comments  If the doc block comments for each method should be included
	 * @return array<string, string>  An associative array of method name => method signature
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	public function reflect(bool $include_doc_comments = false): array
	{
		$signatures = array();

		$class        = get_class($this);
		$table        = ORM::tablize($class);
		$schema       = ORMSchema::retrieve($class);
		$columns_info = self::getColumnInfo($schema, $table);
		foreach ($columns_info as $column => $column_info) {
			$camelized_column = Grammar::camelize($column, true);

			// Get and set methods
			$signature = '';
			if ($include_doc_comments) {
				$fixed_type = is_string($column_info['type'] ?? null) ? $column_info['type'] : '';
				if ($fixed_type == 'blob') {
					$fixed_type = 'string';
				}
				if ($fixed_type == 'date') {
					$fixed_type = 'Date';
				}
				if ($fixed_type == 'timestamp') {
					$fixed_type = 'Timestamp';
				}
				if ($fixed_type == 'time') {
					$fixed_type = 'Time';
				}

				$signature .= "/**\n";
				$signature .= " * Gets the current value of " . $column . "\n";
				$signature .= " * \n";
				$signature .= " * @return " . $fixed_type . "  The current value\n";
				$signature .= " */\n";
			}
			$get_method = 'get' . $camelized_column;
			$signature .= 'public function ' . $get_method . '()';

			$signatures[$get_method] = $signature;


			$signature = '';
			if ($include_doc_comments) {
				$fixed_type = is_string($column_info['type'] ?? null) ? $column_info['type'] : '';
				if ($fixed_type == 'blob') {
					$fixed_type = 'string';
				}
				if ($fixed_type == 'date') {
					$fixed_type = 'Date|string';
				}
				if ($fixed_type == 'timestamp') {
					$fixed_type = 'Timestamp|string';
				}
				if ($fixed_type == 'time') {
					$fixed_type = 'Time|string';
				}

				$signature .= "/**\n";
				$signature .= " * Sets the value for " . $column . "\n";
				$signature .= " * \n";
				$signature .= " * @param  " . $fixed_type . " \$" . $column . "  The new value\n";
				$signature .= " * @return ActiveRecord  The record object, to allow for method chaining\n";
				$signature .= " */\n";
			}
			$set_method = 'set' . $camelized_column;
			$signature .= 'public function ' . $set_method . '($' . $column . ')';

			$signatures[$set_method] = $signature;


			// The encode method
			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Encodes the value of " . $column . " for output into an HTML form\n";
				$signature .= " * \n";

				if (in_array($column_info['type'], array('time', 'timestamp', 'date'))) {
					$signature .= " * @param  string \$date_formatting_string  A date() compatible formatting string\n";
				}
				if (in_array($column_info['type'], array('float'))) {
					$signature .= " * @param  integer \$decimal_places  The number of decimal places to include - if not specified will default to the precision of the column or the current value\n";
				}

				$signature .= " * @return string  The HTML form-ready value\n";
				$signature .= " */\n";
			}
			$encode_method = 'encode' . $camelized_column;
			$signature .= 'public function ' . $encode_method . '(';
			if (in_array($column_info['type'], array('time', 'timestamp', 'date'))) {
				$signature .= '$date_formatting_string';
			}
			if (in_array($column_info['type'], array('float'))) {
				$signature .= '$decimal_places=NULL';
			}
			$signature .= ')';

			$signatures[$encode_method] = $signature;


			// The prepare method
			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Prepares the value of " . $column . " for output into HTML\n";
				$signature .= " * \n";

				if (in_array($column_info['type'], array('time', 'timestamp', 'date'))) {
					$signature .= " * @param  string \$date_formatting_string  A date() compatible formatting string\n";
				}
				if (in_array($column_info['type'], array('float'))) {
					$signature .= " * @param  integer \$decimal_places  The number of decimal places to include - if not specified will default to the precision of the column or the current value\n";
				}
				if (in_array($column_info['type'], array('varchar', 'char', 'text'))) {
					$signature .= " * @param  boolean \$create_links_and_line_breaks  Will cause links to be automatically converted into [a] tags and line breaks into [br] tags \n";
				}

				$signature .= " * @return string  The HTML-ready value\n";
				$signature .= " */\n";
			}
			$prepare_method = 'prepare' . $camelized_column;
			$signature .= 'public function ' . $prepare_method . '(';
			if (in_array($column_info['type'], array('time', 'timestamp', 'date'))) {
				$signature .= '$date_formatting_string';
			}
			if (in_array($column_info['type'], array('float'))) {
				$signature .= '$decimal_places=NULL';
			}
			if (in_array($column_info['type'], array('varchar', 'char', 'text'))) {
				$signature .= '$create_links_and_line_breaks=FALSE';
			}
			$signature .= ')';

			$signatures[$prepare_method] = $signature;


			// The inspect method
			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Returns metadata about " . $column . "\n";
				$signature .= " * \n";
				$elements = array('type', 'not_null', 'default', 'comment');
				if (in_array($column_info['type'], array('varchar', 'char', 'text'))) {
					$elements[] = 'valid_values';
					$elements[] = 'max_length';
				}
				if ($column_info['type'] == 'float') {
					$elements[] = 'decimal_places';
				}
				if ($column_info['type'] == 'integer') {
					$elements[] = 'auto_increment';
					$elements[] = 'min_value';
					$elements[] = 'max_value';
				}
				$signature .= " * @param  string \$element  The element to return. Must be one of: '" . join("', '", $elements) . "'.\n";
				$signature .= " * @return mixed  The metadata array or a single element\n";
				$signature .= " */\n";
			}
			$inspect_method = 'inspect' . $camelized_column;
			$signature .= 'public function ' . $inspect_method . '($element=NULL)';

			$signatures[$inspect_method] = $signature;
		}

		ORMRelated::reflect($class, $signatures, $include_doc_comments);

		ORM::callReflectCallbacks($class, $signatures, $include_doc_comments);

		$reflection = new ReflectionClass($class);
		$methods    = $reflection->getMethods();

		foreach ($methods as $method) {
			$signature = '';

			if (!$method->isPublic() || $method->getName() == '__call') {
				continue;
			}

			if ($method->isFinal()) {
				$signature .= 'final ';
			}

			if ($method->isAbstract()) {
				$signature .= 'abstract ';
			}

			if ($method->isStatic()) {
				$signature .= 'static ';
			}

			$signature .= 'public function ';

			if ($method->returnsReference()) {
				$signature .= '&';
			}

			$signature .= $method->getName();
			$signature .= '(';

			$parameters = $method->getParameters();
			foreach ($parameters as $parameter) {
				if (substr($signature, -1) == '(') {
					$signature .= '';
				} else {
					$signature .= ', ';
				}

				$type = $parameter->getType();
				if ($type !== null) {
					$type_name = ($type instanceof ReflectionNamedType) ? $type->getName() : (string) $type;
					if ($type_name === 'array') {
						$signature .= 'array ';
					} else {
						$signature .= $type_name . ' ';
					}
				}
				if ($parameter->isPassedByReference()) {
					$signature .= '&';
				}
				$signature .= '$' . $parameter->getName();

				if ($parameter->isDefaultValueAvailable()) {
					$val = var_export($parameter->getDefaultValue(), true);
					if ($val == 'true') {
						$val = 'TRUE';
					}
					if ($val == 'false') {
						$val = 'FALSE';
					}
					if (is_array($parameter->getDefaultValue())) {
						$val = (string) preg_replace('#array\s+\(\s+#', 'array(', $val);
						$val = (string) preg_replace('#,(\r)?\n  #', ', ', $val);
						$val = (string) preg_replace('#,(\r)?\n\)#', ')', $val);
					}
					$signature .= '=' . $val;
				}
			}

			$signature .= ')';

			if ($include_doc_comments) {
				$comment = $method->getDocComment();
				if (is_string($comment)) {
					$comment = (string) preg_replace('#^\t+#m', '', $comment);
					$signature = $comment . "\n" . $signature;
				}
			}
			$signatures[$method->getName()] = $signature;
		}

		ksort($signatures);

		$signature_map = array();
		foreach ($signatures as $method_name => $signature) {
			$signature_map[$method_name] = $signature;
		}

		return $signature_map;
	}


	/**
	 * Generates a clone of the current record, removing any auto incremented primary key value and allowing for replicating related records
	 *
	 * This method will accept three different sets of parameters:
	 *
	 *  - No parameters: this object will be cloned
	 *  - A single `TRUE` value: this object plus all many-to-many associations and all child records (recursively) will be cloned
	 *  - Any number of plural related record class names: the many-to-many associations or child records that correspond to the classes specified will be cloned
	 *
	 * The class names specified can be a simple class name if there is only a
	 * single route between the two corresponding database tables. If there is
	 * more than one route between the two tables, the class name should be
	 * substituted with a string in the format `'RelatedClass{route}'`.
	 *
	 * @param  string $related_class  The plural related class to replicate - see method description for details
	 * @return ActiveRecord  The cloned record
	 * @throws NotFoundException
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 */
	public function replicate(?string $related_class = null): ActiveRecord
	{
		$replicate_level = ActiveRecord::$replicate_level;
		ORM::callHookCallbacks(
			$this,
			'pre::replicate()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->cache,
			$replicate_level
		);
		if (is_int($replicate_level)) {
			ActiveRecord::$replicate_level = $replicate_level;
		}

		ActiveRecord::$replicate_level++;

		$class  = get_class($this);
		$hash   = self::hash($this->values, $class);
		if (!is_string($hash)) {
			throw new ProgrammerException(
				'Could not determine the hash of the record being replicated'
			);
		}
		$schema = ORMSchema::retrieve($class);
		$table  = ORM::tablize($class);

		// If the object has not been replicated yet, do it now
		if (!isset(ActiveRecord::$replicate_map[$class])) {
			ActiveRecord::$replicate_map[$class] = array();
		}
		if (!isset(ActiveRecord::$replicate_map[$class][$hash])) {
			ActiveRecord::$replicate_map[$class][$hash] = clone $this;

			// We need the primary key to get a hash, otherwise certain recursive relationships end up losing members
			$pk_columns = $schema->getKeys($table, 'primary');
			if (sizeof($pk_columns) == 1 && $schema->getColumnInfo($table, $pk_columns[0], 'auto_increment')) {
				ActiveRecord::$replicate_map[$class][$hash]->values[$pk_columns[0]] = $this->values[$pk_columns[0]];
			}
		}
		$clone = ActiveRecord::$replicate_map[$class][$hash];

		$parameters = func_get_args();

		$recursive                  = false;
		$many_to_many_relationships = $schema->getRelationships($table, 'many-to-many');
		$one_to_many_relationships  = $schema->getRelationships($table, 'one-to-many');


		// When just TRUE is passed we recursively replicate all related records
		if (sizeof($parameters) == 1 && $parameters[0] === true) {
			$parameters = array();
			$recursive  = true;

			foreach ($many_to_many_relationships as $relationship) {
				$parameters[] = Grammar::pluralize(ORM::classize(self::relationshipString($relationship, 'related_table'))) . '{' . self::relationshipString($relationship, 'join_table') . '}';
			}
			foreach ($one_to_many_relationships as $relationship) {
				$parameters[] = Grammar::pluralize(ORM::classize(self::relationshipString($relationship, 'related_table'))) . '{' . self::relationshipString($relationship, 'related_column') . '}';
			}
		}

		$record_sets = array();

		foreach ($parameters as $parameter) {
			if (!is_string($parameter)) {
				continue;
			}

			// Parse the Class{route} strings
			if (strpos($parameter, '{') !== false) {
				$brace         = strpos($parameter, '{');
				$related_class = Grammar::singularize(substr($parameter, 0, $brace));
				if ($related_class === false) {
					throw new ProgrammerException(
						'The related class name %1$s could not be converted to a class',
						$parameter
					);
				}
				$related_class = ORM::getRelatedClass($class, $related_class);
				$related_table = ORM::tablize($related_class);
				$route         = substr($parameter, $brace + 1, -1);
			} else {
				$related_class = Grammar::singularize($parameter);
				if ($related_class === false) {
					throw new ProgrammerException(
						'The related class name %1$s could not be converted to a class',
						$parameter
					);
				}
				$related_class = ORM::getRelatedClass($class, $related_class);
				$related_table = ORM::tablize($related_class);
				$route         = ORMSchema::getRouteName($schema, $table, $related_table);
			}

			// Determine the kind of relationship
			$many_to_many = false;
			$one_to_many  = false;

			foreach ($many_to_many_relationships as $relationship) {
				if ($relationship['related_table'] == $related_table && $relationship['join_table'] == $route) {
					$many_to_many = true;
					break;
				}
			}

			foreach ($one_to_many_relationships as $relationship) {
				if ($relationship['related_table'] == $related_table && $relationship['related_column'] == $route) {
					$one_to_many = true;
					break;
				}
			}

			if (!$many_to_many && !$one_to_many) {
				throw new ProgrammerException(
					'The related class specified, %1$s, does not appear to be in a many-to-many or one-to-many relationship with %$2s',
					$parameter,
					get_class($this)
				);
			}

			// Get the related records
			$record_set = ORMRelated::buildRecords($class, $this->values, $this->related_records, $related_class, $route);

			// One-to-many records need to be replicated, possibly recursively
			if ($one_to_many) {
				if ($recursive) {
					$records = $record_set->call('replicate', true);
				} else {
					$records = $record_set->call('replicate');
				}
				$record_set = RecordSet::buildFromArray($related_class, $records);
				$record_set->call(
					'set' . Grammar::camelize($route, true),
					null
				);
			}

			// Cause the related records to be associated with the new clone
			ORMRelated::associateRecords($class, $clone->related_records, $related_class, $record_set, $route);
		}

		ActiveRecord::$replicate_level--;
		if (!ActiveRecord::$replicate_level) {
			// This removes the primary keys we had added back in for proper duplicate detection
			foreach (ActiveRecord::$replicate_map as $class => $records) {
				$table      = ORM::tablize($class);
				$pk_columns = $schema->getKeys($table, 'primary');
				if (sizeof($pk_columns) != 1 || !$schema->getColumnInfo($table, $pk_columns[0], 'auto_increment')) {
					continue;
				}
				foreach ($records as $hash => $record) {
					$record->values[$pk_columns[0]] = null;
				}
			}
			ActiveRecord::$replicate_map = array();
		}

		$replicate_level = ActiveRecord::$replicate_level;
		ORM::callHookCallbacks(
			$this,
			'post::replicate()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->cache,
			$replicate_level
		);
		if (is_int($replicate_level)) {
			ActiveRecord::$replicate_level = $replicate_level;
		}

		$replicate_level = ActiveRecord::$replicate_level;
		ORM::callHookCallbacks(
			$clone,
			'cloned::replicate()',
			$clone->values,
			$clone->old_values,
			$clone->related_records,
			$clone->cache,
			$replicate_level
		);
		if (is_int($replicate_level)) {
			ActiveRecord::$replicate_level = $replicate_level;
		}

		return $clone;
	}


	/**
	 * Sets a value to the record
	 *
	 * @param  string $column  The column to set the value to
	 * @param  mixed  $value   The value to set
	 * @return ActiveRecord  This record, to allow for method chaining
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	protected function set(string $column, mixed $value): ActiveRecord
	{
		if (!array_key_exists($column, $this->values)) {
			throw new ProgrammerException(
				'The column specified, %s, does not exist',
				$column
			);
		}

		// We consider an empty string or a string of spaces to be equivalent to NULL
		if ($value === '' || (is_string($value) && trim($value) === '')) {
			$value = null;
		}

		$class = get_class($this);
		$value = ORM::objectify($class, $column, $value);

		// Float and int columns that look like numbers with commas will have the commas removed
		if (is_string($value)) {
			$table  = ORM::tablize($class);
			$schema = ORMSchema::retrieve($class);
			$type   = self::getColumnInfoString($schema, $table, $column, 'type');
			if (in_array($type, array('integer', 'float')) && preg_match('#^(\d+,)+\d+(\.\d+)?$#', $value)) {
				$value = str_replace(',', '', $value);
			}
		}

		self::assign($this->values, $this->old_values, $column, $value);

		return $this;
	}


	/**
	 * Stores a record in the database, whether existing or new
	 *
	 * This method will start database and filesystem transactions if they have
	 * not already been started.
	 *
	 * @throws ValidationException  When ::validate() throws an exception
	 *
	 * @param  boolean $force_cascade  When storing related records, this will force deleting child records even if they have their own children in a relationship with an RESTRICT or NO ACTION for the ON DELETE clause
	 * @return ActiveRecord  The record object, to allow for method chaining
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 */
	public function store(bool $force_cascade = false): ActiveRecord
	{
		$class = get_class($this);

		if (ORM::getActiveRecordMethod($class, 'store')) {
			$stored = $this->__call('store', array());
			return $stored instanceof ActiveRecord ? $stored : $this;
		}

		ORM::callHookCallbacks(
			$this,
			'pre::store()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->cache
		);

		$db     = ORMDatabase::retrieve($class, 'write');
		$schema = ORMSchema::retrieve($class);

		$inside_db_transaction = $db->isInsideTransaction();

		try {
			$table = ORM::tablize($class);

			// New auto-incrementing records require lots of special stuff, so we'll detect them here
			$new_autoincrementing_record = false;
			$pk_column                  = null;
			if (!$this->exists()) {
				$pk_columns           = $schema->getKeys($table, 'primary');
				$pk_column            = $pk_columns[0];
				$pk_auto_incrementing = $schema->getColumnInfo($table, $pk_column, 'auto_increment');

				if (sizeof($pk_columns) == 1 && $pk_auto_incrementing && !$this->values[$pk_column]) {
					$new_autoincrementing_record = true;
				}
			}

			if (!$inside_db_transaction) {
				$db->translatedQuery('BEGIN');
			}

			ORM::callHookCallbacks(
				$this,
				'post-begin::store()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->cache
			);

			$this->validate();

			ORM::callHookCallbacks(
				$this,
				'post-validate::store()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->cache
			);

			// Storing main table

			if (!$this->exists()) {
				$params = $this->constructInsertParams();
			} else {
				$params = $this->constructUpdateParams();
			}
			$result = self::runQuery($db, $params);


			// If there is an auto-incrementing primary key, grab the value from the database
			if ($new_autoincrementing_record) {
				if (!is_string($pk_column)) {
					throw new ProgrammerException(
						'The primary key column could not be determined for the record being stored'
					);
				}
				$this->set($pk_column, $result->getAutoIncrementedValue());
			}


			// Fix cascade updated columns for in-memory objects to prevent issues when saving
			$one_to_one_relationships  = $schema->getRelationships($table, 'one-to-one');
			$one_to_many_relationships = $schema->getRelationships($table, 'one-to-many');

			$relationships = array_merge($one_to_one_relationships, $one_to_many_relationships);

			foreach ($relationships as $relationship) {
				$type  = in_array($relationship, $one_to_one_relationships) ? 'one-to-one' : 'one-to-many';
				$route = ORMSchema::getRouteNameFromRelationship($type, $relationship);

				$related_table = self::relationshipString($relationship, 'related_table');
				$related_class = ORM::classize($related_table);
				$related_class = ORM::getRelatedClass($class, $related_class);

				if (self::relationshipString($relationship, 'on_update') != 'cascade') {
					continue;
				}

				$column = self::relationshipString($relationship, 'column');
				if (!ActiveRecord::changed($this->values, $this->old_values, $column)) {
					continue;
				}

				if (!isset($this->related_records[$related_table][$route]['record_set'])) {
					continue;
				}

				$record_set     = $this->related_records[$related_table][$route]['record_set'];
				$related_column = self::relationshipString($relationship, 'related_column');

				$old_value      = ActiveRecord::retrieveOld($this->old_values, $column);
				$value          = $this->values[$column];

				if ($old_value === null) {
					continue;
				}
				foreach ($record_set as $record) {
					if (isset($record->old_values[$related_column]) && is_array($record->old_values[$related_column])) {
						foreach (array_keys($record->old_values[$related_column]) as $key) {
							if ($record->old_values[$related_column][$key] === $old_value) {
								$record->old_values[$related_column][$key] = $value;
							}
						}
					}
					if ($record->values[$related_column] === $old_value) {
						$record->values[$related_column] = $value;
					}
				}
			}

			// Storing *-to-many and one-to-one relationships
			ORMRelated::store($class, $this->values, $this->related_records, $force_cascade);


			ORM::callHookCallbacks(
				$this,
				'pre-commit::store()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->cache
			);

			if (!$inside_db_transaction) {
				$db->translatedQuery('COMMIT');
			}

			ORM::callHookCallbacks(
				$this,
				'post-commit::store()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->cache
			);
		} catch (FlourishException $e) {
			if (!$inside_db_transaction) {
				$db->translatedQuery('ROLLBACK');
			}

			ORM::callHookCallbacks(
				$this,
				'post-rollback::store()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->cache
			);

			if ($new_autoincrementing_record && is_string($pk_column) && self::hasOld($this->old_values, $pk_column)) {
				$this->values[$pk_column] = self::retrieveOld($this->old_values, $pk_column);
				unset($this->old_values[$pk_column]);
			}

			throw $e;
		}

		ORM::callHookCallbacks(
			$this,
			'post::store()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->cache
		);

		$was_new = !$this->exists();

		// If we got here we succefully stored, so update old values to make exists() work
		foreach ($this->values as $column => $value) {
			$this->old_values[$column] = array($value);
		}

		// If the object was just inserted into the database, save it to the identity map
		if ($was_new) {
			$hash = self::hash($this->values, $class);
			if (!is_string($hash)) {
				throw new ProgrammerException(
					'Could not determine the hash of the record just stored'
				);
			}

			if (!isset(self::$identity_map[$class])) {
				self::$identity_map[$class] = array();
			}
			self::$identity_map[$class][$hash] = $this;
		}

		return $this;
	}


	/**
	 * Validates the values of the record against the database and any additional validation rules
	 *
	 * @throws ValidationException  When the record, or one of the associated records, violates one of the validation rules for the class or can not be properly stored in the database
	 *
	 * @param  boolean $return_messages      If an array of validation messages should be returned instead of an exception being thrown
	 * @param  boolean $remove_column_names  If column names should be removed from the returned messages, leaving just the message itself
	 * @return array<mixed>|void  If $return_messages is TRUE, an array of validation messages will be returned
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 */
	public function validate(bool $return_messages = false, bool $remove_column_names = false): mixed
	{
		$class = get_class($this);

		if (ORM::getActiveRecordMethod($class, 'validate')) {
			$validation_result = $this->__call('validate', array($return_messages));
			if (is_array($validation_result)) {
				return $validation_result;
			}
			return $return_messages ? array() : null;
		}

		$validation_messages = array();

		ORM::callHookCallbacks(
			$this,
			'pre::validate()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->cache,
			$validation_messages
		);
		$validation_messages = is_array($validation_messages) ? $validation_messages : array();

		// Validate the local values
		$local_validation_messages = ORMValidation::validate($this, $this->values, $this->old_values);

		// Validate related records
		$related_validation_messages = ORMValidation::validateRelated($this, $this->values, $this->related_records);

		$validation_messages = array_merge($validation_messages, $local_validation_messages, $related_validation_messages);

		ORM::callHookCallbacks(
			$this,
			'post::validate()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->cache,
			$validation_messages
		);
		$validation_messages = is_array($validation_messages) ? $validation_messages : array();

		// Normalize the messages to the format expected by the message processing methods
		$validation_messages = array_map(
			static function (mixed $message) {
				if (is_string($message)) {
					return $message;
				}
				if (!is_array($message)) {
					return '';
				}
				$name   = (isset($message['name']) && is_string($message['name'])) ? $message['name'] : '';
				$errors = array();
				if (isset($message['errors']) && is_array($message['errors'])) {
					foreach ($message['errors'] as $error_key => $error) {
						if (is_string($error)) {
							$errors[(string) $error_key] = $error;
						}
					}
				}
				return array('name' => $name, 'errors' => $errors);
			},
			$validation_messages
		);

		$validation_messages = ORMValidation::replaceMessages($class, $validation_messages);
		$validation_messages = ORMValidation::reorderMessages($class, $validation_messages);

		if ($return_messages) {
			if ($remove_column_names) {
				$validation_messages = ValidationException::removeFieldNames($validation_messages);
			}
			return $validation_messages;
		}

		if (!empty($validation_messages)) {
			throw new ValidationException(
				'The following problems were found:',
				$validation_messages
			);
		}
	}
}



/**
 * Copyright (c) 2007-2016 Will Bond <will@flourishlib.com>, others
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
