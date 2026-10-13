<?php

declare(strict_types=1);

/**
 * Adds cross-database `CREATE TABLE`, `ALTER TABLE` and `COMMENT ON COLUMN` statements to SQLTranslation
 *
 * @copyright  Copyright (c) 2011-2012 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 * @link       http://flourishlib.com/SQLSchemaTranslation
 *
 * @version    1.0.0b3
 * @changes    1.0.0b3   Fixed associating a sequence with a column in PostgreSQL when setting auto-increment, fixed detection of some Oracle CHECK(IN) constraints, fixed default values for SQLite `ON DELETE` and `ON UPDATE` clauses [wb, 2012-01-12]
 * @changes    1.0.0b2   Fixed detection of explicitly named SQLite foreign key constraints [wb, 2011-08-23]
 * @changes    1.0.0b    The initial implementation [wb, 2011-05-09]
 *
 * @phpstan-type AlterTableData array{table: string, type: 'column_comment', column_name: string, comment: string}|array{table: string, type: 'rename_table', new_table_name: string}|array{table: string, type: 'rename_column', column_name: string, new_column_name: string}|array{table: string, type: 'add_column', column_definition: string, column_name: string}|array{table: string, type: 'drop_column', column_name: string}|array{table: string, type: 'alter_type', column_name: string, data_type: string}|array{table: string, type: 'drop_default', column_name: string}|array{table: string, type: 'set_default', column_name: string, default_value: string}|array{table: string, type: 'drop_not_null', column_name: string}|array{table: string, type: 'set_not_null', column_name: string, default?: string}|array{table: string, type: 'drop_check_constraint', column_name: string}|array{table: string, type: 'set_check_constraint', column_name: string, constraint: string}|array{table: string, type: 'drop_primary_key'}|array{table: string, type: 'add_primary_key', column_names: array<int, string>, autoincrement: bool, column_name?: string}|array{table: string, type: 'drop_foreign_key', column_name: string}|array{table: string, type: 'add_foreign_key', column_name: string, references: string, foreign_table: string, foreign_column: string}|array{table: string, type: 'drop_unique', column_names: array<int, string>, column_name?: string}|array{table: string, type: 'add_unique', column_names: array<int, string>, column_name?: string}
 * @phpstan-type AlterTableRenameTableData array{table: string, type: 'rename_table', new_table_name: string}
 */
class SQLSchemaTranslation
{
	/**
	 * Converts a SQL identifier to lower case and removes double quotes
	 *
	 * @param  string $identifier  The SQL identifier
	 * @return string  The unescaped identifier
	 */
	private static function unescapeIdentifier(string $identifier): string
	{
		return str_replace('"', '', strtolower($identifier));
	}


	/**
	 * Composes text using Text if loaded
	 *
	 * @param  string  $message    The message to compose
	 * @param  mixed   ...$args    The arguments to substitute into the message
	 * @return string  The composed and possible translated message
	 */
	protected static function compose(string $message, mixed ...$args): string
	{
		if (class_exists('Text', false)) {
			return call_user_func_array(
				array('Text', 'compose'),
				array($message, $args)
			);
		}

		$safe_args = array();
		foreach ($args as $arg) {
			if (is_scalar($arg) || $arg === null) {
				$safe_args[] = $arg;
			} elseif ($arg instanceof Stringable) {
				$safe_args[] = (string) $arg;
			} else {
				$safe_args[] = get_debug_type($arg);
			}
		}

		return vsprintf($message, $safe_args);
	}


	/**
	 * Accepts a CREATE TABLE statement and parses out the column definitions
	 *
	 * The return value is an associative array with the keys being column
	 * names and the values being arrays containing the following keys:
	 *  - definition:       (string) the complete column definition
	 *  - pieces:           (array) an associative array that can be joined back together to make the definition
	 *    - beginning
	 *    - column_name
	 *    - data_type
	 *    - not_null
	 *    - null
	 *    - default
	 *    - unique
	 *    - primary_key
	 *    - check_constraint
	 *    - foreign_key
	 *    - deferrable
	 *    - comment/end
	 *
	 * @param  string $sql  The SQL `CREATE TABLE` statement
	 * @return array<string, array{definition: string, pieces: array<string, string>}>  An associative array of information for each column - see method description for details
	 */
	private static function parseSQLiteColumnDefinitions(string $sql): array
	{
		preg_match_all(
			'#(?<=,|\(|\*/|\n)(\s*)[`"\'\[]?(\w+)[`"\'\]]?(\s+(?:[a-z]+)(?:\(\s*(\d+)(?:\s*,\s*(\d+))?\s*\))?)(?:(\s+NOT\s+NULL)|(\s+NULL)|(\s+DEFAULT\s+([^, \'\n]*|\'(?:\'\'|[^\']+)*\'))|(\s+UNIQUE)|(\s+PRIMARY\s+KEY(?:\s+AUTOINCREMENT)?)|(\s+CHECK\s*\("?\w+"?\s+IN\s+\(\s*(?:(?:[^, \'\n]+|\'(?:\'\'|[^\']+)*\')\s*,\s*)*\s*(?:[^, \'\n]+|\'(?:\'\'|[^\']+)*\')\)\)))*(\s+REFERENCES\s+[\'"`\[]?\w+[\'"`\]]?\s*\(\s*[\'"`\[]?\w+[\'"`\]]?\s*\)\s*(?:\s+(?:ON\s+DELETE|ON\s+UPDATE)\s+(?:CASCADE|NO\s+ACTION|RESTRICT|SET\s+NULL|SET\s+DEFAULT))*(\s+(?:DEFERRABLE|NOT\s+DEFERRABLE))?)?((?:\s*(?:/\*\s*((?:(?!\*/).)*?)\s*\*/))?\s*(?:,[ \t]*(?:--[ \t]*([^\n]*?)[ \t]*(?=\n)|/\*\s*((?:(?!\*/).)*?)\s*\*/)?|(?:--[ \t]*([^\n]*?)[ \t]*(?=\n))?\s*(?=\))))#msi',
			$sql,
			$matches,
			PREG_SET_ORDER
		);

		$output = array();
		foreach ($matches as $match) {
			$comment = '';
			foreach (array(16, 17, 18, 19) as $key) {
				if (isset($match[$key])) {
					$comment .= $match[$key];
				}
			}
			$output[strtolower($match[2])] = array(
				'definition'       => $match[0],
				'pieces'           => array(
					'beginning'        => $match[1],
					'column_name'      => $match[2],
					'data_type'        => $match[3],
					'not_null'         => $match[6],
					'null'             => $match[7],
					'default'          => $match[8],
					'unique'           => $match[10],
					'primary_key'      => $match[11],
					'check_constraint' => $match[12],
					'foreign_key'      => $match[13],
					'deferrable'       => $match[14],
					'comment/end'      => $match[15]
				)
			);
		}

		return $output;
	}


	/**
	 * Removes a search string from a `CREATE TABLE` statement
	 *
	 * @param  string $create_table_sql  The SQL `CREATE TABLE` statement
	 * @param  string $search            The string to remove
	 * @return string  The modified `CREATE TABLE` statement
	 */
	private static function removeFromSQLiteCreateTable(string $create_table_sql, string $search): string
	{
		if (preg_match('#,(\s*--.*)?\s*$#D', $search)) {
			$regex = '#' . preg_quote($search, '#') . '#';
		} else {
			$regex = '#,(\s*/\*.*?\*/\s*|\s*--[^\n]+\n\s*)?\s*' . preg_quote($search, '#') . '\s*#';
		}
		return preg_replace($regex, "\\1\n", $create_table_sql) ?? $create_table_sql;
	}


	/**
	 * The Database instance
	 *
	 * @var Database
	 */
	private Database $database;

	/**
	 * Database-specific schema information needed for translation
	 *
	 * @var array{
	 *   sqlite_indexes?: array<string, array{table: string, sql: string}>,
	 *   sqlite_create_tables?: array<string, string>,
	 *   sqlite_triggers?: array<string, array{table: string, sql: string}>,
	 *   version?: string|int,
	 *   foreign_keys_enabled?: bool,
	 *   sqlite_schema_info?: bool
	 * }
	 */
	private array $schema_info;


	/**
	 * Sets up the class
	 *
	 * @param  Database $database    The database being translated for
	 * @return SQLSchemaTranslation
	 */
	public function __construct(mixed $database)
	{
		$this->database    = $database;
		$this->schema_info = array();
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
		return function (mixed ...$args) use ($method): mixed {
			return $this->{$method}(...$args);
		};
	}


	/**
	 * Adds a SQLite index to the internal schema tracker
	 *
	 * @param  string $name   The index name
	 * @param  string $table  The table the index applies to
	 * @param  string $sql    The SQL definition of the index
	 * @return void
	 */
	private function addSQLiteIndex(string $name, string $table, string $sql): void
	{
		if (!isset($this->schema_info['sqlite_indexes'])) {
			$this->schema_info['sqlite_indexes'] = array();
		}

		$this->schema_info['sqlite_indexes'][$name] = array(
			'table' => $table,
			'sql'   => $sql
		);
	}


	/**
	 * Stores the SQL used to create a table
	 *
	 * @param  string $table  The table to set the `CREATE TABLE` statement for
	 * @param  string $sql    The SQL used to create the table
	 * @return void
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 */
	private function addSQLiteTable(string $table, string $sql): void
	{
		if (!isset($this->schema_info['sqlite_create_tables'])) {
			$this->getSQLiteTables();
		}

		$this->schema_info['sqlite_create_tables'][$table] = $sql;
	}


	/**
	 * Adds a SQLite trigger to the internal schema tracker
	 *
	 * @param  string $name   The trigger name
	 * @param  string $table  The table the trigger applies to
	 * @param  string $sql    The SQL definition of the trigger
	 * @return void
	 */
	private function addSQLiteTrigger(string $name, string $table, string $sql): void
	{
		if (!isset($this->schema_info['sqlite_triggers'])) {
			$this->schema_info['sqlite_triggers'] = array();
		}

		$this->schema_info['sqlite_triggers'][$name] = array(
			'table' => $table,
			'sql'   => $sql
		);
	}


	/**
	 * Creates a trigger for SQLite that handles an on delete clause
	 *
	 * @param  array<int, string>  &$extra_statements   An array of extra SQL statements to be added to the SQL
	 * @param  string $referencing_table   The table that contains the foreign key
	 * @param  string $referencing_column  The column the foreign key constraint is on
	 * @param  string $referenced_table    The table the foreign key references
	 * @param  string $referenced_column   The column the foreign key references
	 * @param  string $delete_clause       What is to be done on a delete
	 * @return string  The trigger
	 */
	private function createSQLiteForeignKeyTriggerOnDelete(array &$extra_statements, string $referencing_table, string $referencing_column, string $referenced_table, string $referenced_column, string $delete_clause): string
	{
		switch (strtolower($delete_clause)) {
			case 'no action':
			case 'restrict':
				$name = 'fkd_res_' . $referencing_table . '_' . $referencing_column;
				$trigger = 'CREATE TRIGGER ' . $name . '
							 BEFORE DELETE ON "' . $referenced_table . '"
							 FOR EACH ROW BEGIN
								 SELECT RAISE(ROLLBACK, \'delete on table "' . $referenced_table . '" can not be executed because it would violate the foreign key constraint on column "' . $referencing_column . '" of table "' . $referencing_table . '"\')
								 WHERE (SELECT "' . $referencing_column . '" FROM "' . $referencing_table . '" WHERE "' . $referencing_column . '" = OLD."' . $referenced_table . '") IS NOT NULL;
							 END';
				$extra_statements[] = $trigger;
				$this->addSQLiteTrigger($name, $referenced_table, $trigger);
				return $trigger;

			case 'set null':
				$name = 'fkd_nul_' . $referencing_table . '_' . $referencing_column;
				$trigger = 'CREATE TRIGGER ' . $name . '
							 BEFORE DELETE ON "' . $referenced_table . '"
							 FOR EACH ROW BEGIN
								 UPDATE "' . $referencing_table . '" SET "' . $referencing_column . '" = NULL WHERE "' . $referencing_column . '" = OLD."' . $referenced_column . '";
							 END';
				$extra_statements[] = $trigger;
				$this->addSQLiteTrigger($name, $referenced_table, $trigger);
				return $trigger;

			case 'cascade':
				$name = 'fkd_cas_' . $referencing_table . '_' . $referencing_column;
				$trigger = 'CREATE TRIGGER ' . $name . '
							 BEFORE DELETE ON "' . $referenced_table . '"
							 FOR EACH ROW BEGIN
								 DELETE FROM "' . $referencing_table . '" WHERE "' . $referencing_column . '" = OLD."' . $referenced_column . '";
							 END';
				$extra_statements[] = $trigger;
				$this->addSQLiteTrigger($name, $referenced_table, $trigger);
				return $trigger;
		}

		return '';
	}


	/**
	 * Creates a trigger for SQLite that handles an on update clause
	 *
	 * @param  array<int, string>  &$extra_statements   An array of extra SQL statements to be added to the SQL
	 * @param  string $referencing_table   The table that contains the foreign key
	 * @param  string $referencing_column  The column the foreign key constraint is on
	 * @param  string $referenced_table    The table the foreign key references
	 * @param  string $referenced_column   The column the foreign key references
	 * @param  string $update_clause       What is to be done on an update
	 * @return string  The trigger
	 */
	private function createSQLiteForeignKeyTriggerOnUpdate(array &$extra_statements, string $referencing_table, string $referencing_column, string $referenced_table, string $referenced_column, string $update_clause): string
	{
		switch (strtolower($update_clause)) {
			case 'no action':
			case 'restrict':
				$name = 'fku_res_' . $referencing_table . '_' . $referencing_column;
				$trigger = 'CREATE TRIGGER ' . $name . '
							 BEFORE UPDATE ON "' . $referenced_table . '"
							 FOR EACH ROW BEGIN
								 SELECT RAISE(ROLLBACK, \'update on table "' . $referenced_table . '" can not be executed because it would violate the foreign key constraint on column "' . $referencing_column . '" of table "' . $referencing_table . '"\')
								 WHERE (SELECT "' . $referencing_column . '" FROM "' . $referencing_table . '" WHERE "' . $referencing_column . '" = OLD."' . $referenced_column . '") IS NOT NULL;
							 END';
				$extra_statements[] = $trigger;
				$this->addSQLiteTrigger($name, $referenced_table, $trigger);
				return $trigger;

			case 'set null':
				$name = 'fku_nul_' . $referencing_table . '_' . $referencing_column;
				$trigger = 'CREATE TRIGGER ' . $name . '
							 BEFORE UPDATE ON "' . $referenced_table . '"
							 FOR EACH ROW BEGIN
								 UPDATE "' . $referencing_table . '" SET "' . $referencing_column . '" = NULL WHERE OLD."' . $referenced_column . '" <> NEW."' . $referenced_column . '" AND "' . $referencing_column . '" = OLD."' . $referenced_column . '";
							 END';
				$extra_statements[] = $trigger;
				$this->addSQLiteTrigger($name, $referenced_table, $trigger);
				return $trigger;

			case 'cascade':
				$name = 'fku_cas_' . $referencing_table . '_' . $referencing_column;
				$trigger = 'CREATE TRIGGER ' . $name . '
							 BEFORE UPDATE ON "' . $referenced_table . '"
							 FOR EACH ROW BEGIN
								 UPDATE "' . $referencing_table . '" SET "' . $referencing_column . '" = NEW."' . $referenced_column . '" WHERE OLD."' . $referenced_column . '" <> NEW."' . $referenced_column . '" AND "' . $referencing_column . '" = OLD."' . $referenced_column . '";
							 END';
				$extra_statements[] = $trigger;
				$this->addSQLiteTrigger($name, $referenced_table, $trigger);
				return $trigger;
		}

		return '';
	}


	/**
	 * Creates a trigger for SQLite that prevents inserting or updating to values the violate a `FOREIGN KEY` constraint
	 *
	 * @param  array<int, string>  &$extra_statements   An array of extra SQL statements to be added to the SQL
	 * @param  string  $referencing_table     The table that contains the foreign key
	 * @param  string  $referencing_column    The column the foriegn key constraint is on
	 * @param  string  $referenced_table      The table the foreign key references
	 * @param  string  $referenced_column     The column the foreign key references
	 * @param  boolean $referencing_not_null  If the referencing columns is set to not null
	 * @return string  The trigger
	 */
	private function createSQLiteForeignKeyTriggerValidInsertUpdate(array &$extra_statements, string $referencing_table, string $referencing_column, string $referenced_table, string $referenced_column, bool $referencing_not_null): string
	{
		// Verify key on inserts
		$name = 'fki_ver_' . $referencing_table . '_' . $referencing_column;
		$sql  = 'CREATE TRIGGER ' . $name . '
					  BEFORE INSERT ON "' . $referencing_table . '"
					  FOR EACH ROW BEGIN
						  SELECT RAISE(ROLLBACK, \'insert on table "' . $referencing_table . '" violates foreign key constraint on column "' . $referencing_column . '"\')
							  WHERE ';
		if (!$referencing_not_null) {
			$sql .= 'NEW."' . $referencing_column . '" IS NOT NULL AND ';
		}
		$sql .= ' (SELECT "' . $referenced_column . '" FROM "' . $referenced_table . '" WHERE "' . $referenced_column . '" = NEW."' . $referencing_column . '") IS NULL;
					  END';

		$extra_statements[] = $sql;
		$this->addSQLiteTrigger($name, $referencing_table, $sql);

		// Verify key on updates
		$name = 'fku_ver_' . $referencing_table . '_' . $referencing_column;
		$sql = 'CREATE TRIGGER ' . $name . '
					  BEFORE UPDATE ON "' . $referencing_table . '"
					  FOR EACH ROW BEGIN
						  SELECT RAISE(ROLLBACK, \'update on table "' . $referencing_table . '" violates foreign key constraint on column "' . $referencing_column . '"\')
							  WHERE ';
		if (!$referencing_not_null) {
			$sql .= 'NEW."' . $referencing_column . '" IS NOT NULL AND ';
		}
		$sql .= ' (SELECT "' . $referenced_column . '" FROM "' . $referenced_table . '" WHERE "' . $referenced_column . '" = NEW."' . $referencing_column . '") IS NULL;
					  END';

		$extra_statements[] = $sql;
		$this->addSQLiteTrigger($name, $referencing_table, $sql);

		return $sql;
	}


	/**
	 * Generates a 30 character constraint name for use with `ALTER TABLE` statements
	 *
	 * @param  string $sql    The `ALTER TABLE` statement
	 * @param  string $type   A 2-character string representing the type of constraint
	 */
	private function generateConstraintName(string $sql, string $type): string
	{
		$constraint = '_' . $type;
		$constraint = '_' . substr((string) time(), -8) . $constraint;
		return substr(md5(strtolower($sql)), 0, 30 - strlen($constraint)) . $constraint;
	}


	/**
	 * Returns the check constraint for a table and column
	 *
	 * @param  string $schema  The schema the table is in
	 * @param  string $table   The table the column is in
	 * @param  string $column  The column to get the check constraint for
	 * @return array{name: string, definition: string}|NULL  An associative array with the keys: `name` and `definition` or `NULL`
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	private function getDB2CheckConstraint(string $schema, string $table, string $column): mixed
	{
		$constraint = $this->query(
			"SELECT
				CH.TEXT,
				CH.CONSTNAME
			FROM
				SYSCAT.COLUMNS AS C INNER JOIN
				SYSCAT.COLCHECKS AS CC ON
					C.TABSCHEMA = CC.TABSCHEMA AND
					C.TABNAME = CC.TABNAME AND
					C.COLNAME = CC.COLNAME AND
					CC.USAGE = 'R' INNER JOIN
				SYSCAT.CHECKS AS CH ON
					C.TABSCHEMA = CH.TABSCHEMA AND
					C.TABNAME = CH.TABNAME AND
					CH.TYPE = 'C' AND
					CH.CONSTNAME = CC.CONSTNAME
			WHERE
				LOWER(C.TABSCHEMA) = %s AND
				LOWER(C.TABNAME) = %s AND
				LOWER(C.COLNAME) = %s",
			$schema,
			$table,
			$column
		);

		if (!$constraint->countReturnedRows()) {
			return null;
		}

		$row = $this->fetchRowArray($constraint);
		return array(
			'name'       => $this->stringValue($row['constname']),
			'definition' => $this->stringValue($row['text'])
		);
	}


	/**
	 * Returns the foreign key constraints that involve a specific table or table and column
	 *
	 * @param  string $schema  The schema the table is in
	 * @param  string $table   The table the column is in
	 * @param  string $column  The column to get the foreign keys for and the foreign keys that point to
	 * @return array<string, array{schema: string, table: string, table_without_schema?: string, constraint_name: string, column: string, foreign_schema: string, foreign_table: string, foreign_column: string, on_delete: string, on_update: string}>  An associative array of the key being the constraint name and the value being an associative array containing the keys: `schema`, `table`, `column`, `foreign_schema`, `foreign_table`, `foreign_column`, `on_delete` and `on_cascade`
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 */
	private function getDB2ForeignKeyConstraints(string $schema, string $table, ?string $column = null): array
	{
		if ($column) {
			$where_conditions = "((
			 	LOWER(R.TABSCHEMA) = %s AND
				LOWER(R.TABNAME) = %s AND
				LOWER(K.COLNAME) = %s
			) OR (
				LOWER(R.REFTABSCHEMA) = %s AND
				LOWER(R.REFTABNAME) = %s AND
				LOWER(FK.COLNAME) = %s
			))";
			$params = array(
				strtolower($schema),
				strtolower($table),
				strtolower($column),
				strtolower($schema),
				strtolower($table),
				strtolower($column)
			);
		} else {
			$where_conditions = "LOWER(R.REFTABSCHEMA) = %s AND LOWER(R.REFTABNAME) = %s";
			$params = array(
				strtolower($schema),
				strtolower($table)
			);
		}

		array_unshift(
			$params,
			"SELECT
				 R.CONSTNAME AS CONSTRAINT_NAME,
				 TRIM(LOWER(R.TABSCHEMA)) AS \"SCHEMA\",
				 LOWER(R.TABNAME) AS \"TABLE\",
				 LOWER(K.COLNAME) AS \"COLUMN\",
				 TRIM(LOWER(R.REFTABSCHEMA)) AS FOREIGN_SCHEMA,
				 LOWER(R.REFTABNAME) AS FOREIGN_TABLE,
				 LOWER(FK.COLNAME) AS FOREIGN_COLUMN,
				 CASE R.DELETERULE WHEN 'C' THEN 'CASCADE' WHEN 'A' THEN 'NO ACTION' WHEN 'R' THEN 'RESTRICT' ELSE 'SET NULL' END AS ON_DELETE,
				 CASE R.UPDATERULE WHEN 'A' THEN 'NO ACTION' WHEN 'R' THEN 'RESTRICT' END AS ON_UPDATE
			 FROM
				 SYSCAT.REFERENCES AS R INNER JOIN
				 SYSCAT.KEYCOLUSE AS K ON
				 	R.CONSTNAME = K.CONSTNAME AND
				 	R.TABSCHEMA = K.TABSCHEMA AND
				 	R.TABNAME = K.TABNAME INNER JOIN
				 SYSCAT.KEYCOLUSE AS FK ON
				 	R.REFKEYNAME = FK.CONSTNAME AND
				 	R.REFTABSCHEMA = FK.TABSCHEMA AND
				 	R.REFTABNAME = FK.TABNAME
			 WHERE
				 $where_conditions
			 ORDER BY
			 	 LOWER(R.CONSTNAME) ASC"
		);

		$constraints = $this->query($params[0], ...array_slice($params, 1));

		$keys = array();
		foreach ($this->fetchAllRowArrays($constraints) as $constraint) {
			$name = $this->stringValue($constraint['constraint_name']) . $this->stringValue($constraint['table']);
			$keys[$name] = array(
				'constraint_name' => $this->stringValue($constraint['constraint_name']),
				'schema'          => $this->stringValue($constraint['schema']),
				'table'           => $this->stringValue($constraint['table']),
				'column'          => $this->stringValue($constraint['column']),
				'foreign_schema'  => $this->stringValue($constraint['foreign_schema']),
				'foreign_table'   => $this->stringValue($constraint['foreign_table']),
				'foreign_column'  => $this->stringValue($constraint['foreign_column']),
				'on_delete'       => $this->stringValue($constraint['on_delete']),
				'on_update'       => $this->stringValue($constraint['on_update'])
			);
		}

		return $keys;
	}


	/**
	 * Returns the primary key for a table
	 *
	 * @param  string $schema  The schema the table is in
	 * @param  string $table   The table to get the primary key for
	 * @return array<int, string>  The columns in the primary key
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 */
	private function getDB2PrimaryKeyConstraint(string $schema, string $table): array
	{
		$constraints = $this->query(
			"SELECT
				 LOWER(C.COLNAME) AS \"COLUMN\"
			 FROM
				 SYSCAT.INDEXES AS I INNER JOIN
				 SYSCAT.INDEXCOLUSE AS C ON
				 	I.INDSCHEMA = C.INDSCHEMA AND
				 	I.INDNAME = C.INDNAME
			 WHERE
				 I.UNIQUERULE IN ('P') AND
				 LOWER(I.TABSCHEMA) = %s AND
				 LOWER(I.TABNAME) = %s
			 ORDER BY
			 	 LOWER(I.INDNAME) ASC
			",
			strtolower($schema),
			strtolower($table)
		);

		$key = array();
		foreach ($this->fetchAllRowArrays($constraints) as $constraint) {
			$key[] = $this->stringValue($constraint['column']);
		}

		return $key;
	}


	/**
	 * Returns the unique keys for a table and column
	 *
	 * @param  string $schema  The schema the table is in
	 * @param  string $table   The table to get the unique keys for
	 * @param  string $column  The column to filter the unique keys by
	 * @return array<string, array<int, string>>  An associative array of the key being the constraint name and the value being the columns in the unique key
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 */
	private function getDB2UniqueConstraints(string $schema, string $table, string $column): array
	{
		$constraints = $this->query(
			"SELECT
				 CD.CONSTNAME AS CONSTRAINT_NAME,
				 LOWER(C.COLNAME) AS \"COLUMN\"
			 FROM
				 SYSCAT.INDEXES AS I INNER JOIN
				 SYSCAT.CONSTDEP AS CD ON
					I.TABSCHEMA = CD.TABSCHEMA AND
					I.TABNAME = CD.TABNAME AND
					CD.BTYPE = 'I' AND
					CD.BNAME = I.INDNAME INNER JOIN
				 SYSCAT.INDEXCOLUSE AS C ON
				 	I.INDSCHEMA = C.INDSCHEMA AND
				 	I.INDNAME = C.INDNAME
			 WHERE
				 I.UNIQUERULE IN ('U') AND
				 LOWER(I.TABSCHEMA) = %s AND
				 LOWER(I.TABNAME) = %s
			 ORDER BY
			 	 LOWER(I.INDNAME) ASC
			",
			strtolower($schema),
			strtolower($table)
		);

		$keys = array();
		foreach ($this->fetchAllRowArrays($constraints) as $constraint) {
			$constraint_name = $this->stringValue($constraint['constraint_name']);
			if (!isset($keys[$constraint_name])) {
				$keys[$constraint_name] = array();
			}
			$keys[$constraint_name][] = $this->stringValue($constraint['column']);
		}

		$new_keys = array();
		$column = strtolower($column);
		foreach ($keys as $name => $columns) {
			if (!in_array($column, $columns)) {
				continue;
			}
			$new_keys[$name] = $columns;
		}
		$keys = $new_keys;

		return $keys;
	}


	/**
	 * Returns the check constraint for a column, if it exists
	 *
	 * @param  string $schema  The schema the column is inside of
	 * @param  string $table   The table the column is part of
	 * @param  string $column  The column name
	 * @return array{name: string, definition: string}|NULL  An associative array with the keys `name` and `definition`, or `NULL`
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	private function getMSSQLCheckConstraint(string $schema, string $table, string $column): ?array
	{
		$constraint = $this->query(
			"SELECT
				cc.check_clause AS 'constraint',
				ccu.constraint_name
			FROM
				INFORMATION_SCHEMA.COLUMNS AS c INNER JOIN
				INFORMATION_SCHEMA.CONSTRAINT_COLUMN_USAGE AS ccu ON
					c.column_name = ccu.column_name AND
					c.table_name = ccu.table_name AND
					c.table_catalog = ccu.table_catalog INNER JOIN
				INFORMATION_SCHEMA.CHECK_CONSTRAINTS AS cc ON
					ccu.constraint_name = cc.constraint_name AND
					ccu.constraint_catalog = cc.constraint_catalog
			WHERE
				LOWER(c.table_schema) = %s AND
				LOWER(c.table_name) = %s AND
				LOWER(c.column_name) = %s AND
				c.table_catalog = DB_NAME()",
			strtolower($schema),
			strtolower($table),
			strtolower($column)
		);

		if (!$constraint->countReturnedRows()) {
			return null;
		}

		$row = $this->fetchRowArray($constraint);
		return array(
			'name'       => $this->stringValue($row['constraint_name']),
			'definition' => $this->stringValue($row['constraint'])
		);
	}


	/**
	 * Returns the foreign key constraints that a column is part of
	 *
	 * @param  string       $schema  The schema the column is inside of
	 * @param  string       $table   The table the column is part of
	 * @param  string|array<int, string> $column  The column name(s)
	 * @return array<int, array{table: string, schema: string, table_without_schema: string, column: string, name: string}>  An array of constraint names that reference the column(s)
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 */
	private function getMSSQLForeignKeyConstraints(string $schema, string $table, mixed $column): array
	{
		settype($column, 'array');

		$constraints = $this->query(
			"SELECT
				LOWER(tc.table_schema + '.' + tc.table_name) AS 'table',
				LOWER(tc.table_schema) AS 'schema',
				LOWER(tc.table_name) AS 'table_without_schema',
				LOWER(kcu.column_name) AS 'column',
				kcu.constraint_name AS name
			FROM
				information_schema.table_constraints AS tc INNER JOIN
				information_schema.key_column_usage AS kcu ON
					tc.constraint_name = kcu.constraint_name AND
					tc.constraint_catalog = kcu.constraint_catalog AND
					tc.constraint_schema = kcu.constraint_schema AND
					tc.table_name = kcu.table_name INNER JOIN
				information_schema.referential_constraints AS rc ON
					kcu.constraint_name = rc.constraint_name  AND
					kcu.constraint_catalog = rc.constraint_catalog AND
					kcu.constraint_schema = rc.constraint_schema INNER JOIN
				information_schema.constraint_column_usage AS ccu ON
					ccu.constraint_name = rc.unique_constraint_name  AND
					ccu.constraint_catalog = rc.constraint_catalog AND
					ccu.constraint_schema = rc.constraint_schema
			WHERE
				tc.constraint_type = 'FOREIGN KEY' AND
				(
					LOWER(tc.table_schema) = %s AND
					LOWER(ccu.table_name) = %s AND
					LOWER(ccu.column_name) IN (%s)
				) OR (
					LOWER(tc.table_schema) = %s AND
					LOWER(kcu.table_name) = %s AND
					LOWER(kcu.column_name) IN (%s)
				) AND
				tc.constraint_catalog = DB_NAME()",
			strtolower($schema),
			strtolower($table),
			array_map('strtolower', $column),
			strtolower($schema),
			strtolower($table),
			array_map('strtolower', $column)
		);

		$output = array();
		foreach ($this->fetchAllRowArrays($constraints) as $row) {
			$output[] = array(
				'table'               => $this->stringValue($row['table']),
				'schema'              => $this->stringValue($row['schema']),
				'table_without_schema' => $this->stringValue($row['table_without_schema']),
				'column'              => $this->stringValue($row['column']),
				'name'                => $this->stringValue($row['name'])
			);
		}

		return $output;
	}


	/**
	 * Returns the default constraint for a column, if it exists
	 *
	 * @param  string $schema  The schema the column is inside of
	 * @param  string $table   The table the column is part of
	 * @param  string $column  The column name
	 * @return array{name: string, definition: string}|NULL  An associative array with the keys `name` and `definition`, or `NULL`
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	private function getMSSQLDefaultConstraint(string $schema, string $table, string $column): ?array
	{
		$constraint = $this->query(
			"SELECT
				dc.name,
				CAST(dc.definition AS VARCHAR(MAX)) AS definition
			FROM
				information_schema.columns AS c INNER JOIN
				sys.default_constraints AS dc ON
					OBJECT_ID(QUOTENAME(c.table_schema) + '.' + QUOTENAME(c.table_name)) = dc.parent_object_id AND
					COLUMNPROPERTY(OBJECT_ID(QUOTENAME(c.table_schema) + '.' + QUOTENAME(c.table_name)), c.column_name, 'ColumnId') = dc.parent_column_id
			WHERE
				LOWER(c.table_schema) = %s AND
				LOWER(c.table_name) = %s AND
				LOWER(c.column_name) = %s AND
				c.table_catalog = DB_NAME()",
			strtolower($schema),
			strtolower($table),
			strtolower($column)
		);

		if (!$constraint->countReturnedRows()) {
			return null;
		}

		$row = $this->fetchRowArray($constraint);
		return array(
			'name'       => $this->stringValue($row['name']),
			'definition' => $this->stringValue($row['definition'])
		);
	}


	/**
	 * Returns the primary key constraints for a table
	 *
	 * @param  string $schema  The schema the table is inside of
	 * @param  string $table   The table to get the constraint for
	 * @return array{name: string, columns: array<int, string>, autoincrement: bool}|NULL  An associative array with the keys `name`, `columns` and `autoincrement` or `NULL`
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 */
	private function getMSSQLPrimaryKeyConstraint(string $schema, string $table): mixed
	{
		$column_info = $this->query(
			"SELECT
				kcu.constraint_name AS constraint_name,
				LOWER(kcu.column_name) AS column_name,
				CASE
					WHEN
					  COLUMNPROPERTY(OBJECT_ID(QUOTENAME(c.table_schema) + '.' + QUOTENAME(c.table_name)), c.column_name, 'IsIdentity') = 1 AND
					  OBJECTPROPERTY(OBJECT_ID(QUOTENAME(c.table_schema) + '.' + QUOTENAME(c.table_name)), 'IsMSShipped') = 0
					THEN '1'
					ELSE '0'
				  END AS auto_increment
			FROM
				information_schema.table_constraints AS con INNER JOIN
				information_schema.key_column_usage AS kcu ON
					con.table_name = kcu.table_name AND
					con.table_schema = kcu.table_schema AND
					con.constraint_name = kcu.constraint_name INNER JOIN
				information_schema.columns AS c ON
					c.table_name = kcu.table_name AND
					c.table_schema = kcu.table_schema AND
					c.column_name = kcu.column_name
			WHERE
				con.constraint_type = 'PRIMARY KEY' AND
				LOWER(con.table_schema) = %s AND
				LOWER(con.table_name) = %s AND
				con.table_catalog = DB_NAME()",
			strtolower($schema),
			strtolower($table)
		);

		if (!$column_info->countReturnedRows()) {
			return null;
		}

		$output = array(
			'name'          => '',
			'columns'       => array(),
			'autoincrement' => false
		);
		foreach ($this->fetchAllRowArrays($column_info) as $row) {
			$output['columns'][]     = $this->stringValue($row['column_name']);
			$output['name']          = $this->stringValue($row['constraint_name']);
			$output['autoincrement'] = (bool) $row['auto_increment'];
		}

		return $output;
	}


	/**
	 * Returns the unique constraints that a column is part of
	 *
	 * @param  string $schema  The schema the column is inside of
	 * @param  string $table   The table the column is part of
	 * @param  string $column  The column name
	 * @return array<string, array<int, string>>  An associative array of constraint_name => columns
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 */
	private function getMSSQLUniqueConstraints(string $schema, string $table, string $column): array
	{
		$constraint_columns = $this->query(
			"SELECT
				c.constraint_name,
				LOWER(kcu.column_name) AS column_name
			FROM
				information_schema.table_constraints AS c INNER JOIN
				information_schema.key_column_usage AS kcu ON
					c.table_name = kcu.table_name AND
					c.constraint_name = kcu.constraint_name
			WHERE
				c.constraint_name IN (
					SELECT
						c.constraint_name
					FROM
						information_schema.table_constraints AS c INNER JOIN
						information_schema.key_column_usage AS kcu ON
							c.table_name = kcu.table_name AND
							c.constraint_name = kcu.constraint_name
					WHERE
						c.constraint_type = 'UNIQUE' AND
						LOWER(c.table_schema) = %s AND
						LOWER(c.table_name) = %s AND
						LOWER(kcu.column_name) = %s AND
						c.table_catalog = DB_NAME()

				) AND
				LOWER(c.table_schema) = %s AND
				c.table_catalog = DB_NAME()
			ORDER BY
				c.constraint_name
			",
			strtolower($schema),
			strtolower($table),
			strtolower($column),
			strtolower($schema)
		);

		$unique_constraints = array();
		foreach ($this->fetchAllRowArrays($constraint_columns) as $row) {
			$constraint_name = $this->stringValue($row['constraint_name']);
			if (!isset($unique_constraints[$constraint_name])) {
				$unique_constraints[$constraint_name] = array();
			}
			$unique_constraints[$constraint_name][] = $this->stringValue($row['column_name']);
		}

		return $unique_constraints;
	}


	/**
	 * Returns info about all foreign keys that involve the table and one of the columns specified
	 *
	 * @param  string       $table    The table
	 * @param  string|array<int, string> $columns  The column, or an array of valid column names
	 * @return array<int, array{constraint_name: string, table: string, column: string, foreign_table: string, foreign_column: string, on_delete: string, on_update: string}>  An array of associative arrays containing the keys `constraint_name`, `table`, `column`, `foreign_table` and `foreign_column`
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	private function getMySQLForeignKeys(string $table, mixed $columns): mixed
	{
		if (is_string($columns)) {
			$columns = array($columns);
		}
		$columns = array_map('strtolower', $columns);

		$tables = $this->getMySQLTables();

		$keys = array();
		foreach ($tables as $_table) {
			$row = $this->fetchRowArray($this->query("SHOW CREATE TABLE %r", $_table));

			preg_match_all(
				'#CONSTRAINT\s+"(\w+)"\s+FOREIGN KEY \("([^"]+)"\) REFERENCES "([^"]+)" \("([^"]+)"\)(?:\sON\sDELETE\s(SET\sNULL|SET\sDEFAULT|CASCADE|NO\sACTION|RESTRICT))?(?:\sON\sUPDATE\s(SET\sNULL|SET\sDEFAULT|CASCADE|NO\sACTION|RESTRICT))?#',
				$this->stringValue($row['Create Table']),
				$matches,
				PREG_SET_ORDER
			);

			foreach ($matches as $match) {
				$points_to_column = strtolower($match[3]) == strtolower($table) && in_array(strtolower($match[4]), $columns);
				$is_column        = strtolower($_table) == strtolower($table) && in_array(strtolower($match[2]), $columns);

				if (!$points_to_column && !$is_column) {
					continue;
				}

				$temp = array(
					'constraint_name' => $match[1],
					'table'           => $_table,
					'column'          => $match[2],
					'foreign_table'   => $match[3],
					'foreign_column'  => $match[4],
					'on_delete'       => 'NO ACTION',
					'on_update'       => 'NO ACTION'
				);
				if (!empty($match[5])) {
					$temp['on_delete'] = $match[5];
				}
				if (!empty($match[6])) {
					$temp['on_update'] = $match[6];
				}

				$keys[] = $temp;
			}
		}

		return $keys;
	}


	/**
	 * Returns a list of all tables in the database
	 *
	 * @return array<int, string>  An array of table names
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	private function getMySQLTables(): array
	{
		if (!isset($this->schema_info['version'])) {
			$version = $this->stringValue($this->query("SELECT version()")->fetchScalar());
			$position = strpos($version, '.');
			$this->schema_info['version'] = substr($version, 0, $position === false ? 0 : $position);
		}
		if ($this->schema_info['version'] <= 4) {
			$sql = 'SHOW TABLES';
		} else {
			$sql = "SHOW FULL TABLES WHERE table_type = 'BASE TABLE'";
		}

		$result = $this->query($sql);
		$tables = array();

		foreach ($this->fetchAllRowArrays($result) as $row) {
			$keys = array_keys($row);
			$tables[] = $this->stringValue($row[$keys[0]]);
		}

		return $tables;
	}



	/**
	 * Returns an an array of the column name for a table
	 *
	 * @param  string $table  The table to retrieve the column names for
	 * @return array<int, string>  The column names for the table
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 */
	private function getSQLiteColumns(string $table): array
	{
		$create_sql = $this->getSQLiteCreateTable($table);
		if ($create_sql === null) {
			return array();
		}

		return array_keys(self::parseSQLiteColumnDefinitions($create_sql));
	}


	/**
	 * Returns the SQL used to create a table
	 *
	 * @param  string $table  The table to retrieve the `CREATE TABLE` statement for
	 * @return string|NULL  The `CREATE TABLE` SQL statement, or `NULL` if it could not be found
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 */
	private function getSQLiteCreateTable(string $table): ?string
	{
		if (!isset($this->schema_info['sqlite_create_tables'])) {
			$this->getSQLiteTables();
		}

		if (!isset($this->schema_info['sqlite_create_tables'][$table])) {
			return null;
		}

		return $this->schema_info['sqlite_create_tables'][$table];
	}


	/**
	 * Returns a list of all foreign keys that reference the table, and optionally, column specified
	 *
	 * @param  string  $table   All foreign keys returned will point to this table
	 * @param  string  $column  Only foreign keys pointing to this column will be returned
	 * @return array<int, array{table: string, column: string, foreign_table: string, foreign_column: string, on_delete: string, on_update: string}>  An array of arrays containing they keys: `table`, `column`, `foreign_table`, `foreign_column`, `on_delete` and `on_update`
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 */
	private function getSQLiteForeignKeys(string $table, ?string $column = null): array
	{
		$output = array();
		foreach ($this->getSQLiteTables() as $_table) {
			$create_sql = $this->getSQLiteCreateTable($_table);
			if ($create_sql === null) {
				continue;
			}

			if (stripos($create_sql, 'references') === false) {
				continue;
			}

			preg_match_all('#(?<=,|\(|\*/|\n)\s*[`"\[\']?(\w+)[`"\]\']?\s+(?:[a-z]+)(?:\([^)]*\))?(?:(?:\s+NOT\s+NULL)|(?:\s+NULL)|(?:\s+DEFAULT\s+(?:[^, \']*|\'(?:\'\'|[^\']+)*\'))|(?:\s+UNIQUE)|(?:\s+PRIMARY\s+KEY(?:\s+AUTOINCREMENT)?)|(?:\s+CHECK\s*\("?\w+"?\s+IN\s+\(\s*(?:(?:[^, \']+|\'(?:\'\'|[^\']+)*\')\s*,\s*)*\s*(?:[^, \']+|\'(?:\'\'|[^\']+)*\')\)\)))*\s+REFERENCES\s+[\'"`\[]?(\w+)[\'"`\]]?\s*\(\s*[\'"`\[]?(\w+)[\'"`\]]?\s*\)\s*(?:(?:\s+ON\s+DELETE\s+(CASCADE|NO\s+ACTION|RESTRICT|SET\s+NULL|SET\s+DEFAULT))|(?:\s+ON\s+UPDATE\s+(CASCADE|NO\s+ACTION|RESTRICT|SET\s+NULL|SET\s+DEFAULT)))*(?:\s+(?:DEFERRABLE|NOT\s+DEFERRABLE))?\s*(?:,|/\*|(?:--[^\n]*\n)?\s*(?=\)))#mis', $create_sql, $matches, PREG_SET_ORDER);

			preg_match_all('#(?<=,|\(|\*/|\n)\s*(?:CONSTRAINT\s+["`\[]?\w+["`\]]?\s+)?FOREIGN\s+KEY\s*\(?\s*["`\[]?(\w+)["`\]]?\s*\)?\s+REFERENCES\s+["`\[]?(\w+)["`\]]?\s*\(\s*["`\[]?(\w+)["`\]]?\s*\)\s*(?:(?:\s+ON\s+DELETE\s+(CASCADE|NO\s+ACTION|RESTRICT|SET\s+NULL|SET\s+DEFAULT))|(?:\s+ON\s+UPDATE\s+(CASCADE|NO\s+ACTION|RESTRICT|SET\s+NULL|SET\s+DEFAULT)))*(?:\s+(?:DEFERRABLE|NOT\s+DEFERRABLE))?\s*(?:,|/\*|(?:--[^\n]*\n)?\s*(?=\)))#mis', $create_sql, $matches2, PREG_SET_ORDER);

			foreach (array_merge($matches, $matches2) as $match) {
				$_column        = $match[1];
				$foreign_table  = $match[2];
				$foreign_column = $match[3];
				$on_delete      = empty($match[4]) ? 'RESTRICT' : $match[4];
				$on_update      = empty($match[5]) ? 'RESTRICT' : $match[5];

				if ($foreign_table != $table || ($column !== null && $column != $foreign_column)) {
					continue;
				}

				$output[] = array(
					'table'          => $_table,
					'column'         => $_column,
					'foreign_table'  => $foreign_table,
					'foreign_column' => $foreign_column,
					'on_delete'      => $on_delete,
					'on_update'      => $on_update
				);
			}
		}

		return $output;
	}


	/**
	 * Returns the indexes in the current SQLite database
	 *
	 * @return array<string, array{table: string, sql: string}>  An associative array with the key being the index name and the value an associative arrays, each containing the keys: `table`, `sql`
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 */
	private function getSQLiteIndexes(mixed $table = null): array
	{
		if (!isset($this->schema_info['sqlite_indexes'])) {
			$this->schema_info['sqlite_indexes'] = array();
			$rows = $this->fetchAllRowArrays($this->query(
				"SELECT tbl_name AS \"table\", name, sql FROM sqlite_master WHERE type = 'index' AND sql <> ''"
			));
			foreach ($rows as $row) {
				$this->schema_info['sqlite_indexes'][$this->stringValue($row['name'])] = array(
					'table' => $this->stringValue($row['table']),
					'sql'   => $this->stringValue($row['sql'])
				);
			}
		}

		$output = $this->schema_info['sqlite_indexes'];

		if ($table) {
			$new_output = array();
			foreach ($output as $name => $index) {
				if ($index['table'] != $table) {
					continue;
				}

				$new_output[$name] = $index;
			}
			$output = $new_output;
		}

		return $output;
	}


	/**
	 * Returns the tables in the current SQLite database
	 *
	 * @return array<int, string>
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 */
	private function getSQLiteTables(): array
	{
		if (!isset($this->schema_info['sqlite_create_tables'])) {
			$this->schema_info['sqlite_create_tables'] = array();
			$res = $this->fetchAllRowArrays($this->query(
				"SELECT name, sql FROM sqlite_master WHERE type = 'table'"
			));
			foreach ($res as $row) {
				$this->schema_info['sqlite_create_tables'][$this->stringValue($row['name'])] = $this->stringValue($row['sql']);
			}
		}

		$tables = array_keys($this->schema_info['sqlite_create_tables']);

		natcasesort($tables);

		return $tables;
	}


	/**
	 * Returns the triggers in the current SQLite database
	 *
	 * @return array<string, array{table: string, sql: string}>  An associative array with the key being the trigger name and the value an associative arrays, each containing the keys: `table`, `sql`
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 */
	private function getSQLiteTriggers(mixed $exclude_table = null): array
	{
		if (!isset($this->schema_info['sqlite_triggers'])) {
			$this->schema_info['sqlite_triggers'] = array();
			$rows = $this->fetchAllRowArrays($this->query(
				"SELECT tbl_name AS \"table\", name, sql FROM sqlite_master WHERE type = 'trigger'"
			));
			foreach ($rows as $row) {
				$this->schema_info['sqlite_triggers'][$this->stringValue($row['name'])] = array(
					'table' => $this->stringValue($row['table']),
					'sql'   => $this->stringValue($row['sql'])
				);
			}
		}

		$output = $this->schema_info['sqlite_triggers'];

		if ($exclude_table) {
			$new_output = array();
			foreach ($output as $name => $trigger) {
				if ($trigger['table'] == $exclude_table) {
					continue;
				}

				$new_output[$name] = $trigger;
			}
			$output = $new_output;
		}

		return $output;
	}


	/**
	 * Removes the SQLite indexes from the internal schema tracker
	 *
	 * @param  string $table   The table to remove the indexes for
	 * @return void
	 */
	private function removeSQLiteIndexes(string $table): void
	{
		if (!isset($this->schema_info['sqlite_indexes'])) {
			return;
		}

		$indexes = $this->schema_info['sqlite_indexes'];

		$new_indexes = array();
		foreach ($indexes as $name => $index) {
			if ($index['table'] == $table) {
				continue;
			}
			$new_indexes[$name] = $index;
		}

		$this->schema_info['sqlite_indexes'] = $new_indexes;
	}


	/**
	 * Removes a table from the list of SQLite table
	 *
	 * @param  string $table  The table to remove
	 * @return void
	 */
	private function removeSQLiteTable(string $table): void
	{
		if (!isset($this->schema_info['sqlite_create_tables'])) {
			return;
		}

		unset($this->schema_info['sqlite_create_tables'][$table]);
	}


	/**
	 * Removes a SQLite trigger from the internal schema tracker
	 *
	 * @param  string $name   The trigger name
	 * @return void
	 */
	private function removeSQLiteTrigger(string $name): void
	{
		if (!isset($this->schema_info['sqlite_triggers'])) {
			return;
		}

		unset($this->schema_info['sqlite_triggers'][$name]);
	}


	/**
	 * Removes the SQLite triggers for a table from the internal schema tracker
	 *
	 * @param  string $table   The table to remove the triggers for
	 * @return void
	 */
	private function removeSQLiteTriggers(string $table): void
	{
		if (!isset($this->schema_info['sqlite_triggers'])) {
			return;
		}

		$triggers = $this->schema_info['sqlite_triggers'];

		$new_triggers = array();
		foreach ($triggers as $name => $trigger) {
			if ($trigger['table'] == $table) {
				continue;
			}
			$new_triggers[$name] = $trigger;
		}

		$this->schema_info['sqlite_triggers'] = $new_triggers;
	}


	/**
	 * Throws an SQLException with the information provided
	 *
	 * @param  string $error  The error that occured
	 * @param  string $sql    The SQL statement that caused the error
	 * @return never
	 */
	private function throwException(string $error, string $sql): never
	{
		$db_type_map = array(
			'db2'        => 'DB2',
			'mssql'      => 'MSSQL',
			'mysql'      => 'MySQL',
			'oracle'     => 'Oracle',
			'postgresql' => 'PostgreSQL',
			'sqlite'     => 'SQLite'
		);

		throw new SQLException(
			'%1$s error (%2$s) in %3$s',
			$db_type_map[$this->database->getType()],
			$error,
			$sql
		);
	}


	/**
	 * Runs a SQL query on the database, ensuring the result is a `Result` object
	 *
	 * `Database::query()` returns an array of result objects when the query string
	 * contains multiple statements, however every query in this class is a single
	 * statement, so this helper narrows the return value to a single `Result`.
	 *
	 * @param  string $sql       The SQL query to run
	 * @param  mixed  ...$values The values to substitute into the query
	 * @return Result
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws SQLException
	 */
	private function query(string $sql, mixed ...$values): Result
	{
		$result = $this->database->query($sql, ...$values);
		if (!$result instanceof Result) {
			$this->throwException('A query unexpectedly returned multiple results instead of a single Result object', $sql);
		}
		return $result;
	}


	/**
	 * Fetches a single row from a `Result` object as an associative array
	 *
	 * @return array<int|string, mixed>
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 */
	private function fetchRowArray(Result $result): array
	{
		$row = $result->fetchRow();
		if (!is_array($row)) {
			$this->throwException('A query unexpectedly returned an object row instead of an array row', '');
		}
		return $row;
	}


	/**
	 * Fetches all rows from a `Result` object as associative arrays
	 *
	 * @return array<int, array<int|string, mixed>>
	 */
	private function fetchAllRowArrays(Result $result): array
	{
		$rows = $result->fetchAllRows();
		$output = array();
		foreach ($rows as $row) {
			if (!is_array($row)) {
				$this->throwException('A query unexpectedly returned an object row instead of an array row', '');
			}
			$output[] = $row;
		}
		return $output;
	}


	/**
	 * Converts a value fetched from the database into a string
	 *
	 * @return string
	 */
	private function stringValue(mixed $value): string
	{
		if (is_string($value)) {
			return $value;
		}
		if (is_scalar($value) || $value === null) {
			return (string) $value;
		}
		$this->throwException('The value fetched from the database could not be converted to a string', '');
	}


	/**
	 * Escapes SQL for the current database, narrowing the result to a string
	 *
	 * The database `escape()` method returns `mixed` because it can escape an
	 * array of values at once, however the SQL statements translated here always
	 * pass scalar values and thus always receive a string back.
	 *
	 * @param  string $sql       The SQL or type to escape
	 * @param  mixed  ...$values The values to escape into the SQL
	 * @return string  The escaped SQL
	 */
	private function escape(string $sql, mixed ...$values): string
	{
		return $this->stringValue($this->database->escape($sql, ...$values));
	}


	/**
	 * Translates a Flourish SQL DDL statement into the dialect for the current database
	 *
	 * @internal
	 *
	 * @param  string $sql                  The SQL statement to translate
	 * @param  array<int, string>|NULL &$rollback_statements  SQL statements to rollback the returned SQL statements if something goes wrong - only applicable for MySQL `ALTER TABLE` statements
	 * @return array{0: string, 1: array<int, string>}  An array containing the translated `$sql` statement and an array of extra statements
	 */
	public function translate(string $sql, mixed &$rollback_statements = null): array
	{
		$reset_sqlite_info = false;
		if (!isset($this->schema_info['sqlite_schema_info'])) {
			$this->schema_info['sqlite_schema_info'] = true;
			$reset_sqlite_info = true;
		}

		$new_sql   = $sql;
		$exception = null;

		try {
			$extra_statements = array();
			if (!is_array($rollback_statements)) {
				$rollback_statements = array();
			}
			$new_sql = $this->translateCreateTableStatements($new_sql, $extra_statements);
			$new_sql = $this->translateAlterTableStatements($new_sql, $extra_statements, $rollback_statements);

			if ($this->database->getType() == 'sqlite') {
				$new_sql = $this->translateSQLiteDropTableStatements($new_sql, $extra_statements);
			}
		} catch (Exception $e) {
			$exception = $e;
		}

		if ($reset_sqlite_info) {
			unset($this->schema_info['sqlite_schema_info']);
			unset($this->schema_info['sqlite_create_tables']);
			unset($this->schema_info['sqlite_indexes']);
			unset($this->schema_info['sqlite_triggers']);
		}

		if ($exception) {
			throw $exception;
		}

		return array($new_sql, $extra_statements);
	}


	/**
	 * Translates the structure of `CREATE TABLE` statements to the database specific syntax
	 *
	 * @param  string $sql                   The SQL to translate
	 * @param  array<int, string>  &$extra_statements     Any extra SQL statements that need to be added
	 * @param  array<int, string>  &$rollback_statements  SQL statements to rollback `$sql` and `$extra_statements` if something goes wrong
	 * @return string  The translated SQL
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	private function translateAlterTableStatements(string $sql, array &$extra_statements, array &$rollback_statements): string
	{
		if (!preg_match('#^\s*ALTER\s+TABLE\s+(\w+|"[^"]+")\s+(.*)$#siD', $sql, $table_matches) && !preg_match('#^\s*COMMENT\s+ON\s+COLUMN\s+"?((?:\w+"?\."?)?\w+)"?\.("?\w+"?\s+IS\s+(?:\'.*\'|%\d+\$s))\s*$#Dis', $sql, $table_matches)) {
			return $sql;
		}

		$statement = $table_matches[2];

		$data = array(
			'table' => $table_matches[1]
		);

		if (preg_match('#"?(\w+)"?\s+IS\s+(\'.*\'|:string\w+|%\d+\$s)\s*$#Dis', $statement, $statement_matches)) {
			$data['type']        = 'column_comment';
			$data['column_name'] = trim($statement_matches[1], '"');
			$data['comment']     = $statement_matches[2];
		} elseif (preg_match('#RENAME\s+TO\s+(\w+|"[^"]+")\s*$#isD', $statement, $statement_matches)) {
			$data['type']           = 'rename_table';
			$data['new_table_name'] = trim($statement_matches[1], '"');
		} elseif (preg_match('#RENAME\s+COLUMN\s+(\w+|"[^"]+")\s+TO\s+(\w+|"[^"]+")\s*$#isD', $statement, $statement_matches)) {
			$data['type']            = 'rename_column';
			$data['column_name']     = trim($statement_matches[1], '"');
			$data['new_column_name'] = trim($statement_matches[2], '"');
		} elseif (preg_match('#ADD\s+COLUMN\s+("?(\w+)"?.*)$#isD', $statement, $statement_matches)) {
			$data['type']              = 'add_column';
			$data['column_definition'] = $statement_matches[1];
			$data['column_name']       = $statement_matches[2];
		} elseif (preg_match('#DROP\s+COLUMN\s+(\w+|"[^"]+")\s*$#isD', $statement, $statement_matches)) {
			$data['type']        = 'drop_column';
			$data['column_name'] = trim($statement_matches[1], '"');
		} elseif (preg_match('#ALTER\s+COLUMN\s+(\w+|"[^"]+")\s+TYPE\s+(.*?)\s*$#isD', $statement, $statement_matches)) {
			$data['type']        = 'alter_type';
			$data['column_name'] = trim($statement_matches[1], '"');
			$data['data_type']   = $statement_matches[2];
		} elseif (preg_match('#ALTER\s+COLUMN\s+(\w+|"[^"]+")\s+DROP\s+DEFAULT\s*$#isD', $statement, $statement_matches)) {
			$data['type']        = 'drop_default';
			$data['column_name'] = trim($statement_matches[1], '"');
		} elseif (preg_match('#ALTER\s+COLUMN\s+(\w+|"[^"]+")\s+SET\s+DEFAULT\s+(.*?)\s*$#isD', $statement, $statement_matches)) {
			$data['type']          = 'set_default';
			$data['column_name']   = trim($statement_matches[1], '"');
			$data['default_value'] = trim($statement_matches[2], '"');
		} elseif (preg_match('#ALTER\s+COLUMN\s+(\w+|"[^"]+")\s+DROP\s+NOT\s+NULL\s*$#isD', $statement, $statement_matches)) {
			$data['type']        = 'drop_not_null';
			$data['column_name'] = trim($statement_matches[1], '"');
		} elseif (preg_match('#ALTER\s+COLUMN\s+(\w+|"[^"]+")\s+SET\s+NOT\s+NULL(\s+DEFAULT\s+(.*))?\s*$#isD', $statement, $statement_matches)) {
			$data['type']        = 'set_not_null';
			$data['column_name'] = trim($statement_matches[1], '"');
			if (isset($statement_matches[2], $statement_matches[3])) {
				$data['default'] = $statement_matches[3];
			}
		} elseif (preg_match('#ALTER\s+COLUMN\s+(\w+|"[^"]+")\s+DROP\s+CHECK\s*$#isD', $statement, $statement_matches)) {
			$data['type']        = 'drop_check_constraint';
			$data['column_name'] = trim($statement_matches[1], '"');
		} elseif (preg_match('#ALTER\s+COLUMN\s+(\w+|"[^"]+")\s+SET\s+CHECK\s+IN\s+(\(.*?\))\s*$#isD', $statement, $statement_matches)) {
			$data['type']        = 'set_check_constraint';
			$data['column_name'] = trim($statement_matches[1], '"');
			$data['constraint']  = ' CHECK(' . $statement_matches[1] . ' IN ' . $statement_matches[2] . ')';
		} elseif (preg_match('#DROP\s+PRIMARY\s+KEY\s*$#isD', $statement, $statement_matches)) {
			$data['type'] = 'drop_primary_key';
		} elseif (preg_match('#ADD\s+PRIMARY\s+KEY\s*\(\s*([^\)]+?)\s*\)(\s+AUTOINCREMENT)?\s*$#isD', $statement, $statement_matches)) {
			$data['type']         = 'add_primary_key';
			$data['column_names'] = preg_split(
				'#"?\s*,\s*"?#',
				trim($statement_matches[1], '"'),
				-1,
				PREG_SPLIT_NO_EMPTY
			) ?: array();
			$data['autoincrement'] = count($data['column_names']) == 1 && !empty($statement_matches[2]);
			if (count($data['column_names']) == 1) {
				$data['column_name'] = implode('', $data['column_names']);
			}
		} elseif (preg_match('#DROP\s+FOREIGN\s+KEY\s*\(\s*(\w+|"[^"]+")\s*\)\s*$#isD', $statement, $statement_matches)) {
			$data['type']        = 'drop_foreign_key';
			$data['column_name'] = trim($statement_matches[1], '"');
		} elseif (preg_match('#ADD\s+FOREIGN\s+KEY\s*\((\w+|"[^"]+")\)\s+REFERENCES\s+("?(\w+)"?\s*\(\s*"?(\w+)"?\s*\)\s*.*)\s*$#isD', $statement, $statement_matches)) {
			$data['type']           = 'add_foreign_key';
			$data['column_name']    = trim($statement_matches[1], '"');
			$data['references']     = $statement_matches[2];
			$data['foreign_table']  = self::unescapeIdentifier($statement_matches[3]);
			$data['foreign_column'] = self::unescapeIdentifier($statement_matches[4]);
		} elseif (preg_match('#DROP\s+UNIQUE\s*\(\s*([^\)]+?)\s*\)\s*$#isD', $statement, $statement_matches)) {
			$data['type']         = 'drop_unique';
			$data['column_names'] = preg_split(
				'#"?\s*,\s*"?#',
				trim($statement_matches[1], '"'),
				-1,
				PREG_SPLIT_NO_EMPTY
			) ?: array();
			if (count($data['column_names']) == 1) {
				$data['column_name'] = implode('', $data['column_names']);
			}
		} elseif (preg_match('#ADD\s+UNIQUE\s*\(\s*([^\)]+?)\s*\)\s*$#isD', $statement, $statement_matches)) {
			$data['type']         = 'add_unique';
			$data['column_names'] = preg_split(
				'#"?\s*,\s*"?#',
				trim($statement_matches[1], '"'),
				-1,
				PREG_SPLIT_NO_EMPTY
			) ?: array();
			if (count($data['column_names']) == 1) {
				$data['column_name'] = implode('', $data['column_names']);
			}
		} else {
			return $sql;
		}

		$data['table'] = self::unescapeIdentifier($data['table']);
		if (isset($data['new_table_name'])) {
			$data['new_table_name'] = self::unescapeIdentifier($data['new_table_name']);
		}
		if (isset($data['column_name'])) {
			$data['column_name'] = self::unescapeIdentifier($data['column_name']);
		}
		if (isset($data['column_names'])) {
			$data['column_names'] = array_map(
				array('SQLSchemaTranslation', 'unescapeIdentifier'),
				$data['column_names']
			);
		}
		if (isset($data['new_column_name'])) {
			$data['new_column_name'] = self::unescapeIdentifier($data['new_column_name']);
		}

		if ($this->database->getType() == 'db2') {
			$sql = $this->translateDB2AlterTableStatements($sql, $extra_statements, $data);
		}
		if ($this->database->getType() == 'mssql') {
			$sql = $this->translateMSSQLAlterTableStatements($sql, $extra_statements, $data);
		}
		if ($this->database->getType() == 'mysql') {
			$sql = $this->translateMySQLAlterTableStatements($sql, $extra_statements, $rollback_statements, $data);
		}
		if ($this->database->getType() == 'oracle') {
			$sql = $this->translateOracleAlterTableStatements($sql, $extra_statements, $data);
		}
		if ($this->database->getType() == 'postgresql') {
			$sql = $this->translatePostgreSQLAlterTableStatements($sql, $extra_statements, $data);
		}
		if ($this->database->getType() == 'sqlite') {
			if ($data['type'] == 'rename_table') {
				$sql = $this->translateSQLiteRenameTableStatements($sql, $extra_statements, $data);
			} else {
				$sql = $this->translateSQLiteAlterTableStatements($sql, $extra_statements, $data);
			}
		}

		// All databases except for MySQL and Oracle support transactions around data definition queries
		// All of the Oracle statements will fail on the first query, if at all, so we don't need to
		// worry too much. MySQL is a huge pain though.
		if (!in_array($this->database->getType(), array('mysql', 'oracle'))) {
			array_unshift($extra_statements, $sql);

			if (!$this->database->isInsideTransaction()) {
				$sql = "BEGIN";
				$extra_statements[] = "COMMIT";
				$rollback_statements[] = "ROLLBACK";
			} else {
				$sql = array_shift($extra_statements);
			}
		}

		return $sql;
	}

	/**
	 * Translates the structure of `CREATE TABLE` statements to the database specific syntax
	 *
	 * @param  string $sql                The SQL to translate
	 * @param  array<int, string>  &$extra_statements  Any extra SQL statements that need to be added
	 * @return string  The translated SQL
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	private function translateCreateTableStatements(string $sql, array &$extra_statements): string
	{
		if (!preg_match('#^\s*CREATE\s+TABLE\s+["`\[]?(\w+)["`\]]?#i', $sql, $table_matches)) {
			return $sql;
		}

		$table = $table_matches[1];
		$sql = $this->translateDataTypes($sql);

		if ($this->database->getType() == 'db2') {
			$regex = array(
				'#("[^"]+"|\w+)\s+boolean(.*?)(,|\)|$)#im'   => '\1 CHAR(1)\2 CHECK(\1 IN (\'0\', \'1\'))\3',
				'#\binteger(?:\(\d+\))?\s+autoincrement\b#i' => 'INTEGER GENERATED BY DEFAULT AS IDENTITY',
				'#\)\s*$#D'                                  => ') CCSID UNICODE'
			);
			$sql = preg_replace(array_keys($regex), array_values($regex), $sql) ?? $sql;

			// DB2 only supports some ON UPDATE clauses
			$sql = preg_replace('#(\sON\s+UPDATE\s+(CASCADE|SET\s+NULL))#i', '', $sql) ?? $sql;
		} elseif ($this->database->getType() == 'mssql') {
			$sql = preg_replace('#\binteger(?:\(\d+\))?\s+autoincrement\b#i', 'INTEGER IDENTITY', $sql) ?? $sql;
		} elseif ($this->database->getType() == 'mysql') {
			$sql = preg_replace('#\binteger(?:\(\d+\))?\s+autoincrement\b#i', 'INTEGER AUTO_INCREMENT', $sql) ?? $sql;

			// Make sure MySQL uses InnoDB tables, translate check constraints to enums and fix column-level foreign key definitions
			preg_match_all('#(?<=,|\()\s*(["`]?\w+["`]?)\s+(?:[a-z]+)(?:\(\d+\))?(?:\s+unsigned|\s+zerofill|\s+character\s+set\s+[^ ]+|\s+collate\s+[^ ]+|\s+NULL|\s+NOT\s+NULL|(\s+DEFAULT\s+(?:[^, \']*|\'(?:\'\'|[^\']+)*\'))|\s+UNIQUE|\s+PRIMARY\s+KEY|(\s+CHECK\s*\(\w+\s+IN\s+(\(\s*(?:(?:[^, \']+|\'(?:\'\'|[^\']+)*\')\s*,\s*)*\s*(?:[^, \']+|\'(?:\'\'|[^\']+)*\')\))\)))*(\s+REFERENCES\s+["`]?\w+["`]?\s*\(\s*["`]?\w+["`]?\s*\)\s*(?:\s+(?:ON\s+DELETE|ON\s+UPDATE)\s+(?:CASCADE|NO\s+ACTION|RESTRICT|SET\s+NULL|SET\s+DEFAULT))*)?\s*(,|\s*(?=\))|$)#miD', $sql, $matches, PREG_SET_ORDER);

			foreach ($matches as $match) {
				// MySQL has the enum data type, so we switch check constraints to that
				if (!empty($match[3])) {
					$replacement = "\n " . $match[1] . ' enum' . $match[4] . $match[2] . $match[5] . $match[6];
					$sql = str_replace($match[0], $replacement, $sql);
					// This allows us to do a str_replace below for converting foreign key syntax
					$match[0] = $replacement;
				}

				// Even InnoDB table types don't allow specify foreign key constraints in the column
				// definition, so we move it to its own definition on the next line
				if (!empty($match[5])) {
					$updated_match_0 = str_replace($match[5], ",\nFOREIGN KEY (" . $match[1] . ') ' . $match[5], $match[0]);
					$sql = str_replace($match[0], $updated_match_0, $sql);
				}
			}

			$sql = preg_replace('#\)\s*;?\s*$#D', ')ENGINE=InnoDB, CHARACTER SET utf8', $sql) ?? $sql;
		} elseif ($this->database->getType() == 'oracle') {
			// If NOT NULL DEFAULT '' is present, both are removed since Oracle converts '' to NULL
			$sql = preg_replace('#(\bNOT\s+NULL\s+DEFAULT\s+\'\'|\bDEFAULT\s+\'\'\s+NOT\s+NULL)#', '', $sql) ?? $sql;

			// Oracle does not support ON UPDATE clauses
			$sql = preg_replace('#(\sON\s+UPDATE\s+(CASCADE|SET\s+NULL|NO\s+ACTION|RESTRICT))#i', '', $sql) ?? $sql;

			// Create sequences and triggers for Oracle
			if (stripos($sql, 'autoincrement') !== false && preg_match('#(?<=,|\(|^)\s*("?\w+"?)\s+(?:[a-z]+)(?:\((?:\d+)\))?.*?\bAUTOINCREMENT\b[^,\)]*(?:,|\s*(?=\)))#mi', $sql, $matches)) {
				$column        = $matches[1];

				$table_column  = substr(str_replace('"', '', $table) . '_' . str_replace('"', '', $column), 0, 26);

				$sequence_name = $table_column . '_seq';
				$trigger_name  = $table_column . '_trg';

				$sequence = 'CREATE SEQUENCE ' . $sequence_name;

				$trigger  = 'CREATE OR REPLACE TRIGGER ' . $trigger_name . "\n";
				$trigger .= "BEFORE INSERT ON " . $table . "\n";
				$trigger .= "FOR EACH ROW\n";
				$trigger .= "BEGIN\n";
				$trigger .= "  IF :new." . $column . " IS NULL THEN\n";
				$trigger .= "	SELECT " . $sequence_name . ".nextval INTO :new." . $column . " FROM dual;\n";
				$trigger .= "  END IF;\n";
				$trigger .= "END;";

				$extra_statements[] = $sequence;
				$extra_statements[] = $trigger;

				$sql = preg_replace('#\s+autoincrement\b#i', '', $sql) ?? $sql;
			}
		} elseif ($this->database->getType() == 'postgresql') {
			$sql = preg_replace('#\binteger(?:\(\d+\))?\s+autoincrement\b#i', 'SERIAL', $sql) ?? $sql;
		} elseif ($this->database->getType() == 'sqlite') {
			// Data type translation
			if (version_compare($this->database->getVersion(), '3', '>=')) {
				$sql = preg_replace('#\binteger(?:\(\d+\))?\s+autoincrement\s+primary\s+key\b#i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql) ?? $sql;
				$sql = preg_replace("#datetime\(\s*CURRENT_TIMESTAMP\s*,\s*'localtime'\s*\)#i", 'CURRENT_TIMESTAMP', $sql) ?? $sql;
			} else {
				$sql = preg_replace('#\binteger(?:\(\d+\))?\s+autoincrement\s+primary\s+key\b#i', 'INTEGER PRIMARY KEY', $sql) ?? $sql;
				$sql = preg_replace('#CURRENT_TIMESTAMP\(\)#i', 'CURRENT_TIMESTAMP', $sql) ?? $sql;
			}

			// SQLite 3.6.19 and newer, may or may not have native foreign key support
			$toggle_foreign_key_support = false;
			if (!isset($this->schema_info['foreign_keys_enabled'])) {
				$toggle_foreign_key_support = true;
				$foreign_keys_res = $this->query('PRAGMA foreign_keys');
				if ($foreign_keys_res->countReturnedRows() && $foreign_keys_res->fetchScalar()) {
					$this->schema_info['foreign_keys_enabled'] = true;
				} else {
					$this->schema_info['foreign_keys_enabled'] = false;
				}
			}

			// Create foreign key triggers for SQLite
			if (stripos($sql, 'REFERENCES') !== false && !$this->schema_info['foreign_keys_enabled']) {
				preg_match_all('#(?:(?<=,|\(|\*/|\n)\s*(?:`|"|\[)?(\w+)(?:`|"|\])?\s+(?:[a-z]+)(?:\(\s*(?:\d+)(?:\s*,\s*(?:\d+))?\s*\))?(?:(\s+NOT\s+NULL)|(?:\s+NULL)|(?:\s+DEFAULT\s+(?:[^, \']*|\'(?:\'\'|[^\']+)*\'))|(?:\s+UNIQUE)|(?:\s+PRIMARY\s+KEY(?:\s+AUTOINCREMENT)?)|(?:\s+CHECK\s*\(\w+\s+IN\s+\(\s*(?:(?:[^, \']+|\'(?:\'\'|[^\']+)*\')\s*,\s*)*\s*(?:[^, \']+|\'(?:\'\'|[^\']+)*\')\)\)))*(?:\s+REFERENCES\s+["`\[]?(\w+)["`\]]?\s*\(\s*["`\[]?(\w+)["`\]]?\s*\)\s*(?:(?:\s+ON\s+DELETE\s+(CASCADE|NO\s+ACTION|RESTRICT|SET\s+NULL|SET\s+DEFAULT))|(?:\s+ON\s+UPDATE\s+(CASCADE|NO\s+ACTION|RESTRICT|SET\s+NULL|SET\s+DEFAULT)))*(?:\s+(?:DEFERRABLE|NOT\s+DEFERRABLE))?)?(?:\s*(?:/\*(?:(?!\*/).)*\*/))?\s*(?:,(?:[ \t]*--[^\n]*\n)?|(?:--[^\n]*\n)?\s*(?=\))))|(?:(?<=,|\(|\*/|\n)\s*(?:CONSTRAINT\s+["`\[]?\w+["`\]]?\s+)?FOREIGN\s+KEY\s*\(?\s*["`\[]?(\w+)["`\]]?\s*\)?\s+REFERENCES\s+["`\[]?(\w+)["`\]]?\s*\(\s*["`\[]?(\w+)["`\]]?\s*\)\s*(?:(?:\s+ON\s+DELETE\s+(CASCADE|NO\s+ACTION|RESTRICT|SET\s+NULL|SET\s+DEFAULT))|(?:\s+ON\s+UPDATE\s+(CASCADE|NO\s+ACTION|RESTRICT|SET\s+NULL|SET\s+DEFAULT)))*(?:\s+(?:DEFERRABLE|NOT\s+DEFERRABLE))?\s*(?:,(?:[ \t]*--[^\n]*\n)?|(?:--[^\n]*\n)?\s*(?=\))))#mis', $sql, $matches, PREG_SET_ORDER);

				$not_null_columns = array();
				foreach ($matches as $match) {
					// Find all of the not null columns
					if (!empty($match[2])) {
						$not_null_columns[] = $match[1] ?? '';
					}

					// If neither of these fields is matched, we don't have a foreign key
					if (empty($match[3]) && empty($match[7])) {
						continue;
					}

					if (!empty($match[1])) {
						$column         = $match[1];
						$foreign_table  = $match[3] ?? '';
						$foreign_column = $match[4] ?? '';
						$on_delete      = isset($match[5]) ? $match[5] : null;
						$on_update      = isset($match[6]) ? $match[6] : null;
					} else {
						$column         = $match[7] ?? '';
						$foreign_table  = $match[8] ?? '';
						$foreign_column = $match[9] ?? '';
						$on_delete      = isset($match[10]) ? $match[10] : null;
						$on_update      = isset($match[11]) ? $match[11] : null;
					}

					if (!$on_delete) {
						$on_delete = 'NO ACTION';
					}
					if (!$on_update) {
						$on_update = 'NO ACTION';
					}

					$this->createSQLiteForeignKeyTriggerValidInsertUpdate(
						$extra_statements,
						$table,
						$column,
						$foreign_table,
						$foreign_column,
						in_array($column, $not_null_columns)
					);

					$this->createSQLiteForeignKeyTriggerOnDelete(
						$extra_statements,
						$table,
						$column,
						$foreign_table,
						$foreign_column,
						$on_delete
					);
					$this->createSQLiteForeignKeyTriggerOnUpdate(
						$extra_statements,
						$table,
						$column,
						$foreign_table,
						$foreign_column,
						$on_update
					);
				}
			}

			if ($toggle_foreign_key_support) {
				unset($this->schema_info['foreign_keys_enabled']);
			}
		}

		return $sql;
	}


	/**
	 * Translates basic data types
	 *
	 * @param string $sql  The SQL to translate
	 * @return string  The translated SQL
	 */
	private function translateDataTypes(string $sql): string
	{
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
				// SQLite doesn't have ALTER TABLE statements, so everything for data
				// types is handled via ::translateCreateTableStatements()
				$regex = array();
				break;
		}

		return preg_replace(array_keys($regex), array_values($regex), $sql) ?? $sql;
	}


	/**
	 * Translates Flourish SQL `ALTER TABLE` statements to the appropriate
	 * statements for DB2
	 *
	 * @param string $sql                The SQL statements that will be executed against the database
	 * @param array<int, string>  &$extra_statements  Any extra SQL statements required for DB2
	 * @param AlterTableData $data               Data parsed from the `ALTER TABLE` statement
	 * @return string  The modified SQL statement
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	private function translateDB2AlterTableStatements(string $sql, array &$extra_statements, array $data): string
	{
		$data['schema']               = strtolower($this->database->getUsername());
		$data['table_without_schema'] = $data['table'];
		if (strpos($data['table'], '.') !== false) {
			list ($data['schema'], $data['table_without_schema']) = explode('.', $data['table']);
		}

		if (in_array($data['type'], array('drop_check_constraint', 'drop_primary_key', 'drop_foreign_key', 'drop_unique'))) {
			$column_info = $this->query(
				"SELECT
					C.COLNAME
				FROM
					SYSCAT.TABLES AS T LEFT JOIN
					SYSCAT.COLUMNS AS C ON
						T.TABSCHEMA = C.TABSCHEMA AND
						T.TABNAME = C.TABNAME AND
						LOWER(C.COLNAME) = %s
				WHERE
					LOWER(T.TABSCHEMA) = %s AND
					LOWER(T.TABNAME) = %s",
				isset($data['column_name']) ? $data['column_name'] : '',
				$data['schema'],
				$data['table_without_schema']
			);

			if (!$column_info->countReturnedRows()) {
				$this->throwException(
					self::compose(
						'The table "%1$s" does not exist',
						$data['table']
					),
					$sql
				);
			}

			if (isset($data['column_name'])) {
				$row = $this->fetchRowArray($column_info);
				if (!strlen($this->stringValue($row['colname']))) {
					$this->throwException(
						self::compose(
							'The column "%1$s" does not exist in the table "%2$s"',
							$data['column_name'],
							$data['table']
						),
						$sql
					);
				}
			}
		}

		if ($data['type'] == 'column_comment') {
			// DB2 handles the normalized syntax
		} elseif ($data['type'] == 'rename_table') {
			$foreign_key_constraints = $this->getDB2ForeignKeyConstraints(
				$data['schema'],
				$data['table_without_schema']
			);
			foreach ($foreign_key_constraints as $constraint) {
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r DROP CONSTRAINT %r",
					$constraint['schema'] . '.' . $constraint['table'],
					$constraint['constraint_name']
				);
			}

			$sql = $this->escape(
				"RENAME TABLE %r TO %r",
				$data['table'],
				$data['new_table_name']
			);

			$extra_statements[] = $sql;
			$sql = array_shift($extra_statements);

			foreach ($foreign_key_constraints as $constraint) {
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r ADD FOREIGN KEY (%r) REFERENCES %r(%r) ON DELETE " . $constraint['on_delete'] . ' ON UPDATE ' . $constraint['on_update'],
					$constraint['schema'] . '.' . $constraint['table'],
					$constraint['column'],
					$constraint['foreign_schema'] . '.' . $data['new_table_name'],
					$constraint['foreign_column']
				);
			}
		} elseif ($data['type'] == 'rename_column') {
			$data['column_name'] = strtolower($data['column_name']);

			$foreign_key_constraints = $this->getDB2ForeignKeyConstraints(
				$data['schema'],
				$data['table_without_schema'],
				$data['column_name']
			);
			foreach ($foreign_key_constraints as $constraint) {
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r DROP CONSTRAINT %r",
					$constraint['schema'] . '.' . $constraint['table'],
					$constraint['constraint_name']
				);
			}

			$unique_constraints = $this->getDB2UniqueConstraints(
				$data['schema'],
				$data['table_without_schema'],
				$data['column_name']
			);
			foreach ($unique_constraints as $name => $columns) {
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r DROP UNIQUE %r",
					$data['table'],
					$name
				);
			}

			$check_constraint = $this->getDB2CheckConstraint(
				$data['schema'],
				$data['table_without_schema'],
				$data['column_name']
			);
			if ($check_constraint) {
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r DROP CONSTRAINT %r",
					$data['table'],
					$check_constraint['name']
				);
			}

			$primary_key_columns = $this->getDB2PrimaryKeyConstraint(
				$data['schema'],
				$data['table_without_schema']
			);
			if (in_array($data['column_name'], $primary_key_columns)) {
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r DROP PRIMARY KEY",
					$data['table']
				);
			}

			$extra_statements[] = $sql;
			$sql = array_shift($extra_statements);

			if (in_array($data['column_name'], $primary_key_columns)) {
				$key = array_search($data['column_name'], $primary_key_columns);
				$primary_key_columns[$key] = $data['new_column_name'];
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r ADD PRIMARY KEY (%r)",
					$data['table'],
					$primary_key_columns
				);
			}

			if ($check_constraint) {
				$check_constraint['definition'] = preg_replace(
					'#^\s*"?' . preg_quote($data['column_name'], '#') . '"?#i',
					$this->escape('%r', $data['new_column_name']),
					$check_constraint['definition']
				);
				$constraint_name = $this->generateConstraintName($sql, 'ck');
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r ADD CONSTRAINT %r CHECK(",
					$data['table'],
					$constraint_name
				) . $check_constraint['definition'] . ')';
			}

			foreach ($unique_constraints as $name => $columns) {
				$key = array_search($data['column_name'], $columns);
				$columns[$key] = $data['new_column_name'];
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r ADD UNIQUE (%r)",
					$data['table'],
					$columns
				);
			}

			foreach ($foreign_key_constraints as $constraint) {
				if ($constraint['table'] == $data['table_without_schema'] && $constraint['column'] == $data['column_name']) {
					$constraint['column'] = $data['new_column_name'];
				} else {
					$constraint['foreign_column'] = $data['new_column_name'];
				}

				$extra_statements[] = $this->escape(
					"ALTER TABLE %r ADD FOREIGN KEY (%r) REFERENCES %r(%r) ON DELETE " . $constraint['on_delete'] . ' ON UPDATE ' . $constraint['on_update'],
					$constraint['schema'] . '.' . $constraint['table'],
					$constraint['column'],
					$constraint['foreign_schema'] . '.' . $constraint['foreign_table'],
					$constraint['foreign_column']
				);
			}
		} elseif ($data['type'] == 'add_column') {
			$sql = $this->translateDataTypes($sql);

			// DB2 only supports some ON UPDATE clauses
			$sql = preg_replace('#(\sON\s+UPDATE\s+(CASCADE|SET\s+NULL))#i', '', $sql) ?? $sql;

			// Boolean translation is more context-sensitive, hence it is not part of translateDataTypes()
			$sql = preg_replace('#("[^"]+"|\w+)\s+boolean\b(.*)$#iD', '\1 CHAR(1)\2 CHECK(\1 IN (\'0\', \'1\'))', $sql) ?? $sql;

			if (preg_match('#\binteger(?:\(\d+\))?\s+autoincrement\b#i', $sql)) {
				$sql = preg_replace('# autoincrement\b#i', '', $sql) ?? $sql;
				$sql = preg_replace('# PRIMARY\s+KEY\b#i', ' NOT NULL DEFAULT 0', $sql) ?? $sql;
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r ALTER COLUMN %r DROP DEFAULT",
					$data['table'],
					$data['column_name']
				);
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r ALTER COLUMN %r SET GENERATED BY DEFAULT AS IDENTITY",
					$data['table'],
					$data['column_name']
				);
				//$extra_statements[] = "CALL SYSPROC.ADMIN_CMD('REORG TABLE " . $this->escape('%r', $data['table']) . "')";
				// REORGE implicitly commits
				//$extra_statements[] = "BEGIN";
				$extra_statements[] = $this->escape(
					"UPDATE %r SET %r = DEFAULT",
					$data['table'],
					$data['column_name']
				);
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r ADD PRIMARY KEY (%r)",
					$data['table'],
					$data['column_name']
				);
			}
		} elseif ($data['type'] == 'drop_column') {
			$sql .= ' CASCADE';
			// Certain operations in DB2 require calling REORG
			$extra_statements[] = "CALL SYSPROC.ADMIN_CMD('REORG TABLE " . $this->escape('%r', $data['table']) . "')";
			// REORGE implicitly commits
			$extra_statements[] = "BEGIN";
		} elseif ($data['type'] == 'alter_type') {
			$data['data_type'] = $this->translateDataTypes($data['data_type']);

			$sql = $this->escape(
				"ALTER TABLE %r ALTER COLUMN %r SET DATA TYPE " . $data['data_type'],
				$data['table'],
				$data['column_name']
			);
			// Certain operations in DB2 require calling REORG
			$extra_statements[] = "CALL SYSPROC.ADMIN_CMD('REORG TABLE " . $this->escape('%r', $data['table']) . "')";
			// REORGE implicitly commits
			$extra_statements[] = "BEGIN";
		} elseif ($data['type'] == 'set_default') {
			// DB2 handles the normalized syntax
		} elseif ($data['type'] == 'drop_default') {
			// DB2 complains if you try to drop the default for a column without a default
			$column_info = $this->query(
				"SELECT
					C.DEFAULT
				FROM
					SYSCAT.COLUMNS AS C
				WHERE
					LOWER(C.TABSCHEMA) = %s AND
					LOWER(C.TABNAME) = %s AND
					LOWER(C.COLNAME) = %s",
				$data['schema'],
				$data['table_without_schema'],
				$data['column_name']
			);
			if ($column_info->countReturnedRows()) {
				$default = $column_info->fetchScalar();
				if ($default === null) {
					$sql = "SELECT 'noop - no constraint to drop' FROM SYSIBM.SYSDUMMY1";
				}
			}
		} elseif ($data['type'] == 'set_not_null') {
			$extra_statements[] = "CALL SYSPROC.ADMIN_CMD('REORG TABLE " . $this->escape('%r', $data['table']) . "')";
			// REORGE implicitly commits
			$extra_statements[] = "BEGIN";

			if (isset($data['default'])) {
				$sql = $this->escape(
					"ALTER TABLE %r ALTER COLUMN %r SET NOT NULL",
					$data['table'],
					$data['column_name']
				);
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r ALTER COLUMN %r SET DEFAULT ",
					$data['table'],
					$data['column_name']
				) . $data['default'];
			}
		} elseif ($data['type'] == 'drop_not_null') {
			// DB2 handles the normalized syntax
			// Certain operations in DB2 require calling REORG
			$extra_statements[] = "CALL SYSPROC.ADMIN_CMD('REORG TABLE " . $this->escape('%r', $data['table']) . "')";
			// REORGE implicitly commits
			$extra_statements[] = "BEGIN";
		} elseif ($data['type'] == 'drop_check_constraint') {
			$check_constraint = $this->getDB2CheckConstraint(
				$data['schema'],
				$data['table_without_schema'],
				$data['column_name']
			);
			if (!$check_constraint) {
				$this->throwException(
					self::compose(
						'The column "%1$s" in the table "%2$s" does not have a check constraint',
						$data['column_name'],
						$data['table']
					),
					$sql
				);
			}
			$sql = $this->escape(
				"ALTER TABLE %r DROP CONSTRAINT %r",
				$data['table'],
				$check_constraint['name']
			);
		} elseif ($data['type'] == 'set_check_constraint') {
			$check_constraint = $this->getDB2CheckConstraint(
				$data['schema'],
				$data['table_without_schema'],
				$data['column_name']
			);
			if ($check_constraint) {
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r DROP CONSTRAINT %r",
					$data['table'],
					$check_constraint['name']
				);
			}
			$sql = $this->escape(
				"ALTER TABLE %r ADD CONSTRAINT %r",
				$data['table'],
				$this->generateConstraintName($sql, 'ck')
			) . $data['constraint'];

			$extra_statements[] = $sql;
			$sql = array_shift($extra_statements);
		} elseif ($data['type'] == 'drop_primary_key') {
			// We drop the default value when dropping primary keys to get
			// rid of autoincrementing functionality
			$primary_key_columns = $this->getDB2PrimaryKeyConstraint(
				$data['schema'],
				$data['table_without_schema']
			);
			if (count($primary_key_columns) == 1) {
				$is_identity = (bool) $this->query(
					"SELECT
						CASE WHEN C.IDENTITY = 'Y' AND (C.GENERATED = 'D' OR C.GENERATED = 'A') THEN '1' ELSE '0' END AS AUTO_INCREMENT
					FROM
						SYSCAT.COLUMNS AS C
					WHERE
						LOWER(C.TABSCHEMA) = %s AND
						LOWER(C.TABNAME) = %s AND
						LOWER(C.COLNAME) = %s",
					$data['schema'],
					$data['table_without_schema'],
					reset($primary_key_columns)
				)->fetchScalar();

				if ($is_identity) {
					$extra_statements[] = $this->escape(
						"ALTER TABLE %r ALTER COLUMN %r DROP IDENTITY",
						$data['table'],
						reset($primary_key_columns)
					);
				}
			}
		} elseif ($data['type'] == 'add_primary_key') {
			if ($data['autoincrement']) {
				$column_name = $data['column_name'] ?? '';
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r ALTER COLUMN %r SET GENERATED BY DEFAULT AS IDENTITY",
					$data['table'],
					$column_name
				);
				//$extra_statements[] = "CALL SYSPROC.ADMIN_CMD('REORG TABLE " . $this->escape('%r', $data['table']) . "')";
				// REORGE implicitly commits
				//$extra_statements[] = "BEGIN";
				$extra_statements[] = $this->escape(
					"UPDATE %r SET %r = DEFAULT",
					$data['table'],
					$column_name
				);
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r ADD PRIMARY KEY (%r)",
					$data['table'],
					$column_name
				);

				$sql = array_shift($extra_statements);
			}
		} elseif ($data['type'] == 'drop_foreign_key') {
			$constraint = $this->query(
				"SELECT
					R.CONSTNAME AS CONSTRAINT_NAME
				FROM
					SYSCAT.REFERENCES AS R INNER JOIN
					SYSCAT.KEYCOLUSE AS K ON
						R.CONSTNAME = K.CONSTNAME AND
						R.TABSCHEMA = K.TABSCHEMA AND
						R.TABNAME = K.TABNAME INNER JOIN
					SYSCAT.KEYCOLUSE AS FK ON
						R.REFKEYNAME = FK.CONSTNAME AND
						R.REFTABSCHEMA = FK.TABSCHEMA AND
						R.REFTABNAME = FK.TABNAME
				WHERE
					LOWER(R.TABSCHEMA) = %s AND
					LOWER(R.TABNAME) = %s AND
					LOWER(K.COLNAME) = %s",
				$data['schema'],
				$data['table_without_schema'],
				$data['column_name']
			);
			if (!$constraint->countReturnedRows()) {
				$this->throwException(
					self::compose(
						'The column "%1$s" in the table "%2$s" does not have a foreign key constraint',
						$data['column_name'],
						$data['table']
					),
					$sql
				);
			}
			$sql = $this->escape(
				"ALTER TABLE %r DROP CONSTRAINT %r",
				$data['table'],
				$constraint->fetchScalar()
			);
		} elseif ($data['type'] == 'add_foreign_key') {
			// DB2 only supports some ON UPDATE clauses
			$sql = preg_replace('#(\sON\s+UPDATE\s+(CASCADE|SET\s+NULL))#i', '', $sql) ?? $sql;
		} elseif ($data['type'] == 'drop_unique') {
			$constraint_rows = $this->fetchAllRowArrays($this->query(
				"SELECT
					CD.CONSTNAME AS CONSTRAINT_NAME,
					LOWER(C.COLNAME) AS COLUMN
				FROM
					SYSCAT.INDEXES AS I INNER JOIN
					SYSCAT.CONSTDEP AS CD ON
						I.TABSCHEMA = CD.TABSCHEMA AND
						I.TABNAME = CD.TABNAME AND
						CD.BTYPE = 'I' AND
						CD.BNAME = I.INDNAME INNER JOIN
					SYSCAT.INDEXCOLUSE AS C ON
						I.INDSCHEMA = C.INDSCHEMA AND
						I.INDNAME = C.INDNAME
				WHERE
					LOWER(I.TABSCHEMA) = %s AND
					LOWER(I.TABNAME) = %s AND
					I.UNIQUERULE = 'U'",
				$data['schema'],
				$data['table_without_schema']
			));
			$constraints = array();
			foreach ($constraint_rows as $row) {
				$constraint_name = $this->stringValue($row['constraint_name']);
				if (!isset($constraints[$constraint_name])) {
					$constraints[$constraint_name] = array();
				}
				$constraints[$constraint_name][] = $row['column'];
			}
			$constraint_name = null;
			sort($data['column_names']);
			foreach ($constraints as $name => $columns) {
				sort($columns);
				if ($columns == $data['column_names']) {
					$constraint_name = $name;
					break;
				}
			}
			if (!$constraint_name) {
				if (count($data['column_names']) > 1) {
					$message = self::compose(
						'The columns "%1$s" in the table "%2$s" do not have a unique constraint',
						join('", "', $data['column_names']),
						$data['table']
					);
				} else {
					$message = self::compose(
						'The column "%1$s" in the table "%2$s" does not have a unique constraint',
						reset($data['column_names']),
						$data['table']
					);
				}
				$this->throwException($message, $sql);
			}
			$sql = $this->escape(
				"ALTER TABLE %r DROP UNIQUE %r",
				$data['table'],
				$constraint_name
			);
		} elseif ($data['type'] == 'add_unique') {
			// DB2 handles the normalized syntax
		}

		return $sql;
	}


	/**
	 * Translates Flourish SQL `ALTER TABLE` statements to the appropriate
	 * statements for MSSQL
	 *
	 * @param string $sql                The SQL statements that will be executed against the database
	 * @param array<int, string>  &$extra_statements  Any extra SQL statements required for MSSQL
	 * @param AlterTableData $data               Data parsed from the `ALTER TABLE` statement
	 * @return string  The modified SQL statement
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	private function translateMSSQLAlterTableStatements(string $sql, array &$extra_statements, array $data): string
	{
		$data['schema']               = 'dbo';
		$data['table_without_schema'] = $data['table'];
		if (strpos($data['table'], '.') !== false) {
			list ($data['schema'], $data['table_without_schema']) = explode('.', $data['table']);
		}

		if (in_array($data['type'], array('set_not_null', 'drop_not_null', 'drop_default', 'drop_check_constraint', 'drop_primary_key', 'drop_foreign_key', 'drop_unique'))) {
			$column_info = $this->query(
				"SELECT
					t.table_name,
					c.column_name
				FROM
					information_schema.tables AS t LEFT JOIN
					information_schema.columns AS c ON
						c.table_name = t.table_name AND
						c.table_schema = t.table_schema AND
						LOWER(c.column_name) = %s
				WHERE
					LOWER(t.table_name) = %s AND
					LOWER(t.table_schema) = %s AND
					t.table_catalog = DB_NAME()",
				isset($data['column_name']) ? $data['column_name'] : '',
				$data['table_without_schema'],
				$data['schema']
			);

			if (!$column_info->countReturnedRows()) {
				$this->throwException(
					self::compose(
						'The table "%1$s" does not exist',
						$data['table']
					),
					$sql
				);
			}

			if (isset($data['column_name'])) {
				$row = $this->fetchRowArray($column_info);
				if (!strlen($this->stringValue($row['column_name']))) {
					$this->throwException(
						self::compose(
							'The column "%1$s" does not exist in the table "%2$s"',
							$data['column_name'],
							$data['table']
						),
						$sql
					);
				}
			}
		}

		if (in_array($data['type'], array('set_not_null', 'drop_not_null'))) {
			$column_info = $this->query(
				"SELECT
					c.data_type                AS 'type',
					c.character_maximum_length AS max_length,
					c.numeric_precision        AS precision,
					c.numeric_scale            AS decimal_places
				FROM
					information_schema.columns AS c
				WHERE
					LOWER(c.table_name) = %s AND
					LOWER(c.table_schema) = %s AND
					LOWER(c.column_name) = %s AND
					c.table_catalog = DB_NAME()",
				$data['table_without_schema'],
				$data['schema'],
				$data['column_name']
			);

			$row = $this->fetchRowArray($column_info);
			$data_type = $this->stringValue($row['type']);
			if ($row['max_length']) {
				$data_type .= '(' . $this->stringValue($row['max_length']) . ')';
			}
			if (!preg_match('#^\s*int#i', $data_type) && $row['precision']) {
				$data_type .= '(' . $this->stringValue($row['precision']);
				if ($row['decimal_places']) {
					$data_type .= ', ' . $this->stringValue($row['decimal_places']);
				}
				$data_type .= ')';
			}
		}

		if ($data['type'] == 'column_comment') {
			$get_sql = "SELECT
					CAST(ex.value AS VARCHAR(7500)) AS 'comment'
				FROM
					INFORMATION_SCHEMA.COLUMNS AS c";
			if (version_compare($this->database->getVersion(), '9', '<')) {
				$get_sql .= " INNER JOIN sysproperties AS ex ON ex.id = OBJECT_ID(QUOTENAME(c.table_schema) + '.' + QUOTENAME(c.table_name)) AND ex.smallid = COLUMNPROPERTY(OBJECT_ID(QUOTENAME(c.table_schema) + '.' + QUOTENAME(c.table_name)), c.column_name, 'ColumnId') AND ex.name = 'MS_Description' AND OBJECTPROPERTY(OBJECT_ID(QUOTENAME(c.table_schema) + '.' + QUOTENAME(c.table_name)), 'IsMsShipped') = 0 ";
			} else {
				$get_sql .= " INNER JOIN SYS.EXTENDED_PROPERTIES AS ex ON ex.major_id = OBJECT_ID(QUOTENAME(c.table_schema) + '.' + QUOTENAME(c.table_name)) AND ex.minor_id = COLUMNPROPERTY(OBJECT_ID(QUOTENAME(c.table_schema) + '.' + QUOTENAME(c.table_name)), c.column_name, 'ColumnId') AND ex.name = 'MS_Description' AND OBJECTPROPERTY(OBJECT_ID(QUOTENAME(c.table_schema) + '.' + QUOTENAME(c.table_name)), 'IsMsShipped') = 0 ";
			}
			$get_sql .= "WHERE
				LOWER(c.table_name) = %s AND
				LOWER(c.table_schema) = %s AND
				LOWER(c.column_name) = %s AND
				c.table_catalog = DB_NAME()";
			$result = $this->query($get_sql, $data['table_without_schema'], $data['schema'], $data['column_name']);

			$stored_procedure = 'sys.sp_addextendedproperty';
			if ($result->countReturnedRows()) {
				$stored_procedure = 'sys.sp_updateextendedproperty';
			}

			$sql = "EXECUTE " . $stored_procedure . " @name='MS_Description', @value=" . $data['comment'] . $this->escape(
				", @level0type='SCHEMA', @level0name=%s, @level1type='TABLE',  @level1name=%s, @level2type='COLUMN', @level2name=%s",
				$data['schema'],
				$data['table_without_schema'],
				$data['column_name']
			);
		} elseif ($data['type'] == 'rename_table') {
			$sql = $this->escape(
				"EXECUTE sp_rename %r, %r",
				$data['table'],
				$data['new_table_name']
			);
		} elseif ($data['type'] == 'rename_column') {
			// Find any check constraints and remove them, they will be re-added after the rename
			$check_constraint = $this->getMSSQLCheckConstraint(
				$data['schema'],
				$data['table_without_schema'],
				$data['column_name']
			);

			if ($check_constraint) {
				$check_constraint['definition'] = preg_replace(
					'#(\[)' . $data['column_name'] . '(\]\s*=\s*\')#i',
					'\1' . $data['new_column_name'] . '\2',
					$check_constraint['definition']
				);
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r DROP CONSTRAINT %r",
					$data['table'],
					$check_constraint['name']
				);
			}

			$sql = $this->escape(
				'EXECUTE sp_rename "' . $data['table'] . '.' . $data['column_name'] . '", %r',
				$data['new_column_name']
			);

			$extra_statements[] = $sql;
			$sql = array_shift($extra_statements);

			if ($check_constraint) {
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r ADD CONSTRAINT %r CHECK",
					$data['table'],
					$this->generateConstraintName($sql, 'ck')
				) . $check_constraint['definition'];
			}
		} elseif ($data['type'] == 'add_column') {
			$sql = $this->escape(
				'ALTER TABLE %r ADD ',
				$data['table']
			) . $data['column_definition'];

			$sql = $this->translateDataTypes($sql);
			$sql = preg_replace('#\binteger(?:\(\d+\))?\s+autoincrement\b#i', 'INTEGER IDENTITY', $sql) ?? $sql;
		} elseif ($data['type'] == 'drop_column') {
			// We must find all constraints that reference this column and drop them first
			$foreign_key_constraints = $this->getMSSQLForeignKeyConstraints(
				$data['schema'],
				$data['table_without_schema'],
				$data['column_name']
			);
			foreach ($foreign_key_constraints as $constraint) {
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r DROP CONSTRAINT %r",
					$constraint['table'],
					$constraint['name']
				);
			}

			$unique_constraints = $this->getMSSQLUniqueConstraints(
				$data['schema'],
				$data['table_without_schema'],
				$data['column_name']
			);
			foreach ($unique_constraints as $constraint_name => $constraint) {
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r DROP CONSTRAINT %r",
					$data['table'],
					$constraint_name
				);
			}

			$check_constraint = $this->getMSSQLCheckConstraint(
				$data['schema'],
				$data['table_without_schema'],
				$data['column_name']
			);
			if ($check_constraint) {
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r DROP CONSTRAINT %r",
					$data['table'],
					$check_constraint['name']
				);
			}

			$default_constraint = $this->getMSSQLDefaultConstraint(
				$data['schema'],
				$data['table_without_schema'],
				$data['column_name']
			);
			if ($default_constraint) {
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r DROP CONSTRAINT %r",
					$data['table'],
					$default_constraint['name']
				);
			}

			$primary_key_constraint = $this->getMSSQLPrimaryKeyConstraint(
				$data['schema'],
				$data['table_without_schema']
			);
			if ($primary_key_constraint && in_array($data['column_name'], $primary_key_constraint['columns'])) {
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r DROP CONSTRAINT %r",
					$data['table'],
					$primary_key_constraint['name']
				);
			}

			$extra_statements[] = $sql;
			$sql = array_shift($extra_statements);
		} elseif ($data['type'] == 'alter_type') {
			$default_constraint = $this->getMSSQLDefaultConstraint(
				$data['schema'],
				$data['table_without_schema'],
				$data['column_name']
			);
			if ($default_constraint) {
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r DROP CONSTRAINT %r",
					$data['table'],
					$default_constraint['name']
				);
			}

			$check_constraint = $this->getMSSQLCheckConstraint(
				$data['schema'],
				$data['table_without_schema'],
				$data['column_name']
			);

			if ($check_constraint) {
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r DROP CONSTRAINT %r",
					$data['table'],
					$check_constraint['name']
				);
			}

			// Check if the column is NOT NULL since we have to specify that when changing the type
			$column_info = $this->query(
				"SELECT
					c.is_nullable
				FROM
					INFORMATION_SCHEMA.COLUMNS AS c
				WHERE
					LOWER(c.table_schema) = %s AND
					LOWER(c.table_name) = %s AND
					LOWER(c.column_name) = %s AND
					c.table_catalog = DB_NAME()",
				$data['schema'],
				$data['table_without_schema'],
				$data['column_name']
			);

			$not_null = false;
			if ($column_info->countReturnedRows()) {
				$row = $this->fetchRowArray($column_info);
				$not_null = $row['is_nullable'] == 'NO';
			}

			$unique_constraints = $this->getMSSQLUniqueConstraints(
				$data['schema'],
				$data['table_without_schema'],
				$data['column_name']
			);
			foreach ($unique_constraints as $constraint_name => $columns) {
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r DROP CONSTRAINT %r",
					$data['table'],
					$constraint_name
				);
			}

			$primary_key_constraint = $this->getMSSQLPrimaryKeyConstraint(
				$data['schema'],
				$data['table_without_schema']
			);

			if ($primary_key_constraint && in_array($data['column_name'], $primary_key_constraint['columns'])) {
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r DROP CONSTRAINT %r",
					$data['table'],
					$primary_key_constraint['name']
				);
			}


			$data['data_type'] = $this->translateDataTypes($data['data_type']);

			$sql = $this->escape(
				"ALTER TABLE %r ALTER COLUMN %r " . $data['data_type'] . ($not_null ? ' NOT NULL' : ''),
				$data['table'],
				$data['column_name']
			);

			$extra_statements[] = $sql;
			$sql = array_shift($extra_statements);

			if ($default_constraint) {
				$extra_statements[] = $this->escape("ALTER TABLE %r ADD DEFAULT ", $data['table']) .
					$default_constraint['definition'] .
					$this->escape(" FOR %r", $data['column_name']);
			}

			if ($check_constraint) {
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r ADD CONSTRAINT %r CHECK",
					$data['table'],
					$this->generateConstraintName($sql, 'ck')
				) . $check_constraint['definition'];
			}

			foreach ($unique_constraints as $constraint_name => $columns) {
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r ADD UNIQUE (%r)",
					$data['table'],
					$columns
				);
			}

			if ($primary_key_constraint && in_array($data['column_name'], $primary_key_constraint['columns'])) {
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r ADD PRIMARY KEY (%r)",
					$data['table'],
					$primary_key_constraint['columns']
				);
			}
		} elseif ($data['type'] == 'set_default') {
			// SQL Server requires removing any existing default constraint
			$default_constraint = $this->getMSSQLDefaultConstraint(
				$data['schema'],
				$data['table_without_schema'],
				$data['column_name']
			);
			if ($default_constraint) {
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r DROP CONSTRAINT %r",
					$data['table'],
					$default_constraint['name']
				);
			}
			$sql = $this->escape("ALTER TABLE %r ADD DEFAULT ", $data['table']) .
				$data['default_value'] .
				$this->escape(" FOR %r", $data['column_name']);

			$extra_statements[] = $sql;
			$sql = array_shift($extra_statements);
		} elseif ($data['type'] == 'drop_default') {
			$default_constraint = $this->getMSSQLDefaultConstraint(
				$data['schema'],
				$data['table_without_schema'],
				$data['column_name']
			);
			if (!$default_constraint) {
				$sql = "SELECT 'noop - no constraint to drop'";
			} else {
				$sql = $this->escape(
					"ALTER TABLE %r DROP CONSTRAINT %r",
					$data['table'],
					$default_constraint['name']
				);
			}
		} elseif ($data['type'] == 'set_not_null') {
			$sql = $this->escape(
				"ALTER TABLE %r ALTER COLUMN %r " . $data_type . " NOT NULL",
				$data['table'],
				$data['column_name']
			);
			if (isset($data['default'])) {
				$default_constraint = $this->getMSSQLDefaultConstraint(
					$data['schema'],
					$data['table_without_schema'],
					$data['column_name']
				);
				if ($default_constraint) {
					$extra_statements[] = $this->escape(
						"ALTER TABLE %r DROP CONSTRAINT %r",
						$data['table'],
						$default_constraint['name']
					);
				}
				$extra_statements[] = $this->escape("ALTER TABLE %r ADD DEFAULT ", $data['table']) .
					$data['default'] .
					$this->escape(" FOR %r", $data['column_name']);
			}
		} elseif ($data['type'] == 'drop_not_null') {
			$sql = $this->escape(
				"ALTER TABLE %r ALTER COLUMN %r " . $data_type . " NULL",
				$data['table'],
				$data['column_name']
			);
		} elseif ($data['type'] == 'drop_check_constraint') {
			$check_constraint = $this->getMSSQLCheckConstraint(
				$data['schema'],
				$data['table_without_schema'],
				$data['column_name']
			);
			if (!$check_constraint) {
				$this->throwException(
					self::compose(
						'The column "%1$s" in the table "%2$s" does not have a check constraint',
						$data['column_name'],
						$data['table']
					),
					$sql
				);
			}
			$sql = $this->escape(
				"ALTER TABLE %r DROP CONSTRAINT %r",
				$data['table'],
				$check_constraint['name']
			);
		} elseif ($data['type'] == 'set_check_constraint') {
			$check_constraint = $this->getMSSQLCheckConstraint(
				$data['schema'],
				$data['table_without_schema'],
				$data['column_name']
			);
			if ($check_constraint) {
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r DROP CONSTRAINT %r",
					$data['table'],
					$check_constraint['name']
				);
			}
			$sql = $this->escape(
				"ALTER TABLE %r ADD CONSTRAINT %r",
				$data['table'],
				$this->generateConstraintName($sql, 'ck')
			) . $data['constraint'];

			$extra_statements[] = $sql;
			$sql = array_shift($extra_statements);
		} elseif ($data['type'] == 'drop_primary_key') {
			$primary_key_constraint = $this->getMSSQLPrimaryKeyConstraint(
				$data['schema'],
				$data['table_without_schema']
			);
			if (!$primary_key_constraint) {
				$this->throwException(
					self::compose(
						'The table "%1$s" does not have a primary key constraint',
						$data['table']
					),
					$sql
				);
			}

			$foreign_key_constraints = $this->getMSSQLForeignKeyConstraints(
				$data['schema'],
				$data['table_without_schema'],
				$primary_key_constraint['columns']
			);
			foreach ($foreign_key_constraints as $foreign_key_constraint) {
				// Don't drop the constraints on the primary key columns themselves since that isn't necessary
				$same_schema = $foreign_key_constraint['table_without_schema'] == $data['table_without_schema'];
				$same_table  = $foreign_key_constraint['schema'] == $data['schema'];
				if ($same_schema && $same_table && in_array($foreign_key_constraint['column'], $primary_key_constraint['columns'])) {
					continue;
				}
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r DROP CONSTRAINT %r",
					$foreign_key_constraint['table'],
					$foreign_key_constraint['name']
				);
			}

			$sql = $this->escape(
				"ALTER TABLE %r DROP CONSTRAINT %r",
				$data['table'],
				$primary_key_constraint['name']
			);

			$extra_statements[] = $sql;
			$sql = array_shift($extra_statements);

			if ($primary_key_constraint['autoincrement']) {
				$primary_key_column = (string) reset($primary_key_constraint['columns']);
				$unique_constraints = $this->getMSSQLUniqueConstraints(
					$data['schema'],
					$data['table_without_schema'],
					$primary_key_column
				);

				foreach ($unique_constraints as $constraint_name => $columns) {
					$extra_statements[] = $this->escape(
						"ALTER TABLE %r DROP CONSTRAINT %r",
						$data['table'],
						$constraint_name
					);
				}
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r ADD %r INTEGER",
					$data['table'],
					'fl_tmp_identity_col'
				);
				$extra_statements[] = $this->escape(
					"UPDATE %r SET %r = %r",
					$data['table'],
					'fl_tmp_identity_col',
					$primary_key_column
				);
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r ALTER COLUMN %r INTEGER NOT NULL",
					$data['table'],
					'fl_tmp_identity_col'
				);
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r DROP COLUMN %r",
					$data['table'],
					$primary_key_column
				);
				$extra_statements[] = $this->escape(
					'EXEC sp_rename "' . $data['table'] . '.fl_tmp_identity_col", %r',
					$primary_key_column
				);
				foreach ($unique_constraints as $constraint_name => $columns) {
					$extra_statements[] = $this->escape(
						"ALTER TABLE %r ADD UNIQUE (%r)",
						$data['table'],
						$columns
					);
				}
			}
		} elseif ($data['type'] == 'add_primary_key') {
			if ($data['autoincrement']) {
				$column_name = $data['column_name'] ?? '';
				$unique_constraints = $this->getMSSQLUniqueConstraints(
					$data['schema'],
					$data['table_without_schema'],
					$column_name
				);
				foreach ($unique_constraints as $constraint_name => $columns) {
					$extra_statements[] = $this->escape(
						"ALTER TABLE %r DROP CONSTRAINT %r",
						$data['table'],
						$constraint_name
					);
				}
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r ADD %r INTEGER IDENTITY",
					$data['table'],
					'fl_tmp_identity_col'
				);
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r DROP COLUMN %r",
					$data['table'],
					$column_name
				);
				$extra_statements[] = $this->escape(
					'EXEC sp_rename "' . $data['table'] . '.fl_tmp_identity_col", %r',
					$column_name
				);
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r ADD PRIMARY KEY (%r)",
					$data['table'],
					$column_name
				);
				foreach ($unique_constraints as $constraint_name => $columns) {
					$extra_statements[] = $this->escape(
						"ALTER TABLE %r ADD UNIQUE (%r)",
						$data['table'],
						$columns
					);
				}

				$sql = array_shift($extra_statements);
			}
		} elseif ($data['type'] == 'drop_foreign_key') {
			$constraint = $this->query(
				"SELECT
					kcu.constraint_name AS constraint_name
				FROM
					information_schema.table_constraints AS c INNER JOIN
					information_schema.key_column_usage AS kcu ON
						c.table_name = kcu.table_name AND
						c.constraint_name = kcu.constraint_name
				WHERE
					c.constraint_type = 'FOREIGN KEY' AND
					LOWER(c.table_name) = %s AND
					LOWER(c.table_schema) = %s AND
					LOWER(kcu.column_name) = %s AND
					c.table_catalog = DB_NAME()",
				$data['table_without_schema'],
				$data['schema'],
				$data['column_name']
			);
			if (!$constraint->countReturnedRows()) {
				$this->throwException(
					self::compose(
						'The column "%1$s" in the table "%2$s" does not have a foreign key constraint',
						$data['column_name'],
						$data['table']
					),
					$sql
				);
			}
			$sql = $this->escape(
				"ALTER TABLE %r DROP CONSTRAINT %r",
				$data['table'],
				$constraint->fetchScalar()
			);
		} elseif ($data['type'] == 'add_foreign_key') {
			// MSSQL handles the normalized syntax
		} elseif ($data['type'] == 'drop_unique') {
			$constraint_rows = $this->fetchAllRowArrays($this->query(
				"SELECT
					c.constraint_name,
					LOWER(kcu.column_name) AS \"column\"
				FROM
					information_schema.table_constraints AS c INNER JOIN
					information_schema.key_column_usage AS kcu ON
						c.table_name = kcu.table_name AND
						c.constraint_name = kcu.constraint_name
				WHERE
					c.constraint_type = 'UNIQUE' AND
					LOWER(c.table_name) = %s AND
					LOWER(c.table_schema) = %s AND
					c.table_catalog = DB_NAME()",
				$data['table_without_schema'],
				$data['schema']
			));
			$constraints = array();
			foreach ($constraint_rows as $row) {
				$constraint_name = $this->stringValue($row['constraint_name']);
				if (!isset($constraints[$constraint_name])) {
					$constraints[$constraint_name] = array();
				}
				$constraints[$constraint_name][] = $row['column'];
			}
			$constraint_name = null;
			sort($data['column_names']);
			foreach ($constraints as $name => $columns) {
				sort($columns);
				if ($columns == $data['column_names']) {
					$constraint_name = $name;
					break;
				}
			}
			if (!$constraint_name) {
				if (count($data['column_names']) > 1) {
					$message = self::compose(
						'The columns "%1$s" in the table "%2$s" do not have a unique constraint',
						join('", "', $data['column_names']),
						$data['table']
					);
				} else {
					$message = self::compose(
						'The column "%1$s" in the table "%2$s" does not have a unique constraint',
						reset($data['column_names']),
						$data['table']
					);
				}
				$this->throwException($message, $sql);
			}
			$sql = $this->escape(
				"ALTER TABLE %r DROP CONSTRAINT %r",
				$data['table'],
				$constraint_name
			);
		} elseif ($data['type'] == 'add_unique') {
			// MSSQL handles the normalized syntax
		}


		return $sql;
	}


	/**
	 * Translates Flourish SQL `ALTER TABLE` statements to the appropriate
	 * statements for MySQL
	 *
	 * @param string $sql                   The SQL statements that will be executed against the database
	 * @param array<int, string>  &$extra_statements     Any extra SQL statements required for MySQL
	 * @param array<int, string>  &$rollback_statements  SQL statements to rollback `$sql` and `$extra_statements` if something goes wrong
	 * @param AlterTableData $data                  Data parsed from the `ALTER TABLE` statement
	 * @return string  The modified SQL statement
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	private function translateMySQLAlterTableStatements(string $sql, array &$extra_statements, array &$rollback_statements, array $data): string
	{
		if ($data['type'] == 'rename_table') {
			$sql = $this->escape(
				"RENAME TABLE %r TO %r",
				$data['table'],
				$data['new_table_name']
			);
		}

		$before_statements = array();
		$after_statements  = array();

		if (in_array($data['type'], array('drop_column', 'rename_column', 'alter_type', 'set_not_null', 'drop_not_null', 'column_comment', 'drop_primary_key', 'drop_foreign_key', 'drop_unique', 'add_primary_key', 'drop_check_constraint', 'set_check_constraint'))) {
			// This fetches the original column definition to use with the CHANGE statement
			try {
				$row = $this->fetchRowArray($this->query("SHOW CREATE TABLE %r", $data['table']));
			} catch (SQLException $e) {
				// We catch and throw a new exception so the exception message
				// references the SQL statement passed to this method
				$this->throwException(
					self::compose(
						'The table "%1$s" does not exist',
						$data['table']
					),
					$sql
				);
			}
			$create_sql = $this->stringValue($row['Create Table']);
		} else {
			$create_sql = '';
		}

		$column_match = array();

		if ($data['type'] == 'drop_primary_key') {
			$data['column_names'] = array();
			if (preg_match('/PRIMARY KEY\s+\("(.*?)"\),?\n/U', $create_sql, $match)) {
				$data['column_names'] = explode('","', $match[1]);
			}
			if (count($data['column_names']) == 1) {
				$data['column_name'] = implode('', $data['column_names']);
			}
		}

		if (in_array($data['type'], array('rename_column', 'alter_type', 'set_not_null', 'drop_not_null', 'column_comment', 'add_primary_key', 'drop_primary_key', 'drop_foreign_key', 'drop_check_constraint', 'set_check_constraint', 'drop_unique')) && isset($data['column_name'])) {
			$found = preg_match(
				'#(?<=,|\()\s+(?:"|\`)' . $data['column_name'] . '(?:"|\`)(\s+(?:[a-z]+)(?:\([^)]+\))?(?: unsigned)?(?: zerofill)?(?: character set [^ ]+)?(?: collate [^ ]+)?)( NULL)?( NOT NULL)?( DEFAULT (?:(?:[^, \']*|\'(?:\'\'|[^\']+)*\')))?( auto_increment)?( COMMENT \'(?:\'\'|[^\']+)*\')?( ON UPDATE CURRENT_TIMESTAMP)?\s*(?:,|\s*(?=\)))#mi',
				$create_sql,
				$column_match
			);
			if (!$found) {
				$this->throwException(
					self::compose(
						'The column "%1$s" does not exist in the table "%2$s"',
						$data['column_name'],
						$data['table']
					),
					$sql
				);
			}
			if (isset($column_match[4]) && strtolower($this->stringValue($column_match[4])) == ' default null') {
				$column_match[4] = '';
			}
		}


		if ($data['type'] == 'column_comment') {
			$column_match[6] = ' COMMENT ' . $data['comment'];
			$column_def      = join('', array_slice($column_match, 1));
			$sql = $this->escape(
				"ALTER TABLE %r MODIFY %r ",
				$data['table'],
				$data['column_name']
			) . $column_def;
		} elseif ($data['type'] == 'rename_column') {
			$column_def = join('', array_slice($column_match, 1));
			$sql = $this->escape(
				"ALTER TABLE %r CHANGE %r %r ",
				$data['table'],
				$data['column_name'],
				$data['new_column_name']
			) . $column_def;
		} elseif ($data['type'] == 'add_column') {
			$not_null   = preg_match('#\bNOT\s+NULL(\b|$)#iD', $data['column_definition']);
			$no_default = !preg_match('#\bDEFAULT\s+|\s+AUTOINCREMENT#i', $data['column_definition']);
			if ($not_null && $no_default && $this->query("SELECT COUNT(*) FROM %r", $data['table'])->fetchScalar()) {
				$this->throwException(
					self::compose(
						"It is not possible to add a column with a NOT NULL constraint that does not contain a DEFAULT value and is not an AUTOINCREMENT column for tables with existing rows"
					),
					$sql
				);
			}

			$sql = $this->translateDataTypes($sql);
			$sql = preg_replace('#\binteger(?:\(\d+\))?\s+autoincrement\b#i', 'INTEGER AUTO_INCREMENT', $sql) ?? $sql;

			// Translate check constraints to enums and split out foreign key definitions
			preg_match_all(
				'#^\s*(["`]?\w+["`]?)(\s+(?:[a-z]+)(?:\(\d+\))?(?:\s+unsigned|\s+zerofill)*)((?:\s+character\s+set\s+[^ ]+|\s+collate\s+[^ ]+|\s+NULL|\s+NOT\s+NULL|(\s+DEFAULT\s+(?:[^, \']*|\'(?:\'\'|[^\']+)*\'))|\s+UNIQUE|\s+PRIMARY\s+KEY)*)(\s+CHECK\s*\(\w+\s+IN\s+(\(\s*(?:(?:[^, \']+|\'(?:\'\'|[^\']+)*\')\s*,\s*)*\s*(?:[^, \']+|\'(?:\'\'|[^\']+)*\')\))\))?(\s+REFERENCES\s+["`]?\w+["`]?\s*\(\s*["`]?\w+["`]?\s*\)\s*(?:\s+(?:ON\s+DELETE|ON\s+UPDATE)\s+(?:CASCADE|NO\s+ACTION|RESTRICT|SET\s+NULL|SET\s+DEFAULT))*)?\s*$#iD',
				$data['column_definition'],
				$matches,
				PREG_SET_ORDER
			);

			foreach ($matches as $match) {
				// MySQL has the enum data type, so we switch check constraints to that
				if (!empty($match[5])) {
					$replacement = ' enum' . ($match[6] ?? '');
					$sql = str_replace($match[2], $replacement, $sql);
					$sql = str_replace($match[5], '', $sql);
				}

				// Even InnoDB table types don't allow specify foreign key constraints in the column
				// definition, so we have to create an extra statement
				if (!empty($match[7])) {
					$after_statements[] = $this->escape(
						"ALTER TABLE %r ADD FOREIGN KEY (%r) " . $match[7],
						$data['table'],
						$data['column_name']
					);
					$sql = str_replace($match[7], '', $sql);
				}
			}
		} elseif ($data['type'] == 'drop_column') {
			preg_match_all('/\bUNIQUE\s+KEY\s+"([^"]+)"\s+\("(.*?)"\),?\n/i', $create_sql, $matches, PREG_SET_ORDER);
			foreach ($matches as $match) {
				$columns = explode('","', $match[2]);

				if (in_array($data['column_name'], $columns)) {
					// Set up an array of column names we need to drop the keys for
					if (!isset($data['column_names'])) {
						$data['column_names'] = $columns;
					} else {
						$data['column_names'] = array_merge($data['column_names'], $columns);
					}

					$before_statements[] = $this->escape(
						"ALTER TABLE %r DROP INDEX %r",
						$data['table'],
						$match[1]
					);
				}
			}
		} elseif ($data['type'] == 'alter_type') {
			// We ignore changes from enum to varchar since that will just destroy the check constraint functionality
			if (!(preg_match('#\s*enum\(#i', $this->stringValue($column_match[1] ?? '')) && preg_match('#\s*varchar(\(\d+\))?\s*$#iD', $data['data_type']))) {
				$data['data_type'] = $this->translateDataTypes($data['data_type']);
				$column_match[1]   = ' ' . $data['data_type'];
			}

			$column_def        = join('', array_slice($column_match, 1));
			$sql = $this->escape(
				"ALTER TABLE %r MODIFY %r ",
				$data['table'],
				$data['column_name']
			) . $column_def;
		} elseif ($data['type'] == 'set_default') {
			// MySQL handles the normalized syntax
		} elseif ($data['type'] == 'drop_default') {
			// MySQL handles the normalized syntax
		} elseif ($data['type'] == 'set_not_null') {
			$column_match[2] = '';
			$column_match[3] = ' NOT NULL';
			if (isset($data['default'])) {
				$column_match[4] = ' DEFAULT ' . $data['default'];

			// If the column is being set to NOT NULL we have to drop NULL default values
			} elseif (preg_match('#^\s*DEFAULT\s+NULL\s*$#i', $column_match[4])) {
				$column_match[4] = '';
			}
			$column_def = join('', array_slice($column_match, 1));
			$sql = $this->escape(
				"ALTER TABLE %r MODIFY %r ",
				$data['table'],
				$data['column_name']
			) . $column_def;
		} elseif ($data['type'] == 'drop_not_null') {
			$column_match[2] = '';
			$column_match[3] = '';
			$column_def = join('', array_slice($column_match, 1));
			$sql = $this->escape(
				"ALTER TABLE %r MODIFY %r ",
				$data['table'],
				$data['column_name']
			) . $column_def;
		} elseif ($data['type'] == 'set_check_constraint') {
			preg_match("/^\s*CHECK\s*\(\s*\"?\w+\"?\s+IN\s+(\(\s*(?:(?<!')'(?:''|[^']+)*'|%\d+\\\$s)(?:\s*,\s*(?:(?<!')'(?:''|[^']+)*'|%\d+\\\$s))*\s*\))\s*\)\s*$/i", $data['constraint'], $match);
			$valid_values = $match[1] ?? '';

			$column_match[1] = ' ENUM' . $valid_values;

			$column_def = join('', array_slice($column_match, 1));
			$sql = $this->escape(
				"ALTER TABLE %r MODIFY %r ",
				$data['table'],
				$data['column_name']
			) . $column_def;
		} elseif ($data['type'] == 'drop_check_constraint') {
			$found = preg_match_all("/(?<!')'((''|[^']+)*)'/", $this->stringValue($column_match[1] ?? ''), $matches, PREG_PATTERN_ORDER);
			if (!$found) {
				$this->throwException(
					self::compose(
						'The column "%1$s" in the table "%2$s" is not an ENUM column',
						$data['column_name'],
						$data['table']
					),
					$sql
				);
			}

			$valid_values = str_replace("''", "'", $matches[1]);
			$lengths      = array_map(array('UTF8', 'len'), $valid_values);
			$longest      = max($lengths);

			$column_match[1] = ' VARCHAR(' . $longest . ')';

			$column_def = join('', array_slice($column_match, 1));
			$sql = $this->escape(
				"ALTER TABLE %r MODIFY %r ",
				$data['table'],
				$data['column_name']
			) . $column_def;
		} elseif ($data['type'] == 'drop_primary_key') {
			// MySQL doesn't allow a column to be auto_increment if it is not a primary key
			if (count($data['column_names']) == 1) {
				$column_match[3] = ' NOT NULL';
				if (!empty($column_match[5])) {
					$column_match[5] = '';
				}
				$column_def = join('', array_slice($column_match, 1));
				$before_statements[] = $this->escape(
					"ALTER TABLE %r MODIFY %r ",
					$data['table'],
					$data['column_names'][0]
				) . $column_def;
			}
		} elseif ($data['type'] == 'add_primary_key') {
			if ($data['autoincrement']) {
				$sql = preg_replace('#\s+autoincrement#i', '', $sql) ?? $sql;

				$column_match[5] = ' AUTO_INCREMENT';
				$column_def = join('', array_slice($column_match, 1));
				$after_statements[] = $this->escape(
					"ALTER TABLE %r MODIFY %r " . $column_def,
					$data['table'],
					$data['column_name'] ?? ''
				);
			}
		} elseif ($data['type'] == 'drop_foreign_key') {
			$found = preg_match(
				'#CONSTRAINT\s+"(\w+)"\s+FOREIGN KEY \("' . preg_quote($data['column_name'], '#') . '"\) REFERENCES "([^"]+)" \("([^"]+)"\)(?:\sON\sDELETE\s(SET\sNULL|SET\sDEFAULT|CASCADE|NO\sACTION|RESTRICT))?(?:\sON\sUPDATE\s(SET\sNULL|SET\sDEFAULT|CASCADE|NO\sACTION|RESTRICT))?#',
				$create_sql,
				$match
			);
			if (!$found) {
				$this->throwException(
					self::compose(
						'The column "%1$s" in the table "%2$s" does not have a foreign key constraint',
						$data['column_name'],
						$data['table']
					),
					$sql
				);
			}

			$sql = $this->escape(
				"ALTER TABLE %r DROP FOREIGN KEY %r",
				$data['table'],
				$match[1]
			);
		} elseif ($data['type'] == 'add_foreign_key') {
			// MySQL has terrible error messages for non-existent foreign tables and columns
			try {
				$row = $this->fetchRowArray($this->query("SHOW CREATE TABLE %r", $data['foreign_table']));
			} catch (SQLException $e) {
				// We catch and throw a new exception so the exception message
				// references the SQL statement passed to this method
				$this->throwException(
					self::compose(
						'The referenced table "%1$s" does not exist',
						$data['foreign_table']
					),
					$sql
				);
			}
			$foreign_create_sql = $this->stringValue($row['Create Table']);
			$found = preg_match(
				'#(?<=,|\()\s+(?:"|\`)' . $data['foreign_column'] . '(?:"|\`)(\s+(?:[a-z]+)(?:\([^)]+\))?(?: unsigned)?(?: zerofill)?(?: character set [^ ]+)?(?: collate [^ ]+)?)( NULL)?( NOT NULL)?( DEFAULT (?:(?:[^, \']*|\'(?:\'\'|[^\']+)*\')))?( auto_increment)?( COMMENT \'(?:\'\'|[^\']+)*\')?( ON UPDATE CURRENT_TIMESTAMP)?\s*(?:,|\s*(?=\)))#mi',
				$foreign_create_sql,
				$column_match
			);
			if (!$found) {
				$this->throwException(
					self::compose(
						'The referenced column "%1$s" does not exist in the referenced table "%2$s"',
						$data['foreign_column'],
						$data['foreign_table']
					),
					$sql
				);
			}
		} elseif ($data['type'] == 'drop_unique') {
			preg_match_all('/\bUNIQUE\s+KEY\s+"([^"]+)"\s+\("(.*?)"\),?\n/i', $create_sql, $matches, PREG_SET_ORDER);

			$matched = false;
			foreach ($matches as $match) {
				$columns = explode('","', $match[2]);
				sort($columns);
				sort($data['column_names']);

				if ($columns != $data['column_names']) {
					continue;
				}
				$matched = true;

				$sql = $this->escape(
					"ALTER TABLE %r DROP INDEX %r",
					$data['table'],
					$match[1]
				);
			}

			if (!$matched) {
				if (count($data['column_names']) > 1) {
					$message = self::compose(
						'The columns "%1$s" in the table "%2$s" do not have a unique constraint',
						join('", "', $data['column_names']),
						$data['table']
					);
				} else {
					$message = self::compose(
						'The column "%1$s" in the table "%2$s" does not have a unique constraint',
						reset($data['column_names']),
						$data['table']
					);
				}
				$this->throwException($message, $sql);
			}
		} elseif ($data['type'] == 'add_unique') {
			// MySQL handles the normalized syntax
		}


		$foreign_keys          = array();
		$recreate_foreign_keys = false;
		if (in_array($data['type'], array('drop_column', 'rename_column', 'alter_type', 'set_not_null', 'drop_not_null', 'drop_primary_key', 'drop_unique'))) {
			$foreign_keys = $this->getMySQLForeignKeys($data['table'], isset($data['column_names']) ? $data['column_names'] : ($data['column_name'] ?? ''));

			$new_foreign_keys = array();
			foreach ($foreign_keys as $foreign_key) {
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r DROP FOREIGN KEY %r",
					$foreign_key['table'],
					$foreign_key['constraint_name']
				);

				$rollback_statements[] = $this->escape(
					"ALTER TABLE %r ADD FOREIGN KEY (%r) REFERENCES %r(%r) ON DELETE " . $foreign_key['on_delete'] . " ON UPDATE " . $foreign_key['on_update'],
					$foreign_key['table'],
					$foreign_key['column'],
					$foreign_key['foreign_table'],
					$foreign_key['foreign_column']
				);

				if ($data['type'] == 'rename_column') {
					if ($foreign_key['foreign_table'] == $data['table']) {
						$foreign_key['foreign_column'] = $data['new_column_name'];
					} elseif ($foreign_key['table'] == $data['table']) {
						$foreign_key['column'] = $data['new_column_name'];
					}
				}
				$new_foreign_keys[] = $foreign_key;
			}
			$foreign_keys = $new_foreign_keys;
			$recreate_foreign_keys = true;
		}

		// Put the original SQL into the middle of the extra statements to ensure
		// the column change is not made until all of the foreign keys have been dropped
		$extra_statements = array_merge($extra_statements, $before_statements);
		$extra_statements[] = $sql;
		$extra_statements = array_merge($extra_statements, $after_statements);
		$sql = array_shift($extra_statements);

		if ($recreate_foreign_keys) {
			foreach ($foreign_keys as $foreign_key) {
				// Once a primary key is dropped, it can no longer be references by foreign
				// keys since the values can't be guaranteed to be unique
				if ($data['type'] == 'drop_primary_key' && $foreign_key['foreign_table'] == $data['table']) {
					continue;
				}

				// For a dropped column we want to recreate the foreign keys that
				// reference other columns in unique contraints, but we need
				// to skip those referncing the column specifically
				if ($data['type'] == 'drop_column') {
					$referenced_column = $foreign_key['foreign_table'] == $data['table'] && $foreign_key['foreign_column'] == ($data['column_name'] ?? '');
					$column_itself     = $foreign_key['table'] == $data['table'] && $foreign_key['column'] == ($data['column_name'] ?? '');
					if ($referenced_column || $column_itself) {
						continue;
					}
				}

				$extra_statements[] = $this->escape(
					"ALTER TABLE %r ADD FOREIGN KEY (%r) REFERENCES %r(%r) ON DELETE " . $foreign_key['on_delete'] . " ON UPDATE " . $foreign_key['on_update'],
					$foreign_key['table'],
					$foreign_key['column'],
					$foreign_key['foreign_table'],
					$foreign_key['foreign_column']
				);
			}
		}

		return $sql;
	}


	/**
	 * Translates Flourish SQL `ALTER TABLE` statements to the appropriate
	 * statements for Oracle
	 *
	 * @param string $sql                The SQL statements that will be executed against the database
	 * @param array<int, string>  &$extra_statements  Any extra SQL statements required for Oracle
	 * @param AlterTableData $data               Data parsed from the `ALTER TABLE` statement
	 * @return string  The modified SQL statement
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	private function translateOracleAlterTableStatements(string $sql, array &$extra_statements, array $data): string
	{
		$data['schema']               = strtolower($this->database->getUsername());
		$data['table_without_schema'] = $data['table'];
		if (strpos($data['table'], '.') !== false) {
			list ($data['schema'], $data['table_without_schema']) = explode('.', $data['table']);
		}

		if (in_array($data['type'], array('drop_check_constraint', 'drop_primary_key', 'drop_foreign_key', 'drop_unique'))) {
			$column_info = $this->query(
				"SELECT
					ATC.COLUMN_NAME
				FROM
					ALL_TABLES AT LEFT JOIN
					ALL_TAB_COLUMNS ATC ON
						ATC.OWNER = AT.OWNER AND
						ATC.TABLE_NAME = AT.TABLE_NAME AND
						LOWER(ATC.COLUMN_NAME) = %s
				WHERE
					LOWER(AT.OWNER) = %s AND
					LOWER(AT.TABLE_NAME) = %s",
				isset($data['column_name']) ? $data['column_name'] : '',
				$data['schema'],
				$data['table_without_schema']
			);

			if (!$column_info->countReturnedRows()) {
				$this->throwException(
					self::compose(
						'The table "%1$s" does not exist',
						$data['table']
					),
					$sql
				);
			}

			if (isset($data['column_name'])) {
				$row = $this->fetchRowArray($column_info);
				if (!strlen($this->stringValue($row['column_name']))) {
					$this->throwException(
						self::compose(
							'The column "%1$s" does not exist in the table "%2$s"',
							$data['column_name'],
							$data['table']
						),
						$sql
					);
				}
			}
		}

		$constraint_name = null;
		$not_null_result = null;
		$not_null        = false;

		if ($data['type'] == 'drop_check_constraint' || $data['type'] == 'set_check_constraint') {
			$constraints = $this->fetchAllRowArrays($this->query(
				"SELECT
					AC.CONSTRAINT_NAME,
					AC.SEARCH_CONDITION
				FROM
					ALL_TAB_COLUMNS ATC INNER JOIN
					ALL_CONS_COLUMNS ACC ON
						ATC.OWNER = ACC.OWNER AND
						ATC.COLUMN_NAME = ACC.COLUMN_NAME AND
						ATC.TABLE_NAME = ACC.TABLE_NAME AND
						ACC.POSITION IS NULL INNER JOIN
					ALL_CONSTRAINTS AC ON
						AC.OWNER = ACC.OWNER AND
						AC.CONSTRAINT_NAME = ACC.CONSTRAINT_NAME AND
						AC.CONSTRAINT_TYPE = 'C' AND
						AC.STATUS = 'ENABLED'
				WHERE
					LOWER(ATC.OWNER) = %s AND
					LOWER(ATC.TABLE_NAME) = %s AND
					LOWER(ATC.COLUMN_NAME) = %s",
				$data['schema'],
				$data['table_without_schema'],
				$data['column_name']
			));

			$constraint_name = null;
			foreach ($constraints as $row) {
				if (preg_match("#^\s*\"?\w+\"?\s+IN\s+(\(\s*(?:(?<!')'(?:''|[^']+)*'|%\d+\\\$s)(?:\s*,\s*(?:(?<!')'(?:''|[^']+)*'|%\d+\\\$s))*\s*\))\s*$#i", $this->stringValue($row['search_condition']))) {
					$constraint_name = $this->stringValue($row['constraint_name']);
					break;
				} elseif (preg_match('/^\s*"?' . preg_quote($data['column_name'], '/') . '"?\s*=\s*\'((\'\'|[^\']+)*)\'(\s+OR\s+"?' . preg_quote($data['column_name'], '/') . '"?\s*=\s*\'((\'\'|[^\']+)*)\')*\s*$/i', $this->stringValue($row['search_condition']))) {
					$constraint_name = $this->stringValue($row['constraint_name']);
					break;
				}
			}
		}

		if ($data['type'] == 'set_not_null' || $data['type'] == 'drop_not_null') {
			$not_null_result = $this->query(
				"SELECT
					ATC.NULLABLE
				FROM
					ALL_TAB_COLUMNS ATC
				WHERE
					LOWER(ATC.OWNER) = %s AND
					LOWER(ATC.TABLE_NAME) = %s AND
					LOWER(ATC.COLUMN_NAME) = %s",
				$data['schema'],
				$data['table_without_schema'],
				$data['column_name']
			);

			$not_null = $not_null_result->countReturnedRows() && $not_null_result->fetchScalar() == 'N';
		}

		if ($data['type'] == 'set_not_null' && isset($data['default']) && $data['default'] == "''") {
			$data['type'] = 'drop_not_null';
			$data['default'] = ' NULL';
		}

		if ($data['type'] == 'column_comment') {
			// Oracle handles the normalized syntax
		} elseif ($data['type'] == 'rename_table') {
			// Oracle handles the normalized syntax
		} elseif ($data['type'] == 'rename_column') {
			// When renaming a primary key column we need to check
			// for triggers that implement autoincrement
			$res = $this->fetchAllRowArrays($this->query(
				"SELECT
					TRIGGER_NAME,
					TRIGGER_BODY
				FROM
					ALL_TRIGGERS
				WHERE
					TRIGGERING_EVENT LIKE 'INSERT%' AND
					STATUS = 'ENABLED' AND
					TRIGGER_NAME NOT LIKE 'BIN\$%' AND
					LOWER(TABLE_NAME) = %s AND
					LOWER(OWNER) = %s",
				$data['table_without_schema'],
				$data['schema']
			));

			foreach ($res as $row) {
				if (!preg_match('#\s+:new\."?' . preg_quote($data['column_name'], '#') . '"?\s+FROM\s+DUAL#i', $this->stringValue($row['trigger_body']))) {
					continue;
				}
				$trigger  = 'CREATE OR REPLACE TRIGGER ' . $this->stringValue($row['trigger_name']) . "\n";
				$trigger .= "BEFORE INSERT ON " . $data['table_without_schema'] . "\n";
				$trigger .= "FOR EACH ROW\n";
				$trigger .= preg_replace(
					'#( :new\.)' . preg_quote($data['column_name'], '#') . '( )#i',
					'\1' . $data['new_column_name'] . '\2',
					$this->stringValue($row['trigger_body'])
				);

				$extra_statements[] = $trigger;
			}
		} elseif ($data['type'] == 'add_column') {
			// If NOT NULL DEFAULT '' is present, both are removed since Oracle converts '' to NULL
			$data['column_definition'] = preg_replace('#(\bNOT\s+NULL\s+DEFAULT\s+\'\'|\bDEFAULT\s+\'\'\s+NOT\s+NULL)#', '', $data['column_definition']) ?? $data['column_definition'];

			// Oracle does not support ON UPDATE clauses
			$data['column_definition'] = preg_replace('#(\sON\s+UPDATE\s+(CASCADE|SET\s+NULL|NO\s+ACTION|RESTRICT))#i', '', $data['column_definition']) ?? $data['column_definition'];

			// Oracle requires NOT NULL to come after the DEFAULT value or UNIQUE constraint
			$data['column_definition'] = preg_replace('#(\s+NOT\s+NULL)((?:\s+UNIQUE|\s+DEFAULT\s+(?:[^, \'\n]*|\'(?:\'\'|[^\']+)*\'))+)#i', '\2\1', $data['column_definition']) ?? $data['column_definition'];

			$sql = $this->escape(
				'ALTER TABLE %r ADD ',
				$data['table']
			) . $data['column_definition'];

			$sql = $this->translateDataTypes($sql);

			// Create sequences and triggers for Oracle
			if (stripos($sql, 'autoincrement') !== false && preg_match('#^\s*(?:[a-z]+)(?:\((?:\d+)\))?.*?\bAUTOINCREMENT\b.*$#iD', $sql, $matches)) {
				// If we are creating a new autoincrementing primary key on an existing
				// table we have to take some extra steps to pre-populate the
				// auto-incrementing column, otherwise Oracle will error out
				if (preg_match('#\bPRIMARY\s+KEY\b#i', $sql)) {
					$sql = preg_replace('# PRIMARY\s+KEY\b#i', '', $sql) ?? $sql;
					$extra_statements[] = $this->escape(
						"UPDATE %r SET %r = ROWNUM",
						$data['table'],
						$data['column_name']
					);

					$extra_statements[] = $this->escape(
						"ALTER TABLE %r ADD CONSTRAINT %r PRIMARY KEY (%r)",
						$data['table'],
						$this->generateConstraintName($sql, 'pk'),
						$data['column_name']
					);
				}

				$table_column  = substr(str_replace('"', '', $data['table']) . '_' . str_replace('"', '', $data['column_name']), 0, 26);

				$sequence_name = $table_column . '_seq';
				$trigger_name  = $table_column . '_trg';

				$sequence = "DECLARE
					create_seq_sql VARCHAR2(200);
    			BEGIN
					SELECT
						'CREATE SEQUENCE " . $sequence_name . " START WITH ' || (SQ.TOTAL_ROWS + 1)
					INTO
						create_seq_sql
					FROM
						(SELECT COUNT(*) TOTAL_ROWS FROM " . $data['table'] . ") SQ \;
					EXECUTE IMMEDIATE create_seq_sql\;
				END\;";

				$trigger  = 'CREATE OR REPLACE TRIGGER ' . $trigger_name . "\n";
				$trigger .= "BEFORE INSERT ON " . $data['table'] . "\n";
				$trigger .= "FOR EACH ROW\n";
				$trigger .= "BEGIN\n";
				$trigger .= "  IF :new." . $data['column_name'] . " IS NULL THEN\n";
				$trigger .= "	SELECT " . $sequence_name . ".nextval INTO :new." . $data['column_name'] . " FROM dual;\n";
				$trigger .= "  END IF;\n";
				$trigger .= "END;";

				$extra_statements[] = $sequence;
				$extra_statements[] = $trigger;

				$sql = preg_replace('#\s+autoincrement\b#i', '', $sql) ?? $sql;
			}
		} elseif ($data['type'] == 'drop_column') {
			$sql .= ' CASCADE CONSTRAINTS';

			// Drop any triggers and sequences that implement autoincrement for the column
			$res = $this->fetchAllRowArrays($this->query(
				"SELECT
					TRIGGER_NAME,
					TRIGGER_BODY
				FROM
					ALL_TRIGGERS
				WHERE
					TRIGGERING_EVENT LIKE 'INSERT%' AND
					STATUS = 'ENABLED' AND
					TRIGGER_NAME NOT LIKE 'BIN\$%' AND
					LOWER(TABLE_NAME) = %s AND
					LOWER(OWNER) = %s",
				$data['table_without_schema'],
				$data['schema']
			));

			foreach ($res as $row) {
				if (!preg_match('#SELECT\s+"?(\w+)"?\.nextval\s+INTO\s+:new\."?' . preg_quote($data['column_name'], '#') . '"?\s+FROM\s+dual#i', $this->stringValue($row['trigger_body']), $match)) {
					continue;
				}

				$extra_statements[] = $this->escape("DROP SEQUENCE %r", $match[1]);
				$extra_statements[] = $this->escape("DROP TRIGGER %r", $row['trigger_name']);
			}
		} elseif ($data['type'] == 'alter_type') {
			$data['data_type'] = $this->translateDataTypes($data['data_type']);

			$sql = $this->escape(
				"ALTER TABLE %r MODIFY (%r ",
				$data['table'],
				$data['column_name']
			) . $data['data_type'] . ')';
		} elseif ($data['type'] == 'set_default') {
			$sql = $this->escape(
				"ALTER TABLE %r MODIFY (%r DEFAULT ",
				$data['table'],
				$data['column_name']
			) . $data['default_value'] . ')';
		} elseif ($data['type'] == 'drop_default') {
			$sql = $this->escape(
				"ALTER TABLE %r MODIFY (%r DEFAULT NULL)",
				$data['table'],
				$data['column_name']
			);
		} elseif ($data['type'] == 'set_not_null') {
			// If it is already NOT NULL, don't set it again since it will cause an error
			if ($not_null_result && $not_null_result->countReturnedRows() && $not_null) {
				if (isset($data['default'])) {
					$sql = $this->escape(
						"ALTER TABLE %r MODIFY (%r DEFAULT ",
						$data['table'],
						$data['column_name']
					) . $data['default'] . ')';
				} else {
					$sql = "SELECT 'noop - no NOT NULL to set' FROM dual";
				}
			} else {
				$sql = $this->escape(
					"ALTER TABLE %r MODIFY (%r",
					$data['table'],
					$data['column_name']
				);
				if (isset($data['default'])) {
					$sql .= ' DEFAULT ' . $data['default'];
				}
				$sql .= ' NOT NULL)';
			}
		} elseif ($data['type'] == 'drop_not_null') {
			if (!$not_null_result || !$not_null_result->countReturnedRows() || $not_null) {
				$sql = $this->escape(
					"ALTER TABLE %r MODIFY (%r",
					$data['table'],
					$data['column_name']
				);
				if (isset($data['default'])) {
					$sql .= ' DEFAULT ' . $data['default'];
				}
				$sql .= ' NULL)';

			// If it is already NULL, don't set it again since it will cause an error
			} else {
				if (isset($data['default'])) {
					$sql = $this->escape(
						"ALTER TABLE %r MODIFY (%r DEFAULT",
						$data['table'],
						$data['column_name']
					) . $data['default'] . ')';
				} else {
					$sql = "SELECT 'noop - no NOT NULL to drop' FROM dual";
				}
			}
		} elseif ($data['type'] == 'drop_check_constraint') {
			if (!$constraint_name) {
				$this->throwException(
					self::compose(
						'The column "%1$s" in the table "%2$s" does not have a check constraint',
						$data['column_name'],
						$data['table']
					),
					$sql
				);
			}
			$sql = $this->escape(
				"ALTER TABLE %r DROP CONSTRAINT %r",
				$data['table'],
				$constraint_name
			);
		} elseif ($data['type'] == 'set_check_constraint') {
			if ($constraint_name) {
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r DROP CONSTRAINT %r",
					$data['table'],
					$constraint_name
				);
			}
			$sql = $this->escape(
				"ALTER TABLE %r ADD CONSTRAINT %r",
				$data['table'],
				$this->generateConstraintName($sql, 'ck')
			) . $data['constraint'];

			$extra_statements[] = $sql;
			$sql = array_shift($extra_statements);
		} elseif ($data['type'] == 'drop_primary_key') {
			$constraint_columns = $this->fetchAllRowArrays($this->query(
				"SELECT
					AC.CONSTRAINT_NAME CONSTRAINT_NAME,
					LOWER(ACC.COLUMN_NAME) \"COLUMN\",
					ANC.SEARCH_CONDITION
				FROM
					ALL_CONSTRAINTS AC INNER JOIN
					ALL_CONS_COLUMNS ACC ON
						AC.CONSTRAINT_NAME = ACC.CONSTRAINT_NAME AND
						AC.OWNER = ACC.OWNER LEFT JOIN
					ALL_CONS_COLUMNS ANCC ON
						ACC.OWNER = ANCC.OWNER AND
						ACC.TABLE_NAME = ANCC.TABLE_NAME AND
						ACC.COLUMN_NAME = ANCC.COLUMN_NAME LEFT JOIN
					ALL_CONSTRAINTS ANC ON
						ANC.CONSTRAINT_NAME = ANCC.CONSTRAINT_NAME AND
						ANC.OWNER = ANCC.OWNER AND
						ANC.CONSTRAINT_TYPE = 'C' AND
						ANC.STATUS = 'ENABLED'
				WHERE
					AC.CONSTRAINT_TYPE = 'P' AND
					LOWER(AC.OWNER) = %s AND
					LOWER(AC.TABLE_NAME) = %s",
				$data['schema'],
				$data['table_without_schema']
			));

			$constraint_name     = null;
			$primary_key_columns = array();
			$nullable            = false;
			foreach ($constraint_columns as $row) {
				$constraint_name       = $this->stringValue($row['constraint_name']);
				$primary_key_columns[] = $this->stringValue($row['column']);
				$nullable              = strtolower($this->stringValue($row['search_condition'])) != '"' . $this->stringValue($row['column']) . '" is not null';
			}

			if (!$constraint_name) {
				$this->throwException(
					self::compose(
						'The table "%1$s" does not have a primary key constraint',
						$data['table']
					),
					$sql
				);
			}

			$sql = $this->escape(
				"ALTER TABLE %r DROP CONSTRAINT %r CASCADE",
				$data['table'],
				$constraint_name
			);

			// When dropping a primary key we want to drop any trigger
			// and sequence used to implement autoincrement
			$res = $this->fetchAllRowArrays($this->query(
				"SELECT
					TRIGGER_NAME,
					TRIGGER_BODY
				FROM
					ALL_TRIGGERS
				WHERE
					TRIGGERING_EVENT LIKE 'INSERT%' AND
					STATUS = 'ENABLED' AND
					TRIGGER_NAME NOT LIKE 'BIN\$%' AND
					LOWER(TABLE_NAME) = %s AND
					LOWER(OWNER) = %s",
				$data['table_without_schema'],
				$data['schema']
			));

			foreach ($res as $row) {
				if (!preg_match('#SELECT\s+"?(\w+)"?\.nextval\s+INTO\s+:new\.("?\w+"?)\s+FROM\s+DUAL#i', $this->stringValue($row['trigger_body']), $match)) {
					continue;
				}
				$extra_statements[] = $this->escape(
					"DROP TRIGGER %r",
					$data['schema'] . '.' . $this->stringValue($row['trigger_name'])
				);
				$extra_statements[] = $this->escape(
					"DROP SEQUENCE %r",
					$data['schema'] . '.' . $match[1]
				);
			}

			if (count($primary_key_columns) == 1 && $nullable) {
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r MODIFY (%r NOT NULL)",
					$data['table'],
					reset($primary_key_columns)
				);
			}
		} elseif ($data['type'] == 'add_primary_key') {
			$sql = $this->escape(
				"ALTER TABLE %r ADD CONSTRAINT %r PRIMARY KEY (%r)",
				$data['table'],
				$this->generateConstraintName($sql, 'pk'),
				$data['column_names']
			);

			if ($data['autoincrement']) {
				$extra_statements[] = $sql;

				$sql = $this->escape(
					"UPDATE %r SET %r = ROWNUM",
					$data['table'],
					$data['column_name'] ?? ''
				);

				$table_column  = substr(str_replace('"', '', $data['table']) . '_' . str_replace('"', '', $data['column_name'] ?? ''), 0, 26);

				$sequence_name = $table_column . '_seq';
				$trigger_name  = $table_column . '_trg';

				$sequence = "DECLARE
					create_seq_sql VARCHAR2(200);
    			BEGIN
					SELECT
						'CREATE SEQUENCE " . $sequence_name . " START WITH ' || (SQ.TOTAL_ROWS + 1)
					INTO
						create_seq_sql
					FROM
						(SELECT COUNT(*) TOTAL_ROWS FROM " . $data['table'] . ") SQ \;
					EXECUTE IMMEDIATE create_seq_sql\;
				END\;";

				$trigger  = 'CREATE OR REPLACE TRIGGER ' . $trigger_name . "\n";
				$trigger .= "BEFORE INSERT ON " . $data['table'] . "\n";
				$trigger .= "FOR EACH ROW\n";
				$trigger .= "BEGIN\n";
				$trigger .= "  IF :new." . ($data['column_name'] ?? '') . " IS NULL THEN\n";
				$trigger .= "	SELECT " . $sequence_name . ".nextval INTO :new." . ($data['column_name'] ?? '') . " FROM dual;\n";
				$trigger .= "  END IF;\n";
				$trigger .= "END;";

				$extra_statements[] = $sequence;
				$extra_statements[] = $trigger;
			}
		} elseif ($data['type'] == 'drop_foreign_key') {
			$constraint = $this->query(
				"SELECT
					AC.CONSTRAINT_NAME CONSTRAINT_NAME
				FROM
					ALL_CONSTRAINTS AC INNER JOIN
					ALL_CONS_COLUMNS ACC ON
						AC.CONSTRAINT_NAME = ACC.CONSTRAINT_NAME AND
						AC.OWNER = ACC.OWNER
				WHERE
					AC.CONSTRAINT_TYPE = 'R' AND
					LOWER(AC.OWNER) = %s AND
					LOWER(AC.TABLE_NAME) = %s AND
					LOWER(ACC.COLUMN_NAME) = %s",
				$data['schema'],
				$data['table_without_schema'],
				$data['column_name']
			);
			if (!$constraint->countReturnedRows()) {
				$this->throwException(
					self::compose(
						'The column "%1$s" in the table "%2$s" does not have a foreign key constraint',
						$data['column_name'],
						$data['table']
					),
					$sql
				);
			}
			$sql = $this->escape(
				"ALTER TABLE %r DROP CONSTRAINT %r",
				$data['table'],
				$constraint->fetchScalar()
			);
		} elseif ($data['type'] == 'add_foreign_key') {
			// Oracle does not support ON UPDATE clauses
			$data['references'] = preg_replace('#(\sON\s+UPDATE\s+(CASCADE|SET\s+NULL|NO\s+ACTION|RESTRICT))#i', '', $data['references']);

			$sql = $this->escape(
				"ALTER TABLE %r ADD CONSTRAINT %r FOREIGN KEY (%r) REFERENCES " . $data['references'],
				$data['table'],
				$this->generateConstraintName($sql, 'fk'),
				$data['column_name']
			);
		} elseif ($data['type'] == 'drop_unique') {
			$constraint_rows = $this->fetchAllRowArrays($this->query(
				"SELECT
					AC.CONSTRAINT_NAME,
					LOWER(ACC.COLUMN_NAME) \"COLUMN\"
				FROM
					ALL_CONSTRAINTS AC INNER JOIN
					ALL_CONS_COLUMNS ACC ON
						AC.CONSTRAINT_NAME = ACC.CONSTRAINT_NAME AND
						AC.OWNER = ACC.OWNER
				WHERE
					AC.CONSTRAINT_TYPE = 'U' AND
					LOWER(AC.OWNER) = %s AND
					LOWER(AC.TABLE_NAME) = %s",
				$data['schema'],
				$data['table_without_schema']
			));
			$constraints = array();
			foreach ($constraint_rows as $row) {
				$constraint_name = $this->stringValue($row['constraint_name']);
				if (!isset($constraints[$constraint_name])) {
					$constraints[$constraint_name] = array();
				}
				$constraints[$constraint_name][] = $row['column'];
			}
			$constraint_name = null;
			sort($data['column_names']);
			foreach ($constraints as $name => $columns) {
				sort($columns);
				if ($columns == $data['column_names']) {
					$constraint_name = $name;
					break;
				}
			}
			if (!$constraint_name) {
				if (count($data['column_names']) > 1) {
					$message = self::compose(
						'The columns "%1$s" in the table "%2$s" do not have a unique constraint',
						join('", "', $data['column_names']),
						$data['table']
					);
				} else {
					$message = self::compose(
						'The column "%1$s" in the table "%2$s" does not have a unique constraint',
						reset($data['column_names']),
						$data['table']
					);
				}
				$this->throwException($message, $sql);
			}
			$sql = $this->escape(
				"ALTER TABLE %r DROP CONSTRAINT %r CASCADE",
				$data['table'],
				$constraint_name
			);
		} elseif ($data['type'] == 'add_unique') {
			$sql = $this->escape(
				"ALTER TABLE %r ADD CONSTRAINT %r UNIQUE (%r)",
				$data['table'],
				$this->generateConstraintName($sql, 'uc'),
				$data['column_names']
			);
		}

		return $sql;
	}


	/**
	 * Translates Flourish SQL `ALTER TABLE` statements to the appropriate
	 * statements for PostgreSQL
	 *
	 * @param string $sql                The SQL statements that will be executed against the database
	 * @param array<int, string>  &$extra_statements  Any extra SQL statements required for PostgreSQL
	 * @param AlterTableData $data               Data parsed from the `ALTER TABLE` statement
	 * @return string  The modified SQL statement
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	private function translatePostgreSQLAlterTableStatements(string $sql, array &$extra_statements, array $data): string
	{
		$data['schema']               = 'public';
		$data['table_without_schema'] = $data['table'];
		if (strpos($data['table'], '.') !== false) {
			list ($data['schema'], $data['table_without_schema']) = explode('.', $data['table']);
		}

		if (in_array($data['type'], array('drop_check_constraint', 'drop_primary_key', 'drop_foreign_key', 'drop_unique'))) {
			$column_info = $this->query(
				"SELECT
					col.attname AS column_name
				FROM
					pg_namespace AS n INNER JOIN
					pg_class AS t ON
						t.relnamespace = n.oid LEFT JOIN
					pg_attribute AS col ON
						col.attrelid = t.oid AND
						LOWER(col.attname) = %s AND
						col.attisdropped = FALSE
				WHERE
					LOWER(n.nspname) = %s AND
					LOWER(t.relname) = %s",
				isset($data['column_name']) ? $data['column_name'] : '',
				$data['schema'],
				$data['table_without_schema']
			);

			if (!$column_info->countReturnedRows()) {
				$this->throwException(
					self::compose(
						'The table "%1$s" does not exist',
						$data['table']
					),
					$sql
				);
			}

			if (isset($data['column_name'])) {
				$row = $this->fetchRowArray($column_info);
				if (!strlen($this->stringValue($row['column_name']))) {
					$this->throwException(
						self::compose(
							'The column "%1$s" does not exist in the table "%2$s"',
							$data['column_name'],
							$data['table']
						),
						$sql
					);
				}
			}
		}

		if ($data['type'] == 'drop_check_constraint' || $data['type'] == 'set_check_constraint') {
			$constraint = $this->query(
				"SELECT
					con.conname AS constraint_name
				FROM
					pg_attribute AS col INNER JOIN
					pg_class AS t ON col.attrelid = t.oid INNER JOIN
					pg_namespace AS n ON t.relnamespace = n.oid INNER JOIN
					pg_constraint AS con ON
						col.attnum = ANY (con.conkey) AND
						con.conrelid = t.oid
				WHERE
					NOT col.attisdropped AND
					(con.contype = 'c') AND
					LOWER(n.nspname) = %s AND
					LOWER(t.relname) = %s AND
					LOWER(col.attname) = %s",
				$data['schema'],
				$data['table_without_schema'],
				$data['column_name']
			);
			$constraint_name = null;
			if ($constraint->countReturnedRows()) {
				$constraint_name = $constraint->fetchScalar();
			}
		}

		if ($data['type'] == 'column_comment') {
			// PostgreSQL handles the normalized syntax
		} elseif ($data['type'] == 'rename_table') {
			// PostgreSQL handles the normalized syntax
		} elseif ($data['type'] == 'rename_column') {
			// PostgreSQL handles the normalized syntax
		} elseif ($data['type'] == 'add_column') {
			$sql = $this->translateDataTypes($sql);
			$sql = preg_replace('#\binteger(?:\(\d+\))?\s+autoincrement\b#i', 'SERIAL', $sql) ?? $sql;
		} elseif ($data['type'] == 'drop_column') {
			$sql .= ' CASCADE';
		} elseif ($data['type'] == 'alter_type') {
			$sql = $this->translateDataTypes($sql);
		} elseif ($data['type'] == 'set_default') {
			// PostgreSQL handles the normalized syntax
		} elseif ($data['type'] == 'drop_default') {
			// PostgreSQL handles the normalized syntax
		} elseif ($data['type'] == 'set_not_null') {
			if (isset($data['default'])) {
				$sql = $this->escape(
					"ALTER TABLE %r ALTER COLUMN %r SET NOT NULL",
					$data['table'],
					$data['column_name']
				);
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r ALTER COLUMN %r SET DEFAULT ",
					$data['table'],
					$data['column_name']
				) . $data['default'];
			}
		} elseif ($data['type'] == 'drop_not_null') {
			// PostgreSQL handles the normalized syntax
		} elseif ($data['type'] == 'set_check_constraint') {
			if ($constraint_name) {
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r DROP CONSTRAINT %r",
					$data['table'],
					$constraint_name
				);
			}
			$sql = $this->escape(
				"ALTER TABLE %r ADD ",
				$data['table']
			) . $data['constraint'];

			$extra_statements[] = $sql;
			$sql = array_shift($extra_statements);
		} elseif ($data['type'] == 'drop_check_constraint') {
			if (!$constraint_name) {
				$this->throwException(
					self::compose(
						'The column "%1$s" in the table "%2$s" does not have a check constraint',
						$data['column_name'],
						$data['table']
					),
					$sql
				);
			}
			$sql = $this->escape(
				"ALTER TABLE %r DROP CONSTRAINT %r",
				$data['table'],
				$constraint_name
			);
		} elseif ($data['type'] == 'drop_primary_key') {
			$column_info = $this->query(
				"SELECT
					con.conname AS constraint_name,
					col.attname AS column,
					format_type(col.atttypid, col.atttypmod) AS data_type,
					ad.adsrc AS default
				FROM
					pg_attribute AS col INNER JOIN
					pg_class AS t ON col.attrelid = t.oid INNER JOIN
					pg_namespace AS s ON t.relnamespace = s.oid INNER JOIN
					pg_constraint AS con ON
						col.attnum = ANY (con.conkey) AND
						con.conrelid = t.oid LEFT JOIN
					pg_attrdef AS ad ON t.oid = ad.adrelid AND
						col.attnum = ad.adnum
				WHERE
					NOT col.attisdropped AND
					con.contype = 'p' AND
					LOWER(t.relname) = %s AND
					LOWER(s.nspname) = %s",
				$data['table_without_schema'],
				$data['schema']
			);

			if (!$column_info->countReturnedRows()) {
				$this->throwException(
					self::compose(
						'The table "%1$s" does not have a primary key constraint',
						$data['table']
					),
					$sql
				);
			}

			$row = $this->fetchRowArray($column_info);
			$sql = $this->escape(
				"ALTER TABLE %r DROP CONSTRAINT %r CASCADE",
				$data['table'],
				$row['constraint_name']
			);

			// Change serial columns to integers when removing the primary key
			if ($column_info->countReturnedRows() == 1) {
				if (preg_match('#^(int|bigint)#i', $this->stringValue($row['data_type'])) && preg_match('#^\s*nextval\(\'([^\']+)\'#i', $this->stringValue($row['default']), $match)) {
					$extra_statements[] = $this->escape(
						"ALTER TABLE %r ALTER COLUMN %r DROP DEFAULT",
						$data['table'],
						$row['column']
					);
					$extra_statements[] = $this->escape(
						"DROP SEQUENCE %r",
						$match[1]
					);
				}
			}
		} elseif ($data['type'] == 'add_primary_key') {
			if ($data['autoincrement']) {
				$sequence_name = $this->generateConstraintName($sql, 'seq');
				$extra_statements[] = $this->escape(
					"CREATE SEQUENCE %r",
					$sequence_name
				);
				$extra_statements[] = $this->escape(
					"ALTER SEQUENCE %r OWNED BY %r.%r",
					$sequence_name,
					$data['table'],
					$data['column_name'] ?? ''
				);

				$extra_statements[] = $this->escape(
					"SELECT setval(%s, MAX(%r)) FROM %r",
					$sequence_name,
					$data['column_name'] ?? '',
					$data['table']
				);
				$extra_statements[] = $this->escape(
					"ALTER TABLE %r ALTER COLUMN %r SET DEFAULT nextval(%s::regclass)",
					$data['table'],
					$data['column_name'] ?? '',
					$sequence_name
				);

				$sql = preg_replace('#\s+autoincrement#i', '', $sql) ?? $sql;
			}
		} elseif ($data['type'] == 'drop_foreign_key') {
			$constraint = $this->query(
				"SELECT
					con.conname AS constraint_name
				FROM
					pg_attribute AS col INNER JOIN
					pg_class AS t ON col.attrelid = t.oid INNER JOIN
					pg_namespace AS n ON t.relnamespace = n.oid INNER JOIN
					pg_constraint AS con ON
						col.attnum = ANY (con.conkey) AND
						con.conrelid = t.oid
				WHERE
					NOT col.attisdropped AND
					(con.contype = 'f') AND
					LOWER(n.nspname) = %s AND
					LOWER(t.relname) = %s AND
					LOWER(col.attname) = %s",
				$data['schema'],
				$data['table_without_schema'],
				$data['column_name']
			);
			if (!$constraint->countReturnedRows()) {
				$this->throwException(
					self::compose(
						'The column "%1$s" in the table "%2$s" does not have a foreign key constraint',
						$data['column_name'],
						$data['table']
					),
					$sql
				);
			}
			$sql = $this->escape(
				"ALTER TABLE %r DROP CONSTRAINT %r",
				$data['table'],
				$constraint->fetchScalar()
			);
		} elseif ($data['type'] == 'add_foreign_key') {
			// PostgreSQL handles the normalized syntax
		} elseif ($data['type'] == 'drop_unique') {
			$constraint_rows = $this->fetchAllRowArrays($this->query(
				"SELECT
					con.conname AS constraint_name,
					LOWER(col.attname) AS column
				FROM
					pg_attribute AS col INNER JOIN
					pg_class AS t ON col.attrelid = t.oid INNER JOIN
					pg_namespace AS n ON t.relnamespace = n.oid INNER JOIN
					pg_constraint AS con ON
						col.attnum = ANY (con.conkey) AND
						con.conrelid = t.oid
				WHERE
					NOT col.attisdropped AND
					(con.contype = 'u') AND
					LOWER(n.nspname) = %s AND
					LOWER(t.relname) = %s",
				$data['schema'],
				$data['table_without_schema']
			));
			$constraints = array();
			foreach ($constraint_rows as $row) {
				$constraint_name = $this->stringValue($row['constraint_name']);
				if (!isset($constraints[$constraint_name])) {
					$constraints[$constraint_name] = array();
				}
				$constraints[$constraint_name][] = $row['column'];
			}
			$constraint_name = null;
			sort($data['column_names']);
			foreach ($constraints as $name => $columns) {
				sort($columns);
				if ($columns == $data['column_names']) {
					$constraint_name = $name;
					break;
				}
			}
			if (!$constraint_name) {
				if (count($data['column_names']) > 1) {
					$message = self::compose(
						'The columns "%1$s" in the table "%2$s" do not have a unique constraint',
						join('", "', $data['column_names']),
						$data['table']
					);
				} else {
					$message = self::compose(
						'The column "%1$s" in the table "%2$s" does not have a unique constraint',
						reset($data['column_names']),
						$data['table']
					);
				}
				$this->throwException($message, $sql);
			}
			$sql = $this->escape(
				"ALTER TABLE %r DROP CONSTRAINT %r",
				$data['table'],
				$constraint_name
			);
		} elseif ($data['type'] == 'add_unique') {
			// PostgreSQL handles the normalized syntax
		}

		return $sql;
	}


	/**
	 * Translates Flourish SQL `ALTER TABLE` statements to the appropriate
	 * statements for SQLite
	 *
	 * @param string $sql                The SQL statements that will be executed against the database
	 * @param array<int, string>  &$extra_statements  Any extra SQL statements required for SQLite
	 * @param AlterTableData $data               Data parsed from the `ALTER TABLE` statement
	 * @return string  The modified SQL statement
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	private function translateSQLiteAlterTableStatements(string $sql, array &$extra_statements, array $data): string
	{
		$toggle_foreign_key_support = false;
		if (!isset($this->schema_info['foreign_keys_enabled'])) {
			$toggle_foreign_key_support = true;
			$foreign_keys_res = $this->query('PRAGMA foreign_keys');
			if ($foreign_keys_res->countReturnedRows() && $foreign_keys_res->fetchScalar()) {
				$this->schema_info['foreign_keys_enabled'] = true;
				$this->query("PRAGMA foreign_keys = 0");
			} else {
				$this->schema_info['foreign_keys_enabled'] = false;
			}
		}


		$temp_create_table_sql = $this->getSQLiteCreateTable($data['table']);
		if ($temp_create_table_sql === null) {
			$this->throwException(
				self::compose(
					'The table "%1$s" does not exist',
					$data['table']
				),
				$sql
			);
		}
		$temp_create_table_sql = preg_replace(
			'#(^\s*CREATE\s+TABLE\s+)(?:`|\'|"|\[)?(\w+)(?:`|\'|"|\])?(\s*\()#i',
			'\1"fl_tmp_' . $data['table'] . '"\3',
			$temp_create_table_sql
		) ?? $temp_create_table_sql;

		if ($data['type'] == 'drop_primary_key' && !preg_match('#\bPRIMARY\s+KEY\b#', $temp_create_table_sql)) {
			$this->throwException(
				self::compose(
					'The table "%1$s" does not have a primary key constraint',
					$data['table']
				),
				$sql
			);
		}

		if ($data['type'] == 'add_primary_key' && preg_match('#\bPRIMARY\s+KEY\b#', $temp_create_table_sql)) {
			$this->throwException(
				self::compose(
					'The table "%1$s" already has a primary key constraint',
					$data['table']
				),
				$sql
			);
		}

		$dropped_unique = false;
		$dropped_foreign_key = false;

		if (in_array($data['type'], array('column_comment', 'alter_type', 'set_not_null', 'drop_not_null', 'drop_default', 'set_default', 'drop_primary_key', 'add_primary_key', 'drop_foreign_key', 'add_foreign_key', 'drop_unique', 'add_unique', 'set_check_constraint', 'drop_check_constraint'))) {
			$column_info = self::parseSQLiteColumnDefinitions($temp_create_table_sql);

			if (isset($data['column_name']) && !isset($column_info[$data['column_name']])) {
				$this->throwException(
					self::compose(
						'The column "%1$s" does not exist in the table "%2$s"',
						$data['column_name'],
						$data['table']
					),
					$sql
				);
			}

			foreach ($column_info as $column => $info) {
				if (isset($data['column_name']) && $column == $data['column_name']) {
					if ($data['type'] == 'alter_type') {
						$info['pieces']['data_type'] = ' ' . $data['data_type'];
					} elseif ($data['type'] == 'set_not_null') {
						$info['pieces']['not_null'] = ' NOT NULL';
						$info['pieces']['null']     = '';
						if (isset($data['default'])) {
							$info['pieces']['default'] = ' DEFAULT ' . $data['default'];
						}
					} elseif ($data['type'] == 'drop_not_null') {
						$info['pieces']['not_null'] = '';
						$info['pieces']['null']     = '';
					} elseif ($data['type'] == 'set_default') {
						$info['pieces']['default'] = ' DEFAULT ' . $data['default_value'];
					} elseif ($data['type'] == 'drop_default') {
						$info['pieces']['default'] = '';
					} elseif ($data['type'] == 'set_check_constraint') {
						$info['pieces']['check_constraint'] = $data['constraint'];
					} elseif ($data['type'] == 'drop_check_constraint') {
						$info['pieces']['check_constraint'] = '';
					} elseif ($data['type'] == 'add_primary_key') {
						$info['pieces']['primary_key'] = ' PRIMARY KEY' . (version_compare($this->database->getVersion(), '3', '>=') && $data['autoincrement'] ? ' AUTOINCREMENT' : '');
					} elseif ($data['type'] == 'drop_foreign_key') {
						if (trim($info['pieces']['foreign_key'])) {
							$dropped_foreign_key = true;
						}
						$info['pieces']['foreign_key'] = '';
					} elseif ($data['type'] == 'add_foreign_key') {
						$foreign_create_table = $this->getSQLiteCreateTable($data['foreign_table']);
						if ($foreign_create_table === null) {
							$this->throwException(
								self::compose(
									'The referenced table "%1$s" does not exist',
									$data['foreign_table']
								),
								$sql
							);
						}
						$foreign_column_info = self::parseSQLiteColumnDefinitions($foreign_create_table);
						if (!isset($foreign_column_info[$data['foreign_column']])) {
							$this->throwException(
								self::compose(
									'The referenced column "%1$s" does not exist in the referenced table "%2$s"',
									$data['foreign_column'],
									$data['foreign_table']
								),
								$sql
							);
						}

						$info['pieces']['foreign_key'] = ' REFERENCES ' . $data['references'];
					} elseif ($data['type'] == 'drop_unique') {
						if (trim($info['pieces']['unique'])) {
							$dropped_unique = true;
						}
						$info['pieces']['unique'] = '';
					} elseif ($data['type'] == 'add_unique') {
						$info['pieces']['unique'] = ' UNIQUE';
					} elseif ($data['type'] == 'column_comment') {
						if (preg_match('#(^\s*,)|(^\s*/\*\s*((?:(?!\*/).)*?)\s*\*/\s*,)#', $info['pieces']['comment/end'])) {
							$info['pieces']['comment/end'] = ',';
						}
						$comment = str_replace("''", "'", substr($data['comment'], 1, -1));
						if (strlen(trim($comment))) {
							$info['pieces']['comment/end'] .= ' -- ' . $comment . "\n";
						}
					}
				} elseif ($data['type'] == 'drop_primary_key') {
					if (trim($info['pieces']['primary_key'])) {
						$data['column_name'] = $column;
					}
					$info['pieces']['not_null'] = ' NOT NULL';
					$info['pieces']['primary_key'] = '';
				}

				$temp_create_table_sql = str_replace(
					$info['definition'],
					join('', $info['pieces']),
					$temp_create_table_sql
				);
			}
		}


		$primary_key_regex = '#(?<=,|\()\s*(?:CONSTRAINT\s+["`\[]?\w+["`\]]?\s+)?PRIMARY\s+KEY\s*\(\s*((?:\s*[\'"`\[]?\w+[\'"`\]]?\s*,\s*)*[\'"`\[]?\w+[\'"`\]]?)\s*\)\s*(?:,|\s*(?=\)))#mi';
		$foreign_key_regex = '#(?<=,|\(|\*/|\n)(\s*(?:CONSTRAINT\s+["`\[]?\w+["`\]]?\s+)?FOREIGN\s+KEY\s*\(?\s*[\'"`\[]?(\w+)[\'"`\]]?\s*\)?)\s+REFERENCES\s+[\'"`\[]?(\w+)[\'"`\]]?\s*\(\s*[\'"`\[]?(\w+)[\'"`\]]?\s*\)\s*(?:(?:\s+ON\s+DELETE\s+(CASCADE|NO\s+ACTION|RESTRICT|SET\s+NULL|SET\s+DEFAULT))|(?:\s+ON\s+UPDATE\s+(CASCADE|NO\s+ACTION|RESTRICT|SET\s+NULL|SET\s+DEFAULT)))*(?:\s+(?:DEFERRABLE|NOT\s+DEFERRABLE))?\s*(?:,(?:[ \t]*--[^\n]*\n)?|(?:--[^\n]*\n)?\s*(?=\)))#mis';
		$unique_constraint_regex = '#(?<=,|\()\s*(?:CONSTRAINT\s+["`\[]?\w+["`\]]?\s+)?UNIQUE\s*\(\s*((?:\s*[\'"`\[]?\w+[\'"`\]]?\s*,\s*)*[\'"`\[]?\w+[\'"`\]]?)\s*\)\s*(?:,|\s*(?=\)))#mi';
		$column_regex = '';
		if (isset($data['column_name'])) {
			$column_regex = '#(?:`|\'|"|\[|\b)' . preg_quote($data['column_name'], '#') . '(?:`|\'|"|\]|\b)#i';
		}

		if ($data['type'] == 'drop_primary_key' || $data['type'] == 'drop_column') {
			// Drop any table-level primary keys
			preg_match_all($primary_key_regex, $temp_create_table_sql, $matches, PREG_SET_ORDER);

			foreach ($matches as $match) {
				$columns = preg_split('#[\'"`\]]?\s*,\s*[\'"`\[]?#', strtolower(trim($match[1], '\'[]`"'))) ?: array();
				sort($columns);

				if ($data['type'] == 'drop_primary_key' && count($columns) == 1) {
					$data['column_name'] = implode('', $columns);
				} elseif ($data['type'] == 'drop_column') {
					if (!in_array($data['column_name'] ?? '', $columns)) {
						continue;
					}
				}

				$temp_create_table_sql = self::removeFromSQLiteCreateTable($temp_create_table_sql, $match[0]);
			}
		}


		if ($data['type'] == 'drop_foreign_key' || $data['type'] == 'drop_column') {
			// Drop any table-level foreign keys
			preg_match_all($foreign_key_regex, $temp_create_table_sql, $matches, PREG_SET_ORDER);
			foreach ($matches as $match) {
				// Ignore foreign keys for other columns
				if (strtolower($match[2]) != ($data['column_name'] ?? '')) {
					continue;
				}
				$dropped_foreign_key = true;

				$temp_create_table_sql = self::removeFromSQLiteCreateTable($temp_create_table_sql, $match[0]);
			}
		}


		if ($data['type'] == 'drop_foreign_key' && !$dropped_foreign_key) {
			$this->throwException(
				self::compose(
					'The column "%1$s" in the table "%2$s" does not have a foreign key constraint',
					$data['column_name'] ?? '',
					$data['table']
				),
				$sql
			);
		}


		if ($data['type'] == 'drop_unique' || $data['type'] == 'drop_column') {
			// Drop any table-level unique keys
			preg_match_all($unique_constraint_regex, $temp_create_table_sql, $matches, PREG_SET_ORDER);
			foreach ($matches as $match) {
				$columns = preg_split('#[\'"`\]]?\s*,\s*[\'"`\[]?#', strtolower(trim($match[1], '\'[]`"'))) ?: array();
				sort($columns);

				if ($data['type'] == 'drop_column') {
					if (!in_array($data['column_name'] ?? '', $columns)) {
						continue;
					}
				} else {
					sort($data['column_names']);
					if ($columns != $data['column_names']) {
						continue;
					}
					$dropped_unique = true;
				}

				$temp_create_table_sql = self::removeFromSQLiteCreateTable($temp_create_table_sql, $match[0]);
			}
		}


		if ($data['type'] == 'rename_column') {
			// Rename the column in the column definition and check constraint
			$column_info = self::parseSQLiteColumnDefinitions($temp_create_table_sql);
			if (!isset($column_info[$data['column_name']])) {
				$this->throwException(
					self::compose(
						'The column "%1$s" does not exist in the table "%2$s"',
						$data['column_name'],
						$data['table']
					),
					$sql
				);
			}
			if (isset($column_info[$data['new_column_name']])) {
				$this->throwException(
					self::compose(
						'The column "%1$s" already exists in the table "%2$s"',
						$data['new_column_name'],
						$data['table']
					),
					$sql
				);
			}
			$info = $column_info[$data['column_name']];

			$temp_create_table_sql = str_replace(
				$info['definition'],
				preg_replace(
					'#^(\s*)[`"\'\[]?' . preg_quote($data['column_name'], '#') . '[`"\'\]]?(\s+)#i',
					'\1"' . $data['new_column_name'] . '"\2',
					$info['definition']
				) ?? $info['definition'],
				$temp_create_table_sql
			);
			if ($info['pieces']['check_constraint']) {
				$temp_create_table_sql = str_replace(
					$info['pieces']['check_constraint'],
					preg_replace(
						'#^(\s*CHECK\s*\(\s*)[`"\'\[]?' . preg_quote($data['column_name'], '#') . '[`"\'\]]?(\s+)#i',
						'\1"' . $data['new_column_name'] . '"\2',
						$info['pieces']['check_constraint']
					) ?? $info['pieces']['check_constraint'],
					$temp_create_table_sql
				);
			}


			// Rename the column in table-level primary key
			preg_match_all($primary_key_regex, $temp_create_table_sql, $matches, PREG_SET_ORDER);
			foreach ($matches as $match) {
				$temp_create_table_sql = str_replace(
					$match[0],
					preg_replace($column_regex, '"' . $data['new_column_name'] . '"', $match[0]) ?? $match[0],
					$temp_create_table_sql
				);
			}


			// Rename the column in table-level foreign key definitions
			preg_match_all($foreign_key_regex, $temp_create_table_sql, $matches, PREG_SET_ORDER);
			foreach ($matches as $match) {
				$temp_create_table_sql = str_replace(
					$match[0],
					str_replace(
						$match[1],
						preg_replace($column_regex, '"' . $data['new_column_name'] . '"', $match[1]) ?? $match[1],
						$match[0]
					),
					$temp_create_table_sql
				);
			}


			// Rename the column in table-level unique constraints
			preg_match_all($unique_constraint_regex, $temp_create_table_sql, $matches, PREG_SET_ORDER);
			foreach ($matches as $match) {
				$temp_create_table_sql = str_replace(
					$match[0],
					preg_replace($column_regex, '"' . $data['new_column_name'] . '"', $match[0]) ?? $match[0],
					$temp_create_table_sql
				);
			}
		}


		if ($data['type'] == 'drop_column') {
			$column_info = self::parseSQLiteColumnDefinitions($temp_create_table_sql);
			if (!isset($column_info[$data['column_name'] ?? ''])) {
				$this->throwException(
					self::compose(
						'The column "%1$s" does not exist in the table "%2$s"',
						$data['column_name'] ?? '',
						$data['table']
					),
					$sql
				);
			}
			$temp_create_table_sql = self::removeFromSQLiteCreateTable(
				$temp_create_table_sql,
				$column_info[$data['column_name'] ?? '']['definition']
			);
		}


		if ($data['type'] == 'add_primary_key' && count($data['column_names']) > 1) {
			$temp_create_table_sql = preg_replace(
				'#\s*\)\s*$#D',
				$this->escape(",\n    PRIMARY KEY(%r)\n)", $data['column_names']),
				$temp_create_table_sql
			) ?? $temp_create_table_sql;
		}


		if ($data['type'] == 'add_unique' && count($data['column_names']) > 1) {
			$temp_create_table_sql = preg_replace(
				'#\s*\)\s*$#D',
				$this->escape(",\n    UNIQUE(%r)\n)", $data['column_names']),
				$temp_create_table_sql
			) ?? $temp_create_table_sql;
		}


		if ($data['type'] == 'add_column') {
			if (!preg_match('#\s*"?\w+"?\s+\w+#', $data['column_definition'])) {
				$this->throwException(
					self::compose(
						'Please specify a data type for the column "%1$s"',
						$data['column_name']
					),
					$sql
				);
			}

			preg_match(
				'#^(.*?)((?:(?<=,|\*/|\n)\s*(?:CONSTRAINT\s+["`\[]?\w+["`\]]?\s+)?\b(?:FOREIGN\s+KEY\b|PRIMARY\s+KEY\b|UNIQUE\b).*)|(?:\s*\)\s*))$#Dis',
				$temp_create_table_sql,
				$match
			);
			$match[1] = $match[1] ?? '';
			$match[2] = $match[2] ?? '';

			if (trim($match[2]) != ')') {
				$prefix = '';
				$suffix = ',';
			} else {
				$prefix = ',';
				$suffix = '';
			}

			// Be sure to add any necessary comma before a single-line SQL comment
			// because if it is placed after, the comma will be part of the comment
			if ($prefix) {
				$original_match_1 = $match[1];
				$match[1] = preg_replace('#(\s*--[^\n]+)(\s*)?$#Di', ',\1\2', $match[1]) ?? $match[1];
				if ($match[1] != $original_match_1) {
					$prefix = '';
				}
			}

			if (version_compare($this->database->getVersion(), '3', '>=')) {
				$data['column_definition'] = preg_replace('#\binteger(?:\(\d+\))?\s+autoincrement\s+primary\s+key\b#i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $data['column_definition']) ?? $data['column_definition'];
				$data['column_definition'] = preg_replace("#datetime\(\s*CURRENT_TIMESTAMP\s*,\s*'localtime'\s*\)#i", 'CURRENT_TIMESTAMP', $data['column_definition']) ?? $data['column_definition'];
			} else {
				$data['column_definition'] = preg_replace('#\binteger(?:\(\d+\))?\s+autoincrement\s+primary\s+key\b#i', 'INTEGER PRIMARY KEY', $data['column_definition']) ?? $data['column_definition'];
				$data['column_definition'] = preg_replace('#CURRENT_TIMESTAMP\(\)#i', 'CURRENT_TIMESTAMP', $data['column_definition']) ?? $data['column_definition'];
			}

			$match[1] .= $prefix . "\n\t" . $data['column_definition'] . $suffix;

			$temp_create_table_sql = $match[1] . $match[2];
		}


		// Clean up extra line breaks
		$temp_create_table_sql = preg_replace('#\n([ \t]*\n)+#', "\n", $temp_create_table_sql) ?? $temp_create_table_sql;


		// SQLite 3 supports renaming a table, so we need the full create
		// table with all of the translated triggers, etc
		if (version_compare($this->database->getVersion(), '3', '>=')) {
			// We rename string placeholders to prevent confusion with
			// string placeholders that are added by call to Database
			$temp_create_table_sql = str_replace(
				'%',
				'%%',
				$temp_create_table_sql
			);

			foreach (str_replace('%%', '%', $this->database->preprocess($temp_create_table_sql, array(), true)) as $statement) {
				$extra_statements[] = $statement;
			}

		// For SQLite 2 we can't rename the table, so we end up needing to
		// create a new one so the temporary table doesn't need triggers
		} else {
			$extra_statements[] = $temp_create_table_sql;
		}
		$this->addSQLiteTable('fl_tmp_' . $data['table'], $temp_create_table_sql);


		// Next we copy the data from the original table to the temp table
		if ($data['type'] == 'rename_column') {
			$column_names     = $this->getSQLiteColumns($data['table']);
			$new_column_names = $column_names;
			$column_position  = array_search($data['column_name'], $new_column_names);
			$new_column_names[$column_position] = $data['new_column_name'];
		} elseif ($data['type'] == 'drop_column') {
			$column_names     = array_diff(
				$this->getSQLiteColumns($data['table']),
				array($data['column_name'] ?? '')
			);
			$new_column_names = $column_names;
		} else {
			$column_names     = $this->getSQLiteColumns($data['table']);
			$new_column_names = $column_names;
		}

		$extra_statements[] = $this->escape(
			"INSERT INTO %r (%r) SELECT %r FROM %r",
			'fl_tmp_' . $data['table'],
			$new_column_names,
			$column_names,
			$data['table']
		);


		// Recreate the indexes for the temp table
		$indexes = $this->getSQLiteIndexes($data['table']);
		foreach ($indexes as $name => $index) {
			$create_sql = $index['sql'];
			preg_match(
				'#^\s*CREATE\s+(UNIQUE\s+)?INDEX\s+(?:[\'"`\[]?\w+[\'"`\]]?\.)?[\'"`\[]?\w+[\'"`\]]?\s+(ON\s+[\'"`\[]?\w+[\'"`\]]?)\s*(\((\s*(?:\s*[\'"`\[]?\w+[\'"`\]]?\s*,\s*)*[\'"`\[]?\w+[\'"`\]]?\s*)\))\s*$#Di',
				$create_sql,
				$match
			);
			$match[2] = $match[2] ?? '';
			$match[3] = $match[3] ?? '';
			$match[4] = $match[4] ?? '';
			$columns = preg_split(
				'#[\'"`\]]?\s*,\s*[\'"`\[]?#',
				strtolower(trim($match[4], '\'[]`"'))
			) ?: array();

			if ($data['type'] == 'rename_column') {
				$create_sql = str_replace(
					$match[3],
					preg_replace($column_regex, '"' . $data['new_column_name'] . '"', $match[3]) ?? $match[3],
					$create_sql
				);
			} elseif ($data['type'] == 'drop_column') {
				if (in_array($data['column_name'] ?? '', $columns)) {
					continue;
				}
			} elseif ($data['type'] == 'drop_unique') {
				sort($columns);
				sort($data['column_names']);

				if ($columns == $data['column_names']) {
					$dropped_unique = true;
					continue;
				}
			}

			// The index needs to be altered to be created on the new table
			$create_sql = str_replace(
				$match[2],
				preg_replace(
					'#(?:`|\'|"|\[|\b)?' . preg_quote($data['table'], '#') . '(?:`|\'|"|\]|\b)?#',
					'"fl_tmp_' . $data['table'] . '"',
					$match[2]
				) ?? $match[2],
				$create_sql
			);

			// We fix the name of the index to keep in sync with the table name
			if (version_compare($this->database->getVersion(), '3', '>=')) {
				$new_name = $name;
			} else {
				$new_name = preg_replace(
					'#^' . preg_quote($data['table'], '#') . '#',
					'fl_tmp_' . $data['table'],
					$name
				) ?? $name;
			}

			// Ensure we have a unique index name
			while (isset($indexes[$new_name])) {
				if (preg_match('#(?<=_)(\d+)$#D', $new_name, $match)) {
					$new_name = preg_replace('#(?<=_)\d+$#D', (string) ($match[1] + 1), $new_name) ?? $new_name;
				} else {
					$new_name .= '_2';
				}
			}

			$create_sql = preg_replace(
				'#[\'"`\[]?' . preg_quote($name, '#') . '[\'"`\]]?(\s+ON\s+)#i',
				'"' . $new_name . '"\1',
				$create_sql
			) ?? $create_sql;

			$this->addSQLiteIndex($new_name, 'fl_tmp_' . $data['table'], $create_sql);

			$extra_statements[] = $create_sql;
		}


		if ($data['type'] == 'drop_unique' && !$dropped_unique) {
			if (count($data['column_names']) > 1) {
				$message = self::compose(
					'The columns "%1$s" in the table "%2$s" do not have a unique constraint',
					join('", "', $data['column_names']),
					$data['table']
				);
			} else {
				$message = self::compose(
					'The column "%1$s" in the table "%2$s" does not have a unique constraint',
					reset($data['column_names']),
					$data['table']
				);
			}
			$this->throwException($message, $sql);
		}


		if (in_array($data['type'], array('rename_column', 'drop_column')) || ($data['type'] == 'drop_primary_key' && isset($data['column_name']))) {
			$foreign_keys = $this->getSQLiteForeignKeys($data['table'], $data['column_name'] ?? '');

			foreach ($foreign_keys as $key) {
				foreach (
                    $this->database->preprocess(
                        "ALTER TABLE %r DROP FOREIGN KEY (%r)",
                        array($key['table'], $key['column']),
                        true
                    ) as $statement
                ) {
					$extra_statements[] = $statement;
				}
			}
		}


		// Drop the original table
		foreach ($this->database->preprocess("DROP TABLE %r", array($data['table']), true) as $statement) {
			$extra_statements[] = $statement;
		}


		// Rename the temp table to the original name
		foreach (
            $this->database->preprocess(
                "ALTER TABLE %r RENAME TO %r",
                array('fl_tmp_' . $data['table'], $data['table']),
                true
            ) as $statement
        ) {
			$extra_statements[] = $statement;
		}


		// Re-add the foreign key constraints for renamed columns
		if ($data['type'] == 'rename_column') {
			foreach ($foreign_keys as $key) {
				foreach (
                    $this->database->preprocess(
                        "ALTER TABLE %r ADD FOREIGN KEY (%r) REFERENCES %r(%r) ON UPDATE " . $key['on_update'] . " ON DELETE " . $key['on_delete'],
                        array($key['table'], $key['column'], $data['table'], $data['new_column_name']),
                        true
                    ) as $statement
                ) {
					$extra_statements[] = $statement;
				}
			}
		}


		// Finally, we turn back on foreign keys
		if ($toggle_foreign_key_support) {
			if (!empty($this->schema_info['foreign_keys_enabled'])) {
				$this->query("PRAGMA foreign_keys = 1");
			}
			unset($this->schema_info['foreign_keys_enabled']);
		}

		// Remove any nested transactions
		$extra_statements = array_filter($extra_statements, function (string $statement): bool {
			return $statement != 'BEGIN' && $statement != 'COMMIT';
		});

		// Overwrite the original ALTER TABLE SQL
		$sql = array_shift($extra_statements) ?? '';

		return $sql;
	}


	/**
	 * Translates `DROP TABLE` statements for SQLite
	 *
	 * @param  string $sql                The SQL to translate
	 * @param  array<int, string>  &$extra_statements  Any extra SQL statements that need to be added
	 * @return string  The translated SQL
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 */
	private function translateSQLiteDropTableStatements(string $sql, array &$extra_statements): string
	{
		if (preg_match('#^\s*DROP\s+TABLE\s+[["\'`]?(\w+)["\'`\]]?\s*$#iD', $sql, $match)) {
			$dependent_tables = array();
			$foreign_keys     = $this->getSQLiteForeignKeys($match[1]);
			foreach ($foreign_keys as $foreign_key) {
				$dependent_tables[] = $foreign_key['table'];
			}
			$dependent_tables = array_unique($dependent_tables);

			// We want to find triggers on tables that this table relies on and drop them
			$triggers = $this->getSQLiteTriggers($match[1]);
			foreach ($triggers as $name => $trigger) {
				if (in_array($trigger['table'], $dependent_tables)) {
					continue;
				}
				$matched_from_update = preg_match(
					'#(\s+(?:FROM|UPDATE)\s+)(`' . $match[1] . '`|"' . $match[1] . '"|\'' . $match[1] . '\'|' . $match[1] . '|\[' . $match[1] . '\])#i',
					$trigger['sql']
				);
				if ($matched_from_update) {
					$extra_statements[] = "DROP TRIGGER " . $name;
					$this->removeSQLiteTrigger($name);
				}
			}
			$this->removeSQLiteIndexes($match[1]);
			$this->removeSQLiteTriggers($match[1]);
			$this->removeSQLiteTable($match[1]);
		}

		return $sql;
	}


	/**
	 * Translates Flourish SQL `ALTER TABLE * RENAME TO` statements to the appropriate
	 * statements for SQLite
	 *
	 * @param string $sql                The SQL statements that will be executed against the database
	 * @param array<int, string>  &$extra_statements  Any extra SQL statements required for SQLite
	 * @param AlterTableRenameTableData $data               Data parsed from the `ALTER TABLE` statement
	 * @return string  The modified SQL statement
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	private function translateSQLiteRenameTableStatements(string $sql, array &$extra_statements, array $data): string
	{
		$tables = $this->getSQLiteTables();
		if (in_array($data['new_table_name'], $tables)) {
			$this->throwException(
				self::compose(
					'A table with the name "%1$s" already exists',
					$data['new_table_name']
				),
				$sql
			);
		}
		if (!in_array($data['table'], $tables)) {
			$this->throwException(
				self::compose(
					'The table specified, "%1$s", does not exist',
					$data['table']
				),
				$sql
			);
		}

		// We start by dropping all references to this table
		$foreign_keys = $this->getSQLiteForeignKeys($data['table']);
		foreach ($foreign_keys as $foreign_key) {
			foreach (
                $this->database->preprocess(
                    "ALTER TABLE %r DROP FOREIGN KEY (%r)",
                    array(
					$foreign_key['table'],
					$foreign_key['column']
                    ),
                    true
                ) as $statement
            ) {
				$extra_statements[] = $statement;
			}
		}

		// SQLite 2 does not natively support renaming tables, so we have to do
		// it by creating a new table name and copying all data and indexes
		if (version_compare($this->database->getVersion(), '3', '<')) {
			$renamed_create_sql = preg_replace(
				'#^\s*CREATE\s+TABLE\s+["\[`\']?\w+["\]`\']?\s+#i',
				'CREATE TABLE "' . $data['new_table_name'] . '" ',
				$this->getSQLiteCreateTable($data['table']) ?? ''
			) ?? '';

			$this->addSQLiteTable($data['new_table_name'], $renamed_create_sql);

			// We rename string placeholders to prevent confusion with
			// string placeholders that are added by call to Database
			$renamed_create_sql = str_replace(
				':string_',
				':sub_string_',
				$renamed_create_sql
			);

			$create_statements = str_replace(
				':sub_string_',
				':string_',
				$this->database->preprocess(
					$renamed_create_sql,
					array(),
					true
				)
			);

			$extra_statements[] = array_shift($create_statements) ?? '';

			// Recreate the indexes on the new table
			$indexes = $this->getSQLiteIndexes($data['table']);
			foreach ($indexes as $name => $index) {
				$create_sql = $index['sql'];
				preg_match(
					'#^\s*CREATE\s+(?:UNIQUE\s+)?INDEX\s+(?:[\'"`\[]?\w+[\'"`\]]?\.)?[\'"`\[]?\w+[\'"`\]]?\s+(ON\s+[\'"`\[]?\w+[\'"`\]]?)\s*(\((\s*(?:\s*[\'"`\[]?\w+[\'"`\]]?\s*,\s*)*[\'"`\[]?\w+[\'"`\]]?\s*)\))\s*$#Di',
					$create_sql,
					$match
				);
				$match[1] = $match[1] ?? '';
				$match[2] = $match[2] ?? '';
				$match[3] = $match[3] ?? '';

				// Fix the table name to the new table
				$create_sql = str_replace(
					$match[1],
					preg_replace(
						'#(?:`|\'|"|\[|\b)?' . preg_quote($data['table'], '#') . '(?:`|\'|"|\]|\b)?#i',
						'"' . $data['new_table_name'] . '"',
						$match[1]
					) ?? $match[1],
					$create_sql
				);

				// We change the name of the index to keep it in sync
				// with the new table name
				$new_name = preg_replace(
					'#^' . preg_quote($data['table'], '#') . '_#i',
					$data['new_table_name'] . '_',
					$name
				) ?? $name;
				$create_sql = preg_replace(
					'#[\'"`\[]?' . preg_quote($name, '#') . '[\'"`\]]?(\s+ON\s+)#i',
					'"' . $new_name . '"\1',
					$create_sql
				) ?? $create_sql;

				$extra_statements[] = $create_sql;
				$this->addSQLiteIndex($new_name, $data['new_table_name'], $create_sql);
			}


			$column_names = $this->getSQLiteColumns($data['table']);
			$extra_statements[] = $this->escape(
				"INSERT INTO %r (%r) SELECT %r FROM %r",
				$data['new_table_name'],
				$column_names,
				$column_names,
				$data['table']
			);


			foreach ($create_statements as $statement) {
				$extra_statements[] = $statement;
			}


			foreach (
                $this->database->preprocess(
                    "DROP TABLE %r",
                    array($data['table']),
                    true
                ) as $statement
            ) {
				$extra_statements[] = $statement;
			}


		// SQLite 3 natively supports renaming tables, but it does not fix
		// references to the old table name inside of trigger bodies
		} else {
			// We add the rename SQL in the middle so it happens after we drop the
			// foreign key constraints and before we re-add them
			$extra_statements[] = $sql;
			$this->addSQLiteTable(
				$data['new_table_name'],
				preg_replace(
					'#^\s*CREATE\s+TABLE\s+[\'"\[`]?\w+[\'"\]`]?\s+#i',
					'CREATE TABLE "' . $data['new_table_name'] . '" ',
					$this->getSQLiteCreateTable($data['table']) ?? ''
				) ?? ''
			);
			$this->removeSQLiteTable($data['table']);

			// Copy the trigger definitions to the new table name
			foreach ($this->getSQLiteTriggers() as $name => $trigger) {
				if ($trigger['table'] == $data['table']) {
					$this->addSQLiteTrigger($name, $data['new_table_name'], $trigger['sql']);
				}
			}

			// Move the index definitions to the new table name
			foreach ($this->getSQLiteIndexes($data['table']) as $name => $index) {
				$this->addSQLiteIndex(
					$name,
					$data['new_table_name'],
					preg_replace(
						'#(\s+ON\s+)["\'`\[]?\w+["\'`\]]?#',
						'\1"' . preg_quote($data['new_table_name'], '#') . '"',
						$index['sql']
					) ?? $index['sql']
				);
			}

			foreach ($this->getSQLiteTriggers() as $name => $trigger) {
				$create_sql = $trigger['sql'];
				$create_sql = preg_replace(
					'#( on table )"' . $data['table'] . '"#i',
					'\1"' . $data['new_table_name'] . '"',
					$create_sql
				) ?? $create_sql;
				$create_sql = preg_replace(
					'#(\s+FROM\s+)(`' . $data['table'] . '`|"' . $data['table'] . '"|\'' . $data['table'] . '\'|' . $data['table'] . '|\[' . $data['table'] . '\])#i',
					'\1"' . $data['new_table_name'] . '"',
					$create_sql
				) ?? $create_sql;
				if ($create_sql != $trigger['sql']) {
					$extra_statements[] = $this->escape("DROP TRIGGER %r", $name);
					$this->removeSQLiteTrigger($name);
					$this->addSQLiteTrigger($name, $data['new_table_name'], $create_sql);
					$extra_statements[] = $create_sql;
				}
			}
		}


		// Here we recreate the references that we dropped at the beginning
		foreach ($foreign_keys as $foreign_key) {
			foreach (
                $this->database->preprocess(
                    "ALTER TABLE %r ADD FOREIGN KEY (%r) REFERENCES %r(%r) ON UPDATE " . $foreign_key['on_update'] . " ON DELETE " . $foreign_key['on_delete'],
                    array(
					$foreign_key['table'],
					$foreign_key['column'],
					$data['new_table_name'],
					$foreign_key['foreign_column']
                    ),
                    true
                ) as $statement
            ) {
				$extra_statements[] = $statement;
			}
		}

		// Remove any nested transactions
		$extra_statements = array_filter($extra_statements, function (string $statement): bool {
			return $statement != 'BEGIN' && $statement != 'COMMIT';
		});

		// Since the actual rename or create/drop has to happen after adjusting
		// foreign keys, we previously added it in the appropriate place and
		// now need to provide the first statement to be run
		return array_shift($extra_statements) ?? '';
	}
}



/**
 * Copyright (c) 2011-2012 Will Bond <will@flourishlib.com>
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
