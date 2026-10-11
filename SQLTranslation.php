<?php

declare(strict_types=1);

/**
 * Takes a subset of SQL from IBM DB2, MySQL, PostgreSQL, Oracle, SQLite and MSSQL and translates into the various dialects allowing for cross-database code
 *
 * @copyright  Copyright (c) 2007-2011 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 * @link       http://flourishlib.com/SQLTranslation
 *
 * @version    1.0.0b20
 * @changes    1.0.0b20  Added fix for PostgreSQL to handle `INSERT` statements that don't specify any columns or values [wb, 2011-09-06]
 * @changes    1.0.0b19  Removed the stray method ::removeSQLiteIndexes() that was left over from moving code into SQLSchemaTranslation [wb, 2011-05-17]
 * @changes    1.0.0b18  Fixed `LENGTH()` and `SUBSTR()` functions for non-ascii characters being stored in MySQL, SQLite and DB2, moved `CREATE TABLE` support to SQLSchemaTranslation [wb, 2011-05-09]
 * @changes    1.0.0b17  Internal Backwards Compatiblity Break - changed the array keys for translated queries returned from ::translate() to include a number plus `:` before the original SQL, preventing duplicate keys [wb, 2010-07-14]
 * @changes    1.0.0b16  Added IBM DB2 support [wb, 2010-04-13]
 * @changes    1.0.0b15  Fixed a bug with MSSQL national character conversion when running a SQL statement with a sub-select containing joins [wb, 2009-12-18]
 * @changes    1.0.0b14  Changed PostgreSQL to cast columns in LOWER() calls to VARCHAR to allow UUID columns (which are treated as a VARCHAR by Schema) to work with default primary key ordering in RecordSet [wb, 2009-12-16]
 * @changes    1.0.0b13  Added a parameter to ::enableCaching() to provide a key token that will allow cached values to be shared between multiple databases with the same schema [wb, 2009-10-28]
 * @changes    1.0.0b12  Backwards Compatibility Break - Removed date translation functionality, changed the signature of ::translate(), updated to support quoted identifiers, added support for PostgreSQL, MSSQL and Oracle schemas [wb, 2009-10-22]
 * @changes    1.0.0b11  Fixed a bug with translating MSSQL national columns over an ODBC connection [wb, 2009-09-18]
 * @changes    1.0.0b10  Changed last bug fix to support PHP 5.1.6 [wb, 2009-09-18]
 * @changes    1.0.0b9   Fixed another bug with parsing table aliases for MSSQL national columns [wb, 2009-09-18]
 * @changes    1.0.0b8   Fixed a bug with parsing table aliases that occurs when handling MSSQL national columns [wb, 2009-09-09]
 * @changes    1.0.0b7   Fixed a bug with translating `NOT LIKE` operators in PostgreSQL [wb, 2009-07-15]
 * @changes    1.0.0b6   Changed replacement values in preg_replace() calls to be properly escaped [wb, 2009-06-11]
 * @changes    1.0.0b5   Update code to only translate data types inside of `CREATE TABLE` queries [wb, 2009-05-22]
 * @changes    1.0.0b4   Added the missing ::__get() method for callback support [wb, 2009-05-06]
 * @changes    1.0.0b3   Added Oracle and caching support, various bug fixes [wb, 2009-05-04]
 * @changes    1.0.0b2   Fixed a notice with SQLite foreign key constraints having no `ON` clauses [wb, 2009-02-21]
 * @changes    1.0.0b    The initial implementation [wb, 2007-09-25]
 */
class SQLTranslation
{
	/**
	 * Composes text using Text if loaded
	 *
	 * @param  string  $message    The message to compose
	 * @return string  The composed and possible translated message
	 */
	protected static function compose(string $message, mixed ...$args): string
	{
		$replacements = array();
		foreach ($args as $arg) {
			if (!is_scalar($arg) && $arg !== null) {
				throw new ProgrammerException(
					'%1$s was called with an argument that is not a scalar value',
					'compose()'
				);
			}
			$replacements[] = $arg;
		}

		if (class_exists('Text', false)) {
			return call_user_func_array(
				array('Text', 'compose'),
				array($message, $replacements)
			);
		} else {
			return vsprintf($message, $replacements);
		}
	}


	/**
	 * Takes the `FROM` clause from ::parseSelectSQL() and returns all of the tables and each one's alias
	 *
	 * @return array<string, string>  The tables in the `FROM` clause, in the format `{table_alias} => {table_name}`
	 */
	private static function parseTableAliases(string $sql): array
	{
		$aliases = array();

		// Turn comma joins into cross joins
		if (preg_match('#^(?:"?:?\w+"?(?:\s+(?:as\s+)?(?:"?\w+"?))?)(?:\s*,\s*(?:"?\w+"?(?:\s+(?:as\s+)?(?:"?\w+"?))?))*$#isD', $sql)) {
			$sql = str_replace(',', ' CROSS JOIN ', $sql);
		}

		$tables = preg_split('#\s+((?:(?:CROSS|INNER|OUTER|LEFT|RIGHT)?\s+)*?JOIN)\s+#i', $sql);
		if ($tables === false) {
			throw new ProgrammerException(
				'The SQL statement, %s, could not be parsed into tables',
				$sql
			);
		}

		foreach ($tables as $table) {
			// This grabs the table name and alias (if there is one)
			preg_match('#^\s*([":\w.]+|\(((?:[^()]+|\((?2)\))*)\))(?:\s+(?:as\s+)?((?!ON|USING)["\w.]+))?\s*(?:(?:ON|USING)\s+(.*))?\s*$#im', $table, $parts);

			$table_name  = $parts[1];
			$table_alias = (!empty($parts[3])) ? $parts[3] : $parts[1];

			$table_name  = str_replace('"', '', $table_name);
			$table_alias = str_replace('"', '', $table_alias);

			$aliases[$table_alias] = $table_name;
		}

		return $aliases;
	}


	/**
	 * Callback for custom SQLite function; calculates the cotangent of a number
	 *
	 * @internal
	 *
	 * @param  int|float $x  The number to calculate the cotangent of
	 * @return numeric  The contangent of `$x`
	 */
	public static function sqliteCotangent(int|float $x): mixed
	{
		return 1 / tan($x);
	}


	/**
	 * Callback for custom SQLite function; calculates the log to a specific base of a number
	 *
	 * @internal
	 *
	 * @param  integer $base  The base for the log calculation
	 * @param  int|float $num   The number to calculate the logarithm of
	 * @return numeric  The logarithm of `$num` to `$base`
	 */
	public static function sqliteLogBaseFirst(int $base, int|float $num): mixed
	{
		return log($num, $base);
	}


	/**
	 * Callback for custom SQLite function; returns the sign of the number
	 *
	 * @internal
	 *
	 * @param  int|float $x  The number to change the sign of
	 * @return numeric  `-1` if a negative sign, `0` if zero, `1` if positive sign
	 */
	public static function sqliteSign(mixed $x): mixed
	{
		if ($x == 0) {
			return 0;
		}
		if ($x > 0) {
			return 1;
		}
		return -1;
	}


	/**
	 * The Cache object to cache schema info and, optionally, translated queries to
	 *
	 * @var Cache
	 */
	private ?Cache $cache = null;

	/**
	 * The cache prefix to use for cache entries
	 *
	 * @var string
	 */
	private ?string $cache_prefix = null;

	/**
	 * The Database instance
	 *
	 * @var Database
	 */
	private Database $database;

	/**
	 * If debugging is enabled
	 *
	 * @var boolean
	 */
	private ?bool $debug = null;

	/**
	 * Database-specific schema information needed for translation
	 *
	 * @var array{national_columns?: array<string, list<string>>, national_types?: array<string, array<string, string>>}
	 */
	private array $schema_info;

	/**
	 * An instance of SQLSchemaTranslation for when DDL statements are run
	 *
	 * This object is only loaded when needed since it is so large.
	 *
	 * @var SQLSchemaTranslation
	 */
	private ?SQLSchemaTranslation $schema_translation = null;


	/**
	 * Sets up the class and creates functions for SQLite databases
	 *
	 * @param  Database $database    The database being translated for
	 * @return SQLTranslation
	 */
	public function __construct(Database $database)
	{
		$this->database = $database;
		$this->database->inject($this);

		if ($database->getType() == 'sqlite') {
			$this->createSQLiteFunctions();
		}

		$this->schema_info = array();
	}


	/**
	 * All requests that hit this method should be requests for callbacks
	 *
	 * @internal
	 *
	 * @param  string $method  The method to create a callback for
	 * @return array{0: self, 1: string}  The callback for the method requested
	 */
	public function __get(string $method)
	{
		return array($this, $method);
	}


	/**
	 * Clears all of the schema info out of the object and, if set, the Cache object
	 *
	 * @return void
	 */
	public function clearCache(): void
	{
		$this->schema_info = array();
		if ($this->cache) {
			$prefix = $this->makeCachePrefix();
			$this->cache->delete($prefix . 'schema_info');
		}
	}


	/**
	 * Adds a number of math functions to SQLite that MSSQL, MySQL and PostgreSQL have by default
	 *
	 * @return void
	 */
	private function createSQLiteFunctions(): void
	{
		$functions = array();
		$functions[] = array('acos',     'acos',                                         1);
		$functions[] = array('asin',     'asin',                                         1);
		$functions[] = array('atan',     'atan',                                         1);
		$functions[] = array('atan2',    'atan2',                                        2);
		$functions[] = array('ceil',     'ceil',                                         1);
		$functions[] = array('ceiling',  'ceil',                                         1);
		$functions[] = array('cos',      'cos',                                          1);
		$functions[] = array('cot',      array('SQLTranslation', 'sqliteCotangent'),    1);
		$functions[] = array('degrees',  'rad2deg',                                      1);
		$functions[] = array('exp',      'exp',                                          1);
		$functions[] = array('floor',    'floor',                                        1);
		$functions[] = array('ln',       'log',                                          1);
		$functions[] = array('log',      array('SQLTranslation', 'sqliteLogBaseFirst'), 2);
		$functions[] = array('ltrim',    'ltrim',                                        1);
		$functions[] = array('pi',       'pi',                                           0);
		$functions[] = array('power',    'pow',                                          2);
		$functions[] = array('radians',  'deg2rad',                                      1);
		$functions[] = array('rtrim',    'rtrim',                                        1);
		$functions[] = array('sign',     array('SQLTranslation', 'sqliteSign'),         1);
		$functions[] = array('sqrt',     'sqrt',                                         1);
		$functions[] = array('sin',      'sin',                                          1);
		$functions[] = array('tan',      'tan',                                          1);
		$functions[] = array('trim',     'trim',                                         1);

		$connection = $this->database->getConnection();
		if (!($connection instanceof \PDO)) {
			throw new ProgrammerException(
				'The database connection for %1$s is not a PDO instance',
				'createSQLiteFunctions()'
			);
		}

		foreach ($functions as $function) {
			$connection->sqliteCreateFunction($function[0], $function[1], $function[2]);
		}
	}


	/**
	 * Sets the schema info to be cached to the Cache object specified
	 *
	 * @param  Cache  $cache  The cache to cache to
	 * @return void
	 */
	public function enableCaching(Cache $cache, mixed $key_token = null): void
	{
		$this->cache = $cache;

		if ($key_token !== null) {
			if (!is_scalar($key_token)) {
				throw new ProgrammerException(
					'%1$s was called with a key token that is not a scalar value',
					'enableCaching()'
				);
			}
			$this->cache_prefix = 'SQLTranslation::' . $this->database->getType() . '::' . $key_token . '::';
		}

		$this->schema_info = $this->cache->get($this->makeCachePrefix() . 'schema_info', array());
	}


	/**
	 * Sets if debug messages should be shown
	 *
	 * @param  boolean $flag  If debugging messages should be shown
	 * @return void
	 */
	public function enableDebugging(bool $flag): void
	{
		$this->debug = (bool) $flag;
	}


	/**
	 * Fixes pulling unicode data out of national data type MSSQL columns
	 *
	 * @param  string $sql  The SQL to fix
	 * @return string  The fixed SQL
	 */
	private function fixMSSQLNationalColumns(string $sql): string
	{
		if (!preg_match_all('#select((?:(?:(?!\sfrom\s)[^()])+|\(((?:[^()]+|\((?2)\))*)\))*\s)from((?:(?:(?!\sunion\s|\swhere\s|\sgroup by\s|\slimit\s|\sorder by\s)[^()])+|\(((?:[^()]+|\((?4)\))*)\))*)(?=\swhere\s|\sgroup by\s|\slimit\s|\sorder by\s|\sunion\s|\)|$)#i', $sql, $matches, PREG_SET_ORDER)) {
			return $sql;
		}

		if (!isset($this->schema_info['national_columns']) || !isset($this->schema_info['national_types'])) {
			$result = $this->database->query(
				"SELECT
						c.table_schema AS \"schema\",
						c.table_name   AS \"table\",
						c.column_name  AS \"column\",
						c.data_type    AS \"type\"
					FROM
						INFORMATION_SCHEMA.COLUMNS AS c
					WHERE
						(c.data_type = 'nvarchar' OR
						 c.data_type = 'ntext' OR
						 c.data_type = 'nchar') AND
						c.table_catalog = DB_NAME()
					ORDER BY
						lower(c.table_name) ASC,
						lower(c.column_name) ASC"
			);

			$national_columns = array();
			$national_types   = array();

			foreach ($result as $row) {
				if (!is_array($row)) {
					throw new ProgrammerException(
						'The national column query for %1$s returned a row that is not an array',
						'fixMSSQLNationalColumns()'
					);
				}

				$table  = $row['table'] ?? null;
				$schema = $row['schema'] ?? null;
				$column = $row['column'] ?? null;
				$type   = $row['type'] ?? null;

				if (!is_string($table) || !is_string($schema) || !is_string($column) || !is_string($type)) {
					throw new ProgrammerException(
						'The national column query for %1$s returned a row with invalid column info',
						'fixMSSQLNationalColumns()'
					);
				}

				if (!isset($national_columns[$table])) {
					$national_columns[$table] = array();
					$national_types[$table]   = array();
					$national_columns[$schema . '.' . $table] = array();
					$national_types[$schema . '.' . $table]   = array();
				}
				$national_columns[$table][] = $column;
				$national_types[$table][$column] = $type;
				$national_columns[$schema . '.' . $table][] = $column;
				$national_types[$schema . '.' . $table][$column] = $type;
			}

			$this->schema_info['national_columns'] = $national_columns;
			$this->schema_info['national_types']   = $national_types;

			if ($this->cache) {
				$this->cache->set($this->makeCachePrefix() . 'schema_info', $this->schema_info);
			}
		} else {
			$national_columns = $this->schema_info['national_columns'];
			$national_types   = $this->schema_info['national_types'];
		}

		$additions = array();

		foreach ($matches as $select) {
			$select_clause = trim($select[1]);
			$from_clause   = trim($select[3]);

			$sub_selects = array();
			if (preg_match_all('#\((\s*SELECT\s+((?:[^()]+|\((?2)\))*))\)#i', $from_clause, $from_matches)) {
				$sub_selects = $from_matches[0];
				foreach ($sub_selects as $i => $sub_select) {
					$from_clause = preg_replace('#' . preg_quote($sub_select, '#') . '#', ':sub_select_' . $i, $from_clause, 1) ?? $from_clause;
				}
			}

			$table_aliases = self::parseTableAliases($from_clause);

			preg_match_all('#([^,()]+|\((?:(?1)|,)*\))+#i', $select_clause, $selections);
			$selections    = array_map('trim', $selections[0]);
			$to_fix        = array();

			foreach ($selections as $selection) {
				// We just skip CASE statements since we can't really do those reliably
				if (preg_match('#^case#i', $selection)) {
					continue;
				}

				if (preg_match('#(("?\w+"?\.)"?\w+"?)\.\*#i', $selection, $match)) {
					$match[1] = str_replace('"', '', $match[1]);
					$table = $table_aliases[$match[1]];
					if (empty($national_columns[$table])) {
						continue;
					}
					if (!isset($to_fix[$table])) {
						$to_fix[$table] = array();
					}
					$to_fix[$table] = array_merge($to_fix[$table], $national_columns[$table]);
				} elseif (preg_match('#\*#', $selection, $match)) {
					foreach ($table_aliases as $alias => $table) {
						if (empty($national_columns[$table])) {
							continue;
						}
						if (!isset($to_fix[$table])) {
							$to_fix[$table] = array();
						}
						$to_fix[$table] = array_merge($to_fix[$table], $national_columns[$table]);
					}
				} elseif (preg_match('#^(?:((?:"?\w+"?\.)?"?\w+"?)\.("?\w+"?)|((?:min|max|trim|rtrim|ltrim|substring|replace)\(((?:"?\w+"?\.)"?\w+"?)\.("?\w+"?).*?\)))(?:\s+as\s+("?\w+"?))?$#iD', $selection, $match)) {
					$table = ($match[1] ?? '') . ((isset($match[4])) ? $match[4] : '');

					$column = ($match[2] ?? '') . ((isset($match[5])) ? $match[5] : '');

					// Unquote identifiers
					$table  = str_replace('"', '', $table);
					$column = str_replace('"', '', $column);

					$table = $table_aliases[$table] ?? null;

					if ($table === null || empty($national_columns[$table]) || !in_array($column, $national_columns[$table])) {
						continue;
					}

					if (!isset($to_fix[$table])) {
						$to_fix[$table] = array();
					}

					// Handle column aliasing
					if (!empty($match[6])) {
						$column_value = array('column' => $column, 'alias' => str_replace('"', '', $match[6]));
					} else {
						$column_value = $column;
					}

					if (!empty($match[3])) {
						if (!is_array($column_value)) {
							$column_value = array('column' => $column_value);
						}
						$column_value['expression'] = $match[3];
					}

					$to_fix[$table][] = $column_value;

				// Match unqualified column names
				} elseif (preg_match('#^(?:("?\w+"?)|((?:min|max|trim|rtrim|ltrim|substring|replace)\(("?\w+"?).*?\)))(?:\s+as\s+("?\w+"?))?$#iD', $selection, $match)) {
					$column = ($match[1] ?? '') . ((isset($match[3])) ? $match[3] : '');

					// Unquote the identifiers
					$column = str_replace('"', '', $column);

					foreach ($table_aliases as $alias => $table) {
						if (empty($national_columns[$table])) {
							continue;
						}
						if (!in_array($column, $national_columns[$table])) {
							continue;
						}
						if (!isset($to_fix[$table])) {
							$to_fix[$table] = array();
						}

						// Handle column aliasing
						if (!empty($match[4])) {
							$column_value = array('column' => $column, 'alias' => str_replace('"', '', $match[4]));
						} else {
							$column_value = $column;
						}

						if (!empty($match[2])) {
							if (!is_array($column_value)) {
								$column_value = array('column' => $column_value);
							}
							$column_value['expression'] = $match[2];
						}

						$to_fix[$table][] = $column_value;
					}
				}
			}

			$reverse_table_aliases = array_flip($table_aliases);
			foreach ($to_fix as $table => $columns) {
				$columns = array_unique($columns, SORT_REGULAR);
				$alias   = $reverse_table_aliases[$table] ?? null;
				if ($alias === null) {
					continue;
				}
				foreach ($columns as $column) {
					if (is_array($column)) {
						$column_name = $column['column'];
						if (isset($column['alias'])) {
							$as = ' AS fmssqln__' . $column['alias'];
						} else {
							$as = ' AS fmssqln__' . $column_name;
						}
						if (isset($column['expression'])) {
							$expression = $column['expression'];
						} else {
							$expression = '"' . $alias . '"."' . $column_name . '"';
						}
					} else {
						$column_name = $column;
						$as          = ' AS fmssqln__' . $column;
						$expression  = '"' . $alias . '"."' . $column . '"';
					}
					if ($national_types[$table][$column_name] == 'ntext') {
						$cast = 'CAST(' . $expression . ' AS IMAGE)';
					} else {
						$cast = 'CAST(' . $expression . ' AS VARBINARY(MAX))';
					}
					$additions[] = $cast . $as;
				}
			}

			foreach ($sub_selects as $i => $sub_select) {
				$sql = preg_replace(
					'#:sub_select_' . $i . '\b#',
					strtr(
						$this->fixMSSQLNationalColumns($sub_select),
						array('\\' => '\\\\', '$' => '\\$')
					),
					$sql,
					1
				) ?? $sql;
			}

			$replace = preg_replace(
				'#\bselect\s+' . preg_quote($select_clause, '#') . '#i',
				'SELECT ' . strtr(
					join(', ', array_merge($selections, $additions)),
					array('\\' => '\\\\', '$' => '\\$')
				),
				$select
			);
			$sql = str_replace($select, $replace, $sql);
		}

		return $sql;
	}


	/**
	 * Fixes empty string comparisons in Oracle
	 *
	 * @param  string $sql The SQL to fix
	 * @return string  The fixed SQL
	 */
	private function fixOracleEmptyStrings(string $sql): string
	{
		if (preg_match('#^(UPDATE\s+(?:(?:"?\w+"?\.)?"?\w+"?\.)?"?\w+"?\s+)(SET((?:(?:(?!\bwhere\b|\breturning\b)[^()])+|\(((?:[^()]+|\((?3)\))*)\))*))(.*)$#i', $sql, $set_match)) {
			$sql        = $set_match[1] . ':set_clause ' . $set_match[5];
			$set_clause = $set_match[2];
		} else {
			$set_clause = false;
		}

		$sql = preg_replace('#(?<=[\sa-z"])=\s*\'\'(?=[^\']|$)#', 'IS NULL', $sql) ?? $sql;
		$sql = preg_replace('#(?<=[\sa-z"])(!=|<>)\s*\'\'(?=[^\']|$)#', 'IS NOT NULL', $sql) ?? $sql;

		if ($set_clause) {
			$sql = preg_replace('#:set_clause\b#', strtr($set_clause, array('\\' => '\\\\', '$' => '\\$')), $sql, 1) ?? $sql;
		}

		return $sql;
	}


	/**
	 * Creates a unique cache prefix to help prevent cache conflicts
	 *
	 * @return string  The cache prefix to use
	 */
	private function makeCachePrefix(): string
	{
		if (!$this->cache_prefix) {
			$prefix  = 'SQLTranslation::' . $this->database->getType() . '::';
			if ($this->database->getHost()) {
				$prefix .= $this->database->getHost() . '::';
			}
			if ($this->database->getPort()) {
				$prefix .= $this->database->getPort() . '::';
			}
			$prefix .= $this->database->getDatabase() . '::';
			if ($this->database->getUsername()) {
				$prefix .= $this->database->getUsername() . '::';
			}
			$this->cache_prefix = $prefix;
		}

		return $this->cache_prefix;
	}


	/**
	 * Translates Flourish SQL into the dialect for the current database
	 *
	 * @internal
	 *
	 * @param  array<int|string, string> $statements            The SQL statements to translate
	 * @param  array<int, string>|null &$rollback_statements  SQL statements to rollback the returned SQL statements if something goes wrong - only applicable for MySQL `ALTER TABLE` statements
	 * @return array<int|string, string>  The translated SQL statements all ready for execution. Statements that have been translated will have string key of the number, `:` and the original SQL, all other will have a numeric key.
	 */
	public function translate(array $statements, mixed &$rollback_statements = null): array
	{
		$output = array();

		foreach ($statements as $number => $sql) {
			$new_sql = $this->translateBasicSyntax($sql);
			$new_sql = $this->translateCastClauses($new_sql);

			if (in_array($this->database->getType(), array('mssql', 'oracle', 'db2'))) {
				$new_sql = $this->translateLimitOffsetToRowNumber($new_sql);
			}

			// SQL Server does not like to give unicode results back to PHP without some coersion
			if ($this->database->getType() == 'mssql') {
				$new_sql = $this->fixMSSQLNationalColumns($new_sql);
			}

			if ($this->database->getType() == 'oracle') {
				// Oracle has this nasty habit of silently translating empty strings to null
				$new_sql = $this->fixOracleEmptyStrings($new_sql);
				$new_sql = $this->uppercaseIdentifiers($new_sql);
			}

			if ($this->database->getType() == 'db2') {
				$new_sql = $this->uppercaseIdentifiers($new_sql);
			}

			$extra_statements = array();
			if (preg_match('#^\s*(CREATE|DROP|ALTER|COMMENT)\b#i', $new_sql)) {
				if (!isset($this->schema_translation)) {
					$this->schema_translation = new SQLSchemaTranslation($this->database);
				}
				list($new_sql, $extra_statements) = $this->schema_translation->translate($new_sql, $rollback_statements);
			}

			if ($sql != $new_sql || $extra_statements) {
				Core::debug(
					self::compose(
						"Original SQL:%s",
						"\n" . $sql
					),
					$this->debug ?? false
				);
				$translated_sql = $new_sql;
				if ($extra_statements) {
					$translated_sql .= '; ' . join('; ', $extra_statements);
				}
				Core::debug(
					self::compose(
						"Translated SQL:%s",
						"\n" . $translated_sql
					),
					$this->debug ?? false
				);
			}

			$output = array_merge($output, array($number . ':' . $sql => $new_sql), array_values($extra_statements));
		}

		return $output;
	}


	/**
	 * Translates basic syntax differences of the current database
	 *
	 * @param  string $sql  The SQL to translate
	 * @return string  The translated SQL
	 */
	private function translateBasicSyntax(string $sql): string
	{
		$regex = array();

		if ($this->database->getType() == 'db2') {
			$regex = array(
				'#\brandom\(#i'         => 'RAND(',
				'#\bceil\(#i'           => 'CEILING(',
				'#\btrue\b#i'           => "'1'",
				'#\bfalse\b#i'          => "'0'",
				'#\bpi\(\)#i'           => '3.14159265358979',
				'#\bcot\(\s*((?>[^()\s]+|\(((?:[^()]+|\((?2)\))*)\))+)\s*\)#i' => '(1/TAN(\1))',
				'#\blength\(\s*((?>[^()\s]+|\(((?:[^()]+|\((?2)\))*)\))+)\s*\)#i' => 'CHARACTER_LENGTH(\1, CODEUNITS32)',
				'#\bsubstr\(\s*((?>[^(),]+|\((?1)(?:,(?1))?\)|\(\))+)\s*,\s*((?>[^(),]+|\((?2)(?:,(?2))?\)|\(\))+)\s*,\s*((?>[^(),]+|\((?3)(?:,(?3))?\)|\(\))+)\s*\)#i' => 'SUBSTRING(\1, \2, \3, CODEUNITS32)',
				'#(?:\b|^)((?>[^()%\s]+|\(((?:[^()]+|\((?2)\))*)\))+)\s*%(?!\d+\$[lbdfristp]\b)\s*((?>[^()\s]+|\(((?:[^()]+|\((?4)\))*)\))+)(?:\b|$)#i' => 'MOD(\1, \3)',
				'#(?<!["\w.])((?>[^()\s]+|\(((?:[^()]+|\((?2)\))*)\))+)\s+(NOT\s+)?LIKE\s+((?>[^()\s]+|\(((?:[^()]+|\((?4)\))*)\))+)(?:\b|$)#i'    => 'LOWER(\1) \3LIKE LOWER(\4)',
				'#\blog\(\s*((?>[^(),]+|\((?1)(?:,(?1))?\)|\(\))+)\s*,\s*((?>[^(),]+|\((?2)(?:,(?2))?\)|\(\))+)\s*\)#i'                            => '(LN(\2)/LN(\1))'
			);
		} elseif ($this->database->getType() == 'mssql') {
			$regex = array(
				'#\bbegin\s*(?!tran)#i' => 'BEGIN TRANSACTION ',
				'#\brandom\(#i'         => 'RAND(',
				'#\batan2\(#i'          => 'ATN2(',
				'#\bceil\(#i'           => 'CEILING(',
				'#\bln\(#i'             => 'LOG(',
				'#\blength\(#i'         => 'LEN(',
				'#\bsubstr\(#i'			=> 'SUBSTRING(',
				'#\btrue\b#i'           => "'1'",
				'#\bfalse\b#i'          => "'0'",
				'#\|\|#i'               => '+',
				'#\btrim\(\s*((?>[^(),]+|\((?1)\)|\(\))+)\s*\)#i'  => 'RTRIM(LTRIM(\1))',
				'#\bround\(\s*((?>[^(),]+|\((?1)\)|\(\))+)\s*\)#i' => 'round(\1, 0)',
				'#\blog\(\s*((?>[^(),]+|\((?1)(?:,(?1))?\)|\(\))+)\s*,\s*((?>[^(),]+|\((?2)(?:,(?2))?\)|\(\))+)\s*\)#i' => '(LOG(\2)/LOG(\1))'
			);
		} elseif ($this->database->getType() == 'mysql') {
			$regex = array(
				'#\brandom\(#i' => 'rand(',
				'#\bpi\(\)#i'   => '(pi()+0.0000000000000)',
				'#\blength\(#i' => 'CHAR_LENGTH(',
			);
		} elseif ($this->database->getType() == 'oracle') {
			$regex = array(
				'#\btrue\b#i'     => '1',
				'#\bfalse\b#i'    => '0',
				'#\bceiling\(#i'  => 'CEIL(',
				'#\brandom\(\)#i' => '(ABS(DBMS_RANDOM.RANDOM)/2147483647)',
				'#\bpi\(\)#i'     => '3.14159265358979',
				'#\bcot\(\s*((?>[^()\s]+|\(((?:[^()]+|\((?2)\))*)\))+)\s*\)#i'     => '(1/TAN(\1))',
				'#\bdegrees\(\s*((?>[^()\s]+|\(((?:[^()]+|\((?2)\))*)\))+)\s*\)#i' => '(\1 * 57.295779513083)',
				'#\bradians\(\s*((?>[^()\s]+|\(((?:[^()]+|\((?2)\))*)\))+)\s*\)#i' => '(\1 * 0.017453292519943)',
				'#(?:\b|^)((?>[^()%\s]+|\(((?:[^()]+|\((?2)\))*)\))+)\s*%(?!\d+\$[lbdfristp]\b)\s*((?>[^()\s]+|\(((?:[^()]+|\((?4)\))*)\))+)(?:\b|$)#i' => 'MOD(\1, \3)',
				'#(?<!["\w.])((?>[^()\s]+|\(((?:[^()]+|\((?2)\))*)\))+)\s+(NOT\s+)?LIKE\s+((?>[^()\s]+|\(((?:[^()]+|\((?4)\))*)\))+)(?:\b|$)#i'    => 'LOWER(\1) \3LIKE LOWER(\4)'
			);
		} elseif ($this->database->getType() == 'postgresql') {
			$regex = array(
				'#(?<!["\w.])(["\w.]+)\s+(not\s+)?like\b#i'                           => 'CAST(\1 AS VARCHAR) \2ILIKE',
				'#\blower\(\s*(?<!["\w.])(["\w.]+)\s*\)#i'                            => 'LOWER(CAST(\1 AS VARCHAR))',
				'#\blike\b#i'                                                         => 'ILIKE',
				'#\b(INSERT\s+INTO\s+(?:\w+|"[^"]+")\s+)\(\s*\)\s+VALUES\s+\(\s*\)#i' => '\1DEFAULT VALUES'
			);
		} elseif ($this->database->getType() == 'sqlite') {
			if (version_compare($this->database->getVersion(), '3', '>=')) {
				$regex = array(
					'#\bcurrent_timestamp\b#i' => "datetime(CURRENT_TIMESTAMP, 'localtime')",
					'#\btrue\b#i'              => "'1'",
					'#\bfalse\b#i'             => "'0'",
					'#\brandom\(\)#i'          => '(ABS(RANDOM())/9223372036854775807)'
				);
			} else {
				$regex = array(
					'#\bcurrent_timestamp\b#i' => "CURRENT_TIMESTAMP()",
					'#\bcurrent_time\b#i'      => "CURRENT_TIME()",
					'#\bcurrent_date\b#i'      => "CURRENT_DATE()",
					'#\btrue\b#i'              => "'1'",
					'#\bfalse\b#i'             => "'0'",
					'#\brandom\(\)#i'          => '(ABS(RANDOM())/9223372036854775807)',
					// SQLite 2 doesn't support CAST, but is also type-less, so we remove it
					'#\bcast\(\s*((?:[^()\s]+|\(((?:[^()]+|\((?2)\))*)\))+)\s+as\s+(?:[^()\s]+|\(((?:[^()]+|\((?3)\))*)\))+\s*\)#i'	=> '\1'
				);
			}
		}

		return preg_replace(array_keys($regex), array_values($regex), $sql) ?? $sql;
	}


	/**
	 * Translates simple CAST() clauses
	 *
	 * @param string $sql  The SQL to translate
	 * @return string  The translated SQL
	 */
	private function translateCastClauses(string $sql): string
	{
		if (!preg_match_all('#\b(CAST\(.+?\s+AS\s+)(\w+(\([^)]+\))?\))#i', $sql, $matches, PREG_SET_ORDER)) {
			return $sql;
		}

		foreach ($matches as $match) {
			$regex = array();

			switch ($this->database->getType()) {
				case 'db2':
					$regex = array(
						'#\btext\b#i'       => 'CLOB(1 G)',
						'#\bblob\b(?!\()#i' => 'BLOB(2 G)'
					);
					break;

				case 'mssql':
					$regex = array(
						'#\bblob\b#i'      => 'IMAGE',
						'#\btimestamp\b#i' => 'DATETIME',
						'#\btime\b#i'      => 'DATETIME',
						'#\bdate\b#i'      => 'DATETIME',
						'#\bboolean\b#i'   => 'BIT',
						'#\bvarchar\b#i'   => 'NVARCHAR',
						'#\bchar\b#i'      => 'NCHAR',
						'#\btext\b#i'      => 'NTEXT'
					);
					break;

				case 'mysql':
					$regex = array(
						'#\btext\b#i'      => 'LONGTEXT',
						'#\bblob\b#i'      => 'LONGBLOB',
						'#\btimestamp\b#i' => 'DATETIME'
					);
					break;

				case 'oracle':
					$regex = array(
						'#\bbigint\b#i'  => 'INTEGER',
						'#\bboolean\b#i' => 'NUMBER(1)',
						'#\btext\b#i'    => 'CLOB',
						'#\bvarchar\b#i' => 'VARCHAR2',
						'#\btime\b#i'    => 'TIMESTAMP'
					);
					break;

				case 'postgresql':
					$regex = array(
						'#\bblob\b#i' => 'BYTEA'
					);
					break;

				case 'sqlite':
					$regex = array(

					);
					break;
			}

			$sql = str_replace(
				$match[0],
				$match[1] . (preg_replace(array_keys($regex), array_values($regex), $match[2]) ?? $match[2]),
				$sql
			);
		}

		return $sql;
	}


	/**
	 * Translates `LIMIT x OFFSET x` to `ROW_NUMBER() OVER (ORDER BY)` syntax
	 *
	 * @param  string $sql  The SQL to translate
	 * @return string  The translated SQL
	 */
	private function translateLimitOffsetToRowNumber(string $sql): string
	{
		if (!preg_match('#\sLIMIT\s#i', $sql)) {
			return $sql;
		}

		// Regex details:
		// 1 - The SELECT clause
		// 2 - () recursion handler
		// 3 - FROM clause
		// 4 - () recursion handler
		// 5 - ORDER BY clause
		// 6 - () recursion handler
		// 7 - LIMIT number
		// 8 - OFFSET number
		preg_match_all(
			'#select((?:(?:(?!\sfrom\s)[^()])+|\(((?:[^()]+|\((?2)\))*)\))*\s)(from(?:(?:(?!\slimit\s|\sorder by\s)[^()])+|\(((?:[^()]+|\((?4)\))*)\))*\s)(order by(?:(?:(?!\slimit\s)[^()])+|\(((?:[^()]+|\((?6)\))*)\))*\s)?limit\s+(\d+)(?:\s+offset\s+(\d+))?#i',
			$sql,
			$matches,
			PREG_SET_ORDER
		);

		foreach ($matches as $match) {
			$limit  = (int) $match[7];
			$offset = (int) ($match[8] ?? 0);

			if ($this->database->getType() == 'mssql') {
				// This means we have an offset clause
				if ($offset > 0) {
					if ($match[5] === '') {
						$match[5] = "ORDER BY rand(1) ASC";
					}

					$select  = $match[1] . ', ROW_NUMBER() OVER (';
					$select .= $match[5];
					$select .= ') AS flourish__row__num ';
					$select .= $match[3];

					$replacement = 'SELECT * FROM (SELECT ' . trim($match[1]) . ', ROW_NUMBER() OVER (' . $match[5] . ') AS flourish__row__num ' . $match[3] . ') AS original_query WHERE flourish__row__num > ' . $offset . ' AND flourish__row__num <= ' . ($limit + $offset) . ' ORDER BY flourish__row__num';

				// Otherwise we just have a limit
				} else {
					$replacement = 'SELECT TOP ' . $limit . ' ' . trim($match[1] . $match[3] . $match[5]);
				}

			// While Oracle has the row_number() construct, the rownum pseudo-column is better
			} elseif ($this->database->getType() == 'oracle') {
				 // This means we have an offset clause
				if ($offset > 0) {
					$replacement = 'SELECT * FROM (SELECT flourish__sq.*, rownum flourish__row__num FROM (SELECT' . $match[1] . $match[3] . $match[5] . ') flourish__sq WHERE rownum <= ' . ($limit + $offset) . ') WHERE flourish__row__num > ' . $offset;

				// Otherwise we just have a limit
				} else {
					$replacement = 'SELECT * FROM (SELECT' . $match[1] . $match[3] . $match[5] . ') WHERE rownum <= ' . $limit;
				}
			} elseif ($this->database->getType() == 'db2') {
				// This means we have an offset clause
				if ($offset > 0) {
					if ($match[5] === '') {
						$match[5] = "ORDER BY rand(1) ASC";
					}

					$select  = $match[1] . ', ROW_NUMBER() OVER (';
					$select .= $match[5];
					$select .= ') AS flourish__row__num ';
					$select .= $match[3];

					$replacement = 'SELECT * FROM (SELECT ' . trim($match[1]) . ', ROW_NUMBER() OVER (' . $match[5] . ') AS flourish__row__num ' . $match[3] . ') AS original_query WHERE flourish__row__num > ' . $offset . ' AND flourish__row__num <= ' . ($limit + $offset) . ' ORDER BY flourish__row__num';

				// Otherwise we just have a limit
				} else {
					$replacement = 'SELECT ' . trim($match[1] . $match[3] . $match[5]) . ' FETCH FIRST ' . $limit . ' ROWS ONLY';
				}
			} else {
				$replacement = $match[0];
			}

			$sql = str_replace($match[0], $replacement, $sql);
		}

		return $sql;
	}


	/**
	 * Oracle and DB2 require that all double-quoted identifiers be upper-case
	 *
	 * @param  string $sql The SQL to fix
	 * @return string  The fixed SQL
	 */
	private function uppercaseIdentifiers(string $sql): string
	{
		preg_match_all('#"[^"]+"#i', $sql, $matches, PREG_SET_ORDER);

		foreach ($matches as $match) {
			$sql = str_replace($match[0], strtoupper($match[0]), $sql);
		}

		return $sql;
	}
}



/**
 * Copyright (c) 2007-2011 Will Bond <will@flourishlib.com>
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
