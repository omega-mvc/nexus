<?php
declare(strict_types=1);
/**
 * Representation of an unbuffered result from a query against the Database class
 *
 * @copyright  Copyright (c) 2007-2011 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 * @link       http://flourishlib.com/UnbufferedResult
 *
 * @version    1.0.0b13
 * @changes    1.0.0b13  Added a workaround for iconv having issues in MAMP 1.9.4+ [wb, 2011-07-26]
 * @changes    1.0.0b12  Fixed MSSQL to have a properly reset row array, added ::silenceNotices(), fixed pdo_dblib on Windows when using the Microsoft DBLib driver [wb, 2011-05-09]
 * @changes    1.0.0b11  Fixed some bugs with the mysqli extension and prepared statements [wb, 2010-08-28]
 * @changes    1.0.0b10  Backwards Compatibility Break - removed ODBC support [wb, 2010-07-31]
 * @changes    1.0.0b9   Added IBM DB2 support [wb, 2010-04-13]
 * @changes    1.0.0b8   Added support for prepared statements [wb, 2010-03-02]
 * @changes    1.0.0b7   Fixed a bug with decoding MSSQL national column when using an ODBC connection [wb, 2009-09-18]
 * @changes    1.0.0b6   Added the method ::unescape(), changed ::tossIfNoRows() to return the object for chaining [wb, 2009-08-12]
 * @changes    1.0.0b5   Added the method ::asObjects() to allow for returning objects instead of associative arrays [wb, 2009-06-23]
 * @changes    1.0.0b4   Fixed a bug with not properly converting SQL Server text to UTF-8 [wb, 2009-06-18]
 * @changes    1.0.0b3   Added support for Oracle, various bug fixes [wb, 2009-05-04]
 * @changes    1.0.0b2   Updated for new Core API [wb, 2009-02-16]
 * @changes    1.0.0b    The initial implementation [wb, 2008-05-07]
 *
 * @implements Iterator<int, array<int|string, mixed>|stdClass>
 */
class UnbufferedResult implements Iterator
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
	 * The database object the result was created from
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
	 * The SQL from before translation
	 *
	 * @var string
	 */
	private ?string $untranslated_sql = NULL;


	/**
	 * Configures the result set
	 *
	 * @internal
	 *
	 * @param  mixed $database       The database object this result was created from
	 * @param  string    $character_set  MSSQL only: the character set to transcode from since MSSQL doesn't do UTF-8
	 * @return UnbufferedResult
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
	 * Frees up the result object
	 *
	 * @internal
	 *
	 * @return void
	 */
	public function __destruct()
	{
		$result = $this->result;

		if (!is_resource($result) && !is_object($result)) {
			return;
		}

		// stdClass results are holders for prepared statements, so we don't
		// want to free them since it would break Statement
		if ($result instanceof stdClass) {
			if ($this->extension == 'msyqli' && $result->statement instanceof mysqli_stmt) {
				$result->statement->free_result();
			}
			unset($this->result);
			return;
		}

		switch ($this->extension) {
			case 'ibm_db2':
				if (is_resource($result)) {
					db2_free_result($result);
				}
				break;

			case 'mysqli':
				if ($result instanceof mysqli_result) {
					mysqli_free_result($result);
				}
				break;

			case 'oci8':
				if (is_resource($result)) {
					oci_free_statement($result);
				}
				break;

			case 'pgsql':
				if ($result instanceof PgSql\Result) {
					pg_free_result($result);
				}
				break;

			case 'sqlsrv':
				if (is_resource($result)) {
					sqlsrv_free_stmt($result);
				}
				break;

			case 'pdo':
				if ($result instanceof PDOStatement) {
					$result->closeCursor();
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

		$row = NULL;

		switch ($this->extension) {
			case 'ibm_db2':
				if (is_resource($this->result)) {
					$row = db2_fetch_assoc($this->result);
				}
				break;

			case 'mysqli':
				if (!$this->result instanceof stdClass) {
					if ($this->result instanceof mysqli_result) {
						$row = mysqli_fetch_assoc($this->result);
					}
					break;
				}

				$statement = $this->result->statement;
				if (!$statement instanceof mysqli_stmt) {
					break;
				}

				$meta = $statement->result_metadata();
				if ($meta === FALSE) {
					break;
				}

				$fetched_row    = array();
				$row_references = array();
				while ($field = $meta->fetch_field()) {
					// Populating the key explicitly mirrors what taking a reference to a
					// non-existent offset does, and lets the type of $fetched_row be seen
					$fetched_row[$field->name] = NULL;
					$row_references[] = &$fetched_row[$field->name];
				}

				call_user_func_array(array($statement, 'bind_result'), $row_references);
				$statement->fetch();

				$row = array();
				foreach ($fetched_row as $key => $val) {
					$row[$key] = $val;
				}
				unset($row_references);
				$meta->free_result();
				break;

			case 'oci8':
				if (is_resource($this->result)) {
					$row = oci_fetch_assoc($this->result);
				}
				break;

			case 'pgsql':
				if ($this->result instanceof PgSql\Result) {
					$row = pg_fetch_assoc($this->result);
				}
				break;

			case 'sqlsrv':
				$resource = $this->result instanceof stdClass ? $this->result->statement : $this->result;
				if (is_resource($resource)) {
					$row = sqlsrv_fetch_array($resource, SQLSRV_FETCH_ASSOC);
				}
				break;

			case 'pdo':
				if ($this->result instanceof PDOStatement) {
					$row = $this->result->fetch(PDO::FETCH_ASSOC);
					if (is_array($row) && $row !== array() && $type == 'mssql') {
						$row = $this->fixDblibMSSQLDriver($row);
					}
				}
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
					if (!is_string($key) || !is_string($value) || strpos($key, '__flourish_mssqln_') === 0 || isset($row['fmssqln__' . $key]) || preg_match('#[\x0-\x8\xB\xC\xE-\x1F]#', $value)) {
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
	 * @return UnbufferedResult  The result object, to allow for method chaining
	 */
	public function asObjects(): UnbufferedResult
	{
		$this->output_objects = TRUE;
		return $this;
	}


	/**
	 * Returns the current row in the result set (required by iterator interface)
	 *
	 * @throws NoRowsException       When the query did not return any rows
	 * @throws NoRemainingException  When there are no rows left in the result
	 * @internal
	 *
	 * @return array<int|string, mixed>|stdClass  The current row
	 */
	public function current(): mixed
	{
		$this->validateState();

		// Primes the result set
		if ($this->pointer === NULL) {
			$this->pointer = 0;
			$this->advanceCurrentRow();
		}

		if(!$this->current_row && $this->pointer == 0) {
			throw new NoRowsException('The query did not return any rows');

		} elseif (!$this->current_row) {
			throw new NoRemainingException('There are no remaining rows');
		}

		if ($this->output_objects) {
			return (object) $this->current_row;
		}
		return $this->current_row;
	}


	/**
	 * Decodes national (unicode) character data coming out of MSSQL into UTF-8
	 *
	 * @param  array<int|string, mixed> $row  The row from the database
	 * @return array<int|string, mixed>  The fixed row
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
	 * Returns the row next row in the result set (where the pointer is currently assigned to)
	 *
	 * @throws NoRowsException       When the query did not return any rows
	 * @throws NoRemainingException  When there are no rows left in the result
	 *
	 * @return array<int|string, mixed>|stdClass  The next row in the result
	 */
	public function fetchRow(): mixed
	{
		$this->validateState();

		$row = $this->current();
		$this->next();
		return $row;
	}


	/**
	 * Warns the user about bugs in the DBLib driver for MSSQL, fixes some bugs
	 *
	 * @param  array<int|string, mixed> $row  The row from the database
	 * @return array<int|string, mixed>  The fixed row
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
					$using_dblib[$this->extension] = (bool) preg_match('#MSSQL_70#i', $module_info, $match);
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
	 * Returns the result
	 *
	 * @internal
	 *
	 * @return mixed  The result of the query
	 */
	public function getResult(): mixed
	{
		$this->validateState();

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
	 * @return string  The SQL from before translation
	 */
	public function getUntranslatedSQL(): string
	{
		return $this->untranslated_sql ?? '';
	}


	/**
	 * Returns the current row number (required by iterator interface)
	 *
	 * @throws NoRowsException       When the query did not return any rows
	 * @throws NoRemainingException  When there are no rows left in the result
	 * @internal
	 *
	 * @return integer  The current row number
	 */
	public function key(): int
	{
		$this->validateState();

		if ($this->pointer === NULL) {
			$this->current();
		}

		return $this->pointer ?? 0;
	}


	/**
	 * Advances to the next row in the result (required by iterator interface)
	 *
	 * @throws NoRowsException       When the query did not return any rows
	 * @throws NoRemainingException  When there are no rows left in the result
	 * @internal
	 *
	 * @return void
	 */
	public function next(): void
	{
		$this->validateState();

		if ($this->pointer === NULL) {
			$this->current();
		}

		$this->advanceCurrentRow();
		$this->pointer++;
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
		$this->validateState();

		if (!empty($this->pointer)) {
			throw new ProgrammerException(
				'Unbuffered database results can not be iterated through multiple times'
			);
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
	 * @return UnbufferedResult  The result object, to allow for method chaining
	 */
	public function tossIfNoRows(?string $message=NULL): UnbufferedResult
	{
		try {
			$this->current();
		} catch (NoRowsException $e) {
			if ($message !== NULL) {
				$e->setMessage($message);
			}
			throw $e;
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
	 * @return UnbufferedResult  The result object, to allow for method chaining
	 */
	public function unescape(array $column_data_type_map): UnbufferedResult
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
		$this->validateState();

		if ($this->pointer === NULL) {
			$this->advanceCurrentRow();
			$this->pointer = 0;
		}

		return !empty($this->current_row);
	}


	/**
	 * Throws an exception if this object has been deconstructed already
	 *
	 * @return void
	 */
	private function validateState(): void
	{
		if ($this->result === NULL) {
			throw new ProgrammerException('This unbuffered result has been fully fetched, or replaced by a newer result');
		}
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
