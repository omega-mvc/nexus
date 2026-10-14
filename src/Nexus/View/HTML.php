<?php

declare(strict_types=1);

/**
 * Provides HTML-related methods
 *
 * This class is implemented to use the UTF-8 character encoding.
 *
 * @copyright  Copyright (c) 2007-2010 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @author     Jeff Turcotte [jt] <jeff@imarc.net>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 *
 * @version    1.0.0b9
 * @changes    1.0.0b9  Added force div argument to HTML::show() [jt, 2013-04-09]
 * @changes    1.0.0b8  Changed ::encode() and ::prepare() to handle arrays of strings [wb, 2010-05-19]
 * @changes    1.0.0b7  Fixed a bug where some conditional comments were causing the regex in ::prepare() to break [wb,
 * 2009-11-04]
 * @changes    1.0.0b6  Updated ::showChecked() to require strict equality if one parameter is `NULL` [wb, 2009-06-02]
 * @changes    1.0.0b5  Fixed ::prepare() so it does not encode multi-line HTML comments [wb, 2009-05-09]
 * @changes    1.0.0b4  Added methods ::printOption() and ::showChecked() that were in CRUD [wb, 2009-05-08]
 * @changes    1.0.0b3  Fixed a bug where ::makeLinks() would double-link some URLs [wb, 2009-01-08]
 * @changes    1.0.0b2  Fixed a bug where ::makeLinks() would create links out of URLs in HTML tags [wb, 2008-12-05]
 * @changes    1.0.0b   The initial implementation [wb, 2007-09-25]
 */
class HTML
{
	/**
	 * Inline-level tags allowed when checking for block-level HTML
	 */
	private const string INLINE_TAGS =
		'<a><abbr><acronym><b><big><br><button><cite><code><del><dfn><em><font><i><img><input><ins><kbd><label><q><s><s'
			. 'amp><select><small><span><strike><strong><sub><sup><textarea><tt><u><var>';

	/**
	 * Inline-level tags allowed when converting newlines (`<br>` intentionally excluded)
	 */
	private const string INLINE_TAGS_NO_BR =
		'<a><abbr><acronym><b><big><button><cite><code><del><dfn><em><font><i><img><input><ins><kbd><label><q><s><samp>'
			. '<select><small><span><strike><strong><sub><sup><textarea><tt><u><var>';

	/**
	 * Checks a string of HTML for block level elements
	 *
	 * @param  string $content  The HTML content to check
	 * @return boolean  If the content contains a block level tag
	 */
	public static function containsBlockLevelHTML(string $content): bool
	{
		return strip_tags($content, self::INLINE_TAGS) != $content;
	}


	/**
	 * Converts newlines into `br` tags as long as there aren't any block-level HTML tags present
	 *
	 * @param  string $content  The content to display
	 * @return string
	 */
	public static function convertNewlines(string $content): string
	{
		return (strip_tags($content, self::INLINE_TAGS_NO_BR) != $content) ? $content : nl2br($content);
	}


	/**
	 * Converts all HTML entities to normal characters, using UTF-8
	 *
	 * @param  string $content  The content to decode
	 * @return string  The decoded content
	 */
	public static function decode(string $content): string
	{
		return html_entity_decode($content, ENT_QUOTES, 'UTF-8');
	}


	/**
	 * Converts all special characters to entites, using UTF-8.
	 *
	 * @param  array<array-key, mixed>|string  $content  The content to encode
	 * @return array<array-key, mixed>|string  The encoded content
	 * @phpstan-return ($content is array ? array<array-key, mixed> : string)
	 */
	public static function encode(array|string $content): array|string
	{
		if (is_array($content)) {
			return array_map(
				static function (mixed $value): mixed {
					if (is_array($value)) {
						return self::encode($value);
					}
					if (is_string($value)) {
						return self::encode($value);
					}
					if (is_scalar($value)) {
						return self::encode((string) $value);
					}
					throw new ProgrammerException(
						'Only strings, arrays and scalar values can be HTML encoded, %s given',
						get_debug_type($value)
					);
				},
				$content
			);
		}
		return htmlentities($content, ENT_QUOTES, 'UTF-8');
	}


	/**
	 * Takes a block of text and converts all URLs into HTML links
	 *
	 * @param  string  $content           The content to parse for links
	 * @param  integer $link_text_length  If non-zero, all link text will be truncated to this many characters
	 * @return string  The content with all URLs converted to HTML link
	 */
	public static function makeLinks(string $content, int $link_text_length = 0): string
	{
		// Find all a tags with contents, individual HTML tags and HTML comments
		$reg_exp =
			"/<\s*a(?:\s+[\w:]+(?:\s*=\s*(?:\"[^\"]*?\"|'[^']*?'|[^'\">\s]+))?)*\s*>.*?<\s*\/\s*a\s*>|<\s*\/?\s*[\w:]+("
				. "?:\s+[\w:]+(?:\s*=\s*(?:\"[^\"]*?\"|'[^']*?'|[^'\">\s]+))?)*\s*\/?\s*>|<\!--.*?-->/s";
		preg_match_all($reg_exp, $content, $html_matches, PREG_SET_ORDER);

		// Find all text
		$text_matches = preg_split($reg_exp, $content);
		if ($text_matches === false) {
			// Backtracking limit hit – return the content unchanged rather than emitting a warning
			return $content;
		}

		// For each chunk of text and create the links
		foreach ($text_matches as $key => $text) {
			preg_match_all(
				'~
				  \b([a-z]{3,}://[a-z0-9%\$\-_.+!*;/?:@=&\'\#,]+[a-z0-9\$\-_+!*;/?:@=&\'\#,])\b |
				  # Fully URLs
				  \b(www\.(?:[a-z0-9\-]+\.)+[a-z]{2,}(?:
				  	/[a-z0-9%\$\-_.+!*;/?:@=&\'\#,]+[a-z0-9\$\-_+!*;/?:@=&\'\#,])?)\b |
				  # www. domains
				  \b([a-z0-9\\.+\'_\\-]+@(?:[a-z0-9\\-]+\.)+[a-z]{2,})\b
				  # email addresses
				 ~ix',
				$text,
				$matches,
				PREG_SET_ORDER
			);

			// For each match we find the first occurence, replace it and then
			// start from the end of that finding the next occurence. This
			// prevents double linking of matches for http://www.example.com and
			// www.example.com
			$last_pos = 0;
			foreach ($matches as $match) {
				$match_pos = strpos($text, $match[0], $last_pos);
				if ($match_pos === false) {
					continue;
				}
				$length    = strlen($match[0]);
				$prefix    = '';

				if (!empty($match[3])) {
					$prefix = 'mailto:';
				} elseif (!empty($match[2])) {
					$prefix = 'http://';
				}

				$replacement  = '<a href="' . $prefix . $match[0] . '">';
				$replacement .= ($link_text_length && strlen($match[0]) > $link_text_length) ? substr(
					$match[0],
					0,
					$link_text_length
				) . "…" : $match[0];
				$replacement .= '</a>';

				$text = substr_replace(
					$text,
					$replacement,
					$match_pos,
					$length
				);

				$last_pos = $match_pos + strlen($replacement);
			}

			$text_matches[$key] = $text;
		}

		// Merge the text and html back together
		for ($i = 0; $i < sizeof($html_matches); $i++) {
			$text_matches[$i] .= $html_matches[$i][0];
		}

		return implode($text_matches);
	}


	/**
	 * Prepares content for display in UTF-8 encoded HTML - allows HTML tags
	 *
	 * @param  array<array-key, mixed>|string  $content  The content to prepare
	 * @return array<array-key, mixed>|string  The prepared content
	 * @phpstan-return ($content is array ? array<array-key, mixed> : string)
	 */
	public static function prepare(array|string $content): array|string
	{
		if (is_array($content)) {
			return array_map(
				static function (mixed $value): mixed {
					if (is_array($value)) {
						return self::prepare($value);
					}
					if (is_string($value)) {
						return self::prepare($value);
					}
					if (is_scalar($value)) {
						return self::prepare((string) $value);
					}
					throw new ProgrammerException(
						'Only strings, arrays and scalar values can be prepared for HTML, %s given',
						get_debug_type($value)
					);
				},
				$content
			);
		}

		// Find all html tags, entities and comments
		$reg_exp =
			"/<\s*\/?\s*[\w:]+(?:\s+[\w:]+(?:\s*=\s*(?:\"[^\"]*?\"|'[^']*?'|[^'\">\s]+))?)*\s*\/?\s*>|&(?:#\d+|\w+);|<"
				. "\!--.*?-->/s";
		preg_match_all($reg_exp, $content, $html_matches, PREG_SET_ORDER);

		// Find all text
		$text_matches = preg_split($reg_exp, $content);
		if ($text_matches === false) {
			// Backtracking limit hit – return the content unchanged rather than emitting a warning
			return $content;
		}

		// For each chunk of text, make sure it is converted to entities
		foreach ($text_matches as $key => $value) {
			$text_matches[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
		}

		// Merge the text and html back together
		for ($i = 0; $i < sizeof($html_matches); $i++) {
			$text_matches[$i] .= $html_matches[$i][0];
		}

		return implode($text_matches);
	}


	/**
	 * Prints an `option` tag with the provided value, using the selected value to determine if the option should be
	 * marked as selected
	 *
	 * @param  string $text            The text to display in the option tag
	 * @param  string $value           The value for the option
	 * @param  string|array<array-key, string>|null $selected_value  If the value is the same as this, the option will
	 * be marked as selected
	 * @return void
	 */
	public static function printOption(string $text, string $value, string|array|null $selected_value = null): void
	{
		$selected = false;
		if ($value == $selected_value || (is_array($selected_value) && in_array($value, $selected_value))) {
			$selected = true;
		}

		echo '<option value="' . HTML::encode($value) . '"';
		if ($selected) {
			echo ' selected="selected"';
		}
		echo '>' . HTML::prepare($text) . '</option>';
	}


	/**
	 * Sets the proper Content-Type header for a UTF-8 HTML (or pseudo-XHTML) page
	 *
	 * @return void
	 */
	public static function sendHeader(): void
	{
		header('Content-Type: text/html; charset=utf-8');
	}


	/**
	 * Prints a `p` (or `div` if the content has block-level HTML) tag with the contents and the class specified - will
	 * not print if no content
	 *
	 * @param  string $content    The content to display
	 * @param  string $css_class  The CSS class to apply
	 * @param  boolean $force_div  Force a div for showing html
	 * @return boolean  If the content was shown
	 */
	public static function show(string $content, string $css_class = '', bool $force_div = false): bool
	{
		if (!strlen(trim($content))) {
			return false;
		}

		$class = ($css_class) ? ' class="' . $css_class . '"' : '';
		if (self::containsBlockLevelHTML($content) || $force_div) {
			echo '<div' . $class . '>' . self::prepare($content) . '</div>';
		} else {
			echo '<p' . $class . '>' . self::prepare($content) . '</p>';
		}

		return true;
	}


	/**
	 * Prints a `checked="checked"` HTML input attribute if `$value` equals `$checked_value`, or if `$value` is in
	 * `$checked_value`
	 *
	 * Please note that if either `$value` or `$checked_value` is `NULL`, a
	 * strict comparison will be performed, whereas normally a non-strict
	 * comparison is made. Thus `0` and `FALSE` will cause the checked
	 * attribute to be printed, but `0` and `NULL` will not.
	 *
	 * @param  mixed  $value          The value for the current HTML input tag
	 * @param  mixed  $checked_value  The value (or array of values) that has been checked
	 * @return boolean  If the checked attribute was printed
	 */
	public static function showChecked(mixed $value, mixed $checked_value): bool
	{
		$checked  = false;

		$one_null = $value === null || $checked_value === null;
		$equal    = ($one_null) ? $value === $checked_value : $value == $checked_value;
		$in_array = is_array($checked_value) && in_array($value, $checked_value, $one_null);

		if ($equal || $in_array) {
			$checked = true;
		}

		if ($checked) {
			echo ' checked="checked"';
			return true;
		}

		return false;
	}


	/**
	 * Forces use as a static class
	 *
	 * @return HTML
	 */
	private function __construct()
	{
	}
}



/**
 * Copyright (c) 2007-2010 Will Bond <will@flourishlib.com>
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
