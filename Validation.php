<?php

declare(strict_types=1);

/**
 * Provides validation routines for standalone forms, such as contact forms
 *
 * @copyright  Copyright (c) 2007-2011 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @author     Kerri Gertz [kg] <kerri@imarc.net>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 * @link       http://flourishlib.com/Validation
 *
 * @version    1.0.0b13
 * @changes    1.0.0b13  Fixed return bug in ::checkConditionalRules [kg, 2010-06-19]
 * @changes    1.0.0b12  Fixed some method signatures [wb, 2011-08-24]
 * @changes    1.0.0b11  Fixed ::addCallbackRule() to be able to handle multiple rules per field [wb, 2011-06-02]
 * @changes    1.0.0b10  Fixed ::addRegexRule() to be able to handle multiple rules per field [wb, 2010-08-30]
 * @changes    1.0.0b9   Enhanced all of the add fields methods to accept one field per parameter, or an array of fields [wb, 2010-06-24]
 * @changes    1.0.0b8   Added/fixed support for array-syntax fields names [wb, 2010-06-09]
 * @changes    1.0.0b7   Added the ability to pass an array of replacements to ::addRegexReplacement() and ::addStringReplacement() [wb, 2010-05-31]
 * @changes    1.0.0b6   BackwardsCompatibilityBreak - moved one-or-more required fields from ::addRequiredFields() to ::addOneOrMoreRule(), moved conditional required fields from ::addRequiredFields() to ::addConditionalRule(), changed returned messages array to have field name keys - added lots of functionality [wb, 2010-05-26]
 * @changes    1.0.0b5   Added the `$return_messages` parameter to ::validate() and updated code for new ValidationException API [wb, 2009-09-17]
 * @changes    1.0.0b4   Changed date checking from `strtotime()` to Timestamp for better localization support [wb, 2009-06-01]
 * @changes    1.0.0b3   Updated for new Core API [wb, 2009-02-16]
 * @changes    1.0.0b2   Added support for validating date and URL fields [wb, 2009-01-23]
 * @changes    1.0.0b    The initial implementation [wb, 2007-06-14]
 */
class Validation
{
	/**
	 * Composes text using Text if loaded
	 *
	 * @param  string  $message    The message to compose
	 * @return string  The composed and possible translated message
	 */
	protected static function compose(string $message): string
	{
		$args = array_slice(func_get_args(), 1);
		$safe_args = array();
		foreach ($args as $arg) {
			if (is_scalar($arg) || $arg === null) {
				$safe_args[] = $arg;
			} else {
				throw new ProgrammerException('The values passed to compose() must be scalar values');
			}
		}

		if (class_exists('Text', false)) {
			return call_user_func_array(
				array('Text', 'compose'),
				array($message, $args)
			);
		} else {
			return vsprintf($message, $safe_args);
		}
	}


	/**
	 * Check if a field has a value
	 *
	 * @param  string $key  The key to check for a value
	 * @return boolean  If the key has a value
	 */
	private static function hasValue(string $key): bool
	{
		$value = Request::get($key);
		if (self::stringlike($value)) {
			return true;
		}
		if (is_array($value)) {
			foreach ($value as $individual_value) {
				if (self::stringlike($individual_value)) {
					return true;
				}
			}
		}
		return false;
	}


	/**
	 * Compares the message matching strings by longest first so that the longest matches are made first
	 *
	 * @param  string $a  The first string to compare
	 * @param  string $b  The second string to compare
	 * @return integer  `-1` if `$a` is longer than `$b`, `0` if they are equal length, `1` if `$a` is shorter than `$b`
	 */
	private static function sortMessageMatches(string $a, string $b): int
	{
		if (strlen($a) == strlen($b)) {
			return 0;
		}
		if (strlen($a) > strlen($b)) {
			return -1;
		}
		return 1;
	}


	/**
	 * Returns `TRUE` for non-empty strings, numbers, objects, empty numbers and string-like numbers (such as `0`, `0.0`, `'0'`)
	 *
	 * @param  mixed $value  The value to check
	 * @return boolean  If the value is string-like
	 */
	protected static function stringlike(mixed $value): bool
	{
		if (is_string($value)) {
			return strlen(trim($value)) > 0;
		}

		return is_array($value) || is_object($value) || is_numeric($value);
	}


	/**
	 * Converts a value into a string
	 *
	 * @param  mixed  $value  The value to convert
	 * @return string  The value as a string
	 */
	private static function stringValue(mixed $value): string
	{
		if (is_string($value)) {
			return $value;
		}
		if (is_scalar($value) || $value === null) {
			return (string) $value;
		}
		if ($value instanceof Stringable) {
			return $value->__toString();
		}
		throw new ProgrammerException('The value could not be converted to a string');
	}


	/**
	 * Rules that run through a callback
	 *
	 * @var array<string, array<int, array{callback: callable(mixed...):mixed, message: string}>>
	 */
	private array $callback_rules = array();

	/**
	 * Rules for conditionally requiring fields
	 *
	 * @var array<int, array{main_fields: array<int, string>, conditional_values: array<int, mixed>|null, conditional_fields: array<int, string>}>
	 */
	private array $conditional_rules = array();

	/**
	 * Fields that should be valid dates
	 *
	 * @var array<int, string>
	 */
	private array $date_fields = array();

	/**
	 * An array for custom field names
	 *
	 * @var array<string, ?string>
	 */
	private array $field_names = array();

	/**
	 * File upload rules
	 *
	 * @var array<int, array{field: string, index: string|int|null, uploader: Upload}>
	 */
	private array $file_upload_rules = array();

	/**
	 * An array for ordering the fields in the resulting message
	 *
	 * @var array<int, string>
	 */
	private array $message_order = array();

	/**
	 * Rules for at least one field of multiple having a value
	 *
	 * @var array<int, array<int, string>>
	 */
	private array $one_or_more_rules = array();

	/**
	 * Rules for exactly one field of multiple having a value
	 *
	 * @var array<int, array<int, string>>
	 */
	private array $only_one_rules = array();

	/**
	 * Regular expression replacements for the validation messages
	 *
	 * @var array<string, string>
	 */
	private array $regex_replacements = array();

	/**
	 * Rules to validate fields via regular expressions
	 *
	 * @var array<string, array<int, array{regex: string, message: string}>>
	 */
	private array $regex_rules = array();

	/**
	 * The fields to be required
	 *
	 * @var array<int, string>
	 */
	private array $required_fields = array();

	/**
	 * String replacements for the validation messages
	 *
	 * @var array<string, string>
	 */
	private array $string_replacements = array();

	/**
	 * Rules for validating a field against a set of valid values
	 *
	 * @var array<string, array<int, mixed>>
	 */
	private array $valid_values_rules = array();


	/**
	 * All requests that hit this method should be requests for callbacks
	 *
	 * @internal
	 *
	 * @param  string $method  The method to create a callback for
	 * @return array{Validation, string}  The callback for the method requested
	 */
	public function __get(mixed $method)
	{
		return array($this, $method);
	}


	/**
	 * Adds fields to be checked for 1/0, t/f, true/false, yes/no
	 *
	 * @param  string $field    A field that should contain a boolean value
	 * @param  string ...$fields  Additional fields to check
	 * @return Validation  The validation object, to allow for method chaining
	 */
	public function addBooleanFields(string $field, string ...$fields): Validation
	{
		foreach (array_merge(array($field), $fields) as $arg) {
			$this->addRegexRule($arg, '#^0|1|t|f|true|false|yes|no$#iD', 'Please enter Yes or No');
		}

		return $this;
	}


	/**
	 * Adds a callback validation of a field, with a custom error message
	 *
	 * @param  string   $field     The field to test with the callback
	 * @param  callable(mixed...):mixed $callback  The callback to test the value with - this callback should accept a single string parameter and return a boolean
	 * @param  string   $message   The error message to return if the regular expression does not match the value
	 * @return Validation  The validation object, to allow for method chaining
	 */
	public function addCallbackRule(string $field, callable $callback, string $message): Validation
	{
		if (!isset($this->callback_rules[$field])) {
			$this->callback_rules[$field] = array();
		}
		$this->callback_rules[$field][] = array(
			'callback' => $callback,
			'message'  => $message
		);

		return $this;
	}


	/**
	 * Adds fields to be conditionally required if another field has any value, or specific values
	 *
	 * @param  string|array<int,string>  $main_fields          The fields(s) to check for a value
	 * @param  mixed                    $conditional_values   If `NULL`, any value in the main field(s) will trigger the conditional field(s), otherwise the value must match this scalar value or be present in the array of values
	 * @param  string|array<int,string>  $conditional_fields  The field(s) that are to be required
	 * @return Validation  The validation object, to allow for method chaining
	 */
	public function addConditionalRule(string|array $main_fields, mixed $conditional_values, string|array $conditional_fields): Validation
	{
		$main_fields      = array_values(is_array($main_fields) ? $main_fields : array($main_fields));
		$conditional_fields = array_values(is_array($conditional_fields) ? $conditional_fields : array($conditional_fields));
		if ($conditional_values !== null) {
			$conditional_values = array_values(is_array($conditional_values) ? $conditional_values : array($conditional_values));
		}

		$this->conditional_rules[] = array(
			'main_fields'        => $main_fields,
			'conditional_values' => $conditional_values,
			'conditional_fields' => $conditional_fields
		);

		return $this;
	}


	/**
	 * Adds form fields to the list of fields to be blank or a valid date
	 *
	 * Use ::addRequiredFields() disallow blank values.
	 *
	 * @param  string $field    A field that should contain a valid date
	 * @param  string ...$fields  Additional fields to check
	 * @return Validation  The validation object, to allow for method chaining
	 */
	public function addDateFields(string $field, string ...$fields): Validation
	{
		$this->date_fields = array_merge($this->date_fields, array_values(array_merge(array($field), $fields)));

		return $this;
	}


	/**
	 * Adds form fields to the list of fields to be blank or a valid email address
	 *
	 * Use ::addRequiredFields() disallow blank values.
	 *
	 * @param  string $field    A field that should contain a valid email address
	 * @param  string ...$fields  Additional fields to check
	 * @return Validation  The validation object, to allow for method chaining
	 */
	public function addEmailFields(string $field, string ...$fields): Validation
	{
		foreach (array_merge(array($field), $fields) as $arg) {
			$this->addRegexRule($arg, Email::EMAIL_REGEX, 'Please enter an email address in the form name@example.com');
		}

		return $this;
	}


	/**
	 * Adds form fields to be checked for email injection
	 *
	 * Every field that is included in email headers should be passed to this
	 * method.
	 *
	 * @param  string $field    A field to be checked for email injection
	 * @param  string ...$fields  Additional fields to check
	 * @return Validation  The validation object, to allow for method chaining
	 */
	public function addEmailHeaderFields(string $field, string ...$fields): Validation
	{
		foreach (array_merge(array($field), $fields) as $arg) {
			$this->addRegexRule($arg, '#^[^\r\n]*$#D', 'Line breaks are not allowed');
		}

		return $this;
	}


	/**
	 * Add a file upload field to be validated using an Upload object
	 *
	 * @param  string|int|Upload|null  $index    The index for array file upload fields, or the uploader object
	 * @param  Upload|null           $uploader   The uploader to validate the field with
	 * @return Validation  The validation object, to allow for method chaining
	 */
	public function addFileUploadRule(string $field, string|int|Upload|null $index = null, ?Upload $uploader = null): Validation
	{
		if ($uploader === null && $index instanceof Upload) {
			$uploader = $index;
			$index    = null;
		}
		if ($uploader === null) {
			throw new ProgrammerException('No uploader was specified for the file upload rule');
		}

		if ($index !== null && !is_string($index) && !is_int($index)) {
			throw new ProgrammerException('The file upload index must be a string, integer or null');
		}

		$this->file_upload_rules[] = array(
			'field'    => $field,
			'index'    => $index,
			'uploader' => $uploader
		);

		return $this;
	}


	/**
	 * Adds fields to be checked for float values
	 *
	 * @param  string $field    A field that should contain a float value
	 * @param  string ...$fields  Additional fields to check
	 * @return Validation  The validation object, to allow for method chaining
	 */
	public function addFloatFields(string $field, string ...$fields): Validation
	{
		foreach (array_merge(array($field), $fields) as $arg) {
			$this->addRegexRule($arg, '#^([+\-]?)(?:\d*\.\d+|\d+\.?)(?:e([+\-]?)(\d+))?$#iD', 'Please enter a number');
		}

		return $this;
	}


	/**
	 * Adds fields to be checked for integer values
	 *
	 * @param  string $field    A field that should contain an integer value
	 * @param  string ...$fields  Additional fields to check
	 * @return Validation  The validation object, to allow for method chaining
	 */
	public function addIntegerFields(string $field, string ...$fields): Validation
	{
		foreach (array_merge(array($field), $fields) as $arg) {
			$this->addRegexRule($arg, '#^[+\-]?\d+(?:e[+]?\d+)?$#iD', 'Please enter a whole number');
		}

		return $this;
	}


	/**
	 * Adds a rule to make sure at least one field of multiple has a value
	 *
	 * @param  string $field    One of the fields to check for a value
	 * @param  string $field_2  Another field to check for a value
	 * @param  string ...$fields  Additional fields to check
	 * @return Validation  The validation object, to allow for method chaining
	 */
	public function addOneOrMoreRule(string $field, string $field_2, string ...$fields): Validation
	{
		$this->one_or_more_rules[] = array_values(array_merge(array($field, $field_2), $fields));

		return $this;
	}


	/**
	 * Adds a rule to make sure exactly one field of multiple has a value
	 *
	 * @param  string $field    One of the fields to check for a value
	 * @param  string $field_2  Another field to check for a value
	 * @param  string ...$fields  Additional fields to check
	 * @return Validation  The validation object, to allow for method chaining
	 */
	public function addOnlyOneRule(string $field, string $field_2, string ...$fields): Validation
	{
		$this->only_one_rules[] = array_values(array_merge(array($field, $field_2), $fields));

		return $this;
	}


	/**
	 * Adds a call to [http://php.net/preg_replace `preg_replace()`] for each message
	 *
	 * Replacement is done right before the messages are reordered and returned.
	 *
	 * If a message is an empty string after replacement, it will be
	 * removed from the list of messages.
	 *
	 * @param  string $search   The PCRE regex to search for - see http://php.net/pcre for details
	 * @param  string $replace  The string to replace with - all $ and \ are used in back references and must be escaped with a \ when meant literally
	 * @return Validation  The validation object, to allow for method chaining
	 */
	public function addRegexReplacement(string $search, ?string $replace = null): Validation
	{
		$this->regex_replacements[$search] = $replace ?? '';

		return $this;
	}


	/**
	 * Adds regular expression validation of a field, with a custom error message
	 *
	 * @param  string $field    The field to test with the regular expression
	 * @param  string $regex    The PCRE regex to search for - see http://php.net/pcre for details
	 * @param  string $message  The error message to return if the regular expression does not match the value
	 * @return Validation  The validation object, to allow for method chaining
	 */
	public function addRegexRule(string $field, string $regex, string $message): Validation
	{
		if (!isset($this->regex_rules[$field])) {
			$this->regex_rules[$field] = array();
		}
		$this->regex_rules[$field][] = array(
			'regex'   => $regex,
			'message' => $message
		);

		return $this;
	}


	/**
	 * Adds form fields to be required
	 *
	 * @param  string $field    A field to require a value for
	 * @param  string ...$fields  Additional fields to require a value for
	 * @return Validation  The validation object, to allow for method chaining
	 */
	public function addRequiredFields(string $field, string ...$fields): Validation
	{
		$this->required_fields = array_merge($this->required_fields, array_values(array_merge(array($field), $fields)));

		return $this;
	}


	/**
	 * Adds a call to [http://php.net/str_replace `str_replace()`] for each message
	 *
	 * Replacement is done right before the messages are reordered and returned.
	 *
	 * If a message is an empty string after replacement, it will be
	 * removed from the list of messages.
	 *
	 * @param  string $search   The string to search for
	 * @param  string $replace  The string to replace with
	 * @return Validation  The validation object, to allow for method chaining
	 */
	public function addStringReplacement(string $search, ?string $replace = null): Validation
	{
		$this->string_replacements[$search] = $replace ?? '';

		return $this;
	}


	/**
	 * Adds form fields to the list of fields to be blank or a valid URL
	 *
	 * Use ::addRequiredFields() disallow blank values.
	 *
	 * @param  string $field    A field that should contain a valid URL
	 * @param  string ...$fields  Additional fields to check
	 * @return Validation  The validation object, to allow for method chaining
	 */
	public function addURLFields(string $field, string ...$fields): Validation
	{
		$ip_regex       = '(?:(?:[01]?\d?\d|2[0-4]\d|25[0-5])\.){3}(?:[01]?\d?\d|2[0-4]\d|25[0-5])';
		$hostname_regex = '[a-z]+(?:[a-z0-9\-]*[a-z0-9]\.?|\.)*';
		$domain_regex   = '([a-z]+([a-z0-9\-]*[a-z0-9])?\.)+[a-z]{2,}';
		$regex          = '#^(https?://(' . $ip_regex . '|' . $hostname_regex . ')(?=/|$)|' . $domain_regex . '(?=/|$)|/)#i';

		foreach (array_merge(array($field), $fields) as $arg) {
			$this->addRegexRule($arg, $regex, 'Please enter a URL in the form http://www.example.com/page');
		}

		return $this;
	}


	/**
	 * Adds a rule to make sure a field has one of the specified valid values
	 *
	 * A strict comparison will be made from the string request value to the
	 * array of valid values.
	 *
	 * @param  string            $field         The field to check the value of
	 * @param  array<int, mixed>  $valid_values  The valid values
	 * @return Validation  The validation object, to allow for method chaining
	 */
	public function addValidValuesRule(string $field, array $valid_values): Validation
	{
		$this->valid_values_rules[$field] = $this->castToStrings($valid_values);

		return $this;
	}


	/**
	 * Converts an array of values to string, recursively
	 *
	 * @param  array<array-key, mixed> $values  An array of values to cast to strings
	 * @return array<int, mixed>  The values, casted to strings, but preserving multi-dimensional arrays
	 */
	private function castToStrings(array $values): array
	{
		$casted_values = array();
		foreach ($values as $value) {
			if (is_object($value)) {
				if (method_exists($value, '__toString')) {
					$casted_values[] = $value->__toString();
				} else {
					throw new ProgrammerException('The value could not be converted to a string');
				}
			} elseif (is_array($value)) {
				$casted_values[] = $this->castToStrings($value);
			} else {
				if (!is_scalar($value) && $value !== null) {
					throw new ProgrammerException('The value could not be converted to a string');
				}
				$casted_values[] = (string) $value;
			}
		}
		return $casted_values;
	}


	/**
	 * Runs all callback validation rules
	 *
	 * @param  array<string, string> &$messages  The messages to display to the user
	 * @return void
	 */
	private function checkCallbackRules(array &$messages): void
	{
		foreach ($this->callback_rules as $field => $rules) {
			$value = Request::get($field);
			foreach ($rules as $rule) {
				if (self::stringlike($value) && !call_user_func($rule['callback'], $value)) {
					$messages[$field] = self::compose(
						'%s' . $rule['message'],
						ValidationException::formatField($this->makeFieldName($field))
					);
				}
			}
		}
	}


	/**
	 * Checks the conditional validation rules
	 *
	 * @param  array<string, string> &$messages  The messages to display to the user
	 * @return void
	 */
	private function checkConditionalRules(array &$messages): void
	{
		foreach ($this->conditional_rules as $rule) {
			$check_for_missing_values = false;

			foreach ($rule['main_fields'] as $main_field) {
				$matches_conditional_value = $rule['conditional_values'] !== null && in_array(Request::get($main_field), $rule['conditional_values']);
				$has_some_value            = $rule['conditional_values'] === null && self::hasValue($main_field);
				if ($matches_conditional_value || $has_some_value) {
					$check_for_missing_values = true;
					break;
				}
			}

			if (!$check_for_missing_values) {
				continue;
			}

			foreach ($rule['conditional_fields'] as $conditional_field) {
				if (self::hasValue($conditional_field)) {
                    continue;
                }
				$messages[$conditional_field] = self::compose(
					'%sPlease enter a value',
					ValidationException::formatField($this->makeFieldName($conditional_field))
				);
			}
		}
	}


	/**
	 * Validates the date fields, requiring that any date fields that have a value that can be interpreted as a date
	 *
	 * @param  array<string, string> &$messages  The messages to display to the user
	 * @return void
	 */
	private function checkDateFields(array &$messages): void
	{
		foreach ($this->date_fields as $date_field) {
			$request_value = Request::get($date_field);
			$value = trim(is_string($request_value) ? $request_value : (is_scalar($request_value) ? (string) $request_value : ''));
			if (self::stringlike($value)) {
				try {
					new Timestamp($value);
				} catch (ValidationException $e) {
					$messages[$date_field] = self::compose(
						'%sPlease enter a date',
						ValidationException::formatField($this->makeFieldName($date_field))
					);
				}
			}
		}
	}


	/**
	 * Checks the file upload validation rules
	 *
	 * @param  array<string, string> &$messages  The messages to display to the user
	 * @return void
	 */
	private function checkFileUploadRules(array &$messages): void
	{
		foreach ($this->file_upload_rules as $rule) {
			$message = $rule['uploader']->validate($rule['field'], $rule['index'], true);
			if ($message) {
				$field = $rule['index'] === null ? $rule['field'] : $rule['field'] . '[' . $rule['index'] . ']';
				$messages[$field] = self::compose(
					'%s' . $message,
					ValidationException::formatField($this->makeFieldName($field))
				);
			}
		}
	}


	/**
	 * Ensures all of the one-or-more rules is met
	 *
	 * @param  array<string, string> &$messages  The messages to display to the user
	 * @return void
	 */
	private function checkOneOrMoreRules(array &$messages): void
	{
		foreach ($this->one_or_more_rules as $fields) {
			$found = false;
			foreach ($fields as $field) {
				if (self::hasValue($field)) {
					$found = true;
					break;
				}
			}
			if (!$found) {
				$messages[join(',', $fields)] = self::compose(
					'%sPlease enter a value for at least one',
					ValidationException::formatField(join(', ', array_map(array($this, 'makeFieldName'), $fields)))
				);
			}
		}
	}


	/**
	 * Ensures all of the only-one rules is met
	 *
	 * @param  array<string, string> &$messages  The messages to display to the user
	 * @return void
	 */
	private function checkOnlyOneRules(array &$messages): void
	{
		foreach ($this->only_one_rules as $fields) {
			$found = false;
			foreach ($fields as $field) {
				if (self::hasValue($field)) {
					if ($found) {
						$messages[join(',', $fields)] = self::compose(
							'%sPlease enter a value for only one',
							ValidationException::formatField(join(', ', array_map(array($this, 'makeFieldName'), $fields)))
						);
						continue 2;
					}
					$found = true;
				}
			}
			if (!$found) {
				$messages[join(',', $fields)] = self::compose(
					'%sPlease enter a value for one',
					ValidationException::formatField(join(', ', array_map(array($this, 'makeFieldName'), $fields)))
				);
			}
		}
	}


	/**
	 * Runs all regex validation rules
	 *
	 * @param  array<string, string> &$messages  The messages to display to the user
	 * @return void
	 */
	private function checkRegexRules(array &$messages): void
	{
		foreach ($this->regex_rules as $field => $rules) {
			$value = Request::get($field);
			foreach ($rules as $rule) {
				if (self::stringlike($value) && !preg_match($rule['regex'], self::stringValue($value))) {
					$messages[$field] = self::compose(
						'%s' . $rule['message'],
						ValidationException::formatField($this->makeFieldName($field))
					);
				}
			}
		}
	}


	/**
	 * Validates the required fields, adding any missing fields to the messages array
	 *
	 * @param  array<string, string> &$messages  The messages to display to the user
	 * @return void
	 */
	private function checkRequiredFields(array &$messages): void
	{
		foreach ($this->required_fields as $required_field) {
			if (!self::hasValue($required_field)) {
				$messages[$required_field] = self::compose(
					'%sPlease enter a value',
					ValidationException::formatField($this->makeFieldName($required_field))
				);
			}
		}
	}


	/**
	 * Runs all valid-values rules
	 *
	 * @param  array<string, string> &$messages  The messages to display to the user
	 * @return void
	 */
	private function checkValidValuesRules(array &$messages): void
	{
		foreach ($this->valid_values_rules as $field => $valid_values) {
			$value = Request::get($field);
			if (self::stringlike($value) && !in_array($value, $valid_values, true)) {
				$messages[$field] = self::compose(
					'%1$sPlease choose from one of the following: %2$s',
					ValidationException::formatField($this->makeFieldName($field)),
					$this->joinRecursive(', ', $valid_values)
				);
			}
		}
	}


	/**
	 * Joins a multi-dimensional array recursively
	 *
	 * @param array<array-key, mixed> $values  The array of values to join together
	 * @return string  The joined array
	 */
	private function joinRecursive(string $glue, array $values): string
	{
		$joined = array();
		foreach ($values as $value) {
			if (is_array($value)) {
				$joined[] = '(' . $this->joinRecursive($glue, $value) . ')';
			} else {
				$joined[] = self::stringValue($value);
			}
		}

		return join($glue, $joined);
	}

	/**
	 * Creates the name for a field taking into account custom field names
	 *
	 * @param string $field  The field to get the name for
	 * @return string  The field name
	 */
	private function makeFieldName(string $field): string
	{
		if (isset($this->field_names[$field])) {
			return $this->field_names[$field];
		}

		$suffix = '';
		$bracket_pos = strpos($field, '[');
		if ($bracket_pos !== false) {
			$array_dereference = substr($field, $bracket_pos);
			$field             = substr($field, 0, $bracket_pos);

			preg_match_all('#(?<=\[)[^\[\]]+(?=\])#', $array_dereference, $array_keys, PREG_SET_ORDER);
			$array_keys = array_map('current', $array_keys);

			foreach ($array_keys as $array_key) {
				if (is_numeric($array_key)) {
					$suffix .= ' #' . ($array_key + 1);
				} else {
					$suffix .= ' ' . Grammar::humanize($array_key);
				}
			}
		}

		return Grammar::humanize($field) . $suffix;
	}


	/**
	 * Reorders an array of messages based on the requested order
	 *
	 * @param  array<string, string>  $messages  An array of the messages
	 * @return array<string, string>  The reordered messages
	 */
	private function reorderMessages(array $messages): array
	{
		if (!$this->message_order) {
			return $messages;
		}

		$ordered_items = array_fill(0, sizeof($this->message_order), array());
		$other_items   = array();

		foreach ($messages as $key => $message) {
			foreach ($this->message_order as $num => $match_string) {
				if (UTF8::ipos($message, $match_string) !== false) {
					$ordered_items[$num][$key] = $message;
					continue 2;
				}
			}

			$other_items[$key] = $message;
		}

		$final_list = array();
		foreach ($ordered_items as $ordered_item) {
			$final_list = array_merge($final_list, $ordered_item);
		}
		return array_merge($final_list, $other_items);
	}


	/**
	 * Allows overriding the default name used for a field in the error message
	 *
	 * By default, all fields are referred to by the field name run through
	 * Grammar::humanize(). This may not be correct for acronyms or complex
	 * field names.
	 *
	 * @param  string $field  The field to set the custom name for
	 * @param  string $name   The custom name for the field
	 * @return Validation  The validation object, to allow for method chaining
	 */
	public function overrideFieldName(string $field, ?string $name = null): Validation
	{
		$this->field_names[$field] = $name;

		return $this;
	}


	/**
	 * Allows setting the order that the individual errors in a message will be displayed
	 *
	 * All string comparisons during the reordering process are done in a
	 * case-insensitive manner.
	 *
	 * @param  string      $match    The string match to order first
	 * @param  string|null $match_2  The string match to order second
	 * @param  string|null ...$matches  Additional matches
	 * @return Validation  The validation object, to allow for method chaining
	 */
	public function setMessageOrder(string $match, ?string $match_2 = null, ?string ...$matches): Validation
	{
		$args = array($match);
		if ($match_2 !== null) {
			$args[] = $match_2;
		}
		foreach ($matches as $m) {
			if ($m !== null) {
				$args[] = $m;
			}
		}

		uasort($args, array(self::class, 'sortMessageMatches'));
		$this->message_order = $args;

		return $this;
	}


	/**
	 * Checks for required fields, email field formatting and email header injection using values previously set
	 *
	 * @throws ValidationException  When one of the options set for the object is violated
	 *
	 * @param  boolean $return_messages     If an array of validation messages should be returned instead of an exception being thrown
	 * @param  boolean $remove_field_names  If field names should be removed from the returned messages, leaving just the message itself
	 * @return ?array<string, string>  NULL when validation passes, otherwise an array of validation messages when $return_messages is TRUE
	 */
	public function validate(bool $return_messages = false, bool $remove_field_names = false): ?array
	{
		if (
            !$this->callback_rules &&
			  !$this->conditional_rules &&
			  !$this->date_fields &&
			  !$this->file_upload_rules &&
			  !$this->one_or_more_rules &&
			  !$this->only_one_rules &&
			  !$this->regex_rules &&
			  !$this->required_fields &&
			  !$this->valid_values_rules
        ) {
			throw new ProgrammerException(
				'No fields or rules have been added for validation'
			);
		}

		$messages = array();

		$this->checkRequiredFields($messages);
		$this->checkFileUploadRules($messages);
		$this->checkConditionalRules($messages);
		$this->checkOneOrMoreRules($messages);
		$this->checkOnlyOneRules($messages);
		$this->checkValidValuesRules($messages);
		$this->checkDateFields($messages);
		$this->checkRegexRules($messages);
		$this->checkCallbackRules($messages);

		if ($this->regex_replacements) {
			$regex_search  = array_keys($this->regex_replacements);
			$regex_replace = array_values($this->regex_replacements);
			foreach ($messages as $key => $message) {
				$messages[$key] = preg_replace($regex_search, $regex_replace, $message) ?? $message;
			}
		}
		if ($this->string_replacements) {
			$string_search  = array_keys($this->string_replacements);
			$string_replace = array_values($this->string_replacements);
			foreach ($messages as $key => $message) {
				$messages[$key] = str_replace($string_search, $string_replace, $message);
			}
		}

		$messages = $this->reorderMessages($messages);

		if ($return_messages) {
			if ($remove_field_names) {
				foreach ($messages as $key => $message) {
					$messages[$key] = ValidationException::removeFieldName($message);
				}
			}
			return $messages;
		}

		if ($messages) {
			throw new ValidationException(
				'The following problems were found:',
				$messages
			);
		}

		return null;
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
