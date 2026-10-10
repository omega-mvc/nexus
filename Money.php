<?php
declare(strict_types=1);
/**
 * Represents a monetary value - USD are supported by default and others can be added via ::defineCurrency()
 *
 * @copyright  Copyright (c) 2008-2010 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 * @link       http://flourishlib.com/Money
 *
 * @version    1.0.0b3
 * @changes    1.0.0b3  Added the `$remove_zero_fraction` parameter to ::format() [wb, 2010-06-09]
 * @changes    1.0.0b2  Fixed a bug with calling ::format() when a format callback is set, fixed `NULL` `$element` handling in ::getCurrencyInfo() [wb, 2009-03-24]
 * @changes    1.0.0b   The initial implementation [wb, 2008-08-10]
 *
 * @phpstan-type MoneyCurrency array<string, int|string>
 */
class Money
{
	/**
	 * The information for each defined currency
	 *
	 * @var array<string, MoneyCurrency>
	 */
	static private array $currencies = array(
		'USD' => array(
			'name'      => 'United States Dollar',
			'symbol'    => '$',
			'precision' => 2,
			'value'     => '1.00000000'
		)
	);

	/**
	 * The ISO code (three letters, e.g. 'USD') for the default currency
	 *
	 * @var string
	 */
	static private ?string $default_currency = NULL;

	/**
	 * A callback to process all money values through
	 *
	 * @var callable(mixed ...): mixed|null
	 */
	static private mixed $format_callback = NULL;

	/**
	 * A callback to remove money formatting and return a decimal number
	 *
	 * @var callable(mixed ...): mixed|null
	 */
	static private mixed $unformat_callback = NULL;


	/**
	 * Allows adding a new currency, or modifying an existing one
	 *
	 * @param string  $iso_code   The ISO code (three letters, e.g. `'USD'`) for the currency
	 * @param string  $name       The name of the currency
	 * @param string  $symbol     The symbol for the currency
	 * @param integer $precision  The number of digits after the decimal separator to store
	 * @param string  $value      The value of the currency relative to some common standard between all currencies
	 * @return void
	 */
	static public function defineCurrency(string $iso_code, string $name, string $symbol, int $precision, string $value): void
	{
		self::$currencies[$iso_code] = array(
			'name'      => $name,
			'symbol'    => $symbol,
			'precision' => $precision,
			'value'     => $value
		);
	}


	/**
	 * Lists all of the defined currencies
	 *
	 * @return array<int, string>  The 3 letter ISO codes for all of the defined currencies
	 */
	static public function getCurrencies(): array
	{
		return array_keys(self::$currencies);
	}


	/**
	 * Allows retrieving information about a currency
	 *
	 * @param string  $iso_code  The ISO code (three letters, e.g. `'USD'`) for the currency
	 * @param string  $element   The element to retrieve: `'name'`, `'symbol'`, `'precision'`, `'value'`
	 * @return ($element is null ? MoneyCurrency : ($element is 'precision' ? int : string))  An associative array of the currency info, or the element specified
	 */
	static public function getCurrencyInfo(string $iso_code, ?string $element=NULL): mixed
	{
		if (!isset(self::$currencies[$iso_code])) {
			throw new ProgrammerException(
				'The currency specified, %1$s, is not a valid currency. Must be one of: %2$s.',
				$iso_code,
				join(', ', array_keys(self::$currencies))
			);
		}

		if ($element === NULL) {
			return self::$currencies[$iso_code];
		}

		$currency_info = self::$currencies[$iso_code];

		if (!isset($currency_info[$element])) {
			throw new ProgrammerException(
				'The element specified, %1$s, is not valid. Must be one of: %2$s.',
				$element,
				join(', ', array_keys($currency_info))
			);
		}

		$value = $currency_info[$element];

		if ($element === 'precision') {
			return (int) $value;
		}

		return (string) $value;
	}


	/**
	 * Gets the default currency
	 *
	 * @return string|null  The ISO code of the default currency
	 */
	static public function getDefaultCurrency(): ?string
	{
		return self::$default_currency;
	}


	/**
	 * Allows setting a callback to translate or modify any return values from ::format()
	 *
	 * @param  callable(mixed...):mixed $callback  The callback to pass all Number objects to. Should accept an Number object, a string currency abbreviation and a boolean indicating if a zero-fraction should be removed - it should return a formatted string.
	 * @return void
	 */
	static public function registerFormatCallback(callable $callback): void
	{
		if (is_string($callback) && strpos($callback, '::') !== FALSE) {
			$callback = Closure::fromCallable($callback);
		}
		self::$format_callback = $callback;
	}


	/**
	 * Allows setting a callback to clean any formatted values so they can be passed to Number
	 *
	 * @param  callable(mixed...):mixed $callback  The callback to pass formatted strings to. Should accept a formatted string and a currency code and return a string suitable to passing to the Number constructor.
	 * @return void
	 */
	static public function registerUnformatCallback(callable $callback): void
	{
		if (is_string($callback) && strpos($callback, '::') !== FALSE) {
			$callback = Closure::fromCallable($callback);
		}
		self::$unformat_callback = $callback;
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
		self::$currencies = array(
			'USD' => array(
				'name'      => 'United States Dollar',
				'symbol'    => '$',
				'precision' => 2,
				'value'     => '1.00000000'
			)
		);
		self::$default_currency  = NULL;
		self::$format_callback   = NULL;
		self::$unformat_callback = NULL;
	}


	/**
	 * Sets the default currency to use when creating Money objects
	 *
	 * @param string  $iso_code  The ISO code (three letters, e.g. `'USD'`) for the new default currency
	 * @return void
	 */
	static public function setDefaultCurrency(string $iso_code): void
	{
		if (!isset(self::$currencies[$iso_code])) {
			throw new ProgrammerException(
				'The currency specified, %1$s, is not a valid currency. Must be one of: %2$s.',
				$iso_code,
				join(', ', array_keys(self::$currencies))
			);
		}

		self::$default_currency = $iso_code;
	}


	/**
	 * The raw monetary value
	 *
	 * @var \Number
	 */
	private Number $amount;

	/**
	 * The ISO code or the currency of this value
	 *
	 * @var string
	 */
	private string $currency;


	/**
	 * Creates the monetary to represent, with an optional currency
	 *
	 * @throws ValidationException  When `$amount` is not a valid number/monetary value
	 *
	 * @param  \Number|string $amount    The monetary value to represent, should never be a float since those are imprecise
	 * @param  string|null    $currency  The currency ISO code (three letters, e.g. `'USD'`) for this value
	 * @return Money
	 */
	public function __construct(mixed $amount, mixed $currency=NULL)
	{
		if ($currency !== NULL && !isset(self::$currencies[$currency])) {
			throw new ProgrammerException(
				'The currency specified, %1$s, is not a valid currency. Must be one of: %2$s.',
				$currency,
				join(', ', array_keys(self::$currencies))
			);
		}

		if ($currency === NULL && self::$default_currency === NULL) {
			throw new ProgrammerException(
				'No currency was specified and no default currency has been set'
			);
		}

		$this->currency = ($currency !== NULL) ? $currency : self::$default_currency;

		$precision = self::getCurrencyInfo($this->currency, 'precision');

		// Unformat any money value
		if (self::$unformat_callback !== NULL) {
			$amount = call_user_func(self::$unformat_callback, $amount, $this->currency);
		} else {
			$amount = str_replace(
				array(
					self::getCurrencyInfo($this->currency, 'symbol'),
					','
				),
				'',
				($amount instanceof Number) ? $amount->__toString() : (string) $amount
			);
		}

		if (!is_int($amount) && !is_float($amount) && !is_string($amount)) {
			throw new ProgrammerException(
				'The amount specified, %s, is not a valid number',
				get_debug_type($amount)
			);
		}

		$this->amount = new Number($amount, $precision);
	}


	/**
	 * All requests that hit this method should be requests for callbacks
	 *
	 * @internal
	 *
	 * @param  string $method  The method to create a callback for
	 * @return array{0: $this, 1: string}  The callback for the method requested
	 */
	public function __get(mixed $method)
	{
		return array($this, $method);
	}


	/**
	 * Returns the monetary value without a currency symbol or thousand separator (e.g. `2000.12`)
	 *
	 * @return string  The monetary value without currency symbol or thousands separator
	 */
	public function __toString()
	{
		return $this->amount->__toString();
	}


	/**
	 * Adds the passed monetary value to the current one
	 *
	 * @throws ValidationException  When `$addend` is not a valid number/monetary value
	 *
	 * @param  Money|string|integer $addend  The money object to add - a string or integer will be converted to the default currency (if defined)
	 * @return Money  The sum of the monetary values in this currency
	 */
	public function add(mixed $addend): Money
	{
		$addend           = $this->makeMoney($addend);
		$converted_addend = $addend->convert($this->currency)->amount;
		$precision        = self::getCurrencyInfo($this->currency, 'precision');
		$new_amount       = $this->amount->add($converted_addend, $precision+1)->round($precision);
		return new Money($new_amount, $this->currency);
	}


	/**
	 * Splits the current value into multiple parts ensuring that the sum of the results is exactly equal to this amount
	 *
	 * This method takes two or more parameters. The parameters should each be
	 * fractions that when added together equal 1.
	 *
	 * @throws ValidationException  When one of the ratios is not a number
	 *
	 * @param  \Number|string $ratio1  The ratio of the first amount to this amount
	 * @param  \Number|string $ratio2  The ratio of the second amount to this amount
	 * @return array<int, Money>  Money objects each with the appropriate ratio of the current amount
	 */
	public function allocate(mixed $ratio1, mixed $ratio2): array
	{
		$ratios = array_map(
			static fn(mixed $ratio): \Number|string => self::normalizeNumberInput($ratio),
			func_get_args()
		);

		$total = new Number('0', 10);
		foreach ($ratios as $ratio) {
			$total = $total->add($ratio);
		}

		if (!$total->eq('1.0')) {
			$ratio_values = array();
			foreach ($ratios as $ratio) {
				$ratio_values[] = ($ratio instanceof Number) ? $ratio->__toString() : (string) $ratio;
			}

			throw new ProgrammerException(
				'The ratios specified (%s) combined are not equal to 1',
				join(', ', $ratio_values)
			);
		}

		$precision = self::getCurrencyInfo($this->currency, 'precision');

		if ($precision == 0) {
			$smallest_amount = new Number('1');
		} else {
			$smallest_amount = new Number('0.' . str_pad('', $precision-1, '0') . '1');
		}
		$smallest_money = new Money($smallest_amount, $this->currency);

		$monies = array();
		$sum    = new Number('0', $precision);

		foreach ($ratios as $ratio) {
			$new_amount = $this->amount->mul($ratio)->trunc($precision);
			$sum        = $sum->add($new_amount, $precision+1)->round($precision);
			$monies[] = new Money($new_amount, $this->currency);
		}

		while ($sum->lt($this->amount)) {
			foreach ($monies as &$money) {
				if ($sum->eq($this->amount)) {
					break 2;
				}
				$money = $money->add($smallest_money);
				$sum   = $sum->add($smallest_amount, $precision+1)->round($precision);
			}
		}

		return $monies;
	}


	/**
	 * Converts this money amount to another currency
	 *
	 * @param  string $new_currency  The ISO code (three letters, e.g. `'USD'`) for the new currency
	 * @return Money  A new Money object representing this amount in the new currency
	 */
	public function convert(string $new_currency): Money
	{
		if ($new_currency == $this->currency) {
			return $this;
		}

		if (!isset(self::$currencies[$new_currency])) {
			throw new ProgrammerException(
				'The currency specified, %1$s, is not a valid currency. Must be one of: %2$s.',
				$new_currency,
				join(', ', array_keys(self::$currencies))
			);
		}

		$currency_value     = self::getCurrencyInfo($this->currency, 'value');
		$new_currency_value = self::getCurrencyInfo($new_currency, 'value');
		$new_precision      = self::getCurrencyInfo($new_currency, 'precision');

		$new_amount = $this->amount->mul($currency_value, 8)->div($new_currency_value, $new_precision+1)->round($new_precision);

		return new Money($new_amount, $new_currency);
	}


	/**
	 * Checks to see if two monetary values are equal
	 *
	 * @throws ValidationException  When `$money` is not a valid number/monetary value
	 *
	 * @param  Money|string|integer $money  The money object to compare to - a string or integer will be converted to the default currency (if defined)
	 * @return boolean  If the monetary values are equal
	 */
	public function eq(mixed $money): bool
	{
		$money = $this->makeMoney($money);
		return $this->amount->eq($money->convert($this->currency)->amount);
	}


	/**
	 * Formats the amount by preceeding the amount with the currency symbol and adding thousands separators
	 *
	 * @param  boolean $remove_zero_fraction  If `TRUE` and all digits after the decimal place are `0`, the decimal place and all zeros are removed
	 * @return string  The formatted (and possibly converted) value
	 */
	public function format(bool $remove_zero_fraction=FALSE): string
	{
		if (self::$format_callback !== NULL) {
			return self::stringify(call_user_func(self::$format_callback, $this->amount, $this->currency, $remove_zero_fraction));
		}

		// We can't use number_format() since it takes a float and we have a
		// string that can not be losslessly converted to a float
		$number   = $this->__toString();
		$parts    = explode('.', $number);

		$integer  = $parts[0];
		$fraction = (!isset($parts[1])) ? '' : $parts[1];

		$sign     = '';
		if ($integer[0] == '-') {
			$sign    = '-';
			$integer = substr($integer, 1);
		}

		$int_sections = array();
		for ($i = strlen($integer)-3; $i > 0; $i -= 3) {
			array_unshift($int_sections, substr($integer, $i, 3));
		}
		array_unshift($int_sections, substr($integer, 0, $i+3));

		$symbol   = self::getCurrencyInfo($this->currency, 'symbol');
		$integer  = join(',', $int_sections);
		$fraction = (strlen($fraction)) ? '.' . $fraction : '';

		if ($remove_zero_fraction && rtrim($fraction, '.0') === '') {
			$fraction = '';
		}

		return $sign . $symbol . $integer . $fraction;
	}


	/**
	 * Returns the Number object representing the amount
	 *
	 * @return \Number  The amount of this monetary value
	 */
	public function getAmount(): Number
	{
		return $this->amount;
	}


	/**
	 * Returns the currency ISO code
	 *
	 * @return string  The currency ISO code (three letters, e.g. `'USD'`)
	 */
	public function getCurrency(): string
	{
		return $this->currency;
	}


	/**
	 * Checks to see if this value is greater than the one passed
	 *
	 * @throws ValidationException  When `$money` is not a valid number/monetary value
	 *
	 * @param  Money|string|integer $money  The money object to compare to - a string or integer will be converted to the default currency (if defined)
	 * @return boolean  If this value is greater than the one passed
	 */
	public function gt(mixed $money): bool
	{
		$money = $this->makeMoney($money);
		return $this->amount->gt($money->convert($this->currency)->amount);
	}


	/**
	 * Checks to see if this value is greater than or equal to the one passed
	 *
	 * @throws ValidationException  When `$money` is not a valid number/monetary value
	 *
	 * @param  Money|string|integer $money  The money object to compare to - a string or integer will be converted to the default currency (if defined)
	 * @return boolean  If this value is greater than or equal to the one passed
	 */
	public function gte(mixed $money): bool
	{
		$money = $this->makeMoney($money);
		return $this->amount->gte($money->convert($this->currency)->amount);
	}


	/**
	 * Checks to see if this value is less than the one passed
	 *
	 * @throws ValidationException  When `$money` is not a valid number/monetary value
	 *
	 * @param  Money|string|integer $money  The money object to compare to - a string or integer will be converted to the default currency (if defined)
	 * @return boolean  If this value is less than the one passed
	 */
	public function lt(mixed $money): bool
	{
		$money = $this->makeMoney($money);
		return $this->amount->lt($money->convert($this->currency)->amount);
	}


	/**
	 * Checks to see if this value is less than or equal to the one passed
	 *
	 * @throws ValidationException  When `$money` is not a valid number/monetary value
	 *
	 * @param  Money|string|integer $money  The money object to compare to - a string or integer will be converted to the default currency (if defined)
	 * @return boolean  If this value is less than or equal to the one passed
	 */
	public function lte(mixed $money): bool
	{
		$money = $this->makeMoney($money);
		return $this->amount->lte($money->convert($this->currency)->amount);
	}


	/**
	 * Turns a string into an Money object if a default currency is defined
	 *
	 * @throws ValidationException  When `$money` is not a valid number/monetary value
	 *
	 * @param  mixed $money  The value to convert to an Money object
	 * @return Money  The converted value
	 */
	private function makeMoney(mixed $money): Money
	{
		if ($money instanceof Money) {
			return $money;
		}

		if (is_object($money)) {
			if (!is_callable(array($money, '__toString'))) {
				throw new ProgrammerException(
					'The money value specified, %s, is not an Money object, integer or string and is thus is invalid for this operation',
					get_debug_type($money)
				);
			}
			$money = $money->__toString();
		} elseif (is_numeric($money)) {
			$money = (string) $money;
		}

		if (!is_string($money)) {
			throw new ProgrammerException(
				'The money value specified, %s, is not an Money object, integer or string and is thus is invalid for this operation',
				get_debug_type($money)
			);
		}

		if (!self::$default_currency) {
			throw new ProgrammerException(
				'A default currency must be set in order to convert strings or integers to Money objects on the fly'
			);
		}

		return new Money($money);
	}


	/**
	 * Mupltiplies this monetary value times the number passed
	 *
	 * @throws ValidationException  When `$multiplicand` is not a valid number
	 *
	 * @param  \Number|string|integer $multiplicand  The number of times to multiply this ammount - don't use a float since they are imprecise
	 * @return Money  The product of the monetary value and the multiplicand passed
	 */
	public function mul(mixed $multiplicand): Money
	{
		$precision  = self::getCurrencyInfo($this->currency, 'precision');
		$new_amount = $this->amount->mul(self::normalizeNumberInput($multiplicand), $precision+1)->round($precision);
		return new Money($new_amount, $this->currency);
	}


	/**
	 * Subtracts the passed monetary value from the current one
	 *
	 * @throws ValidationException  When `$subtrahend` is not a valid number/monetary value
	 *
	 * @param  Money|string|integer $subtrahend  The money object to subtract - a string or integer will be converted to the default currency (if defined)
	 * @return Money  The difference of the monetary values in this currency
	 */
	public function sub(mixed $subtrahend): Money
	{
		$subtrahend           = $this->makeMoney($subtrahend);
		$converted_subtrahend = $subtrahend->convert($this->currency)->amount;
		$precision            = self::getCurrencyInfo($this->currency, 'precision');
		$new_amount           = $this->amount->sub($converted_subtrahend, $precision+1)->round($precision);
		return new Money($new_amount, $this->currency);
	}


	/**
	 * Normalizes a value to a \Number object or a numeric string
	 *
	 * @param mixed $value  The value to normalize
	 * @return \Number|string  The normalized number value
	 */
	static private function normalizeNumberInput(mixed $value): \Number|string
	{
		if ($value instanceof Number || is_string($value)) {
			return $value;
		}

		if (is_int($value) || is_float($value)) {
			return (string) $value;
		}

		throw new ProgrammerException(
			'The value specified, %s, is not a valid number',
			get_debug_type($value)
		);
	}


	/**
	 * Converts a value to a string
	 *
	 * @param mixed $value  The value to convert
	 * @return string  The string version of the value
	 */
	static private function stringify(mixed $value): string
	{
		if (is_string($value)) {
			return $value;
		}

		if ($value instanceof Stringable || is_scalar($value) || $value === NULL) {
			return (string) $value;
		}

		throw new ProgrammerException(
			'The value specified, %s, can not be converted to a string',
			get_debug_type($value)
		);
	}
}



/**
 * Copyright (c) 2008-2010 Will Bond <will@flourishlib.com>
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
