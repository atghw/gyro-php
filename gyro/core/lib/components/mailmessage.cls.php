<?php
define('MAIL_LF', "\n"); // "\r\n"

/**
 * Encapsulates an e-mail message, allowing attachments
 *
 * @note The class relies on the PEAR classes Mail and Mail_Mime
 *
 * @author Gerd Riesselmann
 * @ingroup Lib
 */
class MailMessage {
	/** Plain text mime content type */
	const MIME_TEXT_PLAIN = 'text/plain; charset="%charset"';
	/** HTML mime content type */
	const MIME_HTML = 'text/html; charset="%charset"';

	/**
	 * Receipient
	 *
	 * @var String
	 */
	protected $to = '';

	/**
	 * Sender
	 *
	 * @private String
	 */
	protected $from = '';

	/**
	 * Subject
	 *
	 * @private String
	 */
	protected $subject = '';

	/**
	 * Message
	 */
	protected $message = '';
	/**
	 * Alternative message (e.g. plain text for HTML mails)
	 *
	 * Will be always treated as plain text
	 */
	protected $message_alt = '';

	/**
	 * CC
	 *
	 * @private String
	 */
	protected $cc = '';

	protected $content_type = '';

	/**
	 * Attachment (Filename)
	 *
	 * @vat MailAttachment[]
	 */
	protected $data_to_attach = array();

	/**
	 * Additional headers as associative array
	 */
	protected $additional_headers = array();

	/**
	 * constructor
	 */
	public function __construct($subject, $message, $to, $from = '', $content_type = '') {
		$this->subject = trim(Config::get_value(Config::MAIL_SUBJECT) . ' ' . $subject);
		$this->message = $message;
		$this->to = $to;
		$this->from = $from;
		if (empty($content_type)) {
			$this->content_type = self::MIME_TEXT_PLAIN;
		}
		else {
			$this->content_type = $content_type;
		}
	}

	/**
	 * Sends email
	 *
	 * @return Status
	 */
	public function send() {
		// Check for injection attack;
		$ret = $this->safety_validate_header();
		if ($ret->is_error()) {
			return $ret;
		}

		$headers = $this->additional_headers;
		$headers['From'] = empty($this->from) ? Config::get_value(Config::MAIL_SENDER, true) : $this->from;
		if ($this->cc != '') {
			$headers['Bcc'] = $this->cc;
		}

		$return_path = Config::get_value(Config::MAIL_RETURN_PATH);
		$additional_params = $return_path ? "-f$return_path" : '';

		$builder = $this->create_builder();
		$headers['MIME-Version'] = '1.0';
		$headers['Content-Type'] = $builder->get_mail_mime();
		$headers = array_merge($headers, $builder->get_additional_headers());
		$body = $builder->get_body();

		$ret->merge($this->do_send($this->to, $this->subject, $body, $headers, $additional_params));
		return $ret;
	}

	/**
	 * Allow to override the :mail function
	 *
	 * @return Status
	 */
	protected function do_send($to, $subject, $body, $headers, $additional_params = '') {
		$ret = new Status();
		$headers = $this->encode_headers($headers);
		$subject = ConverterFactory::encode($subject, ConverterFactory::MIMEHEADER);
		if (!mail($to, $subject, $body, Arr::implode("\n", $headers, ': '), $additional_params)) {
			$ret->append(tr('Could not send mail', 'core'));
		}

		return $ret;
	}

	protected function encode_headers($headers) {
		$ret = array();
		foreach ($headers as $name => $value) {
			$ret[$name] = ConverterFactory::encode($value, ConverterFactory::MIMEHEADER);
		}
		return $ret;
	}

	/**
	 * Return builder suited for config
	 *
	 * @return IMailMessageBuilder
	 */
	protected function create_builder() {
		$ret = false;
		Load::directories('lib/components/mailmessagebuilder');
		$msg_builder = ($this->message_alt)
			? new AlternativeMessageBuilder($this->message, $this->content_type, $this->message_alt)
			: new SingleMessageBuilder($this->message, $this->content_type);
		if (count($this->data_to_attach)) {
			$ret = new AttachmentsBuilder($msg_builder, $this->data_to_attach);
		}
		else {
			$ret = $msg_builder;
		}
		return $ret;
	}

	/**
	 * Append a file to attach
	 *
	 * @param string|MailAttachment $file_name_or_attachment with members data, mime_type, and optional name
	 * @param string $name Name of attachment
	 */
	public function add_attachment($file_name_or_attachment, $name = '') {
		if (!$file_name_or_attachment instanceof MailAttachment) {
			$file_name_or_attachment = MailAttachment::from_file($file_name_or_attachment, $name);
		} else {
			if ($name) {
				$file_name_or_attachment->change_name($name);
			}
		}
		$this->data_to_attach[] = $file_name_or_attachment;
	}

	/**
	 * Add header.
	 *
	 * @code
	 * add_header('Reply-To', 'somemail@example.com')
	 * @endcode
	 *
	 * @param string $name Name of header
	 * @param string $value Header value
	 */
	public function add_header($name, $value) {
		$this->additional_headers[$name] = $value;
	}

	/**
	 * Clears from, subject, cc and to data to avoid header injection
	 * http://www.anders.com/projects/sysadmin/formPostHijacking/
	 */
	public function preprocess_header() {
		$this->to = $this->safety_preprocess_header_field($this->to);
		$this->from = $this->safety_preprocess_header_field($this->from);
		$this->subject = $this->safety_preprocess_header_field($this->subject);
		$this->cc = $this->safety_preprocess_header_field($this->cc);
	}

	/**
	 * Set alternative message
	 */
	public function set_alt_message($msg) {
		$this->message_alt = $msg;
	}

	/**
	 * Clears header field to avoid injection
	 * http://www.anders.com/projects/sysadmin/formPostHijacking/
	 */
	protected function safety_preprocess_header_field($value) {
		$ret = str_replace("\r", '', $value);
		$ret = str_replace("\n", '', $ret);

		// Remove injected headers
		// From http://www.davidseah.com/archives/2005/09/01/wp-contact-form-spam-attack/
		$find = array("/bcc\:/i", "/Content\-Type\:/i", "/Mime\-Type\:/i", "/cc\:/i", "/to\:/i");
		$ret = preg_replace($find, '**bogus header removed**', $ret);

		return $ret;
	}

	/**
	 * Clears from, subject, cc and to data to avoid header injection
	 */
	protected function safety_validate_header() {
		$ret = new Status();
		$ret->merge($this->safety_check_header_field($this->to, tr('Recipient')));
		$ret->merge($this->safety_check_header_field($this->from, tr('Sender')));
		$ret->merge($this->safety_check_header_field($this->subject, tr('Subject')));
		$ret->merge($this->safety_check_header_field($this->cc, tr('CC')));
		$ret->merge($this->safety_check_header_field($this->content_type, tr('Content-Tye')));
		$ret->merge($this->safety_check_exploit_strings($this->message, tr('Message'), true));

		foreach($this->additional_headers as $key => $value) {
			$ret->merge($this->safety_check_header_field($key, tr('Additional Headers')));
			$ret->merge($this->safety_check_header_field($value, tr('Additional Headers')));
		}
		return $ret;
	}

	protected function safety_check_header_field(&$value, $type) {
		if (strpos($value, "\r") !== false || strpos($value, "\n") !== false) {
			return new Status(tr('%field: Line breaks are not allowed.', 'core', array('%field' => $type)));
		}

		return $this->safety_check_exploit_strings($value, $type, false);
	}

	protected function safety_check_exploit_strings(&$value, $type, $beginLineOnly = false) {
		$err = new Status();
		$find = array(
			$this->safety_prepare_exploit_string("bcc", $beginLineOnly),
			$this->safety_prepare_exploit_string("Content\-Type", $beginLineOnly),
			$this->safety_prepare_exploit_string("Mime\-Type", $beginLineOnly),
			$this->safety_prepare_exploit_string('cc', $beginLineOnly),
			$this->safety_prepare_exploit_string('to', $beginLineOnly)
		);
		$temp = preg_replace($find, '**!HEADERINJECTION!**', $value);
		if (strpos($temp, '**!HEADERINJECTION!**') !== false) {
			$err->append('%type: "To:", "Bcc:", "Subject:" and other reserved words are not allowed.', 'core', array('%type' => $type));
		}

		return $err;
	}

	protected function safety_prepare_exploit_string($val, $multiline) {
		if ($multiline)
			return "/^" . $val . "\:/im";
		else
			return "/" . $val . "\:/i";
	}
}

class MailAttachment {
	private $filename;
	private $name;
	private $data;
	private $mime_type;

	public function get_mime_type() {
		$ret = 'application/octet-stream';
		if (empty($this->mime_type)) {
			if ($this->filename && function_exists('mime_content_type')) {
				$ret = mime_content_type($this->filename);
			}
		} else {
			$ret = $this->mime_type;
		}

		return $ret;
	}

	public function get_name() {
		if ($this->name) {
			return $this->name;
		} else {
			$this->name = Common::create_token('attachment');
			return $this->name;
		}
	}

	public function change_name($name) {
		$this->name = $name;
	}

	public function get_data() {
		if ($this->filename) {
			return file_get_contents($this->filename);
		} else {
			return $this->data;
		}
	}

	public static function from_data($data, $mime_type, $name) {
		$ret = new MailAttachment();
		$ret->data = $data;
		$ret->mime_type = $mime_type;
		$ret->name = $name;
		return $ret;
	}

	public static function from_file($filename, $name = '') {
		$ret = new MailAttachment();
		$ret->filename = $filename;
		$ret->name = $name ? $name : $filename;
		return $ret;
	}

	public static function from_binary(DAOBinaries $binary) {
		$ret = new MailAttachment();
		$ret->data = $binary->get_data();
		$ret->name = $binary->name;
		$ret->mime_type = $binary->mimetype;
		return $ret;
	}
}