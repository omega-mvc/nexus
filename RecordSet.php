<?php
declare(strict_types=1);
/**
 * A lightweight, iterable set of ActiveRecord-based objects
 *
 * @copyright  Copyright (c) 2007-2012 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 * @link       http://flourishlib.com/RecordSet
 *
 * @version    1.0.0b47
 * @changes    1.0.0b47  Fixed the new version of ::precount() to work with tables having an explicit schema [wb, 2012-09-21]
 * @changes    1.0.0b46  Fixed a bug with ::precount() not working for self-joining tables [wb, 2012-09-16]
 * @changes    1.0.0b45  Added support for the starts with like, `^~`, and ends with like, `$~`, operators to both ::build() and ::filter() [wb, 2011-06-20]
 * @changes    1.0.0b44  Backwards Compatibility Break - ::sort() and ::sortByCallback() now return a new RecordSet instead of sorting the record set in place [wb, 2011-06-20]
 * @changes    1.0.0b43  Added the ability to pass SQL and values to ::buildFromSQL(), added the ability to manually pass the `$limit` and `$page` to ::buildFromArray() and ::buildFromSQL(), changed ::slice() to remember `$limit` and `$page` if possible when `$remember_original_count` is `TRUE` [wb, 2011-01-11]
 * @changes    1.0.0b42  Updated class to use ORM::getRelatedClass() [wb, 2010-11-24]
 * @changes    1.0.0b41  Added support for PHP 5.3 namespaced ActiveRecord classes [wb, 2010-11-11]
 * @changes    1.0.0b40  Added the ::tally() method [wb, 2010-09-28]
 * @changes    1.0.0b39  Backwards Compatibility Break - removed the methods ::fetchRecord(), ::current(), ::key(), ::next(), ::rewind() and ::valid() and the Iterator interface - and the `$pointer` parameter for callbacks registered via ORM::registerRecordSetMethod() was replaced with the `$method_name` parameter - added the methods ::getIterator(), ::getLimit(), ::getPage(), ::getPages(), ::getRecord(), ::offsetExists(), ::offsetGet(), ::offsetSet() and ::offsetUnset() and the IteratorAggregate and ArrayAccess interfaces [wb, 2010-09-28]
 * @changes    1.0.0b38  Updated code to work with the new ORM API [wb, 2010-08-06]
 * @changes    1.0.0b37  Fixed a typo/bug in ::reduce() [wb, 2010-06-30]
 * @changes    1.0.0b36  Replaced create_function() with a private method call [wb, 2010-06-08]
 * @changes    1.0.0b35  Added the ::chunk() and ::split() methods [wb, 2010-05-20]
 * @changes    1.0.0b34  Added an integer cast to ::count() to fix issues with the dblib MSSQL driver [wb, 2010-04-09]
 * @changes    1.0.0b33  Updated the class to force configure classes before peforming actions with them [wb, 2010-03-30]
 * @changes    1.0.0b32  Fixed a column aliasing issue with SQLite [wb, 2010-01-25]
 * @changes    1.0.0b31  Added the ability to compare columns in ::build() with the `=:`, `!:`, `<:`, `<=:`, `>:` and `>=:` operators [wb, 2009-12-08]
 * @changes    1.0.0b30  Fixed a bug affecting where conditions with columns that are not null but have a default value [wb, 2009-11-03]
 * @changes    1.0.0b29  Updated code for the new ORMDatabase and ORMSchema APIs [wb, 2009-10-28]
 * @changes    1.0.0b28  Fixed ::prebuild() and ::precount() to work across all databases, changed SQL statements to use value placeholders, identifier escaping and schema support [wb, 2009-10-22]
 * @changes    1.0.0b27  Changed RecordSet::build() to fix bad $page numbers instead of throwing an ProgrammerException [wb, 2009-10-05]
 * @changes    1.0.0b26  Updated the documentation for ::build() and ::filter() to reflect new functionality [wb, 2009-09-21]
 * @changes    1.0.0b25  Fixed ::map() to work with string-style static method callbacks in PHP 5.1 [wb, 2009-09-18]
 * @changes    1.0.0b24  Backwards Compatibility Break - renamed ::buildFromRecords() to ::buildFromArray(). Added ::buildFromCall(), ::buildFromMap() and `::build{RelatedRecords}()` [wb, 2009-09-16]
 * @changes    1.0.0b23  Added an extra parameter to ::diff(), ::filter(), ::intersect(), ::slice() and ::unique() to save the number of records in the current set as the non-limited count for the new set [wb, 2009-09-15]
 * @changes    1.0.0b22  Changed ::__construct() to accept any Iterator instead of just an Result object [wb, 2009-08-12]
 * @changes    1.0.0b21  Added performance tweaks to ::prebuild() and ::precreate() [wb, 2009-07-31]
 * @changes    1.0.0b20  Changed the class to implement Countable, making the [http://php.net/count `count()`] function work [wb, 2009-07-29]
 * @changes    1.0.0b19  Fixed bugs with ::diff() and ::intersect() and empty record sets [wb, 2009-07-29]
 * @changes    1.0.0b18  Added method chaining support to prebuild, precount and precreate methods [wb, 2009-07-15]
 * @changes    1.0.0b17  Changed ::__call() to pass the parameters to the callback [wb, 2009-07-14]
 * @changes    1.0.0b16  Updated documentation for the intersection operator `><` [wb, 2009-07-13]
 * @changes    1.0.0b15  Added the methods ::diff() and ::intersect() [wb, 2009-07-13]
 * @changes    1.0.0b14  Added the methods ::contains() and ::unique() [wb, 2009-07-09]
 * @changes    1.0.0b13  Added documentation to ::build() about the intersection operator `><` [wb, 2009-07-09]
 * @changes    1.0.0b12  Added documentation to ::build() about the `AND LIKE` operator `&~` [wb, 2009-07-09]
 * @changes    1.0.0b11  Added documentation to ::build() about the `NOT LIKE` operator `!~` [wb, 2009-07-08]
 * @changes    1.0.0b10  Moved the private method ::checkConditions() to ActiveRecord::checkConditions() [wb, 2009-07-08]
 * @changes    1.0.0b9   Changed ::build() to only fall back to ordering by primary keys if one exists [wb, 2009-06-26]
 * @changes    1.0.0b8   Updated ::merge() to accept arrays of ActiveRecords or a single ActiveRecord in addition to an RecordSet [wb, 2009-06-02]
 * @changes    1.0.0b7   Backwards Compatibility Break - Removed ::flagAssociate() and ::isFlaggedForAssociation(), callbacks registered via ORM::registerRecordSetMethod() no longer receive the `$associate` parameter [wb, 2009-06-02]
 * @changes    1.0.0b6   Changed ::tossIfEmpty() to return the record set to allow for method chaining [wb, 2009-05-18]
 * @changes    1.0.0b5   ::build() now allows NULL for `$where_conditions` and `$order_bys`, added a check to the SQL passed to ::buildFromSQL() [wb, 2009-05-03]
 * @changes    1.0.0b4   ::__call() was changed to prevent exceptions coming from Grammar when an unknown method is called [wb, 2009-03-27]
 * @changes    1.0.0b3   ::sort() and ::sortByCallback() now return the record set to allow for method chaining [wb, 2009-03-23]
 * @changes    1.0.0b2   Added support for != and <> to ::build() and ::filter() [wb, 2008-12-04]
 * @changes    1.0.0b    The initial implementation [wb, 2007-08-04]
 *
 * @implements IteratorAggregate<int, ActiveRecord>
 * @implements ArrayAccess<int|string, mixed>
 */
class RecordSet implements IteratorAggregate, ArrayAccess, Countable
{
	/**
	 * Creates an RecordSet by specifying the class to create plus the where conditions and order by rules
	 *
	 * The where conditions array can contain `key => value` entries in any of
	 * the following formats:
	 *
	 * {{{
	 * 'column='                    => VALUE,                       // column = VALUE
	 * 'column!'                    => VALUE                        // column <> VALUE
	 * 'column!='                   => VALUE                        // column <> VALUE
	 * 'column<>'                   => VALUE                        // column <> VALUE
	 * 'column~'                    => VALUE                        // column LIKE '%VALUE%'
	 * 'column^~'                   => VALUE                        // column LIKE 'VALUE%'
	 * 'column$~'                   => VALUE                        // column LIKE '%VALUE'
	 * 'column!~'                   => VALUE                        // column NOT LIKE '%VALUE%'
	 * 'column<'                    => VALUE                        // column < VALUE
	 * 'column<='                   => VALUE                        // column <= VALUE
	 * 'column>'                    => VALUE                        // column > VALUE
	 * 'column>='                   => VALUE                        // column >= VALUE
	 * 'column=:'                   => 'other_column'               // column = other_column
	 * 'column!:'                   => 'other_column'               // column <> other_column
	 * 'column!=:'                  => 'other_column'               // column <> other_column
	 * 'column<>:'                  => 'other_column'               // column <> other_column
	 * 'column<:'                   => 'other_column'               // column < other_column
	 * 'column<=:'                  => 'other_column'               // column <= other_column
	 * 'column>:'                   => 'other_column'               // column > other_column
	 * 'column>=:'                  => 'other_column'               // column >= other_column
	 * 'column='                    => array(VALUE, VALUE2, ... )   // column IN (VALUE, VALUE2, ... )
	 * 'column!'                    => array(VALUE, VALUE2, ... )   // column NOT IN (VALUE, VALUE2, ... )
	 * 'column!='                   => array(VALUE, VALUE2, ... )   // column NOT IN (VALUE, VALUE2, ... )
	 * 'column<>'                   => array(VALUE, VALUE2, ... )   // column NOT IN (VALUE, VALUE2, ... )
	 * 'column~'                    => array(VALUE, VALUE2, ... )   // (column LIKE '%VALUE%' OR column LIKE '%VALUE2%' OR column ... )
	 * 'column^~'                   => array(VALUE, VALUE2, ... )   // (column LIKE 'VALUE%' OR column LIKE 'VALUE2%' OR column ... )
	 * 'column$~'                   => array(VALUE, VALUE2, ... )   // (column LIKE '%VALUE' OR column LIKE '%VALUE2' OR column ... )
	 * 'column&~'                   => array(VALUE, VALUE2, ... )   // (column LIKE '%VALUE%' AND column LIKE '%VALUE2%' AND column ... )
	 * 'column!~'                   => array(VALUE, VALUE2, ... )   // (column NOT LIKE '%VALUE%' AND column NOT LIKE '%VALUE2%' AND column ... )
	 * 'column!|column2<|column3='  => array(VALUE, VALUE2, VALUE3) // (column <> '%VALUE%' OR column2 < '%VALUE2%' OR column3 = '%VALUE3%')
	 * 'column|column2><'           => array(VALUE, VALUE2)         // WHEN VALUE === NULL: ((column2 IS NULL AND column = VALUE) OR (column2 IS NOT NULL AND column <= VALUE AND column2 >= VALUE))
	 *                                                              // WHEN VALUE !== NULL: ((column <= VALUE AND column2 >= VALUE) OR (column >= VALUE AND column <= VALUE2))
	 * 'column|column2|column3~'    => VALUE                        // (column LIKE '%VALUE%' OR column2 LIKE '%VALUE%' OR column3 LIKE '%VALUE%')
	 * 'column|column2|column3~'    => array(VALUE, VALUE2, ... )   // ((column LIKE '%VALUE%' OR column2 LIKE '%VALUE%' OR column3 LIKE '%VALUE%') AND (column LIKE '%VALUE2%' OR column2 LIKE '%VALUE2%' OR column3 LIKE '%VALUE2%') AND ... )
	 * }}}
	 *
	 * When creating a condition in the form `column|column2|column3~`, if the
	 * value for the condition is a single string that contains spaces, the
	 * string will be parsed for search terms. The search term parsing will
	 * handle quoted phrases and normal words and will strip punctuation and
	 * stop words (such as "the" and "a").
	 *
	 * The order bys array can contain `key => value` entries in any of the
	 * following formats:
	 *
	 * {{{
	 * 'column'     => 'asc'      // 'first_name' => 'asc'
	 * 'column'     => 'desc'     // 'last_name'  => 'desc'
	 * 'expression' => 'asc'      // "CASE first_name WHEN 'smith' THEN 1 ELSE 2 END" => 'asc'
	 * 'expression' => 'desc'     // "CASE first_name WHEN 'smith' THEN 1 ELSE 2 END" => 'desc'
	 * }}}
	 *
	 * The column in both the where conditions and order bys can be in any of
	 * the formats:
	 *
	 * {{{
	 * 'column'                                                         // e.g. 'first_name'
	 * 'current_table.column'                                           // e.g. 'users.first_name'
	 * 'related_table.column'                                           // e.g. 'user_groups.name'
	 * 'related_table{route}.column'                                    // e.g. 'user_groups{user_group_id}.name'
	 * 'related_table=>once_removed_related_table.column'               // e.g. 'user_groups=>permissions.level'
	 * 'related_table{route}=>once_removed_related_table.column'        // e.g. 'user_groups{user_group_id}=>permissions.level'
	 * 'related_table=>once_removed_related_table{route}.column'        // e.g. 'user_groups=>permissions{read}.level'
	 * 'related_table{route}=>once_removed_related_table{route}.column' // e.g. 'user_groups{user_group_id}=>permissions{read}.level'
	 * 'column||other_column'                                           // e.g. 'first_name||last_name' - this concatenates the column values
	 * }}}
	 *
	 * In addition to using plain column names for where conditions, it is also
	 * possible to pass an aggregate function wrapped around a column in place
	 * of a column name, but only for certain comparison types. //Note that for
	 * column comparisons, the function may be placed on either column or both.//
	 *
	 * {{{
	 * 'function(column)='   => VALUE,                       // function(column) = VALUE
	 * 'function(column)!'   => VALUE                        // function(column) <> VALUE
	 * 'function(column)!=   => VALUE                        // function(column) <> VALUE
	 * 'function(column)<>'  => VALUE                        // function(column) <> VALUE
	 * 'function(column)~'   => VALUE                        // function(column) LIKE '%VALUE%'
	 * 'function(column)^~'  => VALUE                        // function(column) LIKE 'VALUE%'
	 * 'function(column)$~'  => VALUE                        // function(column) LIKE '%VALUE'
	 * 'function(column)!~'  => VALUE                        // function(column) NOT LIKE '%VALUE%'
	 * 'function(column)<'   => VALUE                        // function(column) < VALUE
	 * 'function(column)<='  => VALUE                        // function(column) <= VALUE
	 * 'function(column)>'   => VALUE                        // function(column) > VALUE
	 * 'function(column)>='  => VALUE                        // function(column) >= VALUE
	 * 'function(column)=:'  => 'other_column'               // function(column) = other_column
	 * 'function(column)!:'  => 'other_column'               // function(column) <> other_column
	 * 'function(column)!=:' => 'other_column'               // function(column) <> other_column
	 * 'function(column)<>:' => 'other_column'               // function(column) <> other_column
	 * 'function(column)<:'  => 'other_column'               // function(column) < other_column
	 * 'function(column)<=:' => 'other_column'               // function(column) <= other_column
	 * 'function(column)>:'  => 'other_column'               // function(column) > other_column
	 * 'function(column)>=:' => 'other_column'               // function(column) >= other_column
	 * 'function(column)='   => array(VALUE, VALUE2, ... )   // function(column) IN (VALUE, VALUE2, ... )
	 * 'function(column)!'   => array(VALUE, VALUE2, ... )   // function(column) NOT IN (VALUE, VALUE2, ... )
	 * 'function(column)!='  => array(VALUE, VALUE2, ... )   // function(column) NOT IN (VALUE, VALUE2, ... )
	 * 'function(column)<>'  => array(VALUE, VALUE2, ... )   // function(column) NOT IN (VALUE, VALUE2, ... )
	 * }}}
	 *
	 * The aggregate functions `AVG()`, `COUNT()`, `MAX()`, `MIN()` and
	 * `SUM()` are supported across all database types.
	 *
	 * Below is an example of using where conditions and order bys. Please note
	 * that values should **not** be escaped for the database, but should just
	 * be normal PHP values.
	 *
	 * {{{
	 * #!php
	 * return RecordSet::build(
	 *     'User',
	 *     array(
	 *         'first_name='      => 'John',
	 *         'status!'          => 'Inactive',
	 *         'groups.group_id=' => 2
	 *     ),
	 *     array(
	 *         'last_name'   => 'asc',
	 *         'date_joined' => 'desc'
	 *     )
	 * );
	 * }}}
	 *
	 * @param  string  $class             The class to create the RecordSet of
	 * @param  array<string, mixed>  $where_conditions  The `column => value` comparisons for the `WHERE` clause
	 * @param  array<string, mixed>  $order_bys         The `column => direction` values to use for the `ORDER BY` clause
	 * @param  integer $limit             The number of records to fetch
	 * @param  integer $page              The page offset to use when limiting records
	 * @return RecordSet  A set of ActiveRecord objects
	 */
	static public function build(string $class, array $where_conditions=array(), array $order_bys=array(), ?int $limit=NULL, ?int $page=NULL): RecordSet
	{
		ActiveRecord::validateClass($class);
		ActiveRecord::forceConfigure($class);

		$db     = ORMDatabase::retrieve($class, 'read');
		$schema = ORMSchema::retrieve($class);
		$table  = ORM::tablize($class);

		$params = array($db->escape("SELECT %r.* FROM :from_clause", $table));

		if ($where_conditions) {
			$having_conditions = ORMDatabase::splitHavingConditions($where_conditions);
		} else {
			$having_conditions = NULL;
		}

		if ($where_conditions) {
			$params[0] = (is_string($params[0]) ? $params[0] : '') . ' WHERE ';
			$params = ORMDatabase::addWhereClause($db, $schema, $params, $table, $where_conditions);
		}

		$params[0] = (is_string($params[0]) ? $params[0] : '') . ' :group_by_clause ';

		if ($having_conditions) {
			$params[0] .= ' HAVING ';
			$params = ORMDatabase::addHavingClause($db, $schema, $params, $table, $having_conditions);
		}

		// If no ordering is specified, order by the primary key
		if (!$order_bys) {
			$order_bys = array();
			foreach ($schema->getKeys($table, 'primary') as $pk_column) {
				$order_bys[$table . '.' . $pk_column] = 'ASC';
			}
		}

		$params[0] = (is_string($params[0]) ? $params[0] : '') . ' ORDER BY ';
		$params = ORMDatabase::addOrderByClause($db, $schema, $params, $table, $order_bys);

		$params = ORMDatabase::injectFromAndGroupByClauses($db, $schema, $params, $table);

		// Add the limit clause and create a query to get the non-limited total
		$non_limited_count_sql = NULL;
		if ($limit !== NULL) {
			$pk_columns = array();
			foreach ($schema->getKeys($table, 'primary') as $pk_column) {
				$pk_columns[] = $table . '.' . $pk_column;
			}

			$count_search  = $db->escape('SELECT %r.*', $table);
			$count_replace = $db->escape('SELECT %r', $pk_columns);
			$count_subject = $params[0];
			$count_search  = is_string($count_search) ? $count_search : '';
			$count_replace = is_string($count_replace) ? $count_replace : '';
			$count_subject = is_string($count_subject) ? $count_subject : '';

			$non_limited_count_sql = str_replace($count_search, $count_replace, $count_subject);
			$stripped_count_sql    = preg_replace('#\s+ORDER BY.*$#', '', $non_limited_count_sql);
			$stripped_count_sql    = is_string($stripped_count_sql) ? $stripped_count_sql : '';
			$count_sql             = $db->escape('SELECT count(*) FROM (' . $stripped_count_sql . ') subquery', array_slice($params, 1));
			$non_limited_count_sql = is_string($count_sql) ? $count_sql : NULL;

			$params[0] = (is_string($params[0]) ? $params[0] : '') . ' LIMIT ' . $limit;

			if ($page !== NULL) {

				if ($page < 1) {
					$page = 1;
				}

				$params[0] .= ' OFFSET ' . (($page-1) * $limit);
			}
		} else {
			$page = 1;
		}

		return new RecordSet($class, self::runQuery($db, $params), $non_limited_count_sql, $limit, $page ?? 1);
	}


	/**
	 * Creates an RecordSet from an array of records
	 *
	 * @internal
	 *
	 * @param  string|array<string> $class          The class or classes of the records
	 * @param  array<array-key, mixed> $records  The records to create the set from, the order of the record set will be the same as the order of the array.
	 * @param  integer      $total_records  The total number of records - this should only be provided if the array is a segment of a larger array - this is informational only and does not affect the array
	 * @param  integer      $limit          The maximum number of records the array was limited to - this is informational only and does not affect the array
	 * @param  integer      $page           The page of records the array is from - this is informational only and does not affect the array
	 * @return RecordSet  A set of ActiveRecord objects
	 */
	static public function buildFromArray(mixed $class, array $records, ?int $total_records=NULL, ?int $limit=NULL, int $page=1): RecordSet
	{
		if (is_array($class)) {
			foreach ($class as $_class) {
				ActiveRecord::validateClass($_class);
				ActiveRecord::forceConfigure($_class);
			}
		} else {
			ActiveRecord::validateClass($class);
			ActiveRecord::forceConfigure($class);
		}

		$records = array_values($records);

		if (!self::containsOnlyRecords($records)) {
			throw new ProgrammerException('A record set can only be built from ActiveRecord objects');
		}

		return new RecordSet($class, $records, $total_records, $limit, $page);
	}


	/**
	 * Checks if every value in an array is an ActiveRecord object
	 *
	 * @param  array<array-key, mixed>  $records  The records to check
	 * @return bool  Whether or not all of the values are ActiveRecord objects
	 *
	 * @phpstan-assert-if-true array<int, ActiveRecord> $records
	 */
	static private function containsOnlyRecords(array $records): bool
	{
		foreach ($records as $record) {
			if (!$record instanceof ActiveRecord) {
				return FALSE;
			}
		}

		return TRUE;
	}


	/**
	 * Creates an RecordSet from an SQL statement
	 *
	 * The SQL statement should select all columns from a single table with a *
	 * pattern since that is what an ActiveRecord models. If any columns are
	 * left out or added, strange error may happen when loading or saving
	 * records.
	 *
	 * Here is an example of an appropriate SQL statement:
	 *
	 * {{{
	 * #!sql
	 * SELECT users.* FROM users INNER JOIN groups ON users.group_id = groups.group_id WHERE groups.name = 'Public'
	 * }}}
	 *
	 * Here is an example of a SQL statement that will cause errors:
	 *
	 * {{{
	 * #!sql
	 * SELECT users.*, groups.name FROM users INNER JOIN groups ON users.group_id = groups.group_id WHERE groups.group_id = 2
	 * }}}
	 *
	 * The `$non_limited_count_sql` should only be passed when the `$sql`
	 * contains a `LIMIT` clause and should contain a count of the records when
	 * a `LIMIT` is not imposed.
	 *
	 * Here is an example of a `$sql` statement with a `LIMIT` clause and a
	 * corresponding `$non_limited_count_sql`:
	 *
	 * {{{
	 * #!php
	 * RecordSet::buildFromSQL('User', 'SELECT * FROM users LIMIT 5', 'SELECT count(*) FROM users');
	 * }}}
	 *
	 * The `$non_limited_count_sql` is used when ::count() is called with `TRUE`
	 * passed as the parameter.
	 *
	 * Both the `$sql` and `$non_limited_count_sql` can be passed as a string
	 * SQL statement, or an array containing a SQL statement and the values to
	 * escape into it:
	 *
	 * {{{
	 * #!php
	 * RecordSet::buildFromSQL(
	 *     'User',
	 *     array("SELECT * FROM users WHERE date_created > %d LIMIT %i OFFSET %i", $start_date, 10, 10*($page-1)),
	 *     array("SELECT * FROM users WHERE date_created > %d", $start_date),
	 *     10,
	 *     $page
	 * )
	 * }}}
	 *
	 * @param  string        $class                  The class to create the RecordSet of
	 * @param  string|array<mixed>  $sql             The SQL to create the set from, or an array of the SQL statement plus values to escape
	 * @param  string|array<mixed>|null  $non_limited_count_sql  An SQL statement, or an array of the SQL statement plus values to escape, to get the total number of rows that would have been returned if a `LIMIT` clause had not been used. Should only be passed if a `LIMIT` clause is used in `$sql`.
	 * @param  integer       $limit                  The number of records the SQL statement was limited to - this is information only and does not affect the SQL
	 * @param  integer       $page                   The page of records the SQL statement returned - this is information only and does not affect the SQL
	 * @return RecordSet  A set of ActiveRecord objects
	 */
	static public function buildFromSQL(string $class, mixed $sql, mixed $non_limited_count_sql=NULL, ?int $limit=NULL, int $page=1): RecordSet
	{
		ActiveRecord::validateClass($class);
		ActiveRecord::forceConfigure($class);

		$sql_statement = is_array($sql) ? ($sql[0] ?? NULL) : $sql;
		$sql_display   = is_string($sql_statement) ? $sql_statement : (is_scalar($sql_statement) ? (string) $sql_statement : '');

		if (!is_string($sql_statement) || !preg_match('#^\s*SELECT\s*(DISTINCT|ALL)?\s*(("?\w+"?\.)?"?\w+"?\.)?\*\s*FROM#i', $sql_statement)) {
			throw new ProgrammerException(
				'The SQL statement specified, %s, does not appear to be in the form SELECT * FROM table',
				$sql_display
			);
		}

		$db = ORMDatabase::retrieve($class, 'read');

		if (is_array($sql)) {
			$result = self::runQuery($db, $sql);
		} else {
			$result = self::runQuery($db, array($sql));
		}

		if (is_array($non_limited_count_sql)) {
			$escaped_count = call_user_func_array($db->escape(...), $non_limited_count_sql);
		} else {
			$escaped_count = $non_limited_count_sql;
		}
		$non_limited_count_sql = (is_string($escaped_count) || is_int($escaped_count)) ? $escaped_count : NULL;

		return new RecordSet(
			$class,
			$result,
			$non_limited_count_sql,
			$limit,
			$page
		);
	}


	/**
	 * Composes text using Text if loaded
	 *
	 * @param  string  $message    The message to compose
	 * @return string  The composed and possible translated message
	 */
	static protected function compose(string $message): string
	{
		$args = array_map(static function (mixed $arg): bool|float|int|string|null {
			if ($arg === NULL || is_bool($arg) || is_int($arg) || is_float($arg) || is_string($arg)) {
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

		if (class_exists('Text', FALSE)) {
			return call_user_func_array(
				array('Text', 'compose'),
				array($message, $args)
			);
		} else {
			return vsprintf($message, $args);
		}
	}


	/**
	 * Runs a single query and returns the Result object
	 *
	 * @param  Database                $db      The database to run the query on
	 * @param  array<array-key, mixed> $params  The SQL statement and the values to escape into it
	 * @return Result  The result of the query
	 */
	static private function runQuery(Database $db, array $params): Result
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
	 * Converts the values of an array to strings for comparison purposes
	 *
	 * @param  array<array-key, mixed> $values  The values to convert
	 * @return array<array-key, string>  The values converted to strings
	 */
	static private function stringifyValues(array $values): array
	{
		$output = array();
		foreach ($values as $key => $value) {
			if (is_string($value)) {
				$output[$key] = $value;
			} elseif (is_scalar($value)) {
				$output[$key] = (string) $value;
			} else {
				$output[$key] = '';
			}
		}
		return $output;
	}


	/**
     * Counts the number of records that match the conditions specified
     *
     * @param  string  $class             The class of records to count
     * @param  mixed   $where_conditions  An array of where clause parameters in the same format as ::build()
     * @return integer  The number of records
     */
    static public function tally(string $class, mixed $where_conditions=array()): int
    {
        ActiveRecord::validateClass($class);
		ActiveRecord::forceConfigure($class);

		$where_conditions = is_array($where_conditions) ? $where_conditions : array();

		$db     = ORMDatabase::retrieve($class, 'read');
		$schema = ORMSchema::retrieve($class);
		$table  = ORM::tablize($class);

		$pk_columns = array();
        foreach ($schema->getKeys($table, 'primary') as $pk_column) {
            $pk_columns[] = $table . '.' . $pk_column;
        }

        $params = array($db->escape("SELECT COUNT(*) FROM (SELECT %r FROM :from_clause", $pk_columns));

		if ($where_conditions) {
			$having_conditions = ORMDatabase::splitHavingConditions($where_conditions);
		} else {
			$having_conditions = NULL;
		}

		if ($where_conditions) {
			$params[0] = (is_string($params[0]) ? $params[0] : '') . ' WHERE ';
			$params = ORMDatabase::addWhereClause($db, $schema, $params, $table, $where_conditions);
		}

		$params[0] = (is_string($params[0]) ? $params[0] : '') . ' :group_by_clause ';

		if ($having_conditions) {
			$params[0] .= ' HAVING ';
			$params = ORMDatabase::addHavingClause($db, $schema, $params, $table, $having_conditions);
		}

        $params[0] = (is_string($params[0]) ? $params[0] : '') . ') subquery';

		$params = ORMDatabase::injectFromAndGroupByClauses($db, $schema, $params, $table);

		return (int) self::runQuery($db, $params)->fetchScalar();
    }


	/**
	 * The class of the contained records
	 *
	 * @var string|array<string>
	 */
	private string|array $class = '';

	/**
	 * The limit that was used when creating the set
	 *
	 * @var integer
	 */
	private ?int $limit = NULL;

	/**
	 * The page of results the record set represents
	 *
	 * @var integer
	 */
	private int $page = 1;

	/**
	 * The number of rows that would have been returned if a `LIMIT` clause had not been used, or the SQL to get that number
	 *
	 * @var integer|string|null
	 */
	private mixed $non_limited_count = null;

	/**
	 * An array of the records in the set, initially empty
	 *
	 * @var array<int, ActiveRecord>
	 */
	private array $records = array();

	/**
	 * The method used to sort the record set
	 *
	 * @var string
	 */
	private string $sort_method = '';

	/**
	 * The direction used to sort the record set
	 *
	 * @var string
	 */
	private string $sort_direction = 'asc';


	/**
	 * Allows for preloading various data related to the record set in single database queries, as opposed to one query per record
	 *
	 * This method will handle methods in the format `verbRelatedRecords()` for
	 * the verbs `build`, `prebuild`, `precount` and `precreate`.
	 *
	 * `build` calls `create{RelatedClass}()` on each record in the set and
	 * returns the result as a new record set. The relationship route can be
	 * passed as an optional parameter.
	 *
	 * `prebuild` builds *-to-many record sets for all records in the record
	 * set. `precount` will count records in *-to-many record sets for every
	 * record in the record set. `precreate` will create a *-to-one record
	 * for every record in the record set.
	 *
	 * @param  string $method_name  The name of the method called
	 * @param  array<mixed> $parameters  The parameters passed
	 * @return mixed  The result of the called method
	 */
	public function __call(mixed $method_name, mixed $parameters)
	{
		$callback = ORM::getRecordSetMethod($method_name);
		if ($callback) {
			return call_user_func_array(
				$callback,
				array(
					$this,
					$this->class,
					&$this->records,
					$method_name,
					$parameters
				)
			);
		}

		list($action, $subject) = ORM::parseMethod($method_name);

		$route = (isset($parameters[0]) && is_string($parameters[0])) ? $parameters[0] : NULL;

		// This check prevents Grammar exceptions being thrown when an unknown method is called
		$related_class = '';
		$related_class_sans_namespace = '';
		if (in_array($action, array('build', 'prebuild', 'precount', 'precreate'))) {
			$related_class = Grammar::singularize($subject);
			if ($related_class === FALSE) {
				$related_class = '';
			}
			$related_class_sans_namespace = $related_class;
			if (!is_array($this->class)) {
				$related_class = ORM::getRelatedClass($this->class, $related_class);
			}
		}

		switch ($action) {
			case 'build':
				if ($route) {
					$this->precreate($related_class, $route);
					return $this->buildFromCall('create' . $related_class_sans_namespace, $route);
				}
				$this->precreate($related_class);
				return $this->buildFromCall('create' . $related_class_sans_namespace);

			case 'prebuild':
				return $this->prebuild($related_class, $route);

			case 'precount':
				return $this->precount($related_class, $route);

			case 'precreate':
				return $this->precreate($related_class, $route);
		}

		throw new ProgrammerException(
			'Unknown method, %s(), called',
			$method_name
		);
	}


	/**
	 * Sets the contents of the set
	 *
	 * @param  string|array<string> $class              The type(s) of records the object will contain
	 * @param  Iterator|array<int, ActiveRecord> $records  The Iterator object of the records to create or an array of records
	 * @param  string|integer $non_limited_count  An SQL statement to get the total number of records sans a `LIMIT` clause or a integer of the total number of records
	 * @param  integer        $limit              The number of records the set was limited to
	 * @param  integer        $page               The page of records that was built
	 * @return RecordSet
	 */
	protected function __construct(mixed $class, mixed $records=NULL, mixed $non_limited_count=NULL, mixed $limit=NULL, mixed $page=1)
	{
		$this->class = (is_array($class) && count($class) == 1) ? current($class) : $class;

		if ($non_limited_count !== NULL) {
			$this->non_limited_count = $non_limited_count;
		}

		if ($records && $records instanceof Iterator) {
			if (!is_string($class)) {
				throw new ProgrammerException('Unable to build a record set from an iterator without a single class name, %1$s given', get_debug_type($class));
			}
			ActiveRecord::validateClass($class);
			while ($records->valid()) {
				$this->records[] = new $class($records);
				$records->next();
			}
		}

		if (is_array($records)) {
			$this->records = $records;
		}

		$this->limit = $limit;
		$this->page  = $page;
	}


	/**
	 * All requests that hit this method should be requests for callbacks
	 *
	 * @internal
	 *
	 * @param  string $method  The method to create a callback for
	 * @return callable(mixed...):mixed  The callback for the method requested
	 */
	public function __get(mixed $method): callable
	{
		return $this->$method(...);
	}


	/**
	 * The single class of the record set
	 *
	 * @return string
	 * @throws ProgrammerException  When the set contains multiple classes
	 */
	private function getSingleClass(): string
	{
		if (is_string($this->class)) {
			return $this->class;
		}
		throw new ProgrammerException(
			'A single class is required for this operation, %1$s given',
			get_debug_type($this->class)
		);
	}


	/**
	 * Adds an `ORDER BY` clause to the SQL for the primary keys of this record set
	 *
	 * @param  Database $db             The database the query will be executed on
	 * @param  Schema   $schema         The schema for the database
	 * @param  array<array-key, mixed>  $params  The parameters for the Database::query() call
	 * @param  string    $related_class  The related class to add the order bys for
	 * @param  string|null  $route       The route to this table from another table
	 * @return array<array-key, mixed>  The params with the `ORDER BY` clause added
	 */
	private function addOrderByParams(Database $db, Schema $schema, array $params, string $related_class, ?string $route=NULL): array
	{
		$table = ORM::tablize($this->getSingleClass());
		$table_with_route = ($route) ? $table . '{' . $route . '}' : $table;

		$pk_columns      = $schema->getKeys($table, 'primary');
		$first_pk_column = $pk_columns[0];

		$escaped_pk_columns = array();
		foreach ($pk_columns as $pk_column) {
			$escaped_pk = $db->escape('%r', $table_with_route . '.' . $pk_column);
			$escaped_pk_columns[$pk_column] = is_string($escaped_pk) ? $escaped_pk : '';
		}

		$column_info = $schema->getColumnInfo($table);
		$column_info = is_array($column_info) ? $column_info : array();

		$sql    = '';
		$number = 0;
		foreach ($this->getPrimaryKeys() as $primary_key) {
			$sql .= 'WHEN ';

			if (is_array($primary_key)) {
				$conditions = array();
				foreach ($pk_columns as $pk_column) {

					$value = $primary_key[$pk_column];

					// This makes sure the query performs the way an insert will
					$pk_info = $column_info[$pk_column] ?? NULL;
					if ($value === NULL && is_array($pk_info) && ($pk_info['not_null'] ?? NULL) && ($pk_info['default'] ?? NULL) !== NULL) {
						$value = $pk_info['default'];
					}

					$conditions[] = str_replace(
						'%r',
						$escaped_pk_columns[$pk_column],
						ORMDatabase::makeCondition($schema, $table, $pk_column, '=', $value)
					);
					$params[] = $value;
				}
				$sql .= join(' AND ', $conditions);

			} else {
				$sql .= str_replace(
					'%r',
					$escaped_pk_columns[$first_pk_column],
					ORMDatabase::makeCondition($schema, $table, $first_pk_column, '=', $primary_key)
				);
				$params[] = $primary_key;
			}

			$sql .= ' THEN ' . $number . ' ';

			$number++;
		}

		$params[0] = (is_string($params[0]) ? $params[0] : '') . 'CASE ' . $sql . 'END ASC';

		if ($related_order_bys = ORMRelated::getOrderBys($this->getSingleClass(), $related_class, $route ?? '')) {
			$params[0] .= ', ';
			$params = ORMDatabase::addOrderByClause($db, $schema, $params, ORM::tablize($related_class), $related_order_bys);
		}

		return $params;
	}


	/**
	 * Adds `WHERE` params to the SQL for the primary keys of this record set
	 *
	 * @param  Database $db      The database the query will be executed on
	 * @param  Schema   $schema  The schema for the database
	 * @param  array<array-key, mixed> $params  The parameters for the Database::query() call
	 * @param  string   $route   The route to this table from another table
	 * @return array<array-key, mixed>  The params with the `WHERE` clause added
	 */
	private function addWhereParams(Database $db, Schema $schema, array $params, ?string $route=NULL): array
	{
		$table = ORM::tablize($this->getSingleClass());
		$table_with_route = ($route) ? $table . '{' . $route . '}' : $table;

		$pk_columns = $schema->getKeys($table, 'primary');

		// We have a multi-field primary key, making things kinda ugly
		if (sizeof($pk_columns) > 1) {

			$escaped_pk_columns = array();
			foreach ($pk_columns as $pk_column) {
				$escaped_pk_columns[$pk_column] = $db->escape('%r', $table_with_route . '.' . $pk_column);
			}

			$column_info = $schema->getColumnInfo($table);
			$column_info = is_array($column_info) ? $column_info : array();

			$conditions = array();

			foreach ($this->getPrimaryKeys() as $primary_key) {
				$sub_conditions = array();
				foreach ($pk_columns as $pk_column) {
					$pk_info = is_array($column_info[$pk_column] ?? NULL) ? $column_info[$pk_column] : array();
					$value   = is_array($primary_key) ? ($primary_key[$pk_column] ?? NULL) : NULL;

					// This makes sure the query performs the way an insert will
					if ($value === NULL && !empty($pk_info['not_null']) && array_key_exists('default', $pk_info) && $pk_info['default'] !== NULL) {
						$value = $pk_info['default'];
					}

					$escaped_pk = $escaped_pk_columns[$pk_column] ?? NULL;
					$escaped_pk = is_string($escaped_pk) ? $escaped_pk : '';

					$sub_conditions[] = str_replace(
						'%r',
						$escaped_pk,
						ORMDatabase::makeCondition($schema, $table, $pk_column, '=', $value)
					);
					$params[] = $value;
				}
				$conditions[] = join(' AND ', $sub_conditions);
			}
			$params[0] = (is_string($params[0]) ? $params[0] : '') . '(' . join(') OR (', $conditions) . ')';

		// We have a single primary key field, making things nice and easy
		} else {
			$first_pk_column = $pk_columns[0];
			$escaped_pk = $db->escape('%r IN ', $table_with_route . '.' . $first_pk_column);
			$params[0] = (is_string($params[0]) ? $params[0] : '') . (is_string($escaped_pk) ? $escaped_pk : '');
			$placeholder = $schema->getColumnInfo($table, $first_pk_column, 'placeholder');
			$params[0] .= '(' . (is_string($placeholder) ? $placeholder : '') . ')';
			$params[] = $this->getPrimaryKeys();
		}

		return $params;
	}


	/**
	 * Calls a specific method on each object, returning an RecordSet of the results
	 *
	 * @param  string $method     The method to call
	 * @return RecordSet  A set of records that resulted from calling the method
	 */
	public function buildFromCall(string $method): RecordSet
	{
		$parameters = func_get_args();

		$result = call_user_func_array(array($this, 'call'), $parameters);
		$result = is_array($result) ? $result : array();

		$classes = array();
		foreach ($result as $record) {
			if (!$record instanceof ActiveRecord) {
				throw new ProgrammerException(
					'The method called, %1$s, returned something other than an ActiveRecord object',
					$method
				);
			}

			$class = get_class($record);

			if (!isset($classes[$class])) {
				$classes[$class] = TRUE;
			}
		}

		// If no objects were returned we need to fake the class
		if (!$classes) {
			$classes = array('ActiveRecord' => TRUE);
		}

		return new RecordSet(array_keys($classes), array_values($result));
	}


	/**
	 * Maps each record in the set to a callback function, returning an RecordSet of the results
	 *
	 * @param  callable(mixed...):mixed $callback   The callback to pass the values to
	 * @return RecordSet  A set of records that resulted from the mapping operation
	 */
	public function buildFromMap(callable $callback): RecordSet
	{
		$parameters = func_get_args();

		$result = call_user_func_array(array($this, 'map'), $parameters);
		$result = is_array($result) ? $result : array();

		$classes = array();
		foreach ($result as $record) {
			if (!$record instanceof ActiveRecord) {
				throw new ProgrammerException(
					'The map operation specified, %1$s, returned something other than an ActiveRecord object',
					$callback
				);
			}

			$class = get_class($record);

			if (!isset($classes[$class])) {
				$classes[$class] = TRUE;
			}
		}

		// If no objects were returned we need to fake the class
		if (!$classes) {
			$classes = array('ActiveRecord' => TRUE);
		}

		return new RecordSet(array_keys($classes), array_values($result));
	}


	/**
	 * Calls a specific method on each object, returning an array of the results
	 *
	 * @param  string $method     The method to call
	 * @return array<int, mixed>  An array the size of the record set with one result from each record/method
	 */
	public function call(string $method): array
	{
		$parameters = array_slice(func_get_args(), 1);

		$output = array();
		foreach ($this->records as $record) {
			$callback = array($record, $method);
			if (!is_callable($callback)) {
				throw new ProgrammerException(
					'The method %s() does not exist on the class %s',
					$method,
					get_class($record)
				);
			}
			$output[] = call_user_func_array($callback, $parameters);
		}
		return $output;
	}


	/**
	 * Chunks the record set into an array of RecordSet objects
	 *
	 * Each RecordSet would contain `$number` records, except for the last,
	 * which will contain between 1 and `$number` records.
	 *
	 * @param  integer $number  The number of ActiveRecord objects to place in each RecordSet
	 * @return array<int, RecordSet>  An array of RecordSet objects
	 */
	public function chunk(int $number): array
	{
		$output = array();
		$number_of_sets = ceil($this->count()/$number);
		for ($i=0; $i < $number_of_sets; $i++) {
			$output[] = new RecordSet($this->class, array_slice($this->records, $i*$number, $number));
		}
		return $output;
	}


	/**
	 * Checks if the record set contains the record specified
	 *
	 * @param  ActiveRecord $record  The record to check, must exist in the database
	 * @return boolean  If the record specified is in this record set
	 */
	public function contains(ActiveRecord $record): bool
	{
		$class = get_class($record);
		if (!in_array($class, (array) $this->class)) {
			return FALSE;
		}

		if (!$record->exists()) {
			throw new ProgrammerException(
				'Only records that exist can be checked for in the record set'
			);
		}

		$hash = ActiveRecord::hash($record);

		foreach ($this->records as $_record) {
			if ($class != get_class($_record)) {
				continue;
			}
			if ($hash == ActiveRecord::hash($_record)) {
				return TRUE;
			}
		}

		return FALSE;
	}


	/**
	 * Returns the number of records in the set
	 *
	 * @param  boolean $ignore_limit  If set to `TRUE`, this method will return the number of records that would be in the set if there was no `LIMIT` clause
	 * @return integer  The number of records in the set
	 */
	public function count(mixed $ignore_limit=FALSE): int
	{
		if ($ignore_limit !== TRUE || $this->non_limited_count === NULL) {
			return sizeof($this->records);
		}

		if (!is_numeric($this->non_limited_count)) {
			try {
				$db = ORMDatabase::retrieve($this->getSingleClass(), 'read');
				// The integer cast here is to solve issues with the broken dblib
				// SQL Server driver that is sometimes present on Windows machines
				$result = self::runQuery($db, array($this->non_limited_count));
				$this->non_limited_count = (int) $result->fetchScalar();
			} catch (ExpectedException $e) {
				$this->non_limited_count = $this->count();
			}
		}
		return (int) $this->non_limited_count;
	}


	/**
	 * Removes all passed records from the current record set
	 *
	 * @param  RecordSet|array<array-key, mixed>|ActiveRecord $records                  The record set, array of records, or record to remove from the current record set, all instances will be removed
	 * @param  boolean                        $remember_original_count  If the number of records in the current set should be saved as the non-limited count for the new set - the page will be reset to `1` either way
	 * @return RecordSet  The records not present in the passed records
	 */
	public function diff(mixed $records, bool $remember_original_count=FALSE): RecordSet
	{
		$remove_records = array();

		if ($records instanceof ActiveRecord) {
			$records = array($records);
		} elseif ($records instanceof RecordSet) {
			$records = $records->getRecords();
		}
		foreach ($records as $record) {
			if (!$record instanceof ActiveRecord) {
				continue;
			}
			$class = get_class($record);
			$hash  = ActiveRecord::hash($record);
			$remove_records[$class . '::' . $hash] = TRUE;
		}

		$new_records = array();
		$classes     = array();

		foreach ($this->records as $record) {
			$class = get_class($record);
			$hash  = ActiveRecord::hash($record);
			if (!isset($remove_records[$class . '::' . $hash])) {
				$new_records[]   = $record;
				$classes[$class] = TRUE;
			}
		}

		if ($classes) {
			$class = array_keys($classes);
		} else {
			$class = $this->class;
		}

		return new RecordSet(
			$class,
			$new_records,
			$remember_original_count ? $this->count() : NULL
		);
	}


	/**
	 * Filters the records in the record set via a callback
	 *
	 * The `$callback` parameter can be one of three different forms to filter
	 * the records in the set:
	 *
	 *  - A callback that accepts a single record and returns `FALSE` if it should be removed
	 *  - A psuedo-callback in the form `'{record}::methodName'` to filter out any records where the output of `$record->methodName()` is equivalent to `FALSE`
	 *  - A conditions array that will remove any records that don't meet all of the conditions
	 *
	 * The conditions array can use one or more of the following `key => value`
	 * syntaxes to perform various comparisons. The array keys are method
	 * names followed by a comparison operator.
	 *
	 * {{{
	 * // The following forms work for any $value that is not an array
	 * 'methodName='                           => $value  // If the output is equal to $value
	 * 'methodName!'                           => $value  // If the output is not equal to $value
	 * 'methodName!='                          => $value  // If the output is not equal to $value
	 * 'methodName<>'                          => $value  // If the output is not equal to $value
	 * 'methodName<'                           => $value  // If the output is less than $value
	 * 'methodName<='                          => $value  // If the output is less than or equal to $value
	 * 'methodName>'                           => $value  // If the output is greater than $value
	 * 'methodName>='                          => $value  // If the output is greater than or equal to $value
	 * 'methodName~'                           => $value  // If the output contains the $value (case insensitive)
	 * 'methodName^~'                          => $value  // If the output starts with the $value (case insensitive)
	 * 'methodName$~'                          => $value  // If the output ends with the $value (case insensitive)
	 * 'methodName!~'                          => $value  // If the output does not contain the $value (case insensitive)
	 * 'methodName|methodName2|methodName3~'   => $value  // Parses $value as a search string and make sure each term is present in at least one output (case insensitive)
	 *
	 * // The following forms work for any $array that is an array
	 * 'methodName='                           => $array  // If the output is equal to at least one value in $array
	 * 'methodName!'                           => $array  // If the output is not equal to any value in $array
	 * 'methodName!='                          => $array  // If the output is not equal to any value in $array
	 * 'methodName<>'                          => $array  // If the output is not equal to any value in $array
	 * 'methodName~'                           => $array  // If the output contains one of the strings in $array (case insensitive)
	 * 'methodName^~'                          => $array  // If the output starts with one of the strings in $array (case insensitive)
	 * 'methodName$~'                          => $array  // If the output ends with one of the strings in $array (case insensitive)
	 * 'methodName!~'                          => $array  // If the output contains none of the strings in $array (case insensitive)
	 * 'methodName&~'                          => $array  // If the output contains all of the strings in $array (case insensitive)
	 * 'methodName|methodName2|methodName3~'   => $array  // If each value in the array is present in the output of at least one method (case insensitive)
	 *
	 * // The following works for an equal number of methods and values in the array
	 * 'methodName!|methodName2<|methodName3=' => array($value, $value2, $value3) // An OR statement - one of the method to value comparisons must be TRUE
	 *
	 * // The following accepts exactly two methods and two values, although the second value may be NULL
	 * 'methodName|methodName2><'              => array($value, $value2) // If the range of values from the methods intersects the range of $value and $value2 - should be dates, times, timestamps or numbers
	 * }}}
	 *
	 * @param  callable(mixed...):mixed|string|array<array-key, mixed> $procedure                The way in which to filter the records - see method description for possible forms
	 * @param  boolean               $remember_original_count  If the number of records in the current set should be saved as the non-limited count for the new set - the page will be reset to `1` either way
	 * @return RecordSet  A new RecordSet with the filtered records
	 */
	public function filter(mixed $procedure, bool $remember_original_count=FALSE): RecordSet
	{
		if (!$this->records) {
			return clone $this;
		}

		if (is_array($procedure) && is_string(key($procedure))) {
			$type       = 'conditions';
			$conditions = array();
			foreach ($procedure as $condition_key => $condition_value) {
				$conditions[(string) $condition_key] = $condition_value;
			}

		} elseif (is_string($procedure) && preg_match('#^\{record\}::([a-z0-9_\-]+)$#iD', $procedure, $matches)) {
			$type   = 'psuedo-callback';
			$method = $matches[1];

		} else {
			$type     = 'callback';
			$callback = $procedure;
			if (is_string($callback) && strpos($callback, '::') !== FALSE) {
				$callback = explode('::', $callback);
			}
		}

		$new_records = array();
		$classes     = (!is_array($this->class)) ? array($this->class => TRUE) : array();

		foreach ($this->records as $record) {
			switch ($type) {
				case 'conditions':
					$value = ActiveRecord::checkConditions($record, $conditions);
					break;

				case 'psuedo-callback':
					$value = $record->$method();
					break;

				case 'callback':
					if (!is_callable($callback)) {
						throw new ProgrammerException(
							'The callback specified is not callable'
						);
					}
					$value = call_user_func($callback, $record);
					break;
			}

			if ($value) {
				$classes[get_class($record)] = TRUE;

				$new_records[] = $record;
			}
		}

		return new RecordSet(
			array_keys($classes),
			$new_records,
			$remember_original_count ? $this->count() : NULL
		);
	}


	/**
	 * Returns the class name of the record being stored
	 *
	 * @return string|array<string>  The class name(s) of the records in the set
	 */
	public function getClass(): mixed
	{
		return $this->class;
	}


	/**
	 * Returns an iterator for the record set
	 *
	 * This method is required by the IteratorAggregate interface.
	 *
	 * @internal
	 *
	 * @return ArrayIterator<int, ActiveRecord>  An iterator for the record set
	 */
	public function getIterator(): ArrayIterator
	{
		return new ArrayIterator($this->records);
	}


	/**
	 * Returns the number of records the set was limited to
	 *
	 * @return integer|null  The number of records the set was limited to
	 */
	public function getLimit(): ?int
	{
		return (!$this->limit) ? NULL : $this->limit;
	}


	/**
	 * Returns the page of records this set represents
	 *
	 * @return integer  The page of records this set represents
	 */
	public function getPage(): int
	{
		return $this->page;
	}


	/**
	 * Returns the number of pages of records exist for the limit used when creating this set
	 *
	 * @return integer  The number of pages of records that exist for the limit specified
	 */
	public function getPages(): int
	{
		if (!$this->limit) {
			return 1;
		}
		return (int) ceil($this->count(TRUE) / $this->limit);
	}


	/**
	 * Returns the record at the zero-based index specified
	 *
	 * @throw  NoRemainingException  When the index is beyond the end of the set
	 *
	 * @param  integer $index  The index of the record to return
	 * @return ActiveRecord  The record requested
	 */
	public function getRecord(int $index): ActiveRecord
	{
		return $this->offsetGet($index);
	}


	/**
	 * Returns all of the records in the set
	 *
	 * @return array<int, ActiveRecord>  The records in the set
	 */
	public function getRecords(): array
	{
		return $this->records;
	}


	/**
	 * Returns the primary keys for all of the records in the set
	 *
	 * @return array<int, mixed>  The primary keys of all the records in the set
	 */
	public function getPrimaryKeys(): array
	{
		if (!sizeof($this->records)) {
			return array();
		}

		$this->validateSingleClass('get primary key');

		$table           = ORM::tablize($this->getSingleClass());
		$schema          = ORMSchema::retrieve($this->getSingleClass());
		$pk_columns      = $schema->getKeys($table, 'primary');
		$first_pk_column = $pk_columns[0];

		$primary_keys = array();

		foreach ($this->records as $number => $record) {
			$keys = array();

			foreach ($pk_columns as $pk_column) {
				$method = 'get' . Grammar::camelize($pk_column, TRUE);
				$keys[$pk_column] = $record->$method();
			}

			$primary_keys[$number] = (sizeof($pk_columns) == 1) ? $keys[$first_pk_column] : $keys;
		}

		return $primary_keys;
	}


	/**
	 * Returns all records in the current record set that are also present in the passed records
	 *
	 * @param  RecordSet|array<array-key, mixed>|ActiveRecord $records                  The record set, array of records, or record to create an intersection of with the current record set
	 * @param  boolean                        $remember_original_count  If the number of records in the current set should be saved as the non-limited count for the new set - the page will be reset to `1` either way
	 * @return RecordSet  The records present in the current record set that are also present in the passed records
	 */
	public function intersect(mixed $records, bool $remember_original_count=FALSE): RecordSet
	{
		$hashes = array();

		if ($records instanceof ActiveRecord) {
			$records = array($records);
		} elseif ($records instanceof RecordSet) {
			$records = $records->getRecords();
		}
		foreach ($records as $record) {
			if (!$record instanceof ActiveRecord) {
				continue;
			}
			$class = get_class($record);
			$hash  = ActiveRecord::hash($record);
			$hashes[$class . '::' . $hash] = TRUE;
		}

		$new_records = array();
		$classes     = array();

		foreach ($this->records as $record) {
			$class = get_class($record);
			$hash  = ActiveRecord::hash($record);
			if (isset($hashes[$class . '::' . $hash])) {
				$new_records[]   = $record;
				$classes[$class] = TRUE;
			}
		}

		if ($classes) {
			$class = array_keys($classes);
		} else {
			$class = $this->class;
		}

		return new RecordSet(
			$class,
			$new_records,
			$remember_original_count ? $this->count() : NULL
		);
	}


	/**
	 * Performs an [http://php.net/array_map array_map()] on the record in the set
	 *
	 * The record will be passed to the callback as the first parameter unless
	 * it's position is specified by the placeholder string `'{record}'`.
	 *
	 * Additional parameters can be passed to the callback in one of two
	 * different ways:
	 *
	 *  - Passing a non-array value will cause it to be passed to the callback
	 *  - Passing an array value will cause the array values to be passed to the callback with their corresponding record
	 *
	 * If an array parameter is too long (more items than records in the set)
	 * it will be truncated. If an array parameter is too short (less items
	 * than records in the set) it will be padded with `NULL` values.
	 *
	 * To allow passing the record as a specific parameter to the callback, a
	 * placeholder string `'{record}'` will be replaced with a the record. It
	 * is also possible to specify `'{record}::methodName'` to cause the output
	 * of a method from the record to be passed instead of the whole record.
	 *
	 * It is also possible to pass the zero-based record index to the callback
	 * by passing a parameter that contains `'{index}'`.
	 *
	 * @param  callable(mixed...):mixed $callback   The callback to pass the values to
	 * @return array<array-key, mixed>  An array of the results from the callback
	 */
	public function map(callable $callback): array
	{
		$parameters = array_slice(func_get_args(), 1);

		if (!$this->records) {
			return array();
		}

		$parameters_array = array();
		$found_record     = FALSE;
		$total_records    = sizeof($this->records);

		foreach ($parameters as $parameter) {
			if (!is_array($parameter)) {
				if (is_string($parameter) && preg_match('#^\{record\}::([a-z0-9_\-]+)$#iD', $parameter, $matches)) {
					$parameters_array[] = $this->call($matches[1]);
					$found_record = TRUE;
				} elseif ($parameter === '{record}') {
					$parameters_array[] = $this->records;
					$found_record = TRUE;
				} elseif ($parameter === '{index}') {
					$parameters_array[] = array_keys($this->records);
				} else {
					$parameters_array[] = array_pad(array(), $total_records, $parameter);
				}

			} elseif (sizeof($parameter) > $total_records) {
				$parameters_array[] = array_slice($parameter, 0, $total_records);
			} elseif (sizeof($parameter) < $total_records) {
				$parameters_array[] = array_pad($parameter, $total_records, NULL);
			} else {
				$parameters_array[] = $parameter;
			}
		}

		if (!$found_record) {
			array_unshift($parameters_array, $this->records);
		}

		if (is_string($callback) && strpos($callback, '::') !== FALSE) {
			$callback = explode('::', $callback);
		}

		array_unshift($parameters_array, $callback);

		$result = call_user_func_array('array_map', $parameters_array);

		return is_array($result) ? $result : array();
	}


	/**
	 * Merges the record set with more records
	 *
	 * @param  RecordSet|array<array-key, mixed>|ActiveRecord $records  The record set, array of records, or record to merge with the current record set, duplicates will **not** be removed
	 * @return RecordSet  The merged record sets
	 */
	public function merge(mixed $records): RecordSet
	{
		$classes = array_flip((array) $this->class);

		if ($records instanceof RecordSet) {
			$new_records = $records->records;
			$classes    += array_flip((array) $records->class);

		} elseif (is_array($records)) {
			$new_records = array();
			foreach ($records as $record) {
				if (!$record instanceof ActiveRecord) {
					throw new ProgrammerException(
						'One of the records specified is not an instance of %s',
						'ActiveRecord'
					);
				}
				$new_records[] = $record;
				$classes[get_class($record)] = TRUE;
			}

		} elseif ($records instanceof ActiveRecord) {
			$new_records = array($records);
			$classes[get_class($records)] = TRUE;

		} else {
			throw new ProgrammerException(
				'The records specified, %1$s, are invalid. Must be an %2$s, %3$s or an array of %4$s.',
				$records,
				'RecordSet',
				'ActiveRecord',
				'ActiveRecords'
			);
		}

		if (!$new_records) {
			return $this;
		}

		return new RecordSet(
			array_keys($classes),
			array_merge(
				$this->records,
				$new_records
			)
		);
	}


	/**
	 * Checks to see if an offset exists
	 *
	 * This method is required by the ArrayAccess interface.
	 *
	 * @internal
	 *
	 * @param  mixed $offset  The offset to check
	 * @return boolean  If the offset exists
	 */
	public function offsetExists(mixed $offset): bool
	{
		if (!is_int($offset) && !is_string($offset)) {
			return FALSE;
		}
		return isset($this->records[$offset]);
	}


	/**
	 * Returns a record based on the offset
	 *
	 * This method is required by the ArrayAccess interface.
	 *
	 * @internal
	 *
	 * @throws NoRemainingException  When the offset specified is beyond the last record
	 *
	 * @param  mixed $offset  The offset of the record to get
	 * @return ActiveRecord  The requested record
	 */
	public function offsetGet(mixed $offset): ActiveRecord
	{
		if ((!is_integer($offset) && !is_numeric($offset)) || $offset < 0) {
			throw new ProgrammerException(
				'The offset specified, %1$s, is invalid. Offsets must be a non-negative integer.',
				$offset
			);
		}
		$index = (int) $offset;
		if ($index >= count($this->records)) {
			throw new NoRemainingException(
				'The offset specified, %1$s, is beyond the last record in the set',
				$offset
			);
		}
		return $this->records[$index];
	}


	/**
	 * Prevents setting values to the record set
	 *
	 * This method is required by the ArrayAccess interface.
	 *
	 * @internal
	 *
	 * @param  mixed $offset  The offset to set
	 * @param  mixed $value   The value to set to the offset
	 * @return void
	 */
	public function offsetSet(mixed $offset, mixed $value): void
	{
		throw new ProgrammerException(
			'%1$s does not allow setting records via array syntax',
			'RecordSet'
		);
	}


	/**
	 * Prevents unsetting values from the record set
	 *
	 * This method is required by the ArrayAccess interface.
	 *
	 * @internal
	 *
	 * @param  mixed $offset  The offset to unset
	 * @return void
	 */
	public function offsetUnset(mixed $offset): void
	{
		throw new ProgrammerException(
			'%1$s does not allow unsetting records via array syntax',
			'RecordSet'
		);
	}


	/**
	 * Builds the related records for all records in this set in one DB query
	 *
	 * @param  string $related_class  This should be the name of a related class
	 * @param  string $route          This should be a column name or a join table name and is only required when there are multiple routes to a related table. If there are multiple routes and this is not specified, an ProgrammerException will be thrown.
	 * @return RecordSet  The record set object, to allow for method chaining
	 */
	private function prebuild(string $related_class, ?string $route=NULL): RecordSet
	{
		if (!$this->records) {
			return $this;
		}

		$this->validateSingleClass('prebuild');

		// If there are no primary keys we can just exit
		if (!array_merge($this->getPrimaryKeys())) {
			return $this;
		}

		ActiveRecord::validateClass($related_class);
		ActiveRecord::forceConfigure($related_class);

		$db     = ORMDatabase::retrieve($this->getSingleClass(), 'read');
		$schema = ORMSchema::retrieve($this->getSingleClass());

		$related_table = ORM::tablize($related_class);
		$table         = ORM::tablize($this->getSingleClass());

		$route        = ORMSchema::getRouteName($schema, $table, $related_table, $route, '*-to-many');
		$relationship = ORMSchema::getRoute($schema, $table, $related_table, $route, '*-to-many');

		$relationship_column         = is_string($relationship['column'] ?? NULL) ? $relationship['column'] : '';
		$relationship_related_column = is_string($relationship['related_column'] ?? NULL) ? $relationship['related_column'] : '';
		$has_join_table              = isset($relationship['join_table']);

		$table_with_route = ($route) ? $table . '{' . $route . '}' : $table;

		// Build the query out
		$select = $db->escape('SELECT %r.*', $related_table);
		$params = array(is_string($select) ? $select : '');

		// If we are going through a join table we need the related primary key for matching
		if ($has_join_table) {
			// We explicitly alias the column because of SQLite issues
			$aliased = $db->escape(", %r AS %r", $table_with_route . '.' . $relationship_column, $relationship_column);
			$params[0] .= is_string($aliased) ? $aliased : '';
		}

		$params[0] .= ' FROM :from_clause WHERE ';
		$params     = $this->addWhereParams($db, $schema, $params, $route);
		$params[0] = (is_string($params[0]) ? $params[0] : '') . ' :group_by_clause ORDER BY ';
		$params     = $this->addOrderByParams($db, $schema, $params, $related_class, $route);
		$params[0] = is_string($params[0]) ? $params[0] : '';

		$params = ORMDatabase::injectFromAndGroupByClauses($db, $schema, $params, $related_table);
		$params[0] = is_string($params[0]) ? $params[0] : '';

		// Add the joining column to the group by
		if (strpos($params[0], 'GROUP BY') !== FALSE) {
			$escaped_order = $db->escape(', %r ORDER BY', $table . '.' . $relationship_column);
			$params[0] = str_replace(
				' ORDER BY',
				is_string($escaped_order) ? $escaped_order : '',
				$params[0]
			);
		}

		// Run the query and inject the results into the records
		$result = self::runQuery($db, $params);

		$total_records = sizeof($this->records);
		for ($i=0; $i < $total_records; $i++) {


			// Get the record we are injecting into
			$record = $this->records[$i];
			$keys   = array();


			// If we are going through a join table, keep track of the record by the value in the join table
			if ($has_join_table) {
				try {
					$current_row = $result->current();
					if (is_array($current_row)) {
						$keys[$relationship_column] = $current_row[$relationship_column] ?? NULL;
					}
				} catch (ExpectedException $e) { }

			// If it is a straight join, keep track of the value by the related column value
			} else {
				$method = 'get' . Grammar::camelize($relationship_column, TRUE);
				$keys[$relationship_related_column] = $record->$method();
			}


			// Loop through and find each row for the current record
			$rows = array();

			try {
				$current = $result->current();
				while (is_array($current) && !array_diff_assoc(self::stringifyValues($keys), self::stringifyValues($current))) {
					$row = $result->fetchRow();

					// If we are going through a join table we need to remove the related primary key that was used for matching
					if ($has_join_table && is_array($row)) {
						unset($row[$relationship_column]);
					}

					$rows[] = $row;

					$current = $result->current();
				}
			} catch (ExpectedException $e) { }


			// Set up the result object for the new record set
			$set = new RecordSet($related_class, new ArrayIterator($rows));

			// Inject the new record set into the record
			$method = 'inject' . Grammar::pluralize($related_class);
			$record->$method($set, $route);
		}

		return $this;
	}


	/**
	 * Counts the related records for all records in this set in one DB query
	 *
	 * @param  string $related_class  This should be the name of a related class
	 * @param  string $route          This should be a column name or a join table name and is only required when there are multiple routes to a related table. If there are multiple routes and this is not specified, an ProgrammerException will be thrown.
	 * @return RecordSet  The record set object, to allow for method chaining
	 */
	private function precount(string $related_class, ?string $route=NULL): RecordSet
	{
		if (!$this->records) {
			return $this;
		}

		$this->validateSingleClass('precount');

		// If there are no primary keys we can just exit
		if (!array_merge($this->getPrimaryKeys())) {
			return $this;
		}

		ActiveRecord::validateClass($related_class);
		ActiveRecord::forceConfigure($related_class);

		$db     = ORMDatabase::retrieve($this->getSingleClass(), 'read');
		$schema = ORMSchema::retrieve($this->getSingleClass());

		$related_table = ORM::tablize($related_class);
		$table         = ORM::tablize($this->getSingleClass());

		$route        = ORMSchema::getRouteName($schema, $table, $related_table, $route, '*-to-many');
		$relationship = ORMSchema::getRoute($schema, $table, $related_table, $route, '*-to-many');

		$relationship_column = is_string($relationship['column'] ?? NULL) ? $relationship['column'] : '';

		// Build the query out
		$table_and_column = $table . '.' . $relationship_column;

		if (isset($relationship['join_table'])) {
			$table_to_join  = is_string($relationship['join_table']) ? $relationship['join_table'] : '';
			$column_to_join = is_string($relationship['join_column'] ?? NULL) ? $relationship['join_column'] : '';

		} else {
			$table_to_join  = $related_table;
			$column_to_join = is_string($relationship['related_column'] ?? NULL) ? $relationship['related_column'] : '';
		}

		$table_to_join_alias = $table_to_join;
		if ($table_to_join == $table) {
			$table_to_join_alias = $table_to_join . '_1';
		}

		// For tables in a specific schema, this prevents the alias from having a . in it
		if (strpos($table_to_join_alias, '.') !== FALSE) {
			$alias = preg_replace('#"?."?#', '_', $table_to_join_alias);
			$table_to_join_alias = is_string($alias) ? $alias : '';
		}

		$count_sql = $db->escape(
			"SELECT count(*) AS flourish__count, %r AS flourish__column FROM %r INNER JOIN %r AS %r ON %r = %r WHERE ",
			$table_and_column,
			$table,
			$table_to_join,
			$table_to_join_alias,
			$table_and_column,
			$table_to_join_alias . '.' . $column_to_join
		);
		$params = array(is_string($count_sql) ? $count_sql : '');
		$params = $this->addWhereParams($db, $schema, $params);
		$params[0] = is_string($params[0]) ? $params[0] : '';
		$group_by = $db->escape(' GROUP BY %r', $table_and_column);
		$params[0] .= is_string($group_by) ? $group_by : '';

		// Run the query and inject the results into the records
		$result = self::runQuery($db, $params);

		$counts = array();
		foreach ($result as $row) {
			if (!is_array($row)) {
				continue;
			}
			$count_column = $row['flourish__column'] ?? NULL;
			if (!is_string($count_column)) {
				continue;
			}
			$count_value = $row['flourish__count'] ?? 0;
			$counts[$count_column] = is_numeric($count_value) ? (int) $count_value : 0;
		}

		unset($result);

		$total_records = sizeof($this->records);
		$get_method   = 'get' . Grammar::camelize($relationship_column, TRUE);
		$tally_method = 'tally' . Grammar::pluralize($related_class);

		for ($i=0; $i < $total_records; $i++) {
			$record = $this->records[$i];
			$value  = $record->$get_method();
			$key    = is_scalar($value) ? (string) $value : '';
			$count  = (isset($counts[$key])) ? $counts[$key] : 0;
			$record->$tally_method($count, $route);
		}

		return $this;
	}


	/**
	 * Creates the objects for related records that are in a one-to-one or many-to-one relationship with the current class in a single DB query
	 *
	 * @param  string $related_class  This should be the name of a related class
	 * @param  string $route          This should be the column name of the foreign key and is only required when there are multiple routes to a related table. If there are multiple routes and this is not specified, an ProgrammerException will be thrown.
	 * @return RecordSet  The record set object, to allow for method chaining
	 */
	private function precreate(string $related_class, ?string $route=NULL): RecordSet
	{
		if (!$this->records) {
			return $this;
		}

		$this->validateSingleClass('precreate');

		// If there are no primary keys we can just exit
		if (!array_merge($this->getPrimaryKeys())) {
			return $this;
		}

		ActiveRecord::validateClass($related_class);
		ActiveRecord::forceConfigure($related_class);

		$relationship = ORMSchema::getRoute(
			ORMSchema::retrieve($this->getSingleClass()),
			ORM::tablize($this->getSingleClass()),
			ORM::tablize($related_class),
			$route,
			'*-to-one'
		);

		$relationship_column         = is_string($relationship['column'] ?? NULL) ? $relationship['column'] : '';
		$relationship_related_column = is_string($relationship['related_column'] ?? NULL) ? $relationship['related_column'] : '';

		$values = $this->call('get' . Grammar::camelize($relationship_column, TRUE));
		$values = array_unique(self::stringifyValues($values));

		self::build(
			$related_class,
			array(
				$relationship_related_column . '=' => $values
			)
		);

		return $this;
	}


	/**
	 * Reduces the record set to a single value via a callback
	 *
	 * The callback should take two parameters and return a single value:
	 *
	 *  - The initial value and the first record for the first call
	 *  - The result of the last call plus the next record for the second and subsequent calls
	 *
	 * {{{
	 * #!php
	 * function my_reduce($sum, $record)
	 * {
	 *     return $sum + $record->getQuantity();
	 * )
	 * // For the first record, 0.0 will be passed as the $sum, then subsequent
	 * // calls end up getting the return value of the last call to my_reduce()
	 * $total_quantity = $record_set->reduce('my_reduce', 0.0);
	 * }}}
	 *
	 * @param  callable(mixed...):mixed $callback       The callback to pass the records to - see method description for details
	 * @param  mixed    $initial_value  The initial value to seed reduce with
	 * @return mixed  The result of the reduce operation
	 */
	public function reduce(callable $callback, mixed $initial_value=NULL): mixed
	{
		if (!$this->records) {
			return $initial_value;
		}

		$result = $initial_value;

		if (is_string($callback) && strpos($callback, '::') !== FALSE) {
			$callback = explode('::', $callback);
		}
		if (!is_callable($callback)) {
			throw new ProgrammerException(
				'The callback specified is not callable'
			);
		}

		foreach($this->records as $record) {
			$result = call_user_func($callback, $result, $record);
		}

		return $result;
	}


	/**
	 * Slices a section of records from the set and returns a new set containing those
	 *
	 * @param  integer $offset                   The index to start at, negative indexes will slice that many records from the end
	 * @param  integer $length                   The number of records to return, negative values will stop that many records before the end, `NULL` will return all records to the end of the set - if there are not enough records, less than `$length` will be returned
	 * @param  boolean $remember_original_count  If the number of records in the current set should be saved as the non-limited count for the new set - the page will be reset to `1` either way
	 * @return RecordSet  The new slice of records
	 */
	public function slice(int $offset, ?int $length=NULL, bool $remember_original_count=FALSE): RecordSet
	{
		if ($length === NULL) {
			if ($offset >= 0) {
				$length = sizeof($this->records) - $offset;
			} else {
				$length = abs($offset);
			}
		}

		$limit = NULL;
		$page  = 1;

		if ($remember_original_count && $offset % $length == 0) {
			$limit = $length;
			$page  = (int) (($offset / $length) + 1);
		}

		return new RecordSet(
			$this->class,
			array_slice($this->records, $offset, $length),
			$remember_original_count ? $this->count() : NULL,
			$limit,
			$page
		);
	}


	/**
	 * Sorts the set by the return value of a method from the class created and rewind the interator
	 *
	 * This methods uses UTF8::inatcmp() to perform comparisons.
	 *
	 * @param  string $method     The method to call on each object to get the value to sort by
	 * @param  string $direction  Either `'asc'` or `'desc'`
	 * @return RecordSet  A new record set object, with the records sorted as requested
	 */
	public function sort(string $method, string $direction): RecordSet
	{
		if (!in_array($direction, array('asc', 'desc'))) {
			throw new ProgrammerException(
				'The sort direction specified, %1$s, is invalid. Must be one of: %2$s or %3$s.',
				$direction,
				'asc',
				'desc'
			);
		}

		$this->sort_method    = $method;
		$this->sort_direction = $direction;
		$set = $this->sortByCallback(array($this, 'sortCallback'));
		unset($this->sort_method);
		unset($this->sort_direction);

		return $set;
	}


	/**
	 * A usort callback to sort by methods on records
	 *
	 * @param ActiveRecord $a  The first record to compare
	 * @param ActiveRecord $b  The second record to compare
	 * @return integer  Negative if `$a` is less than `$b`, 0 if `$a` = `$b`, positive if `$a` is greater than `$b`
	 */
	private function sortCallback(ActiveRecord $a, ActiveRecord $b): int
	{
		if ($this->sort_direction == 'asc') {
			return UTF8::inatcmp($this->getSortValue($a), $this->getSortValue($b));
		}
		return UTF8::inatcmp($this->getSortValue($b), $this->getSortValue($a));
	}


	/**
	 * Returns the string value of a record method for sorting
	 *
	 * @param  ActiveRecord $record  The record to get the value from
	 * @return string  The string value of the sort method
	 */
	private function getSortValue(ActiveRecord $record): string
	{
		$value = $record->{$this->sort_method}();
		if (is_string($value)) {
			return $value;
		}
		return is_scalar($value) ? (string) $value : '';
	}


	/**
	 * Sorts the set by passing the callback to [http://php.net/usort `usort()`] and rewinds the interator
	 *
	 * @param  callable(ActiveRecord, ActiveRecord): int $callback  The function/method to pass to `usort()`
	 * @return RecordSet  A new record set object, with the records sorted as requested
	 */
	public function sortByCallback(callable $callback): RecordSet
	{
		$records = $this->records;
		usort($records, $callback);

		return new self(
			$this->class,
			$records,
			$this->non_limited_count,
			$this->limit,
			$this->page
		);
	}


	/**
	 * Splits the record set into an array of RecordSet objects
	 *
	 * Each RecordSet would contain ceil(number of records/`$number`) records,
	 * except for the last, which will contain between 1 and ceil(…) records.
	 *
	 * @param  integer $number  The number of RecordSet objects to create
	 * @return array<int, RecordSet>  An array of RecordSet objects
	 */
	public function split(int $number): array
	{
		$output = array();
		$records_per_set = (int) ceil($this->count()/$number);
		for ($i=0; $i < $number; $i++) {
			$output[] = new RecordSet($this->class, array_slice($this->records, $i*$records_per_set, $records_per_set));
		}
		return $output;
	}


	/**
	 * Throws an EmptySetException if the record set is empty
	 *
	 * @throws EmptySetException  When there are no record in the set
	 *
	 * @param  string $message  The message to use for the exception if there are no records in this set
	 * @return RecordSet  The record set object, to allow for method chaining
	 */
	public function tossIfEmpty(?string $message=NULL): RecordSet
	{
		if ($this->records) {
			return $this;
		}

		if ($message === NULL) {
			if (is_array($this->class)) {
				$names = array_map(array('ORM', 'getRecordName'), $this->class);
				$names = array_map(array('Grammar', 'pluralize'), $names);
				$name  = join(', ', $names);
			} else {
				$name = Grammar::pluralize(ORM::getRecordName($this->class));
			}

			$message = self::compose(
				'No %s could be found',
				$name
			);
		}

		throw new EmptySetException($message);
	}


	/**
	 * Returns a new RecordSet containing only unique records in the record set
	 *
	 * @param  boolean $remember_original_count  If the number of records in the current set should be saved as the non-limited count for the new set - the page will be reset to `1` either way
	 * @return RecordSet  The new record set with only unique records
	 */
	public function unique(bool $remember_original_count=FALSE): RecordSet
	{
		$records = array();

		foreach ($this->records as $record) {
			$class = get_class($record);
			$hash  = ActiveRecord::hash($record);
			if (isset($records[$class . '::' . $hash])) {
				continue;
			}
			$records[$class . '::' . $hash] = $record;
		}

		$set = new RecordSet(
			$this->class,
			array_values($records)
		);

		if ($remember_original_count) {
			$set->non_limited_count	= $this->count();
		}

		return $set;
	}


	/**
	 * Ensures the record set only contains a single kind of record to prevent issues with certain operations
	 *
	 * @param  string $operation  The operation being performed - used in the exception thrown
	 * @return void
	 */
	private function validateSingleClass(string $operation): void
	{
		if (!is_array($this->class) && $this->class != 'ActiveRecord') {
			return;
		}

		throw new ProgrammerException(
			'The %1$s operation can not be performed on a record set with multiple types (%2$s) of records',
			$operation,
			join(', ', is_array($this->class) ? $this->class : array($this->class))
		);
	}
}



/**
 * Copyright (c) 2007-2012 Will Bond <will@flourishlib.com>
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
