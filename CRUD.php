<?php

declare(strict_types=1);

/**
 * Provides miscellaneous functionality for [http://en.wikipedia.org/wiki/Create,_read,_update_and_delete CRUD-like] pages
 *
 * @copyright  Copyright (c) 2007-2009 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 * @link       http://flourishlib.com/CRUD
 *
 * @version    1.0.0b5
 * @changes    1.0.0b5  Updated class to use new Session API [wb, 2009-10-23]
 * @changes    1.0.0b4  Updated class to use new Session API [wb, 2009-05-08]
 * @changes    1.0.0b3  Backwards Compatiblity Break - moved ::printOption() to HTML::printOption(), ::showChecked() to HTML::showChecked(), ::removeListItems() and ::reorderListItems() to FlourishException::splitMessage(), ::generateRequestToken() to Request::generateCSRFToken(), and ::validateRequestToken() to Request::validateCSRFToken() [wb, 2009-05-08]
 * @changes    1.0.0b2  Fixed a bug preventing loaded search values from being included in redirects [wb, 2009-03-18]
 * @changes    1.0.0b   The initial implementation [wb, 2007-06-14]
 */
class CRUD
{
	/**
	 * Any values that were loaded from the session, used for redirection
	 *
	 * @var array<string, mixed>
	 */
	private static array $loaded_values = array();

	/**
	 * The current row number for alternating rows
	 *
	 * @var integer
	 */
	private static int $row_number = 1;

	/**
	 * The values for a search form
	 *
	 * @var array<string, mixed>
	 */
	private static array $search_values = array();

	/**
	 * The column to sort by
	 *
	 * @var string|null
	 */
	private static ?string $sort_column = null;

	/**
	 * The direction to sort
	 *
	 * @var string|null
	 */
	private static ?string $sort_direction = null;


	/**
	 * Return the string `'sorted'` if `$column` is the column that is currently being sorted by, otherwise returns `''`
	 *
	 * This method will only be useful if used with the other sort methods
	 * ::printSortableColumn(), ::getSortColumn() and ::getSortDirection().
	 *
	 * @param  string $column  The column to check
	 * @return string  The CSS class for the column, either `''` or `'sorted'`
	 */
	public static function getColumnClass(string $column): string
	{
		if (self::$sort_column == $column) {
			return 'sorted';
		}
		return '';
	}


	/**
	 * Returns the previous values for the specified search field
	 *
	 * @param  string $column  The column to get the value for
	 * @return mixed  The previous value
	 */
	private static function getPreviousSearchValue(string $column): mixed
	{
		return Session::get(__CLASS__ . '::' . URL::get() . '::previous_search::' . $column, null);
	}


	/**
	 * Return the previous sort column, if one exists
	 *
	 * @return string|null  The previous sort column, or `NULL` if none was saved
	 */
	private static function getPreviousSortColumn(): ?string
	{
		$previous_sort_column = Session::get(__CLASS__ . '::' . URL::get() . '::previous_sort_column', null);
		return is_string($previous_sort_column) ? $previous_sort_column : null;
	}


	/**
	 * Return the previous sort direction, if one exists
	 *
	 * @return string|null  The previous sort direction, or `NULL` if none was saved
	 */
	private static function getPreviousSortDirection(): ?string
	{
		$previous_sort_direction = Session::get(__CLASS__ . '::' . URL::get() . '::previous_sort_direction', null);
		return is_string($previous_sort_direction) ? $previous_sort_direction : null;
	}


	/**
	 * Returns a CSS class name for a row
	 *
	 * Will return `'even'`, `'odd'`, or `'highlighted'` if the two parameters
	 * are equal and not `NULL`. The first call to this method will return
	 * the appropriate class concatenated with `' first'`.
	 *
	 * @param  mixed $row_value       The value from the row
	 * @param  mixed $affected_value  The value that was just added or updated
	 * @return string  The css class
	 */
	public static function getRowClass(mixed $row_value = null, mixed $affected_value = null): string
	{
		if ($row_value !== null && $row_value == $affected_value) {
			 self::$row_number++;
			 $class = 'highlighted';
		} else {
			$class = (self::$row_number++ % 2) ? 'odd' : 'even';
		}

		$class .= (self::$row_number == 2) ? ' first' : '';
		return $class;
	}


	/**
	 * Gets the current value of a search field
	 *
	 * If a value is an empty string and no cast to is specified, the value will
	 * become `NULL`.
	 *
	 * If a query string of `?reset` is passed, all previous search values will
	 * be erased.
	 *
	 * @param  string $column   The column that is being pulled back
	 * @param  string $cast_to  The data type to cast to
	 * @param  string $default  The default value
	 * @return mixed  The current value
	 */
	public static function getSearchValue(string $column, ?string $cast_to = null, ?string $default = null): mixed
	{
		// Reset values if requested
		if (self::wasResetRequested()) {
			self::setPreviousSearchValue($column, null);
			return null;
		}

		if (self::getPreviousSearchValue($column) && !Request::check($column)) {
			self::$search_values[$column] = self::getPreviousSearchValue($column);
			self::$loaded_values[$column] = self::$search_values[$column];
		} else {
			self::$search_values[$column] = Request::get($column, $cast_to, $default);
			self::setPreviousSearchValue($column, self::$search_values[$column]);
		}
		return self::$search_values[$column];
	}


	/**
	 * Gets the current column to sort by, defaults to first one specified
	 *
	 * @param  string $possible_column  The columns that can be sorted by, defaults to first
	 * @return string|null  The column to sort by, or `NULL` if no value was set
	 */
	public static function getSortColumn(string $possible_column): ?string
	{
		// Reset value if requested
		if (self::wasResetRequested()) {
			self::setPreviousSortColumn(null);
			return null;
		}

		$possible_columns = func_get_args();

		if (sizeof($possible_columns) == 1 && is_array($possible_columns[0])) {
			$possible_columns = $possible_columns[0];
		}

		$previous_sort_column = self::getPreviousSortColumn();
		if ($previous_sort_column && !Request::check('sort')) {
			self::$sort_column         = $previous_sort_column;
			self::$loaded_values['sort'] = $previous_sort_column;
		} else {
			$valid_sort_column = Request::getValid('sort', $possible_columns);
			self::$sort_column = is_string($valid_sort_column) ? $valid_sort_column : null;
			self::setPreviousSortColumn(self::$sort_column);
		}
		return self::$sort_column;
	}


	/**
	 * Gets the current sort direction
	 *
	 * @param  string $default_direction  The default direction, `'asc'` or `'desc'`
	 * @return string|null  The direction, `'asc'` or `'desc'`, or `NULL` if no value was set
	 */
	public static function getSortDirection(string $default_direction): ?string
	{
		// Reset value if requested
		if (self::wasResetRequested()) {
			self::setPreviousSortDirection(null);
			return null;
		}

		$previous_sort_direction = self::getPreviousSortDirection();
		if ($previous_sort_direction && !Request::check('dir')) {
			self::$sort_direction           = $previous_sort_direction;
			self::$loaded_values['dir']     = $previous_sort_direction;
		} else {
			$valid_sort_direction = Request::getValid('dir', array($default_direction, ($default_direction == 'asc') ? 'desc' : 'asc'));
			self::$sort_direction = is_string($valid_sort_direction) ? $valid_sort_direction : null;
			self::setPreviousSortDirection(self::$sort_direction);
		}
		return self::$sort_direction;
	}


	/**
	 * Prints a sortable column header `a` tag
	 *
	 * The a tag will include the CSS class `'sortable_column'` and the
	 * direction being sorted, `'asc'` or `'desc'`.
	 *
	 * {{{
	 * #!php
	 * CRUD::printSortableColumn('name', 'Name');
	 * }}}
	 *
	 * would create the following HTML based on the page context
	 *
	 * {{{
	 * #!html
	 * <!-- If name is the current sort column in the asc direction, the output would be -->
	 * <a href="?sort=name&dir=desc" class="sorted_column asc">Name</a>
	 *
	 * <!-- If name is not the current sort column, the output would be -->
	 * <a href="?sort-name&dir=asc" class="sorted_column">Name</a>
	 * }}}
	 *
	 * @param  string $column       The column to create the sortable header for
	 * @param  string $column_name  This will override the humanized version of the column
	 * @return void
	 */
	public static function printSortableColumn(string $column, ?string $column_name = null): void
	{
		if ($column_name === null) {
			$column_name = Grammar::humanize($column);
		}

		if (self::$sort_column == $column) {
			$sort      = $column;
			$direction = (self::$sort_direction == 'asc') ? 'desc' : 'asc';
		} else {
			$sort      = $column;
			$direction = 'asc';
		}

		$columns = array_merge(array('sort', 'dir'), array_keys(self::$search_values));
		$values  = array_merge(array($sort, $direction), array_values(self::$search_values));

		$url         = HTML::encode(URL::get() . URL::replaceInQueryString($columns, $values));
		$css_class   = (self::$sort_column == $column) ? ' ' . self::$sort_direction : '';
		$column_name = HTML::prepare($column_name);

		echo '<a href="' . $url . '" class="sortable_column' . $css_class . '">' . $column_name . '</a>';
	}


	/**
	 * Checks to see if any values (search or sort) were loaded from the session, and if so redirects the user to the current URL with those values added
	 *
	 * @return void
	 */
	public static function redirectWithLoadedValues(): void
	{
		// If values were reset, redirect to the plain URL
		if (self::wasResetRequested()) {
			URL::redirect(URL::get() . URL::removeFromQueryString('reset'));
		}

		$query_string = URL::replaceInQueryString(array_keys(self::$loaded_values), array_values(self::$loaded_values));
		$url = URL::get() . $query_string;

		if ($url != URL::getWithQueryString() && $url != URL::getWithQueryString() . '?') {
			URL::redirect($url);
		}
	}


	/**
	 * Resets the configuration and data of the class
	 *
	 * @internal
	 *
	 * @return void
	 */
	public static function reset(): void
	{
		Session::clear(__CLASS__ . '::');

		self::$loaded_values  = array();
		self::$row_number     = 1;
		self::$search_values  = array();
		self::$sort_column    = null;
		self::$sort_direction = null;
	}


	/**
	 * Sets a value for a search field
	 *
	 * @param  string $column  The column to save the value for
	 * @param  mixed  $value   The value to save
	 * @return void
	 */
	private static function setPreviousSearchValue(string $column, mixed $value): void
	{
		Session::set(__CLASS__ . '::' . URL::get() . '::previous_search::' . $column, $value);
	}


	/**
	 * Set the sort column to be used on returning pages
	 *
	 * @param  string|null $sort_column  The sort column to save, or `NULL` to reset it
	 * @return void
	 */
	private static function setPreviousSortColumn(?string $sort_column): void
	{
		Session::set(__CLASS__ . '::' . URL::get() . '::previous_sort_column', $sort_column);
	}


	/**
	 * Set the sort direction to be used on returning pages
	 *
	 * @param  string|null $sort_direction  The sort direction to save, or `NULL` to reset it
	 * @return void
	 */
	private static function setPreviousSortDirection(?string $sort_direction): void
	{
		Session::set(__CLASS__ . '::' . URL::get() . '::previous_sort_direction', $sort_direction);
	}


	/**
	 * Indicates if a reset was requested for search values
	 *
	 * @return boolean  If a reset was requested
	 */
	private static function wasResetRequested(): bool
	{
		$tail = substr(URL::getWithQueryString(), -6);
		return $tail == '?reset' || $tail == '&reset';
	}


	/**
	 * Prevent instantiation
	 *
	 * @return CRUD
	 */
	private function __construct()
    {
    }
}



/**
 * Copyright (c) 2007-2009 Will Bond <will@flourishlib.com>
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
