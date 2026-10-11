<?php

declare(strict_types=1);

/**
 * Retrieves and deletes messages from a email account via IMAP or POP3
 *
 * All headers, text and html content returned by this class are encoded in
 * UTF-8. Please see http://flourishlib.com/docs/UTF-8 for more information.
 *
 * @copyright  Copyright (c) 2010-2012 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 *
 * @package    Flourish
 * @link       http://flourishlib.com/Mailbox
 *
 * @version    1.0.0b18
 * @changes    1.0.0b18  Fixed a bug in ::fetchMessageSource() where IMAP connections would add an extra `\r\n` to the end of the source [wb, 2012-09-16]
 * @changes    1.0.0b17  Updated the class to be more forgiving when parsing the response for `STATUS` and `FETCH` IMAP commands [wb, 2012-09-15]
 * @changes    1.0.0b16  Added method ::fetchMessageSource() [wb, 2012-09-15]
 * @changes    1.0.0b15  Fixed handling of bounces with no headers [wb, 2012-09-15]
 * @changes    1.0.0b14  Added a workaround for iconv having issues in MAMP 1.9.4+ [wb, 2011-07-26]
 * @changes    1.0.0b13  Fixed handling of headers in relation to encoded-words being embedded inside of quoted strings [wb, 2011-07-26]
 * @changes    1.0.0b12  Enhanced the error checking in ::write() [wb, 2011-06-03]
 * @changes    1.0.0b11  Added code to work around PHP bug #42682 (http://bugs.php.net/bug.php?id=42682) where `stream_select()` doesn't work on 64bit machines from PHP 5.2.0 to 5.2.5, improved connectivity error handling and timeouts while reading data [wb, 2011-01-10]
 * @changes    1.0.0b10  Fixed ::parseMessage() to properly handle a header format edge case and properly set the `text` and `html` keys even when the email has an explicit `Content-disposition: inline` header [wb, 2010-11-25]
 * @changes    1.0.0b9   Fixed a bug in ::parseMessage() that could cause HTML alternate content to be included in the `inline` content array instead of the `html` element [wb, 2010-09-20]
 * @changes    1.0.0b8   Fixed ::parseMessage() to be able to handle non-text/non-html multipart parts that do not have a `Content-disposition` header [wb, 2010-09-18]
 * @changes    1.0.0b7   Fixed a typo in ::read() [wb, 2010-09-07]
 * @changes    1.0.0b6   Fixed a typo from 1.0.0b4 [wb, 2010-07-21]
 * @changes    1.0.0b5   Fixes for increased compatibility with various IMAP and POP3 servers, hacked around a bug in PHP 5.3 on Windows [wb, 2010-06-22]
 * @changes    1.0.0b4   Added code to handle emails without an explicit `Content-type` header [wb, 2010-06-04]
 * @changes    1.0.0b3   Added missing static method callback constants [wb, 2010-05-11]
 * @changes    1.0.0b2   Added the missing ::enableDebugging() [wb, 2010-05-05]
 * @changes    1.0.0b    The initial implementation [wb, 2010-05-05]
 *
 * @phpstan-type MailboxAddress array{personal?: string, mailbox: string, host?: string}
 * @phpstan-type MailboxStructure array{type: string, subtype: string, encoding: string, data: string, content_id: string|null, disposition: string|null, disposition_fields: array<string, string>, type_fields: array<string, string>, parts: list<array<string, mixed>>|null}
 */
class Mailbox
{
	/**
	 * S/MIME certificates and private keys for verification and decryption
	 *
	 * @var array<string, array{certificate: File, private_key: File|null, password: string|null}>
	 */
	private static array $smime_pairs = array();


	/**
	 * Adds an S/MIME certificate, or certificate + private key pair for verification and decryption of S/MIME messages
	 *
	 * @param string       $email_address         The email address the certificate or private key is for
	 * @param File|string $certificate_file      The file the S/MIME certificate is stored in - required for verification and decryption
	 * @param File        $private_key_file      The file the S/MIME private key is stored in - required for decryption only
	 * @param string       $private_key_password  The password for the private key
	 * @return void
	 */
	public static function addSMIMEPair(string $email_address, mixed $certificate_file, ?File $private_key_file = null, ?string $private_key_password = null): void
	{
		if (!$certificate_file instanceof File) {
			$certificate_file = new File($certificate_file);
		}
		self::$smime_pairs[strtolower($email_address)] = array(
			'certificate' => $certificate_file,
			'private_key' => $private_key_file,
			'password'    => $private_key_password
		);
	}


	/**
	 * Takes a date, removes comments and cleans up some common formatting inconsistencies
	 *
	 * @param string $date  The date to clean
	 * @return string  The cleaned date
	 */
	private static function cleanDate(string $date): string
	{
		$date = preg_replace('#\([^)]+\)#', ' ', trim($date)) ?? '';
		$date = preg_replace('#\s+#', ' ', $date) ?? '';
		$date = preg_replace('#(\d+)-([a-z]+)-(\d{4})#i', '\1 \2 \3', $date) ?? '';
		$date = preg_replace('#^[a-z]+\s*,\s*#i', '', trim($date)) ?? '';
		return trim($date);
	}


	/**
	 * Decodes encoded-word headers of any encoding into raw UTF-8
	 *
	 * @param string $text  The header value to decode
	 * @return string  The decoded UTF-8
	 */
	private static function decodeHeader(string $text): string
	{
		$parts = preg_split('#(=\?[^\?]+\?[QB]\?[^\?]+\?=)#i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
		if ($parts === false) {
			return $text;
		}

		$part_with_encoding = array();
		$output = '';
		foreach ($parts as $part) {
			if ($part === '') {
				continue;
			}

			if (preg_match_all('#=\?([^\?]+)\?([QB])\?([^\?]+)\?=#i', $part, $matches, PREG_SET_ORDER)) {
				foreach ($matches as $match) {
					if (strtoupper($match[2]) == 'Q') {
						$part_string = rawurldecode(strtr(
							$match[3],
							array(
								'=' => '%',
								'_' => ' '
							)
						));
					} else {
						$part_string = base64_decode($match[3]);
					}
					$lower_encoding = strtolower($match[1]);
					$last_key = count($part_with_encoding) - 1;
					if (isset($part_with_encoding[$last_key]) && $part_with_encoding[$last_key]['encoding'] == $lower_encoding) {
						$part_with_encoding[$last_key]['string'] .= $part_string;
					} else {
						$part_with_encoding[] = array('encoding' => $lower_encoding, 'string' => $part_string);
					}
				}
			} else {
				$last_key = count($part_with_encoding) - 1;
				if (isset($part_with_encoding[$last_key]) && $part_with_encoding[$last_key]['encoding'] == 'iso-8859-1') {
					$part_with_encoding[$last_key]['string'] .= $part;
				} else {
					$part_with_encoding[] = array('encoding' => 'iso-8859-1', 'string' => $part);
				}
			}
		}

		foreach ($part_with_encoding as $part) {
			$output .= self::iconv($part['encoding'], 'UTF-8', $part['string']);
		}

		return $output;
	}


	/**
	 * Handles an individual part of a multipart message
	 *
	 * @param  array<string, mixed>       $info       An array of information about the message
	 * @param  array<array-key, mixed>    $structure  An array describing the structure of the message
	 * @return array<string, mixed>  The modified $info array
	 */
	private static function handlePart(array $info, array $structure): array
	{
		$structure_type = $structure['type'] ?? null;
		$subtype        = $structure['subtype'] ?? null;

		if ($structure_type == 'multipart') {
			$parts = $structure['parts'] ?? null;
			if (is_array($parts)) {
				foreach ($parts as $part) {
					if (is_array($part)) {
						$info = self::handlePart($info, $part);
					}
				}
			}
			return $info;
		}

		if ($structure_type == 'application' && in_array($subtype, array('pkcs7-mime', 'x-pkcs7-mime'))) {
			$to = null;
			$info_headers = $info['headers'] ?? null;
			$to_header    = is_array($info_headers) ? ($info_headers['to'] ?? null) : null;
			if (is_array($to_header) && isset($to_header[0]) && is_array($to_header[0])) {
				$mailbox = $to_header[0]['mailbox'] ?? null;
				if (is_string($mailbox)) {
					$to = $mailbox;
				}
				$host = $to_header[0]['host'] ?? null;
				if (is_string($host) && $to !== null && $host !== '') {
					$to .= '@' . $host;
				}
			}
			if (is_string($to)) {
				$smime_pair = self::$smime_pairs[$to] ?? null;
				if ($smime_pair !== null && $smime_pair['private_key'] !== null && self::handleSMIMEDecryption($info, $structure, $smime_pair)) {
					return $info;
				}
			}
		}

		if ($structure_type == 'application' && in_array($subtype, array('pkcs7-signature', 'x-pkcs7-signature'))) {
			$from = null;
			$info_headers = $info['headers'] ?? null;
			$from_header  = is_array($info_headers) ? ($info_headers['from'] ?? null) : null;
			if (is_array($from_header)) {
				$mailbox = $from_header['mailbox'] ?? null;
				if (is_string($mailbox)) {
					$from = $mailbox;
				}
				$host = $from_header['host'] ?? null;
				if (is_string($host) && $from !== null && $host !== '') {
					$from .= '@' . $host;
				}
			}
			if (is_string($from)) {
				$smime_pair = self::$smime_pairs[$from] ?? null;
				if ($smime_pair !== null && self::handleSMIMEVerification($info, $structure, $smime_pair)) {
					return $info;
				}
			}
		}

		$data = $structure['data'] ?? null;
		$data = is_string($data) ? $data : '';

		if (($structure['encoding'] ?? null) == 'base64') {
			$content = '';
			foreach (explode("\r\n", $data) as $line) {
				$content .= base64_decode($line);
			}
		} elseif (($structure['encoding'] ?? null) == 'quoted-printable') {
			$content = quoted_printable_decode($data);
		} else {
			$content = $data;
		}

		if ($structure_type == 'text') {
			$charset = 'iso-8859-1';
			$type_fields = $structure['type_fields'] ?? null;
			if (is_array($type_fields)) {
				foreach ($type_fields as $field => $value) {
					if (is_string($field) && is_string($value) && strtolower($field) == 'charset') {
						$charset = $value;
						break;
					}
				}
			}
			$content = self::iconv($charset, 'UTF-8', $content);
			if ($subtype == 'html') {
				$content = preg_replace('#(content=(["\'])text/html\s*;\s*charset=(["\']?))' . preg_quote($charset, '#') . '(\3\2)#i', '\1utf-8\4', $content) ?? $content;
			}
		}

		// This indicates a content-id which is used for multipart/related
		if (!empty($structure['content_id'])) {
			$related = isset($info['related']) && is_array($info['related']) ? $info['related'] : array();
			$content_id = $structure['content_id'];
			if (is_string($content_id)) {
				$cid = substr($content_id, 0, 1) == '<' ? substr($content_id, 1, -1) : $content_id;
				$related['cid:' . $cid] = array(
					'mimetype' => (is_string($structure_type) ? $structure_type : '') . '/' . (is_string($subtype) ? $subtype : ''),
					'data'     => $content
				);
			}
			$info['related'] = $related;
			return $info;
		}


		$has_disposition = !empty($structure['disposition']);
		$is_text         = $structure_type == 'text' && $subtype == 'plain';
		$is_html         = $structure_type == 'text' && $subtype == 'html';

		// If the part doesn't have a disposition and is not the default text or html, set the disposition to inline
		if (!$has_disposition && ((!$is_text || !empty($info['text'])) && (!$is_html || !empty($info['html'])))) {
			$is_web_image = $structure_type == 'image' && in_array($subtype, array('gif', 'png', 'jpeg', 'pjpeg'));
			$structure['disposition'] = $is_text || $is_html || $is_web_image ? 'inline' : 'attachment';
			$structure['disposition_fields'] = array();
			$has_disposition = true;
		}


		// Attachments or inline content
		if ($has_disposition) {
			$filename = '';
			$disposition_fields = $structure['disposition_fields'] ?? null;
			if (is_array($disposition_fields)) {
				foreach ($disposition_fields as $field => $value) {
					if (is_string($field) && is_string($value) && strtolower($field) == 'filename') {
						$filename = $value;
						break;
					}
				}
			}
			$type_fields = $structure['type_fields'] ?? null;
			if (is_array($type_fields)) {
				foreach ($type_fields as $field => $value) {
					if (is_string($field) && is_string($value) && strtolower($field) == 'name') {
						$filename = $value;
						break;
					}
				}
			}
			$disposition = is_string($structure['disposition']) ? $structure['disposition'] : '';

			// This automatically handles primary content that has a content-disposition header on it
			if ($disposition == 'inline' && $filename === '') {
				if ($is_text && !isset($info['text'])) {
					$info['text'] = $content;
					return $info;
				}
				if ($is_html && !isset($info['html'])) {
					$info['html'] = $content;
					return $info;
				}
			}

			$attachments = isset($info[$disposition]) && is_array($info[$disposition]) ? $info[$disposition] : array();
			$attachments[] = array(
				'filename' => $filename,
				'mimetype' => (is_string($structure_type) ? $structure_type : '') . '/' . (is_string($subtype) ? $subtype : ''),
				'data'     => $content
			);
			$info[$disposition] = $attachments;
			return $info;
		}

		if ($is_text) {
			$info['text'] = $content;
			return $info;
		}

		if ($is_html) {
			$info['html'] = $content;
			return $info;
		}

		return $info;
	}


	/**
	 * Tries to decrypt an S/MIME message using a private key
	 *
	 * @param  array<string, mixed>       &$info       The array of information about a message
	 * @param  array<array-key, mixed>    $structure   The structure of this part
	 * @param  array{certificate: File, private_key: File|null, password: string|null}  $smime_pair  An associative array containing an S/MIME certificate, private key and password
	 * @return boolean  If the message was decrypted
	 */
	private static function handleSMIMEDecryption(array &$info, array $structure, array $smime_pair): bool
	{
		$plaintext_file  = tempnam('', '__fMailbox_');
		$ciphertext_file = tempnam('', '__fMailbox_');
		if (!is_string($plaintext_file) || !is_string($ciphertext_file)) {
			return false;
		}

		$structure_type = $structure['type'] ?? null;
		$subtype        = $structure['subtype'] ?? null;
		$encoding       = $structure['encoding'] ?? null;
		$data           = $structure['data'] ?? null;

		$headers   = array();
		$headers[] = "Content-Type: " . (is_string($structure_type) ? $structure_type : '') . '/' . (is_string($subtype) ? $subtype : '');
		$headers[] = "Content-Transfer-Encoding: " . (is_string($encoding) ? $encoding : '');
		$disposition = $structure['disposition'] ?? null;
		$header    = "Content-Disposition: " . (is_string($disposition) ? $disposition : '');
		$disposition_fields = $structure['disposition_fields'] ?? null;
		if (is_array($disposition_fields)) {
			foreach ($disposition_fields as $field => $value) {
				if (is_string($field) && is_string($value)) {
					$header .= '; ' . $field . '="' . $value . '"';
				}
			}
		}
		$headers[] = $header;

		file_put_contents($ciphertext_file, join("\r\n", $headers) . "\r\n\r\n" . (is_string($data) ? $data : ''));

		if ($smime_pair['private_key'] === null) {
			unlink($ciphertext_file);
			unlink($plaintext_file);
			return false;
		}
		$private_key = openssl_pkey_get_private(
			$smime_pair['private_key']->read(),
			$smime_pair['password']
		);
		if ($private_key === false) {
			unlink($ciphertext_file);
			unlink($plaintext_file);
			return false;
		}
		$certificate = $smime_pair['certificate']->read();

		$result = openssl_pkcs7_decrypt($ciphertext_file, $plaintext_file, $certificate, $private_key);
		unlink($ciphertext_file);

		if (!$result) {
			unlink($plaintext_file);
			return false;
		}

		$contents = file_get_contents($plaintext_file);
		unlink($plaintext_file);
		if (!is_string($contents)) {
			return false;
		}
		$info['raw_message'] = $contents;
		$info = self::handlePart($info, self::parseStructure($contents));
		$info['decrypted'] = true;

		return true;
	}



	/**
	 * Takes a message with an S/MIME signature and verifies it if possible
	 *
	 * @param array<string, mixed>       &$info       The array of information about a message
	 * @param array<array-key, mixed>    $structure   The structure of this part
	 * @param array{certificate: File, private_key: File|null, password: string|null}  $smime_pair  An associative array containing an S/MIME certificate file
	 * @return boolean  If the message was verified
	 */
	private static function handleSMIMEVerification(array &$info, array $structure, array $smime_pair): bool
	{
		$certificates_file = tempnam('', '__fMailbox_');
		$ciphertext_file   = tempnam('', '__fMailbox_');
		if (!is_string($certificates_file) || !is_string($ciphertext_file)) {
			return false;
		}

		$raw_message = $info['raw_message'] ?? null;
		file_put_contents($ciphertext_file, is_string($raw_message) ? $raw_message : '');

		$result = openssl_pkcs7_verify(
			$ciphertext_file,
			PKCS7_NOINTERN | PKCS7_NOVERIFY,
			$certificates_file,
			array(),
			$smime_pair['certificate']->getPath()
		);
		unlink($ciphertext_file);
		unlink($certificates_file);

		if (!$result || $result === -1) {
			return false;
		}

		$info['verified'] = true;

		return true;
	}


	/**
	 * This works around a bug in MAMP 1.9.4+ and PHP 5.3 where iconv()
	 * does not seem to properly assign the return value to a variable, but
	 * does work when returning the value.
	 *
	 * @param string $in_charset   The incoming character encoding
	 * @param string $out_charset  The outgoing character encoding
	 * @param string $string       The string to convert
	 * @return string  The converted string
	 */
	private static function iconv(string $in_charset, string $out_charset, string $string): string
	{
		$result = iconv($in_charset, $out_charset, $string);
		return is_string($result) ? $result : $string;
	}


	/**
	 * Joins parsed emails into a comma-delimited string
	 *
	 * @param array<array-key, mixed>  $emails  An array of emails split into personal, mailbox and host parts
	 * @return string  An comma-delimited list of emails
	 */
	private static function joinEmails(array $emails): string
	{
		$output = '';
		foreach ($emails as $email) {
			if (!is_array($email)) {
				continue;
			}
			if ($output) {
                $output .= ', ';
            }

			if (!array_key_exists(0, $email)) {
				$email[0] = isset($email['personal']) && is_string($email['personal']) ? $email['personal'] : '';
				$email[2] = isset($email['mailbox']) && is_string($email['mailbox']) ? $email['mailbox'] : '';
				$email[3] = isset($email['host']) && is_string($email['host']) ? $email['host'] : '';
			}

			$address = isset($email[2]) && is_string($email[2]) ? $email[2] : '';
			if (isset($email[3]) && is_string($email[3]) && $email[3] !== '') {
				$address .= '@' . $email[3];
			}
			$name = isset($email[0]) && is_string($email[0]) ? $email[0] : '';
			$output .= Email::combineNameEmail($name, $address);
		}
		return $output;
	}


	/**
	 * Parses a string representation of an email into the persona, mailbox and host parts
	 *
	 * @param  string $string  The email string to parse
	 * @return MailboxAddress  An associative array with the key `mailbox`, and possibly `host` and `personal`
	 */
	private static function parseEmail(string $string): array
	{
		$email_regex = '((?:[^\x00-\x20\(\)<>@,;:\\\\"\.\[\]]+|"[^"\\\\\n\r]+")(?:\.[ \t]*(?:[^\x00-\x20\(\)<>@,;:\\\\"\.\[\]]+|"[^"\\\\\n\r]+"[ \t]*))*)@((?:[a-z0-9\\-]+\.)+[a-z]{2,}|\[(?:(?:[01]?\d?\d|2[0-4]\d|25[0-5])\.){3}(?:[01]?\d?\d|2[0-4]\d|25[0-5])\])';
		$name_regex  = '((?:[^\x00-\x20\(\)<>@,;:\\\\"\.\[\]]+[ \t]*|"[^"\\\\\n\r]+"[ \t]*)(?:\.?[ \t]*(?:[^\x00-\x20\(\)<>@,;:\\\\"\.\[\]]+[ \t]*|"[^"\\\\\n\r]+"[ \t]*))*)';

		if (preg_match('~^[ \t]*' . $name_regex . '[ \t]*<[ \t]*' . $email_regex . '[ \t]*>[ \t]*$~ixD', $string, $match)) {
			$match[1] = trim($match[1]);
			if ($match[1][0] == '"' && substr($match[1], -1) == '"') {
				$match[1] = substr($match[1], 1, -1);
			}
			return array(
				'personal' => self::decodeHeader($match[1]),
				'mailbox' => self::decodeHeader($match[2]),
				'host' => self::decodeHeader($match[3])
			);
		} elseif (preg_match('~^[ \t]*(?:<[ \t]*)?' . $email_regex . '(?:[ \t]*>)?[ \t]*$~ixD', $string, $match)) {
			return array(
				'mailbox' => self::decodeHeader($match[1]),
				'host' => self::decodeHeader($match[2])
			);

		// This handles the outdated practice of including the personal
		// part of the email in a comment after the email address
		} elseif (preg_match('~^[ \t]*(?:<[ \t]*)?' . $email_regex . '(?:[ \t]*>)?[ \t]*\(([^)]+)\)[ \t]*$~ixD', $string, $match)) {
			$match[3] = trim($match[1]);
			if ($match[3][0] == '"' && substr($match[3], -1) == '"') {
				$match[3] = substr($match[3], 1, -1);
			}

			return array(
				'personal' => self::decodeHeader($match[3]),
				'mailbox' => self::decodeHeader($match[1]),
				'host' => self::decodeHeader($match[2])
			);
		}

		if (strpos($string, '@') !== false) {
			list ($mailbox, $host) = explode('@', $string, 2);
			return array(
				'mailbox' => self::decodeHeader($mailbox),
				'host' => self::decodeHeader($host)
			);
		}

		return array(
			'mailbox' => self::decodeHeader($string),
			'host' => ''
		);
	}


	/**
	 * Parses full email headers into an associative array
	 *
	 * @param  string $headers  The header to parse
	 * @param  string $filter   Remove any headers that match this
	 * @return array<string, mixed>  The parsed headers
	 */
	private static function parseHeaders(string $headers, ?string $filter = null): array
	{
		$headers = trim($headers);
		if (!strlen($headers)) {
			return array();
		}
		$header_lines = preg_split("#\r\n(?!\s)#", $headers);
		if (!is_array($header_lines)) {
			$header_lines = array();
		}

		$single_email_fields    = array('from', 'sender', 'reply-to');
		$multi_email_fields     = array('to', 'cc');
		$additional_info_fields = array('content-type', 'content-disposition');

		$headers = array();
		foreach ($header_lines as $header_line) {
			$header_line = preg_replace("#\r\n\s+#", '', $header_line) ?? $header_line;

			$header_value_parts = preg_split('#:\s*#', $header_line, 2);
			if (!is_array($header_value_parts)) {
				$header_value_parts = array('', '');
			}
			list ($header, $value) = array_pad($header_value_parts, 2, '');
			$header = strtolower($header);

			if ($filter !== null && strpos($header, $filter) !== false) {
				continue;
			}

			$is_single_email          = in_array($header, $single_email_fields);
			$is_multi_email           = in_array($header, $multi_email_fields);
			$is_additional_info_field = in_array($header, $additional_info_fields);

			if ($is_additional_info_field) {
				$pieces = preg_split('#;\s*#', $value, 2);
				if (!is_array($pieces)) {
					$pieces = array($value, '');
				}
				$value = $pieces[0];

				$headers[$header] = array('value' => self::decodeHeader($value));

				$fields = array();
				if (!empty($pieces[1])) {
					preg_match_all('#(\w+)=("([^"]+)"|([^\s;]+))(?=;|$)#', $pieces[1], $matches, PREG_SET_ORDER);
					foreach ($matches as $match) {
						$fields[$match[1]] = self::decodeHeader($match[4] ?? $match[3] ?? '');
					}
				}
				$headers[$header]['fields'] = $fields;
			} elseif ($is_single_email) {
				$headers[$header] = self::parseEmail($value);
			} elseif ($is_multi_email) {
				$strings = array();

				preg_match_all('#"[^"]+?"#', $value, $matches, PREG_SET_ORDER);
				foreach ($matches as $i => $match) {
					$strings[] = $match[0];
					$value = preg_replace('#' . preg_quote($match[0], '#') . '#', ':string' . sizeof($strings), $value, 1) ?? $value;
				}
				preg_match_all('#\([^)]+?\)#', $value, $matches, PREG_SET_ORDER);
				foreach ($matches as $i => $match) {
					$strings[] = $match[0];
					$value = preg_replace('#' . preg_quote($match[0], '#') . '#', ':string' . sizeof($strings), $value, 1) ?? $value;
				}

				$emails = explode(',', $value);
				$emails = array_map('trim', $emails);
				foreach ($strings as $i => $string) {
					$emails = preg_replace(
						'#:string' . ($i + 1) . '\b#',
						strtr($string, array('\\' => '\\\\', '$' => '\\$')),
						$emails,
						1
					);
				}

				$email_list = array();
				foreach ($emails as $email) {
					$email_list[] = self::parseEmail($email);
				}
				$headers[$header] = $email_list;
			} elseif ($header == 'references') {
				$references = preg_split('#(?<=>)\s+(?=<)#', $value);
				if (!is_array($references)) {
					$references = array();
				}
				$headers[$header] = array_map(array('Mailbox', 'decodeHeader'), $references);
			} elseif ($header == 'received') {
				$received_list = isset($headers[$header]) && is_array($headers[$header]) ? $headers[$header] : array();
				$received_list[] = preg_replace('#\s+#', ' ', self::decodeHeader($value)) ?? '';
				$headers[$header] = $received_list;
			} else {
				$headers[$header] = self::decodeHeader($value);
			}
		}

		return $headers;
	}


	/**
	 * Parses a MIME message into an associative array of information
	 *
	 * The output includes the following keys:
	 *
	 *  - `'received'`: The date the message was received by the server
	 *  - `'headers'`: An associative array of mail headers, the keys are the header names, in lowercase
	 *
	 * And one or more of the following:
	 *
	 *  - `'text'`: The plaintext body
	 *  - `'html'`: The HTML body
	 *  - `'attachment'`: An array of attachments, each containing:
	 *   - `'filename'`: The name of the file
	 *   - `'mimetype'`: The mimetype of the file
	 *   - `'data'`: The raw contents of the file
	 *  - `'inline'`: An array of inline files, each containing:
	 *   - `'filename'`: The name of the file
	 *   - `'mimetype'`: The mimetype of the file
	 *   - `'data'`: The raw contents of the file
	 *  - `'related'`: An associative array of related files, such as embedded images, with the key `'cid:{content-id}'` and an array value containing:
	 *   - `'mimetype'`: The mimetype of the file
	 *   - `'data'`: The raw contents of the file
	 *  - `'verified'`: If the message contents were verified via an S/MIME certificate - if not verified the smime.p7s will be listed as an attachment
	 *  - `'decrypted'`: If the message contents were decrypted via an S/MIME private key - if not decrypted the smime.p7m will be listed as an attachment
	 *
	 * All values in `headers`, `text` and `body` will have been decoded to
	 * UTF-8. Files in the `attachment`, `inline` and `related` array will all
	 * retain their original encodings.
	 *
	 * @param string  $message           The full source of the email message
	 * @param boolean $convert_newlines  If `\r\n` should be converted to `\n` in the `text` and `html` parts the message
	 * @return array<string, mixed>  The parsed email message - see method description for details
	 */
	public static function parseMessage(string $message, bool $convert_newlines = false): array
	{
		$info = array();
		list ($headers, $body)   = explode("\r\n\r\n", $message, 2);
		$parsed_headers          = self::parseHeaders($headers);

		$received_info   = $parsed_headers['received'] ?? null;
		$received_value  = is_array($received_info) ? ($received_info[0] ?? null) : $received_info;
		$received_value  = is_string($received_value) ? $received_value : '';
		$received_date   = preg_replace('#^.*;\s*([^;]+)$#', '\1', $received_value);
		$info['received'] = self::cleanDate(is_string($received_date) ? $received_date : $received_value);

		$info['headers']         = array();
		foreach ($parsed_headers as $header => $value) {
			if (substr($header, 0, 8) == 'content-') {
				continue;
			}
			$info['headers'][$header] = $value;
		}
		$info['raw_headers'] = $headers;
		$info['raw_message'] = $message;

		$info = self::handlePart($info, self::parseStructure($body, $parsed_headers));
		unset($info['raw_message']);
		unset($info['raw_headers']);

		if ($convert_newlines) {
			if (isset($info['text']) && is_string($info['text'])) {
				$info['text'] = str_replace("\r\n", "\n", $info['text']);
			}
			if (isset($info['html']) && is_string($info['html'])) {
				$info['html'] = str_replace("\r\n", "\n", $info['html']);
			}
		}

		if (isset($info['text']) && is_string($info['text'])) {
			$info['text'] = preg_replace('#\r?\n$#D', '', $info['text']) ?? $info['text'];
		}
		if (isset($info['html']) && is_string($info['html'])) {
			$info['html'] = preg_replace('#\r?\n$#D', '', $info['html']) ?? $info['html'];
		}

		return $info;
	}


	/**
	 * Takes a response from an IMAP command and parses it into a
	 * multi-dimensional array
	 *
	 * @param string  $text       The IMAP command response
	 * @param boolean $top_level  If we are parsing the top level
	 * @return array<array-key, mixed>  The parsed representation of the response text
	 */
	private static function parseResponse(string $text, bool $top_level = false): array
	{
		$regex = '[\\\\\w.\[\]]+|"([^"\\\\]+|\\\\"|\\\\\\\\)*"|\((?:(?1)[ \t]*)*\)';

		if (preg_match('#\{(\d+)\}#', $text, $match)) {
			$regex = '\{' . $match[1] . '\}\r\n.{' . ($match[1]) . '}|' . $regex;
		}

		preg_match_all('#(' . $regex . ')#s', $text, $matches, PREG_SET_ORDER);
		$output = array();
		foreach ($matches as $match) {
			if (substr($match[0], 0, 1) == '"') {
				$output[] = str_replace('\\"', '"', substr($match[0], 1, -1));
			} elseif (substr($match[0], 0, 1) == '(') {
				$output[] = self::parseResponse(substr($match[0], 1, -1));
			} elseif (substr($match[0], 0, 1) == '{') {
				$output[] = preg_replace('#^[^\r]+\r\n#', '', $match[0]);
			} else {
				$output[] = $match[0];
			}
		}

		if ($top_level) {
			$new_output = array();
			$total_size = count($output);
			for ($i = 0; $i < $total_size; $i = $i + 2) {
				$name  = $output[$i] ?? null;
				$value = $output[$i + 1] ?? null;
				if (is_string($name)) {
					$new_output[strtolower($name)] = $value;
				}
			}
			$output = $new_output;
		}

		return $output;
	}


	/**
	 * Takes the raw contents of a MIME message and creates an array that
	 * describes the structure of the message
	 *
	 * @param string $data     The contents to get the structure of
	 * @param array<string, mixed>|string|null $headers  The parsed headers for the message - if not present they will be extracted from the `$data`
	 * @return MailboxStructure  The multi-dimensional, associative array containing the message structure
	 */
	private static function parseStructure(string $data, array|string|null $headers = null): array
	{
		if ($headers === null) {
			$split = preg_split("#^\r\n|\r\n\r\n#", $data, 2);
			if (!is_array($split) || sizeof($split) != 2) {
				throw new ProgrammerException('An invalid email message was passed to parseStructure(), the header and body could not be split');
			}
			list ($headers, $data) = $split;
			$headers = self::parseHeaders($headers);
		}
		if (is_string($headers)) {
			$headers = self::parseHeaders($headers);
		}

		$content_type_info = $headers['content-type'] ?? null;
		if (!is_array($content_type_info)) {
			$content_type_info = array('value' => 'text/plain', 'fields' => array());
		}
		$content_type_value  = is_string($content_type_info['value'] ?? null) ? $content_type_info['value'] : 'text/plain';
		$content_type_fields = is_array($content_type_info['fields'] ?? null) ? $content_type_info['fields'] : array();
		list ($type, $subtype) = array_pad(explode('/', strtolower($content_type_value), 2), 2, '');

		$disposition_fields = array();
		$type_fields        = array();
		foreach ($content_type_fields as $field_name => $field_value) {
			if (is_string($field_name) && is_string($field_value)) {
				$type_fields[$field_name] = $field_value;
			}
		}

		if ($type == 'multipart') {
			$structure    = array(
				'type'               => $type,
				'subtype'            => $subtype,
				'encoding'           => '8bit',
				'data'               => '',
				'content_id'         => null,
				'disposition'        => null,
				'disposition_fields' => $disposition_fields,
				'type_fields'        => $type_fields,
				'parts'              => array()
			);
			$boundary     = $content_type_fields['boundary'] ?? null;
			$boundary     = is_string($boundary) ? $boundary : '';
			$start_pos    = strpos($data, '--' . $boundary) + strlen($boundary) + 4;
			$end_pos      = strrpos($data, '--' . $boundary . '--') - 2;
			$sub_contents = explode("\r\n--" . $boundary . "\r\n", substr(
				$data,
				$start_pos,
				$end_pos - $start_pos
			));
			foreach ($sub_contents as $sub_content) {
				$structure['parts'][] = self::parseStructure($sub_content);
			}
		} else {
			$encoding_header = $headers['content-transfer-encoding'] ?? null;
			$encoding        = is_string($encoding_header) ? strtolower($encoding_header) : '8bit';
			$disposition_info = $headers['content-disposition'] ?? null;
			if (is_array($disposition_info)) {
				$disposition_value  = is_string($disposition_info['value'] ?? null) ? $disposition_info['value'] : '';
				$disposition        = strtolower($disposition_value);
				if (is_array($disposition_info['fields'] ?? null)) {
					foreach ($disposition_info['fields'] as $d_field => $d_value) {
						if (is_string($d_field) && is_string($d_value)) {
							$disposition_fields[$d_field] = $d_value;
						}
					}
				}
			} else {
				$disposition        = null;
			}
			$content_id = isset($headers['content-id']) && is_string($headers['content-id']) ? $headers['content-id'] : null;
			$structure  = array(
				'type'               => $type,
				'type_fields'        => $type_fields,
				'subtype'            => $subtype,
				'content_id'         => $content_id,
				'encoding'           => $encoding,
				'disposition'        => $disposition,
				'disposition_fields' => $disposition_fields,
				'data'               => $data,
				'parts'              => null
			);
		}

		return $structure;
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
		self::$smime_pairs = array();
	}


	/**
	 * A counter to use for generating command keys
	 *
	 * @var integer
	 */
	private int $command_num = 1;

	/**
	 * The connection resource
	 *
	 * @var resource|null
	 */
	private mixed $connection = null;

	/**
	 * If debugging has been enabled
	 *
	 * @var boolean
	 */
	private ?bool $debug = null;

	/**
	 * The server hostname or IP address
	 *
	 * @var string
	 */
	private string $host;

	/**
	 * The password for the account
	 *
	 * @var string
	 */
	private string $password;

	/**
	 * The port for the server
	 *
	 * @var integer
	 */
	private int $port;

	/**
	 * If the connection to the server should be secure
	 *
	 * @var boolean
	 */
	private bool $secure;

	/**
	 * The timeout for the connection
	 *
	 * @var int|string
	 */
	private int|string $timeout = 5;

	/**
	 * The type of mailbox, `'imap'` or `'pop3'`
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * The username for the account
	 *
	 * @var string
	 */
	private string $username;


	/**
	 * Configures the connection to the server
	 *
	 * Please note that the GMail POP3 server does not act like other POP3
	 * servers and the GMail IMAP server should be used instead. GMail POP3 only
	 * allows retrieving a message once - during future connections the email
	 * in question will no longer be available.
	 *
	 * @param  string  $type      The type of mailbox, `'pop3'` or `'imap'`
	 * @param  string  $host      The server hostname or IP address
	 * @param  string  $username  The user to log in as
	 * @param  string  $password  The user's password
	 * @param  integer $port      The port to connect via - only required if non-standard
	 * @param  boolean $secure    If SSL should be used for the connection - this requires the `openssl` extension
	 * @param  integer $timeout   The timeout to use when connecting
	 * @return Mailbox
	 */
	public function __construct(mixed $type, mixed $host, mixed $username, mixed $password, mixed $port = null, mixed $secure = false, mixed $timeout = null)
	{
		if ($timeout === null) {
			$timeout = ini_get('default_socket_timeout');
		}

		$valid_types = array('imap', 'pop3');
		if (!in_array($type, $valid_types)) {
			throw new ProgrammerException(
				'The mailbox type specified, %1$s, in invalid. Must be one of: %2$s.',
				$type,
				join(', ', $valid_types)
			);
		}

		if ($port === null) {
			if ($type == 'imap') {
				$port = !$secure ? 143 : 993;
			} else {
				$port = !$secure ? 110 : 995;
			}
		}

		if ($secure && !extension_loaded('openssl')) {
			throw new EnvironmentException(
				'A secure connection was requested, but the %s extension is not installed',
				'openssl'
			);
		}

		$this->type     = $type;
		$this->host     = $host;
		$this->username = $username;
		$this->password = $password;
		$this->port     = $port;
		$this->secure   = $secure;
		$this->timeout  = $timeout;
	}


	/**
	 * Disconnects from the server
	 *
	 * @return void
	 */
	public function __destruct()
	{
		$this->close();
	}


	/**
	 * Closes the connection to the server
	 *
	 * @return void
	 */
	public function close(): void
	{
		if (!$this->connection) {
			return;
		}

		if ($this->type == 'imap') {
			$this->write('LOGOUT');
		} else {
			$this->write('QUIT', 1);
		}

		$this->connection = null;
	}


	/**
	 * Connects to the server
	 *
	 * @return void
	 */
	private function connect(): void
	{
		if ($this->connection) {
			return;
		}

		Core::startErrorCapture(E_WARNING);

		$connection = fsockopen(
			$this->secure ? 'tls://' . $this->host : $this->host,
			$this->port,
			$error_number,
			$error_string,
			(float) $this->timeout
		);

		foreach (Core::stopErrorCapture('#ssl#i') as $error) {
			throw new ConnectivityException('There was an error connecting to the server. A secure connection was requested, but was not available. Try a non-secure connection instead.');
		}

		if (!$connection) {
			throw new ConnectivityException('There was an error connecting to the server');
		}

		$this->connection = $connection;
		stream_set_timeout($connection, (int) $this->timeout);


		if ($this->type == 'imap') {
			if (!$this->secure && extension_loaded('openssl')) {
				$response = $this->write('CAPABILITY');
				if (isset($response[0]) && preg_match('#\bstarttls\b#i', $response[0])) {
					$this->write('STARTTLS');
					do {
						if (isset($res)) {
							usleep(100000);
						}
						$res = stream_socket_enable_crypto($connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
					} while ($res === 0);
				}
			}

			$response = $this->write('LOGIN ' . $this->username . ' ' . $this->password);
			$login_ok = false;
			if ($response) {
				$last_line = end($response);
				if (preg_match('#^[^ ]+\s+OK#', $last_line)) {
					$login_ok = true;
				}
			}
			if (!$login_ok) {
				throw new ValidationException(
					'The username and password provided were not accepted for the %1$s server %2$s on port %3$s',
					strtoupper($this->type),
					$this->host,
					$this->port
				);
			}
			$this->write('SELECT "INBOX"');
		} elseif ($this->type == 'pop3') {
			$response = $this->read(1);
			if (isset($response[0])) {
				if ($response[0][0] == '-') {
					throw new ConnectivityException(
						'There was an error connecting to the POP3 server %1$s on port %2$s',
						$this->host,
						$this->port
					);
				}
				preg_match('#<[^@]+@[^>]+>#', $response[0], $match);
			}

			if (!$this->secure && extension_loaded('openssl')) {
				$response = $this->write('STLS', 1);
				if (isset($response[0]) && $response[0][0] == '+') {
					do {
						if (isset($res)) {
							usleep(100000);
						}
						$res = stream_socket_enable_crypto($connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
					} while ($res === 0);
					if ($res === false) {
						throw new ConnectivityException('Error establishing secure connection');
					}
				}
			}

			$authenticated = false;
			if (isset($match[0])) {
				$response = $this->write('APOP ' . $this->username . ' ' . md5($match[0] . $this->password), 1);
				if (isset($response[0]) && $response[0][0] == '+') {
					$authenticated = true;
				}
			}

			if (!$authenticated) {
				$response = $this->write('USER ' . $this->username, 1);
				if (isset($response[0]) && $response[0][0] == '+') {
					$response = $this->write('PASS ' . $this->password, 1);
					if (isset($response[0][0]) && $response[0][0] == '+') {
						$authenticated = true;
					}
				}
			}

			if (!$authenticated) {
				throw new ValidationException(
					'The username and password provided were not accepted for the %1$s server %2$s on port %3$s',
					strtoupper($this->type),
					$this->host,
					$this->port
				);
			}
		}
	}


	/**
	 * Deletes one or more messages from the server
	 *
	 * Passing more than one UID at a time is more efficient for IMAP mailboxes,
	 * whereas POP3 mailboxes will see no difference in performance.
	 *
	 * @param  integer|array<array-key, mixed> $uid  The UID(s) of the message(s) to delete
	 * @return void
	 */
	public function deleteMessages(mixed $uid): void
	{
		$this->connect();

		if (!is_array($uid)) {
			$uid = array($uid);
		}

		if ($this->type == 'imap') {
			$uids = array();
			foreach ($uid as $id) {
				if (is_int($id) || is_string($id)) {
					$uids[] = (string) $id;
				}
			}
			$this->write('UID STORE ' . join(',', $uids) . ' +FLAGS (\\Deleted)');
			$this->write('EXPUNGE');
		} elseif ($this->type == 'pop3') {
			foreach ($uid as $id) {
				if (is_int($id) || is_string($id)) {
					$this->write('DELE ' . (string) $id, 1);
				}
			}
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
	 * Retrieves a single message from the server
	 *
	 * The output includes the following keys:
	 *
	 *  - `'uid'`: The UID of the message
	 *  - `'received'`: The date the message was received by the server
	 *  - `'headers'`: An associative array of mail headers, the keys are the header names, in lowercase
	 *
	 * And one or more of the following:
	 *
	 *  - `'text'`: The plaintext body
	 *  - `'html'`: The HTML body
	 *  - `'attachment'`: An array of attachments, each containing:
	 *   - `'filename'`: The name of the file
	 *   - `'mimetype'`: The mimetype of the file
	 *   - `'data'`: The raw contents of the file
	 *  - `'inline'`: An array of inline files, each containing:
	 *   - `'filename'`: The name of the file
	 *   - `'mimetype'`: The mimetype of the file
	 *   - `'data'`: The raw contents of the file
	 *  - `'related'`: An associative array of related files, such as embedded images, with the key `'cid:{content-id}'` and an array value containing:
	 *   - `'mimetype'`: The mimetype of the file
	 *   - `'data'`: The raw contents of the file
	 *  - `'verified'`: If the message contents were verified via an S/MIME certificate - if not verified the smime.p7s will be listed as an attachment
	 *  - `'decrypted'`: If the message contents were decrypted via an S/MIME private key - if not decrypted the smime.p7m will be listed as an attachment
	 *
	 * All values in `headers`, `text` and `body` will have been decoded to
	 * UTF-8. Files in the `attachment`, `inline` and `related` array will all
	 * retain their original encodings.
	 *
	 * @param  integer $uid               The UID of the message to retrieve
	 * @param  boolean $convert_newlines  If `\r\n` should be converted to `\n` in the `text` and `html` parts the message
	 * @return array<string, mixed>  The parsed email message - see method description for details
	 */
	public function fetchMessage(int $uid, bool $convert_newlines = false): array
	{
		$this->connect();

		$source = $this->fetchMessageSource($uid);

		$info = self::parseMessage($source, $convert_newlines);
		$info['uid'] = $uid;

		return $info;
	}


	/**
	 * Retrieves the raw source of a single message from the server
	 *
	 * This method is primarily useful for storing the raw source of a message.
	 * Normal use of Mailbox would involved calling ::fetchMessage(), which
	 * calls this method and then ::parseMessage().
	 *
	 * @param  integer $uid  The UID of the message to retrieve
	 * @return string  The raw message source of the email
	 */
	public function fetchMessageSource(int $uid): string
	{
		$this->connect();

		if ($this->type == 'imap') {
			$response = $this->write('UID FETCH ' . $uid . ' (BODY[])');
			preg_match('#\{(\d+)\}$#', $response[0] ?? '', $match);
			if (!isset($match[1])) {
				throw new ProgrammerException('Unable to determine the size of the message being fetched');
			}
			$byte_count = (int) $match[1];

			$message = '';
			foreach ($response as $i => $line) {
				if (!$i) {
                    continue;
                }
				if (strlen($message) + strlen($line) + 2 > $byte_count) {
					$message .= substr($line . "\r\n", 0, $byte_count - strlen($message));
				} else {
					$message .= $line . "\r\n";
				}
			}

			// Removes the extra trailing \r\n added above to the last line
			return substr($message, 0, -2);
		} elseif ($this->type == 'pop3') {
			$response = $this->write('RETR ' . $uid);
			array_shift($response);
			$response = join("\r\n", $response);

			return $response;
		}

		throw new ProgrammerException('An invalid mailbox type was specified, must be one of: imap, pop3');
	}


	/**
	 * Gets a list of messages from the server
	 *
	 * The structure of the returned array is:
	 *
	 * {{{
	 * array(
	 *     (int) {uid} => array(
	 *         'uid'         => (int) {a unique identifier for this message on this server},
	 *         'received'    => (string) {date message was received},
	 *         'size'        => (int) {size of message in bytes},
	 *         'date'        => (string) {date message was sent},
	 *         'from'        => (string) {the from header value},
	 *         'subject'     => (string) {the message subject},
	 *         'message_id'  => (string) {optional - the message-id header value, should be globally unique},
	 *         'to'          => (string) {optional - the to header value},
	 *         'in_reply_to' => (string) {optional - the in-reply-to header value}
	 *     ), ...
	 * )
	 * }}}
	 *
	 * All values will have been decoded to UTF-8.
	 *
	 * @param  integer $limit  The number of messages to retrieve
	 * @param  integer $page   The page of messages to retrieve
	 * @return array<int, array<string, mixed>>  A list of messages on the server - see method description for details
	 */
	public function listMessages(?int $limit = null, ?int $page = null): array
	{
		$this->connect();

		$output = array();

		if ($this->type == 'imap') {
			if (!$limit) {
				$start = 1;
				$end   = '*';
			} else {
				if (!$page) {
					$page = 1;
				}
				$start = ($limit * ($page - 1)) + 1;
				$end   = $start + $limit - 1;
			}

			$total_messages = 0;
			$response = $this->write('STATUS "INBOX" (MESSAGES)');
			foreach ($response as $line) {
				if (preg_match('#^\s*\*\s+STATUS\s+"?INBOX"?\s+\((.*)\)\s*$#', $line, $match)) {
					$details = self::parseResponse($match[1], true);
					$total_messages_value = $details['messages'] ?? null;
					$total_messages = is_numeric($total_messages_value) ? (int) $total_messages_value : 0;
				}
			}

			if ($start > $total_messages) {
				return array();
			}

			if ($end > $total_messages) {
				$end = $total_messages;
			}

			$output = array();
			$response = $this->write('FETCH ' . $start . ':' . $end . ' (UID INTERNALDATE RFC822.SIZE ENVELOPE)');
			foreach ($response as $line) {
				if (preg_match('#^\s*\*\s+(\d+)\s+FETCH\s+\((.*)\)\s*$#', $line, $match)) {
					$details = self::parseResponse($match[2], true);
					$info    = array();

					$uid_value  = $details['uid'] ?? null;
					$date_value = $details['internaldate'] ?? null;
					$size_value = $details['rfc822.size'] ?? null;
					$info['uid']      = is_numeric($uid_value) ? (int) $uid_value : 0;
					$info['received'] = self::cleanDate(is_string($date_value) ? $date_value : '');
					$info['size']     = is_numeric($size_value) ? (int) $size_value : 0;

					$envelope = $details['envelope'] ?? null;
					if (!is_array($envelope)) {
						continue;
					}
					$envelope_date    = isset($envelope[0]) && is_string($envelope[0]) ? $envelope[0] : '';
					$envelope_subject = isset($envelope[1]) && is_string($envelope[1]) ? $envelope[1] : '';
					$envelope_from    = isset($envelope[2]) && is_array($envelope[2]) ? $envelope[2] : array();
					$info['date']     = $envelope_date != 'NIL' ? $envelope_date : '';
					$info['from']     = self::joinEmails($envelope_from);
					$subject          = $envelope_subject;
					if (preg_match('#=\?[^\?]+\?[QB]\?[^\?]+\?=#', $subject)) {
						do {
							$last_subject = $subject;
							$subject = preg_replace('#(=\?([^\?]+)\?[QB]\?[^\?]+\?=) (\s*=\?\2)#', '\1\3', $subject) ?? $subject;
						} while ($subject != $last_subject);
						$info['subject'] = self::decodeHeader($subject);
					} else {
						$info['subject'] = $subject == 'NIL' ? '' : self::decodeHeader($subject);
					}
					if (isset($envelope[9]) && is_string($envelope[9]) && $envelope[9] != 'NIL') {
						$info['message_id'] = $envelope[9];
					}
					if (isset($envelope[5]) && is_array($envelope[5])) {
						$info['to'] = self::joinEmails($envelope[5]);
					}
					if (isset($envelope[8]) && is_string($envelope[8]) && $envelope[8] != 'NIL') {
						$info['in_reply_to'] = $envelope[8];
					}

					$output[$info['uid']] = $info;
				}
			}
		} elseif ($this->type == 'pop3') {
			if (!$limit) {
				$start = 1;
				$end   = null;
			} else {
				if (!$page) {
					$page = 1;
				}
				$start = ($limit * ($page - 1)) + 1;
				$end   = $start + $limit - 1;
			}

			$total_messages = 0;
			$response = $this->write('STAT', 1);
			if (isset($response[0]) && preg_match('#^\+OK\s+(\d+)\s+#', $response[0], $match)) {
				$total_messages = (int) $match[1];
			}

			if ($start > $total_messages) {
				return array();
			}

			if ($end === null || $end > $total_messages) {
				$end = $total_messages;
			}

			$sizes = array();
			$response = $this->write('LIST');
			array_shift($response);
			foreach ($response as $line) {
				if (preg_match('#^(\d+)\s+(\d+)$#', $line, $match)) {
					$sizes[(int) $match[1]] = (int) $match[2];
				}
			}

			$output = array();
			for ($i = $start; $i <= $end; $i++) {
				$response = $this->write('TOP ' . $i . ' 1');
				array_shift($response);
				$value = array_pop($response);
				// Some servers add an extra blank line after the 1 requested line
				if (is_string($value) && trim($value) == '') {
					array_pop($response);
				}

				$response = trim(join("\r\n", $response));
				$headers  = self::parseHeaders($response);

				$received_header = $headers['received'] ?? null;
				if (is_array($received_header)) {
					$received_value = isset($received_header[0]) && is_string($received_header[0]) ? $received_header[0] : '';
				} else {
					$received_value = is_string($received_header) ? $received_header : '';
				}
				$received_date = preg_replace('#^.*;\s*([^;]+)$#', '\1', $received_value);

				$from_header = $headers['from'] ?? null;
				$output[$i] = array(
					'uid'      => $i,
					'received' => self::cleanDate(is_string($received_date) ? $received_date : $received_value),
					'size'     => isset($sizes[$i]) ? $sizes[$i] : 0,
					'date'     => isset($headers['date']) && is_string($headers['date']) ? $headers['date'] : '',
					'from'     => is_array($from_header) ? self::joinEmails(array($from_header)) : '',
					'subject'  => isset($headers['subject']) && is_string($headers['subject']) ? $headers['subject'] : ''
				);
				if (isset($headers['message-id']) && is_string($headers['message-id'])) {
					$output[$i]['message_id'] = $headers['message-id'];
				}
				if (isset($headers['to']) && is_array($headers['to'])) {
					$output[$i]['to'] = self::joinEmails($headers['to']);
				}
				if (isset($headers['in-reply-to']) && is_string($headers['in-reply-to'])) {
					$output[$i]['in_reply_to'] = $headers['in-reply-to'];
				}
			}
		}

		return $output;
	}


	/**
	 * Reads responses from the server
	 *
	 * @param  integer|string $expect  The expected number of lines of response or a regex of the last line
	 * @return array<int, string>  The lines of response from the server
	 */
	private function read(mixed $expect = null): array
	{
		$connection = $this->connection;
		if (!is_resource($connection)) {
			throw new ProgrammerException('Unable to read data since the connection has already been closed');
		}

		$read     = array($connection);
		$write    = null;
		$except   = null;
		$response = array();

		// Fixes an issue with stream_select throwing a warning on Windows
		if (Core::checkOS('windows')) {
			$select = @stream_select($read, $write, $except, (int) $this->timeout);
		} else {
			$select = stream_select($read, $write, $except, (int) $this->timeout);
		}

		if ($select) {
			while (!feof($connection)) {
				$line = fgets($connection);
				if ($line === false) {
					break;
				}
				$line = substr($line, 0, -2);

				$response[] = $line;

				// Automatically stop at the termination octet or a bad response
				if ($this->type == 'pop3' && ($line == '.' || (count($response) == 1 && $response[0][0] == '-'))) {
					break;
				}

				if ($expect !== null) {
					$matched_number = is_int($expect) && sizeof($response) == $expect;
					$matched_regex  = is_string($expect) && preg_match($expect, $line);
					if ($matched_number || $matched_regex) {
						break;
					}
				}
			}
		}
		if (Core::getDebug($this->debug === true)) {
			Core::debug("Received:\n" . join("\r\n", $response), $this->debug === true);
		}

		if ($this->type == 'pop3') {
			// Remove the termination octet
			if ($response && end($response) == '.') {
				$response = array_slice($response, 0, -1);
			}
			// Remove byte-stuffing
			$lines = count($response);
			for ($i = 0; $i < $lines; $i++) {
				if (strlen($response[$i]) && $response[$i][0] == '.') {
					$response[$i] = substr($response[$i], 1);
				}
			}
		}

		return $response;
	}


	/**
	 * Sends commands to the IMAP or POP3 server
	 *
	 * @param  string  $command   The command to send
	 * @param  integer $expected  The number of lines or regex expected for a POP3 command
	 * @return array<int, string>  The response from the server
	 */
	private function write(string $command, ?int $expected = null): array
	{
		$connection = $this->connection;
		if (!is_resource($connection)) {
			throw new ProgrammerException('Unable to send data since the connection has already been closed');
		}

		$identifier = '';
		if ($this->type == 'imap') {
			$identifier = 'a' . str_pad((string) $this->command_num++, 4, '0', STR_PAD_LEFT);
			$command    = $identifier . ' ' . $command;
		}

		if (substr($command, -2) != "\r\n") {
			$command .= "\r\n";
		}

		if (Core::getDebug($this->debug === true)) {
			Core::debug("Sending:\n" . trim($command), $this->debug === true);
		}

		$res = fwrite($connection, $command);

		if ($res === false || $res === 0) {
			throw new ConnectivityException(
				'Unable to write data to %1$s server %2$s on port %3$s',
				strtoupper($this->type),
				$this->host,
				$this->port
			);
		}

		if ($this->type == 'imap') {
			return $this->read('#^' . $identifier . '#');
		} elseif ($this->type == 'pop3') {
			return $this->read($expected);
		}

		throw new ProgrammerException('An invalid mailbox type was specified, must be one of: imap, pop3');
	}
}

/**
 * Copyright (c) 2010-2012 Will Bond <will@flourishlib.com>
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
