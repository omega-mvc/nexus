<?php
declare(strict_types=1);
/**
 * Provides file manipulation functionality for ActiveRecord classes
 *
 * @copyright  Copyright (c) 2008-2011 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 * @link       http://flourishlib.com/ORMFile
 *
 * @version    1.0.0b31
 * @changes    1.0.0b31  Added an `isset()` guard around `$_SERVER['DOCUMENT_ROOT']` access for PHP 8.1 undefined array key compatibility [wb, 2026-07-10]
 * @changes    1.0.0b30  Updated code for the new Upload API [wb, 2011-08-24]
 * @changes    1.0.0b29  Fixed a bug when uploading a new file to a column with an existing file that was not found on the filesystem [wb, 2011-05-10]
 * @changes    1.0.0b28  Backwards Compatibility Break - ::configureImageUploadColumn() no longer accepts the optional `$image_type` as the fourth parameter, instead ::addFImageMethodCall() must be called with `saveChanges` as the `$method` and the image type as the first parameter [wb, 2010-11-30]
 * @changes    1.0.0b27  Fixed column inheritance to properly handle non-images and inheriting into image upload columns [wb, 2010-09-18]
 * @changes    1.0.0b26  Enhanced ::configureColumnInheritance() to ensure both columns specified have been set up as file upload columns [wb, 2010-08-18]
 * @changes    1.0.0b25  Updated code to work with the new ORM API [wb, 2010-08-06]
 * @changes    1.0.0b24  Changed validation messages array to use column name keys [wb, 2010-05-26]
 * @changes    1.0.0b23  Fixed a bug with ::upload() that could cause a method called on a non-object error in relation to the upload directory not being defined [wb, 2010-05-10]
 * @changes    1.0.0b22  Updated the TEMP_DIRECTORY constant to not include the trailing slash, code now uses DIRECTORY_SEPARATOR to fix issues on Windows [wb, 2010-04-28]
 * @changes    1.0.0b21  Fixed ::set() to perform column inheritance, just like ::upload() does [wb, 2010-03-15]
 * @changes    1.0.0b20  Fixed the `set` and `process` methods to return the record instance, changed `upload` methods to return the File object, updated ::reflect() with new return values [wb, 2010-03-15]
 * @changes    1.0.0b19  Fixed a few missed instances of old File method names [wb, 2009-12-16]
 * @changes    1.0.0b18  Updated code for the new File API [wb, 2009-12-16]
 * @changes    1.0.0b17  Updated code for the new ORMDatabase and ORMSchema APIs [wb, 2009-10-28]
 * @changes    1.0.0b16  Image method calls for file upload columns will no longer cause notices due to a missing image type [wb, 2009-09-09]
 * @changes    1.0.0b15  ::addFImageMethodCall() no longer requires column be an image upload column, inheritance to an image column now only happens for Image objects [wb, 2009-07-29]
 * @changes    1.0.0b14  Updated to use new ORM::registerInspectCallback() method [wb, 2009-07-13]
 * @changes    1.0.0b13  Updated code for new ORM API [wb, 2009-06-15]
 * @changes    1.0.0b12  Changed replacement values in preg_replace() calls to be properly escaped [wb, 2009-06-11]
 * @changes    1.0.0b11  Updated code to use new ValidationException::formatField() method [wb, 2009-06-04]
 * @changes    1.0.0b10  Fixed a bug where an inherited file upload column would not be properly re-set with an `existing-` input [wb, 2009-05-26]
 * @changes    1.0.0b9   ::upload() and ::set() now set the `$values` entry to `NULL` for filenames that are empty [wb, 2009-03-02]
 * @changes    1.0.0b8   Changed ::set() to accept objects and reject directories [wb, 2009-01-21]
 * @changes    1.0.0b7   Changed the class to use the new Filesystem::createObject() method [wb, 2009-01-21]
 * @changes    1.0.0b6   Old files are now checked against the current file to prevent removal of an in-use file [wb, 2008-12-23]
 * @changes    1.0.0b5   Fixed ::replicate() to ensure the temp directory exists and ::set() to use the temp directory [wb, 2008-12-23]
 * @changes    1.0.0b4   ::objectify() no longer throws an exception when a file can't be found [wb, 2008-12-18]
 * @changes    1.0.0b3   Added ::replicate() so that replicated files get pu in the temp directory [wb, 2008-12-12]
 * @changes    1.0.0b2   Fixed a bug with objectifying file columns [wb, 2008-11-24]
 * @changes    1.0.0b    The initial implementation [wb, 2008-05-28]
 */
class ORMFile
{
	/**
	 * The temporary directory to use for various tasks
	 *
	 * @internal
	 *
	 * @var string
	 */
	const string TEMP_DIRECTORY = '__flourish_temp';


	/**
	 * Defines how columns can inherit uploaded files
	 *
	 * @var array
	 */
	static private array $column_inheritence = array();

	/**
	 * Methods to be called on Upload before the file is uploaded
	 *
	 * @var array
	 */
	static private array $fupload_method_calls = array();

	/**
	 * Columns that can be filled by file uploads
	 *
	 * @var array
	 */
	static private array $file_upload_columns = array();

	/**
	 * Methods to be called on the Image instance
	 *
	 * @var array
	 */
	static private array $fimage_method_calls = array();

	/**
	 * Columns that can be filled by image uploads
	 *
	 * @var array
	 */
	static private array $image_upload_columns = array();

	/**
	 * Keeps track of the nesting level of the filesystem transaction so we know when to start, commit, rollback, etc
	 *
	 * @var integer
	 */
	static private int $transaction_level = 0;


	/**
	 * Adds an Image method call to the image manipulation for a column if an image file is uploaded
	 *
	 * Any call to Image::saveChanges() will be called last. If no explicit
	 * method call to Image::saveChanges() is made, it will be called
	 * implicitly with default parameters.
	 *
	 * @param  mixed  $class       The class name or instance of the class
	 * @param  string $column      The column to call the method for
	 * @param  string $method      The Image method to call
	 * @param  array  $parameters  The parameters to pass to the method
	 * @return void
	 */
	static public function addFImageMethodCall(mixed $class, string $column, string $method, array $parameters=array()): void
	{
		$class = ORM::getClass($class);

		if (empty(self::$file_upload_columns[$class][$column])) {
			throw new ProgrammerException(
				'The column specified, %s, has not been configured as a file or image upload column',
				$column
			);
		}

		if (empty(self::$fimage_method_calls[$class])) {
			self::$fimage_method_calls[$class] = array();
		}
		if (empty(self::$fimage_method_calls[$class][$column])) {
			self::$fimage_method_calls[$class][$column] = array();
		}

		self::$fimage_method_calls[$class][$column][] = array(
			'method'     => $method,
			'parameters' => $parameters
		);
	}


	/**
	 * Adds an Upload method call to the Upload initialization for a column
	 *
	 * @param  mixed  $class       The class name or instance of the class
	 * @param  string $column      The column to call the method for
	 * @param  string $method      The Upload method to call
	 * @param  array  $parameters  The parameters to pass to the method
	 * @return void
	 */
	static public function addFUploadMethodCall(mixed $class, string $column, string $method, array $parameters=array()): void
	{
		if ($method == 'enableOverwrite') {
			throw new ProgrammerException(
				'The method specified, %1$s, is not compatible with how %2$s stores and associates files with records',
				$method,
				'ORMFile'
			);
		}

		$class = ORM::getClass($class);

		if (empty(self::$file_upload_columns[$class][$column])) {
			throw new ProgrammerException(
				'The column specified, %s, has not been configured as a file or image upload column',
				$column
			);
		}

		if (empty(self::$fupload_method_calls[$class])) {
			self::$fupload_method_calls[$class] = array();
		}
		if (empty(self::$fupload_method_calls[$class][$column])) {
			self::$fupload_method_calls[$class][$column] = array();
		}

		self::$fupload_method_calls[$class][$column][] = array(
			'method'     => $method,
			'parameters' => $parameters
		);
	}


	/**
	 * Begins a transaction, or increases the level
	 *
	 * @internal
	 *
	 * @return void
	 */
	static public function begin(): void
	{
		// If the transaction was started by something else, don't even track it
		if (self::$transaction_level == 0 && Filesystem::isInsideTransaction()) {
			return;
		}

		self::$transaction_level++;

		if (!Filesystem::isInsideTransaction()) {
			Filesystem::begin();
		}
	}


	/**
	 * Commits a transaction, or decreases the level
	 *
	 * @internal
	 *
	 * @return void
	 */
	static public function commit(): void
	{
		// If the transaction was started by something else, don't even track it
		if (self::$transaction_level == 0) {
			return;
		}

		self::$transaction_level--;

		if (!self::$transaction_level) {
			Filesystem::commit();
		}
	}


	/**
	 * Composes text using Text if loaded
	 *
	 * @param  string  $message    The message to compose
	 * @return string  The composed and possible translated message
	 */
	static private function compose(string $message): string
	{
		$args = array_slice(func_get_args(), 1);

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
	 * Sets a column to be a file upload column
	 *
	 * Configuring a column to be a file upload column means that whenever
	 * ActiveRecord::populate() is called for an ActiveRecord object, any
	 * appropriately named file uploads (via `$_FILES`) will be moved into
	 * the directory for this column.
	 *
	 * Setting the column to a file path will cause the specified file to
	 * be copied into the directory for this column.
	 *
	 * @param  mixed             $class      The class name or instance of the class
	 * @param  string            $column     The column to set as a file upload column
	 * @param  Directory|string $directory  The directory to upload/move to
	 * @return void
	 */
	static public function configureFileUploadColumn(mixed $class, string $column, mixed $directory): void
	{
		$class     = ORM::getClass($class);
		$table     = ORM::tablize($class);
		$schema    = ORMSchema::retrieve($class);
		$data_type = $schema->getColumnInfo($table, $column, 'type');

		$valid_data_types = array('varchar', 'char', 'text');
		if (!in_array($data_type, $valid_data_types)) {
			throw new ProgrammerException(
				'The column specified, %1$s, is a %2$s column. Must be one of %3$s to be set as a file upload column.',
				$column,
				$data_type,
				join(', ', $valid_data_types)
			);
		}

		if (!is_object($directory)) {
			$directory = new Directory($directory);
		}

		if (!$directory->isWritable()) {
			throw new EnvironmentException(
				'The file upload directory, %s, is not writable',
				$directory->getPath()
			);
		}

		$camelized_column = Grammar::camelize($column, TRUE);

		ORM::registerActiveRecordMethod(
			$class,
			'upload' . $camelized_column,
			array(self::class, 'upload')
		);

		ORM::registerActiveRecordMethod(
			$class,
			'set' . $camelized_column,
			array(self::class, 'set')
		);

		ORM::registerActiveRecordMethod(
			$class,
			'encode' . $camelized_column,
			array(self::class, 'encode')
		);

		ORM::registerActiveRecordMethod(
			$class,
			'prepare' . $camelized_column,
			array(self::class, 'prepare')
		);

		ORM::registerReflectCallback($class, array(self::class, 'reflect'));
		ORM::registerInspectCallback($class, $column, array(self::class, 'inspect'));
		ORM::registerReplicateCallback($class, $column, array(self::class, 'replicate'));
		ORM::registerObjectifyCallback($class, $column, array(self::class, 'objectify'));

		$only_once_hooks = array(
			'post-begin::delete()'    => array(self::class, 'begin'),
			'pre-commit::delete()'    => array(self::class, 'delete'),
			'post-commit::delete()'   => array(self::class, 'commit'),
			'post-rollback::delete()' => array(self::class, 'rollback'),
			'post::populate()'        => array(self::class, 'populate'),
			'post-begin::store()'     => array(self::class, 'begin'),
			'post-validate::store()'  => array(self::class, 'moveFromTemp'),
			'pre-commit::store()'     => array(self::class, 'deleteOld'),
			'post-commit::store()'    => array(self::class, 'commit'),
			'post-rollback::store()'  => array(self::class, 'rollback'),
			'post::validate()'        => array(self::class, 'validate')
		);

		foreach ($only_once_hooks as $hook => $callback) {
			if (!ORM::checkHookCallback($class, $hook, $callback)) {
				ORM::registerHookCallback($class, $hook, $callback);
			}
		}

		if (empty(self::$file_upload_columns[$class])) {
			self::$file_upload_columns[$class] = array();
		}

		self::$file_upload_columns[$class][$column] = $directory;
	}


	/**
	 * Takes one file or image upload columns and sets it to inherit any uploaded/set files from another column
	 *
	 * @param  mixed  $class                The class name or instance of the class
	 * @param  string $column               The column that will inherit the uploaded file
	 * @param  string $inherit_from_column  The column to inherit the uploaded file from
	 * @return void
	 */
	static public function configureColumnInheritance(mixed $class, string $column, string $inherit_from_column): void
	{
		$class = ORM::getClass($class);

		if (empty(self::$file_upload_columns[$class][$column])) {
			throw new ProgrammerException(
				'The column specified, %s, has not been configured as a file upload column',
				$column
			);
		}

		if (empty(self::$file_upload_columns[$class][$inherit_from_column])) {
			throw new ProgrammerException(
				'The column specified, %s, has not been configured as a file upload column',
				$column
			);
		}

		if (empty(self::$column_inheritence[$class])) {
			self::$column_inheritence[$class] = array();
		}

		if (empty(self::$column_inheritence[$class][$inherit_from_column])) {
			self::$column_inheritence[$class][$inherit_from_column] = array();
		}

		self::$column_inheritence[$class][$inherit_from_column][] = $column;
	}


	/**
	 * Sets a column to be an image upload column
	 *
	 * This method works exactly the same as ::configureFileUploadColumn()
	 * except that only image files are accepted.
	 *
	 * To alter an image, including the file type, use ::addFImageMethodCall().
	 *
	 * @param  mixed             $class       The class name or instance of the class
	 * @param  string            $column      The column to set as a file upload column
	 * @param  Directory|string $directory   The directory to upload to
	 * @return void
	 */
	static public function configureImageUploadColumn(mixed $class, string $column, mixed $directory): void
	{
		self::configureFileUploadColumn($class, $column, $directory);

		$class = ORM::getClass($class);

		$camelized_column = Grammar::camelize($column, TRUE);

		ORM::registerActiveRecordMethod(
			$class,
			'process' . $camelized_column,
			array(self::class, 'process')
		);

		if (empty(self::$image_upload_columns[$class])) {
			self::$image_upload_columns[$class] = array();
		}

		self::$image_upload_columns[$class][$column] = TRUE;

		self::addFUploadMethodCall(
			$class,
			$column,
			'setMimeTypes',
			array(
				array(
					'image/gif',
					'image/jpeg',
					'image/pjpeg',
					'image/png'
				),
				self::compose('The file uploaded is not an image')
			)
		);
	}


	/**
	 * Deletes the files for this record
	 *
	 * @internal
	 *
	 * @param  ActiveRecord $object            The ActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  array         &$cache            The cache array for the record
	 * @return void
	 */
	static public function delete(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache): void
	{
		$class = get_class($object);

		foreach (self::$file_upload_columns[$class] as $column => $directory) {

			// Remove the current file for the column
			if ($values[$column] instanceof File) {
				$values[$column]->delete();
			}

			// Remove the old files for the column
			foreach (ActiveRecord::retrieveOld($old_values, $column, array(), TRUE) as $file) {
				if ($file instanceof File) {
					$file->delete();
				}
			}
		}
	}


	/**
	 * Deletes old files for this record that have been replaced by new ones
	 *
	 * @internal
	 *
	 * @param  ActiveRecord $object            The ActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  array         &$cache            The cache array for the record
	 * @return void
	 */
	static public function deleteOld(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache): void
	{
		$class = get_class($object);

		// Remove the old files for the column
		foreach (self::$file_upload_columns[$class] as $column => $directory) {
			$current_file = $values[$column];
			foreach (ActiveRecord::retrieveOld($old_values, $column, array(), TRUE) as $file) {
				if ($file instanceof File && (!$current_file instanceof File || $current_file->getPath() != $file->getPath())) {
					$file->delete();
				}
			}
		}
	}


	/**
	 * Encodes a file for output into an HTML `input` tag
	 *
	 * @internal
	 *
	 * @param  ActiveRecord $object            The ActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  array         &$cache            The cache array for the record
	 * @param  string        $method_name       The method that was called
	 * @param  array         $parameters        The parameters passed to the method
	 * @return void
	 */
	static public function encode(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache, string $method_name, array $parameters): mixed
	{
		list ($action, $subject) = ORM::parseMethod($method_name);

		$column   = Grammar::underscorize($subject);
		$filename = ($values[$column] instanceof File) ? $values[$column]->getName() : NULL;
		if ($filename && strpos($values[$column]->getPath(), self::TEMP_DIRECTORY . DIRECTORY_SEPARATOR . $filename) !== FALSE) {
			$filename = self::TEMP_DIRECTORY . DIRECTORY_SEPARATOR . $filename;
		}

		return HTML::encode($filename);
	}


	/**
	 * Adds metadata about features added by this class
	 *
	 * @internal
	 *
	 * @param  string $class      The class being inspected
	 * @param  string $column     The column being inspected
	 * @param  array  &$metadata  The array of metadata about a column
	 * @return void
	 */
	static public function inspect(string $class, string $column, mixed &$metadata): void
	{
		if (!empty(self::$image_upload_columns[$class][$column])) {
			$metadata['feature'] = 'image';

		} elseif (!empty(self::$file_upload_columns[$class][$column])) {
			$metadata['feature'] = 'file';
		}

		$metadata['directory'] = self::$file_upload_columns[$class][$column]->getPath();
	}


	/**
	 * Moves uploaded files from the temporary directory to the permanent directory
	 *
	 * @internal
	 *
	 * @param  ActiveRecord $object            The ActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  array         &$cache            The cache array for the record
	 * @return void
	 */
	static public function moveFromTemp(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache): void
	{
		foreach ($values as $column => $value) {
			if (!$value instanceof File) {
				continue;
			}

			// If the file is in a temp dir, move it out
			if (strpos($value->getParent()->getPath(), self::TEMP_DIRECTORY . DIRECTORY_SEPARATOR) !== FALSE) {
				$new_filename = str_replace(self::TEMP_DIRECTORY . DIRECTORY_SEPARATOR, '', $value->getPath());
				$new_filename = Filesystem::makeUniqueName($new_filename);
				$value->rename($new_filename, FALSE);
			}
		}
	}


	/**
	 * Turns a filename into an File or Image object
	 *
	 * @internal
	 *
	 * @param  string $class   The class this value is for
	 * @param  string $column  The column the value is in
	 * @param  mixed  $value   The value
	 * @return mixed  The File, Image or raw value
	 */
	static public function objectify(string $class, string $column, mixed $value): mixed
	{
		if ((!is_string($value) && !is_numeric($value) && !is_object($value)) || !strlen(trim($value))) {
			return $value;
		}

		$path = self::$file_upload_columns[$class][$column]->getPath() . $value;

		try {

			return Filesystem::createObject($path);

		// If there was some error creating the file, just return the raw value
		} catch (ExpectedException $e) {
			return $value;
		}
	}


	/**
	 * Performs the upload action for file uploads during ActiveRecord::populate()
	 *
	 * @internal
	 *
	 * @param  ActiveRecord $object            The ActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  array         &$cache            The cache array for the record
	 * @return void
	 */
	static public function populate(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache): void
	{
		$class = get_class($object);

		foreach (self::$file_upload_columns[$class] as $column => $directory) {
			if (Upload::check($column, FALSE) || Request::check('existing-' . $column) || Request::check('delete-' . $column)) {
				$method = 'upload' . Grammar::camelize($column, TRUE);
				$object->$method();
			}
		}
	}


	/**
	 * Prepares a file for output into HTML by returning filename or the web server path to the file
	 *
	 * @internal
	 *
	 * @param  ActiveRecord $object            The ActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  array         &$cache            The cache array for the record
	 * @param  string        $method_name       The method that was called
	 * @param  array         $parameters        The parameters passed to the method
	 * @return void
	 */
	static public function prepare(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache, string $method_name, array $parameters): mixed
	{
		list ($action, $subject) = ORM::parseMethod($method_name);
		$column = Grammar::underscorize($subject);

		if (sizeof($parameters) > 1) {
			throw new ProgrammerException(
				'The column specified, %s, does not accept more than one parameter',
				$column
			);
		}

		$translate_to_web_path = (empty($parameters[0])) ? FALSE : TRUE;
		$value                 = $values[$column];

		if ($value instanceof File) {
			$path = ($translate_to_web_path) ? $value->getPath(TRUE) : $value->getName();
		} else {
			$path = NULL;
		}

		return HTML::prepare($path);
	}


	/**
	 * Takes a directory and creates a temporary directory inside of it - if the temporary folder exists, all files older than 6 hours will be deleted
	 *
	 * @param  string $folder  The folder to create a temporary directory inside of
	 * @return Directory  The temporary directory for the folder specified
	 */
	static private function prepareTempDir(string $folder): Directory
	{
		// Let's clean out the upload temp dir
		try {
			$temp_dir = new Directory($folder->getPath() . self::TEMP_DIRECTORY . DIRECTORY_SEPARATOR);
		} catch (ValidationException $e) {
			$temp_dir = Directory::create($folder->getPath() . self::TEMP_DIRECTORY . DIRECTORY_SEPARATOR);
		}

		$temp_files = $temp_dir->scan();
		foreach ($temp_files as $temp_file) {
			if (filemtime($temp_file->getPath()) < strtotime('-6 hours')) {
				unlink($temp_file->getPath());
			}
		}

		return $temp_dir;
	}


	/**
	 * Handles re-processing an existing image file
	 *
	 * @internal
	 *
	 * @param  ActiveRecord $object            The ActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  array         &$cache            The cache array for the record
	 * @param  string        $method_name       The method that was called
	 * @param  array         $parameters        The parameters passed to the method
	 * @return ActiveRecord  The record object, to allow for method chaining
	 */
	static public function process(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache, string $method_name, array $parameters): ActiveRecord
	{
		list ($action, $subject) = ORM::parseMethod($method_name);

		$column = Grammar::underscorize($subject);
		$class  = get_class($object);

		self::processImage($class, $column, $values[$column]);

		return $object;
	}


	/**
	 * Performs image manipulation on an uploaded/set image
	 *
	 * @internal
	 *
	 * @param  string $class   The name of the class we are manipulating the image for
	 * @param  string $column  The column the image is assigned to
	 * @param  File  $image   The image object to manipulate
	 * @return void
	 */
	static public function processImage(string $class, string $column, File $image): void
	{
		// If we don't have an image or we haven't set it up to manipulate images, just exit
		if (!$image instanceof Image || empty(self::$fimage_method_calls[$class][$column])) {
			return;
		}

		$save_changes_called = FALSE;

		// Manipulate the image
		if (!empty(self::$fimage_method_calls[$class][$column])) {
			foreach (self::$fimage_method_calls[$class][$column] as $method_call) {
				if ($method_call['method'] == 'saveChanges') {
					$save_changes_called = TRUE;
				}
				$callback   = array($image, $method_call['method']);
				$parameters = $method_call['parameters'];
				if (!is_callable($callback)) {
					throw new ProgrammerException(
						'The Image method specified, %s, is not a valid method',
						$method_call['method'] . '()'
					);
				}
				call_user_func_array($callback, $parameters);
			}
		}

		if (!$save_changes_called) {
			call_user_func($image->saveChanges);
		}
	}


	/**
	 * Adjusts the ActiveRecord::reflect() signatures of columns that have been configured in this class
	 *
	 * @internal
	 *
	 * @param  string  $class                 The class to reflect
	 * @param  array   &$signatures           The associative array of `{method name} => {signature}`
	 * @param  boolean $include_doc_comments  If doc comments should be included with the signature
	 * @return void
	 */
	static public function reflect(string $class, mixed &$signatures, bool $include_doc_comments): void
	{
		$image_columns = (isset(self::$image_upload_columns[$class])) ? array_keys(self::$image_upload_columns[$class]) : array();
		$file_columns  = (isset(self::$file_upload_columns[$class]))  ? array_keys(self::$file_upload_columns[$class])  : array();

		foreach($file_columns as $column) {
			$camelized_column = Grammar::camelize($column, TRUE);

			$noun = 'file';
			if (in_array($column, $image_columns)) {
				$noun = 'image';
			}

			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Encodes the filename of " . $column . " for output into an HTML form\n";
				$signature .= " * \n";
				$signature .= " * Only the filename will be returned, any directory will be stripped.\n";
				$signature .= " * \n";
				$signature .= " * @return string  The HTML form-ready value\n";
				$signature .= " */\n";
			}
			$encode_method = 'encode' . $camelized_column;
			$signature .= 'public function ' . $encode_method . '()';

			$signatures[$encode_method] = $signature;

			if (in_array($column, $image_columns)) {
				$signature = '';
				if ($include_doc_comments) {
					$signature .= "/**\n";
					$signature .= " * Takes the existing image and runs it through the prescribed Image method calls\n";
					$signature .= " * \n";
					$signature .= " * @return ActiveRecord  The record object, to allow for method chaining\n";
					$signature .= " */\n";
				}
				$process_method = 'process' . $camelized_column;
				$signature .= 'public function ' . $process_method . '()';

				$signatures[$process_method] = $signature;
			}

			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Prepares the filename of " . $column . " for output into HTML\n";
				$signature .= " * \n";
				$signature .= " * By default only the filename will be returned and any directory will be stripped.\n";
				$signature .= " * The \$include_web_path parameter changes this behaviour.\n";
				$signature .= " * \n";
				$signature .= " * @param  boolean \$include_web_path  If the full web path to the " . $noun . " should be included\n";
				$signature .= " * @return string  The HTML-ready value\n";
				$signature .= " */\n";
			}
			$prepare_method = 'prepare' . $camelized_column;
			$signature .= 'public function ' . $prepare_method . '($include_web_path=FALSE)';

			$signatures[$prepare_method] = $signature;

			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Takes a file uploaded through an HTML form for " . $column . " and moves it into the specified directory\n";
				$signature .= " * \n";
				$signature .= " * Any columns that were designated as inheriting from this column will get a copy\n";
				$signature .= " * of the uploaded file.\n";
				$signature .= " * \n";
				if ($noun == 'image') {
					$signature .= " * Any Image calls that were added to the column will be processed on the uploaded image.\n";
					$signature .= " * \n";
				}
				$signature .= " * @return File  The uploaded file\n";
				$signature .= " */\n";
			}
			$upload_method = 'upload' . $camelized_column;
			$signature .= 'public function ' . $upload_method . '()';

			$signatures[$upload_method] = $signature;

			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Takes a file that exists on the filesystem and copies it into the specified directory for " . $column . "\n";
				$signature .= " * \n";
				if ($noun == 'image') {
					$signature .= " * Any Image calls that were added to the column will be processed on the copied image.\n";
					$signature .= " * \n";
				}
				$signature .= " * @return ActiveRecord  The record object, to allow for method chaining\n";
				$signature .= " */\n";
			}
			$set_method = 'set' . $camelized_column;
			$signature .= 'public function ' . $set_method . '()';

			$signatures[$set_method] = $signature;

			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Returns metadata about " . $column . "\n";
				$signature .= " * \n";
				$signature .= " * @param  string \$element  The element to return. Must be one of: 'type', 'not_null', 'default', 'valid_values', 'max_length', 'feature', 'directory'.\n";
				$signature .= " * @return mixed  The metadata array or a single element\n";
				$signature .= " */\n";
			}
			$inspect_method = 'inspect' . $camelized_column;
			$signature .= 'public function ' . $inspect_method . '($element=NULL)';

			$signatures[$inspect_method] = $signature;
		}
	}


	/**
	 * Creates a copy of an uploaded file in the temp directory for the newly cloned record
	 *
	 * @internal
	 *
	 * @param  string $class   The class this value is for
	 * @param  string $column  The column the value is in
	 * @param  mixed  $value   The value
	 * @return mixed  The cloned File object
	 */
	static public function replicate(string $class, string $column, mixed $value): mixed
	{
		if (!$value instanceof File) {
			return $value;
		}

		// If the file we are replicating is in the temp dir, the copy can live there too
		if (strpos($value->getParent()->getPath(), self::TEMP_DIRECTORY . DIRECTORY_SEPARATOR) !== FALSE) {
			$value = clone $value;

		// Otherwise, the copy of the file must be placed in the temp dir so it is properly cleaned up
		} else {
			$upload_dir = self::$file_upload_columns[$class][$column];

			try {
				$temp_dir = new Directory($upload_dir->getPath() . self::TEMP_DIRECTORY . DIRECTORY_SEPARATOR);
			} catch (ValidationException $e) {
				$temp_dir = Directory::create($upload_dir->getPath() . self::TEMP_DIRECTORY . DIRECTORY_SEPARATOR);
			}

			$value = $value->duplicate($temp_dir);
		}

		return $value;
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
		self::$column_inheritence   = array();
		self::$fupload_method_calls = array();
		self::$file_upload_columns  = array();
		self::$fimage_method_calls  = array();
		self::$image_upload_columns = array();
		self::$transaction_level    = 0;
	}


	/**
	 * Rolls back a transaction, or decreases the level
	 *
	 * @internal
	 *
	 * @return void
	 */
	static public function rollback(): void
	{
		// If the transaction was started by something else, don't even track it
		if (self::$transaction_level == 0) {
			return;
		}

		self::$transaction_level--;

		if (!self::$transaction_level) {
			Filesystem::rollback();
		}
	}


	/**
	 * Copies a file from the filesystem to the file upload directory and sets it as the file for the specified column
	 *
	 * This method will perform the Image calls defined for the column.
	 *
	 * @internal
	 *
	 * @param  ActiveRecord $object            The ActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  array         &$cache            The cache array for the record
	 * @param  string        $method_name       The method that was called
	 * @param  array         $parameters        The parameters passed to the method
	 * @return ActiveRecord  The record object, to allow for method chaining
	 */
	static public function set(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache, string $method_name, array $parameters): ActiveRecord
	{
		$class = get_class($object);

		list ($action, $subject) = ORM::parseMethod($method_name);

		$column   = Grammar::underscorize($subject);
		$doc_root = realpath(isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '');

		if (!array_key_exists(0, $parameters)) {
			throw new ProgrammerException(
				'The method %s requires exactly one parameter',
				$method_name . '()'
			);
		}

		$file_path = $parameters[0];

		// Handle objects being passed in
		if ($file_path instanceof File) {
			$file_path = $file_path->getPath();
		} elseif (is_object($file_path) && is_callable(array($file_path, '__toString'))) {
			$file_path = $file_path->__toString();
		} elseif (is_object($file_path)) {
			$file_path = (string) $file_path;
		}

		if ($file_path !== NULL && $file_path !== '' && $file_path !== FALSE) {
			if (!$file_path || (!file_exists($file_path) && !file_exists($doc_root . $file_path))) {
				throw new EnvironmentException(
					'The file specified, %s, does not exist. This may indicate a missing enctype="multipart/form-data" attribute in form tag.',
					$file_path
				);
			}

			if (!file_exists($file_path) && file_exists($doc_root . $file_path)) {
				$file_path = $doc_root . $file_path;
			}

			if (is_dir($file_path)) {
				throw new ProgrammerException(
					'The file specified, %s, is not a file but a directory',
					$file_path
				);
			}

			$upload_dir = self::$file_upload_columns[$class][$column];

			try {
				$temp_dir = new Directory($upload_dir->getPath() . self::TEMP_DIRECTORY . DIRECTORY_SEPARATOR);
			} catch (ValidationException $e) {
				$temp_dir = Directory::create($upload_dir->getPath() . self::TEMP_DIRECTORY . DIRECTORY_SEPARATOR);
			}

			$file     = Filesystem::createObject($file_path);
			$new_file = $file->duplicate($temp_dir);

		} else {
			$new_file = NULL;
		}

		ActiveRecord::assign($values, $old_values, $column, $new_file);

		// Perform column inheritance
		if (!empty(self::$column_inheritence[$class][$column])) {
			foreach (self::$column_inheritence[$class][$column] as $other_column) {
				self::set($object, $values, $old_values, $related_records, $cache, 'set' . Grammar::camelize($other_column, TRUE), array($file));
			}
		}

		if ($new_file) {
			self::processImage($class, $column, $new_file);
		}

		return $object;
	}


	/**
	 * Sets up an Upload object for a specific column
	 *
	 * @param  string $class   The class to set up for
	 * @param  string $column  The column to set up for
	 * @return Upload  The configured Upload object
	 */
	static private function setUpFUpload(string $class, string $column): Upload
	{
		$upload = new Upload();

		// Set up the Upload class
		if (!empty(self::$fupload_method_calls[$class][$column])) {
			foreach (self::$fupload_method_calls[$class][$column] as $method_call) {
				if (!is_callable($upload->{$method_call['method']})) {
					throw new ProgrammerException(
						'The Upload method specified, %s, is not a valid method',
						$method_call['method'] . '()'
					);
				}
				call_user_func_array($upload->{$method_call['method']}, $method_call['parameters']);
			}
		}

		return $upload;
	}


	/**
	 * Uploads a file
	 *
	 * @internal
	 *
	 * @param  ActiveRecord $object            The ActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  array         &$cache            The cache array for the record
	 * @param  string        $method_name       The method that was called
	 * @param  array         $parameters        The parameters passed to the method
	 * @return File  The uploaded file
	 */
	static public function upload(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache, string $method_name, array $parameters): mixed
	{
		$class = get_class($object);

		list ($action, $subject) = ORM::parseMethod($method_name);
		$column = Grammar::underscorize($subject);

		$existing_temp_file = FALSE;


		// Try to upload the file putting it in the temp dir incase there is a validation problem with the record
		try {
			$upload_dir = self::$file_upload_columns[$class][$column];
			$temp_dir   = self::prepareTempDir($upload_dir);

			if (!Upload::check($column)) {
				throw new ExpectedException('Please upload a file');
			}

			$uploader = self::setUpFUpload($class, $column);
			$file     = $uploader->move($temp_dir, $column);

		// If there was an eror, check to see if we have an existing file
		} catch (ExpectedException $e) {

			// If there is an existing file and none was uploaded, substitute the existing file
			$existing_file = Request::get('existing-' . $column);
			$delete_file   = Request::get('delete-' . $column, 'boolean');
			$no_upload     = $e->getMessage() == self::compose('Please upload a file');

			if ($existing_file && $delete_file && $no_upload) {
				$file = NULL;

			} elseif ($existing_file) {

				$file_path = $upload_dir->getPath() . $existing_file;
				$file      = Filesystem::createObject($file_path);

				$current_file = $values[$column];

				// If the existing file is the same as the current file, we can just exit now
				if ($current_file && $current_file instanceof File && $file->getPath() == $current_file->getPath()) {
					return null;
				}

				$existing_temp_file = TRUE;

			} else {
				$file = NULL;
			}
		}

		// Assign the file
		ActiveRecord::assign($values, $old_values, $column, $file);

		// Perform the file upload inheritance
		if (!empty(self::$column_inheritence[$class][$column])) {
			foreach (self::$column_inheritence[$class][$column] as $other_column) {

				if ($file) {

					// Image columns will only inherit if it is an Image object
					if (!$file instanceof Image && isset(self::$image_upload_columns[$class]) && array_key_exists($other_column, self::$image_upload_columns[$class])) {
						continue;
					}

					$other_upload_dir = self::$file_upload_columns[$class][$other_column];
					$other_temp_dir   = self::prepareTempDir($other_upload_dir);

					if ($existing_temp_file) {
						$other_file = Filesystem::createObject($other_temp_dir->getPath() . $file->getName());
					} else {
						$other_file = $file->duplicate($other_temp_dir, FALSE);
					}

				} else {
					$other_file = $file;
				}

				ActiveRecord::assign($values, $old_values, $other_column, $other_file);

				if (!$existing_temp_file && $other_file) {
					self::processImage($class, $other_column, $other_file);
				}
			}
		}

		// Process the file
		if (!$existing_temp_file && $file) {
			self::processImage($class, $column, $file);
		}

		return $file;
	}


	/**
	 * Validates uploaded files to ensure they match all of the criteria defined
	 *
	 * @internal
	 *
	 * @param  ActiveRecord $object                The ActiveRecord instance
	 * @param  array         &$values               The current values
	 * @param  array         &$old_values           The old values
	 * @param  array         &$related_records      Any records related to this record
	 * @param  array         &$cache                The cache array for the record
	 * @param  array         &$validation_messages  The existing validation messages
	 * @return void
	 */
	static public function validate(ActiveRecord $object, mixed &$values, mixed &$old_values, mixed &$related_records, mixed &$cache, mixed &$validation_messages): void
	{
		$class = get_class($object);

		foreach (self::$file_upload_columns[$class] as $column => $directory) {
			$column_name = ORM::getColumnName($class, $column);

			if (isset($validation_messages[$column])) {
				$search_message  = self::compose('%sPlease enter a value', ValidationException::formatField($column_name));
				$replace_message = self::compose('%sPlease upload a file', ValidationException::formatField($column_name));
				$validation_messages[$column] = str_replace($search_message, $replace_message, $validation_messages[$column]);
			}

			// Grab the error that occured
			try {
				if (Upload::check($column)) {
					$uploader = self::setUpFUpload($class, $column);
					$uploader->validate($column);
				}
			} catch (ValidationException $e) {
				if ($e->getMessage() != self::compose('Please upload a file')) {
					$validation_messages[$column] = ValidationException::formatField($column_name) . $e->getMessage();
				}
			}
		}
	}


	/**
	 * Forces use as a static class
	 *
	 * @return ORMFile
	 */
	private function __construct() { }
}



/**
 * Copyright (c) 2008-2011 Will Bond <will@flourishlib.com>
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
