<?php

declare(strict_types=1);

/**
 * Holds a single instance of the Connection class and provides database manipulation functionality for ORM code
 *
 * @copyright  Copyright (c) 2007-2011 Will Bond, others
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @author     Craig Ruksznis, iMarc LLC [cr-imarc] <craigruk@imarc.net>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 *
 * @version    1.0.0b32
 * @changes    1.0.0b32  Added support to ::addWhereClause() for the `^~` and `$~` operators [wb, 2011-06-20]
 * @changes    1.0.0b31  Fixed a bug with ::addWhereClause() generating invalid SQL [wb, 2011-05-10]
 * @changes    1.0.0b30  Fixed ::insertFromAndGroupByClauses() to insert `MAX()` around columns in related tables in the
 * `ORDER BY` clause when a `GROUP BY` is used [wb, 2011-02-03]
 * @changes    1.0.0b29  Added code to handle old PCRE engines that don't support unicode character properties [wb,
 * 2010-12-06]
 * @changes    1.0.0b28  Fixed a bug in the ProgrammerException that is thrown when an improperly formatted OR condition
 * is provided [wb, 2010-11-24]
 * @changes    1.0.0b27  Fixed ::addWhereClause() to ignore fuzzy search clauses with no values to match [wb,
 * 2010-10-19]
 * @changes    1.0.0b26  Fixed ::insertFromAndGroupByClauses() to handle SQL where a table is references in more than
 * one capitalization [wb, 2010-07-26]
 * @changes    1.0.0b25  Fixed ::insertFromAndGroupByClauses() to properly handle recursive relationships [wb,
 * 2010-07-22]
 * @changes    1.0.0b24  Fixed ::parseSearchTerms() to work with non-ascii terms [wb, 2010-06-30]
 * @changes    1.0.0b23  Fixed error messages in ::retrieve() [wb, 2010-04-23]
 * @changes    1.0.0b22  Added support for IBM DB2, fixed an issue with building record sets or records that have
 * recursive relationships [wb, 2010-04-13]
 * @changes    1.0.0b21  Changed ::injectFromAndGroupByClauses() to be able to handle table aliases that contain other
 * aliases inside of them [wb, 2010-03-03]
 * @changes    1.0.0b20  Fixed a bug where joining to a table two separate ways could cause table alias issues and
 * incorrect SQL to be generated [wb, 2009-12-16]
 * @changes    1.0.0b19  Added the ability to compare columns with the `=:`, `!:`, `<:`, `<=:`, `>:` and `>=:` operators
 * [wb, 2009-12-08]
 * @changes    1.0.0b18  Fixed a bug affecting where conditions with columns that are not null but have a default value
 * [wb, 2009-11-03]
 * @changes    1.0.0b17  Added support for multiple databases [wb, 2009-10-28]
 * @changes    1.0.0b16  Internal Backwards Compatibility Break - Renamed methods and significantly changed parameters
 * and functionality for SQL statements to use value placeholders, identifier escaping and to handle schemas [wb,
 * 2009-10-22]
 * @changes    1.0.0b15  Streamlined intersection operator SQL and added support for the second value being NULL [wb,
 * 2009-09-21]
 * @changes    1.0.0b14  Added support for the intersection operator `><` to ::createWhereClause() [wb, 2009-07-13]
 * @changes    1.0.0b13  Added support for the `AND LIKE` operator `&~` to ::createWhereClause() [wb, 2009-07-09]
 * @changes    1.0.0b12  Added support for the `NOT LIKE` operator `!~` to ::createWhereClause() [wb, 2009-07-08]
 * @changes    1.0.0b11  Added support for concatenated columns to ::escapeBySchema() [cr-imarc, 2009-06-19]
 * @changes    1.0.0b10  Updated ::createWhereClause() to properly handle NULLs for arrays of values when doing = and !=
 * comparisons [wb, 2009-06-17]
 * @changes    1.0.0b9   Changed replacement values in preg_replace() calls to be properly escaped [wb, 2009-06-11]
 * @changes    1.0.0b8   Fixed a bug with ::creatingWhereClause() where a null value would not be escaped property [wb,
 * 2009-05-12]
 * @changes    1.0.0b7   Fixed a bug where an OR condition in ::createWhereClause() could not have one of the values be
 * an array [wb, 2009-04-22]
 * @changes    1.0.0b6   ::insertFromAndGroupByClauses() will no longer wrap ungrouped columns if in a CAST or CASE
 * statement for ORDER BY clauses of queries with a GROUP BY clause [wb, 2009-03-23]
 * @changes    1.0.0b5   Fixed ::parseSearchTerms() to include stop words when they are the only thing in the search
 * string [wb, 2008-12-31]
 * @changes    1.0.0b4   Fixed a bug where loading a related record in the same table through a one-to-many relationship
 * caused recursion [wb, 2008-12-24]
 * @changes    1.0.0b3   Fixed a bug from 1.0.0b2 [wb, 2008-12-05]
 * @changes    1.0.0b2   Added support for != and <> to ::createWhereClause() and ::createHavingClause() [wb,
 * 2008-12-04]
 * @changes    1.0.0b    The initial implementation [wb, 2007-08-04]
 */
class ORMDatabase
{
	/**
	 * An array of Connection objects
	 *
	 * @var array<string, array<string, Connection>>
	 */
	private static array $database_objects = array();

	/**
	 * If the PCRE engine supports unicode character properties
	 *
	 * @var boolean
	 */
	private static ?bool $pcre_supports_unicode_character_properties = null;


	/**
	 * Allows attaching an Connection-compatible objects for by ORM code
	 *
	 * If a `$name` other than `default` is used, any ActiveRecord classes
	 * that should use it will need to be configured by passing the class name
	 * and `$name` to ::mapClassToDatabase(). The `$name` parameter should be
	 * unique per database or database master/slave setup.
	 *
	 * The `$role` is used by code to allow for master/slave database setups.
	 * There can only be one database object attached for either of the roles,
	 * `'read'` or `'write'`. If the role `'both'` is specified, it will
	 * be applied to both the `'read'` and `'write'` roles. Any sort of logic
	 * for picking one out of multiple databases should be done before this
	 * method is called.
	 *
	 * @param  Connection $database  An object that is compatible with Connection
	 * @param  string    $name      The name for the database instance
	 * @param  string    $role      If the database should be used for `'read'`, `'write'` or `'both'` operations
	 * @return void
	 */
	public static function attach(Connection $database, string $name = 'default', string $role = 'both'): void
	{
		$valid_roles = array('both', 'write', 'read');
		if (!in_array($role, $valid_roles)) {
			throw new ProgrammerException(
				'The role specified, %1$s, is invalid. Must be one of: %2$s.',
				$role,
				join(', ', $valid_roles)
			);
		}

		if (!isset(self::$database_objects[$name])) {
			self::$database_objects[$name] = array();
		}

		$roles = ($role == 'both') ? array('write', 'read') : array($role);

		foreach ($roles as $_role) {
			self::$database_objects[$name][$_role] = $database;
		}
	}


	/**
	 * Escapes a single value for SQL, ensuring a string result
	 *
	 * @param  Connection $db     The database to escape the value for
	 * @param  string  $type   The type of escape to perform
	 * @param  mixed   $value  The value to escape
	 * @return string  The escaped value
	 */
	private static function escapeSql(Connection $db, string $type, mixed $value): string
	{
		$escaped = $db->escape($type, $value);

		if (!is_string($escaped)) {
			throw new ProgrammerException(
				'A single escaped %1$s value was expected, %2$s given',
				$type,
				get_debug_type($escaped)
			);
		}

		return $escaped;
	}


	/**
	 * Extracts the SQL statement from a query parameters array
	 *
	 * @param  array<array-key, mixed>  $params  The query parameters
	 * @return string  The SQL statement portion of the query
	 */
	private static function initialSql(array $params): string
	{
		$sql = $params[0] ?? null;

		if (!is_string($sql)) {
			throw new ProgrammerException(
				'The first query parameter must be the SQL statement, %1$s given',
				get_debug_type($sql)
			);
		}

		return $sql;
	}


	/**
	 * Translated the where condition for a single column into a SQL clause
	 *
	 * @param  Connection $db              The database the query will be run on
	 * @param  Schema   $schema          The schema for the database
	 * @param  array     $params          The parameters for the Connection::query() call
	 * @param  string    $table           The table to create the condition for
	 * @param  string    $column          The column to store the value in, may also be shorthand column name like
	 * `table.column` or `table=>related_table.column`
	 * @param  string    $operator        Should be `'='`, `'!='`, `'!'`, `'<>'`, `'<'`, `'<='`, `'>'`, `'>='`, `'IN'`,
	 * `'NOT IN'`
	 * @param  mixed     $values          The value(s) to escape
	 * @param  string    $escaped_column  The escaped column to use in the SQL
	 * @param  string    $placeholder     This allows overriding the placeholder
	 * @return array  The modified parameters
	 */
	/**
	 * @param array<array-key, mixed> $params
	 * @return array<array-key, mixed>
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	private static function addColumnCondition(
		Connection $db,
		Schema $schema,
		array $params,
		string $table,
		string $column,
		string $operator,
		mixed $values,
		?string $escaped_column = null,
		?string $placeholder = null
	): array {
		// Some objects when cast to an array turn the members into array keys
		if (is_array($values)) {
			$values_array = $values;
		} elseif (is_object($values)) {
			$values_array = array($values);
		} else {
			$values_array = array($values);
		}
		$values = $values_array;
		// Make sure we have an array with something in it to compare to
		if (!$values) {
			$values = array(null);
		}

		// If the table and column specified are real and not some combination
		$real_column = $escaped_column === null && $placeholder === null;

		if ($escaped_column === null) {
			$escaped_column = $db->escape('%r', (strpos($column, '.') === false) ? $table . '.' . $column : $column);
		}

		list($table, $column) = self::getTableAndColumn($schema, $table, $column);

		if ($placeholder === null && !in_array($operator, array('=:', '!=:', '<:', '<=:', '>:', '>=:'))) {
			$placeholder = $schema->getColumnInfo($table, $column, 'placeholder');
		}

		$ec = is_string($escaped_column) ? $escaped_column : (is_scalar($escaped_column) ? (string)$escaped_column :
			'');
		$ph = is_string($placeholder) ? $placeholder : '%s';

		// More than one value
		if (sizeof($values) > 1) {
			switch ($operator) {
				case '=':
					$non_null_values = array();
					$has_null  = false;
					foreach ($values as $value) {
						if ($value === null) {
							$has_null = true;
							continue;
						}
						$non_null_values[] = $value;
					}
					if ($has_null) {
						$sql_acc = self::initialSql($params);
						$sql_acc .= '(' . $ec . ' IS NULL OR ';
						$params[0] = $sql_acc;
					}
					$sql_acc = self::initialSql($params);
					$sql_acc .= $ec . ' IN (' . $ph . ')';
					$params[0] = $sql_acc;
					$params[]   = $non_null_values;
					if ($has_null) {
						$sql_acc = self::initialSql($params);
						$sql_acc .= ')';
						$params[0] = $sql_acc;
					}
					break;

				case '!':
					$non_null_values = array();
					$has_null  = false;
					foreach ($values as $value) {
						if ($value === null) {
							$has_null = true;
							continue;
						}
						$non_null_values[] = $value;
					}
					if ($has_null) {
						$sql_acc = self::initialSql($params);
						$sql_acc .= '(' . $ec . ' IS NOT NULL AND ';
						$params[0] = $sql_acc;
					}
					$sql_acc = self::initialSql($params);
					$sql_acc .= $ec . ' NOT IN (' . $ph . ')';
					$params[0] = $sql_acc;
					$params[]   = $non_null_values;
					if ($has_null) {
						$sql_acc = self::initialSql($params);
						$sql_acc .= ')';
						$params[0] = $sql_acc;
					}
					break;

				case '~':
					$condition = array();
					foreach ($values as $value) {
						$sql_acc = self::initialSql($params);
						$condition[] = $ec . ' LIKE %s';
						if (!is_scalar($value) && !($value instanceof Stringable)) {
							throw new ProgrammerException('Expected stringable value');
						}
						$params[] = '%' . (string)$value . '%';
					}
					$sql_acc = self::initialSql($params);
					$sql_acc .= '(' . join(' OR ', $condition) . ')';
					$params[0] = $sql_acc;
					break;

				case '^~':
					$condition = array();
					foreach ($values as $value) {
						$sql_acc = self::initialSql($params);
						$condition[] = $ec . ' LIKE %s';
						if (!is_scalar($value) && !($value instanceof Stringable)) {
							throw new ProgrammerException('Expected stringable value');
						}
						$params[] = (string)$value . '%';
					}
					$sql_acc = self::initialSql($params);
					$sql_acc .= '(' . join(' OR ', $condition) . ')';
					$params[0] = $sql_acc;
					break;

				case '$~':
					$condition = array();
					foreach ($values as $value) {
						$sql_acc = self::initialSql($params);
						$condition[] = $ec . ' LIKE %s';
						if (!is_scalar($value) && !($value instanceof Stringable)) {
							throw new ProgrammerException('Expected stringable value');
						}
						$params[] = '%' . (string)$value;
					}
					$sql_acc = self::initialSql($params);
					$sql_acc .= '(' . join(' OR ', $condition) . ')';
					$params[0] = $sql_acc;
					break;

				case '&~':
					$condition = array();
					foreach ($values as $value) {
						$sql_acc = self::initialSql($params);
						$condition[] = $ec . ' LIKE %s';
						if (!is_scalar($value) && !($value instanceof Stringable)) {
							throw new ProgrammerException('Expected stringable value');
						}
						$params[] = '%' . (string)$value . '%';
					}
					$sql_acc = self::initialSql($params);
					$sql_acc .= '(' . join(' AND ', $condition) . ')';
					$params[0] = $sql_acc;
					break;

				case '!~':
					$condition = array();
					foreach ($values as $value) {
						$condition[] = $ec . ' NOT LIKE %s';
						if (!is_scalar($value) && !($value instanceof Stringable)) {
							throw new ProgrammerException('Expected stringable value');
						}
						$params[] = '%' . (string)$value . '%';
					}
					$sql_acc = self::initialSql($params);
					$sql_acc .= '(' . join(' AND ', $condition) . ')';
					$params[0] = $sql_acc;
					break;

				default:
					throw new ProgrammerException(
						'An invalid array comparison operator, %s, was specified for an array of values',
						$operator
					);
			}

		// A single value
		} else {
			$value = current($values);

			switch ($operator) {
				case '!:':
					$operator = '<>:';
					// no break
				case '=:':
				case '<:':
				case '<=:':
				case '>:':
				case '>=:':
					$sql_acc = self::initialSql($params);
					$sql_acc .= $ec . ' ';
					$params[0] = $sql_acc;
					$sql_acc = self::initialSql($params);
					$sql_acc .= substr($operator, 0, -1) . ' ';
					$params[0] = $sql_acc;

					// If the column to match is a function, split the function
					// name off so we can escape the column name
					$prefix = '';
					$suffix = '';
					if (is_string($value) && preg_match('#^([^(]+\()\s*([^\s]+)\s*(\))$#D', $value, $parts)) {
						 $prefix = $parts[1];
						 $value  = $parts[2];
						 $suffix = $parts[3];
					}

					if ($value === null) {
						$value = '';
					} elseif (!is_string($value)) {
						if (is_numeric($value) || $value instanceof Stringable) {
							$value = (string) $value;
						} else {
							throw new ProgrammerException(
								'An invalid value was specified for the %1$s column',
								$column
							);
						}
					}

					$sql_acc = self::initialSql($params);
					$sql_acc .= $prefix . self::escapeSql(
						$db,
						'%r',
						(strpos($value, '.') === false) ? $table . '.' . $value : $value
					) . $suffix;
					$params[0] = $sql_acc;
					break;

				case '=':
					if ($value === null) {
						$operator = 'IS';
					}
					$sql_acc = self::initialSql($params);
					$sql_acc .= $ec . ' ' . (string)$operator . ' ' . $ph;
					$params[0] = $sql_acc;
					$params[] = $value;
					break;

				case '!':
					$operator = '<>';
					if ($value !== null) {
						$sql_acc = self::initialSql($params);
						$sql_acc .= '(';
						$params[0] = $sql_acc;
					} else {
						$operator = 'IS NOT';
					}
					$sql_acc = self::initialSql($params);
					$sql_acc .= $ec . ' ' . (string)$operator . ' ' . $ph;
					$params[0] = $sql_acc;
					$params[] = $value;
					if ($value !== null) {
						$sql_acc = self::initialSql($params);
						$sql_acc .= ' OR ' . $ec . ' IS NULL)';
						$params[0] = $sql_acc;
					}
					break;

				case '<':
				case '<=':
				case '>':
				case '>=':
					$sql_acc = self::initialSql($params);
					$sql_acc .= $ec . ' ' . (string)$operator . ' ' . $ph;
					$params[0] = $sql_acc;
					$params[]   = $value;
					break;

				case '~':
					$sql_acc = self::initialSql($params);
					$sql_acc .= $ec . ' LIKE %s';
					$params[0] = $sql_acc;
					if (!is_scalar($value) && !($value instanceof Stringable)) {
						throw new ProgrammerException('Expected stringable value');
					}
						$params[]   = '%' . (string)$value . '%';
					break;

				case '^~':
					$sql_acc = self::initialSql($params);
					$sql_acc .= $ec . ' LIKE %s';
					$params[0] = $sql_acc;
					if (!is_scalar($value) && !($value instanceof Stringable)) {
						throw new ProgrammerException('Expected stringable value');
					}
						$params[]   = (string)$value . '%';
					break;

				case '$~':
					$sql_acc = self::initialSql($params);
					$sql_acc .= $ec . ' LIKE %s';
					$params[0] = $sql_acc;
					if (!is_scalar($value) && !($value instanceof Stringable)) {
						throw new ProgrammerException('Expected stringable value');
					}
						$params[]   = '%' . (string)$value;
					break;

				case '!~':
					$sql_acc = self::initialSql($params);
					$sql_acc .= $ec . ' NOT LIKE %s';
					$params[0] = $sql_acc;
					if (!is_scalar($value) && !($value instanceof Stringable)) {
						throw new ProgrammerException('Expected stringable value');
					}
						$params[]   = '%' . (string)$value . '%';
					break;

				default:
					throw new ProgrammerException(
						'An invalid comparison operator, %s, was specified for a single value',
						$operator
					);
			}
		}

		return $params;
	}


	/**
	 * Creates a `HAVING` clause from an array of conditions
	 *
	 * @internal
	 *
	 * @param  Connection $db          The database the query will be executed on
	 * @param  Schema   $schema      The schema for the database
	 * @param  array     $params      The params for the Connection::query() call
	 * @param  string    $table       The table the query is being executed on
	 * @param  array     $conditions  The array of conditions - see RecordSet::build() for format
	 * @return array  The params with the `HAVING` clause added
	 */
	/**
	 * @param array<array-key, mixed> $params
	 * @param array<array-key, mixed> $conditions
	 * @return array<array-key, mixed>
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	public static function addHavingClause(
		Connection $db,
		Schema $schema,
		array $params,
		string $table,
		array $conditions
	): array {
		$i = 0;
		foreach ($conditions as $expression => $value) {
			if ($i) {
				$sql_acc = self::initialSql($params);
				$sql_acc .= ' AND ';
				$params[0] = $sql_acc;
			}

			if (!is_string($expression)) {
				$expression = (string) $expression;
			}

			// Splits the operator off of the end of the expression
			if (in_array(substr($expression, -3), array('!=:', '>=:', '<=:', '<>:'))) {
				$operator = strtr(
					substr($expression, -3),
					array('<>:' => '!:', '!=:' => '!:')
				);
				$expression = substr($expression, 0, -3);
			} elseif (in_array(substr($expression, -2), array('<=', '>=', '!=', '<>', '=:', '!:', '<:', '>:'))) {
				$operator   = strtr(
					substr($expression, -2),
					array('<>' => '!', '!=' => '!')
				);
				$expression = substr($expression, 0, -2);
			} else {
				$operator   = substr($expression, -1);
				$expression = substr($expression, 0, -1);
			}

			// Quotes the identifier in the expression
			if (preg_match('#^([^(]+\()\s*([^\s]+)\s*(\))$#D', $expression, $parts)) {
				$expression = $parts[1] . self::escapeSql($db, '%r', $parts[2]) . $parts[3];
			} else {
				$parts = array('', $expression, '');
				$expression = $parts[1];
			}

			// The AVG, SUM and COUNT functions all return a number
			$function    = strtolower(substr($parts[2], 0, -1));
			$placeholder = (in_array($function, array('avg', 'sum', 'count'))) ? '%f' : null;

			// This removes stray quoting inside of {route} specified for shorthand column names
			$expression  = preg_replace('#(\{\w+)"\."(\w+\})#', '\1.\2', $expression);

			$params = self::addColumnCondition(
				$db,
				$schema,
				$params,
				$table,
				$parts[2],
				$operator,
				$value,
				$expression,
				$placeholder
			);

			$i++;
		}

		return $params;
	}


	/**
	 * Adds an `ORDER BY` clause to an array of params for an Connection::query() call
	 *
	 * @internal
	 *
	 * @param  Connection $db         The database the query will be executed on
	 * @param  Schema   $schema     The schema object for the database the query will be executed on
	 * @param  array     $params     The parameters for the Connection::query() call
	 * @param  string    $table      The table any ambigious column references will refer to
	 * @param  array     $order_bys  The array of order bys to use - see RecordSet::build() for format
	 * @return array  The params with a SQL `ORDER BY` clause added
	 */
	/**
	 * @param array<array-key, mixed> $params
	 * @param array<array-key, mixed> $order_bys
	 * @return array<array-key, mixed>
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	public static function addOrderByClause(
		Connection $db,
		Schema $schema,
		array $params,
		string $table,
		array $order_bys
	): array {
		$expressions = array();

		foreach ($order_bys as $column => $direction) {
			if (!strlen(trim((string) $column))) {
				throw new ProgrammerException(
					'An invalid sort column, %s, was specified',
					$column
				);
			}

			if (
				$direction !== null && !is_string($direction) && !is_numeric(
					$direction
				) && !($direction instanceof Stringable)
			) {
				throw new ProgrammerException(
					'An invalid direction, %s, was specified',
					$direction
				);
			}
			$direction = strtoupper((string) $direction);
			if (!in_array($direction, array('ASC', 'DESC'))) {
				throw new ProgrammerException(
					'An invalid direction, %s, was specified',
					$direction
				);
			}

			$col_str = is_string($column) ? $column : (string)$column;
			if (
				preg_match(
					'#^((?:max|min|avg|sum|count)\()?((?:(?:(?:"?\w+"?\.)?"?\w+(?:\{[\w.]+\})?"?=>)?"?(?:(?:\w+"?\."?)?'
						. '\w+)(?:\{[\w.]+\})?"?\.)?"?(?:\w+)"?)(?:\))?$#D',
					$col_str,
					$matches
				)
			) {
				// Parse the expression and get a table and column to determine the data type
				list ($clause_table, $clause_column) = self::getTableAndColumn($schema, $table, $matches[2]);
				$column_type = $schema->getColumnInfo($clause_table, $clause_column, 'type');

				// Make sure each column is qualified with a table name
				if (strpos($matches[2], '.') === false) {
					$matches[2] = $table . '.' . $matches[2];
				}

				$matches[2] = self::escapeSql($db, '%r', $matches[2]);

				// Text columns are converted to lowercase for more accurate sorting
				if (in_array($column_type, array('varchar', 'char', 'text'))) {
					$expression = 'LOWER(' . $matches[2] . ')';
				} else {
					$expression = $matches[2];
				}

				// If the column is in an aggregate function, add the function back in
				if ($matches[1]) {
					$expression = $matches[1] . $expression . ')';
				}

				$expressions[] = $expression . ' ' . $direction;
			} else {
				$expressions[] = $column . ' ' . $direction;
			}
		}

		$sql_acc = self::initialSql($params);
		$sql_acc .= join(', ', $expressions);
		$params[0] = $sql_acc;

		return $params;
	}


	/**
	 * Add the appropriate SQL and params for a `WHERE` clause condition for primary keys of the table specified
	 *
	 * @internal
	 *
	 * @param  Schema $schema        The schema for the database the query will be run on
	 * @param  array   $params        The currently constructed params for Connection::query() - the first param should
	 * be a SQL statement
	 * @param  string  $table         The table to build the where clause for
	 * @param  string  $table_alias   The alias for the table
	 * @param  array   &$values       The values array for the ActiveRecord object
	 * @param  array   &$old_values   The old values array for the ActiveRecord object
	 * @return array  The params to pass to Connection::query(), including the new primary key where condition
	 */
	/**
	 * @param array<array-key, mixed> $params
	 * @param array<array-key, mixed> $values
	 * @param array<array-key, mixed> $old_values
	 * @return array<array-key, mixed>
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	public static function addPrimaryKeyWhereParams(
		Schema $schema,
		array $params,
		string $table,
		string $table_alias,
		mixed &$values,
		mixed &$old_values
	): array {
		$pk_columns = $schema->getKeys($table, 'primary');

		$column_info = $schema->getColumnInfo($table);
		if (!is_array($column_info)) {
			$column_info = array();
		}

		$conditions = array();
		foreach ($pk_columns as $pk_column) {
			$value = ActiveRecord::retrieveOld($old_values, $pk_column, $values[$pk_column] ?? null);

			// This makes sure the query performs the way an insert will
			$pk_info = $column_info[$pk_column] ?? null;
			if ($value === null && is_array($pk_info) && !empty($pk_info['not_null']) && isset($pk_info['default'])) {
				$value = $pk_info['default'];
			}

			$params[] = $table_alias . '.' . $pk_column;
			$params[] = $value;

			$conditions[] = self::makeCondition($schema, $table, $pk_column, '=', $value);
		}

		$sql_acc = self::initialSql($params);
		$sql_acc .= join(' AND ', $conditions);
		$params[0] = $sql_acc;

		return $params;
	}


	/**
	 * Adds a `WHERE` clause, from an array of conditions, to the parameters for an Connection::query() call
	 *
	 * @internal
	 *
	 * @param  Connection $db          The database the query will be executed on
	 * @param  Schema   $schema      The schema for the database
	 * @param  array     $params      The parameters for the Connection::query() call
	 * @param  string    $table       The table any ambigious column references will refer to
	 * @param  array     $conditions  The array of conditions - see RecordSet::build() for format
	 * @return array  The params with the SQL `WHERE` clause added
	 */
	/**
	 * @param array<array-key, mixed> $params
	 * @param array<array-key, mixed> $conditions
	 * @return array<array-key, mixed>
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	public static function addWhereClause(
		Connection $db,
		Schema $schema,
		array $params,
		string $table,
		array $conditions
	): array {
		$i = 0;
		foreach ($conditions as $column => $values) {
			if ($i) {
				$sql_acc = self::initialSql($params);
				$sql_acc .= ' AND ';
				$params[0] = $sql_acc;
			}

			if (!is_string($column)) {
				$column = (string) $column;
			}

			if (in_array(substr($column, -3), array('!=:', '>=:', '<=:', '<>:'))) {
				$operator = strtr(
					substr($column, -3),
					array('<>:' => '!:', '!=:' => '!:')
				);
				$column   = substr($column, 0, -3);
			} elseif (
				in_array(
					substr($column, -2),
					array('<=', '>=', '!=', '<>', '!~', '&~', '^~', '$~', '><', '=:', '!:', '<:', '>:')
				)
			) {
				$operator = strtr(
					substr($column, -2),
					array('<>' => '!', '!=' => '!')
				);
				$column   = substr($column, 0, -2);
			} else {
				$operator = substr($column, -1);
				$column   = substr($column, 0, -1);
			}

			if (is_array($values)) {
				$values_array = $values;
			} elseif (is_object($values)) {
				$values_array = array($values);
			} else {
				$values_array = array($values);
			}
			$values = $values_array;
			if (!$values) {
				$values = array(null);
			}

			$new_values = array();
			foreach ($values as $value) {
				if ($value instanceof Stringable) {
					$value = (string) $value;
				} elseif (is_object($value)) {
					throw new ProgrammerException(
						'Expected a value that can be converted to a string'
					);
				}
				$new_values[] = $value;
			}
			$values = $new_values;

			// Multi-column condition
			if (preg_match('#(?<!\|)\|(?!\|)#', $column)) {
				$columns   = explode('|', $column);
				$operators = array();

				foreach ($columns as &$_column) {
					if (in_array(substr($_column, -3), array('!=:', '>=:', '<=:', '<>:'))) {
						$operators[] = strtr(
							substr($_column, -3),
							array('<>:' => '!:', '!=:' => '!:')
						);
						$_column     = substr($_column, 0, -3);
					} elseif (
						in_array(
							substr($_column, -2),
							array('<=', '>=', '!=', '<>', '!~', '&~', '^~', '$~', '=:', '!:', '<:', '>:')
						)
					) {
						$operators[] = strtr(
							substr($_column, -2),
							array('<>' => '!', '!=' => '!')
						);
						$_column     = substr($_column, 0, -2);
					} elseif (!ctype_alnum(substr($_column, -1))) {
						$operators[] = substr($_column, -1);
						$_column     = substr($_column, 0, -1);
					}
				}
				$operators[] = $operator;

				if (sizeof($operators) == 1) {
					// Make sure every column is qualified by a table name
					$new_columns = array();
					foreach ($columns as $column) {
						if (strpos($column, '.') === false) {
							$column = $table . '.' . $column;
						}
						$new_columns[] = $column;
					}
					$columns = $new_columns;

					// Handle fuzzy searches
					if ($operator == '~') {
						// If the value to search is a single string value, parse it for search terms
						if (sizeof($values) == 1 && is_string($values[0])) {
							$values = self::parseSearchTerms($values[0], true);
						}

						// Skip fuzzy matches with no values to match
						if ($values === array()) {
							$sql_acc = self::initialSql($params);
							$sql_acc .= ' 1 = 1 ';
							$params[0] = $sql_acc;
							$i++;
							continue;
						}

						$condition = array();
						foreach ($values as $value) {
							$sub_condition = array();
							foreach ($columns as $column) {
								$sub_condition[] = self::escapeSql($db, '%r', $column) . ' LIKE %s';
								if (!is_scalar($value)) {
									throw new ProgrammerException('Expected stringable value');
								}
								$params[] = '%' . (string)$value . '%';
							}
							$condition[] = '(' . join(' OR ', $sub_condition) . ')';
						}
						$sql_acc = self::initialSql($params);
						$sql_acc .= ' (' . join(' AND ', $condition) . ') ';
						$params[0] = $sql_acc;

					// Handle intersection
					} elseif ($operator == '><') {
						if (sizeof($columns) != 2 || sizeof($values) != 2) {
							throw new ProgrammerException(
								'The intersection operator, %s, requires exactly two columns and two values',
								$operator
							);
						}

						$escaped_columns = array(
							self::escapeSql($db, '%r', $columns[0]),
							self::escapeSql($db, '%r', $columns[1])
						);

						list($column_1_table, $column_1) = self::getTableAndColumn($schema, $table, $columns[0]);
						list($column_2_table, $column_2) = self::getTableAndColumn($schema, $table, $columns[1]);
						$placeholders = array(
							$schema->getColumnInfo($column_1_table, $column_1, 'placeholder'),
							$schema->getColumnInfo($column_2_table, $column_2, 'placeholder')
						);

						if ($values[1] === null) {
							$part_1 = '(' . $escaped_columns[1] . ' IS NULL AND ' . $escaped_columns[0] . ' = ' .
								(is_string(
									$placeholders[0]
								) ? $placeholders[0] : '%s') . ')';
							$part_2 = '(' . $escaped_columns[1] . ' IS NOT NULL AND ' . $escaped_columns[0] . ' <= ' .
								(is_string(
									$placeholders[0]
								) ? $placeholders[0] : '%s') . ' AND ' . $escaped_columns[1] . ' >= ' . (is_string(
									$placeholders[1]
								) ? $placeholders[1] : '%s') . ')';
							$params[] = $values[0];
							$params[] = $values[0];
							$params[] = $values[0];
						} else {
							$part_1 = '(' . $escaped_columns[0] . ' <= ' . (is_string(
								$placeholders[0]
							) ? $placeholders[0] : '%s') . ' AND ' . $escaped_columns[1] . ' >= ' . (is_string(
								$placeholders[1]
							) ? $placeholders[1] : '%s') . ')';
							$part_2 = '(' . $escaped_columns[0] . ' >= ' . (is_string(
								$placeholders[0]
							) ? $placeholders[0] : '%s') . ' AND ' . $escaped_columns[0] . ' <= ' . (is_string(
								$placeholders[0]
							) ? $placeholders[0] : '%s') . ')';
							$params[] = $values[0];
							$params[] = $values[0];
							$params[] = $values[0];
							$params[] = $values[1];
						}

						$sql_acc = self::initialSql($params);
						$sql_acc .= ' (' . $part_1 . ' OR ' . $part_2 . ') ';
						$params[0] = $sql_acc;
					} else {
						throw new ProgrammerException(
							'An invalid comparison operator, %s, was specified for multiple columns',
							$operator
						);
					}

				// Handle OR combos
				} else {
					if (sizeof($columns) != sizeof($values)) {
						throw new ProgrammerException(
							'When creating an %1$s where clause there must be an equal number of columns and values, '
								. 'however %2$s column(s) and %3$s value(s) were provided',
							'OR',
							sizeof($columns),
							sizeof($values)
						);
					}

					if (sizeof($columns) != sizeof($operators)) {
						throw new ProgrammerException(
							'When creating an %s where clause there must be a comparison operator for each column, '
								. 'however one or more is missing',
							'OR'
						);
					}

					$sql_acc = self::initialSql($params);
					$sql_acc .= ' (';
					$params[0] = $sql_acc;
					$iterations = sizeof($columns);
					for ($j = 0; $j < $iterations; $j++) {
						if ($j) {
							$sql_acc = self::initialSql($params);
							$sql_acc .= ' OR ';
							$params[0] = $sql_acc;
						}
						$params = self::addColumnCondition(
							$db,
							$schema,
							$params,
							$table,
							$columns[$j],
							$operators[$j],
							$values[$j]
						);
					}
					$sql_acc = self::initialSql($params);
					$sql_acc .= ') ';
					$params[0] = $sql_acc;
				}


			// Concatenated columns
			} elseif (strpos($column, '||') !== false) {
				$parts     = explode('||', $column);
				$new_parts = array();
				foreach ($parts as $part) {
					$part = trim($part);
					if ($part[0] != "'") {
						$new_parts[] = self::escapeSql($db, '%r', $part);
					} else {
						$new_parts[] = $part;
					}
				}
				$escaped_column = join('||', $new_parts);
				$params = self::addColumnCondition(
					$db,
					$schema,
					$params,
					$table,
					$column,
					$operator,
					$values,
					$escaped_column,
					'%s'
				);

			// Single column condition
			} else {
				$params = self::addColumnCondition($db, $schema, $params, $table, $column, $operator, $values);
			}

			$i++;
		}

		return $params;
	}


	/**
	 * Takes a table name, cleans off quoting and removes the schema name if unambiguous
	 *
	 * @param Schema $schema  The schema object for the database being inspected
	 * @param string  $table   The table name to be made cleaned
	 * @return string  The cleaned table name
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 * @throws ValidationException
	 */
	private static function cleanTableName(Schema $schema, string $table): string
	{
		$table = str_replace('"', '', $table);
		$tables = array_flip($schema->getTables());
		if (!isset($tables[$table])) {
			$short_table = preg_replace('#^\w\.#', '', $table);
			if (is_string($short_table) && isset($tables[$short_table])) {
				$table = $short_table;
			}
		}
		return strtolower($table);
	}


	/**
	 * Creates a `FROM` clause from a join array
	 *
	 * @internal
	 *
	 * @param  Connection $db     The database the query will be run on
	 * @param  array<array-key, array{join_type: string, table_name: string, table_alias: string, on_clause_fields?:
	 * array<array-key, string>}>  $joins  The joins to create the `FROM` clause out of
	 * @return string  The from clause (does not include the word `FROM`)
	 */
	private static function createFromClauseFromJoins(Connection $db, array $joins): string
	{
		$sql = '';

		foreach ($joins as $join) {
			// Here we handle the first table in a join
			if ($join['join_type'] == 'none') {
				$sql .= self::escapeSql($db, '%r', $join['table_name']);
				if ($join['table_alias'] != $join['table_name']) {
					$sql .= ' ' . self::escapeSql($db, '%r', $join['table_alias']);
				}

			// Here we handle all other joins
			} else {
				$sql .= ' ' . strtoupper($join['join_type']) . ' ' . self::escapeSql($db, '%r', $join['table_name']);
				if ($join['table_alias'] != $join['table_name']) {
					$sql .= ' ' . self::escapeSql($db, '%r', $join['table_alias']);
				}
				if (
					!empty($join['on_clause_fields']) && isset(
						$join['on_clause_fields'][0],
						$join['on_clause_fields'][1]
					)
				) {
					$sql .= ' ON ' . self::escapeSql($db, '%r', $join['on_clause_fields'][0]) . ' = ' . self::escapeSql(
						$db,
						'%r',
						$join['on_clause_fields'][1]
					);
				}
			}
		}

		return $sql;
	}


	/**
	 * Creates join information for the table shortcut provided
	 *
	 * @internal
	 *
	 * @param  Schema $schema         The schema object for the tables/joins
	 * @param  string  $table          The primary table
	 * @param  string  $table_alias    The primary table alias
	 * @param  string  $related_table  The related table
	 * @param  string  $route          The route to the related table
	 * @param  array<array-key, array{join_type: string, table_name: string, table_alias: string, on_clause_fields?:
	 * array<array-key, string>}>  &$joins  The names of the joins that have been created
	 * @param  array<int, string>  &$used_aliases  The aliases that have been used
	 * @return string  The name of the significant join created
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 * @throws ValidationException
	 */
	private static function createJoin(
		Schema $schema,
		string $table,
		string $table_alias,
		string $related_table,
		string $route,
		array &$joins,
		array &$used_aliases
	): string {
		$routes = ORMSchema::getRoutes($schema, $table, $related_table);

		if (!isset($routes[$route])) {
			throw new ProgrammerException(
				'An invalid route, %1$s, was specified for the relationship from %2$s to %3$s',
				$route,
				$table,
				$related_table
			);
		}

		if (isset($joins[$table . '_' . $related_table . '{' . $route . '}'])) {
			return  $table . '_' . $related_table . '{' . $route . '}';
		}

		$route_info = $routes[$route];
		$join_table  = $route_info['join_table'] ?? null;
		$column      = $route_info['column'] ?? null;
		$join_column = $route_info['join_column'] ?? null;
		$join_related_column = $route_info['join_related_column'] ?? null;
		$related_column = $route_info['related_column'] ?? null;
		if (
			!is_string($column) || !is_string($join_column) || !is_string($join_related_column) || !is_string(
				$related_column
			)
		) {
			throw new ProgrammerException(
				'An invalid route, %1$s, was specified for the relationship from %2$s to %3$s',
				$route,
				$table,
				$related_table
			);
		}

		// If the route uses a join table
		if (is_string($join_table)) {
			$join = array(
				'join_type'        => 'LEFT JOIN',
				'table_name'       => $join_table,
				'table_alias'      => self::createNewAlias($join_table, $used_aliases),
				'on_clause_fields' => array()
			);

			$join2 = array(
				'join_type'        => 'LEFT JOIN',
				'table_name'       => $related_table,
				'table_alias'      => self::createNewAlias($related_table, $used_aliases),
				'on_clause_fields' => array()
			);

			if ($table != $related_table) {
				$join['on_clause_fields'][]  = $table_alias . '.' . $column;
				$join['on_clause_fields'][]  = $join['table_alias'] . '.' . $join_column;
				$join2['on_clause_fields'][] = $join['table_alias'] . '.' . $join_related_column;
				$join2['on_clause_fields'][] = $join2['table_alias'] . '.' . $related_column;
			} else {
				$join['on_clause_fields'][]  = $table_alias . '.' . $column;
				$join['on_clause_fields'][]  = $join['table_alias'] . '.' . $join_related_column;
				$join2['on_clause_fields'][] = $join['table_alias'] . '.' . $join_column;
				$join2['on_clause_fields'][] = $join2['table_alias'] . '.' . $related_column;
			}

			$joins[$table . '_' . $related_table . '{' . $route . '}_join'] = $join;
			$joins[$table . '_' . $related_table . '{' . $route . '}'] = $join2;

		// If the route is a direct join
		} else {
			$join = array(
				'join_type'        => 'LEFT JOIN',
				'table_name'       => $related_table,
				'table_alias'      => self::createNewAlias($related_table, $used_aliases),
				'on_clause_fields' => array()
			);

			if ($table != $related_table) {
				$join['on_clause_fields'][] = $table_alias . '.' . $column;
				$join['on_clause_fields'][] = $join['table_alias'] . '.' . $related_column;
			} else {
				$join['on_clause_fields'][] = $table_alias . '.' . $related_column;
				$join['on_clause_fields'][] = $join['table_alias'] . '.' . $column;
			}

			$joins[$table . '_' . $related_table . '{' . $route . '}'] = $join;
		}

		return $table . '_' . $related_table . '{' . $route . '}';
	}


	/**
	 * Creates a new table alias
	 *
	 * @internal
	 *
	 * @param  string $table          The table to create an alias for
	 * @param  array<int, string>  &$used_aliases  The aliases that have been used
	 * @return string  The alias to use for the table
	 */
	private static function createNewAlias(string $table, array &$used_aliases): string
	{
		if (!in_array($table, $used_aliases)) {
			$used_aliases[] = $table;
			return $table;
		}

		// This will strip any schema name off the beginning
		$cleaned_table = preg_replace('#^\w+\.#', '', $table);
		if (is_string($cleaned_table)) {
			$table = $cleaned_table;
		}

		$i = 1;
		while (in_array($table . $i, $used_aliases)) {
			$i++;
		}
		$used_aliases[] = $table . $i;
		return $table . $i;
	}


	/**
	 * Gets the table and column name from a shorthand column name
	 *
	 * @param  Schema $schema  The schema for the database
	 * @param  string  $table   The table to use when no table is specified in the shorthand
	 * @param  string  $column  The shorthand column definition - see RecordSet::build() for possible syntaxes
	 * @return array{0: string, 1: string}  The $table and $column, suitable for use with Schema
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 * @throws ValidationException
	 */
	private static function getTableAndColumn(Schema $schema, string $table, string $column): array
	{
		// Handle shorthand column names like table.column and table=>related_table.column
		if (preg_match('#((?:"?\w+"?\.)?"?\w+)(?:\{[\w.]+\})?"?\."?(\w+)"?$#D', $column, $match)) {
			$table  = $match[1];
			$column = $match[2];
		}
		$table  = self::cleanTableName($schema, $table);
		$column = str_replace('"', '', $column);

		return array($table, $column);
	}


	/**
	 * Finds all of the table names in the SQL and creates the appropriate `FROM` and `GROUP BY` clauses with all
	 * necessary joins
	 *
	 * The SQL string should contain two placeholders, `:from_clause` and
	 * `:group_by_clause`, although the later may be omitted if necessary. All
	 * columns should be qualified with their full table name.
	 *
	 * Here is an example SQL string to pass in presumming that the tables
	 * users and groups are in a relationship:
	 *
	 * {{{
	 * SELECT users.* FROM :from_clause WHERE groups.group_id = 5 :group_by_clause ORDER BY lower(users.first_name) ASC
	 * }}}
	 *
	 * @internal
	 *
	 * @param  Connection $db      The database the query is to be executed on
	 * @param  Schema   $schema  The schema for the database
	 * @param  array     $params  The parameters for the Connection::query() call
	 * @param  string    $table   The main table to be queried
	 * @return array  The params with the SQL `FROM` and `GROUP BY` clauses injected
	 */
	/**
	 * @param array<array-key, mixed> $params
	 * @return array<array-key, mixed>
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	public static function injectFromAndGroupByClauses(
		Connection $db,
		Schema $schema,
		array $params,
		string $table
	): array {
		$table_with_schema = $table;
		$table = self::cleanTableName($schema, $table);
		$joins = array();

		$sql = self::initialSql($params);

		if (strpos($sql, ':from_clause') === false) {
			throw new ProgrammerException(
				'No %1$s placeholder was found in:%2$s',
				':from_clause',
				"\n" . $sql
			);
		}

		$has_group_by_placeholder = strpos($sql, ':group_by_clause') !== false;

		// Separate the SQL from quoted values
		preg_match_all("#(?:'(?:''|\\\\'|\\\\[^']|[^'\\\\])*')|(?:[^']+)#", $sql, $matches);

		$table_alias = $table;

		$used_aliases  = array();
		$table_map     = array();

		// If we are not passing in existing joins, start with the specified table
		$joins[] = array(
			'join_type'   => 'none',
			'table_name'  => $table,
			'table_alias' => $table_alias
		);

		$used_aliases[] = $table_alias;

		foreach ($matches[0] as $match) {
			if ($match[0] != "'") {
				// This removes quotes from around . in the {route} specified of a shorthand column name
				$cleaned_match = preg_replace('#(\{\w+)"\."(\w+\})#', '\1.\2', $match);
				if (is_string($cleaned_match)) {
					$match = $cleaned_match;
				}

				preg_match_all(
					'#(?<!\w|"|=>)((?:"?((?:\w+"?\."?)?\w+)(?:\{([\w.]+)\})?"?=>)?("?(?:\w+"?\."?)?\w+)(?:\{([\w.]+)\})'
						. '?"?)\."?\w+"?(?=[^\w".{])#m',
					$match,
					$table_matches,
					PREG_SET_ORDER
				);
				foreach ($table_matches as $table_match) {
					if (!isset($table_match[5])) {
						$table_match[5] = null;
					}

					if (!empty($table_match[2])) {
						$table_match[2] = self::cleanTableName($schema, $table_match[2]);
					}
					$table_match[4] = self::cleanTableName($schema, $table_match[4]);

					if (in_array($db->getType(), array('oracle', 'db2'))) {
						foreach (array(2, 3, 4, 5) as $subpattern) {
							if (isset($table_match[$subpattern])) {
								$table_match[$subpattern] = strtolower($table_match[$subpattern]);
							}
						}
					}

					// This is a related table that is going to join to a once-removed table
					if (!empty($table_match[2])) {
						$related_table = $table_match[2];
						$route = ORMSchema::getRouteName($schema, $table, $related_table, $table_match[3]);

						$join_name = $table . '_' . $related_table . '{' . $route . '}';

						$once_removed_table = $table_match[4];

						// Add the once removed table to the aliases in case we also join directly to it
						// which may cause the replacements later in this method to convert first to the
						// real table name and then from the real table to the real table's alias
						if (!in_array($once_removed_table, $used_aliases)) {
							$used_aliases[] = $once_removed_table;
						}

						self::createJoin($schema, $table, $table_alias, $related_table, $route, $joins, $used_aliases);

						$route = ORMSchema::getRouteName($schema, $related_table, $once_removed_table, $table_match[5]);

						$join_name = self::createJoin(
							$schema,
							$related_table,
							$joins[$join_name]['table_alias'],
							$once_removed_table,
							$route,
							$joins,
							$used_aliases
						);

						$table_map[$table_match[1]] = self::escapeSql(
							$db,
							'%r',
							isset($joins[$join_name]['table_alias']) ? $joins[$join_name]['table_alias'] :
								$related_table
						);

						// Remove the once removed table from the aliases so we also join directly to it without an
						// alias
						unset($used_aliases[array_search($once_removed_table, $used_aliases)]);

					// This is a related table
					} elseif (
						($table_match[4] != $table || ORMSchema::getRoutes(
							$schema,
							$table,
							$table_match[4]
						)) && self::cleanTableName(
							$schema,
							$table_match[1]
						) != $table
					) {
						$related_table = $table_match[4];
						$route = ORMSchema::getRouteName($schema, $table, $related_table, $table_match[5]);

						$join_name = self::createJoin(
							$schema,
							$table,
							$table_alias,
							$related_table,
							$route,
							$joins,
							$used_aliases
						);

						$table_map[$table_match[1]] = self::escapeSql(
							$db,
							'%r',
							isset($joins[$join_name]['table_alias']) ? $joins[$join_name]['table_alias'] :
								$related_table
						);
					}
				}
			}
		}

		// Determine if we joined a *-to-many relationship
		$joined_to_many = false;
		foreach ($joins as $name => $join) {
			if (is_numeric($name)) {
				continue;
			}

			// Many-to-many uses a join table
			if (substr($name, -5) == '_join') {
				$joined_to_many = true;
				break;
			}

			$main_table   = preg_replace('#_' . $join['table_name'] . '{\w+}$#iD', '', $name);
			$second_table = $join['table_name'];
			$route        = preg_replace('#^[^{]+{([\w.]+)}$#D', '\1', $name);
			if (!is_string($main_table)) {
				$main_table = $name;
			}
			if (!is_string($route)) {
				$route = $name;
			}
			$routes       = ORMSchema::getRoutes($schema, $main_table, $second_table, '*-to-many');
			if (isset($routes[$route])) {
				$joined_to_many = true;
				break;
			}
		}

		$from_clause     = self::createFromClauseFromJoins($db, $joins);

		// If we are joining on a *-to-many relationship we need to group by the
		// columns in the main table to prevent duplicate entries
		if ($joined_to_many) {
			$column_info     = $schema->getColumnInfo($table);
			if (!is_array($column_info)) {
				$column_info = array();
			}
			$columns         = array();
			foreach ($column_info as $column => $info) {
				$columns[] = $table . '.' . $column;
			}
			$escaped_group_columns = array();
			foreach ($columns as $group_column) {
				$escaped_group_columns[] = self::escapeSql($db, '%r', $group_column);
			}
			$group_by_columns_str = implode(', ', $escaped_group_columns);
			$group_by_clause  = ' GROUP BY ' . $group_by_columns_str;
		} else {
			$group_by_clause = ' ';
			$group_by_columns_str = '';
		}

		// Put the SQL back together
		$new_sql = '';

		$preg_table_pattern = preg_quote($table_with_schema, '#') . '\.|' . preg_quote(
			'"' . trim($table_with_schema, '"') . '"',
			'#'
		) . '\.';
		foreach ($matches[0] as $match) {
			$temp_sql = $match;

			// Get rid of the => notation and the :from_clause placeholder
			if ($match[0] !== "'") {
				// This removes quotes from around . in the {route} specified of a shorthand column name
				$cleaned_temp = preg_replace('#(\{\w+)"\."(\w+\})#', '\1.\2', $match);
				if (is_string($cleaned_temp)) {
					$temp_sql = $cleaned_temp;
				}

				foreach ($table_map as $arrow_table => $alias) {
					$replaced_temp = preg_replace(
						'#(?<![\w"])' . preg_quote($arrow_table, '#') . '(?![\w"])#',
						$alias,
						$temp_sql
					);
					if (is_string($replaced_temp)) {
						$temp_sql = $replaced_temp;
					}
				}

				// This automatically adds max() around column from other tables when a group by is used
				if ($joined_to_many && preg_match('#order\s+by#i', $temp_sql)) {
					$parts = preg_split('#(order\s+by)#i', $temp_sql, -1, PREG_SPLIT_DELIM_CAPTURE);
					if (is_array($parts) && isset($parts[2])) {
						$maxed_parts = preg_replace(
							'#(?<!avg\(|count\(|max\(|min\(|sum\(|cast\(|case |when '
								. '|"|avg\("|count\("|max\("|min\("|sum\("|cast\("|case "|when "|\{|\.)((?!' .
									$preg_table_pattern .
								')((?:"|\b)\w+"?\.)?(?:"|\b)\w+"?\."?\w+"?)(?![\w."])#i',
							'max(\1)',
							$parts[2]
						);
						if (is_string($maxed_parts)) {
							$parts[2] = $maxed_parts;
						}
						$temp_sql = join('', $parts);
					}
				}

				$temp_sql = str_replace(':from_clause', $from_clause, $temp_sql);
				if ($has_group_by_placeholder) {
					$group_by_temp = preg_replace(
						'#\s:group_by_clause(\s|$)#',
						strtr($group_by_clause, array('\\' => '\\\\', '$' => '\\$')),
						$temp_sql
					);
					if (is_string($group_by_temp)) {
						$temp_sql = $group_by_temp;
					}
				} elseif (!empty($group_by_columns_str)) {
					$group_by_temp = preg_replace(
						'#(\sGROUP\s+BY\s((?!HAVING|ORDER\s+BY).)*)\s#i',
						'\1, ' . strtr($group_by_columns_str, array('\\' => '\\\\', '$' => '\\$')),
						$temp_sql
					);
					if (is_string($group_by_temp)) {
						$temp_sql = $group_by_temp;
					}
				}
			}

			$new_sql .= $temp_sql;
		}

		$params[0] = $new_sql;

		return $params;
	}


	/**
	 * Makes a condition for a SQL statement out of Connection::escape() placeholders
	 *
	 * @internal
	 *
	 * @param  Schema $schema               The schema object for the database the query will be executed on
	 * @param  string  $table                The table to create the condition for
	 * @param  string  $column               The column to make the condition for
	 * @param  string  $comparison_operator  The comparison operator for the condition
	 * @param  mixed   $value                The value for the condition, which allows the $comparison_operator to be
	 * tweaked for NULL values
	 * @return string  A SQL condition using Connection::escape() placeholders
	 * @throws ValidationException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	public static function makeCondition(
		Schema $schema,
		string $table,
		string $column,
		string $comparison_operator,
		mixed $value
	): string {
		list($table, $column) = self::getTableAndColumn($schema, $table, $column);

		$co = strtr($comparison_operator, array('!' => '<>', '!=' => '<>'));

		if ($value === null) {
			if (in_array(trim($co), array('=', 'IN'))) {
				$co = 'IS';
			} elseif (in_array(trim($co), array('<>', 'NOT IN'))) {
				$co = 'IS NOT';
			}
		}

		$placeholder = $schema->getColumnInfo($table, $column, 'placeholder');
		if (!is_string($placeholder)) {
			$placeholder = '%s';
		}

		return '%r ' . $co . ' ' . $placeholder;
	}


	/**
	 * Parses a search string into search terms, supports quoted phrases and removes extra punctuation
	 *
	 * @internal
	 *
	 * @param  string  $terms              A text string from a form input to parse into search terms
	 * @param  boolean $ignore_stop_words  If stop words should be ignored, this setting will be ignored if all words
	 * are stop words
	 * @return array<int, string>  The parsed search terms
	 */
	public static function parseSearchTerms(string $terms, bool $ignore_stop_words = false): array
	{
		$stop_words = array(
			'i',     'a',     'an',    'are',   'as',    'at',    'be',
			'by',    'de',    'en',    'en',    'for',   'from',  'how',
			'in',    'is',    'it',    'la',    'of',    'on',    'or',
			'that',  'the',   'this',  'to',    'was',   'what',  'when',
			'where', 'who',   'will'
		);

		preg_match_all('#(?:"[^"]+"|[^\s]+)#', $terms, $matches);

		$good_terms    = array();
		$ignored_terms = array();
		foreach ($matches[0] as $match) {
			// Remove phrases from quotes
			if ($match[0] == '"' && substr($match, -1)) {
				$match = substr($match, 1, -1);

			// Trim any punctuation off of the beginning and end of terms
			} else {
				if (self::$pcre_supports_unicode_character_properties === null) {
					Core::startErrorCapture();
					preg_match('#\pC#u', 'test');
					self::$pcre_supports_unicode_character_properties = !((bool) Core::stopErrorCapture());
				}
				if (self::$pcre_supports_unicode_character_properties) {
					$replacement = preg_replace('#(^[\pC\pC\pM\pP\pS\pZ]+|[\pC\pC\pM\pP\pS\pZ]+$)#iDu', '', $match);
					if (is_string($replacement)) {
						$match = $replacement;
					}
				} else {
					// This just removes ascii non-alphanumeric characters, plus the unicode punctuation and
					// supplemental punctuation blocks
					$replacement = preg_replace(
						'#(^[\x21-\x2F\x3A-\x40\x5B-\x60\x7B-\x7F\x{2000}-\x{206F}\x{2E00}-\x{2E7F}\x{00A1}-\x{00A9}\x{'
							. '00AB}-\x{00B1}\x{00B4}\x{00B6}-\x{00B8}\x{00BB}\x{00BF}\x{00D7}\x{00F7}]+|[\x21-\x2F\x3A'
							. '-\x40\x5B-\x60\x7B-\x7F\x{2000}-\x{206F}\x{2E00}-\x{2E7F}\x{00A1}-\x{00A9}\x{00AB}-\x{00'
							. 'B1}\x{00B4}\x{00B6}-\x{00B8}\x{00BB}\x{00BF}\x{00D7}\x{00F7}]+$)#iDu',
						'',
						$match
					);
					if (is_string($replacement)) {
						$match = $replacement;
					}
				}
			}

			if ($ignore_stop_words && in_array(strtolower($match), $stop_words)) {
				$ignored_terms[] = $match;
				continue;
			}
			$good_terms[] = $match;
		}

		// If no terms were parsed, that means all words were stop words
		if ($ignored_terms && !$good_terms) {
			$good_terms = $ignored_terms;
		}

		return $good_terms;
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
		self::$database_objects = array();
	}


	/**
	 * Return the instance of the Connection class
	 *
	 * @param  string $class  The class to retrieve the database for - if not specified, the default database will be
	 * returned
	 * @param  string $role   If the database will be used for `'write'`, `'read'` or `'either'` operations
	 * @return Connection  The database instance
	 */
	public static function retrieve(string $class = 'ActiveRecord', string $role = 'either'): Connection
	{
		if (substr($class, 0, 5) == 'name:') {
			$database_name = substr($class, 5);
		} else {
			$database_name = ORM::getDatabaseName($class);
		}

		if (!isset(self::$database_objects[$database_name])) {
			throw new ProgrammerException(
				'The database object named "%1$s" has not been attached via %2$s yet',
				$database_name,
				__CLASS__ . '::attach()'
			);
		}

		if ($role == 'write' || $role == 'read') {
			// If the user wants a read database but we are in a transaction on the write database, return
			// the write database to allow for comparing data changed since the transaction started
			if (
				$role == 'read' && isset(self::$database_objects[$database_name]['write']) &&
				self::$database_objects[$database_name]['write']->isInsideTransaction()
			) {
				$role = 'write';
			}

			if (!isset(self::$database_objects[$database_name][$role])) {
				throw new ProgrammerException(
					'The database object named "%1$s" for the %s$2 role has not been attached via %3$s yet',
					$database_name,
					$role,
					__CLASS__ . '::attach()'
				);
			}

			return self::$database_objects[$database_name][$role];
		}

		if (isset(self::$database_objects[$database_name]['write'])) {
			return self::$database_objects[$database_name]['write'];
		} elseif (isset(self::$database_objects[$database_name]['read'])) {
			return self::$database_objects[$database_name]['read'];
		}

		throw new ProgrammerException(
			'No database object is available for the named "%1$s" database',
			$database_name
		);
	}


	/**
	 * Removed aggregate function calls from where conditions array and puts them in a having conditions array
	 *
	 * @internal
	 *
	 * @param  array<array-key, mixed>  &$where_conditions  The where conditions to look through for aggregate functions
	 * @return array<string, mixed>  The conditions to be put in a `HAVING` clause
	 */
	public static function splitHavingConditions(array &$where_conditions): array
	{
		$having_conditions = array();

		foreach ($where_conditions as $column => $value) {
			if (!is_string($column) || !is_string($value)) {
				continue;
			}
			$column_has_aggregate             = preg_match('#^(count\(|max\(|avg\(|min\(|sum\()#i', $column);
			$is_column_compare_with_aggregate = substr($column, -1) == ':' && preg_match(
				'#^(count\(|max\(|avg\(|min\(|sum\()#i',
				$value
			);
			if ($column_has_aggregate || $is_column_compare_with_aggregate) {
				$having_conditions[$column] = $value;
				unset($where_conditions[$column]);
			}
		}

		return $having_conditions;
	}


	/**
	 * Forces use as a static class
	 *
	 * @return ORMDatabase
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
