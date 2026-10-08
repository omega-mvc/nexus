<?php
declare(strict_types=1);
/**
 * Provides special column functionality for ActiveRecord classes
 *
 * @copyright  Copyright (c) 2008-2016 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 * @link       http://flourishlib.com/ORMColumn
 *
 * @version    1.0.0b16
 * @changes    1.0.0b16  Add ::configurePasswordColumn() [wb, 2016-10-14]
 * @changes    1.0.0b15  Fixed a bug with empty string email values passing through required validation [wb, 2011-07-29]
 * @changes    1.0.0b14  Updated code to work with the new ORM API [wb, 2010-08-06]
 * @changes    1.0.0b13  Fixed ::reflect() to include some missing parameters [wb, 2010-06-08]
 * @changes    1.0.0b12  Changed validation messages array to use column name keys [wb, 2010-05-26]
 * @changes    1.0.0b11  Fixed a bug with ::prepareLinkColumn() returning `http://` for empty link columns and not adding `http://` to links that contained a /, but did not start with it [wb, 2010-03-16]
 * @changes    1.0.0b10  Fixed ::reflect() to specify the value returned from `set` and `generate` methods, changed ::generate() methods to return the newly generated string [wb, 2010-03-15]
 * @changes    1.0.0b9   Changed email columns to be automatically trimmed if they are a value email address surrounded by whitespace [wb, 2010-03-14]
 * @changes    1.0.0b8   Made the validation on link columns a bit more strict [wb, 2010-03-09]
 * @changes    1.0.0b7   Updated code for the new ORMDatabase and ORMSchema APIs [wb, 2009-10-28]
 * @changes    1.0.0b6   Changed SQL statements to use value placeholders, identifier escaping and schema support [wb, 2009-10-22]
 * @changes    1.0.0b5   Updated to use new ORM::registerInspectCallback() method [wb, 2009-07-13]
 * @changes    1.0.0b4   Updated code for new ORM API [wb, 2009-06-15]
 * @changes    1.0.0b3   Updated code to use new ValidationException::formatField() method [wb, 2009-06-04]
 * @changes    1.0.0b2   Fixed a bug with objectifying number columns [wb, 2008-11-24]
 * @changes    1.0.0b    The initial implementation [wb, 2008-05-27]
 */
class ORMColumn
{
	/**
	 * Columns that should be formatted as email addresses
	 *
	 * @var array<string, array<string, bool>>
	 */
	static private array $email_columns = array();

	/**
	 * Columns that should be formatted as links
	 *
	 * @var array<string, array<string, bool>>
	 */
	static private array $link_columns = array();

	/**
	 * Columns that should be returned as Number objects
	 *
	 * @var array<string, array<string, bool>>
	 */
	static private array $number_columns = array();

	/**
	 * Columns that should store passwords
	 *
	 * @var array<string, array<string, array{min_length: int}>>
	 */
	static private array $password_columns = array();

	/**
	 * Columns that should be formatted as a random string
	 *
	 * @var array<string, array<string, array{type: string, length: int}>>
	 */
	static private array $random_columns = array();


	/**
	 * Composes text using Text if loaded
	 *
	 * @param  string  $message    The message to compose
	 * @return string  The composed and possible translated message
	 */
	static private function compose(string $message): string
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
	 * Sets a column to be formatted as an email address
	 *
	 * @param  mixed  $class   The class name or instance of the class to set the column format
	 * @param  string $column  The column to format as an email address
	 * @return void
	 */
	static public function configureEmailColumn(mixed $class, string $column): void
	{
		$class     = ORM::getClass($class);
		$table     = ORM::tablize($class);
		$schema    = ORMSchema::retrieve($class);
		$data_type = $schema->getColumnInfo($table, $column, 'type');

		$valid_data_types = array('varchar', 'char', 'text');
		if (!in_array($data_type, $valid_data_types)) {
			throw new ProgrammerException(
				'The column specified, %1$s, is a %2$s column. Must be one of %3$s to be set as an email column.',
				$column,
				$data_type,
				join(', ', $valid_data_types)
			);
		}

		ORM::registerActiveRecordMethod(
			$class,
			'set' . Grammar::camelize($column, TRUE),
			array(self::class, 'setEmailColumn')
		);

		if (!ORM::checkHookCallback($class, 'post::validate()', array(self::class, 'validateEmailColumns'))) {
			ORM::registerHookCallback($class, 'post::validate()', array(self::class, 'validateEmailColumns'));
		}

		ORM::registerInspectCallback($class, $column, array(self::class, 'inspect'));

		if (empty(self::$email_columns[$class])) {
			self::$email_columns[$class] = array();
		}

		self::$email_columns[$class][$column] = TRUE;
	}


	/**
	 * Sets a column to be formatted as a link
	 *
	 * @param  mixed  $class   The class name or instance of the class to set the column format
	 * @param  string $column  The column to format as a link
	 * @return void
	 */
	static public function configureLinkColumn(mixed $class, string $column): void
	{
		$class     = ORM::getClass($class);
		$table     = ORM::tablize($class);
		$schema    = ORMSchema::retrieve($class);
		$data_type = $schema->getColumnInfo($table, $column, 'type');

		$valid_data_types = array('varchar', 'char', 'text');
		if (!in_array($data_type, $valid_data_types)) {
			throw new ProgrammerException(
				'The column specified, %1$s, is a %2$s column. Must be one of %3$s to be set as a link column.',
				$column,
				$data_type,
				join(', ', $valid_data_types)
			);
		}

		ORM::registerActiveRecordMethod(
			$class,
			'prepare' . Grammar::camelize($column, TRUE),
			array(self::class, 'prepareLinkColumn')
		);

		if (!ORM::checkHookCallback($class, 'post::validate()', array(self::class, 'validateLinkColumns'))) {
			ORM::registerHookCallback($class, 'post::validate()', array(self::class, 'validateLinkColumns'));
		}

		ORM::registerReflectCallback($class, array(self::class, 'reflect'));
		ORM::registerInspectCallback($class, $column, array(self::class, 'inspect'));

		if (empty(self::$link_columns[$class])) {
			self::$link_columns[$class] = array();
		}

		self::$link_columns[$class][$column] = TRUE;
	}


	/**
	 * Sets a column to be returned as an Number object from calls to `get{ColumnName}()`
	 *
	 * @param  mixed  $class   The class name or instance of the class to set the column format
	 * @param  string $column  The column to return as an Number object
	 * @return void
	 */
	static public function configureNumberColumn(mixed $class, string $column): void
	{
		$class     = ORM::getClass($class);
		$table     = ORM::tablize($class);
		$schema    = ORMSchema::retrieve($class);
		$data_type = $schema->getColumnInfo($table, $column, 'type');

		$valid_data_types = array('integer', 'float');
		if (!in_array($data_type, $valid_data_types)) {
			throw new ProgrammerException(
				'The column specified, %1$s, is a %2$s column. Must be %3$s to be set as a number column.',
				$column,
				$data_type,
				join(', ', $valid_data_types)
			);
		}

		$camelized_column = Grammar::camelize($column, TRUE);

		ORM::registerActiveRecordMethod(
			$class,
			'encode' . $camelized_column,
			array(self::class, 'encodeNumberColumn')
		);

		ORM::registerActiveRecordMethod(
			$class,
			'prepare' . $camelized_column,
			array(self::class, 'prepareNumberColumn')
		);

		ORM::registerReflectCallback($class, array(self::class, 'reflect'));
		ORM::registerInspectCallback($class, $column, array(self::class, 'inspect'));
		ORM::registerObjectifyCallback($class, $column, array(self::class, 'objectifyNumber'));

		if (empty(self::$number_columns[$class])) {
			self::$number_columns[$class] = array();
		}

		self::$number_columns[$class][$column] = TRUE;
	}

	/**
	 * Sets a column to be a password column
	 *
	 * @param  mixed    $class           The class name or instance of the class
	 * @param  string   $column          The column to set as a password column
	 * @param  integer  $minimum_length  The minimum password a user can use
	 * @return void
	 */
	static function configurePasswordColumn(mixed $class, string $column, int $minimum_length): void
	{
		$class     = ORM::getClass($class);
		$table     = ORM::tablize($class);
		$schema    = ORMSchema::retrieve($class);
		$data_type = $schema->getColumnInfo($table, $column, 'type');

		$valid_data_types = array('varchar', 'char', 'text');
		if (!in_array($data_type, $valid_data_types)) {
			throw new ProgrammerException(
				'The column specified, %1$s, is a %2$s column. Must be one of %3$s to be set as a password column.',
				$column,
				$data_type,
				join(', ', $valid_data_types)
			);
		}

		$max_length = $schema->getColumnInfo($table, $column, 'max_length');
		$required_length = 80;
		if ($max_length < $required_length)
		{
			throw new ProgrammerException(
				'The column specified, %1$s, has a maximum length of %2$s. Must be at least %3$s to be set as a password column.',
				$column,
				$max_length,
				$required_length
			);
		}

		ORM::registerActiveRecordMethod(
			$class,
			'verify' . Grammar::camelize($column, TRUE),
			array(self::class, 'verifyPassword')
		);

		if (!ORM::checkHookCallback($class, 'pre::validate()', array(self::class, 'resetPasswordColumn'))) {
			ORM::registerHookCallback($class, 'pre::validate()', array(self::class, 'resetPasswordColumn'));
			ORM::registerHookCallback($class, 'post::validate()', array(self::class, 'validatePasswordColumns'));
		}

		ORM::registerInspectCallback($class, $column, array(self::class, 'inspect'));

		if (empty(self::$password_columns[$class])) {
			self::$password_columns[$class] = array();
		}

		self::$password_columns[$class][$column] = array('min_length' => (int) $minimum_length);
	}


	/**
	 * Sets a column to be a random string column - a random string will be generated when the record is saved
	 *
	 * @param  mixed   $class   The class name or instance of the class
	 * @param  string  $column  The column to set as a random column
	 * @param  string  $type    The type of random string, must be one of: `'alphanumeric'`, `'alpha'`, `'numeric'`, `'hexadecimal'`
	 * @param  integer $length  The length of the random string
	 * @return void
	 */
	static public function configureRandomColumn(mixed $class, string $column, string $type, int $length): void
	{
		$class     = ORM::getClass($class);
		$table     = ORM::tablize($class);
		$schema    = ORMSchema::retrieve($class);
		$data_type = $schema->getColumnInfo($table, $column, 'type');

		$valid_data_types = array('varchar', 'char', 'text');
		if (!in_array($data_type, $valid_data_types)) {
			throw new ProgrammerException(
				'The column specified, %1$s, is a %2$s column. Must be one of %3$s to be set as a random string column.',
				$column,
				$data_type,
				join(', ', $valid_data_types)
			);
		}

		$valid_types = array('alphanumeric', 'alpha', 'numeric', 'hexadecimal');
		if (!in_array($type, $valid_types)) {
			throw new ProgrammerException(
				'The type specified, %1$s, is an invalid type. Must be one of: %2$s.',
				$type,
				join(', ', $valid_types)
			);
		}

		if ($length < 1) {
			throw new ProgrammerException(
				'The length specified, %s, needs to be an integer greater than zero.',
				$length
			);
		}

		ORM::registerActiveRecordMethod(
			$class,
			'generate' . Grammar::camelize($column, TRUE),
			array(self::class, 'generate')
		);

		if (!ORM::checkHookCallback($class, 'pre::validate()', array(self::class, 'setRandomStrings'))) {
			ORM::registerHookCallback($class, 'pre::validate()', array(self::class, 'setRandomStrings'));
		}

		ORM::registerInspectCallback($class, $column, array(self::class, 'inspect'));

		if (empty(self::$random_columns[$class])) {
			self::$random_columns[$class] = array();
		}

		self::$random_columns[$class][$column] = array('type' => $type, 'length' => (int) $length);
	}


	/**
	 * Encodes a number column by calling Number::__toString()
	 *
	 * @internal
	 *
	 * @param  ActiveRecord $object            The ActiveRecord instance
	 * @param  array<array-key, mixed> &$values           The current values
	 * @param  array<array-key, mixed> &$old_values       The old values
	 * @param  array<array-key, mixed> &$related_records  Any records related to this record
	 * @param  array<array-key, mixed> &$cache            The cache array for the record
	 * @param  string        $method_name      The method that was called
	 * @param  array<array-key, mixed> $parameters       The parameters passed to the method
	 * @return string  The encoded number
	 */
	static public function encodeNumberColumn(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache, string $method_name, array $parameters): string
	{
		list ($action, $subject) = ORM::parseMethod($method_name);

		$column      = Grammar::underscorize(is_string($subject) ? $subject : '');
		$class       = get_class($object);
		$schema      = ORMSchema::retrieve($class);
		$table       = ORM::tablize($class);
		$column_info = $schema->getColumnInfo($table, $column);
		$value       = $values[$column];

		if ($value instanceof Number) {
			if (is_array($column_info) && ($column_info['type'] ?? NULL) == 'float') {
				if (isset($parameters[0]) && is_scalar($parameters[0])) {
					$decimal_places = (int) $parameters[0];
				} elseif (isset($column_info['decimal_places']) && is_numeric($column_info['decimal_places'])) {
					$decimal_places = (int) $column_info['decimal_places'];
				} else {
					$decimal_places = 0;
				}
				$value = $value->trunc($decimal_places)->__toString();
			} else {
				$value = $value->__toString();
			}
		}

		$value = is_string($value) ? $value : (is_scalar($value) ? (string) $value : '');

		return HTML::prepare($value);
	}


	/**
	 * Generates a new random value for the column
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
	 * @return string  The newly generated random value
	 */
	static public function generate(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache, string $method_name, array $parameters): string
	{
		list ($action, $subject) = ORM::parseMethod($method_name);

		$column = Grammar::underscorize(is_string($subject) ? $subject : '');
		$class  = get_class($object);
		$table  = ORM::tablize($class);

		$schema = ORMSchema::retrieve($class);
		$db     = ORMDatabase::retrieve($class, 'read');

		$settings = self::$random_columns[$class][$column];

		// Check to see if this is a unique column
		$unique_keys      = $schema->getKeys($table, 'unique');
		$is_unique_column = FALSE;
		$value            = '';
		foreach ($unique_keys as $unique_key) {
			if ($unique_key == array($column)) {
				$is_unique_column = TRUE;
				$sql = "SELECT %r FROM %r WHERE %r = %s";
				do {
					$value = Cryptography::randomString($settings['length'], $settings['type']);
				} while (($result = $db->query($sql, $column, $table, $column, $value)) instanceof Result && $result->countReturnedRows());
			}
		}

		// If is is not a unique column, just generate a value
		if (!$is_unique_column) {
			$value = Cryptography::randomString($settings['length'], $settings['type']);
		}

		ActiveRecord::assign($values, $old_values, $column, $value);

		return $value;
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
	static public function inspect(string $class, string $column, mixed &$metadata): void
	{
		if (!empty(self::$email_columns[$class][$column])) {
			$metadata['feature'] = 'email';
		}

		if (!empty(self::$link_columns[$class][$column])) {
			$metadata['feature'] = 'link';
		}

		if (!empty(self::$random_columns[$class][$column])) {
			$metadata['feature'] = 'random';
		}

		if (!empty(self::$password_columns[$class][$column])) {
			$metadata['feature'] = 'password';
		}

		if (!empty(self::$number_columns[$class][$column])) {
			$metadata['feature'] = 'number';
		}
	}


	/**
	 * Turns a numeric value into an Number object
	 *
	 * @internal
	 *
	 * @param  string $class   The class this value is for
	 * @param  string $column  The column the value is in
	 * @param  mixed  $value   The value
	 * @return mixed  The Number object or raw value
	 */
	static public function objectifyNumber(string $class, string $column, mixed $value): mixed
	{
		if (is_string($value)) {
			$string_value = $value;
		} elseif (is_numeric($value)) {
			$string_value = (string) $value;
		} elseif ($value instanceof Stringable) {
			$string_value = (string) $value;
		} else {
			return $value;
		}

		if (!strlen(trim($string_value))) {
			return $value;
		}

		try {
			return new Number($string_value);

		// If there was some error creating the number object, just return the raw value
		} catch (ExpectedException $e) {
			return $value;
		}
	}


	/**
	 * Prepares a link column so that the link will work properly in an `a` tag
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
	 * @return string  The formatted link
	 */
	static public function prepareLinkColumn(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache, string $method_name, array $parameters): string
	{
		list ($action, $subject) = ORM::parseMethod($method_name);

		$column = Grammar::underscorize(is_string($subject) ? $subject : '');
		$value  = $values[$column];
		$value  = is_string($value) ? $value : (is_scalar($value) ? (string) $value : '');

		// Fix domains that don't have the protocol to start
		if (strlen($value) && !preg_match('#^https?://|^/#iD', $value)) {
			$value = 'http://' . $value;
		}

		$value = HTML::prepare($value);

		if (isset($parameters[0]) && $parameters[0] === TRUE) {
			return '<a href="' . $value . '">' . $value . '</a>';
		}

		return $value;
	}


	/**
	 * Prepares a number column by calling Number::format()
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
	 * @return string  The formatted link
	 */
	static public function prepareNumberColumn(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache, string $method_name, array $parameters): string
	{
		list ($action, $subject) = ORM::parseMethod($method_name);

		$column      = Grammar::underscorize(is_string($subject) ? $subject : '');
		$class       = get_class($object);
		$table       = ORM::tablize($class);
		$schema      = ORMSchema::retrieve($class);
		$column_info = $schema->getColumnInfo($table, $column);
		$value       = $values[$column];

		if ($value instanceof Number) {
			if (is_array($column_info) && ($column_info['type'] ?? NULL) == 'float') {
				if (isset($parameters[0]) && is_scalar($parameters[0])) {
					$decimal_places = (int) $parameters[0];
				} elseif (isset($column_info['decimal_places']) && is_numeric($column_info['decimal_places'])) {
					$decimal_places = (int) $column_info['decimal_places'];
				} else {
					$decimal_places = NULL;
				}
				if ($decimal_places !== NULL) {
					$value = $value->trunc($decimal_places)->format();
				} else {
					$value = $value->format();
				}
			} else {
				$value = $value->format();
			}
		}

		$value = is_string($value) ? $value : (is_scalar($value) ? (string) $value : '');

		return HTML::prepare($value);
	}


	/**
	 * Adjusts the ActiveRecord::reflect() signatures of columns that have been configured in this class
	 *
	 * @internal
	 *
	 * @param  string  $class                 The class to reflect
	 * @param  array<array-key, mixed>   &$signatures           The associative array of `{method name} => {signature}`
	 * @param  boolean $include_doc_comments  If doc comments should be included with the signature
	 * @return void
	 */
	static public function reflect(string $class, mixed &$signatures, bool $include_doc_comments): void
	{

		if (isset(self::$link_columns[$class])) {
			foreach(self::$link_columns[$class] as $column => $enabled) {
				$signature = '';
				if ($include_doc_comments) {
					$signature .= "/**\n";
					$signature .= " * Prepares the value of " . $column . " for output into HTML\n";
					$signature .= " * \n";
					$signature .= " * This method will ensure all links that start with a domain name are preceeded by http://\n";
					$signature .= " * \n";
					$signature .= " * @param  boolean \$create_link  Will cause link to be automatically converted into an [a] tag\n";
					$signature .= " * @return string  The HTML-ready value\n";
					$signature .= " */\n";
				}
				$prepare_method = 'prepare' . Grammar::camelize($column, TRUE);
				$signature .= 'public function ' . $prepare_method . '($create_link=FALSE)';

				$signatures[$prepare_method] = $signature;
			}
		}

		if (isset(self::$number_columns[$class])) {

			$table  = ORM::tablize($class);
			$schema = ORMSchema::retrieve($class);

			foreach(self::$number_columns[$class] as $column => $enabled) {
				$camelized_column = Grammar::camelize($column, TRUE);
				$type             = $schema->getColumnInfo($table, $column, 'type');

				// Get and set methods
				$signature = '';
				if ($include_doc_comments) {
					$signature .= "/**\n";
					$signature .= " * Gets the current value of " . $column . "\n";
					$signature .= " * \n";
					$signature .= " * @return Number  The current value\n";
					$signature .= " */\n";
				}
				$get_method = 'get' . $camelized_column;
				$signature .= 'public function ' . $get_method . '()';

				$signatures[$get_method] = $signature;


				$signature = '';
				if ($include_doc_comments) {
					$signature .= "/**\n";
					$signature .= " * Sets the value for " . $column . "\n";
					$signature .= " * \n";
					$signature .= " * @param  Number|string|integer \$" . $column . "  The new value - don't use floats since they are imprecise\n";
					$signature .= " * @return ActiveRecord  The record object, to allow for method chaining\n";
					$signature .= " */\n";
				}
				$set_method = 'set' . $camelized_column;
				$signature .= 'public function ' . $set_method . '($' . $column . ')';

				$signatures[$set_method] = $signature;

				$signature = '';
				if ($include_doc_comments) {
					$signature .= "/**\n";
					$signature .= " * Encodes the value of " . $column . " for output into an HTML form\n";
					$signature .= " * \n";
					$signature .= " * If the value is an Number object, the ->__toString() method will be called\n";
					$signature .= " * resulting in the value without any thousands separators\n";
					$signature .= " * \n";
					if ($type == 'float') {
						$signature .= " * @param  integer \$decimal_places  The number of decimal places to display - not passing any value or passing NULL will result in the intrisinc number of decimal places being shown\n";
					}
					$signature .= " * @return string  The HTML form-ready value\n";
					$signature .= " */\n";
				}
				$encode_method = 'encode' . $camelized_column;
				$signature .= 'public function ' . $encode_method . '(';
				if ($type == 'float') {
					$signature .= '$decimal_places=NULL';
				}
				$signature .= ')';

				$signatures[$encode_method] = $signature;

				$signature = '';
				if ($include_doc_comments) {
					$signature .= "/**\n";
					$signature .= " * Prepares the value of " . $column . " for output into HTML\n";
					$signature .= " * \n";
					$signature .= " * If the value is an Number object, the ->format() method will be called\n";
					$signature .= " * resulting in the value including thousands separators\n";
					$signature .= " * \n";
					if ($type == 'float') {
						$signature .= " * @param  integer \$decimal_places  The number of decimal places to display - not passing any value or passing NULL will result in the intrisinc number of decimal places being shown\n";
					}
					$signature .= " * @return string  The HTML-ready value\n";
					$signature .= " */\n";
				}
				$prepare_method = 'prepare' . $camelized_column;
				$signature .= 'public function ' . $prepare_method . '(';
				if ($type == 'float') {
					$signature .= '$decimal_places=NULL';
				}
				$signature .= ')';

				$signatures[$prepare_method] = $signature;
			}
		}

		if (isset(self::$password_columns[$class])) {
			foreach(self::$password_columns[$class] as $column => $settings) {
				$signature = '';
				if ($include_doc_comments) {
					$signature .= "/**\n";
					$signature .= " * Verifies the password specified matches the hash in " . $column . "\n";
					$signature .= " * \n";
					$signature .= " * @throws NotFoundException  When password does not match hashed value\n";
					$signature .= " * \n";
					$signature .= " * @param  string \$password  The password to verify against the hashed value\n";
					$signature .= " * @return void\n";
					$signature .= " */\n";
				}
				$verify_method = 'verify' . Grammar::camelize($column, TRUE);
				$signature .= 'public function ' . $verify_method . '()';

				$signatures[$verify_method] = $signature;
			}
		}

		if (isset(self::$random_columns[$class])) {
			foreach(self::$random_columns[$class] as $column => $settings) {
				$signature = '';
				if ($include_doc_comments) {
					$signature .= "/**\n";
					$signature .= " * Generates a new random " . $settings['type'] . " character " . $settings['type'] . " string for " . $column . "\n";
					$signature .= " * \n";
					$signature .= " * If there is a UNIQUE constraint on the column and the value is not unique it will be regenerated until unique\n";
					$signature .= " * \n";
					$signature .= " * @return string  The randomly generated string\n";
					$signature .= " */\n";
				}
				$generate_method = 'generate' . Grammar::camelize($column, TRUE);
				$signature .= 'public function ' . $generate_method . '()';

				$signatures[$generate_method] = $signature;
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
	static public function reset(): void
	{
		self::$email_columns    = array();
		self::$link_columns     = array();
		self::$number_columns   = array();
		self::$password_columns = array();
		self::$random_columns   = array();
	}


	/**
	 * Makes sure only to hash a password field if it has a value
	 *
	 * @internal
	 *
	 * @param  ActiveRecord $self                  The object being stored
	 * @param  array<array-key, mixed> &$values               The current values
	 * @param  array<array-key, mixed> &$old_values           The old values
	 * @param  array<array-key, mixed> &$related_records      Any records related to this record
	 * @param  array<array-key, mixed> &$cache                The cache for the record
	 * @param  array<array-key, mixed> &$validation_messages  An array of ordered validation messages
	 * @return void
	 */
	static function resetPasswordColumn(ActiveRecord $self, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache, mixed &$validation_messages): void
	{
		$class = ORM::getClass($self);

		if (empty(self::$password_columns[$class])) {
			return;
		}

		foreach(self::$password_columns[$class] as $column => $options) {
			$password  = is_string($values[$column]) ? $values[$column] : '';
			$has_value = !empty($password) && UTF8::len($password) > 0;

			if ($has_value && array_key_exists($column, $old_values)) {
				$values[$column] = Cryptography::hashPassword($password);
			}
			if (!$has_value) {
				$old_value      = $old_values[$column] ?? NULL;
				$values[$column] = is_array($old_value) ? ($old_value[0] ?? NULL) : NULL;
			}
		}
	}


	/**
	 * Sets the value for an email column, trimming the value if it is a valid email
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
	 * @return ActiveRecord  The record object, to allow for method chaining
	 */
	static public function setEmailColumn(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache, string $method_name, array $parameters): ActiveRecord
	{
		list ($action, $subject) = ORM::parseMethod($method_name);

		$column = Grammar::underscorize(is_string($subject) ? $subject : '');
		$class  = get_class($object);

		if (count($parameters) < 1) {
			throw new ProgrammerException(
				'The method, %s(), requires at least one parameter',
				$method_name
			);
		}

		$email = is_string($parameters[0]) ? $parameters[0] : '';
		if (preg_match('#^\s*[a-z0-9\\.\'_\\-\\+]+@(?:[a-z0-9\\-]+\.)+[a-z]{2,}\s*$#iD', $email)) {
			$email = trim($email);
		}

		if ($email === '') {
			$email = NULL;
		}

		ActiveRecord::assign($values, $old_values, $column, $email);

		return $object;
	}


	/**
	 * Sets the appropriate column values to a random string if the object is new
	 *
	 * @internal
	 *
	 * @param  ActiveRecord $object            The ActiveRecord instance
	 * @param  array<array-key, mixed> &$values           The current values
	 * @param  array<array-key, mixed> &$old_values       The old values
	 * @param  array<array-key, mixed> &$related_records  Any records related to this record
	 * @param  array<array-key, mixed> &$cache            The cache array for the record
	 * @return void
	 */
	static public function setRandomStrings(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache): void
	{
		if ($object->exists()) {
			return;
		}

		$class = get_class($object);
		$table = ORM::tablize($class);

		foreach (self::$random_columns[$class] as $column => $settings) {
			if (ActiveRecord::hasOld($old_values, $column) && $values[$column]) {
				continue;
			}
			self::generate(
				$object,
				$values,
				$old_values,
				$related_records,
				$cache,
				'generate' . Grammar::camelize($column, TRUE),
				array()
			);
		}
	}


	/**
	 * Validates all email columns
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
	 */
	static public function validateEmailColumns(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache, mixed &$validation_messages): void
	{
		$class = get_class($object);

		if (empty(self::$email_columns[$class])) {
			return;
		}

		foreach (self::$email_columns[$class] as $column => $enabled) {
			if (!is_string($values[$column]) || !strlen($values[$column])) {
				continue;
			}
			if (!preg_match('#^[a-z0-9\\.\'_\\-\\+]+@(?:[a-z0-9\\-]+\.)+[a-z]{2,}$#iD', $values[$column])) {
				$validation_messages[$column] = self::compose(
					'%sPlease enter an email address in the form name@example.com',
					ValidationException::formatField(ORM::getColumnName($class, $column))
				);
			}
		}
	}


	/**
	 * Validates all link columns
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
	 */
	static public function validateLinkColumns(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache, mixed &$validation_messages): void
	{
		$class = get_class($object);

		if (empty(self::$link_columns[$class])) {
			return;
		}

		foreach (self::$link_columns[$class] as $column => $enabled) {
			if (!is_string($values[$column])) {
				continue;
			}

			$ip_regex       = '(?:(?:[01]?\d?\d|2[0-4]\d|25[0-5])\.){3}(?:[01]?\d?\d|2[0-4]\d|25[0-5])';
			$hostname_regex = '[a-z]+(?:[a-z0-9\-]*[a-z0-9]\.?|\.)*';
			$domain_regex   = '([a-z]+([a-z0-9\-]*[a-z0-9])?\.)+[a-z]{2,}';
			if (!preg_match('#^(https?://(' . $ip_regex . '|' . $hostname_regex . ')(?=/|$)|' . $domain_regex . '(?=/|$)|/)#i', $values[$column])) {
				$validation_messages[$column] = self::compose(
					'%sPlease enter a link in the form http://www.example.com',
					ValidationException::formatField(ORM::getColumnName($class, $column))
				);
			}
		}
	}


	/**
	 * Validates all password columns
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
	 */
	static public function validatePasswordColumns(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache, mixed &$validation_messages): void
	{
		$class = get_class($object);

		if (empty(self::$password_columns[$class])) {
			return;
		}

		foreach (self::$password_columns[$class] as $column => $options) {
			$column_name = ORM::getColumnName($class, $column);
			$confirm_column_name = $column_name . ' Confirmation';
			$request_value = Request::get($column);
			$confirm_value = Request::get($column . '_confirmation');
			$request_value = is_string($request_value) ? $request_value : '';
			$confirm_value = is_string($confirm_value) ? $confirm_value : '';

			if (UTF8::len($request_value) > 0 && ActiveRecord::changed($values, $old_values, $column)) {
				if (UTF8::len($request_value) < $options['min_length']) {
					$validation_messages[$column] = self::compose(
						'%1$sPlease enter a value at least %2$s characters long',
						ValidationException::formatField($column_name),
						$options['min_length']
					);
				} elseif (!$confirm_value) {
					$validation_messages[$column] = self::compose(
						'%sPlease enter a value',
						ValidationException::formatField($confirm_column_name)
					);

				} elseif ($request_value != $confirm_value) {
					$validation_messages[$column] = self::compose(
						'%1$sThe value entered does not match %2$s',
						ValidationException::formatField($column_name),
						ValidationException::formatField($confirm_column_name)
					);
				}
			}
		}
	}


	/**
	 * Verifies a password against the hash stored in a password column
	 *
	 * @internal
	 *
	 * @throws NotFoundException  When the password is not valid
	 *
	 * @param  ActiveRecord $object            The ActiveRecord instance
	 * @param  array<array-key, mixed> &$values           The current values
	 * @param  array<array-key, mixed> &$old_values       The old values
	 * @param  array<array-key, mixed> &$related_records  Any records related to this record
	 * @param  array<array-key, mixed> &$cache            The cache array for the record
	 * @param  string        $method_name       The method that was called
	 * @param  array<array-key, mixed> $parameters        The parameters passed to the method
	 * @return void
	 */
	static public function verifyPassword(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache, string $method_name, array $parameters): void
	{
		list ($action, $subject) = ORM::parseMethod($method_name);

		$column = Grammar::underscorize(is_string($subject) ? $subject : '');
		$value  = $values[$column];

		if (
			isset($parameters[0])
			&& is_string($parameters[0])
			&& is_string($value)
			&& Cryptography::checkPasswordHash($parameters[0], $value)
		) {
			return;
		}

		throw new NotFoundException(self::compose('The password specified is invalid'));
	}


	/**
	 * Forces use as a static class
	 *
	 * @return ORMColumn
	 */
	private function __construct() { }
}



/**
 * Copyright (c) 2008-2016 Will Bond <will@flourishlib.com>
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
