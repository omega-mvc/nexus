<?php

declare(strict_types=1);

/**
 * Adds JSON functionality to ActiveRecord and RecordSet
 *
 * @copyright  Copyright (c) 2008-2009 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 *
 * @version    1.0.0b3
 * @changes    1.0.0b3  Removed the `$pointer` parameter from ::toJSONRecordSet() since RecordSet no longer has a
 * pointer [wb, 2010-09-28]
 * @changes    1.0.0b2  Updated the code to remove the `$associate` parameter for the record set method callback [wb,
 * 2009-06-02]
 * @changes    1.0.0b   The initial implementation [wb, 2008-06-25]
 */
class ORMJSON
{
	/**
	 * Adds the method `toJSON()` to ActiveRecord and RecordSet instances
	 *
	 * @return void
	 */
	public static function extend(): void
	{
		ORM::registerReflectCallback(
			'*',
			array(self::class, 'reflect')
		);

		ORM::registerActiveRecordMethod(
			'*',
			'toJSON',
			array(self::class, 'toJSON')
		);

		ORM::registerRecordSetMethod(
			'toJSON',
			array(self::class, 'toJSONRecordSet')
		);
	}


	/**
	 * Adjusts the ActiveRecord::reflect() signatures of columns that have been added by this class
	 *
	 * @internal
	 *
	 * @param  string  $class                 The class to reflect
	 * @param  array<string, string> &$signatures  The associative array of `{method name} => {signature}`
	 * @param  boolean $include_doc_comments  If doc comments should be included with the signature
	 * @return void
	 */
	public static function reflect(string $class, mixed &$signatures, bool $include_doc_comments): void
	{
		$signature = '';
		if ($include_doc_comments) {
			$signature .= "/**\n";
			$signature .= " * Converts the values from the record into a JSON object\n";
			$signature .= " * \n";
			$signature .= " * @return string  The JSON object representation of this record\n";
			$signature .= " */\n";
		}
		$signature .= 'public function toJSON()';

		$signatures['toJSON'] = $signature;
	}


	/**
	 * Returns a JSON object representation of the record
	 *
	 * @internal
	 *
	 * @param  ActiveRecord $object            The ActiveRecord instance
	 * @param  array<string, mixed> &$values           The current values
	 * @param  array<string, mixed> &$old_values       The old values
	 * @param  array<string, mixed> &$related_records  Any records related to this record
	 * @param  array<string, mixed> &$cache            The cache array for the record
	 * @param  string        $method_name       The method that was called
	 * @param  array<int, mixed> $parameters        The parameters passed to the method
	 * @return string  The JSON object that represents the values of this record
	 */
	public static function toJSON(
		ActiveRecord $object,
		mixed &$values,
		mixed &$old_values,
		mixed &$related_records,
		mixed &$cache,
		string $method_name,
		array $parameters
	): string {
		$output = array();
		foreach ($values as $column => $value) {
			if (is_object($value) && is_callable(array($value, '__toString'))) {
				$value = $value->__toString();
			}
			$output[$column] = $value;
		}

		return JSON::encode($output);
	}


	/**
	 * Returns a JSON object representation of a record set
	 *
	 * @internal
	 *
	 * @param  RecordSet $record_set  The RecordSet instance
	 * @param  string     $class       The class of the records
	 * @param  array<string, mixed> &$records    The ActiveRecord objects
	 * @return string  The JSON object that represents an array of all of the ActiveRecord objects
	 */
	public static function toJSONRecordSet(RecordSet $record_set, string $class, mixed &$records): string
	{
		/** @var list<string> $json */
		$json = $record_set->call('toJSON');
		return '[' . join(',', $json) . ']';
	}


	/**
	 * Forces use as a static class
	 *
	 * @return ORMJSON
	 */
	private function __construct()
	{
	}
}



/**
 * Copyright (c) 2008-2009 Will Bond <will@flourishlib.com>
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
