<?php

declare(strict_types=1);

/**
 * Provides cryptography functionality, including hashing, symmetric-key encryption and public-key encryption
 *
 * @copyright  Copyright (c) 2007-2016 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 *
 * @version    1.0.0b16
 * @changes    1.0.0b16  Guarded `openssl_free_key()` calls with `is_resource()` for PHP 8.0+ compatibility where
 * OpenSSL keys are objects [wb, 2026-07-10]
 * @changes    1.0.0b15  Added support for bcrypt password hashing in PHP 5.5+ [wb, 2016-10-14]
 * @changes    1.0.0b14  Added the `base36`, `base56` and custom types to ::randomString() [wb, 2011-08-25]
 * @changes    1.0.0b13  Updated documentation about symmetric-key encryption to explicitly state block and key sizes,
 * added base64 type to ::randomString() [wb, 2010-11-06]
 * @changes    1.0.0b12  Fixed an inline comment that incorrectly references AES-256 [wb, 2010-11-04]
 * @changes    1.0.0b11  Updated class to use Core::startErrorCapture() instead of `error_reporting()` [wb, 2010-08-09]
 * @changes    1.0.0b10  Added a missing parameter to an ProgrammerException in ::randomString() [wb, 2010-07-29]
 * @changes    1.0.0b9   Added ::hashHMAC() [wb, 2010-04-20]
 * @changes    1.0.0b8   Fixed ::seedRandom() to pass a directory instead of a file to [http://php.net/disk_free_space
 * `disk_free_space()`] [wb, 2010-03-09]
 * @changes    1.0.0b7   SECURITY FIX: fixed issue with ::random() and ::randomString() not producing random output on
 * OSX, made ::seedRandom() more robust [wb, 2009-10-06]
 * @changes    1.0.0b6   Changed ::symmetricKeyEncrypt() to throw an ValidationException when the $secret_key is less
 * than 8 characters [wb, 2009-09-30]
 * @changes    1.0.0b5   Fixed a bug where some windows machines would throw an exception when generating random strings
 * or numbers [wb, 2009-06-09]
 * @changes    1.0.0b4   Updated for new Core API [wb, 2009-02-16]
 * @changes    1.0.0b3   Changed @ error suppression operator to `error_reporting()` calls [wb, 2009-01-26]
 * @changes    1.0.0b2   Backwards compatibility break - changed ::symmetricKeyEncrypt() to not encrypt the IV since we
 * are using HMAC on it [wb, 2009-01-26]
 * @changes    1.0.0b    The initial implementation [wb, 2007-11-27]
 */
class Cryptography
{
	/**
	 * Checks a password against a hash created with ::hashPassword()
	 *
	 * @param  string $password  The password to check
	 * @param  string $hash      The hash to check against
	 * @return boolean  If the password matches the hash
	 */
	public static function checkPasswordHash(string $password, string $hash): bool
	{
		// Hashes created before the class was renamed carry the old f-prefixed marker
		if (str_starts_with($hash, 'fCryptography::')) {
			$hash = 'Cryptography::' . substr($hash, strlen('fCryptography::'));
		}

		if (str_starts_with($hash, 'Cryptography::pwd3#')) {
			return password_verify($password, substr($hash, strlen('Cryptography::pwd3#')));
		} elseif (str_starts_with($hash, 'Cryptography::passwd_hashv2#')) {
			$salt = substr($hash, strlen('Cryptography::passwd_hashv2#'), 10);
			return self::hashWithSaltV2($password, $salt) === $hash;
		} elseif (str_starts_with($hash, 'Cryptography::password_hash#')) {
			$salt = substr($hash, strlen('Cryptography::password_hash#'), 10);
			return self::hashWithSalt($password, $salt) === $hash;
		}

		return false;
	}


	/**
	 * Create a private key resource based on a filename and password
	 *
	 * @throws ValidationException  When the private key is invalid
	 *
	 * @param  string $private_key_file  The path to a PEM-encoded private key
	 * @param  string $password          The password for the private key
	 * @return OpenSSLAsymmetricKey  The private key resource
	 */
	private static function createPrivateKeyResource(string $private_key_file, string $password): OpenSSLAsymmetricKey
	{
		if (!file_exists($private_key_file)) {
			throw new ProgrammerException(
				'The path to the PEM-encoded private key specified, %s, is not valid',
				$private_key_file
			);
		}
		if (!is_readable($private_key_file)) {
			throw new EnvironmentException(
				'The PEM-encoded private key specified, %s, is not readable',
				$private_key_file
			);
		}

		$private_key          = file_get_contents($private_key_file);
		if ($private_key === false) {
			throw new ProgrammerException(
				'The PEM-encoded private key specified, %s, could not be read',
				$private_key_file
			);
		}
		$private_key_resource = openssl_pkey_get_private($private_key, $password);

		if ($private_key_resource === false) {
			throw new ValidationException(
				'The private key file specified, %s, does not appear to be a valid private key or the password '
					. 'provided is incorrect',
				$private_key_file
			);
		}

		return $private_key_resource;
	}


	/**
	 * Create a public key resource based on a filename
	 *
	 * @param  string $public_key_file  The path to an X.509 public key certificate
	 * @return OpenSSLAsymmetricKey  The public key resource
	 */
	private static function createPublicKeyResource(string $public_key_file): OpenSSLAsymmetricKey
	{
		if (!file_exists($public_key_file)) {
			throw new ProgrammerException(
				'The path to the X.509 certificate specified, %s, is not valid',
				$public_key_file
			);
		}
		if (!is_readable($public_key_file)) {
			throw new EnvironmentException(
				'The X.509 certificate specified, %s, can not be read',
				$public_key_file
			);
		}

		$public_key = file_get_contents($public_key_file);
		if ($public_key === false) {
			throw new ProgrammerException(
				'The X.509 certificate specified, %s, could not be read',
				$public_key_file
			);
		}
		$public_key_resource = openssl_pkey_get_public($public_key);

		if ($public_key_resource === false) {
			throw new ProgrammerException(
				'The public key certificate specified, %s, does not appear to be a valid certificate',
				$public_key_file
			);
		}

		return $public_key_resource;
	}


	/**
	 * Generates an HMAC for the passed data
	 *
	 * @internal
	 *
	 * @param  string $algorithm  The hashing algorithm to use: `'md5'` or `'sha1'`
	 * @param  string $data       The data to create an HMAC for
	 * @param  string $key        The key to generate the HMAC with
	 * @return string  The HMAC
	 */
	public static function hashHMAC(string $algorithm, string $data, string $key): string
	{
		return hash_hmac($algorithm, $data, $key);
	}


	/**
	 * Hashes a password using a loop of sha1 hashes and a salt, making rainbow table attacks infeasible
	 *
	 * @param  string $password  The password to hash
	 * @return string  An 80 character string of the Flourish fingerprint, salt and hashed password
	 */
	public static function hashPassword(string $password): string
	{
		return self::hashWithBcrypt($password);
	}


	/**
	 * Performs a large iteration of hashing a string with a salt
	 *
	 * @param  string $source  The string to hash
	 * @param  string $salt    The salt for the hash
	 * @return string  An 80 character string of the Flourish fingerprint, salt and hashed password
	 */
	private static function hashWithSalt(string $source, string $salt): string
	{
		$sha1 = sha1($salt . $source);
		for ($i = 0; $i < 1000; $i++) {
			$sha1 = sha1($sha1 . (($i % 2 == 0) ? $source : $salt));
		}

		return 'Cryptography::password_hash#' . $salt . '#' . $sha1;
	}


	/**
	 * Performs a larger iteration of hashing a string with a salt. This is
	 * to allow for support of older platforms, but to better protect against
	 * the speed increases of modern password cracking rigs
	 *
	 * @param  string $source  The string to hash
	 * @param  string $salt    The salt for the hash
	 * @return string  An 80 character string of the Flourish fingerprint, salt and hashed password
	 */
	private static function hashWithSaltV2(string $source, string $salt): string
	{
		$sha1 = sha1($salt . $source);
		for ($i = 0; $i < 100000; $i++) {
			$sha1 = sha1($sha1 . (($i % 2 == 0) ? $source : $salt));
		}

		return 'Cryptography::passwd_hashv2#' . $salt . '#' . $sha1;
	}


	/**
	 * Hashes a password using bcrypt
	 *
	 * @param  string $source  The string to hash
	 * @return string  An 80 character string of the Flourish fingerprint and crypt()-style hash
	 */
	private static function hashWithBcrypt(string $source): string
	{
		return 'Cryptography::pwd3#' . password_hash($source, PASSWORD_BCRYPT);
	}


	/**
	 * Decrypts ciphertext encrypted using public-key encryption via ::publicKeyEncrypt()
	 *
	 * A public key (X.509 certificate) is required for encryption and a
	 * private key (PEM) is required for decryption.
	 *
	 * @throws ValidationException  When the ciphertext appears to be corrupted
	 *
	 * @param  string $ciphertext        The content to be decrypted
	 * @param  string $private_key_file  The path to a PEM-encoded private key
	 * @param  string $password          The password for the private key
	 * @return string  The decrypted plaintext
	 */
	public static function publicKeyDecrypt(string $ciphertext, string $private_key_file, string $password): string
	{
		self::verifyOpenSSLEnvironment();

		$private_key_resource = self::createPrivateKeyResource($private_key_file, $password);

		$elements = explode('#', $ciphertext);

		// Ciphertexts produced before the class was renamed carry the old f-prefixed marker
		if (str_starts_with($elements[0], 'fCryptography::')) {
			$elements[0] = substr($elements[0], 1);
		}

		// We need to make sure this ciphertext came from here, otherwise we are gonna have issues decrypting it
		if ($elements[0] != 'Cryptography::public' || (count($elements) != 4 && count($elements) != 5)) {
			throw new ProgrammerException(
				'The ciphertext provided does not appear to have been encrypted using %s',
				__CLASS__ . '::publicKeyEncrypt()'
			);
		}

		if (count($elements) == 5) {
			// Current format: marker#encrypted_key#iv#ciphertext#hmac using AES-256-CBC
			$encrypted_key = base64_decode($elements[1]);
			$iv            = base64_decode($elements[2]);
			$ciphertext    = base64_decode($elements[3]);
			$provided_hmac = $elements[4];
			$cipher        = 'aes-256-cbc';
			$hmac_input    = $encrypted_key . $iv . $ciphertext;
		} else {
			// Legacy format was sealed with RC4 by older PHP versions, which is no
			// longer available in OpenSSL 3 - re-encryption is required
			if (!in_array('RC4', openssl_get_cipher_methods(), true)) {
				throw new EnvironmentException(
					'The ciphertext provided was encrypted using the legacy RC4-based %s implementation, which is not '
						. 'supported by the current OpenSSL version. Please re-encrypt the data using the current '
						. 'implementation.',
					__CLASS__ . '::publicKeyEncrypt()'
				);
			}
			$encrypted_key = base64_decode($elements[1]);
			$ciphertext    = base64_decode($elements[2]);
			$provided_hmac = $elements[3];
			$iv            = null;
			$cipher        = 'RC4';
			$hmac_input    = $encrypted_key . $ciphertext;
		}

		$plaintext = '';
		$result = openssl_open($ciphertext, $plaintext, $encrypted_key, $private_key_resource, $cipher, $iv);

		if ($result === false) {
			throw new EnvironmentException(
				'There was an unknown error decrypting the ciphertext provided'
			);
		}

		if (!is_string($plaintext)) {
			throw new EnvironmentException(
				'There was an unknown error decrypting the ciphertext provided'
			);
		}

		$hmac = self::hashHMAC('sha1', $hmac_input, $plaintext);

		// By verifying the HMAC we ensure the integrity of the data
		if ($hmac != $provided_hmac) {
			throw new ValidationException(
				'The ciphertext provided appears to have been tampered with or corrupted'
			);
		}

		return $plaintext;
	}


	/**
	 * Encrypts the passed data using public key encryption via OpenSSL
	 *
	 * A public key (X.509 certificate) is required for encryption and a
	 * private key (PEM) is required for decryption.
	 *
	 * @param  string $plaintext        The content to be encrypted
	 * @param  string $public_key_file  The path to an X.509 public key certificate
	 * @return string  A base-64 encoded result containing a Flourish fingerprint and suitable for decryption using
	 * ::publicKeyDecrypt()
	 */
	public static function publicKeyEncrypt(string $plaintext, string $public_key_file): string
	{
		self::verifyOpenSSLEnvironment();

		$public_key_resource = self::createPublicKeyResource($public_key_file);

		$ciphertext     = '';
		$encrypted_keys = array();
		$iv             = '';
		$result = openssl_seal(
			$plaintext,
			$ciphertext,
			$encrypted_keys,
			array($public_key_resource),
			'aes-256-cbc',
			$iv
		);

		if ($result === false) {
			throw new EnvironmentException(
				'There was an unknown error encrypting the plaintext provided'
			);
		}

		if (!is_array($encrypted_keys)) {
			throw new EnvironmentException(
				'There was an unknown error encrypting the plaintext provided'
			);
		}

		$encrypted_key = $encrypted_keys[0] ?? null;
		if (!is_string($encrypted_key) || !is_string($iv) || !is_string($ciphertext)) {
			throw new EnvironmentException(
				'There was an unknown error encrypting the plaintext provided'
			);
		}

		$hmac = self::hashHMAC('sha1', $encrypted_key . $iv . $ciphertext, $plaintext);

		return 'Cryptography::public#' . base64_encode($encrypted_key) . '#' . base64_encode($iv) . '#' . base64_encode(
			$ciphertext
		) . '#' . $hmac;
	}


	/**
	 * Creates a signature for plaintext to allow verification of the creator
	 *
	 * A private key (PEM) is required for signing and a public key
	 * (X.509 certificate) is required for verification.
	 *
	 * @throws ValidationException  When the private key is invalid
	 *
	 * @param  string $plaintext         The content to be signed
	 * @param  string $private_key_file  The path to a PEM-encoded private key
	 * @param  string $password          The password for the private key
	 * @return string  The base64-encoded signature suitable for verification using ::publicKeyVerify()
	 */
	public static function publicKeySign(string $plaintext, string $private_key_file, string $password): string
	{
		self::verifyOpenSSLEnvironment();

		$private_key_resource = self::createPrivateKeyResource($private_key_file, $password);

		$result = openssl_sign($plaintext, $signature, $private_key_resource);

		if (!$result) {
			throw new EnvironmentException(
				'There was an unknown error signing the plaintext'
			);
		}

		if (!is_string($signature)) {
			throw new EnvironmentException(
				'There was an unknown error signing the plaintext'
			);
		}

		return base64_encode($signature);
	}


	/**
	 * Checks a signature for plaintext to verify the creator - works with ::publicKeySign()
	 *
	 * A private key (PEM) is required for signing and a public key
	 * (X.509 certificate) is required for verification.
	 *
	 * @param  string $plaintext         The content to check
	 * @param  string $signature         The base64-encoded signature for the plaintext
	 * @param  string $public_key_file   The path to an X.509 public key certificate
	 * @return boolean  If the public key file is the public key of the user who signed the plaintext
	 */
	public static function publicKeyVerify(string $plaintext, string $signature, string $public_key_file): bool
	{
		self::verifyOpenSSLEnvironment();

		$public_key_resource = self::createPublicKeyResource($public_key_file);

		$result = openssl_verify($plaintext, base64_decode($signature), $public_key_resource);

		if ($result === -1) {
			throw new EnvironmentException(
				'There was an unknown error verifying the plaintext and signature against the public key specified'
			);
		}

		return ($result === 1) ? true : false;
	}


	/**
	 * Generates a random number using [http://php.net/mt_rand mt_rand()] after ensuring a good PRNG seed
	 *
	 * @param  integer $min  The minimum number to return
	 * @param  integer $max  The maximum number to return
	 * @return integer  The psuedo-random number
	 */
	public static function random(?int $min = null, ?int $max = null): int
	{
		self::seedRandom();
		if ($min !== null || $max !== null) {
			return mt_rand($min ?? 0, $max ?? mt_getrandmax());
		}
		return mt_rand();
	}


	/**
	 * Returns a random string of the type and length specified
	 *
	 * @param  integer $length  The length of string to return
	 * @param  string  $type    The type of string to return: `'base64'`, `'base56'`, `'base36'`, `'alphanumeric'`,
	 * `'alpha'`, `'numeric'`, or `'hexadecimal'`, if a different string is provided, it will be used for the alphabet
	 * @return string  A random string of the type and length specified
	 */
	public static function randomString(int $length, string $type = 'alphanumeric'): string
	{
		if ($length < 1) {
			throw new ProgrammerException(
				'The length specified, %1$s, is less than the minimum of %2$s',
				$length,
				1
			);
		}

		switch ($type) {
			case 'base64':
				$alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789+/';
				break;

			case 'alphanumeric':
				$alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
				break;

			case 'base56':
				$alphabet = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
				break;

			case 'alpha':
				$alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
				break;

			case 'base36':
				$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
				break;

			case 'hexadecimal':
				$alphabet = 'abcdef0123456789';
				break;

			case 'numeric':
				$alphabet = '0123456789';
				break;

			default:
				$alphabet = $type;
		}

		$alphabet_length = strlen($alphabet);
		$output = '';

		for ($i = 0; $i < $length; $i++) {
			$output .= $alphabet[self::random(0, $alphabet_length - 1)];
		}

		return $output;
	}


	/**
	 * Makes sure that the PRNG has been seeded with a fairly secure value
	 *
	 * @return void
	 */
	private static function seedRandom(): void
	{
		static $seeded = false;

		if ($seeded) {
			return;
		}

		Core::startErrorCapture(E_WARNING);

		$bytes = null;

		// On linux/unix/solaris we should be able to use /dev/urandom
		if (!Core::checkOS('windows') && $handle = fopen('/dev/urandom', 'rb')) {
			$bytes = fread($handle, 4);
			fclose($handle);

		// On windows the CAPICOM COM object provides a random source, however
		// it was retired by Microsoft and the API no longer functions
		} elseif (Core::checkOS('windows')) {
			$bytes = null;
		}

		// If we could not use the OS random number generators we get some of the most unique info we can
		if (!$bytes) {
			$stats  = stat(__FILE__);
			if ($stats === false) {
				$stats = array();
			}
			$string = microtime(true) . uniqid('', true) . join('', $stats) . disk_free_space(dirname(__FILE__));
			$bytes  = substr(pack('H*', md5($string)), 0, 4);
		}

		Core::stopErrorCapture();

		$seed = (int) base_convert(bin2hex($bytes), 16, 10) - 2147483647;

		mt_srand($seed);

		$seeded = true;
	}


	/**
	 * Decrypts ciphertext encrypted using symmetric-key encryption via ::symmetricKeyEncrypt()
	 *
	 * Since this is symmetric-key cryptography, the same key is used for
	 * encryption and decryption.
	 *
	 * @throws ValidationException  When the ciphertext appears to be corrupted
	 *
	 * @param  string $ciphertext  The content to be decrypted
	 * @param  string $secret_key  The secret key to use for decryption
	 * @return string  The decrypted plaintext
	 */
	public static function symmetricKeyDecrypt(string $ciphertext, string $secret_key): string
	{
		self::verifyOpenSSLEnvironment();

		$elements = explode('#', $ciphertext);

		// Ciphertexts produced before the class was renamed carry the old f-prefixed marker
		if (str_starts_with($elements[0], 'fCryptography::')) {
			$elements[0] = substr($elements[0], 1);
		}

		// Legacy ciphertexts were produced by the mcrypt-based implementation, which
		// cannot be decrypted on PHP 8.4+ since the mcrypt extension no longer exists
		if ($elements[0] === 'Cryptography::symmetric') {
			throw new EnvironmentException(
				'The ciphertext provided was encrypted using the legacy mcrypt-based %s implementation, which is not '
					. 'supported in PHP 8.4+. Please re-encrypt the data using the current implementation.',
				'Cryptography::symmetricKeyEncrypt()'
			);
		}

		// We need to make sure this ciphertext came from here, otherwise we are gonna have issues decrypting it
		if (count($elements) != 4 || $elements[0] != 'Cryptography::symmetric2') {
			throw new ProgrammerException(
				'The ciphertext provided does not appear to have been encrypted using %s',
				__CLASS__ . '::symmetricKeyEncrypt()'
			);
		}

		$iv            = base64_decode($elements[1]);
		$ciphertext    = base64_decode($elements[2]);
		$provided_hmac = $elements[3];

		$hmac = self::hashHMAC('sha1', $iv . '#' . $ciphertext, $secret_key);

		// By verifying the HMAC we ensure the integrity of the data
		if ($hmac != $provided_hmac) {
			throw new ValidationException(
				'The ciphertext provided appears to have been tampered with or corrupted'
			);
		}

		$key       = hash('sha256', $secret_key, true);
		$plaintext = openssl_decrypt($ciphertext, 'aes-256-cfb', $key, OPENSSL_RAW_DATA, $iv);
		if ($plaintext === false) {
			throw new ValidationException(
				'The ciphertext provided appears to have been tampered with or corrupted'
			);
		}

		return $plaintext;
	}


	/**
	 * Encrypts the passed data using symmetric-key encryption
	 *
	 * Since this is symmetric-key cryptography, the same key is used for
	 * encryption and decryption.
	 *
	 * @throws ValidationException  When the $secret_key is less than 8 characters long
	 *
	 * @param  string $plaintext   The content to be encrypted
	 * @param  string $secret_key  The secret key to use for encryption - must be at least 8 characters
	 * @return string  An encrypted and base-64 encoded result containing a Flourish fingerprint and suitable for
	 * decryption using ::symmetricKeyDecrypt()
	 */
	public static function symmetricKeyEncrypt(string $plaintext, string $secret_key): string
	{
		if (strlen($secret_key) < 8) {
			throw new ValidationException(
				'The secret key specified does not meet the minimum requirement of being at least %s characters long',
				8
			);
		}

		self::verifyOpenSSLEnvironment();

		// AES-256 in cipher feedback mode is chosen because no extra padding is
		// added, ensuring we always get the exact same plaintext out of the
		// decrypt method. The key is derived from the secret key and a random
		// IV is generated for every encryption
		$key = hash('sha256', $secret_key, true);
		$iv  = random_bytes(16);

		$ciphertext = openssl_encrypt($plaintext, 'aes-256-cfb', $key, OPENSSL_RAW_DATA, $iv);
		if ($ciphertext === false) {
			throw new EnvironmentException(
				'The data could not be encrypted using the PHP %s extension',
				'openssl'
			);
		}

		// Here we are generating the HMAC for the encrypted data to ensure data integrity
		$hmac = self::hashHMAC('sha1', $iv . '#' . $ciphertext, $secret_key);

		// All of the data is then encoded using base64 to prevent issues with character sets
		$encoded_iv         = base64_encode($iv);
		$encoded_ciphertext = base64_encode($ciphertext);

		// Indicate in the resulting encrypted data what the encryption tool was
		return 'Cryptography::symmetric2#' . $encoded_iv . '#' . $encoded_ciphertext . '#' . $hmac;
	}


	/**
	 * Makes sure the required PHP extensions and library versions are all correct
	 *
	 * @return void
	 */
	private static function verifyOpenSSLEnvironment(): void
	{
		if (!extension_loaded('openssl')) {
			throw new EnvironmentException(
				'The PHP %s extension is required, however is does not appear to be loaded',
				'openssl'
			);
		}
	}


	/**
	 * Forces use as a static class
	 *
	 * @return void
	 */
	private function __construct()
	{
	}
}



/**
 * Copyright (c) 2007-2016 Will Bond <will@flourishlib.com>
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
