<?php

declare(strict_types=1);

/**
 * Handles filesystem-level tasks including filesystem transactions and the reference map to keep all File and Directory objects in sync
 *
 * @copyright  Copyright (c) 2008-2010 Will Bond, others
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @author     Alex Leeds [al] <alex@kingleeds.com>
 * @author     Will Bond, iMarc LLC [wb-imarc] <will@imarc.net>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 * @link       http://flourishlib.com/Filesystem
 *
 * @version    1.0.0b17
 * @changes    1.0.0b17  Added an `isset()` guard around `$_SERVER['DOCUMENT_ROOT']` access in ::translateToWebPath() for PHP 8.1 undefined array key compatibility [wb, 2026-07-10]
 * @changes    1.0.0b16  Added a call to clearstatcache() to ::rollback() to prevent incorrect data from being returned by File::getMTime() and File::getSize() [wb, 2010-11-27]
 * @changes    1.0.0b15  Fixed ::translateToWebPath() to handle Windows \s [wb, 2010-04-09]
 * @changes    1.0.0b14  Added ::recordAppend() [wb, 2010-03-15]
 * @changes    1.0.0b13  Changed the way files/directories deleted in a filesystem transaction are handled, including improvements to the exception that is thrown [wb+wb-imarc, 2010-03-05]
 * @changes    1.0.0b12  Updated ::convertToBytes() to properly handle integers without a suffix and sizes with fractions [al+wb, 2009-11-14]
 * @changes    1.0.0b11  Corrected the API documentation for ::getPathInfo() [wb, 2009-09-09]
 * @changes    1.0.0b10  Updated ::updateExceptionMap() to not contain the Exception class parameter hint, allowing NULL to be passed [wb, 2009-08-20]
 * @changes    1.0.0b9   Added some performance tweaks to ::createObject() [wb, 2009-08-06]
 * @changes    1.0.0b8   Changed ::formatFilesize() to not use decimal places for bytes, add a space before and drop the `B` in suffixes [wb, 2009-07-12]
 * @changes    1.0.0b7   Fixed ::formatFilesize() to work when `$bytes` equals zero [wb, 2009-07-08]
 * @changes    1.0.0b6   Changed replacement values in preg_replace() calls to be properly escaped [wb, 2009-06-11]
 * @changes    1.0.0b5   Changed ::formatFilesize() to use proper uppercase letters instead of lowercase [wb, 2009-06-02]
 * @changes    1.0.0b4   Added the ::createObject() method [wb, 2009-01-21]
 * @changes    1.0.0b3   Removed some unnecessary error suppresion operators [wb, 2008-12-11]
 * @changes    1.0.0b2   Fixed a bug where the filepath and exception maps weren't being updated after a rollback [wb, 2008-12-11]
 * @changes    1.0.0b    The initial implementation [wb, 2008-03-24]
 *
 * @phpstan-type TraceEntry array{file?: string, line?: int, class?: string, type?: string, function?: string, args?: list<mixed>}
 */
class Filesystem
{
	/**
	 * Stores the operations to perform when a commit occurs
	 *
	 * @var list<array{action: 'delete', filename?: string, object?: File|Directory}>|null
	 */
	private static ?array $commit_operations = null;

	/**
	 * Maps deletion backtraces to all instances of a file or directory, providing consistency
	 *
	 * @var array<string, list<TraceEntry>|null>
	 */
	private static array $deleted_map = array();

	/**
	 * Stores file and directory names by reference, allowing all object instances to be updated at once
	 *
	 * @var array<string>
	 */
	private static array $filename_map = array();

	/**
	 * Stores the operations to perform if a rollback occurs
	 *
	 * @var list<array{action: 'append', filename: string, length: int}|array{action: 'delete', filename: string}|array{action: 'delete', object: File|Directory}|array{action: 'write', filename: string, old_data: string|false}|array{action: 'rename', old_name: string, new_name: string}>|null
	 */
	private static ?array $rollback_operations = null;

	/**
	 * Stores a list of search => replace strings for web path translations
	 *
	 * @var array<string, string>
	 */
	private static array $web_path_translations = array();


	/**
	 * Adds a directory to the web path translation list
	 *
	 * The web path conversion list is a list of directory paths that will be
	 * converted (from the beginning of filesystem paths) when preparing a path
	 * for output into HTML.
	 *
	 * By default the `$_SERVER['DOCUMENT_ROOT']` will be converted to a blank
	 * string, in essence stripping it from filesystem paths.
	 *
	 * @param  string $search_path   The path to look for
	 * @param  string $replace_path  The path to replace with
	 * @return void
	 */
	public static function addWebPathTranslation(string $search_path, string $replace_path): void
	{
		// Ensure we have the correct kind of slash for the OS being used
		$search_path  = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $search_path);
		$replace_path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $replace_path);
		self::$web_path_translations[$search_path] = $replace_path;
	}


	/**
	 * Starts a filesystem pseudo-transaction, should only be called when no transaction is in progress.
	 *
	 * Flourish filesystem transactions are NOT full ACID-compliant
	 * transactions, but rather more of an filesystem undo buffer which can
	 * return the filesystem to the state when ::begin() was called. If your PHP
	 * script dies in the middle of an operation this functionality will do
	 * nothing for you and all operations will be retained, except for deletes
	 * which only occur once the transaction is committed.
	 *
	 * @return void
	 */
	public static function begin(): void
	{
		if (self::$commit_operations !== null) {
			throw new ProgrammerException(
				'There is already a filesystem transaction in progress'
			);
		}
		self::$commit_operations   = array();
		self::$rollback_operations = array();
	}


	/**
	 * Commits a filesystem transaction, should only be called when a transaction is in progress
	 *
	 * @return void
	 * @throws ValidationException
	 */
	public static function commit(): void
	{
		$commit_operations = self::$commit_operations;
		if ($commit_operations === null) {
			throw new ProgrammerException(
				'There is no filesystem transaction in progress to commit'
			);
		}

		self::$commit_operations   = null;
		self::$rollback_operations = null;

		$commit_operations = array_reverse($commit_operations);

		foreach ($commit_operations as $operation) {
			// Commit operations only include deletes, however it could be a filename or object
			if (isset($operation['filename'])) {
				unlink($operation['filename']);
			} elseif (isset($operation['object'])) {
				$operation['object']->delete();
			}
		}
	}


	/**
	 * Takes a file size including a unit of measure (i.e. kb, GB, M) and converts it to bytes
	 *
	 * Sizes are interpreted using base 2, not base 10. Sizes above 2GB may not
	 * be accurately represented on 32 bit operating systems.
	 *
	 * @param  string $size  The size to convert to bytes
	 * @return integer  The number of bytes represented by the size
	 */
	public static function convertToBytes(string $size): int
	{
		if (!preg_match('#^(\d+(?:\.\d+)?)\s*(k|m|g|t)?(ilo|ega|era|iga)?( )?b?(yte(s)?)?$#D', strtolower(trim($size)), $matches)) {
			throw new ProgrammerException(
				'The size specified, %s, does not appears to be a valid size',
				$size
			);
		}

		if (empty($matches[2])) {
			$matches[2] = 'b';
		}

		$size_map = array('b' => 1,
						  'k' => 1024,
						  'm' => 1048576,
						  'g' => 1073741824,
						  't' => 1099511627776);
		$multiplier = $size_map[$matches[2]];
		return (int) round((float) $matches[1] * $multiplier);
	}


	/**
	 * Takes a filesystem path and creates either an Directory, File or Image object from it
	 *
	 * @throws ValidationException  When no path was specified or the path specified does not exist
	 *
	 * @param  string $path  The path to the filesystem object
	 * @return Directory|File|Image
	 */
	public static function createObject(string $path): mixed
	{
		if (empty($path)) {
			throw new ValidationException(
				'No path was specified'
			);
		}

		if (!is_readable($path)) {
			throw new ValidationException(
				'The path specified, %s, does not exist or is not readable',
				$path
			);
		}

		if (is_dir($path)) {
			return new Directory($path, true);
		}

		if (Image::isImageCompatible($path)) {
			return new Image($path, true);
		}

		return new File($path, true);
	}


	/**
	 * Takes the size of a file in bytes and returns a friendly size in B/K/M/G/T
	 *
	 * @param  integer $bytes           The size of the file in bytes
	 * @param  integer $decimal_places  The number of decimal places to display
	 * @return string
	 */
	public static function formatFilesize(int $bytes, int $decimal_places = 1): string
	{
		if ($bytes < 0) {
			$bytes = 0;
		}
		$suffixes  = array('B', 'K', 'M', 'G', 'T');
		$sizes     = array(1, 1024, 1048576, 1073741824, 1099511627776);
		$suffix    = (!$bytes) ? 0 : (int) floor(log($bytes) / 6.9314718);
		if ($suffix < 0) {
			$suffix = 0;
		} elseif ($suffix > 4) {
			$suffix = 4;
		}
		return number_format($bytes / $sizes[$suffix], ($suffix == 0) ? 0 : $decimal_places) . ' ' . $suffixes[$suffix];
	}


	/**
	 * Returns info about a path including dirname, basename, extension and filename
	 *
	 * @param  string   $path     The file/directory path to retrieve information about
	 * @param  ?string  $element  The piece of information to return: `'dirname'`, `'basename'`, `'extension'`, or `'filename'`
	 * @return ($element is null ? array{dirname: string, basename: string, extension: string, filename: string} : string)
	 */
	public static function getPathInfo(string $path, ?string $element = null): string|array
	{
		$valid_elements = array('dirname', 'basename', 'extension', 'filename');
		if ($element !== null && !in_array($element, $valid_elements)) {
			throw new ProgrammerException(
				'The element specified, %1$s, is invalid. Must be one of: %2$s.',
				$element,
				join(', ', $valid_elements)
			);
		}

		$path_info = pathinfo($path);

		if (!isset($path_info['extension'])) {
			$path_info['extension'] = '';
		}

		if (!isset($path_info['dirname'])) {
			$path_info['dirname'] = '';
		}
		$path_info['dirname'] .= DIRECTORY_SEPARATOR;

		if ($element !== null) {
			return $path_info[$element];
		}

		return $path_info;
	}


	/**
	 * Hooks a file/directory into the deleted backtrace map entry for that filename
	 *
	 * Since the value is returned by reference, all objects that represent
	 * this file/directory always see the same backtrace.
	 *
	 * @internal
	 *
	 * @param  string $file  The name of the file or directory
	 * @return list<TraceEntry>|null  Will return `NULL` if no match, or the backtrace array if a match occurs
	 */
	public static function &hookDeletedMap(string $file): ?array
	{
		if (!isset(self::$deleted_map[$file])) {
			self::$deleted_map[$file] = null;
		}
		return self::$deleted_map[$file];
	}


	/**
	 * Hooks a file/directory name to the filename map
	 *
	 * Since the value is returned by reference, all objects that represent
	 * this file/directory will always be update on a rename.
	 *
	 * @internal
	 *
	 * @param  string $file  The name of the file or directory
	 * @return string  The filename, returned by reference so all objects representing it are updated on a rename
	 */
	public static function &hookFilenameMap(string $file): string
	{
		if (!isset(self::$filename_map[$file])) {
			self::$filename_map[$file] = $file;
		}
		return self::$filename_map[$file];
	}


	/**
	 * Indicates if a transaction is in progress
	 *
	 * @return bool
	 */
	public static function isInsideTransaction(): bool
	{
		return is_array(self::$commit_operations);
	}


	/**
	 * Changes a filename to be safe for URLs by making it all lower case and changing everything but letters, numers, - and . to _
	 *
	 * @param  string $filename  The filename to clean up
	 * @return string  The cleaned up filename
	 */
	public static function makeURLSafe(string $filename): string
	{
		$filename = strtolower(trim($filename));
		$filename = str_replace("'", '', $filename);
		$safe     = preg_replace('#[^a-z0-9\-\.]+#', '_', $filename);
		return is_string($safe) ? $safe : $filename;
	}


	/**
	 * Returns a unique name for a file
	 *
	 * @param  string $file           The filename to check
	 * @param  string $new_extension  The new extension for the filename, should not include `.`
	 * @return string  The unique file name
	 */
	public static function makeUniqueName(string $file, ?string $new_extension = null): string
	{
		$info = self::getPathInfo($file);

		// Change the file extension
		if ($new_extension !== null) {
			$new_extension = ($new_extension) ? '.' . $new_extension : $new_extension;
			$file = $info['dirname'] . $info['filename'] . $new_extension;
			$info = self::getPathInfo($file);
		}

		// If there is an extension, be sure to add . before it
		$extension = (!empty($info['extension'])) ? '.' . $info['extension'] : '';

		// Remove _copy# from the filename to start
		$pattern  = '#_copy(\d+)' . preg_quote($extension, '#') . '$#D';
		$stripped = preg_replace($pattern, $extension, $file);
		if ($stripped === null) {
			throw new ProgrammerException(
				'Unable to update the copy number of the filename %s',
				$file
			);
		}
		$file = $stripped;

		// Look for a unique name by adding _copy# to the end of the file
		while (file_exists($file)) {
			$info = self::getPathInfo($file);
			if (preg_match($pattern, $file, $match)) {
				$next = preg_replace($pattern, '_copy' . ((int) $match[1] + 1) . $extension, $file);
				if ($next === null) {
					throw new ProgrammerException(
						'Unable to update the copy number of the filename %s',
						$file
					);
				}
				$file = $next;
			} else {
				$file = $info['dirname'] . $info['filename'] . '_copy1' . $extension;
			}
		}

		return $file;
	}


	/**
	 * Updates the deleted backtrace for a file or directory
	 *
	 * @internal
	 *
	 * @param  string          $file       A file or directory name, directories should end in `/` or `\`
	 * @param  list<TraceEntry>|null  $backtrace  The backtrace for this file/directory, `NULL` when no backtrace should be kept
	 * @return void
	 */
	public static function updateDeletedMap(string $file, ?array $backtrace): void
	{
		self::$deleted_map[$file] = $backtrace;
	}


	/**
	 * Updates the filename map, causing all objects representing a file/directory to be updated
	 *
	 * @internal
	 *
	 * @param  string $existing_filename  The existing filename
	 * @param  string $new_filename       The new filename
	 * @return void
	 */
	public static function updateFilenameMap(string $existing_filename, string $new_filename): void
	{
		if ($existing_filename == $new_filename) {
			return;
		}

		self::$filename_map[$new_filename] =& self::$filename_map[$existing_filename];
		self::$deleted_map[$new_filename]  =& self::$deleted_map[$existing_filename];

		unset(self::$filename_map[$existing_filename]);
		unset(self::$deleted_map[$existing_filename]);

		self::$filename_map[$new_filename] = $new_filename;
	}


	/**
	 * Updates the filename map recursively, causing all objects representing a directory to be updated
	 *
	 * Also updates all files and directories in the specified directory to the new paths.
	 *
	 * @internal
	 *
	 * @param  string $existing_dirname  The existing directory name
	 * @param  string $new_dirname       The new dirname
	 * @return void
	 */
	public static function updateFilenameMapForDirectory(string $existing_dirname, string $new_dirname): void
	{
		if ($existing_dirname == $new_dirname) {
			return;
		}

		// Handle the directory name
		self::$filename_map[$new_dirname] =& self::$filename_map[$existing_dirname];
		self::$deleted_map[$new_dirname]  =& self::$deleted_map[$existing_dirname];

		unset(self::$filename_map[$existing_dirname]);
		unset(self::$deleted_map[$existing_dirname]);

		self::$filename_map[$new_dirname] = $new_dirname;

		// Handle all of the directories and files inside this directory
		foreach (self::$filename_map as $filename => $ignore) {
			if (!is_string($filename)) {
				continue;
			}
			if (preg_match('#^' . preg_quote($existing_dirname, '#') . '#', $filename)) {
				$new_filename = preg_replace(
					'#^' . preg_quote($existing_dirname, '#') . '#',
					strtr($new_dirname, array('\\' => '\\\\', '$' => '\\$')),
					$filename
				);
				if ($new_filename === null) {
					continue;
				}

				self::$filename_map[$new_filename] =& self::$filename_map[$filename];
				self::$deleted_map[$new_filename]  =& self::$deleted_map[$filename];

				unset(self::$filename_map[$filename]);
				unset(self::$deleted_map[$filename]);

				self::$filename_map[$new_filename] = $new_filename;
			}
		}
	}


	/**
	 * Stores what data has been added to a file so it can be removed if there is a rollback
	 *
	 * @internal
	 *
	 * @param  File  $file  The file that is being written to
	 * @param  string $data  The data being appended to the file
	 * @return void
	 */
	public static function recordAppend(File $file, string $data): void
	{
		self::$rollback_operations[] = array(
			'action'   => 'append',
			'filename' => $file->getPath(),
			'length'   => strlen($data)
		);
	}


	/**
	 * Keeps a record of created files so they can be deleted up in case of a rollback
	 *
	 * @internal
	 *
	 * @param  File|Directory $object  The new file or directory to get rid of on rollback
	 * @return void
	 */
	public static function recordCreate(File|Directory $object): void
	{
		self::$rollback_operations[] = array(
			'action' => 'delete',
			'object' => $object
		);
	}


	/**
	 * Keeps track of file and directory names to delete when a transaction is committed
	 *
	 * @internal
	 *
	 * @param  File|Directory $object  The filesystem object to delete
	 * @return void
	 */
	public static function recordDelete(File|Directory $object): void
	{
		self::$commit_operations[] = array(
			'action' => 'delete',
			'object' => $object
		);
	}


	/**
	 * Keeps a record of duplicated files so they can be cleaned up in case of a rollback
	 *
	 * @internal
	 *
	 * @param  File $file  The duplicate file to get rid of on rollback
	 * @return void
	 */
	public static function recordDuplicate(File $file): void
	{
		self::$rollback_operations[] = array(
			'action'   => 'delete',
			'filename' => $file->getPath()
		);
	}


	/**
	 * Keeps a temp file in place of the old filename so the file can be restored during a rollback
	 *
	 * @internal
	 *
	 * @param  string $old_name  The old file or directory name
	 * @param  string $new_name  The new file or directory name
	 * @return void
	 */
	public static function recordRename(string $old_name, string $new_name): void
	{
		self::$rollback_operations[] = array(
			'action'   => 'rename',
			'old_name' => $old_name,
			'new_name' => $new_name
		);

		// Create the file with no content to prevent overwriting by another process
		file_put_contents($old_name, '');

		self::$commit_operations[] = array(
			'action'   => 'delete',
			'filename' => $old_name
		);
	}


	/**
	 * Keeps backup copies of files so they can be restored if there is a rollback
	 *
	 * @internal
	 *
	 * @param  File $file  The file that is being written to
	 * @return void
	 */
	public static function recordWrite(File $file): void
	{
		self::$rollback_operations[] = array(
			'action'   => 'write',
			'filename' => $file->getPath(),
			'old_data' => file_get_contents($file->getPath())
		);
	}


	/**
	 * Resets the configuration of the class
	 *
	 * @internal
	 *
	 * @return void
	 */
	public static function reset(): void
	{
		self::rollback();
		self::$commit_operations     = null;
		self::$deleted_map           = array();
		self::$filename_map          = array();
		self::$rollback_operations   = null;
		self::$web_path_translations = array();
	}


	/**
	 * Rolls back a filesystem transaction, it is safe to rollback when no transaction is in progress
	 *
	 * @return void
	 */
	public static function rollback(): void
	{
		$rollback_operations = self::$rollback_operations;
		if ($rollback_operations === null) {
			return;
		}

		$rollback_operations = array_reverse($rollback_operations);

		$clear_cache = false;
		foreach ($rollback_operations as $operation) {
			switch ($operation['action']) {
				case 'append':
					$current_length = filesize($operation['filename']);
					$handle         = fopen($operation['filename'], 'r+');
					if (is_int($current_length) && is_resource($handle)) {
						ftruncate($handle, max(0, $current_length - $operation['length']));
						fclose($handle);
					}
					$clear_cache = true;
					break;

				case 'delete':
					if (isset($operation['filename'])) {
						self::updateDeletedMap(
							$operation['filename'],
							debug_backtrace()
						);
						unlink($operation['filename']);
						Filesystem::updateFilenameMap($operation['filename'], '*DELETED at ' . time() . ' with token ' . uniqid('', true) . '* ' . $operation['filename']);
					}
					break;

				case 'write':
					file_put_contents($operation['filename'], is_string($operation['old_data']) ? $operation['old_data'] : '');
					$clear_cache = true;
					break;

				case 'rename':
					Filesystem::updateFilenameMap($operation['new_name'], $operation['old_name']);
					rename($operation['new_name'], $operation['old_name']);
					break;
			}
		}

		// All files to be deleted should have their backtraces erased
		$commit_operations = self::$commit_operations ?? array();
		foreach ($commit_operations as $operation) {
			if (isset($operation['object'])) {
				$path = $operation['object']->getPath();
				self::updateDeletedMap($path, null);
				$clean = preg_replace('#\*DELETED at \d+ with token [\w.]+\* #', '', $path);
				Filesystem::updateFilenameMap($path, is_string($clean) ? $clean : $path);
			}
		}

		self::$commit_operations   = null;
		self::$rollback_operations = null;

		if ($clear_cache) {
			clearstatcache();
		}
	}


	/**
	 * Takes a filesystem path and translates it to a web path using the rules added
	 *
	 * @param  string $path  The path to translate
	 * @return string  The filesystem path translated to a web path
	 */
	public static function translateToWebPath(string $path): string
	{
		$document_root = isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '';
		$resolved      = realpath($document_root);
		$translations  = array(($resolved === false ? '' : $resolved) => '') + self::$web_path_translations;

		foreach ($translations as $search => $replace) {
			$translated = preg_replace(
				'#^' . preg_quote($search, '#') . '#',
				strtr($replace, array('\\' => '\\\\', '$' => '\\$')),
				$path
			);
			if (is_string($translated)) {
				$path = $translated;
			}
		}

		return str_replace('\\', '/', $path);
	}


	/**
	 * Forces use as a static class
	 *
	 * @return Filesystem
	 */
	private function __construct()
    {
    }
}



/**
 * Copyright (c) 2008-2010 Will Bond <will@flourishlib.com>, others
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
