<?php

declare(strict_types=1);

/**
 * Allows a column in an ActiveRecord class to be a relative sort order column
 *
 * @copyright  Copyright (c) 2008-2010 Will Bond, others
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @author     Dan Collins, iMarc LLC [dc-imarc] <dan@imarc.net>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 * @link       http://flourishlib.com/Ordering
 *
 * @version    1.0.0b19
 * @changes    1.0.0b19  Updated code to work with the new ORM API [wb, 2010-08-06]
 * @changes    1.0.0b18  Changed ::configureOrderingColumn() to ensure the column specified can store negative values [wb, 2010-07-21]
 * @changes    1.0.0b17  Changed validation messages array to use column name keys [wb, 2010-05-26]
 * @changes    1.0.0b16  Updated the class to allow for multiple ordering columns per class [dc-imarc, 2010-05-10]
 * @changes    1.0.0b15  Fixed a bug with ordering columns that are part of a multi-column unique constraint [wb, 2009-11-13]
 * @changes    1.0.0b14  Fixed a bug affecting where conditions with columns that are not null but have a default value [wb, 2009-11-03]
 * @changes    1.0.0b13  Updated code for the new ORMDatabase and ORMSchema APIs [wb, 2009-10-28]
 * @changes    1.0.0b12  Changed SQL statements to use value placeholders, identifier escaping and schema support [wb, 2009-10-22]
 * @changes    1.0.0b11  Fixed another bug with deleting records in the middle of a set, added support for reordering multiple records at once [dc-imarc, 2009-07-17]
 * @changes    1.0.0b10  Fixed a bug with deleting multiple in-memory records in the same set [dc-imarc, 2009-07-15]
 * @changes    1.0.0b9   Fixed a bug with using ORM::registerInspectCallback() [wb, 2009-07-15]
 * @changes    1.0.0b8   Updated to use new ORM::registerInspectCallback() method [wb, 2009-07-13]
 * @changes    1.0.0b7   Fixed ::validate() so it properly ignores ordering columns in multi-column unique constraints [wb, 2009-06-17]
 * @changes    1.0.0b6   Updated code for new ORM API [wb, 2009-06-15]
 * @changes    1.0.0b5   Updated class to automatically correct ordering values that are too high [wb, 2009-06-14]
 * @changes    1.0.0b4   Updated code to use new ValidationException::formatField() method [wb, 2009-06-04]
 * @changes    1.0.0b3   Fixed a bug with setting a new record to anywhere but the end of a set [wb, 2009-03-18]
 * @changes    1.0.0b2   Fixed a bug with ::inspect(), 'max_ordering_value' was being returned as 'max_ordering_index' [wb, 2009-03-02]
 * @changes    1.0.0b    The initial implementation [wb, 2008-06-25]
 */
class Ordering
{
	/**
	 * The columns configured as ordering columns
	 *
	 * @var array<string, array<string, array<array-key, string>>>
	 */
	private static array $ordering_columns = array();


	/**
	 * Composes text using Text if loaded
	 *
	 * @param  string  $message    The message to compose
	 * @return string  The composed and possible translated message
	 */
	private static function compose(string $message): string
	{
		$args = array_map(static function (mixed $arg): bool|float|int|string|null {
			if ($arg === null || is_bool($arg) || is_int($arg) || is_float($arg) || is_string($arg)) {
				return $arg;
			}
			if ($arg instanceof Stringable) {
				return (string) $arg;
			}
			throw new ProgrammerException(
				'Only scalar values and NULL can be used to compose the message, %s given',
				get_debug_type($arg)
			);
		}, array_slice(func_get_args(), 1));

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
	 * Runs a single translated query and returns its Result
	 *
	 * @param  Database                 $db      The database to run the query on
	 * @param  array<array-key, mixed>  $params  The SQL statement and the values to escape into it
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
	 * Sets a column to be an ordering column
	 *
	 * There can only be one ordering column per class/table and it must be
	 * part of a single or multi-column `UNIQUE` constraint.
	 *
	 * @param  mixed  $class   The class name or instance of the class
	 * @param  string $column  The column to set as an ordering column
	 * @return void
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	public static function configureOrderingColumn(mixed $class, string $column): void
	{
		$class       = ORM::getClass($class);
		$table       = ORM::tablize($class);
		$schema      = ORMSchema::retrieve($class);
		$info        = $schema->getColumnInfo($table, $column);
		$info        = is_array($info) ? $info : array();
		$unique_keys = $schema->getKeys($table, 'unique');

		$data_type = (isset($info['type']) && is_scalar($info['type'])) ? (string) $info['type'] : '';
		if ($data_type != 'integer') {
			throw new ProgrammerException(
				'The column specified, %1$s, is a %2$s column. It must be an integer column to be set as an ordering column.',
				$column,
				$data_type
			);
		}

		$min_value = $info['min_value'] ?? null;
		if ($min_value instanceof Number && $min_value->eq('0')) {
			throw new ProgrammerException(
				'The column specified, %1$s, does not allow for negative values. Please adjust the data type to an integer type that allows for negative values.',
				$column
			);
		}

		$found = false;
		$other_columns = array();
		foreach ($unique_keys as $unique_key) {
			settype($unique_key, 'array');
			if (in_array($column, $unique_key)) {
				$other_columns = array_diff($unique_key, array($column));
				$found = true;
				break;
			}
		}

		if (!$found) {
			throw new ProgrammerException(
				'The column specified, %s, does not appear to be part of a unique key. It must be part of a unique key to be set as an ordering column.',
				$column
			);
		}

		if (!ORM::checkHookCallback($class, 'post::validate()', array(self::class, 'validate'))) {
			ORM::registerHookCallback($class, 'post::validate()', array(self::class, 'validate'));
		}
		if (!ORM::checkHookCallback($class, 'post-validate::store()', array(self::class, 'reorder'))) {
			ORM::registerHookCallback($class, 'post-validate::store()', array(self::class, 'reorder'));
		}
		if (!ORM::checkHookCallback($class, 'pre-commit::delete()', array(self::class, 'delete'))) {
			ORM::registerHookCallback($class, 'pre-commit::delete()', array(self::class, 'delete'));
		}

		ORM::registerReflectCallback($class, array(self::class, 'reflect'));

		ORM::registerActiveRecordMethod($class, 'inspect' . Grammar::camelize($column, true), array(self::class, 'inspect'));

		self::$ordering_columns[$class][$column] = $other_columns;
	}


	/**
	 * Add params for a `WHERE` clause for the //old// multi-column set a record was part of
	 *
	 * @param  Schema $schema         The schema of the database the query will be executed on
	 * @param  array<array-key, mixed>  $params         The params for the Database::query() call
	 * @param  string  $table          The table the `WHERE` clause is for
	 * @param  array<array-key, string>  $other_columns  The other columns in the multi-column unique constraint
	 * @param  array<array-key, mixed>  &$values        The record's current values
	 * @param  array<array-key, mixed>  &$old_values    The record's old values
	 * @return array<array-key, mixed>  The updated params for Database::query()
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	private static function addOldOtherFieldsWhereParams(Schema $schema, array $params, string $table, array $other_columns, mixed &$values, mixed &$old_values): array
	{
		$column_info = $schema->getColumnInfo($table);
		$column_info = is_array($column_info) ? $column_info : array();

		$conditions = array();
		foreach ($other_columns as $other_column) {
			$value = ActiveRecord::retrieveOld($old_values, $other_column, $values[$other_column]);

			$other_info = $column_info[$other_column] ?? null;

			// This makes sure the query performs the way an insert will
			if ($value === null && is_array($other_info) && ($other_info['not_null'] ?? null) && ($other_info['default'] ?? null) !== null) {
				$value = $other_info['default'];
			}

			$params[] = $table . '.' . $other_column;
			$params[] = $value;

			$conditions[] = ORMDatabase::makeCondition($schema, $table, $other_column, '=', $value);
		}

		$params[0] = (is_string($params[0]) ? $params[0] : '') . join(' AND ', $conditions);

		return $params;
	}


	/**
	 * Adds params for a `WHERE` clause to ensure a database call is only selecting from rows that are part of the same set when an ordering field is in multi-column `UNIQUE` constraint.
	 *
	 * @param  Schema $schema         The schema of the database the query will be executed on
	 * @param  array<array-key, mixed>  $params         The parameters for the Database::query() call
	 * @param  string  $table          The table the `WHERE` clause is for
	 * @param  array<array-key, string>  $other_columns  The other columns in the multi-column unique constraint
	 * @param  array<array-key, mixed>  &$values        The values to match with
	 * @return array<array-key, mixed>  The updated params for Database::query()
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	private static function addOtherFieldsWhereParams(Schema $schema, array $params, string $table, array $other_columns, mixed &$values): array
	{
		$column_info = $schema->getColumnInfo($table);
		$column_info = is_array($column_info) ? $column_info : array();

		$conditions = array();
		foreach ($other_columns as $other_column) {
			$value = $values[$other_column];

			$other_info = $column_info[$other_column] ?? null;

			// This makes sure the query performs the way an insert will
			if ($value === null && is_array($other_info) && ($other_info['not_null'] ?? null) && ($other_info['default'] ?? null) !== null) {
				$value = $other_info['default'];
			}

			$params[] = $table . '.' . $other_column;
			$params[] = $value;

			$conditions[] = ORMDatabase::makeCondition($schema, $table, $other_column, '=', $value);
		}

		$params[0] = (is_string($params[0]) ? $params[0] : '') . join(' AND ', $conditions);

		return $params;
	}


	/**
	 * Re-orders other records in the set when the record specified is deleted
	 *
	 * @internal
	 *
	 * @param  ActiveRecord $object            The ActiveRecord instance
	 * @param  array<array-key, mixed> &$values           The current values
	 * @param  array<array-key, mixed> &$old_values       The old values
	 * @param  array<array-key, mixed> &$related_records  Any records related to this record
	 * @param  array<array-key, mixed> &$cache            The cache array for the record
	 * @return void
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws ValidationException
	 * @throws NotFoundException
	 */
	public static function delete(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache): void
	{
		$class = get_class($object);
		$table = ORM::tablize($class);

		$db     = ORMDatabase::retrieve($class, 'write');
		$schema = ORMSchema::retrieve($class);

		foreach (self::$ordering_columns[$class] as $column => $other_columns) {
			$current_value = $values[$column];
			$old_value     = ActiveRecord::retrieveOld($old_values, $column, $current_value);

			// Figure out the range we are dealing with
			$params = array("SELECT MAX(%r) FROM %r", $column, $table);
			if ($other_columns) {
				$params[0] .= " WHERE ";
				$params     = self::addOtherFieldsWhereParams($schema, $params, $table, $other_columns, $values);
			}

			$current_max_value = (int) self::runQuery($db, $params)->fetchScalar();

			$shift_down = $current_max_value + 10;
			$shift_up   = $current_max_value + 9;

			$params = array(
				"SELECT %r FROM %r LEFT JOIN %r t2 ON %r = t2.%r + 1",
				$table . '.' . $column,
				$table,
				$table,
				$table . '.' . $column,
				$column
			);

			foreach ($other_columns as $other_column) {
				$params[0] .= " AND %r = t2.%r";
				$params[] = $table . '.' . $other_column;
				$params[] = $other_column;
			}

			$params[0] .= " WHERE t2.%r IS NULL AND %r != 1";
			$params[] = $column;
			$params[] = $table . '.' . $column;

			if ($other_columns) {
				$params[0] .= " AND ";
				$params = self::addOldOtherFieldsWhereParams($schema, $params, $table, $other_columns, $values, $old_values);
			}

			$res = self::runQuery($db, $params);

			if (!$res->countReturnedRows()) {
				continue;
			}

			$scalar   = $res->fetchScalar();
			$old_value = (is_numeric($scalar) ? $scalar : 0) - 1;

			// Close the gap for all records after this one in the set
			$params = array(
				'UPDATE %r SET %r = %r - %i WHERE %r > %i',
				$table,
				$column,
				$column,
				$shift_down,
				$column,
				$old_value
			);
			if ($other_columns) {
				$params[0] .= " AND ";
				$params = self::addOldOtherFieldsWhereParams($schema, $params, $table, $other_columns, $values, $old_values);
			}

			call_user_func_array($db->translatedQuery(...), $params);

			// Close the gap for all records after this one in the set
			$params = array(
				'UPDATE %r SET %r = %r + %i WHERE %r < 0',
				$table,
				$column,
				$column,
				$shift_up,
				$column
			);
			if ($other_columns) {
				$params[0] .= " AND ";
				$params = self::addOldOtherFieldsWhereParams($schema, $params, $table, $other_columns, $values, $old_values);
			}

			call_user_func_array($db->translatedQuery(...), $params);
		}
	}

	/**
	 * Returns the metadata about a column including features added by this class
	 *
	 * @internal
	 *
	 * @param  ActiveRecord $object            The ActiveRecord instance
	 * @param  array<array-key, mixed> &$values           The current values
	 * @param  array<array-key, mixed> &$old_values       The old values
	 * @param  array<array-key, mixed> &$related_records  Any records related to this record
	 * @param  array<array-key, mixed> &$cache            The cache array for the record
	 * @param  string        $method_name       The method that was called
	 * @param  array<array-key, mixed> $parameters        The parameters passed to the method
	 * @return mixed  The metadata array or element specified
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws ValidationException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	public static function inspect(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache, string $method_name, array $parameters): mixed
	{
		list ($action, $subject) = ORM::parseMethod($method_name);

		$column = Grammar::underscorize($subject);
		$class  = get_class($object);
		$table  = ORM::tablize($class);

		$db     = ORMDatabase::retrieve($class, 'read');
		$schema = ORMSchema::retrieve($class);

		$info       = $schema->getColumnInfo($table, $column);
		$info       = is_array($info) ? $info : array();
		$element    = (isset($parameters[0]) && is_string($parameters[0])) ? $parameters[0] : null;

		$other_columns = self::$ordering_columns[$class][$column];

		// Retrieve the current max ordering index from the database
		$params = array("SELECT MAX(%r) FROM %r", $column, $table);
		if ($other_columns) {
			$params[0] .= " WHERE ";
			$params = self::addOtherFieldsWhereParams($schema, $params, $table, $other_columns, $values);
		}
		$max_value = (int) self::runQuery($db, $params)->fetchScalar();

		// If this is a new record, or in a new set, we need one more space in the ordering index
		if (self::isInNewSet($column, $other_columns, $values, $old_values)) {
			$max_value += 1;
		}

		$info['max_ordering_value'] = $max_value;
		$info['feature']            = 'ordering';

		ORM::callInspectCallbacks($class, $column, $info);

		if ($element) {
			return (isset($info[$element])) ? $info[$element] : null;
		}

		return $info;
	}


	/**
	 * Checks to see if the values specified are part of a record that is new to its order set
	 *
	 * @param  string $ordering_column  The column being ordered by
	 * @param  array<array-key, string>  $other_columns    The other columns in the multi-column unique constraint
	 * @param  array<array-key, mixed>  &$values          The values of the record
	 * @param  array<array-key, mixed>  &$old_values      The old values of the record
	 * @return boolean  If the record is part of a new ordering set
	 */
	private static function isInNewSet(string $ordering_column, array $other_columns, mixed &$values, mixed &$old_values): bool
	{
		$value_empty      = !$values[$ordering_column];
		$old_value_empty  = !ActiveRecord::retrieveOld($old_values, $ordering_column, true);
		$no_old_value_set = !ActiveRecord::hasOld($old_values, $ordering_column);

		// If the value appears to be new, the record must be new to the order
		if ($old_value_empty || ($value_empty && $no_old_value_set)) {
			return true;
		}

		// If there aren't any other columns to check, there is
		// only a single order, so it must have already existed
		if (!$other_columns) {
			return false;
		}

		// Check through each of the other columns to see if the set could have
		// changed because of a new value in one of those columns
		foreach ($other_columns as $other_column) {
			if (ActiveRecord::changed($values, $old_values, $other_column)) {
				return true;
			}
		}

		// If none of the multi-column values changed, the record must be part
		// of the same set it was
		return false;
	}


	/**
	 * Adjusts the ActiveRecord::reflect() signatures of columns that have been configured in this class
	 *
	 * @internal
	 *
	 * @param  string  $class                 The class to reflect
	 * @param  array<array-key, mixed>  &$signatures           The associative array of `{method name} => {signature}`
	 * @param  boolean $include_doc_comments  If doc comments should be included with the signature
	 * @return void
	 */
	public static function reflect(string $class, mixed &$signatures, bool $include_doc_comments): void
	{
		if (!isset(self::$ordering_columns[$class])) {
			return;
		}

		foreach (self::$ordering_columns[$class] as $column => $enabled) {
			$camelized_column = Grammar::camelize($column, true);

			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Returns metadata about " . $column . "\n";
				$signature .= " * \n";
				$signature .= " * @param  string \$element  The element to return. Must be one of: 'type', 'not_null', 'default', 'feature', 'max_ordering_value'.\n";
				$signature .= " * @return mixed  The metadata array or a single element\n";
				$signature .= " */\n";
			}
			$inspect_method = 'inspect' . $camelized_column;
			$signature .= 'public function ' . $inspect_method . '($element=NULL)';

			$signatures[$inspect_method] = $signature;
		}
	}


	/**
	 * Re-orders the object based on it's current state and new position
	 *
	 * @internal
	 *
	 * @param  ActiveRecord $object            The ActiveRecord instance
	 * @param  array<array-key, mixed> &$values           The current values
	 * @param  array<array-key, mixed> &$old_values       The old values
	 * @param  array<array-key, mixed> &$related_records  Any records related to this record
	 * @param  array<array-key, mixed> &$cache            The cache array for the record
	 * @return void
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws ValidationException
	 * @throws NotFoundException
	 */
	public static function reorder(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache): void
	{
		$class = get_class($object);
		$table = ORM::tablize($class);

		$db     = ORMDatabase::retrieve($class, 'write');
		$schema = ORMSchema::retrieve($class);

		foreach (self::$ordering_columns[$class] as $column => $other_columns) {
			$current_value = $values[$column];
			if (!$object->exists()) {
				$old_value = ActiveRecord::retrieveOld($old_values, $column);
			} else {
				$params = array("SELECT %r FROM %r WHERE ", $column, $table);
				$params = ORMDatabase::addPrimaryKeyWhereParams($schema, $params, $table, $table, $values, $old_values);
				$old_value = self::runQuery($db, $params)->fetchScalar();
			}

			// Figure out the range we are dealing with
			$params = array("SELECT MAX(%r) FROM %r", $column, $table);
			if ($other_columns) {
				$params[0] .= ' WHERE ';
				$params = self::addOtherFieldsWhereParams($schema, $params, $table, $other_columns, $values);
			}

			$current_max_value = (int) self::runQuery($db, $params)->fetchScalar();
			$new_max_value     = $current_max_value;

			if ($new_set = self::isInNewSet($column, $other_columns, $values, $old_values)) {
				$new_max_value = $current_max_value + 1;
			}

			$changed = false;

			// If a blank value was set, correct it to the old value (if there
			// was one), or a new value at the end of the set
			if ($current_value === '' || $current_value === null) {
				if ($old_value) {
					$current_value = $old_value;
				} else {
					$current_value = $new_max_value;
				}
				$changed = true;
			}

			// When we move an object into a new set and the value didn't change then move it to the end of the new set
			if ($new_set && $object->exists() && ($old_value === null || $old_value == $current_value)) {
				$current_value = $new_max_value;
				$changed = true;
			}

			// If the value is too high, then set it to the last value
			if ($current_value > $new_max_value) {
				$current_value = $new_max_value;
				$changed = true;
			}

			if ($changed) {
				ActiveRecord::assign($values, $old_values, $column, $current_value);
			}

			// If the value didn't change, we can exit
			$value_didnt_change = ($old_value && $current_value == $old_value) || !$old_value;
			if (!$new_set && $value_didnt_change) {
				continue;
			}

			// If we are entering a new record at the end of the set we don't need to shuffle anything either
			if (!$object->exists() && $new_set && $current_value == $new_max_value) {
				continue;
			}


			// If the object already exists in the database, grab the ordering value
			// right now in case some other object reordered it since it was loaded
			$db_value = null;
			if ($object->exists()) {
				$params = array("SELECT %r FROM %r WHERE ", $column, $table);
				$params = ORMDatabase::addPrimaryKeyWhereParams($schema, $params, $table, $table, $values, $old_values);
				$db_value = (int) self::runQuery($db, $params)->fetchScalar();
			}


			// We only need to move things in the new set around if we are inserting into the middle
			// of a new set, or if we are moving around in the current set
			if (!$new_set || $current_value != $new_max_value) {
				$shift_down = $new_max_value + 10;

				// To prevent issues with the unique constraint, we move everything below 0
				$params = array(
					"UPDATE %r SET %r = %r - %i WHERE ",
					$table,
					$column,
					$column,
					$shift_down
				);
				$conditions = array();

				// If we are moving into the middle of a new set we just push everything up one value
				if ($new_set) {
					$shift_up     = $new_max_value + 11;
					$conditions[] = ORMDatabase::makeCondition($schema, $table, $column, '>=', $current_value);
					$params[]     = $table . '.' . $column;
					$params[]     = $current_value;

				// If we are moving a value down in a set, we push values in the difference zone up one
				} elseif ($current_value < $db_value) {
					$shift_up     = $new_max_value + 11;
					$conditions[] = ORMDatabase::makeCondition($schema, $table, $column, '<', $db_value);
					$params[]     = $table . '.' . $column;
					$params[]     = $db_value;
					$conditions[] = ORMDatabase::makeCondition($schema, $table, $column, '>=', $current_value);
					$params[]     = $table . '.' . $column;
					$params[]     = $current_value;

				// If we are moving a value up in a set, we push values in the difference zone down one
				} else {
					$shift_up     = $new_max_value + 9;
					$conditions[] = ORMDatabase::makeCondition($schema, $table, $column, '>', $db_value);
					$params[]     = $table . '.' . $column;
					$params[]     = $db_value;
					$conditions[] = ORMDatabase::makeCondition($schema, $table, $column, '<=', $current_value);
					$params[]     = $table . '.' . $column;
					$params[]     = $current_value;
				}

				$params[0] .= join(' AND ', $conditions);
				if ($other_columns) {
					$params[0] .= " AND ";
					$params = self::addOtherFieldsWhereParams($schema, $params, $table, $other_columns, $values);
				}
				call_user_func_array($db->translatedQuery(...), $params);

				if ($object->exists()) {
					// Put the actual record we are changing in limbo to be updated when the actual update happens
					$params = array(
						"UPDATE %r SET %r = 0 WHERE %r = %i",
						$table,
						$column,
						$column,
						$db_value
					);
					if ($other_columns) {
						$params[0] .= " AND ";
						$params = self::addOldOtherFieldsWhereParams($schema, $params, $table, $other_columns, $values, $old_values);
					}
					call_user_func_array($db->translatedQuery(...), $params);
				}

				// Anything below zero needs to be moved back up into its new position
				$params = array(
					"UPDATE %r SET %r = %r + %i WHERE %r < 0",
					$table,
					$column,
					$column,
					$shift_up,
					$column
				);
				if ($other_columns) {
					$params[0] .= " AND ";
					$params = self::addOtherFieldsWhereParams($schema, $params, $table, $other_columns, $values);
				}
				call_user_func_array($db->translatedQuery(...), $params);
			}


			// If there was an old set, we need to close the gap
			if ($object->exists() && $new_set) {
				$params = array("SELECT MAX(%r) FROM %r WHERE ", $column, $table);
				$params = self::addOldOtherFieldsWhereParams($schema, $params, $table, $other_columns, $values, $old_values);

				$old_set_max = (int) self::runQuery($db, $params)->fetchScalar();

				// We only need to close the gap if the record was not at the end
				if ($db_value < $old_set_max) {
					$shift_down = $old_set_max + 10;
					$shift_up   = $old_set_max + 9;

					// To prevent issues with the unique constraint, we move everything below 0 and then back up above

					$params = array(
						"UPDATE %r SET %r = %r - %i WHERE %r > %i AND ",
						$table,
						$column,
						$column,
						$shift_down,
						$column,
						$db_value
					);
					$params = self::addOldOtherFieldsWhereParams($schema, $params, $table, $other_columns, $values, $old_values);
					call_user_func_array($db->translatedQuery(...), $params);

					if ($current_value == $new_max_value) {
						// Put the actual record we are changing in limbo to be updated when the actual update happens
						$params = array(
							"UPDATE %r SET %r = 0 WHERE %r = %i",
							$table,
							$column,
							$column,
							$db_value
						);
						if ($other_columns) {
							$params[0] .= " AND ";
							$params = self::addOldOtherFieldsWhereParams($schema, $params, $table, $other_columns, $values, $old_values);
						}
						call_user_func_array($db->translatedQuery(...), $params);
					}

					$params = array(
						"UPDATE %r SET %r = %r + %i WHERE %r < 0 AND ",
						$table,
						$column,
						$column,
						$shift_up,
						$column
					);
					$params = self::addOldOtherFieldsWhereParams($schema, $params, $table, $other_columns, $values, $old_values);
					call_user_func_array($db->translatedQuery(...), $params);
				}
			}
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
		self::$ordering_columns = array();
	}


	/**
	 * Makes sure the ordering value is sane, removes error messages about missing values
	 *
	 * @internal
	 *
	 * @param  ActiveRecord $object                The ActiveRecord instance
	 * @param  array<array-key, mixed> &$values               The current values
	 * @param  array<array-key, mixed> &$old_values           The old values
	 * @param  array<array-key, mixed> &$related_records      Any records related to this record
	 * @param  array<array-key, mixed> &$cache                The cache array for the record
	 * @param  array<array-key, mixed> &$validation_messages  An array of ordered validation messages
	 * @return void
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws ValidationException
	 * @throws NotFoundException
	 */
	public static function validate(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache, mixed &$validation_messages): void
	{
		$class = get_class($object);
		$table = ORM::tablize($class);

		$db     = ORMDatabase::retrieve($class, 'read');
		$schema = ORMSchema::retrieve($class);

		foreach (self::$ordering_columns[$class] as $column => $other_columns) {
			$current_value = $values[$column];
			$old_value     = ActiveRecord::retrieveOld($old_values, $column);

			$params = array("SELECT MAX(%r) FROM %r", $column, $table);
			if ($other_columns) {
				$params[0] .= " WHERE ";
				$params = self::addOtherFieldsWhereParams($schema, $params, $table, $other_columns, $values);
			}

			$current_max_value = (int) self::runQuery($db, $params)->fetchScalar();
			$new_max_value     = $current_max_value;

			if ($new_set = self::isInNewSet($column, $other_columns, $values, $old_values)) {
				$new_max_value     = $current_max_value + 1;
				$new_set_new_value = ActiveRecord::changed($values, $old_values, $column);
			}

			$column_name = ORM::getColumnName($class, $column);

			// Remove any previous validation warnings
			$filtered_messages = array();
			foreach ($validation_messages as $validation_column => $validation_message) {
				if (!is_string($validation_column) || !preg_match('#(^|,)' . preg_quote($column, '#') . '(,|$)#D', $validation_column)) {
					$filtered_messages[$validation_column] = $validation_message;
				}
			}
			$validation_messages = $filtered_messages;

			// If we have a completely empty value, we don't need to validate since a valid value will be generated
			if ($current_value === '' || $current_value === null) {
				continue;
			}

			if (!is_numeric($current_value) || strlen((string) (int) $current_value) != strlen((string) $current_value)) {
				$validation_messages[$column] = self::compose('%sPlease enter an integer', ValidationException::formatField($column_name));
			} elseif ($current_value < 1) {
				$validation_messages[$column] = self::compose('%sThe value can not be less than 1', ValidationException::formatField($column_name));
			}
		}
	}


	/**
	 * Forces use as a static class
	 *
	 * @return Ordering
	 */
	private function __construct()
    {
    }
}



/**
 * Copyright (c) 2008-2010 Will Bond <will@flourishlib.com>, others
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
