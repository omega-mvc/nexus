<?php

declare(strict_types=1);

/**
 * Creates a connection to an SMTP server to be used by Email
 *
 * @copyright  Copyright (c) 2010-2011 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 * @link       http://flourishlib.com/SMTP
 *
 * @version    1.0.0b12
 * @changes    1.0.0b11  Enhanced the error checking for ::write() [wb, 2011-06-03]
 * @changes    1.0.0b12  Added ::hackLineEnding() to support SMTP servers that require `\n` line endings instead of `\r\n` [wb, 2019-06-29]
 * @changes    1.0.0b10  Added code to work around PHP bug #42682 (http://bugs.php.net/bug.php?id=42682) where `stream_select()` doesn't work on 64bit machines from PHP 5.2.0 to 5.2.5, improved timeouts while reading data [wb, 2011-01-10]
 * @changes    1.0.0b9   Fixed a bug where lines starting with `.` and containing other content would have the `.` stripped [wb, 2010-09-11]
 * @changes    1.0.0b8   Updated the class to use Email::getFQDN() [wb, 2010-09-07]
 * @changes    1.0.0b7   Updated class to use new Core::startErrorCapture() functionality [wb, 2010-08-09]
 * @changes    1.0.0b6   Updated the class to use new Core functionality [wb, 2010-07-05]
 * @changes    1.0.0b5   Hacked around a bug in PHP 5.3 on Windows [wb, 2010-06-22]
 * @changes    1.0.0b4   Updated the class to not connect and authenticate until a message is sent, moved message id generation in Email [wb, 2010-05-05]
 * @changes    1.0.0b3   Fixed a bug with connecting to servers that send an initial response of `220-` and instead of `220 ` [wb, 2010-04-26]
 * @changes    1.0.0b2   Fixed a bug where `STARTTLS` would not be triggered if it was last in the SMTP server's list of supported extensions [wb, 2010-04-20]
 * @changes    1.0.0b    The initial implementation [wb, 2010-04-20]
 */
class SMTP
{
	/**
	 * The socket connection to the SMTP server
	 *
	 * @var resource|null
	 */
	private mixed $connection = null;

	/**
	 * If debugging has been enabled
	 *
	 * @var boolean
	 */
	private bool $debug = false;

	/**
	 * The hostname or IP of the SMTP server
	 *
	 * @var string
	 */
	private string $host;

	/**
	 * The line ending to use
	 *
	 * @var string
	 */
	private string $line_ending = "\r\n";

	/**
	 * The maximum size message the SMTP server supports
	 *
	 * @var integer
	 */
	private ?int $max_size = null;

	/**
	 * The password to authenticate with
	 *
	 * @var string
	 */
	private ?string $password = null;

	/**
	 * If the server supports pipelining
	 *
	 * @var boolean
	 */
	private ?bool $pipelining = null;

	/**
	 * The port the SMTP server is on
	 *
	 * @var integer
	 */
	private int $port;

	/**
	 * If the connection to the SMTP server is secure
	 *
	 * @var boolean
	 */
	private bool $secure;

	/**
	 * The timeout for the connection
	 *
	 * @var int|string
	 */
	private int|string $timeout;

	/**
	 * The username to authenticate with
	 *
	 * @var string
	 */
	private ?string $username = null;


	/**
	 * Configures the SMTP connection
	 *
	 * The SMTP connection is only made once authentication is attempted or
	 * an email is sent.
	 *
	 * Please note that this class will upgrade the connection to TLS via the
	 * SMTP `STARTTLS` command if possible, even if a secure connection was not
	 * requested. This helps to keep authentication information secure.
	 *
	 * @param string  $host     The hostname or IP address to connect to
	 * @param integer $port     The port number to use
	 * @param boolean $secure   If the connection should be secure - if `STARTTLS` is available, the connection will be upgraded even if this is `FALSE`
	 * @param integer $timeout  The timeout for the connection - defaults to the `default_socket_timeout` ini setting
	 * @return SMTP
	 */
	public function __construct(mixed $host, mixed $port = null, mixed $secure = false, mixed $timeout = null)
	{
		if ($timeout === null) {
			$timeout = ini_get('default_socket_timeout');
		}
		if ($port === null) {
			$port = !$secure ? 25 : 465;
		}

		if ($secure && !extension_loaded('openssl')) {
			throw new EnvironmentException(
				'A secure connection was requested, but the %s extension is not installed',
				'openssl'
			);
		}

		$this->host    = $host;
		$this->port    = $port;
		$this->secure  = $secure;
		$this->timeout = $timeout;
	}


	/**
	 * Closes the connection to the SMTP server
	 *
	 * @return void
	 */
	public function __destruct()
	{
		$this->close();
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
	 * Authenticates with the SMTP server
	 *
	 * This method supports the digest-md5, cram-md5, login and plain
	 * SMTP authentication methods. This method will try to use the more secure
	 * digest-md5 and cram-md5 methods first since they do not send information
	 * in the clear.
	 *
	 * @throws ValidationException  When the `$username` and `$password` are not accepted
	 *
	 * @param string $username  The username
	 * @param string $password  The password
	 * @return void
	 */
	public function authenticate(string $username, string $password): void
	{
		$this->username = $username;
		$this->password = $password;
	}


	/**
	 * Closes the connection to the SMTP server
	 *
	 * @return void
	 */
	public function close(): void
	{
		if (!$this->connection) {
			return;
		}

		$this->write('QUIT', 1);
		fclose($this->connection);
		$this->connection = null;
	}


	/**
	 * Initiates the connection to the server
	 *
	 * @return void
	 */
	private function connect(): void
	{
		if ($this->connection) {
			return;
		}

		$fqdn = Email::getFQDN();

		Core::startErrorCapture(E_WARNING);

		$host       = ($this->secure) ? 'tls://' . $this->host : $this->host;
		$connection = fsockopen($host, $this->port, $error_int, $error_string, (float) $this->timeout);

		foreach (Core::stopErrorCapture('#ssl#i') as $error) {
			throw new ConnectivityException('There was an error connecting to the server. A secure connection was requested, but was not available. Try a non-secure connection instead.');
		}

		if ($connection === false) {
			throw new ConnectivityException('There was an error connecting to the server');
		}
		$this->connection = $connection;

		stream_set_timeout($this->connection, (int) $this->timeout);
		$response = $this->read('#^220 #');
		if (!$this->find($response, '#^220[ -]#')) {
			throw new ConnectivityException(
				'Unknown SMTP welcome message, %1$s, from server %2$s on port %3$s',
				join($this->line_ending, $response),
				$this->host,
				$this->port
			);
		}

		// Try sending the ESMTP EHLO command, but fall back to normal SMTP HELO
		$response = $this->write('EHLO ' . $fqdn, '#^250 #m');
		if ($this->find($response, '#^500#')) {
			$response = $this->write('HELO ' . $fqdn, 1);
		}

		// If STARTTLS is available, use it
		if (!$this->secure && extension_loaded('openssl') && $this->find($response, '#^250[ -]STARTTLS#')) {
			$response    = $this->write('STARTTLS', '#^220 #');
			$affirmative = $this->find($response, '#^220[ -]#');
			if ($affirmative) {
				do {
					if (isset($res)) {
						usleep(100000);
					}
					$res = stream_socket_enable_crypto($this->connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
				} while ($res === 0);
			}
			if (!$affirmative || $res === false) {
				throw new ConnectivityException('Error establishing secure connection');
			}
			$response = $this->write('EHLO ' . $fqdn, '#^250 #m');
		}

		$this->max_size = 0;
		if ($match = $this->find($response, '#^250[ -]SIZE\s+(\d+)$#')) {
			$this->max_size = (int) $match[0][1];
		}

		$this->pipelining = (bool) $this->find($response, '#^250[ -]PIPELINING$#');

		$auth_methods = array();
		if ($match = $this->find($response, '#^250[ -]AUTH[ =](.*)$#')) {
			$auth_methods = array_map('strtoupper', explode(' ', $match[0][1]));
		}

		if (!$auth_methods || !$this->username) {
			return;
		}

		if ($this->password === null) {
			throw new ProgrammerException('An SMTP password must be set in order to authenticate');
		}

		if (in_array('DIGEST-MD5', $auth_methods)) {
			$response = $this->write('AUTH DIGEST-MD5', 1);
			$this->handleErrors($response);

			$match     = $this->find($response, '#^334 (.*)$#');
			$challenge = base64_decode($match[0][1]);

			preg_match_all('#(?<=,|^)(\w+)=("[^"]+"|[^,]+)(?=,|$)#', $challenge, $matches, PREG_SET_ORDER);
			$request_params = array();
			foreach ($matches as $_match) {
				$request_params[$_match[1]] = ($_match[2][0] == '"') ? substr($_match[2], 1, -1) : $_match[2];
			}

			$missing_qop_auth = !isset($request_params['qop']) || !in_array('auth', explode(',', $request_params['qop']));
			$missing_nonce    = empty($request_params['nonce']);
			if ($missing_qop_auth || $missing_nonce) {
				throw new UnexpectedException(
					'The SMTP server %1$s on port %2$s claims to support DIGEST-MD5, but does not seem to provide auth functionality',
					$this->host,
					$this->port
				);
			}
			if (!isset($request_params['realm'])) {
				$request_params['realm'] = '';
			}

			// Algorithm from http://www.ietf.org/rfc/rfc2831.txt
			$realm      = $request_params['realm'];
			$nonce      = $request_params['nonce'];
			$cnonce     = Cryptography::randomString(32, 'hexadecimal');
			$nc         = '00000001';
			$digest_uri = 'smtp/' . $this->host;

			$a1 = md5($this->username . ':' . $realm . ':' . $this->password, true) . ':' . $nonce . ':' . $cnonce;
			$a2 = 'AUTHENTICATE:' . $digest_uri;
			$response = md5(md5($a1) . ':' . $nonce . ':' . $nc . ':' . $cnonce . ':auth:' . md5($a2));

			$response_params = array(
				'charset=utf-8',
				'username="' . $this->username . '"',
				'realm="' . $realm . '"',
				'nonce="' . $nonce . '"',
				'nc=' . $nc,
				'cnonce="' . $cnonce . '"',
				'digest-uri="' . $digest_uri . '"',
				'response=' . $response,
				'qop=auth'
			);

			$response = $this->write(base64_encode(join(',', $response_params)), 2);
		} elseif (in_array('CRAM-MD5', $auth_methods)) {
			$response = $this->write('AUTH CRAM-MD5', 1);
			$match     = $this->find($response, '#^334 (.*)$#');
			$challenge = base64_decode($match[0][1]);
			$response  = $this->write(base64_encode($this->username . ' ' . Cryptography::hashHMAC('md5', $challenge, $this->password)), 1);
		} elseif (in_array('LOGIN', $auth_methods)) {
			$response = $this->write('AUTH LOGIN', 1);
			$this->write(base64_encode($this->username), 1);
			$response = $this->write(base64_encode($this->password), 1);
		} elseif (in_array('PLAIN', $auth_methods)) {
			$response = $this->write('AUTH PLAIN ' . base64_encode($this->username . "\0" . $this->username . "\0" . $this->password), 1);
		}

		if ($this->find($response, '#^535[ -]#')) {
			throw new ValidationException(
				'The username and password provided were not accepted for the SMTP server %1$s on port %2$s',
				$this->host,
				$this->port
			);
		}
		if (!array_filter($response)) {
			throw new ConnectivityException('No response was received for the authorization request');
		}
	}


	/**
	 * Sets if debug messages should be shown
	 *
	 * @param  boolean $flag  If debugging messages should be shown
	 * @return void
	 */
	public function enableDebugging(bool $flag): void
	{
		$this->debug = (bool) $flag;
	}


	/**
	 * Searches the response array for the the regex and returns any matches
	 *
	 * @param array<int, string> $response  The lines of data to search through
	 * @param string $regex     The regex to search with
	 * @return array<int, array<int|string, string>>  The regex matches
	 */
	private function find(array $response, string $regex): array
	{
		$matches = array();
		foreach ($response as $line) {
			if (preg_match($regex, $line, $match)) {
				$matches[] = $match;
			}
		}
		return $matches;
	}


	/**
	 * Changes the line endings to `\n` from `\r\n` for crappy SMTP servers
	 * such as Sendgrid.
	 *
	 * @return void
	 */
	public function hackLineEnding(): void
	{
		$this->line_ending = "\n";
	}


	/**
	 * Searches the response array for SMTP error codes
	 *
	 * @param array<int, string> $response  The response array to search through
	 * @return void
	 */
	private function handleErrors(array $response): void
	{
		$codes = array(
			450, 451, 452, 500, 501, 502, 503, 504, 521, 530, 550, 551, 552, 553
		);

		$regex  = '#^(' . join('|', $codes) . ')#';
		$errors = array();
		foreach ($response as $line) {
			if (preg_match($regex, $line)) {
				$errors[] = $line;
			}
		}
		if ($errors) {
			throw new UnexpectedException(
				"The following unexpected SMTP errors occurred for the server %1\$s on port %2\$s:\n%3\$s",
				$this->host,
				$this->port,
				join("\n", $errors)
			);
		}
	}


	/**
	 * Reads lines from the SMTP server
	 *
	 * @param  integer|string|null $expect  The expected number of lines of response or a regex of the last line
	 * @return array<int, string>  The lines of response from the server
	 */
	private function read(mixed $expect): array
	{
		$response   = array();
		$connection = $this->connection;
		if (!is_resource($connection)) {
			throw new ProgrammerException('The connection to the SMTP server has already been closed');
		}
		if ($result = $this->select((int) $this->timeout, 0)) {
			while (!feof($connection)) {
				$line = fgets($connection);
				if ($line === false) {
					break;
				}
				$line       = substr($line, 0, -2);
				$response[] = $line;
				if ($expect !== null) {
					$result = null;
					$matched_number = is_int($expect) && sizeof($response) == $expect;
					$matched_regex  = is_string($expect) && preg_match($expect, $response[sizeof($response) - 1]);
					if ($matched_number || $matched_regex) {
						break;
					}
				} elseif (!($result = $this->select(0, 200000))) {
					break;
				}
			}
		}
		if (Core::getDebug($this->debug)) {
			Core::debug("Received:\n" . join($this->line_ending, $response), $this->debug);
		}
		$this->handleErrors($response);
		return $response;
	}


	/**
	 * Performs a "fixed" stream_select() for the connection
	 *
	 * @param integer $timeout   The number of seconds in the timeout
	 * @param integer $utimeout  The number of microseconds in the timeout
	 * @return int|false  The number of streams ready to be read from, or FALSE on error
	 */
	private function select(int $timeout, int $utimeout): int|false
	{
		$connection = $this->connection;
		if (!is_resource($connection)) {
			throw new ProgrammerException('The connection to the SMTP server has already been closed');
		}
		$read     = array($connection);
		$write    = null;
		$except   = null;

		// Fixes an issue with stream_select throwing a warning on Windows
		if (Core::checkOS('windows')) {
			$select = @stream_select($read, $write, $except, $timeout, $utimeout);
		} else {
			$select = stream_select($read, $write, $except, $timeout, $utimeout);
		}

		return $select;
	}


	/**
	 * Sends a message via the SMTP server
	 *
	 * @internal
	 *
	 * @throws ValidationException  When the message is too large for the server
	 *
	 * @param string $from     The email address being sent from - this will be used as the `Return-Path` header
	 * @param array<int, string> $to       All of the To, Cc and Bcc email addresses to send the message to - this does not affect the message headers in any way
	 * @param string $headers  The message headers - the Bcc header will be removed if present
	 * @param string $body     The mail body
	 * @return void
	 */
	public function send(string $from, array $to, string $headers, string $body): void
	{
		$this->connect();

		// Lines starting with . need to start with two .s because the leading
		// . will be stripped
		$body = preg_replace('#^\.#m', '..', $body);

		// Removed the Bcc header incase the SMTP server doesn't
		$headers = preg_replace('#^Bcc:(.*?)\r\n([^ ])#mi', '\2', $headers);

		// Add the Date header
		$headers = "Date: " . date('D, j M Y H:i:s O') . "\r\n" . $headers;

		$data = $headers . "\r\n\r\n" . $body;

		if ($this->line_ending == "\n") {
			$data = str_replace("\r\n", "\n", $data);
		}

		if ($this->max_size && strlen($data) > $this->max_size) {
			throw new ValidationException(
				'The email provided is %1$s, which is larger than the maximum size of %2$s that the server supports',
				Filesystem::formatFilesize(strlen($data)),
				Filesystem::formatFilesize($this->max_size)
			);
		}

		$mail_from = "MAIL FROM:<" . $from . ">";

		if ($this->pipelining) {
			$expect = 2;
			$rcpt_to = '';
			foreach ($to as $email) {
				$rcpt_to .= "RCPT TO:<" . $email . ">" . $this->line_ending;
				$expect++;
			}
			$rcpt_to = trim($rcpt_to);

			$this->write($mail_from . $this->line_ending . $rcpt_to . $this->line_ending . "DATA" . $this->line_ending, $expect);
		} else {
			$this->write($mail_from, 1);
			foreach ($to as $email) {
				$this->write("RCPT TO:<" . $email . ">", 1);
			}
			$this->write('DATA', 1);
		}

		$this->write($data . $this->line_ending . "." . $this->line_ending, 1);
		$this->write('RSET', 1);
	}


	/**
	 * Sends raw text/commands to the SMTP server
	 *
	 * @param  string         $data    The data or commands to send
	 * @param  integer|string $expect  The expected number of lines of response or a regex of the last line
	 * @return array<int, string>  The response from the server
	 */
	private function write(string $data, mixed $expect): array
	{
		if (!$this->connection) {
			throw new ProgrammerException('Unable to send data since the connection has already been closed');
		}

		$look_length = 0 - strlen($this->line_ending);
		if (substr($data, $look_length) != $this->line_ending) {
			$data .= $this->line_ending;
		}
		if (Core::getDebug($this->debug)) {
			Core::debug("Sending:\n" . trim($data), $this->debug);
		}

		$res = fwrite($this->connection, $data);

		if ($res === false || $res === 0) {
			throw new ConnectivityException('Unable to write data to SMTP server %1$s on port %2$s', $this->host, $this->port);
		}
		$response = $this->read($expect);
		return $response;
	}
}


/**
 * Copyright (c) 2010-2011 Will Bond <will@flourishlib.com>
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
