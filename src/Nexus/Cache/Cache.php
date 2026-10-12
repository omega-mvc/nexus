<?php

declare(strict_types=1);

/**
 * A simple interface to cache data using different backends
 *
 * @copyright  Copyright (c) 2009-2012 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 * @link       http://flourishlib.com/Cache
 *
 * @version    1.0.0b6
 * @changes    1.0.0b6  Fixed a bug with ::add() setting a value when it shouldn't if no ttl was given for the file backend [wb, 2012-01-12]
 * @changes    1.0.0b5  Added missing documentation for using Redis as a backend [wb, 2011-08-25]
 * @changes    1.0.0b4  Added the `database`, `directory` and `redis` types, added support for the memcached extention and support for custom serialization callbacks [wb, 2011-06-21]
 * @changes    1.0.0b3  Added `0` to the memcache delete method call since otherwise the method triggers notices on some installs [wb, 2011-05-10]
 * @changes    1.0.0b2  Fixed API calls to the memcache extension to pass the TTL as the correct parameter [wb, 2011-02-01]
 * @changes    1.0.0b   The initial implementation [wb, 2009-04-28]
 */
/**
 * @phpstan-type FileCacheStore array<string, array{value: string, expire: int}>
 */
class Cache
{
	/**
	 * The cache configuration, used for database, directory and file caches
	 *
	 * The array structure for database caches is:
	 * {{{
	 * array(
	 *     'table'        => (string) {the database table to use},
	 *     'key_column'   => (string) {the varchar column to store the key in, should be able to handle at least 250 characters},
	 *     'value_column' => (string) {the text/varchar column to store the value in},
	 *     'ttl_column'   => (string) {the integer column to store the expiration time in}
	 * )
	 * }}}
	 *
	 * The array structure for directory caches:
	 * {{{
	 * array(
	 *     'path' => (string) {the directory path with trailing slash}
	 * )
	 * }}}
	 *
	 * The array structure for file caches:
	 * {{{
	 * array(
	 *     'path'  => (string) {the file path},
	 *     'state' => (string) {clean or dirty, used to appropriately save}
	 * )
	 * }}}
	 *
	 * @var array<string, mixed>
	 */
	protected array $config;

	/**
	 * The data store to use
	 *
	 * Either the:
	 *  - array structure for file cache
	 *  - Memcached object for memcached
	 *  - Database object for database
	 *  - Redis object for redis
	 *
	 * Not used for apcu or directory
	 *
	 * @var FileCacheStore|Database|Memcached|Redis|null
	 */
	protected mixed $data_store = null;

	/**
	 * The type of cache
	 *
	 * The valid values are:
	 *  - `'apcu'`
	 *  - `'database'`
	 *  - `'directory'`
	 *  - `'file'`
	 *  - `'memcached'`
	 *  - `'redis'`
	 *
	 * @var string
	 */
	protected string $type;

	/**
	 * Set the type and master key for the cache
	 *
	 * A `file` cache uses a single file to store values in an associative
	 * array and is probably not suitable for a large number of keys.
	 *
	 * Using an `apcu` cache will have far better performance than a file or
	 * directory, however please remember that keys are shared server-wide.
	 *
	 * `$config` is an associative array of configuration options for the various
	 * backends. Some backend require additional configuration, while others
	 * provide provide optional settings.
	 *
	 * The following `$config` options must be set for the `database` backend:
	 *
	 *  - `table`: The database table to use for caching
	 *  - `key_column`: The column to store the cache key in - must support at least 250 character strings
	 *  - `value_column`: The column to store the serialized value in - this should probably be a `TEXT` column to support large values, or `BLOB` if binary serialization is used
	 *  - `value_data_type`: If a `BLOB` column is being used for the `value_column`, this should be set to 'blob', otherwise `string`
	 *  - `ttl_column`: The column to store the expiration timestamp of the cached entry - this should be an integer
	 *
	 * The following `$config` for the following items can be set for all backends:
	 *
	 *  - `serializer`: A callback to serialize data with, defaults to the PHP function `serialize()`
	 *  - `unserializer`: A callback to unserialize data with, defaults to the PHP function `unserialize()`
	 *
	 * Common serialization callbacks include:
	 *
	 *  - `json_encode`/`json_decode`
	 *  - `igbinary_serialize`/`igbinary_unserialize`
	 *
	 * Please note that using JSON for serialization will exclude all non-public
	 * properties of objects from being serialized.
	 *
	 * A custom `serialize` and `unserialze` option is `string`, which will cast
	 * all values to a string when storing, instead of serializing them. If a
	 * `__toString()` method is provided for objects, it will be called.
	 *
	 * @param  string $type        The type of caching to use: `'apcu'`, `'database'`, `'directory'`, `'file'`, `'memcached'`, `'redis'`
	 * @param  mixed  $data_store  The path for a `file` or `directory` cache, a `Memcached` object for a `memcached` cache, an Database object for a `database` cache or a `Redis` object for a `redis` cache - not used for `apcu`
	 * @param  array<string, mixed>  $config      Configuration options - see method description for details
	 * @return Cache
	 */
	public function __construct(mixed $type, mixed $data_store = null, mixed $config = array())
	{
		switch ($type) {
			case 'database':
				foreach (array('table', 'key_column', 'value_column', 'ttl_column') as $key) {
					if (empty($config[$key])) {
						throw new ProgrammerException(
							'The config key %s is not set',
							$key
						);
					}
				}
				$this->config = $config;
				if (!isset($this->config['value_data_type'])) {
					$this->config['value_data_type'] = 'string';
				}
				if (!$data_store instanceof Database) {
					throw new ProgrammerException(
						'The data store provided is not a valid %s object',
						'Database'
					);
				}
				$this->data_store = $data_store;
				break;

			case 'directory':
				if (!is_string($data_store)) {
					throw new ProgrammerException(
						'The path for a directory cache must be a string'
					);
				}
				$exists = file_exists($data_store);
				if (!$exists) {
					throw new EnvironmentException(
						'The directory specified, %s, does not exist',
						$data_store
					);
				}
				if (!is_dir($data_store)) {
					throw new EnvironmentException(
						'The path specified, %s, is not a directory',
						$data_store
					);
				}
				if (!is_writable($data_store)) {
					throw new EnvironmentException(
						'The directory specified, %s, is not writable',
						$data_store
					);
				}
				$real_path = realpath($data_store);
				if ($real_path === false) {
					throw new EnvironmentException(
						'The directory specified, %s, does not exist',
						$data_store
					);
				}
				$this->config['path'] = $real_path . DIRECTORY_SEPARATOR;
				break;

			case 'file':
				if (!is_string($data_store)) {
					throw new ProgrammerException(
						'The path for a file cache must be a string'
					);
				}
				$exists = file_exists($data_store);
				if (!$exists && !is_writable(dirname($data_store))) {
					throw new EnvironmentException(
						'The file specified, %s, does not exist and the directory it in inside of is not writable',
						$data_store
					);
				}
				if ($exists && !is_writable($data_store)) {
					throw new EnvironmentException(
						'The file specified, %s, is not writable',
						$data_store
					);
				}
				$this->config['path'] = $data_store;
				if ($exists) {
					$contents = file_get_contents($data_store);
					if ($contents === false) {
						throw new EnvironmentException(
							'The file specified, %s, could not be read',
							$data_store
						);
					}
					$data = unserialize($contents);
					if (!is_array($data)) {
						throw new ProgrammerException(
							'The file cache %s did not contain a valid cache array',
							$data_store
						);
					}
					$store = array();
					foreach ($data as $cache_key => $entry) {
						if (!is_string($cache_key) || !is_array($entry)) {
							continue;
						}
						$entry_value  = $entry['value'] ?? null;
						$entry_expire = $entry['expire'] ?? null;
						if (!is_string($entry_value) || !is_int($entry_expire)) {
							continue;
						}
						$store[$cache_key] = array(
							'value'  => $entry_value,
							'expire' => $entry_expire
						);
					}
					$this->data_store = $store;
				} else {
					$this->data_store = array();
				}
				$this->config['state'] = 'clean';
				break;

			case 'memcached':
				if (!$data_store instanceof Memcached) {
					throw new ProgrammerException(
						'The data store provided is not a valid %s object',
						'Memcached'
					);
				}
				$this->data_store = $data_store;
				break;

			case 'redis':
				if (!$data_store instanceof Redis) {
					throw new ProgrammerException(
						'The data store provided is not a valid %s object',
						'Redis'
					);
				}
				$this->data_store = $data_store;
				break;

			case 'apcu':
				if (!extension_loaded($type)) {
					throw new EnvironmentException(
						'The %s extension does not appear to be installed',
						$type
					);
				}
				break;

			default:
				throw new ProgrammerException(
					'The type specified, %s, is not a valid cache type. Must be one of: %s.',
					$type,
					join(', ', array('apcu', 'database', 'directory', 'file', 'memcached', 'redis'))
				);
		}

		$this->config['serializer']   = isset($config['serializer'])   ? $config['serializer']   : 'serialize';
		$this->config['unserializer'] = isset($config['unserializer']) ? $config['unserializer'] : 'unserialize';

		$this->type = $type;
	}


	/**
	 * Cleans up after the cache object
	 *
	 * @internal
	 *
	 * @return void
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 */
	public function __destruct()
	{
		// Only sometimes clean the cache of expired values
		if (rand(0, 99) == 50) {
			$this->clean();
		}

		$this->save();
	}


	/**
	 * Tries to set a value to the cache, but stops if a value already exists
	 *
	 * @param  string  $key    The key to store as, this should not exceed 250 characters
	 * @param  mixed   $value  The value to store, this will be serialized
	 * @param  integer $ttl    The number of seconds to keep the cache valid for, 0 for no limit
	 * @return boolean  If the key/value pair were added successfully
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 */
	public function add(string $key, mixed $value, int $ttl = 0): bool
	{
		$value = $this->serialize($value);

		switch ($this->type) {
			case 'apcu':
				return apcu_add($key, $value, $ttl);

			case 'file':
				$store = $this->getFileStore();
				if (isset($store[$key]) && (($store[$key]['expire'] && $store[$key]['expire'] >= time()) || !$store[$key]['expire'])) {
					return false;
				}
				$store[$key] = array(
					'value'  => $value,
					'expire' => (!$ttl) ? 0 : time() + $ttl
				);
				$this->data_store = $store;
				$this->config['state'] = 'dirty';
				return true;

			case 'database':
				$res = $this->queryDatabase(
					"SELECT %r FROM %r WHERE %r = %s",
					$this->config['key_column'],
					$this->config['table'],
					$this->config['key_column'],
					$key
				);
				if ($res->countReturnedRows()) {
					return false;
				}
				try {
					$value_placeholder = $this->config['value_data_type'] == 'blob' ? '%l' : '%s';
					$this->queryDatabase(
						"INSERT INTO %r (%r, %r, %r) VALUES (%s, " . $value_placeholder . ", %i)",
						$this->config['table'],
						$this->config['key_column'],
						$this->config['value_column'],
						$this->config['ttl_column'],
						$key,
						$value,
						(!$ttl) ? 0 : time() + $ttl
					);
					return true;
				} catch (SQLException $e) {
					return false;
				}

			case 'directory':
				$path = $this->getPath();
				if (file_exists($path . $key)) {
					return false;
				}
				$expiration_date = (!$ttl) ? 0 : time() + $ttl;
				file_put_contents(
					$path . $key,
					$expiration_date . "\n" . $value
				);
				return true;

			case 'memcached':
				$memcached = $this->getMemcached();
				if ($ttl > 2592000) {
					$ttl = time() + 2592000;
				}
				return $memcached->add($key, $value, $ttl);

			case 'redis':
				$redis = $this->getRedis();
				if (!$ttl) {
					return (bool) $redis->setnx($key, $value);
				}
				if ($redis->exists($key)) {
					return false;
				}
				$redis->setex($key, $ttl, $value);
				return true;
		}

		throw new ProgrammerException(
			'The cache type specified, %s, is not valid',
			$this->type
		);
	}


	/**
	 * Removes all cache entries that have expired
	 *
	 * @return void
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 */
	public function clean(): void
	{
		switch ($this->type) {
			case 'database':
				$this->queryDatabase(
					"DELETE FROM %r WHERE %r != 0 AND %r < %i",
					$this->config['table'],
					$this->config['ttl_column'],
					$this->config['ttl_column'],
					time()
				);
				break;

			case 'directory':
				$path = $this->getPath();
				$clear_before = time();
				$files = scandir($path);
				if ($files === false) {
					throw new EnvironmentException(
						'The directory specified, %s, could not be scanned',
						$path
					);
				}
				$files = array_diff($files, array('.', '..'));
				foreach ($files as $file) {
					if (!file_exists($path . $file)) {
						continue;
					}
					$handle = fopen($path . $file, 'r');
					if ($handle === false) {
						continue;
					}
					$expiration_date = fgets($handle);
					fclose($handle);
					if ($expiration_date === false) {
						continue;
					}
					if (trim($expiration_date) && (int) trim($expiration_date) < $clear_before) {
						unlink($path . $file);
					}
				}
				break;

			case 'file':
				$store = $this->getFileStore();
				$clear_before = time();
				foreach ($store as $key => $value) {
					if ($value['expire'] && $value['expire'] < $clear_before) {
						unset($store[$key]);
						$this->config['state'] = 'dirty';
					}
				}
				$this->data_store = $store;
				break;
		}
	}


	/**
	 * Clears the WHOLE cache of every key, use with caution!
	 *
	 * @return boolean  If the cache was successfully cleared
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 */
	public function clear(): bool
	{
		switch ($this->type) {
			case 'apcu':
				return apcu_clear_cache();

			case 'database':
				$this->queryDatabase(
					"DELETE FROM %r",
					$this->config['table']
				);
				return true;

			case 'directory':
				$path = $this->getPath();
				$files = scandir($path);
				if ($files === false) {
					throw new EnvironmentException(
						'The directory specified, %s, could not be scanned',
						$path
					);
				}
				$files = array_diff($files, array('.', '..'));
				$success = true;
				foreach ($files as $file) {
					$success = unlink($path . $file) && $success;
				}
				return $success;

			case 'file':
				$this->data_store = array();
				$this->config['state'] = 'dirty';
				return true;

			case 'memcached':
				return $this->getMemcached()->flush();

			case 'redis':
				return (bool) $this->getRedis()->flushDB();
		}

		throw new ProgrammerException(
			'The cache type specified, %s, is not valid',
			$this->type
		);
	}


	/**
	 * Deletes a value from the cache
	 *
	 * @param  string $key  The key to delete
	 * @return boolean  If the delete succeeded
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 */
	public function delete(string $key): bool
	{
		switch ($this->type) {
			case 'apcu':
				return apcu_delete($key);

			case 'database':
				return (bool) $this->queryDatabase(
					"DELETE FROM %r WHERE %r = %s",
					$this->config['table'],
					$this->config['key_column'],
					$key
				)->countAffectedRows();

			case 'directory':
				return unlink($this->getPath() . $key);

			case 'file':
				$store = $this->getFileStore();
				if (isset($store[$key])) {
					unset($store[$key]);
					$this->data_store = $store;
					$this->config['state'] = 'dirty';
				}
				return true;

			case 'memcached':
				return $this->getMemcached()->delete($key, 0);

			case 'redis':
				return (bool) $this->getRedis()->delete($key);
		}

		throw new ProgrammerException(
			'The cache type specified, %s, is not valid',
			$this->type
		);
	}


	/**
	 * Returns a value from the cache
	 *
	 * @template T
	 * @param  string $key      The key to return the value for
	 * @param  T      $default  The value to return if the key did not exist
	 * @return T      The cached value or the default value if no cached value was found
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 */
	public function get(string $key, mixed $default = null): mixed
	{
		switch ($this->type) {
			case 'apcu':
				$value = apcu_fetch($key);
				if ($value === false) {
                    return $default;
                }
				break;

			case 'database':
				$res = $this->queryDatabase(
					"SELECT %r FROM %r WHERE %r = %s AND (%r = 0 OR %r >= %i)",
					$this->config['value_column'],
					$this->config['table'],
					$this->config['key_column'],
					$key,
					$this->config['ttl_column'],
					$this->config['ttl_column'],
					time()
				);
				if (!$res->countReturnedRows()) {
                    return $default;
                }
				$value = $res->fetchScalar();
				break;

			case 'directory':
				$path = $this->getPath() . $key;
				if (!file_exists($path)) {
					return $default;
				}
				$handle = fopen($path, 'r');
				if ($handle === false) {
					return $default;
				}
				$expiration_date = fgets($handle);
				if ($expiration_date === false) {
					fclose($handle);
					return $default;
				}
				if ($expiration_date != 0 && $expiration_date < time()) {
					fclose($handle);
					return $default;
				}
				$value = '';
				while (!feof($handle)) {
					$chunk = fread($handle, 524288);
					if ($chunk === false) {
						break;
					}
					$value .= $chunk;
				}
				fclose($handle);
				break;

			case 'file':
				$store = $this->getFileStore();
				if (isset($store[$key])) {
					$expire = $store[$key]['expire'];
					if (!$expire || $expire >= time()) {
						$value = $store[$key]['value'];
					} elseif ($expire) {
						unset($store[$key]);
						$this->data_store = $store;
						$this->config['state'] = 'dirty';
					}
				}
				if (!isset($value)) {
					return $default;
				}
				break;

			case 'memcached':
				$value = $this->getMemcached()->get($key);
				if ($value === false) {
                    return $default;
                }
				break;

			case 'redis':
				$value = $this->getRedis()->get($key);
				if ($value === false) {
                    return $default;
                }
				break;
		}

		if (!isset($value) || !is_string($value)) {
			return $default;
		}

		return $this->unserialize($value);
	}


	/**
	 * Only valid for `file` caches, saves the file to disk
	 *
	 * @return void
	 */
	public function save(): void
	{
		if ($this->type != 'file' || $this->config['state'] == 'clean') {
			return;
		}

		$path = $this->getPath();
		file_put_contents($path, serialize($this->data_store));
		$this->config['state'] = 'clean';
	}


	/**
	 * Serializes a value before storing it in the cache
	 *
	 * @param mixed $value  The value to serialize
	 * @return string  The serialized value
	 */
	protected function serialize(mixed $value): string
	{
		if ($this->config['serializer'] == 'string') {
			if (is_scalar($value) || $value === null) {
				return (string) $value;
			}
			if (is_object($value) && method_exists($value, '__toString')) {
				return (string) $value;
			}
			throw new ProgrammerException(
				'The value for the cache could not be converted to a string'
			);
		}

		$serializer = $this->config['serializer'] ?? null;
		if (!is_callable($serializer)) {
			throw new ProgrammerException(
				'The serializer for the cache is not callable'
			);
		}
		$result = call_user_func($serializer, $value);
		if (!is_string($result)) {
			throw new ProgrammerException(
				'The serializer for the cache did not return a string'
			);
		}
		return $result;
	}


	/**
	 * Sets a value to the cache, overriding any previous value
	 *
	 * @param  string  $key    The key to store as, this should not exceed 250 characters
	 * @param  mixed   $value  The value to store, this will be serialized
	 * @param  integer $ttl    The number of seconds to keep the cache valid for, 0 for no limit
	 * @return boolean  If the value was successfully saved
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 */
	public function set(string $key, mixed $value, int $ttl = 0): bool
	{
		$value = $this->serialize($value);

		switch ($this->type) {
			case 'apcu':
				return apcu_store($key, $value, $ttl);

			case 'database':
				$res = $this->queryDatabase(
					"SELECT %r FROM %r WHERE %r = %s",
					$this->config['key_column'],
					$this->config['table'],
					$this->config['key_column'],
					$key
				);

				$expiration_date = (!$ttl) ? 0 : time() + $ttl;

				try {
					$value_placeholder = $this->config['value_data_type'] == 'blob' ? '%l' : '%s';
					if (!$res->countReturnedRows()) {
						$this->queryDatabase(
							"INSERT INTO %r (%r, %r, %r) VALUES (%s, " . $value_placeholder . ", %i)",
							$this->config['table'],
							$this->config['key_column'],
							$this->config['value_column'],
							$this->config['ttl_column'],
							$key,
							$value,
							$expiration_date
						);
					} else {
						$this->queryDatabase(
							"UPDATE %r SET %r = " . $value_placeholder . ", %r = %s WHERE %r = %s",
							$this->config['table'],
							$this->config['value_column'],
							$value,
							$this->config['ttl_column'],
							$expiration_date,
							$this->config['key_column'],
							$key
						);
					}
				} catch (SQLException $e) {
					return false;
				}
				return true;

			case 'directory':
				$path = $this->getPath();
				$expiration_date = (!$ttl) ? 0 : time() + $ttl;
				return (bool) file_put_contents(
					$path . $key,
					$expiration_date . "\n" . $value
				);

			case 'file':
				$store = $this->getFileStore();
				$store[$key] = array(
					'value'  => $value,
					'expire' => (!$ttl) ? 0 : time() + $ttl
				);
				$this->data_store = $store;
				$this->config['state'] = 'dirty';
				return true;

			case 'memcached':
				$memcached = $this->getMemcached();
				if ($ttl > 2592000) {
					$ttl = time() + 2592000;
				}
				return $memcached->set($key, $value, $ttl);

			case 'redis':
				$redis = $this->getRedis();
				if ($ttl) {
					return (bool) $redis->setex($key, $ttl, $value);
				}
				return (bool) $redis->set($key, $value);
		}

		throw new ProgrammerException(
			'The cache type specified, %s, is not valid',
			$this->type
		);
	}


	/**
	 * Unserializes a value before returning it
	 *
	 * @param string $value  The serialized value
	 * @return mixed  The PHP value
	 */
	protected function unserialize(mixed $value): mixed
	{
		if ($this->config['unserializer'] == 'string') {
			return $value;
		}

		$unserializer = $this->config['unserializer'] ?? null;
		if (!is_callable($unserializer)) {
			throw new ProgrammerException(
				'The unserializer for the cache is not callable'
			);
		}
		return call_user_func($unserializer, $value);
	}


	/**
	 * Returns the database data store, verifying it is a Database object
	 *
	 * @return Database  The database data store
	 */
	private function getDatabase(): Database
	{
		if (!$this->data_store instanceof Database) {
			throw new ProgrammerException(
				'The data store for the database cache is not a Database object'
			);
		}
		return $this->data_store;
	}


	/**
	 * Returns the memcached data store, verifying it is a Memcached object
	 *
	 * @return Memcached  The memcached data store
	 */
	private function getMemcached(): Memcached
	{
		if ($this->data_store instanceof Memcached) {
			return $this->data_store;
		}
		throw new ProgrammerException(
			'The data store for the memcached cache is not a Memcached object'
		);
	}


	/**
	 * Returns the redis data store, verifying it is a Redis object
	 *
	 * @return Redis  The redis data store
	 */
	private function getRedis(): Redis
	{
		if (!$this->data_store instanceof Redis) {
			throw new ProgrammerException(
				'The data store for the redis cache is not a Redis object'
			);
		}
		return $this->data_store;
	}


	/**
	 * Returns the file cache data store, verifying it is a valid cache array
	 *
	 * @return FileCacheStore  The file cache data store
	 */
	private function getFileStore(): array
	{
		if (!is_array($this->data_store)) {
			throw new ProgrammerException(
				'The data store for the file cache is not an array'
			);
		}
		return $this->data_store;
	}


	/**
	 * Returns the path configuration value for directory and file caches
	 *
	 * @return string  The configured path
	 */
	private function getPath(): string
	{
		$path = $this->config['path'] ?? null;
		if (!is_string($path)) {
			throw new ProgrammerException(
				'The path for the %s cache is not configured',
				$this->type
			);
		}
		return $path;
	}


	/**
	 * Runs a query against the database data store, verifying the result
	 *
	 * @param  Statement|string $statement  The SQL statement
	 * @param  mixed ...$params  The parameters for the statement
	 * @return Result  The result of the query
	 * @throws AuthorizationException
	 * @throws NotFoundException
	 * @throws NoRowsException
	 * @throws NoRemainingException
	 */
	private function queryDatabase(Statement|string $statement, mixed ...$params): Result
	{
		$result = $this->getDatabase()->query($statement, ...$params);
		if (!$result instanceof Result) {
			throw new ProgrammerException(
				'The database cache query did not return a single Result object'
			);
		}
		return $result;
	}
}



/**
 * Copyright (c) 2009-2012 Will Bond <will@flourishlib.com>
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
