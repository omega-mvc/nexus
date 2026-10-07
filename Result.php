<?php
declare(strict_types=1);
/**
 * Representation of a result from a query against the Database class
 *
 * @copyright  Copyright (c) 2007-2011 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 * @link       http://flourishlib.com/Result
 *
 * @version    1.0.0b13
 * @changes    1.0.0b12  Added a workaround for iconv having issues in MAMP 1.9.4+ [wb, 2011-07-26]
 * @changes    1.0.0b12  Fixed MSSQL to have a properly reset row array, added ::silenceNotices(), fixed pdo_dblib on Windows when using the Microsoft DBLib driver [wb, 2011-05-09]
 * @changes    1.0.0b11  Backwards Compatibility Break - removed ODBC support [wb, 2010-07-31]
 * @changes    1.0.0b10  Added IBM DB2 support [wb, 2010-04-13]
 * @changes    1.0.0b9   Added support for prepared statements [wb, 2010-03-02]
 * @changes    1.0.0b8   Fixed a bug with decoding MSSQL national column when using an ODBC connection [wb, 2009-09-18]
 * @changes    1.0.0b7   Added the method ::unescape(), changed ::tossIfNoRows() to return the object for chaining [wb, 2009-08-12]
 * @changes    1.0.0b6   Fixed a bug where ::fetchAllRows() would throw a NoRowsException [wb, 2009-06-30]
 * @changes    1.0.0b5   Added the method ::asObjects() to allow for returning objects instead of associative arrays [wb, 2009-06-23]
 * @changes    1.0.0b4   Fixed a bug with not properly converting SQL Server text to UTF-8 [wb, 2009-06-18]
 * @changes    1.0.0b3   Added support for Oracle, various bug fixes [wb, 2009-05-04]
 * @changes    1.0.0b2   Updated for new Core API [wb, 2009-02-16]
 * @changes    1.0.0b    The initial implementation [wb, 2007-09-25]
 *
 * @implements Iterator<int, array|stdClass>
 */
class Result implements Iterator
{
	/**
	 * If notices should be hidden for broken database drivers
	 *
	 * @var boolean
	 */
	static private bool $silence_notices = FALSE;


	/**
	 * Composes text using Text if loaded
	 *
	 * @param  string  $message    The message to compose
	 * @return string  The composed and possible translated message
	 */
	static protected function compose(string $message): string
	{
		$args = array_map(
			fn(mixed $arg): bool|float|int|string|null => $arg === NULL || is_scalar($arg) ? $arg : get_debug_type($arg),
			array_slice(func_get_args(), 1)
		);

		if (class_exists('Text', FALSE)) {
			return Text::compose($message, $args);
		}

		return vsprintf($message, $args);
	}


	/**
	 * This works around a bug in MAMP 1.9.4+ and PHP 5.3 where iconv()
	 * does not seem to properly assign the return value to a variable, but
	 * does work when returning the value.
	 *
	 * @param string $in_charset   The incoming character encoding
	 * @param string $out_charset  The outgoing character encoding
	 * @param string $string       The string to convert
	 * @return string  The converted string
	 */
	static private function iconv(string $in_charset, string $out_charset, string $string): string
	{
		$converted = iconv($in_charset, $out_charset, $string);

		return $converted === FALSE ? '' : $converted;
	}


	/**
	 * Turns off notices about broken database extensions much as the MSSQL DBLib driver
	 *
	 * @return void
	 */
	static public function silenceNotices(): void
	{
		self::$silence_notices = TRUE;
	}


	/**
	 * The number of rows affected by an `INSERT`, `UPDATE`, `DELETE`, etc
	 *
	 * @var integer
	 */
	private int $affected_rows = 0;

	/**
	 * The auto incremented value from the query
	 *
	 * @var int|string|null
	 */
	private int|string|null $auto_incremented_value = NULL;

	/**
	 * The character set to transcode from for MSSQL queries
	 *
	 * @var string
	 */
	private ?string $character_set = NULL;

	/**
	 * The current row of the result set
	 *
	 * @var array<int|string, mixed>|null
	 */
	private ?array $current_row = NULL;

	/**
	 * The database object this result was created from
	 *
	 * @var Database
	 */
	private Database $database;

	/**
	 * The database extension
	 *
	 * @var string
	 */
	private string $extension = '';

	/**
	 * If rows should be converted to objects
	 *
	 * @var boolean
	 */
	private bool $output_objects = FALSE;

	/**
	 * The position of the pointer in the result set
	 *
	 * @var integer
	 */
	private ?int $pointer = null;

	/**
	 * The result resource or array
	 *
	 * @var mixed
	 */
	private mixed $result = NULL;

	/**
	 * The number of rows returned by a select
	 *
	 * @var integer
	 */
	private int $returned_rows = 0;

	/**
	 * The SQL query
	 *
	 * @var string
	 */
	private string $sql = '';

	/**
	 * Holds the data types for each column to allow for on-the-fly unescaping
	 *
	 * @var array<string, string>
	 */
	private array $unescape_map = array();

	/**
	 * The SQL from before translation - only applicable to translated queries
	 *
	 * @var string
	 */
	private ?string $untranslated_sql = NULL;


	/**
	 * Configures the result set
	 *
	 * @internal
	 *
	 * @param  mixed $database       The database object this result set was created from
	 * @param  string    $character_set  MSSQL only: the character set to transcode from since MSSQL doesn't do UTF-8
	 * @return Result
	 */
	public function __construct(mixed $database, mixed $character_set=NULL)
	{
		if (!$database instanceof Database) {
			throw new ProgrammerException(
				'The database object provided does not appear to be a descendant of Database'
			);
		}

		$this->database      = $database;
		$this->extension     = $this->database->getExtension();
		$this->character_set = $character_set;
	}


	/**
	 * Frees up the result object to save memory
	 *
	 * @internal
	 *
	 * @return void
	 */
	public function __destruct()
	{
		if (!is_resource($this->result) && !is_object($this->result)) {
			return;
		}

		switch ($this->extension) {
			case 'mysqli':
				if ($this->result instanceof mysqli_result) {
					mysqli_free_result($this->result);
				}
				break;

			case 'pgsql':
				if ($this->result instanceof PgSql\Result) {
					pg_free_result($this->result);
				}
				break;
		}

		$this->result = NULL;
	}


	/**
	 * All requests that hit this method should be requests for callbacks
	 *
	 * @internal
	 *
	 * @param  string $method  The method to create a callback for
	 * @return callable(mixed...):mixed  The callback for the method requested
	 */
	public function __get(string $method): callable
	{
		return $this->$method(...);
	}


	/**
	 * Gets the next row from the result and assigns it to the current row
	 *
	 * @return void
	 */
	private function advanceCurrentRow(): void
	{
		$type = $this->database->getType();

		$rows = $this->result;
		if (!is_array($rows)) {
			$rows = array();
		}

		$row = NULL;
		$pointer = $this->pointer ?? 0;

		switch ($this->extension) {
			case 'mysqli':
				if ($this->result instanceof mysqli_result) {
					$row = mysqli_fetch_assoc($this->result);
				} else {
					$row = $rows[$pointer] ?? NULL;
				}
				break;

			case 'pgsql':
				if ($this->result instanceof PgSql\Result) {
					$row = pg_fetch_assoc($this->result);
				} else {
					$row = $rows[$pointer] ?? NULL;
				}
				break;

			case 'pdo':
				$row = $rows[$pointer] ?? NULL;
				if (is_array($row) && $row !== array() && $type == 'mssql') {
					$row = $this->fixDblibMSSQLDriver($row);
				}
				break;

			case 'ibm_db2':
			case 'oci8':
			case 'sqlsrv':
				$row = $rows[$pointer] ?? NULL;
				break;
		}

		if (!is_array($row)) {
			$row = array();
		}

		// Fix uppercase column names to lowercase
		if ($row !== array() && ($type == 'oracle' || ($type == 'db2' && $this->extension != 'ibm_db2'))) {
			$row = array_change_key_case($row);
		}

		// This is an unfortunate fix that required for databases that don't support limit
		// clauses with an offset. It prevents unrequested columns from being returned.
		if (isset($row['flourish__row__num'])) {
			unset($row['flourish__row__num']);
		}

		// This decodes the data coming out of MSSQL into UTF-8
		if ($row !== array() && $type == 'mssql') {
			if ($this->character_set) {
				foreach ($row as $key => $value) {
					if (!is_string($key) || !is_string($value) || strpos($key, 'fmssqln__') === 0 || isset($row['fmssqln__' . $key]) || preg_match('#[\x0-\x8\xB\xC\xE-\x1F]#', $value)) {
						continue;
					}
					$row[$key] = self::iconv($this->character_set, 'UTF-8', $value);
				}
			}
			$row = $this->decodeMSSQLNationalColumns($row);
			// This resets the array pointer so that
			// current() will work as expected
			reset($row);
		}

		if ($this->unescape_map) {
			foreach ($this->unescape_map as $column => $column_data_type) {
				if (!isset($row[$column])) { continue; }
				$row[$column] = $this->database->unescape($column_data_type, $row[$column]);
			}
		}

		$this->current_row = $row;
	}


	/**
	 * Sets the object to return rows as objects instead of associative arrays (the default)
	 *
	 * @return Result  The result object, to allow for method chaining
	 */
	public function asObjects(): Result
	{
		$this->output_objects = TRUE;
		return $this;
	}


	/**
	 * Returns the number of rows affected by the query
	 *
	 * @return integer  The number of rows affected by the query
	 */
	public function countAffectedRows(): int
	{
		return $this->affected_rows;
	}


	/**
	 * Returns the number of rows returned by the query
	 *
	 * @return integer  The number of rows returned by the query
	 */
	public function countReturnedRows(): int
	{
		return $this->returned_rows;
	}


	/**
	 * Returns the current row in the result set (required by iterator interface)
	 *
	 * @throws NoRowsException       When the query did not return any rows
	 * @throws NoRemainingException  When there are no remaining rows in the result
	 * @internal
	 *
	 * @return array<int|string, mixed>|stdClass  The current row
	 */
	public function current(): mixed
	{
		if(!$this->returned_rows) {
			throw new NoRowsException('The query did not return any rows');
		}

		if (!$this->valid()) {
			throw new NoRemainingException('There are no remaining rows');
		}

		// Primes the result set
		if ($this->pointer === NULL) {
			$this->pointer = 0;
			$this->advanceCurrentRow();
		}

		$row = $this->current_row;
		if ($row === NULL) {
			throw new NoRemainingException('There are no remaining rows');
		}

		if ($this->output_objects) {
			return (object) $row;
		}

		return $row;
	}


	/**
	 * Decodes national (unicode) character data coming out of MSSQL into UTF-8
	 *
	 * @param  array<mixed, mixed> $row  The row from the database
	 * @return array<mixed, mixed>  The fixed row
	 */
	private function decodeMSSQLNationalColumns(array $row): array
	{
		if (strpos($this->sql, 'fmssqln__') === FALSE) {
			return $row;
		}

		$columns = array_keys($row);

		foreach ($columns as $column) {
			if (!is_string($column) || substr($column, 0, 9) != 'fmssqln__') {
				continue;
			}

			$real_column = substr($column, 9);
			$decoded = $this->database->unescape('blob', $row[$column]);

			$row[$real_column] = is_string($decoded)
				? self::iconv('UCS-2LE', 'UTF-8', $decoded)
				: $decoded;
			unset($row[$column]);
		}

		return $row;
	}


	/**
	 * Returns all of the rows from the result set
	 *
	 * @return array<int, array<int|string, mixed>|stdClass>  The array of rows
	 */
	public function fetchAllRows(): array
	{
		$all_rows = array();
		foreach ($this as $row) {
			$all_rows[] = $row;
		}
		return $all_rows;
	}


	/**
	 * Returns the row next row in the result set (where the pointer is currently assigned to)
	 *
	 * @throws NoRowsException       When the query did not return any rows
	 * @throws NoRemainingException  When there are no rows left in the result
	 *
	 * @return array<int|string, mixed>|stdClass  The next row in the result
	 */
	public function fetchRow(): mixed
	{
		$row = $this->current();
		$this->next();
		return $row;
	}


	/**
	 * Wraps around ::fetchRow() and returns the first field from the row instead of the whole row
	 *
	 * @throws NoRowsException       When the query did not return any rows
	 * @throws NoRemainingException  When there are no rows left in the result
	 *
	 * @return bool|float|int|string|null  The first scalar value from ::fetchRow()
	 */
	public function fetchScalar(): mixed
	{
		$row = $this->fetchRow();

		if ($row instanceof stdClass) {
			$row = (array) $row;
		}

		$value = array_shift($row);

		return is_scalar($value) ? $value : NULL;
	}


	/**
	 * Warns the user about bugs in the DBLib driver for MSSQL, fixes some bugs
	 *
	 * @param  array<mixed, mixed> $row  The row from the database
	 * @return array<mixed, mixed>  The fixed row
	 */
	private function fixDblibMSSQLDriver(array $row): array
	{
		/** @var array<string, bool> $using_dblib  Cached detection of the MSSQL driver in use */
		static $using_dblib = array();

		if (!isset($using_dblib[$this->extension])) {

			// If it is not a windows box we are definitely not using dblib
			if (!Core::checkOS('windows')) {
				$using_dblib[$this->extension] = FALSE;

			// Check this windows box for dblib
			} else {
				ob_start();
				phpinfo(INFO_MODULES);
				$module_info = ob_get_contents();
				ob_end_clean();

				if ($module_info === FALSE) {
					$module_info = '';
				}

				if ($this->extension == 'pdo_mssql') {
					$using_dblib[$this->extension] = preg_match('#MSSQL_70#i', $module_info, $match);
				} else {
					$using_dblib[$this->extension] = !preg_match('#FreeTDS#i', $module_info, $match);
				}
			}
		}

		if (!$using_dblib[$this->extension]) {
			return $row;
		}

		foreach ($row as $key => $value) {
			if ($value === ' ') {
				$row[$key] = '';
				if (!self::$silence_notices) {
					trigger_error(
						self::compose(
							'A single space was detected coming out of the database and was converted into an empty string - see %s for more information',
							'http://bugs.php.net/bug.php?id=26315'
						),
						E_USER_NOTICE
					);
				}
			}
			if (!self::$silence_notices && is_string($key) && strlen($key) == 30) {
				trigger_error(
					self::compose(
						'A column name exactly 30 characters in length was detected coming out of the database - this column name may be truncated, see %s for more information.',
						'http://bugs.php.net/bug.php?id=23990'
					),
					E_USER_NOTICE
				);
			}
			if (!self::$silence_notices && is_string($value) && strlen($value) == 256) {
				trigger_error(
					self::compose(
						'A value exactly 255 characters in length was detected coming out of the database - this value may be truncated, see %s for more information.',
						'http://bugs.php.net/bug.php?id=37757'
					),
					E_USER_NOTICE
				);
			}
		}

		return $row;
	}


	/**
	 * Returns the last auto incremented value for this database connection. This may or may not be from the current query.
	 *
	 * @return int|string|null  The auto incremented value
	 */
	public function getAutoIncrementedValue(): int|string|null
	{
		return $this->auto_incremented_value;
	}


	/**
	 * Returns the result
	 *
	 * @internal
	 *
	 * @return mixed  The result of the query
	 */
	public function getResult(): mixed
	{
		return $this->result;
	}


	/**
	 * Returns the SQL used in the query
	 *
	 * @return string  The SQL used in the query
	 */
	public function getSQL(): string
	{
		return $this->sql;
	}


	/**
	 * Returns the SQL as it was before translation
	 *
	 * @return string  The SQL from before translation, or an empty string when the query was not translated
	 */
	public function getUntranslatedSQL(): string
	{
		return $this->untranslated_sql ?? '';
	}


	/**
	 * Returns the current row number (required by iterator interface)
	 *
	 * @throws NoRowsException       When the query did not return any rows
	 * @throws NoRemainingException  When there are no remaining rows in the result
	 * @internal
	 *
	 * @return integer  The current row number
	 */
	public function key(): int
	{
		if ($this->pointer === NULL) {
			$this->current();
		}

		return $this->pointer ?? 0;
	}


	/**
	 * Advances to the next row in the result (required by iterator interface)
	 *
	 * @throws NoRowsException       When the query did not return any rows
	 * @throws NoRemainingException  When there are no remaining rows in the result
	 * @internal
	 *
	 * @return void
	 */
	public function next(): void
	{
		if ($this->pointer === NULL) {
			$this->current();
		}

		$this->pointer++;

		if ($this->valid()) {
			$this->advanceCurrentRow();
		} else {
			$this->current_row = NULL;
		}
	}


	/**
	 * Rewinds the query (required by iterator interface)
	 *
	 * @internal
	 *
	 * @return void
	 */
	public function rewind(): void
	{
		try {
			$this->seek(0);
		} catch (Exception $e) { }
	}


	/**
	 * Seeks to the specified zero-based row for the specified SQL query
	 *
	 * @throws NoRowsException  When the query did not return any rows
	 *
	 * @param  integer $row  The row number to seek to (zero-based)
	 * @return void
	 */
	public function seek(int $row): void
	{
		if(!$this->returned_rows) {
			throw new NoRowsException('The query did not return any rows');
		}

		if ($row >= $this->returned_rows || $row < 0) {
			throw new ProgrammerException('The row requested does not exist');
		}

		$this->pointer = $row;

		$success = FALSE;

		switch ($this->extension) {
			case 'mysqli':
				$success = $this->result instanceof mysqli_result
					? mysqli_data_seek($this->result, $row)
					: TRUE;
				break;

			case 'pgsql':
				$success = $this->result instanceof PgSql\Result
					? pg_result_seek($this->result, $row)
					: TRUE;
				break;

			case 'ibm_db2':
			case 'oci8':
			case 'pdo':
			case 'sqlsrv':
				// Do nothing since we already changed the pointer
				$success = TRUE;
				break;
		}

		if (!$success) {
			throw new SQLException(
				'There was an error seeking to row %s',
				$row
			);
		}

		$this->advanceCurrentRow();
	}


	/**
	 * Sets the number of affected rows
	 *
	 * @internal
	 *
	 * @param  integer $affected_rows  The number of affected rows
	 * @return void
	 */
	public function setAffectedRows(int $affected_rows): void
	{
		if ($affected_rows === -1) { $affected_rows = 0; }
		$this->affected_rows = (int) $affected_rows;
	}


	/**
	 * Sets the auto incremented value
	 *
	 * @internal
	 *
	 * @param  int|string|null $auto_incremented_value  The auto incremented value
	 * @return void
	 */
	public function setAutoIncrementedValue(int|string|null $auto_incremented_value): void
	{
		if ($auto_incremented_value === NULL || $auto_incremented_value === 0 || $auto_incremented_value === '0') {
			$this->auto_incremented_value = NULL;
		} else {
			$this->auto_incremented_value = $auto_incremented_value;
		}
	}


	/**
	 * Sets the result from the query
	 *
	 * @internal
	 *
	 * @param  mixed $result  The result from the query
	 * @return void
	 */
	public function setResult(mixed $result): void
	{
		$this->result = $result;
	}


	/**
	 * Sets the number of rows returned
	 *
	 * @internal
	 *
	 * @param  integer $returned_rows  The number of rows returned
	 * @return void
	 */
	public function setReturnedRows(int $returned_rows): void
	{
		$this->returned_rows = (int) $returned_rows;
		if ($this->returned_rows) {
			$this->affected_rows = 0;
		}
	}


	/**
	 * Sets the SQL used in the query
	 *
	 * @internal
	 *
	 * @param  string $sql  The SQL used in the query
	 * @return void
	 */
	public function setSQL(string $sql): void
	{
		$this->sql = $sql;
	}


	/**
	 * Sets the SQL from before translation
	 *
	 * @internal
	 *
	 * @param  string $untranslated_sql  The SQL from before translation
	 * @return void
	 */
	public function setUntranslatedSQL(string $untranslated_sql): void
	{
		$this->untranslated_sql = $untranslated_sql;
	}


	/**
	 * Throws a NoRowsException if the query did not return any rows
	 *
	 * @throws NoRowsException  When the query did not return any rows
	 *
	 * @param  string $message  The message to use for the exception if there are no rows in this result set
	 * @return Result  The result object, to allow for method chaining
	 */
	public function tossIfNoRows(?string $message=NULL): Result
	{
		if (!$this->returned_rows && !$this->affected_rows) {
			if ($message === NULL) {
				$message = 'No rows were returned or affected by the query';
			}
			throw new NoRowsException($message);
		}

		return $this;
	}


	/**
	 * Sets the result object to unescape all values as they are retrieved from the object
	 *
	 * The data types should be from the list of types supported by
	 * Database::unescape().
	 *
	 * @param  array<string, string> $column_data_type_map  An associative array with column names as the keys and the data types as the values
	 * @return Result  The result object, to allow for method chaining
	 */
	public function unescape(array $column_data_type_map): Result
	{
		 $this->unescape_map = $column_data_type_map;

		 return $this;
	}


	/**
	 * Returns if the query has any rows left
	 *
	 * @return boolean  If the iterator is still valid
	 */
	public function valid(): bool
	{
		if (!$this->returned_rows) {
			return FALSE;
		}

		if ($this->pointer === NULL) {
			return TRUE;
		}

		return ($this->pointer < $this->returned_rows);
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
