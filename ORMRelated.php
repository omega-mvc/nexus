<?php

declare(strict_types=1);

/**
 * Handles related record tasks for ActiveRecord classes
 *
 * The functionality of this class only works with single-field `FOREIGN KEY`
 * constraints.
 *
 * @copyright  Copyright (c) 2007-2011 Will Bond, others
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @author     Will Bond, iMarc LLC [wb-imarc] <will@imarc.net>
 * @author     Jeff Turcotte <jeff.turcotte@gmail.com>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 * @link       http://flourishlib.com/ORMRelated
 *
 * @version    1.0.0b47
 * @changes    1.0.0b47  Fixed issues with set_null db constraints and one-to-many table relationships [jt, 2013-05-22]
 * @changes    1.0.0b46  Fixed route issue with linkRecord. [jt, 2013-04-30]
 * @changes    1.0.0b45  Added the `$recursive` parameter to ::populateRecords() [wb, 2011-09-16]
 * @changes    1.0.0b44  Added missing information for has and list methods to ::reflect() [wb, 2011-09-07]
 * @changes    1.0.0b43  Fixed some bugs in handling relationships between PHP 5.3 namespaced classes [wb, 2011-05-26]
 * @changes    1.0.0b42  Fixed a bug with ::associateRecords() not associating record set via primary key [wb, 2011-05-23]
 * @changes    1.0.0b41  Fixed a bug in generating errors messages for many-to-many relationships [wb, 2011-03-07]
 * @changes    1.0.0b40  Updated ::getRelatedRecordName() to use Text if loaded [wb, 2011-02-02]
 * @changes    1.0.0b39  Fixed a bug with ::validate() not properly removing validation messages about a related primary key value not being present yet, if the column and related column names were different [wb, 2010-11-24]
 * @changes    1.0.0b38  Updated ::overrideRelatedRecordName() to prefix any namespace from `$class` to `$related_class` if not already present [wb, 2010-11-24]
 * @changes    1.0.0b37  Fixed a documentation typo [wb, 2010-11-04]
 * @changes    1.0.0b36  Fixed ::getPrimaryKeys() to not throw SQL exceptions [wb, 2010-10-20]
 * @changes    1.0.0b35  Backwards Compatibility Break - changed the validation messages array to use nesting for child records [wb-imarc+wb, 2010-10-03]
 * @changes    1.0.0b35  Updated ::getPrimaryKeys() to always return primary keys in a consistent order when no order bys are specified [wb, 2010-07-26]
 * @changes    1.0.0b34  Updated the class to work with fixes in ORMRelated [wb, 2010-07-22]
 * @changes    1.0.0b33  Fixed the related table populate action to use the plural underscore_notation version of the related class name [wb, 2010-07-08]
 * @changes    1.0.0b32  Backwards Compatibility Break - related table populate action now use the underscore_notation version of the class name instead of the related table name, allowing for related tables in non-standard schemas [wb, 2010-06-23]
 * @changes    1.0.0b31  Fixed ::reflect() to properly show parameters for associate methods [wb, 2010-06-08]
 * @changes    1.0.0b30  Fixed a bug where related record error messages could be overwritten if there were multiple related records with the same error [wb, 2010-05-29]
 * @changes    1.0.0b29  Changed validation messages array to use column name keys [wb, 2010-05-26]
 * @changes    1.0.0b28  Updated ::associateRecords() to accept just a single ActiveRecord [wb, 2010-05-06]
 * @changes    1.0.0b27  Updated the class to force configure classes before peforming actions with them [wb, 2010-03-30]
 * @changes    1.0.0b26  Fixed ::reflect() to show the proper return values for `associate`, `link` and `populate` methods [wb, 2010-03-15]
 * @changes    1.0.0b25  Fixed a bug when storing a one-to-one related record with different column names on each end of the relationship [wb, 2010-03-04]
 * @changes    1.0.0b24  Added the ability to associate a single record via primary key [wb, 2010-03-03]
 * @changes    1.0.0b23  Fixed a column aliasing issue with SQLite [wb, 2010-01-25]
 * @changes    1.0.0b22  Fixed a bug with associating a non-contiguous array of ActiveRecord objects [wb, 2009-12-17]
 * @changes    1.0.0b21  Added support for the $force_cascade parameter of ActiveRecord::store(), added ::hasRecords() and fixed a bug with creating non-existent one-to-one related records [wb, 2009-12-16]
 * @changes    1.0.0b20  Updated code for the new ORMDatabase and ORMSchema APIs [wb, 2009-10-28]
 * @changes    1.0.0b19  Internal Backwards Compatibility Break - Added the `$class` parameter to ::storeManyToMany() - also fixed ::countRecords() to work across all databases, changed SQL statements to use value placeholders, identifier escaping and support schemas [wb, 2009-10-22]
 * @changes    1.0.0b18  Fixed a bug in ::countRecords() that would occur when multiple routes existed to the table being counted [wb, 2009-10-05]
 * @changes    1.0.0b17  Updated code for new RecordSet API [wb, 2009-09-16]
 * @changes    1.0.0b16  Fixed a bug with ::createRecord() not creating non-existent record when the related value is NULL [wb, 2009-08-25]
 * @changes    1.0.0b15  Fixed a bug with ::createRecord() where foreign keys with a different column and related column name would not load properly [wb, 2009-08-17]
 * @changes    1.0.0b14  Fixed a bug with ::createRecord() when a foreign key constraint is on a column other than the primary key [wb, 2009-08-10]
 * @changes    1.0.0b13  ::setOrderBys() now (properly) only recognizes *-to-many relationships [wb, 2009-07-31]
 * @changes    1.0.0b12  Changed how related record values are set and how related validation messages are ignored because of recursive relationships [wb, 2009-07-29]
 * @changes    1.0.0b11  Fixed some bugs with one-to-one relationships [wb, 2009-07-21]
 * @changes    1.0.0b10  Fixed a couple of bugs with validating related records [wb, 2009-06-26]
 * @changes    1.0.0b9   Fixed a bug where ::store() would not save associations with no related records [wb, 2009-06-23]
 * @changes    1.0.0b8   Changed ::associateRecords() to work for *-to-many instead of just many-to-many relationships [wb, 2009-06-17]
 * @changes    1.0.0b7   Updated code for new ORM API, fixed API documentation bugs [wb, 2009-06-15]
 * @changes    1.0.0b6   Updated code to use new ValidationException::formatField() method [wb, 2009-06-04]
 * @changes    1.0.0b5   Added ::getPrimaryKeys() and ::setPrimaryKeys(), renamed ::setRecords() to ::setRecordSet() and ::tallyRecords() to ::setCount() [wb, 2009-06-02]
 * @changes    1.0.0b4   Updated code to handle new association method for related records and new `$related_records` structure, added ::store() and ::validate() [wb, 2009-06-02]
 * @changes    1.0.0b3   ::associateRecords() can now accept an array of records or primary keys instead of only an RecordSet [wb, 2009-06-01]
 * @changes    1.0.0b2   ::populateRecords() now accepts any input field keys instead of sequential ones starting from 0 [wb, 2009-05-03]
 * @changes    1.0.0b    The initial implementation [wb, 2007-12-30]
 *
 * @phpstan-type RelatedInfo    array{record_set?: RecordSet|null, count?: int, primary_keys?: array<int|string, mixed>|null, associate?: bool}
 * @phpstan-type RelatedRecords array<string, array<string, RelatedInfo>>
 * @phpstan-type RelatedValues  array<array-key, mixed>
 */
class ORMRelated
{
	/**
	 * A generic cache for the class
	 *
	 * @var array<string, bool>
	 */
	private static array $cache = array();

	/**
	 * Rules that control what order related data is returned in
	 *
	 * @var array<string, array<string, array<string, array<string, string>>>>
	 */
	private static array $order_bys = array();

	/**
	 * Names for related records
	 *
	 * @var array<string, array<string, array<string, string>>>
	 */
	private static array $related_record_names = array();

	/**
	 * Methods to use for getting the name of related records when performing validation
	 *
	 * @var array<string, array<string, array<string, string>>>
	 */
	private static array $validation_name_methods = array();


	/**
	 * Creates associations for one-to-one relationships
	 *
	 * @internal
	 *
	 * @param  string                             $class             The class to get the related values for
	 * @param  RelatedRecords                            &$related_records  The related records existing for the ActiveRecord class
	 * @param  string                                    $related_class     The class we are associating with the current record
	 * @param  ActiveRecord|array<array-key, mixed>|string|int|null $record  The record (or primary key of the record) to be associated
	 * @param  ?string                                   $route             The route to use between the current class and the related class
	 * @return void
	 */
	public static function associateRecord(string $class, array &$related_records, string $related_class, mixed $record, ?string $route = null): void
	{
		ActiveRecord::validateClass($related_class);
		ActiveRecord::forceConfigure($related_class);

		$table         = ORM::tablize($class);
		$related_table = ORM::tablize($related_class);

		if ($record !== null) {
			if (!$record instanceof ActiveRecord) {
				$record = new $related_class($record);
			}
			$records = array($record);
		} else {
			$records = array();
		}

		$schema  = ORMSchema::retrieve($class);
		$records = RecordSet::buildFromArray($related_class, $records);
		$route   = ORMSchema::getRouteName($schema, $table, $related_table, $route, 'one-to-one');

		self::setRecordSet($class, $related_records, $related_class, $records, $route);
		self::flagForAssociation($class, $related_records, $related_class, $route);
	}


	/**
	 * Creates associations for *-to-many relationships
	 *
	 * @internal
	 *
	 * @param  string            $class                 The class to get the related values for
	 * @param  RelatedRecords                             &$related_records      The related records existing for the ActiveRecord class
	 * @param  string                                     $related_class         The class we are associating with the current record
	 * @param  ActiveRecord|RecordSet|array<array-key, mixed> $records_to_associate  An ActiveRecord, a RecordSet, an array of records, or an array of primary keys of the records to be associated
	 * @param  ?string                                    $route                 The route to use between the current class and the related class
	 * @return void
	 */
	public static function associateRecords(string $class, array &$related_records, string $related_class, mixed $records_to_associate, ?string $route = null): void
	{
		ActiveRecord::validateClass($related_class);
		ActiveRecord::forceConfigure($related_class);

		$table         = ORM::tablize($class);
		$related_table = ORM::tablize($related_class);

		$primary_keys = false;

		if ($records_to_associate instanceof ActiveRecord) {
			$records = RecordSet::buildFromArray($related_class, array($records_to_associate));
		} elseif ($records_to_associate instanceof RecordSet) {
			$records = clone $records_to_associate;
		} elseif (!sizeof($records_to_associate)) {
			$records = RecordSet::buildFromArray($related_class, array());
		} elseif (self::containsOnlyRecords($records_to_associate)) {
			$records = RecordSet::buildFromArray($related_class, array_values($records_to_associate));

		// This indicates we are working with just primary keys, so we have to call a different method
		} else {
			$primary_keys = true;
		}

		$schema = ORMSchema::retrieve($class);
		$route  = ORMSchema::getRouteName($schema, $table, $related_table, $route, '*-to-many');

		if ($primary_keys) {
			self::setPrimaryKeys($class, $related_records, $related_class, $records_to_associate, $route);
		} else {
			self::setRecordSet($class, $related_records, $related_class, $records, $route);
		}
		self::flagForAssociation($class, $related_records, $related_class, $route);
	}


	/**
	 * Builds a set of related records along a one-to-many or many-to-many relationship
	 *
	 * @internal
	 *
	 * @param  string $class             The class to get the related values for
	 * @param  RelatedValues  &$values           The values for the ActiveRecord class
	 * @param  RelatedRecords  &$related_records  The related records existing for the ActiveRecord class
	 * @param  string $related_class     The class that is related to the current record
	 * @param  ?string $route            The route to follow for the class specified
	 * @return RecordSet  A record set of the related records
	 */
	public static function buildRecords(string $class, array &$values, array &$related_records, string $related_class, ?string $route = null): RecordSet
	{
		ActiveRecord::validateClass($related_class);
		ActiveRecord::forceConfigure($related_class);

		$table         = ORM::tablize($class);
		$related_table = ORM::tablize($related_class);

		$schema = ORMSchema::retrieve($class);
		$route  = ORMSchema::getRouteName($schema, $table, $related_table, $route, '*-to-many');

		// If we already have the sequence, we can stop here
		if (isset($related_records[$related_table][$route]['record_set'])) {
			return $related_records[$related_table][$route]['record_set'];
		}

		$relationship = ORMSchema::getRoute($schema, $table, $related_table, $route, '*-to-many');
		$column       = self::relationshipString($relationship, 'column');

		// Determine how we are going to build the sequence
		if (isset($related_records[$related_table][$route]['primary_keys'])) {
			$primary_key_column = current($schema->getKeys($related_table, 'primary'));
			$where_conditions   = array($primary_key_column . '=' => $related_records[$related_table][$route]['primary_keys']);
			$order_bys          = self::getOrderBys($class, $related_class, $route);
			$record_set         = RecordSet::build($related_class, $where_conditions, $order_bys);
			$related_records[$related_table][$route]['record_set'] = $record_set;
			return $record_set;
		} elseif ($values[$column] === null) {
			$record_set = RecordSet::buildFromArray($related_class, array());
		} else {
			$where_column   = $table . '{' . $route . '}.' . $column;
			$where_conditions = array($where_column . '=' => $values[$column]);
			$order_bys        = self::getOrderBys($class, $related_class, $route);
			$record_set       = RecordSet::build($related_class, $where_conditions, $order_bys);
		}

		self::setRecordSet($class, $related_records, $related_class, $record_set, $route);

		return $record_set;
	}


	/**
	 * Composes text using Text if loaded
	 *
	 * @param  string  $message    The message to compose
	 * @return string  The composed and possible translated message
	 */
	private static function compose(string $message): string
	{
		$args = array();
		foreach (array_slice(func_get_args(), 1) as $arg) {
			if ($arg !== null && !is_scalar($arg)) {
				throw new ProgrammerException(
					'Only scalar values can be composed into a message, %1$s given',
					get_debug_type($arg)
				);
			}
			$args[] = $arg;
		}

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
	 * Checks if every value in an array is an ActiveRecord object
	 *
	 * @param  array<array-key, mixed>  $records  The records to check
	 * @return bool  Whether or not all of the values are ActiveRecord objects
	 *
	 * @phpstan-assert-if-true array<array-key, ActiveRecord> $records
	 */
	private static function containsOnlyRecords(array $records): bool
	{
		foreach ($records as $record) {
			if (!$record instanceof ActiveRecord) {
				return false;
			}
		}

		return true;
	}


	/**
	 * Removes duplicate values from a list of related key values
	 *
	 * @param  array<array-key, mixed>  $values  The values to remove duplicates from
	 * @return array<int, string|int|float|bool|null>  The unique values
	 */
	private static function uniqueValues(array $values): array
	{
		$unique = array();

		foreach ($values as $value) {
			if ($value !== null && !is_scalar($value)) {
				throw new ProgrammerException(
					'Only scalar values and NULL can be related through a join table, %1$s given',
					get_debug_type($value)
				);
			}
			$unique[] = $value;
		}

		return array_unique($unique);
	}


	/**
	 * Escapes one or more values into a single SQL string
	 *
	 * @param  Database  $db       The database to escape against
	 * @param  string    $sql      The SQL string or data type to escape with
	 * @param  mixed     ...$values  The value(s) to escape
	 * @return string  The escaped SQL
	 */
	private static function escape(Database $db, string $sql, mixed ...$values): string
	{
		$escaped = $db->escape($sql, ...$values);

		if (!is_string($escaped)) {
			throw new ProgrammerException(
				'Escaping %1$s did not produce a single SQL string, %2$s given',
				$sql,
				get_debug_type($escaped)
			);
		}

		return $escaped;
	}


	/**
	 * Runs one or more translated statements and returns the single result
	 *
	 * @param  Database     $db      The database to run the statements through
	 * @param  array<array-key, mixed>  $params  The SQL statement and the values to escape into it
	 * @return Result  The result of the query
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
	 * Unescapes a value fetched for the column specified
	 *
	 * @param  Database  $db      The database to unescape against
	 * @param  Schema    $schema  The schema the column belongs to
	 * @param  string    $table   The table the column belongs to
	 * @param  string    $column  The column the value was fetched for
	 * @param  mixed     $value   The value to unescape
	 * @return mixed  The unescaped value
	 */
	private static function unescapeValue(Database $db, Schema $schema, string $table, string $column, mixed $value): mixed
	{
		$data_type = $schema->getColumnInfo($table, $column, 'type');

		if (!is_string($data_type)) {
			throw new ProgrammerException(
				'Unable to determine the data type of the %1$s.%2$s column',
				$table,
				$column
			);
		}

		return $db->unescape($data_type, $value);
	}


	/**
	 * Returns the placeholder to use for the column specified
	 *
	 * @param  Schema  $schema  The schema the column belongs to
	 * @param  string  $table   The table the column belongs to
	 * @param  string  $column  The column to get the placeholder for
	 * @return string  The placeholder for the column specified
	 */
	private static function placeholder(Schema $schema, string $table, string $column): string
	{
		$placeholder = $schema->getColumnInfo($table, $column, 'placeholder');

		if (!is_string($placeholder)) {
			throw new ProgrammerException(
				'Unable to determine the placeholder for the %1$s.%2$s column',
				$table,
				$column
			);
		}

		return $placeholder;
	}


	/**
	 * Casts a fetched value into an integer count
	 *
	 * @param  mixed  $value  The value to convert
	 * @return int  The count represented by the value
	 */
	private static function countValue(mixed $value): int
	{
		return is_numeric($value) ? (int) $value : 0;
	}


	/**
	 * Counts the number of related one-to-many or many-to-many records
	 *
	 * @internal
	 *
	 * @param  string $class             The class to get the related values for
	 * @param  RelatedValues  &$values           The values for the ActiveRecord class
	 * @param  RelatedRecords  &$related_records  The related records existing for the ActiveRecord class
	 * @param  string $related_class     The class that is related to the current record
	 * @param  ?string $route            The route to follow for the class specified
	 * @return integer  The number of related records
	 */
	public static function countRecords(string $class, array &$values, array &$related_records, string $related_class, ?string $route = null): int
	{
		ActiveRecord::validateClass($related_class);
		ActiveRecord::forceConfigure($related_class);

		$db     = ORMDatabase::retrieve($class, 'read');
		$schema = ORMSchema::retrieve($class);

		$table         = ORM::tablize($class);
		$related_table = ORM::tablize($related_class);

		$route = ORMSchema::getRouteName($schema, $table, $related_table, $route, '*-to-many');

		// If we already have the sequence, we can stop here
		if (isset($related_records[$related_table][$route]['count'])) {
			return $related_records[$related_table][$route]['count'];
		}

		$relationship = ORMSchema::getRoute($schema, $table, $related_table, $route, '*-to-many');
		$column       = self::relationshipString($relationship, 'column');

		// Determine how we are going to build the sequence
		if ($values[$column] === null) {
			$count = 0;
		} else {
			$value = $values[$column];

			$pk_columns = $schema->getKeys($related_table, 'primary');

			// One-to-many relationships require joins
			if (!isset($relationship['join_table'])) {
				$table_with_route = $table . '{' . self::relationshipString($relationship, 'related_column') . '}';

				$params = array("SELECT count(*) AS flourish__count FROM :from_clause WHERE ");

				$params[0] .= str_replace(
					'%r',
					self::escape($db, '%r', $table_with_route . '.' . $column),
					ORMDatabase::makeCondition($schema, $table, $column, '=', $value)
				);
				$params[] = $value;

				$params = ORMDatabase::injectFromAndGroupByClauses($db, $schema, $params, $related_table);

			// Many-to-many relationships allow counting just from the join table
			} else {
				$params = array(self::escape(
					$db,
					"SELECT count(*) FROM %r WHERE %r = ",
					self::relationshipString($relationship, 'join_table'),
					self::relationshipString($relationship, 'join_column')
				));

				$params[0] .= self::placeholder($schema, $table, $column);
				$params[] = $value;
			}

			$result = self::runQuery($db, $params);

			$count = ($result->valid()) ? self::countValue($result->fetchScalar()) : 0;
		}

		self::setCount($class, $related_records, $related_class, $count, $route);

		return $count;
	}


	/**
	 * Builds the object for the related class specified
	 *
	 * @internal
	 *
	 * @param  string $class             The class to create the related record for
	 * @param  RelatedValues  $values            The values existing in the ActiveRecord class
	 * @param  RelatedRecords  &$related_records  The related records for the record
	 * @param  string $related_class     The related class name
	 * @param  ?string $route            The route to the related class
	 * @return ActiveRecord  An instance of the class specified
	 */
	public static function createRecord(string $class, array $values, array &$related_records, string $related_class, ?string $route = null): ActiveRecord
	{
		ActiveRecord::validateClass($related_class);
		ActiveRecord::forceConfigure($related_class);

		$schema        = ORMSchema::retrieve($class);
		$table         = ORM::tablize($class);
		$related_table = ORM::tablize($related_class);

		$relationship = ORMSchema::getRoute($schema, $table, $related_table, $route, '*-to-one');
		$route          = self::relationshipString($relationship, 'column');
		$related_column = self::relationshipString($relationship, 'related_column');

		// Determine if the relationship is one-to-one
		if (isset(self::$cache['one-to-one::' . $table . '::' . $related_table . '::' . $route])) {
			$one_to_one = self::$cache['one-to-one::' . $table . '::' . $related_table . '::' . $route];
		} else {
			$one_to_one = false;
			$one_to_one_relationships = ORMSchema::getRoutes($schema, $table, $related_table, 'one-to-one');
			foreach ($one_to_one_relationships as $one_to_one_relationship) {
				if ($route == $one_to_one_relationship['column']) {
					$one_to_one = true;
					break;
				}
			}

			self::$cache['one-to-one::' . $table . '::' . $related_table . '::' . $route] = $one_to_one;
		}

		// One-to-one records are stored in the related records array to support populating
		if ($one_to_one) {
			if (isset($related_records[$related_table][$route]['record_set'])) {
				if ($related_records[$related_table][$route]['record_set']->count()) {
					return $related_records[$related_table][$route]['record_set'][0];
				}
				return new $related_class();
			}

			// If the value is NULL, don't pass it to the constructor because an NotFoundException will be thrown
			if ($values[$route] !== null) {
				try {
					$records = array(new $related_class(array($related_column => $values[$route])));
				} catch (NotFoundException $e) {
					$records = array();
				}
			} else {
				$records = array();
			}
			$record_set = RecordSet::buildFromArray($related_class, $records);
			self::setRecordSet($class, $related_records, $related_class, $record_set, $route);

			if ($record_set->count()) {
				return $record_set[0];
			}
			return new $related_class();
		}

		// This allows records without a related record to return a non-existent one
		if ($values[$route] === null) {
			return new $related_class();
		}

		return new $related_class(array($related_column => $values[$route]));
	}



	/**
	 * Figures out the first primary key column for a related class that is not the related column
	 *
	 * @internal
	 *
	 * @param  string $class          The class name of the main class
	 * @param  string $related_class  The related class being filtered for
	 * @param  ?string $route         The route to the related class
	 * @return string  The first primary key column in the related class
	 */
	public static function determineFirstPKColumn(string $class, string $related_class, ?string $route = null): string
	{
		$table         = ORM::tablize($class);
		$related_table = ORM::tablize($related_class);

		$schema        = ORMSchema::retrieve($class);
		$pk_columns    = $schema->getKeys($related_table, 'primary');

		// If there is a multi-fiend primary key we want to populate based on any field BUT the foreign key to the current class
		if (sizeof($pk_columns) > 1) {
			$first_pk_column = null;
			$relationships   = ORMSchema::getRoutes($schema, $related_table, $table, '*-to-one');
			foreach ($pk_columns as $pk_column) {
				foreach ($relationships as $relationship) {
					if ($pk_column == $relationship['column']) {
						continue;
					}
					$first_pk_column = $pk_column;
					break 2;
				}
			}

			if (!$first_pk_column) {
				$first_pk_column = $pk_columns[0];
			}
		} else {
			$first_pk_column = $pk_columns[0];
		}

		return $first_pk_column;
	}


	/**
	 * Figures out what filter to pass to Request::filter() for the specified related class
	 *
	 * @internal
	 *
	 * @param  string $class          The class name of the main class
	 * @param  string $related_class  The related class being filtered for
	 * @param  ?string $route         The route to the related class
	 * @return string  The prefix to filter the request fields by
	 */
	public static function determineRequestFilter(string $class, string $related_class, ?string $route = null): string
	{
		$table           = ORM::tablize($class);
		$schema          = ORMSchema::retrieve($class);

		$related_table   = ORM::tablize($related_class);
		$relationship    = ORMSchema::getRoute($schema, $table, $related_table, $route);

		$route_name    	 = ORMSchema::getRouteNameFromRelationship('one-to-many', $relationship);

		$primary_keys    = $schema->getKeys($related_table, 'primary');
		$first_pk_column = $primary_keys[0];

		$filter_class            = Grammar::pluralize(Grammar::underscorize($related_class));
		$filter_class_with_route = $filter_class . '{' . $route_name . '}';

		$pk_field            = $filter_class . '::' . $first_pk_column;
		$pk_field_with_route = $filter_class_with_route . '::' . $first_pk_column;

		if (!Request::check($pk_field) && Request::check($pk_field_with_route)) {
			$filter_class = $filter_class_with_route;
		}

		return $filter_class . '::';
	}


	/**
	 * Sets the related records for a *-to-many relationship to be associated upon ActiveRecord::store()
	 *
	 * @internal
	 *
	 * @param  string $class             The class to associate the related records to
	 * @param  RelatedRecords  &$related_records  The related records existing for the ActiveRecord class
	 * @param  string $related_class     The class we are associating with the current record
	 * @param  string $route             The route to use between the current class and the related class
	 * @return void
	 */
	public static function flagForAssociation(string $class, array &$related_records, string $related_class, ?string $route = null): void
	{
		$table         = ORM::tablize($class);
		$related_table = ORM::tablize($related_class);

		$schema = ORMSchema::retrieve($class);
		$route  = ORMSchema::getRouteName($schema, $table, $related_table, $route, '!many-to-one');

		if (!isset($related_records[$related_table][$route]['record_set']) && !isset($related_records[$related_table][$route]['primary_keys'])) {
			throw new ProgrammerException(
				'%1$s can only be called after %2$s or %3$s',
				__CLASS__ . '::flagForAssociation()',
				__CLASS__ . '::setRecordSet()',
				__CLASS__ . '::setPrimaryKeys()'
			);
		}

		$related_records[$related_table][$route]['associate'] = true;
	}


	/**
	 * Gets the ordering to use when returning an RecordSet of related objects
	 *
	 * @internal
	 *
	 * @param  string $class          The class to get the order bys for
	 * @param  string $related_class  The related class the ordering rules apply to
	 * @param  string $route          The route to the related table, should be a column name in the current table or a join table name
	 * @return array<string, string>  An array of the order bys - see RecordSet::build() for format
	 */
	public static function getOrderBys(string $class, string $related_class, string $route): array
	{
		$table         = ORM::tablize($class);
		$related_table = ORM::tablize($related_class);

		$schema = ORMSchema::retrieve($class);
		$route  = ORMSchema::getRouteName($schema, $table, $related_table, $route);

		if (!isset(self::$order_bys[$table][$related_table]) || !isset(self::$order_bys[$table][$related_table][$route])) {
			return array();
		}

		return self::$order_bys[$table][$related_table][$route];
	}


	/**
	 * Gets the primary keys of the related records for *-to-many relationships
	 *
	 * @internal
	 *
	 * @param  string $class             The class to get the related primary keys for
	 * @param  RelatedValues  &$values           The values for the ActiveRecord class
	 * @param  RelatedRecords  &$related_records  The related records existing for the ActiveRecord class
	 * @param  string $related_class     The class that is related to the current record
	 * @param  string $route             The route to follow for the class specified
	 * @return array<int|string, mixed>  The primary keys of the related records
	 */
	public static function getPrimaryKeys(string $class, array &$values, array &$related_records, string $related_class, ?string $route = null): array
	{
		ActiveRecord::validateClass($related_class);
		ActiveRecord::forceConfigure($related_class);

		$table         = ORM::tablize($class);
		$related_table = ORM::tablize($related_class);

		$db     = ORMDatabase::retrieve($class, 'read');
		$schema = ORMSchema::retrieve($class);

		$route = ORMSchema::getRouteName($schema, $table, $related_table, $route, '*-to-many');

		if (!isset($related_records[$related_table])) {
			$related_records[$related_table] = array();
		}
		if (!isset($related_records[$related_table][$route])) {
			$related_records[$related_table][$route] = array();
		}

		$related_info =& $related_records[$related_table][$route];
		if (!isset($related_info['primary_keys'])) {
			if (isset($related_info['record_set'])) {
				$related_info['primary_keys'] = $related_info['record_set']->getPrimaryKeys();

			// If we don't have a record set yet we want to use a single SQL query to just get the primary keys
			} else {
				$relationship       = ORMSchema::getRoute($schema, $table, $related_table, $route, '*-to-many');
				$related_pk_columns = $schema->getKeys($related_table, 'primary');
				$column             = self::relationshipString($relationship, 'column');

				$aliased_related_pk_columns = array();
				foreach ($related_pk_columns as $related_pk_column) {
					// We explicitly alias the columns due to SQLite issues
					$aliased_related_pk_columns[] = self::escape($db, "%r AS %r", $related_table . '.' . $related_pk_column, $related_pk_column);
				}

				if (isset($relationship['join_table'])) {
					$table_with_route = $table . '{' . self::relationshipString($relationship, 'join_table') . '}';
				} else {
					$table_with_route = $table . '{' . self::relationshipString($relationship, 'related_column') . '}';
				}

				$params = array(
					self::escape(
						$db,
						sprintf(
							"SELECT %s FROM :from_clause WHERE",
							join(', ', $aliased_related_pk_columns)
						) . " %r = ",
						$table_with_route . '.' . $column
					),
				);
				$params[0] .= self::placeholder($schema, $table, $column);
				$params[] = $values[$column];

				$params[0] .= " :group_by_clause ";

				if (!$order_bys = self::getOrderBys($class, $related_class, $route)) {
					$order_bys = array();
					foreach ($related_pk_columns as $related_pk_column) {
						$order_bys[$related_pk_column] = 'ASC';
					}
				}
				$params[0] .= " ORDER BY ";
				$params = ORMDatabase::addOrderByClause($db, $schema, $params, $related_table, $order_bys);

				$params = ORMDatabase::injectFromAndGroupByClauses($db, $schema, $params, $related_table);

				$result = self::runQuery($db, $params);

				$primary_keys = array();

				foreach ($result->fetchAllRows() as $row) {
					if (!is_array($row)) {
						continue;
					}

					if (sizeof($row) > 1) {
						$primary_key = array();
						foreach ($row as $column => $value) {
							if (is_string($column)) {
								$primary_key[$column] = self::unescapeValue($db, $schema, $related_table, $column, $value);
							}
						}
						$primary_keys[] = $primary_key;
					} else {
						$column = key($row);
						if (is_string($column)) {
							$primary_keys[] = self::unescapeValue($db, $schema, $related_table, $column, $row[$column]);
						}
					}
				}

				$related_info['record_set']   = null;
				$related_info['count']        = sizeof($primary_keys);
				$related_info['associate']    = false;
				$related_info['primary_keys'] = $primary_keys;
			}
		}

		return $related_info['primary_keys'];
	}


	/**
	 * Returns the record name for a related class
	 *
	 * The default record name of a related class is the result of
	 * Grammar::humanize() called on the class.
	 *
	 * @internal
	 *
	 * @param  string $class          The class to get the related class name for
	 * @param  string $related_class  The related class to get the record name of
	 * @param  ?string $route  The route to the related class, if any
	 * @return string  The record name for the related class specified
	 */
	public static function getRelatedRecordName(string $class, string $related_class, ?string $route = null): string
	{
		ActiveRecord::validateClass($related_class);
		ActiveRecord::forceConfigure($related_class);

		$table         = ORM::tablize($class);
		$related_table = ORM::tablize($related_class);

		$schema = ORMSchema::retrieve($class);
		$route  = ORMSchema::getRouteName($schema, $table, $related_table, $route);

		if (
            !isset(self::$related_record_names[$table]) ||
			  !isset(self::$related_record_names[$table][$related_class]) ||
			  !isset(self::$related_record_names[$table][$related_class][$route])
        ) {
			return ORM::getRecordName($related_class);
		}

		// If Text is loaded, use it
		if (class_exists('Text', false)) {
			return call_user_func(
				array('Text', 'compose'),
				str_replace('%', '%%', self::$related_record_names[$table][$related_class][$route])
			);
		}

		return self::$related_record_names[$table][$related_class][$route];
	}


	/**
	 * Indicates if a record has a one-to-one or any *-to-many related records
	 *
	 * @internal
	 *
	 * @param  string $class             The class to check related records for
	 * @param  RelatedValues  &$values           The values for the record we are checking
	 * @param  RelatedRecords  &$related_records  The related records for the record we are checking
	 * @param  string $related_class     The related class we are checking for
	 * @param  string $route             The route to the related class
	 * @return bool  Whether the related records exist for the record specified
	 */
	public static function hasRecords(string $class, array &$values, array &$related_records, string $related_class, ?string $route = null): bool
	{
		ActiveRecord::validateClass($related_class);
		ActiveRecord::forceConfigure($related_class);

		$table         = ORM::tablize($class);
		$related_table = ORM::tablize($related_class);

		$schema = ORMSchema::retrieve($class);
		$route  = ORMSchema::getRouteName($schema, $table, $related_table, $route, '!many-to-one');

		if (!isset($related_records[$related_table][$route]['count'])) {
			if (ORMSchema::isOneToOne($schema, $table, $related_table, $route)) {
				self::createRecord($class, $values, $related_records, $related_class, $route);
			} else {
				self::countRecords($class, $values, $related_records, $related_class, $route);
			}
		}

		return (bool) ($related_records[$related_table][$route]['count'] ?? null);
	}


	/**
	 * Parses associations for many-to-many relationships from the page request
	 *
	 * @internal
	 *
	 * @param  string $class             The class to get link the related records to
	 * @param  RelatedRecords  &$related_records  The related records existing for the ActiveRecord class
	 * @param  string $related_class     The related class to populate
	 * @param  string $route             The route to the related class
	 * @return void
	 */
	public static function linkRecords(string $class, array &$related_records, string $related_class, ?string $route = null): void
	{
		ActiveRecord::validateClass($related_class);
		ActiveRecord::forceConfigure($related_class);

		$table         = ORM::tablize($class);
		$related_table = ORM::tablize($related_class);

		$schema       = ORMSchema::retrieve($class);
		$route_name   = ORMSchema::getRouteName($schema, $table, $related_table, $route, 'many-to-many');
		$relationship = ORMSchema::getRoute($schema, $table, $related_table, $route, 'many-to-many');

		$field_table      = self::relationshipString($relationship, 'related_table');
		$field_column     = '::' . self::relationshipString($relationship, 'related_column');

		$field            = $field_table . $field_column;
		$field_with_route = $field_table . '{' . $route_name . '}' . $field_column;

		// If there is only one route and they specified the route instead of leaving it off, use that
		if ($route !== null || (!Request::check($field) && Request::check($field_with_route))) {
			$field = $field_with_route;
		}

		$record_set = RecordSet::build(
			$related_class,
			array(
				self::relationshipString($relationship, 'related_column') . '=' => Request::get($field, 'array', array())
			)
		);

		self::associateRecords($class, $related_records, $related_class, $record_set, $route_name);
	}


	/**
	 * Does an [http://php.net/array_diff array_diff()] for two arrays that have arrays as values
	 *
	 * @param  array<int|string, mixed> $array1  The array to remove items from
	 * @param  array<int|string, mixed> $array2  The array of items to remove
	 * @return array<int|string, mixed>  The items in `$array1` that were not also in `$array2`
	 */
	private static function multidimensionArrayDiff(array $array1, array $array2): array
	{
		$output = array();
		foreach ($array1 as $sub_array1) {
			$remove = false;
			foreach ($array2 as $sub_array2) {
				if ($sub_array1 == $sub_array2) {
					$remove = true;
				}
			}
			if (!$remove) {
				$output[] = $sub_array1;
			}
		}
		return $output;
	}


	/**
	 * Allows overriding of default record names or related records
	 *
	 * The default record name of a related record is the result of
	 * Grammar::humanize() called on the class name.
	 *
	 * @param  mixed  $class           The class name or instance of the class to set the related record name for
	 * @param  string $related_class   The name of the related class
	 * @param  string $record_name     The human version of the related record
	 * @param  ?string $route          The route to the related class
	 * @return void
	 */
	public static function overrideRelatedRecordName(mixed $class, string $related_class, string $record_name, ?string $route = null): void
	{
		ActiveRecord::validateClass($related_class);
		ActiveRecord::forceConfigure($related_class);

		$class         = ORM::getClass($class);
		$table         = ORM::tablize($class);

		$related_class = ORM::getClass($related_class);
		$related_class = ORM::getRelatedClass($class, $related_class);
		$related_table = ORM::tablize($related_class);

		$schema = ORMSchema::retrieve($class);
		$route  = ORMSchema::getRouteName($schema, $table, $related_table, $route, '*-to-many');

		if (!isset(self::$related_record_names[$table])) {
			self::$related_record_names[$table] = array();
		}

		if (!isset(self::$related_record_names[$table][$related_class])) {
			self::$related_record_names[$table][$related_class] = array();
		}

		self::$related_record_names[$table][$related_class][$route] = $record_name;
	}


	/**
	 * Sets the values for records in a one-to-many relationship with this record
	 *
	 * @internal
	 *
	 * @param  string  $class             The class to populate the related records of
	 * @param  RelatedRecords   &$related_records  The related records existing for the ActiveRecord class
	 * @param  string  $related_class     The related class to populate
	 * @param  string  $route             The route to the related class
	 * @param  boolean $recursive         If a recursive populate should be performed on the child records
	 * @return void
	 */
	public static function populateRecords(string $class, array &$related_records, string $related_class, ?string $route = null, bool $recursive = false): void
	{
		ActiveRecord::validateClass($related_class);
		ActiveRecord::forceConfigure($related_class);

		$table           = ORM::tablize($class);
		$related_table   = ORM::tablize($related_class);
		$schema          = ORMSchema::retrieve($class);
		$pk_columns      = $schema->getKeys($related_table, 'primary');

		$first_pk_column = self::determineFirstPKColumn($class, $related_class, $route);

		$filter          = self::determineRequestFilter($class, $related_class, $route);
		$pk_field        = $filter . $first_pk_column;

		$pk_input   = Request::get($pk_field, 'array', array());
		$input_keys = is_array($pk_input) ? array_keys($pk_input) : array();
		$records    = array();

		foreach ($input_keys as $input_key) {
			Request::filter($filter, $input_key);

			// Try to load the value from the database first
			try {
				if (sizeof($pk_columns) == 1) {
					$primary_key_values = Request::get($first_pk_column);
				} else {
					$primary_key_values = array();
					foreach ($pk_columns as $pk_column) {
						$primary_key_values[$pk_column] = Request::get($pk_column);
					}
				}

				$record = new $related_class($primary_key_values);
			} catch (NotFoundException $e) {
				$record = new $related_class();
			}

			$record->populate($recursive);
			$records[] = $record;

			Request::unfilter();
		}

		$record_set = RecordSet::buildFromArray($related_class, $records);
		self::setRecordSet($class, $related_records, $related_class, $record_set, $route);
		self::flagForAssociation($class, $related_records, $related_class, $route);
	}


	/**
	 * Adds information about methods provided by this class to ActiveRecord
	 *
	 * @internal
	 *
	 * @param  string  $class                 The class to reflect the related record methods for
	 * @param  array<string, string>  &$signatures  The associative array of `{method_name} => {signature}`
	 * @param  boolean $include_doc_comments  If the doc block comments for each method should be included
	 * @return void
	 */
	public static function reflect(string $class, array &$signatures, bool $include_doc_comments): void
	{
		$table  = ORM::tablize($class);
		$schema = ORMSchema::retrieve($class);

		$one_to_one_relationships   = $schema->getRelationships($table, 'one-to-one');
		$one_to_many_relationships  = $schema->getRelationships($table, 'one-to-many');
		$many_to_one_relationships  = $schema->getRelationships($table, 'many-to-one');
		$many_to_many_relationships = $schema->getRelationships($table, 'many-to-many');

		$to_one_relationships  = array_merge($one_to_one_relationships, $many_to_one_relationships);
		$to_many_relationships = array_merge($one_to_many_relationships, $many_to_many_relationships);

		$to_one_created = array();

		foreach ($to_one_relationships as $relationship) {
			$related_table_name = self::relationshipString($relationship, 'related_table');
			$related_class = ORM::classize($related_table_name);
			$related_class = ORM::getRelatedClass($class, $related_class);

			if (isset($to_one_created[$related_class])) {
				continue;
			}

			$routes = ORMSchema::getRoutes($schema, $table, $related_table_name, '*-to-one');
			$route_names = array();

			foreach ($routes as $route) {
				$route_names[] = ORMSchema::getRouteNameFromRelationship('*-to-one', $route);
			}

			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Creates the related " . $related_class . "\n";
				$signature .= " * \n";
				if (sizeof($route_names) > 1) {
					$signature .= " * @param  string \$route  The route to the related class. Must be one of: '" . join("', '", $route_names) . "'.\n";
				}
				$signature .= " * @return " . $related_class . "  The related object\n";
				$signature .= " */\n";
			}
			$create_method = 'create' . $related_class;
			$signature .= 'public function ' . $create_method . '(';
			if (sizeof($route_names) > 1) {
				$signature .= '$route';
			}
			$signature .= ')';

			$signatures[$create_method] = $signature;

			$to_one_created[$related_class] = true;
		}

		$one_to_one_created = array();

		foreach ($one_to_one_relationships as $relationship) {
			$related_table_name = self::relationshipString($relationship, 'related_table');
			$related_class = ORM::classize($related_table_name);
			$related_class = ORM::getRelatedClass($class, $related_class);

			if (isset($one_to_one_created[$related_class])) {
				continue;
			}

			$routes = ORMSchema::getRoutes($schema, $table, $related_table_name, 'one-to-one');
			$route_names = array();

			foreach ($routes as $route) {
				$route_names[] = ORMSchema::getRouteNameFromRelationship('one-to-one', $route);
			}

			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Populates the related " . $related_class . "\n";
				$signature .= " * \n";
				if (sizeof($route_names) > 1) {
					$signature .= " * @param  string \$route  The route to the related class. Must be one of: '" . join("', '", $route_names) . "'.\n";
				}
				$signature .= " * @return ActiveRecord  The record object, to allow for method chaining\n";
				$signature .= " */\n";
			}
			$populate_method = 'populate' . $related_class;
			$signature .= 'public function ' . $populate_method . '(';
			if (sizeof($route_names) > 1) {
				$signature .= '$route';
			}
			$signature .= ')';

			$signatures[$populate_method] = $signature;

			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Associates the related " . $related_class . " to this record\n";
				$signature .= " * \n";
				$signature .= " * @param  ActiveRecord|array|string|integer \$record  The record, or the primary key of the record, to associate\n";
				if (sizeof($route_names) > 1) {
					$signature .= " * @param  string \$route  The route to the related class. Must be one of: '" . join("', '", $route_names) . "'.\n";
				}
				$signature .= " * @return ActiveRecord  The record object, to allow for method chaining\n";
				$signature .= " */\n";
			}
			$associate_method = 'associate' . $related_class;
			$signature .= 'public function ' . $associate_method . '($record';
			if (sizeof($route_names) > 1) {
				$signature .= ', $route';
			}
			$signature .= ')';

			$signatures[$associate_method] = $signature;

			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Indicates if a related " . $related_class . " exists\n";
				$signature .= " * \n";
				if (sizeof($route_names) > 1) {
					$signature .= " * @param  string \$route  The route to the related class. Must be one of: '" . join("', '", $route_names) . "'.\n";
				}
				$signature .= " * @return boolean  If a related record exists\n";
				$signature .= " */\n";
			}
			$has_method = 'has' . $related_class;
			$signature .= 'public function ' . $has_method . '($record';
			if (sizeof($route_names) > 1) {
				$signature .= ', $route';
			}
			$signature .= ')';

			$signatures[$has_method] = $signature;

			$one_to_one_created[$related_class] = true;
		}

		$to_many_created = array();

		foreach ($to_many_relationships as $relationship) {
			$related_table_name = self::relationshipString($relationship, 'related_table');
			$related_class = ORM::classize($related_table_name);
			$related_class = ORM::getRelatedClass($class, $related_class);

			if (isset($to_many_created[$related_class])) {
				continue;
			}

			$routes = ORMSchema::getRoutes($schema, $table, $related_table_name, '*-to-many');
			$route_names = array();

			$many_to_many_route_names = array();
			$one_to_many_route_names  = array();

			foreach ($routes as $route) {
				if (isset($route['join_table'])) {
					$route_name = ORMSchema::getRouteNameFromRelationship('many-to-many', $route);
					$route_names[]              = $route_name;
					$many_to_many_route_names[] = $route_name;
				} else {
					$route_name = ORMSchema::getRouteNameFromRelationship('one-to-many', $route);
					$route_names[]             = $route_name;
					$one_to_many_route_names[] = $route_name;
				}
			}

			if ($one_to_many_route_names) {
				$signature = '';
				if ($include_doc_comments) {
					$related_table = ORM::tablize($related_class);

					$signature .= "/**\n";
					$signature .= " * Calls the ::populate() method for multiple child " . $related_class . " records. Uses request value arrays in the form " . $related_table . "::{column_name}[].\n";
					$signature .= " * \n";
					if (sizeof($one_to_many_route_names) > 1) {
						$signature .= " * @param  string \$route  The route to the related class. Must be one of: '" . join("', '", $one_to_many_route_names) . "'.\n";
					}
					$signature .= " * @return ActiveRecord  The record object, to allow for method chaining\n";
					$signature .= " */\n";
				}
				$populate_related_method = 'populate' . Grammar::pluralize($related_class);
				$signature .= 'public function ' . $populate_related_method . '(';
				if (sizeof($one_to_many_route_names) > 1) {
					$signature .= '$route';
				}
				$signature .= ')';

				$signatures[$populate_related_method] = $signature;
			}


			if ($many_to_many_route_names) {
				$signature = '';
				if ($include_doc_comments) {
					$related_table = ORM::tablize($related_class);

					$signature .= "/**\n";
					$signature .= " * Creates entries in the appropriate joining table to create associations with the specified " . $related_class . " records. Uses request value array(s) in the form " . $related_table . "::{primary_key_column_name(s)}[].\n";
					$signature .= " * \n";
					if (sizeof($many_to_many_route_names) > 1) {
						$signature .= " * @param  string \$route  The route to the related class. Must be one of: '" . join("', '", $many_to_many_route_names) . "'.\n";
					}
					$signature .= " * @return ActiveRecord  The record object, to allow for method chaining\n";
					$signature .= " */\n";
				}
				$link_related_method = 'link' . Grammar::pluralize($related_class);
				$signature .= 'public function ' . $link_related_method . '(';
				if (sizeof($many_to_many_route_names) > 1) {
					$signature .= '$route';
				}
				$signature .= ')';

				$signatures[$link_related_method] = $signature;


				$signature = '';
				if ($include_doc_comments) {
					$related_table = ORM::tablize($related_class);

					$signature .= "/**\n";
					$signature .= " * Creates entries in the appropriate joining table to create associations with the specified " . $related_class . " records\n";
					$signature .= " * \n";
					$signature .= " * @param  RecordSet|array \$records_to_associate  The records to associate - should be a RecordSet, an array of records or an array of primary keys\n";
					if (sizeof($many_to_many_route_names) > 1) {
						$signature .= " * @param  string           \$route  The route to the related class. Must be one of: '" . join("', '", $many_to_many_route_names) . "'.\n";
					}
					$signature .= " * @return ActiveRecord  The record object, to allow for method chaining\n";
					$signature .= " */\n";
				}
				$associate_related_method = 'associate' . Grammar::pluralize($related_class);
				$signature .= 'public function ' . $associate_related_method . '($records_to_associate';
				if (sizeof($many_to_many_route_names) > 1) {
					$signature .= ', $route';
				}
				$signature .= ')';

				$signatures[$associate_related_method] = $signature;
			}


			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Builds an RecordSet of the related " . $related_class . " objects\n";
				$signature .= " * \n";
				if (sizeof($route_names) > 1) {
					$signature .= " * @param  string \$route  The route to the related class. Must be one of: '" . join("', '", $route_names) . "'.\n";
				}
				$signature .= " * @return RecordSet  A record set of the related " . $related_class . " objects\n";
				$signature .= " */\n";
			}
			$build_method = 'build' . Grammar::pluralize($related_class);
			$signature .= 'public function ' . $build_method . '(';
			if (sizeof($route_names) > 1) {
				$signature .= '$route';
			}
			$signature .= ')';

			$signatures[$build_method] = $signature;

			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Indicates if related " . $related_class . " objects exist\n";
				$signature .= " * \n";
				if (sizeof($route_names) > 1) {
					$signature .= " * @param  string \$route  The route to the related class. Must be one of: '" . join("', '", $route_names) . "'.\n";
				}
				$signature .= " * @return boolean  If related " . $related_class . " objects exist\n";
				$signature .= " */\n";
			}
			$has_method = 'has' . Grammar::pluralize($related_class);
			$signature .= 'public function ' . $has_method . '(';
			if (sizeof($route_names) > 1) {
				$signature .= '$route';
			}
			$signature .= ')';

			$signatures[$has_method] = $signature;

			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Returns an array of the primary keys for the related " . $related_class . " objects\n";
				$signature .= " * \n";
				if (sizeof($route_names) > 1) {
					$signature .= " * @param  string \$route  The route to the related class. Must be one of: '" . join("', '", $route_names) . "'.\n";
				}
				$signature .= " * @return array  The primary keys of the related " . $related_class . " objects\n";
				$signature .= " */\n";
			}
			$list_method = 'list' . Grammar::pluralize($related_class);
			$signature .= 'public function ' . $list_method . '(';
			if (sizeof($route_names) > 1) {
				$signature .= '$route';
			}
			$signature .= ')';

			$signatures[$list_method] = $signature;


			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Counts the number of related " . $related_class . " objects\n";
				$signature .= " * \n";
				if (sizeof($route_names) > 1) {
					$signature .= " * @param  string \$route  The route to the related class. Must be one of: '" . join("', '", $route_names) . "'.\n";
				}
				$signature .= " * @return integer  The number related " . $related_class . " objects\n";
				$signature .= " */\n";
			}
			$count_method = 'count' . Grammar::pluralize($related_class);
			$signature .= 'public function ' . $count_method . '(';
			if (sizeof($route_names) > 1) {
				$signature .= '$route';
			}
			$signature .= ')';

			$signatures[$count_method] = $signature;


			$to_many_created[$related_class] = true;
		}
	}


	/**
	 * Registers a method to use to get a name for a related record when doing validation
	 *
	 * @param string|ActiveRecord $class          The class to register the method for
	 * @param string               $related_class  The related class to register the method for
	 * @param string               $method         The method to be called on the related class that will return the name
	 * @param ?string              $route          The route to the related class
	 * @return void
	 */
	public static function registerValidationNameMethod(mixed $class, string $related_class, string $method, ?string $route = null): void
	{
		$class         = ORM::getClass($class);
		$table         = ORM::tablize($class);
		$related_table = ORM::tablize($related_class);
		$schema        = ORMSchema::retrieve($class);
		$route         = ORMSchema::getRouteName($schema, $table, $related_table, $route, 'one-to-many');

		if (!isset(self::$validation_name_methods[$class])) {
			self::$validation_name_methods[$class] = array();
		}
		if (!isset(self::$validation_name_methods[$class][$related_class])) {
			self::$validation_name_methods[$class][$related_class] = array();
		}

		self::$validation_name_methods[$class][$related_class][$route] = $method;
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
		self::$cache                = array();
		self::$order_bys            = array();
		self::$related_record_names = array();
	}


	/**
	 * Sets the ordering to use when returning an RecordSet of related objects
	 *
	 * @param  mixed  $class           The class name or instance of the class this ordering rule applies to
	 * @param  string $related_class   The related class we are getting info from
	 * @param  array<string, string>  $order_bys       An array of the order bys for this table.column combination - see RecordSet::build() for format
	 * @param  string $route           The route to the related table, this should be a column name in the current table or a join table name
	 * @return void
	 */
	public static function setOrderBys(mixed $class, string $related_class, array $order_bys, ?string $route = null): void
	{
		ActiveRecord::validateClass($related_class);
		ActiveRecord::forceConfigure($related_class);

		$class         = ORM::getClass($class);
		$table         = ORM::tablize($class);

		$related_class = ORM::getRelatedClass($class, $related_class);
		$related_table = ORM::tablize($related_class);

		$schema = ORMSchema::retrieve($class);
		$route  = ORMSchema::getRouteName($schema, $table, $related_table, $route, '*-to-many');

		if (!isset(self::$order_bys[$table])) {
			self::$order_bys[$table] = array();
		}

		if (!isset(self::$order_bys[$table][$related_table])) {
			self::$order_bys[$table][$related_table] = array();
		}

		self::$order_bys[$table][$related_table][$route] = $order_bys;
	}


	/**
	 * Records the number of related one-to-many or many-to-many records
	 *
	 * @internal
	 *
	 * @param  string  $class             The class to set the related records count for
	 * @param  RelatedRecords   &$related_records  The related records existing for the ActiveRecord class
	 * @param  string  $related_class     The class that is related to the current record
	 * @param  integer $count             The number of records
	 * @param  string  $route             The route to follow for the class specified
	 * @return void
	 */
	public static function setCount(string $class, array &$related_records, string $related_class, int $count, ?string $route = null): void
	{
		ActiveRecord::validateClass($related_class);
		ActiveRecord::forceConfigure($related_class);

		$table         = ORM::tablize($class);
		$related_table = ORM::tablize($related_class);

		$schema = ORMSchema::retrieve($class);
		$route  = ORMSchema::getRouteName($schema, $table, $related_table, $route, '*-to-many');

		// Cache the results for subsequent calls
		if (!isset($related_records[$related_table])) {
			$related_records[$related_table] = array();
		}
		if (!isset($related_records[$related_table][$route])) {
			$related_records[$related_table][$route] = array();
		}

		if (!isset($related_records[$related_table][$route]['record_set'])) {
			$related_records[$related_table][$route]['record_set']   = null;
			$related_records[$related_table][$route]['associate']    = false;
			$related_records[$related_table][$route]['primary_keys'] = null;
		}

		$related_records[$related_table][$route]['count'] = $count;
	}


	/**
	 * Sets the related records for *-to-many relationships, providing only primary keys
	 *
	 * @internal
	 *
	 * @param  string $class             The class to set the related primary keys for
	 * @param  RelatedRecords  &$related_records  The related records existing for the ActiveRecord class
	 * @param  string $related_class     The class we are setting the records for
	 * @param  array<int|string, mixed>  $primary_keys      The records to set
	 * @param  string $route             The route to use between the current class and the related class
	 * @return void
	 */
	public static function setPrimaryKeys(string $class, array &$related_records, string $related_class, array $primary_keys, ?string $route = null): void
	{
		ActiveRecord::validateClass($related_class);
		ActiveRecord::forceConfigure($related_class);

		$table         = ORM::tablize($class);
		$related_table = ORM::tablize($related_class);

		$schema = ORMSchema::retrieve($class);
		$route  = ORMSchema::getRouteName($schema, $table, $related_table, $route, '*-to-many');

		if (!isset($related_records[$related_table])) {
			$related_records[$related_table] = array();
		}
		if (!isset($related_records[$related_table][$route])) {
			$related_records[$related_table][$route] = array();
		}

		$related_records[$related_table][$route]['record_set']   = null;
		$related_records[$related_table][$route]['count']        = sizeof($primary_keys);
		$related_records[$related_table][$route]['associate']    = false;
		$related_records[$related_table][$route]['primary_keys'] = $primary_keys;
	}


	/**
	 * Sets the related records for *-to-many relationships
	 *
	 * @internal
	 *
	 * @param  string $class             The class to set the related records for
	 * @param  RelatedRecords  &$related_records  The related records existing for the ActiveRecord class
	 * @param  string $related_class     The class we are associating with the current record
	 * @param  RecordSet $records       The records are associating
	 * @param  string $route             The route to use between the current class and the related class
	 * @return void
	 */
	public static function setRecordSet(string $class, array &$related_records, string $related_class, RecordSet $records, ?string $route = null): void
	{
		ActiveRecord::validateClass($related_class);
		ActiveRecord::forceConfigure($related_class);

		$table         = ORM::tablize($class);
		$related_table = ORM::tablize($related_class);

		$schema = ORMSchema::retrieve($class);
		$route  = ORMSchema::getRouteName($schema, $table, $related_table, $route, '!many-to-one');

		if (!isset($related_records[$related_table])) {
			$related_records[$related_table] = array();
		}
		if (!isset($related_records[$related_table][$route])) {
			$related_records[$related_table][$route] = array();
		}

		$related_records[$related_table][$route]['record_set']   = $records;
		$related_records[$related_table][$route]['count']        = $records->count();
		$related_records[$related_table][$route]['associate']    = false;
		$related_records[$related_table][$route]['primary_keys'] = null;
	}


	/**
	 * Stores any many-to-many associations or any one-to-many records that have been flagged for association
	 *
	 * @internal
	 *
	 * @param  string  $class             The class to store the related records for
	 * @param  RelatedValues   &$values           The current values for the main record being stored
	 * @param  RelatedRecords   &$related_records  The related records array
	 * @param  boolean $force_cascade     This flag will be passed to the ActiveRecord::delete() method on related records that are being deleted
	 * @return void
	 */
	public static function store(string $class, array &$values, array &$related_records, bool $force_cascade): void
	{
		$table  = ORM::tablize($class);
		$schema = ORMSchema::retrieve($class);

		foreach ($related_records as $related_table => $relationships) {
			foreach ($relationships as $route => $related_info) {
				if (!($related_info['associate'] ?? false)) {
					continue;
				}

				$relationship = ORMSchema::getRoute($schema, $table, $related_table, $route);
				if (isset($relationship['join_table'])) {
					ORMRelated::storeManyToMany($class, $values, $relationship, $related_info);
				} else {
					$related_class = ORM::classize($related_table);
					$related_class = ORM::getRelatedClass($class, $related_class);
					ORMRelated::storeOneToStar($class, $values, $related_records, $related_class, $route, $force_cascade);
				}
			}
		}
	}


	/**
	 * Associates a set of many-to-many related records with the current record
	 *
	 * @internal
	 *
	 * @param  string $class         The class the relationship is being stored for
	 * @param  RelatedValues  &$values       The current values for the main record being stored
	 * @param  array<string, mixed>  $relationship  The information about the relationship between this object and the records in the record set
	 * @param  RelatedInfo  $related_info  An array containing the keys `'record_set'`, `'count'`, `'primary_keys'` and `'associate'`
	 * @return void
	 */
	public static function storeManyToMany(string $class, array &$values, array $relationship, array $related_info): void
	{
		$db     = ORMDatabase::retrieve($class, 'write');
		$schema = ORMSchema::retrieve($class);

		$column_value      = $values[self::relationshipString($relationship, 'column')];

		// First, we remove all existing relationships between the two tables
		$join_table        = self::relationshipString($relationship, 'join_table');
		$join_column       = self::relationshipString($relationship, 'join_column');

		$params = array(
			"DELETE FROM %r WHERE " . ORMDatabase::makeCondition($schema, $join_table, $join_column, '=', $column_value),
			$join_table,
			$join_column,
			$column_value
		);
		call_user_func_array($db->translatedQuery(...), $params);

		// Then we add back the ones in the record set
		$join_related_column = self::relationshipString($relationship, 'join_related_column');
		$related_table       = self::relationshipString($relationship, 'related_table');
		$related_column      = self::relationshipString($relationship, 'related_column');

		$related_pk_columns  = $schema->getKeys($related_table, 'primary');

		$related_column_values = array();

		// If the related column is the primary key, we can just use the primary keys if we have them
		if ($related_pk_columns[0] == $related_column && ($related_info['primary_keys'] ?? null)) {
			$related_column_values = $related_info['primary_keys'];

		// Otherwise we need to pull the related values out of the record set
		} else {
			$record_set = $related_info['record_set'] ?? null;

			// If there is no record set, build it from the primary keys
			if (!$record_set instanceof RecordSet) {
				$related_class = ORM::classize($related_table);
				$related_class = ORM::getRelatedClass($class, $related_class);
				$record_set    = RecordSet::build($related_class, array($related_pk_columns[0] . '=' => ($related_info['primary_keys'] ?? null)));
			}

			$get_related_method_name = 'get' . Grammar::camelize($related_column, true);

			foreach ($record_set as $record) {
				$related_column_values[] = $record->$get_related_method_name();
			}
		}

		// Ensure we aren't storing duplicates
		$related_column_values = self::uniqueValues($related_column_values);

		$join_column_placeholder    = self::placeholder($schema, $join_table, $join_column);
		$related_column_placeholder = self::placeholder($schema, $join_table, $join_related_column);

		foreach ($related_column_values as $related_column_value) {
			$params = array(
				"INSERT INTO %r (%r, %r) VALUES (" . $join_column_placeholder . ", " . $related_column_placeholder . ")",
				$join_table,
				$join_column,
				$join_related_column,
				$column_value,
				$related_column_value
			);
			call_user_func_array($db->translatedQuery(...), $params);
		}
	}


	/**
	 * Stores a set of one-to-many related records in the database
	 *
	 * @throws ValidationException  When one of the "many" records throws an exception from ActiveRecord::store()
	 * @internal
	 *
	 * @param  string  $class             The class to store the related records for
	 * @param  RelatedValues   &$values           The current values for the main record being stored
	 * @param  RelatedRecords   &$related_records  The related records array
	 * @param  string  $related_class     The related class being stored
	 * @param  string  $route             The route to the related class
	 * @param  boolean $force_cascade     This flag will be passed to the ActiveRecord::delete() method on related records that are being deleted
	 * @return void
	 */
	public static function storeOneToStar(string $class, array &$values, array &$related_records, string $related_class, string $route, bool $force_cascade): void
	{
		ActiveRecord::validateClass($related_class);

		$table         = ORM::tablize($class);
		$related_table = ORM::tablize($related_class);

		$schema        = ORMSchema::retrieve($class);
		$relationship  = ORMSchema::getRoute($schema, $table, $related_table, $route);
		$column        = self::relationshipString($relationship, 'column');
		$related_column = self::relationshipString($relationship, 'related_column');
		$column_value  = $values[$column];

		if (!empty($related_records[$related_table][$route]['record_set'])) {
			$record_set = $related_records[$related_table][$route]['record_set'];
		} else {
			$record_set = self::buildRecords($class, $values, $related_records, $related_class, $route);
		}

		$where_conditions = array(
			$related_column . '=' => $column_value
		);

		$existing_records = RecordSet::build($related_class, $where_conditions);

		$existing_primary_keys  = $existing_records->getPrimaryKeys();
		$new_primary_keys       = $record_set->getPrimaryKeys();

		$primary_keys_to_delete = self::multidimensionArrayDiff($existing_primary_keys, $new_primary_keys);

		foreach ($primary_keys_to_delete as $primary_key_to_delete) {
			$object_to_delete = new $related_class($primary_key_to_delete);

			// don't delete record if the relationship does not call for it
			if ($relationship['on_delete'] == 'set_null') {
				$related_set_method = 'set' . Grammar::camelize($related_column, true);
				$object_to_delete->$related_set_method(null);
				$object_to_delete->store();
				continue;
			}

			$object_to_delete->delete($force_cascade);
		}

		$set_method_name = 'set' . Grammar::camelize($related_column, true);

		$first_pk_column = self::determineFirstPKColumn($class, $related_class, $route);
		$filter          = self::determineRequestFilter(ORM::classize(self::relationshipString($relationship, 'table')), $related_class, $route);
		$pk_field        = $filter . $first_pk_column;
		$pk_input        = Request::get($pk_field, 'array', array());
		$input_keys      = is_array($pk_input) ? array_keys($pk_input) : array();

		// Set all of the values first to prevent issues with recursive relationships
		foreach ($record_set as $i => $record) {
			$record->$set_method_name($column_value);
		}

		foreach ($record_set as $i => $record) {
			Request::filter($filter, isset($input_keys[$i]) ? $input_keys[$i] : $i);
			$record->store();
			Request::unfilter();
		}
	}


	/**
	 * Validates any many-to-many associations or any one-to-many records that have been flagged for association
	 *
	 * @internal
	 *
	 * @param  string $class             The class to validate the related records for
	 * @param  RelatedValues  &$values           The values for the object
	 * @param  RelatedRecords  &$related_records  The related records for the object
	 * @return array<string, string|array{name: string, errors: array<string, string>}>  The validation messages for the related records
	 */
	public static function validate(string $class, array &$values, array &$related_records): array
	{
		$table  = ORM::tablize($class);
		$schema = ORMSchema::retrieve($class);

		$validation_messages = array();

		// Find the record sets to validate
		foreach ($related_records as $related_table => $routes) {
			foreach ($routes as $route => $related_info) {
				if (!($related_info['count'] ?? null) || !($related_info['associate'] ?? null)) {
					continue;
				}

				$related_class = ORM::classize($related_table);
				$related_class = ORM::getRelatedClass($class, $related_class);
				$relationship  = ORMSchema::getRoute($schema, $table, $related_table, $route);

				if (isset($relationship['join_table'])) {
					$related_messages = self::validateManyToMany($class, $related_class, $route, $related_info);
				} else {
					$related_messages = self::validateOneToStar($class, $values, $related_records, $related_class, $route);
				}

				$validation_messages = array_merge($validation_messages, $related_messages);
			}
		}

		return $validation_messages;
	}


	/**
	 * Validates one-to-* related records
	 *
	 * @param  string $class             The class to validate the related records for
	 * @param  RelatedValues  &$values           The values for the object
	 * @param  RelatedRecords  &$related_records  The related records for the object
	 * @param  string $related_class     The name of the class for this record set
	 * @param  string $route             The route between the table and related table
	 * @return array<string, string|array{name: string, errors: array<string, string>}>  An array of validation messages
	 */
	private static function validateOneToStar(string $class, array &$values, array &$related_records, string $related_class, string $route): array
	{
		$schema              = ORMSchema::retrieve($class);
		$table               = ORM::tablize($class);
		$related_table       = ORM::tablize($related_class);
		$relationship        = ORMSchema::getRoute($schema, $table, $related_table, $route);
		$related_column      = self::relationshipString($relationship, 'related_column');

		$first_pk_column     = self::determineFirstPKColumn($class, $related_class, $route);
		$filter              = self::determineRequestFilter($class, $related_class, $route);
		$pk_field            = $filter . $first_pk_column;
		$pk_input            = Request::get($pk_field, 'array', array());
		$input_keys          = is_array($pk_input) ? array_keys($pk_input) : array();

		$related_record_name = self::getRelatedRecordName($class, $related_class, $route);

		$messages = array();

		$one_to_one = ORMSchema::isOneToOne($schema, $table, $related_table, $route);
		if ($one_to_one) {
			$records = array(self::createRecord($class, $values, $related_records, $related_class, $route));
		} else {
			$records = self::buildRecords($class, $values, $related_records, $related_class, $route);
		}

		foreach ($records as $i => $record) {
			Request::filter($filter, isset($input_keys[$i]) ? $input_keys[$i] : $i);
			$record_messages = $record->validate(true);
			if (!is_array($record_messages)) {
				$record_messages = array();
			}

			foreach ($record_messages as $column => $record_message) {
				// Ignore validation messages about the primary key, non-string keys and
				// non-string messages since they cannot be matched up to a field
				if ($column == $related_column || !is_string($column) || !is_string($record_message)) {
				    continue;
				}

				if ($one_to_one) {
					$token_field           = ValidationException::formatField('__TOKEN__');
					$extract_message_regex = '#' . str_replace('__TOKEN__', '(.*?)', preg_quote($token_field, '#')) . '(.*)$#D';

					$column_name  = $related_record_name;
					$message_text = $record_message;

					if (preg_match($extract_message_regex, $record_message, $matches) && isset($matches[1], $matches[2])) {
						$column_name  = self::compose(
							'%1$s %2$s',
							$related_record_name,
							$matches[1]
						);
						$message_text = $matches[2];
					}

					$messages[$related_table . '::' . $column] = self::compose(
						'%1$s%2$s',
						ValidationException::formatField($column_name),
						$message_text
					);
				} else {
					$main_key = $related_table . '[' . $i . ']';
					if (!isset($messages[$main_key]) || !is_array($messages[$main_key])) {
						if (isset(self::$validation_name_methods[$class][$related_class][$route])) {
							$name = $record->{self::$validation_name_methods[$class][$related_class][$route]}($i + 1);
							if (is_scalar($name)) {
								$name = (string) $name;
							} else {
								$name = '';
							}
						} else {
							$name = $related_record_name . ' #' . ($i + 1);
						}
						$messages[$main_key] = array(
							'name'   => $name,
							'errors' => array()
						);
					}

					$messages[$main_key]['errors'][$column] = $record_message;
				}
			}
			Request::unfilter();
		}

		return $messages;
	}


	/**
	 * Validates many-to-many related records
	 *
	 * @param  string $class          The class to validate the related records for
	 * @param  string $related_class  The name of the class for this record set
	 * @param  string $route          The route between the table and related table
	 * @param  RelatedInfo  $related_info   The related info to validate
	 * @return array<string, string>  An array of validation messages
	 */
	private static function validateManyToMany(string $class, string $related_class, string $route, array $related_info): array
	{
		$related_record_name = self::getRelatedRecordName($class, $related_class, $route);
		$record_number = 1;

		$messages = array();

		$related_records = ($related_info['record_set'] ?? null) ?? ($related_info['primary_keys'] ?? null);

		if (!$related_records) {
			return $messages;
		}

		foreach ($related_records as $record) {
			if (($record instanceof ActiveRecord && !$record->exists()) || !$record) {
				$messages[ORM::tablize($related_class)] = self::compose(
					'%1$sPlease select a %2$s',
					ValidationException::formatField(
						self::compose(
							'%1$s #%2$s',
							$related_record_name,
							$record_number
						)
					),
					$related_record_name
				);
			}
			$record_number++;
		}

		return $messages;
	}


	/**
	 * Forces use as a static class
	 *
	 * @return ORMRelated
	 */
	private function __construct()
    {
    }
}



/**
 * Copyright (c) 2007-2011 Will Bond <will@flourishlib.com>, others
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
