<?php

declare(strict_types=1);

/**
 * Provides validation and movement of uploaded files
 *
 * @copyright  Copyright (c) 2007-2012 Will Bond, others
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @author     Will Bond, iMarc LLC [wb-imarc] <will@imarc.net>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 * @link       http://flourishlib.com/Upload
 * @phpstan-type UploadFileEntry array{name: string, type: string, tmp_name: string, error: int, size: int}
 * @phpstan-type UploadImageDimensions array{min_width: int, min_height: int, max_width: int, max_height: int}
 * @phpstan-type UploadImageRatio array{width: float, height: float, allow_excess_dimension: string}
 *
 * @version    1.0.0b15
 * @changes    1.0.0b15  Fixed an undefined variable error in ::setMaxSize() [wb, 2012-09-16]
 * @changes    1.0.0b14  Fixed some method signatures [wb, 2011-08-24]
 * @changes    1.0.0b13  Changed the class to throw ValidationException objects instead of ProgrammerException objects when the form is improperly configured - this is to prevent error logs when bad requests are sent by scanners/hackers [wb, 2011-08-24]
 * @changes    1.0.0b12  Fixed the ::filter() callback constant [wb, 2010-11-24]
 * @changes    1.0.0b11  Added ::setImageDimensions() and ::setImageRatio() [wb-imarc, 2010-11-11]
 * @changes    1.0.0b10  BackwardsCompatibilityBreak - renamed ::setMaxFilesize() to ::setMaxSize() to be consistent with File::getSize() [wb, 2010-05-30]
 * @changes    1.0.0b9   BackwardsCompatibilityBreak - the class no longer accepts uploaded files that start with a `.` unless ::allowDotFiles() is called - added ::setOptional() [wb, 2010-05-30]
 * @changes    1.0.0b8   BackwardsCompatibilityBreak - ::validate() no longer returns the `$_FILES` array for the file being validated - added `$return_message` parameter to ::validate(), fixed a bug with detection of mime type for text files [wb, 2010-05-26]
 * @changes    1.0.0b7   Added ::filter() to allow for ignoring array file upload field entries that did not have a file uploaded [wb, 2009-10-06]
 * @changes    1.0.0b6   Updated ::move() to use the new Filesystem::createObject() method [wb, 2009-01-21]
 * @changes    1.0.0b5   Removed some unnecessary error suppression operators from ::move() [wb, 2009-01-05]
 * @changes    1.0.0b4   Updated ::validate() so it properly handles upload max filesize specified in human-readable notation [wb, 2009-01-05]
 * @changes    1.0.0b3   Removed the dependency on Request [wb, 2009-01-05]
 * @changes    1.0.0b2   Fixed a bug with validating filesizes [wb, 2008-11-25]
 * @changes    1.0.0b    The initial implementation [wb, 2007-06-14]
 */
class Upload
{
	// The following constants allow for nice looking callbacks to static methods
	const string check  = 'Upload::check';
	const string count  = 'Upload::count';
	const string filter = 'Upload::filter';


	/**
	 * Checks to see if the field specified is a valid file upload field
	 *
	 * @throws ValidationException  If `$throw_exception` is `TRUE` and the request was not a POST or the content type is not multipart/form-data
	 *
	 * @param  string  $field            The field to check
	 * @param  boolean $throw_exception  If an exception should be thrown when there are issues with the form
	 * @return boolean  If the field is a valid file upload field
	 */
	public static function check(string $field, bool $throw_exception = true): bool
	{
		if (isset($_GET[$field]) && ($_SERVER['REQUEST_METHOD'] ?? '') != 'POST') {
			if ($throw_exception) {
				throw new ValidationException(
					'Missing method="post" attribute in form tag'
				);
			}
			return false;
		}

		if (isset($_POST[$field])) {
			$content_type = $_SERVER['CONTENT_TYPE'] ?? null;
			if (!is_string($content_type) || stripos($content_type, 'multipart/form-data') === false) {
				if ($throw_exception) {
					throw new ValidationException(
						'Missing enctype="multipart/form-data" attribute in form tag'
					);
				}
				return false;
			}
		}

		return isset($_FILES[$field]) && is_array($_FILES[$field]);
	}


	/**
	 * Returns the number of files uploaded to a file upload array field
	 *
	 * @throws ValidationException  If the form is not properly configured for file uploads
	 *
	 * @param  string  $field  The field to get the number of files for
	 * @return integer  The number of uploaded files
	 */
	public static function count(mixed $field): int
	{
		if (!self::check($field)) {
			throw new ValidationException(
				'The field specified, %s, does not appear to be a file upload field',
				$field
			);
		}

		$file_array = $_FILES[$field];
		if (!is_array($file_array)) {
			throw new ProgrammerException(
				'The field specified, %s, does not appear to be a file upload field',
				$field
			);
		}

		if (!is_array($file_array['name'])) {
			throw new ValidationException(
				'The field specified, %s, does not appear to be an array file upload field',
				$field
			);
		}

		return sizeof($file_array['name']);
	}


	/**
	 * Composes text using Text if loaded
	 *
	 * @param  string  $message    The message to compose
	 * @return string  The composed and possible translated message
	 */
	protected static function compose(string $message): string
	{
		$args = array_slice(func_get_args(), 1);
		$args = array_map(
			static fn(mixed $arg): bool|float|int|string|null => ($arg === null || is_scalar($arg)) ? $arg : get_debug_type($arg),
			$args
		);

		if (class_exists('Text', false)) {
			return call_user_func_array(
				array('Text', 'compose'),
				array($message, $args)
			);
		} else {
			return vsprintf($message, $args);
		}
	}


	/**
	 * Removes individual file upload entries from an array of file inputs in `$_FILES` when no file was uploaded
	 *
	 * @throws ValidationException  If the form is not properly configured for file uploads
	 *
	 * @param  string  $field  The field to filter
	 * @return array<int, int|string>|NULL  The indexes of the files that were uploaded, or `NULL` if no file was uploaded
	 */
	public static function filter(string $field): mixed
	{
		$indexes = array();
		$columns = array('name', 'type', 'tmp_name', 'error', 'size');

		if (!self::count($field)) {
			return null;
		}

		$file_array = $_FILES[$field];
		if (!is_array($file_array)) {
			throw new ProgrammerException(
				'The field specified, %s, does not appear to be a file upload field',
				$field
			);
		}

		$name_column  = $file_array['name'];
		$error_column = $file_array['error'];
		if (!is_array($name_column) || !is_array($error_column)) {
			throw new ProgrammerException(
				'The field specified, %s, does not appear to be an array file upload field',
				$field
			);
		}

		foreach (array_keys($name_column) as $index) {
			if (($error_column[$index] ?? 0) == UPLOAD_ERR_NO_FILE) {
				foreach ($columns as $column) {
					if (isset($file_array[$column]) && is_array($file_array[$column])) {
						unset($file_array[$column][$index]);
					}
				}
			} else {
				$indexes[] = $index;
			}
		}

		$_FILES[$field] = $file_array;

		return $indexes;
	}


	/**
	 * Normalizes an arbitrary `$_FILES` value to a string
	 *
	 * @internal
	 *
	 * @param  mixed $value  The value to normalize
	 * @return string  The normalized value
	 */
	private static function normalizeString(mixed $value): string
	{
		return is_scalar($value) ? (string) $value : '';
	}


	/**
	 * Normalizes an arbitrary `$_FILES` value to an integer
	 *
	 * @internal
	 *
	 * @param  mixed $value  The value to normalize
	 * @return int  The normalized value
	 */
	private static function normalizeInt(mixed $value): int
	{
		return is_scalar($value) ? (int) $value : 0;
	}


	/**
	 * Converts an arbitrary file size value to a number of bytes
	 *
	 * @internal
	 *
	 * @param  mixed $value  The size to convert
	 * @return int  The number of bytes represented by the size
	 */
	private static function normalizeFileSize(mixed $value): int
	{
		if (is_numeric($value)) {
			return (int) $value;
		}
		if (is_string($value)) {
			return Filesystem::convertToBytes($value);
		}
		throw new ProgrammerException(
			'The size specified, %s, is not a valid size',
			get_debug_type($value)
		);
	}


	/**
	 * If files starting with `.` can be uploaded
	 *
	 * @var boolean
	 */
	private bool $allow_dot_files = false;

	/**
	 * If PHP files can be uploaded
	 *
	 * @var boolean
	 */
	private bool $allow_php = false;

	/**
	 * The dimension restrictions for uploaded images
	 *
	 * @var UploadImageDimensions|array{}
	 */
	private array $image_dimensions = array();

	/**
	 * The dimension ratio restriction for uploaded images
	 *
	 * @var UploadImageRatio|array{}
	 */
	private array $image_ratio = array();

	/**
	 * If existing files of the same name should be overwritten
	 *
	 * @var boolean
	 */
	private bool $enable_overwrite = false;

	/**
	 * The maximum file size in bytes
	 *
	 * @var integer
	 */
	private int $max_size = 0;

	/**
	 * The error message to display if the mime types do not match
	 *
	 * @var string
	 */
	private ?string $mime_type_message = null;

	/**
	 * The mime types of files accepted
	 *
	 * @var array<int, string>
	 */
	private array $mime_types = array();

	/**
	 * If the file upload is required
	 *
	 * @var boolean
	 */
	private bool $required = true;


	/**
	 * All requests that hit this method should be requests for callbacks
	 *
	 * @internal
	 *
	 * @param  string $method  The method to create a callback for
	 * @return array{0: self, 1: string}  The callback for the method requested
	 */
	public function __get(string $method)
	{
		return array($this, $method);
	}


	/**
	 * Sets the upload class to allow files starting with a `.`
	 *
	 * Files starting with `.` may change the behaviour of web servers,
	 * for instance `.htaccess` files for Apache.
	 *
	 * @return void
	 */
	public function allowDotFiles(): void
	{
		$this->allow_dot_files = true;
	}


	/**
	 * Sets the upload class to allow PHP files
	 *
	 * @return void
	 */
	public function allowPHP(): void
	{
		$this->allow_php = true;
	}


	/**
	 * Set the class to overwrite existing files in the destination directory instead of renaming the file
	 *
	 * @return void
	 */
	public function enableOverwrite(): void
	{
		$this->enable_overwrite = true;
	}


	/**
	 * Returns the `$_FILES` array for the field specified.
	 *
	 * @param  string $field  The field to get the file array for
	 * @param  mixed  $index  If the field is an array file upload field, use this to specify which array index to return
	 * @return UploadFileEntry  The file info array from `$_FILES`
	 * @throws ValidationException
	 */
	private function extractFileUploadArray(string $field, mixed $index = null): array
	{
		$file_data = $_FILES[$field];
		if (!is_array($file_data)) {
			throw new ValidationException(
				'The field specified, %s, does not appear to be a file upload field',
				$field
			);
		}

		if ($index === null) {
			$entry = $file_data;
		} else {
			$name_column = $file_data['name'];
			if (!is_array($name_column)) {
				throw new ValidationException(
					'The field specified, %s, does not appear to be an array file upload field',
					$field
				);
			}

			if (!is_int($index) && !is_string($index)) {
				throw new ValidationException(
					'The index specified, %1$s, is invalid for the field %2$s',
					$index,
					$field
				);
			}

			if (!isset($name_column[$index])) {
				throw new ValidationException(
					'The index specified, %1$s, is invalid for the field %2$s',
					$index,
					$field
				);
			}

			$entry = array(
				'name'     => $name_column[$index] ?? '',
				'type'     => (!is_array($file_data['type'])) ? '' : ($file_data['type'][$index] ?? ''),
				'tmp_name' => (!is_array($file_data['tmp_name'])) ? '' : ($file_data['tmp_name'][$index] ?? ''),
				'error'    => (!is_array($file_data['error'])) ? 0 : ($file_data['error'][$index] ?? 0),
				'size'     => (!is_array($file_data['size'])) ? 0 : ($file_data['size'][$index] ?? 0)
			);
		}

		return array(
			'name'     => self::normalizeString($entry['name'] ?? ''),
			'type'     => self::normalizeString($entry['type'] ?? ''),
			'tmp_name' => self::normalizeString($entry['tmp_name'] ?? ''),
			'error'    => self::normalizeInt($entry['error'] ?? 0),
			'size'     => self::normalizeInt($entry['size'] ?? 0)
		);
	}


	/**
	 * Moves an uploaded file from the temp directory to a permanent location
	 *
	 * @throws ValidationException  When the form is not setup for file uploads, the `$directory` is somehow invalid or ::validate() thows an exception
	 *
	 * @param  string|Directory $directory  The directory to upload the file to
	 * @param  string            $field      The file upload field to get the file from
	 * @param  mixed             $index      If the field was an array file upload field, upload the file corresponding to this index
	 * @return File|Image|NULL  A File (or Image) object, or `NULL` if no file was uploaded
	 */
	public function move(mixed $directory, string $field, mixed $index = null): mixed
	{
		if (!is_object($directory)) {
			$directory = new Directory($directory);
		}

		if (!$directory->isWritable()) {
			throw new EnvironmentException(
				'The directory specified, %s, is not writable',
				$directory->getPath()
			);
		}

		if (!self::check($field)) {
			throw new ValidationException(
				'The field specified, %s, does not appear to be a file upload field',
				$field
			);
		}

		$file_array = $this->extractFileUploadArray($field, $index);
		$error      = $this->validateField($file_array);
		if ($error) {
			throw new ValidationException($error);
		}

		// This will only ever be true if the file is optional
		if ($file_array['name'] == '' || $file_array['tmp_name'] == '' || $file_array['size'] == 0) {
			return null;
		}

		$file_name  = Filesystem::makeURLSafe($file_array['name']);

		$file_name = $directory->getPath() . $file_name;
		if (!$this->enable_overwrite) {
			$file_name = Filesystem::makeUniqueName($file_name);
		}

		if (!move_uploaded_file($file_array['tmp_name'], $file_name)) {
			throw new EnvironmentException('There was an error moving the uploaded file');
		}

		if (!chmod($file_name, 0644)) {
			throw new EnvironmentException('Unable to change permissions on the uploaded file');
		}

		$object = Filesystem::createObject($file_name);
		if ($object instanceof Directory) {
			throw new ProgrammerException('The uploaded file was moved to a directory');
		}

		return $object;
	}


	/**
	 * Sets the allowable dimensions for an uploaded image
	 *
	 * @param  integer $min_width   The minimum width - `0` for no minimum
	 * @param  integer $min_height  The minimum height - `0` for no minimum
	 * @param  integer $max_width   The maximum width - `0` for no maximum
	 * @param  integer $max_height  The maximum height - `0` for no maximum
	 * @return void
	 */
	public function setImageDimensions(int $min_width, int $min_height, int $max_width = 0, int $max_height = 0): void
	{
		if ($min_width < 0) {
			throw new ProgrammerException(
				'The minimum width specified, %s, is not an integer, or is less than 0',
				$min_width
			);
		}
		if ($min_height < 0) {
			throw new ProgrammerException(
				'The minimum height specified, %s, is not an integer, or is less than 0',
				$min_height
			);
		}
		if ($max_width < 0) {
			throw new ProgrammerException(
				'The maximum width specified, %s, is not an integer, or is less than 0',
				$max_width
			);
		}
		if ($max_height < 0) {
			throw new ProgrammerException(
				'The maximum height specified, %s, is not an integer, or is less than 0',
				$max_height
			);
		}

		settype($min_width, 'int');
		settype($min_height, 'int');
		settype($max_width, 'int');
		settype($max_height, 'int');

		// If everything is 0 then there are no restrictions
		if (!$min_width && !$min_height && !$max_width && !$max_height) {
			$this->image_dimensions = array();
			return;
		}

		$this->image_dimensions = array(
			'min_width'  => $min_width,
			'min_height' => $min_height,
			'max_width'  => $max_width,
			'max_height' => $max_height
		);
	}


	/**
	 * Sets the allowable dimensions for an uploaded image
	 *
	 * @param  mixed  $width                   The minimum ratio width
	 * @param  mixed  $height                  The minimum ratio height
	 * @param  string  $allow_excess_dimension  The dimension that should allow for excess pixels
	 * @return void
	 */
	public function setImageRatio(mixed $width, mixed $height, string $allow_excess_dimension): void
	{
		if (!is_numeric($width) || (float) $width <= 0) {
			throw new ProgrammerException(
				'The width specified, %s, is not a number, or is less than or equal to 0',
				$width
			);
		}
		if (!is_numeric($height) || (float) $height <= 0) {
			throw new ProgrammerException(
				'The height specified, %s, is not a number, or is less than or equal to 0',
				$height
			);
		}

		$valid_dimensions = array('width', 'height');
		if (!in_array($allow_excess_dimension, $valid_dimensions)) {
			throw new ProgrammerException(
				'The allow excess dimension specified, %1$s, is not valid. Must be one of: %2$s.',
				$allow_excess_dimension,
				$valid_dimensions
			);
		}

		$this->image_ratio = array(
			'width'                  => (float) $width,
			'height'                 => (float) $height,
			'allow_excess_dimension' => $allow_excess_dimension
		);
	}


	/**
	 * Sets the maximum size the uploaded file may be
	 *
	 * This method should be used with the
	 * [http://php.net/file-upload.post-method `MAX_FILE_SIZE`] hidden form
	 * input since the hidden form input will reject a file that is too large
	 * before the file completely uploads, while this method will wait until the
	 * whole file has been uploaded. This method should always be used since it
	 * is very easy for the `MAX_FILE_SIZE` post field to be manipulated on the
	 * client side.
	 *
	 * This method can only further restrict the
	 * [http://php.net/upload_max_filesize `upload_max_filesize` ini setting],
	 * it can not increase that setting. `upload_max_filesize` must be set
	 * in the php.ini (or an Apache configuration) since file uploads are
	 * handled before the request is handed off to PHP.
	 *
	 * @param  string $size  The maximum file size (e.g. `1MB`, `200K`, `10.5M`) - `0` for no limit
	 * @return void
	 */
	public function setMaxSize(string $size): void
	{
		$ini_max_size = ini_get('upload_max_filesize');
		if ($ini_max_size === false) {
			throw new ProgrammerException(
				'The %s ini setting could not be found',
				'upload_max_filesize'
			);
		}
		$ini_max_size = self::normalizeFileSize($ini_max_size);

		$size = Filesystem::convertToBytes($size);

		if ($size && $size > $ini_max_size) {
			throw new EnvironmentException(
				'The requested max file upload size, %1$s, is larger than the %2$s ini setting, which is currently set at %3$s. The ini setting must be increased to allow files of this size.',
				$size,
				'upload_max_filesize',
				$ini_max_size
			);
		}

		$this->max_size = $size;
	}


	/**
	 * Sets the file mime types accepted
	 *
	 * @param  array<int, string>  $mime_types  The mime types to accept
	 * @param  string             $message     The message to display if the uploaded file is not one of the mime type specified
	 * @return void
	 */
	public function setMIMETypes(array $mime_types, string $message): void
	{
		$this->mime_types        = $mime_types;
		$this->mime_type_message = $message;
	}


	/**
	 * Sets the file upload to be optional instead of required
	 *
	 * @return void
	 */
	public function setOptional(): void
	{
		$this->required = false;
	}


	/**
	 * Validates the uploaded file, ensuring a file was actually uploaded and that is matched the restrictions put in place
	 *
	 * @throws ValidationException  When the form is not configured for file uploads, no file is uploaded or the uploaded file violates the options set for this object
	 *
	 * @param  string  $field           The field the file was uploaded through
	 * @param  mixed   $index           If the field was an array of file uploads, this specifies which one to validate
	 * @param  boolean $return_message  If any validation error should be returned as a string instead of being thrown as an ValidationException
	 * @return NULL|string  If `$return_message` is not `TRUE` or if no error occurs, `NULL`, otherwise a string error message
	 */
	public function validate(string $field, mixed $index = null, ?bool $return_message = null): mixed
	{
		if (is_bool($index) && $return_message === null) {
			$return_message = $index;
			$index          = null;
		}

		if (!self::check($field)) {
			throw new ValidationException(
				'The field specified, %s, does not appear to be a file upload field',
				$field
			);
		}

		$file_array = $this->extractFileUploadArray($field, $index);
		$error      = $this->validateField($file_array);
		if ($error) {
			if ($return_message) {
				return $error;
			}
			throw new ValidationException($error);
		}

		return null;
	}


	/**
	 * Validates a $_FILES array against the upload configuration
	 *
	 * @param UploadFileEntry $file_array  The `$_FILES` array for a single file
	 * @return NULL|string  The validation error message, or `NULL` if there is no error
	 * @throws ValidationException
	 */
	private function validateField(array $file_array): ?string
	{
		if (empty($file_array['name'])) {
			if ($this->required) {
				return self::compose('Please upload a file');
			}
			return null;
		}

		if ($file_array['error'] == UPLOAD_ERR_FORM_SIZE || $file_array['error'] == UPLOAD_ERR_INI_SIZE) {
			$max_size = (!empty($_POST['MAX_FILE_SIZE'])) ? $_POST['MAX_FILE_SIZE'] : ini_get('upload_max_filesize');
			$max_size = self::normalizeFileSize($max_size);
			return self::compose(
				'The file uploaded is over the limit of %s',
				Filesystem::formatFilesize($max_size)
			);
		}
		if ($this->max_size && $file_array['size'] > $this->max_size) {
			return self::compose(
				'The file uploaded is over the limit of %s',
				Filesystem::formatFilesize($this->max_size)
			);
		}

		if (empty($file_array['tmp_name']) || empty($file_array['size'])) {
			if ($this->required) {
				return self::compose('Please upload a file');
			}
			return null;
		}

		if (!empty($this->mime_types) && file_exists($file_array['tmp_name'])) {
			$contents = file_get_contents($file_array['tmp_name'], false, null, 0, 4096);
			if ($contents === false) {
				$contents = null;
			}
			if (!in_array(File::determineMimeType($file_array['name'], $contents), $this->mime_types)) {
				if ($this->mime_type_message === null) {
					throw new ProgrammerException('No mime type message was specified for the uploaded file');
				}
				return self::compose($this->mime_type_message);
			}
		}

		if (!$this->allow_php) {
			$file_info = Filesystem::getPathInfo($file_array['name']);
			if (in_array(strtolower($file_info['extension']), array('php', 'php4', 'php5'))) {
				return self::compose('The file uploaded is a PHP file, but those are not permitted');
			}
		}

		if (!$this->allow_dot_files) {
			if (substr($file_array['name'], 0, 1) == '.') {
				return self::compose('The name of the uploaded file may not being with a .');
			}
		}

		if ($this->image_dimensions && file_exists($file_array['tmp_name'])) {
			if (Image::isImageCompatible($file_array['tmp_name'])) {
				$image_info = getimagesize($file_array['tmp_name']);
				if ($image_info === false) {
					throw new ValidationException('Unable to determine the dimensions of the uploaded image');
				}
				$width  = (int) $image_info[0];
				$height = (int) $image_info[1];

				if ($this->image_dimensions['min_width'] && $width < $this->image_dimensions['min_width']) {
					return self::compose(
						'The uploaded image is narrower than the minimum width of %spx',
						$this->image_dimensions['min_width']
					);
				}

				if ($this->image_dimensions['min_height'] && $height < $this->image_dimensions['min_height']) {
					return self::compose(
						'The uploaded image is shorter than the minimum height of %spx',
						$this->image_dimensions['min_height']
					);
				}

				if ($this->image_dimensions['max_width'] && $width > $this->image_dimensions['max_width']) {
					return self::compose(
						'The uploaded image is wider than the maximum width of %spx',
						$this->image_dimensions['max_width']
					);
				}

				if ($this->image_dimensions['max_height'] && $height > $this->image_dimensions['max_height']) {
					return self::compose(
						'The uploaded image is taller than the maximum height of %spx',
						$this->image_dimensions['max_height']
					);
				}
			}
		}

		if ($this->image_ratio && file_exists($file_array['tmp_name'])) {
			if (Image::isImageCompatible($file_array['tmp_name'])) {
				$image_info = getimagesize($file_array['tmp_name']);
				if ($image_info === false) {
					throw new ValidationException('Unable to determine the dimensions of the uploaded image');
				}
				$width  = (int) $image_info[0];
				$height = (int) $image_info[1];

				if ($this->image_ratio['allow_excess_dimension'] == 'width' && $width / $height < $this->image_ratio['width'] / $this->image_ratio['height']) {
					return self::compose(
						'The uploaded image is too narrow for its height. The required ratio is %1$sx%2$s or wider.',
						$this->image_ratio['width'],
						$this->image_ratio['height']
					);
				}

				if ($this->image_ratio['allow_excess_dimension'] == 'height' && $width / $height > $this->image_ratio['width'] / $this->image_ratio['height']) {
					return self::compose(
						'The uploaded image is too short for its width. The required ratio is %1$sx%2$s or taller.',
						$this->image_ratio['width'],
						$this->image_ratio['height']
					);
				}
			}
		}

		return null;
	}
}



/**
 * Copyright (c) 2007-2012 Will Bond <will@flourishlib.com>, others
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
