<?php
/**
 * PHPMailer - PHP email creation and transport class.
 * PHP Version 5.5.
 *
 * @see https://github.com/PHPMailer/PHPMailer/ The PHPMailer GitHub project
 */

namespace PHPMailer\PHPMailer;

class PHPMailer
{
    const VERSION = '6.8.0';
    const CHARSET_ASCII = 'us-ascii';
    const CHARSET_ISO88591 = 'iso-8859-1';
    const CHARSET_UTF8 = 'utf-8';
    const CONTENT_TYPE_PLAINTEXT = 'text/plain';
    const CONTENT_TYPE_TEXT_CALENDAR = 'text/calendar';
    const CONTENT_TYPE_TEXT_HTML = 'text/html';
    const CONTENT_TYPE_MULTIPART_ALTERNATIVE = 'multipart/alternative';
    const CONTENT_TYPE_MULTIPART_MIXED = 'multipart/mixed';
    const CONTENT_TYPE_MULTIPART_RELATED = 'multipart/related';
    const ENCODING_7BIT = '7bit';
    const ENCODING_8BIT = '8bit';
    const ENCODING_BASE64 = 'base64';
    const ENCODING_BINARY = 'binary';
    const ENCODING_QUOTED_PRINTABLE = 'quoted-printable';
    const ENCRYPTION_STARTTLS = 'tls';
    const ENCRYPTION_SMTPS = 'ssl';
    const ICAL_METHOD_REQUEST = 'REQUEST';
    const ICAL_METHOD_PUBLISH = 'PUBLISH';
    const ICAL_METHOD_REPLY = 'REPLY';
    const ICAL_METHOD_ADD = 'ADD';
    const ICAL_METHOD_CANCEL = 'CANCEL';
    const ICAL_METHOD_REFRESH = 'REFRESH';
    const ICAL_METHOD_COUNTER = 'COUNTER';
    const ICAL_METHOD_DECLINECOUNTER = 'DECLINECOUNTER';

    public $Priority;
    public $CharSet = self::CHARSET_UTF8;
    public $ContentType = self::CONTENT_TYPE_PLAINTEXT;
    public $Encoding = self::ENCODING_8BIT;
    public $ErrorInfo = '';
    public $From = '';
    public $FromName = '';
    public $Sender = '';
    public $Subject = '';
    public $Body = '';
    public $AltBody = '';
    public $Ical = '';
    public $WordWrap = 0;
    public $Mailer = 'mail';
    public $Sendmail = '/usr/sbin/sendmail';
    public $UseSendmailOptions = true;
    public $ConfirmReadingTo = '';
    public $Hostname = '';
    public $MessageID = '';
    public $MessageDate = '';
    public $Host = 'localhost';
    public $Port = 25;
    public $Helo = '';
    public $SMTPSecure = '';
    public $SMTPAutoTLS = true;
    public $SMTPAuth = false;
    public $Username = '';
    public $Password = '';
    public $AuthType = '';
    public $SMTPOptions = [];
    public $Timeout = 300;
    public $SMTPDebug = 0;
    public $Debugoutput = 'echo';
    public $SMTPKeepAlive = false;
    public $SingleTo = false;
    public $XMailer = '';
    public $DKIM_selector = '';
    public $DKIM_identity = '';
    public $DKIM_passphrase = '';
    public $DKIM_domain = '';
    public $DKIM_copyHeaderFields = true;
    public $DKIM_extraHeaders = [];
    public $DKIM_private = '';
    public $DKIM_private_string = '';
    public $action_function = '';
    public $AllowEmpty = false;
    protected $to = [];
    protected $cc = [];
    protected $bcc = [];
    protected $ReplyTo = [];
    protected $all_recipients = [];
    protected $RecipientsQueue = [];
    protected $ReplyToQueue = [];
    protected $attachment = [];
    protected $CustomHeader = [];
    protected $lastMessageID = '';
    protected $message_type = '';
    protected $boundary = [];
    protected $language = [];
    protected $error_count = 0;
    protected $sign_cert_file = '';
    protected $sign_key_file = '';
    protected $sign_extracerts_file = '';
    protected $sign_key_pass = '';
    protected $exceptions = false;
    protected $smtp;
    protected $uniqueid = '';

    const STOP_MESSAGE = 0;
    const STOP_CONTINUE = 1;
    const STOP_CRITICAL = 2;

    public function __construct($exceptions = null)
    {
        if (null !== $exceptions) {
            $this->exceptions = (bool) $exceptions;
        }
        $this->Debugoutput = 'error_log';
    }

    public function isSMTP()
    {
        $this->Mailer = 'smtp';
    }

    public function isMail()
    {
        $this->Mailer = 'mail';
    }

    public function isSendmail()
    {
        $this->Mailer = 'sendmail';
    }

    public function isQmail()
    {
        $this->Sendmail = '/var/qmail/bin/qmail-inject';
        $this->Mailer = 'qmail';
    }

    public function setFrom($address, $name = '', $auto = true)
    {
        $address = trim($address);
        $name = trim(preg_replace('/[\r\n]+/', '', $name));
        $pos = strrpos($address, '@');
        if ((!$pos) || ((!$this->has8bitChars(substr($address, ++$pos))) || !$this->idnSupported()) && !$this->validateAddress($address)) {
            $error_message = sprintf('%s (From): %s', $this->lang('invalid_address'), $address);
            $this->setError($error_message);
            $this->edebug($error_message);
            if ($this->exceptions) {
                throw new Exception($error_message);
            }
            return false;
        }
        $this->From = $address;
        $this->FromName = $name;
        if ($auto && empty($this->Sender)) {
            $this->Sender = $address;
        }
        return true;
    }

    public function addAddress($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('to', $address, $name);
    }

    public function addCC($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('cc', $address, $name);
    }

    public function addBCC($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('bcc', $address, $name);
    }

    public function addReplyTo($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('Reply-To', $address, $name);
    }

    protected function addOrEnqueueAnAddress($kind, $address, $name)
    {
        $address = trim($address);
        $name = trim(preg_replace('/[\r\n]+/', '', $name));
        $pos = strrpos($address, '@');
        if (false === $pos) {
            $error_message = sprintf('%s (%s): %s', $this->lang('invalid_address'), $kind, $address);
            $this->setError($error_message);
            $this->edebug($error_message);
            if ($this->exceptions) {
                throw new Exception($error_message);
            }
            return false;
        }
        if (array_key_exists(strtolower($address), $this->all_recipients)) {
            return false;
        }
        switch ($kind) {
            case 'to':
                $this->to[] = [$address, $name];
                break;
            case 'cc':
                $this->cc[] = [$address, $name];
                break;
            case 'bcc':
                $this->bcc[] = [$address, $name];
                break;
            case 'Reply-To':
                $this->ReplyTo[] = [$address, $name];
                break;
        }
        $this->all_recipients[strtolower($address)] = true;
        return true;
    }

    public function clearAddresses()
    {
        foreach ($this->to as $to) {
            unset($this->all_recipients[strtolower($to[0])]);
        }
        $this->to = [];
        $this->clearQueuedAddresses('to');
    }

    public function clearCCs()
    {
        foreach ($this->cc as $cc) {
            unset($this->all_recipients[strtolower($cc[0])]);
        }
        $this->cc = [];
        $this->clearQueuedAddresses('cc');
    }

    public function clearBCCs()
    {
        foreach ($this->bcc as $bcc) {
            unset($this->all_recipients[strtolower($bcc[0])]);
        }
        $this->bcc = [];
        $this->clearQueuedAddresses('bcc');
    }

    public function clearReplyTos()
    {
        $this->ReplyTo = [];
        $this->ReplyToQueue = [];
    }

    public function clearAllRecipients()
    {
        $this->to = [];
        $this->cc = [];
        $this->bcc = [];
        $this->all_recipients = [];
        $this->RecipientsQueue = [];
    }

    public function clearAttachments()
    {
        $this->attachment = [];
    }

    public function clearCustomHeaders()
    {
        $this->CustomHeader = [];
    }

    protected function clearQueuedAddresses($kind)
    {
        $this->RecipientsQueue = array_filter(
            $this->RecipientsQueue,
            static function ($params) use ($kind) {
                return $params[0] !== $kind;
            }
        );
    }

    public function send()
    {
        try {
            if (!$this->preSend()) {
                return false;
            }
            return $this->postSend();
        } catch (Exception $exc) {
            $this->setError($exc->getMessage());
            if ($this->exceptions) {
                throw $exc;
            }
            return false;
        }
    }

    public function preSend()
    {
        if (empty($this->Body) && empty($this->AltBody) && !$this->AllowEmpty) {
            throw new Exception($this->lang('empty_message'), self::STOP_CRITICAL);
        }

        if ('smtp' === $this->Mailer) {
            if (count($this->to) + count($this->cc) + count($this->bcc) < 1) {
                throw new Exception($this->lang('provide_address'), self::STOP_CRITICAL);
            }
        }

        $this->setMessageType();
        $header = $this->createHeader();
        $body = $this->createBody();

        if (empty($this->Body) && empty($this->AltBody)) {
            throw new Exception($this->lang('empty_message'), self::STOP_CRITICAL);
        }

        $this->MIMEHeader = $header;
        $this->MIMEBody = $body;

        return true;
    }

    public function postSend()
    {
        try {
            switch ($this->Mailer) {
                case 'sendmail':
                case 'qmail':
                    return $this->sendmailSend($this->MIMEHeader, $this->MIMEBody);
                case 'smtp':
                    return $this->smtpSend($this->MIMEHeader, $this->MIMEBody);
                case 'mail':
                    return $this->mailSend($this->MIMEHeader, $this->MIMEBody);
                default:
                    return $this->mailSend($this->MIMEHeader, $this->MIMEBody);
            }
        } catch (Exception $exc) {
            $this->setError($exc->getMessage());
            if ($this->exceptions) {
                throw $exc;
            }
            return false;
        }
    }

    protected function sendmailSend($header, $body)
    {
        $header = static::stripTrailingWSP($header) . static::$LE . static::$LE;
        $sendmailFmt = '%s -oi -f%s -t';
        if (empty($this->Sender)) {
            $sendmail = sprintf($sendmailFmt, escapeshellcmd($this->Sendmail), escapeshellarg($this->From));
        } else {
            $sendmail = sprintf($sendmailFmt, escapeshellcmd($this->Sendmail), escapeshellarg($this->Sender));
        }
        $mail = @popen($sendmail, 'w');
        if (!$mail) {
            throw new Exception($this->lang('execute') . $this->Sendmail, self::STOP_CRITICAL);
        }
        fwrite($mail, $header);
        fwrite($mail, $body);
        $result = pclose($mail);
        if (0 !== $result) {
            throw new Exception($this->lang('execute') . $this->Sendmail, self::STOP_CRITICAL);
        }
        return true;
    }

    protected function mailSend($header, $body)
    {
        $toArr = [];
        foreach ($this->to as $toaddr) {
            $toArr[] = $this->addrFormat($toaddr);
        }
        $to = implode(', ', $toArr);
        $subject = $this->encodeHeader($this->secureHeader($this->Subject));
        $header = static::stripTrailingWSP($header);

        $params = '';
        if (!empty($this->Sender) && static::validateAddress($this->Sender)) {
            $params = sprintf('-f%s', $this->Sender);
        }
        if (!empty($this->Sender) && static::validateAddress($this->Sender)) {
            $old_from = ini_get('sendmail_from');
            ini_set('sendmail_from', $this->Sender);
        }
        $result = @mail($to, $subject, $body, $header, $params);
        if (isset($old_from)) {
            ini_set('sendmail_from', $old_from);
        }
        if (!$result) {
            throw new Exception($this->lang('instantiate'), self::STOP_CRITICAL);
        }
        return true;
    }

    protected function smtpSend($header, $body)
    {
        $header = static::stripTrailingWSP($header) . static::$LE . static::$LE;
        $bad_rcpt = [];
        if (!$this->smtpConnect($this->SMTPOptions)) {
            throw new Exception($this->lang('smtp_connect_failed'), self::STOP_CRITICAL);
        }

        $smtp_from = $this->Sender === '' ? $this->From : $this->Sender;
        if (!$this->smtp->mail($smtp_from)) {
            $this->setError($this->lang('from_failed') . $smtp_from . ' : ' . implode(',', $this->smtp->getError()));
            throw new Exception($this->ErrorInfo, self::STOP_CRITICAL);
        }

        $callbacks = [];
        foreach ([$this->to, $this->cc, $this->bcc] as $togroup) {
            foreach ($togroup as $to) {
                if (!$this->smtp->recipient($to[0], $this->dsn)) {
                    $error = $this->smtp->getError();
                    $bad_rcpt[] = ['to' => $to[0], 'error' => $error['detail']];
                    $callbacks[] = ['issent' => false, 'to' => $to[0], 'name' => $to[1]];
                } else {
                    $callbacks[] = ['issent' => true, 'to' => $to[0], 'name' => $to[1]];
                }
            }
        }

        if (count($bad_rcpt) > 0 && count($bad_rcpt) === count($callbacks)) {
            throw new Exception($this->lang('recipients_failed'), self::STOP_CRITICAL);
        }

        if (!$this->smtp->data($header . $body)) {
            $this->setError($this->lang('data_not_accepted'));
            throw new Exception($this->ErrorInfo, self::STOP_CRITICAL);
        }

        foreach ($callbacks as $cb) {
            $this->doCallback($cb['issent'], [[$cb['to'], $cb['name']]], [], [], $this->Subject, $body, $this->From, []);
        }

        if ($this->SMTPKeepAlive) {
            $this->smtp->reset();
        } else {
            $this->smtp->quit();
            $this->smtp->close();
        }

        return true;
    }

    public function smtpConnect($options = null)
    {
        if (null === $this->smtp) {
            $this->smtp = new SMTP();
        }

        $this->smtp->do_debug = $this->SMTPDebug;
        $this->smtp->Debugoutput = $this->Debugoutput;
        $this->smtp->Timeout = $this->Timeout;
        $this->smtp->Timelimit = $this->Timeout;

        $hosts = explode(';', $this->Host);
        $lastexception = null;

        foreach ($hosts as $hostentry) {
            $hostinfo = [];
            if (!preg_match('/^(?:(ssl|tls):\/\/)?(.+?)(?::(\d+))?$/', trim($hostentry), $hostinfo)) {
                $this->edebug($this->lang('invalid_hostentry') . ' ' . trim($hostentry));
                continue;
            }
            $prefix = '';
            $secure = $this->SMTPSecure;
            $tls = (static::ENCRYPTION_STARTTLS === $this->SMTPSecure);
            if ('ssl' === $hostinfo[1] || ('' === $hostinfo[1] && static::ENCRYPTION_SMTPS === $this->SMTPSecure)) {
                $prefix = 'ssl://';
                $tls = false;
                $secure = static::ENCRYPTION_SMTPS;
            } elseif ('tls' === $hostinfo[1]) {
                $tls = true;
                $secure = static::ENCRYPTION_STARTTLS;
            }
            $sslext = defined('OPENSSL_ALGO_SHA256');
            if (static::ENCRYPTION_STARTTLS === $secure || static::ENCRYPTION_SMTPS === $secure) {
                if (!$sslext) {
                    throw new Exception($this->lang('extension_missing') . 'openssl', self::STOP_CRITICAL);
                }
            }
            $host = $hostinfo[2];
            $port = $this->Port;
            if (array_key_exists(3, $hostinfo) && is_numeric($hostinfo[3]) && $hostinfo[3] > 0 && $hostinfo[3] < 65536) {
                $port = (int) $hostinfo[3];
            }
            if ($this->smtp->connect($prefix . $host, $port, $this->Timeout, $options)) {
                try {
                    if ($this->Helo) {
                        $hello = $this->Helo;
                    } else {
                        $hello = $this->serverHostname();
                    }
                    $this->smtp->hello($hello);
                    $this->smtp->getServerExtList();
                    if ($tls) {
                        if (!$this->smtp->startTLS()) {
                            throw new Exception($this->lang('connect_host'));
                        }
                        $this->smtp->hello($hello);
                    }
                    if ($this->SMTPAuth && !$this->smtp->authenticate($this->Username, $this->Password, $this->AuthType)) {
                        throw new Exception($this->lang('authenticate'));
                    }
                    return true;
                } catch (Exception $exc) {
                    $lastexception = $exc;
                    $this->edebug($exc->getMessage());
                    $this->smtp->quit();
                }
            }
        }
        $this->smtp->close();
        if ($lastexception) {
            throw $lastexception;
        }
        throw new Exception($this->lang('connect_host'));
    }

    public function smtpClose()
    {
        if ((null !== $this->smtp) && $this->smtp->connected()) {
            $this->smtp->quit();
            $this->smtp->close();
        }
    }

    public function addAttachment($path, $name = '', $encoding = self::ENCODING_BASE64, $type = '', $disposition = 'attachment')
    {
        try {
            if (!is_file($path)) {
                throw new Exception($this->lang('file_access') . $path, self::STOP_CONTINUE);
            }
            $filename = basename($path);
            if ('' === $name) {
                $name = $filename;
            }
            if ('' === $type) {
                $type = static::filenameToType($filename);
            }
            $this->attachment[] = [
                0 => $path,
                1 => $filename,
                2 => $name,
                3 => $encoding,
                4 => $type,
                5 => false,
                6 => $disposition,
                7 => $name,
            ];
        } catch (Exception $exc) {
            $this->setError($exc->getMessage());
            if ($this->exceptions) {
                throw $exc;
            }
            return false;
        }
        return true;
    }

    public function addCustomHeader($name, $value = null)
    {
        if (null === $value && strpos($name, ':') !== false) {
            [$name, $value] = explode(':', $name, 2);
        }
        $name = trim($name);
        $value = trim($value);
        $this->CustomHeader[] = [$name, $value];
    }

    public function isHTML($isHtml = true)
    {
        if ($isHtml) {
            $this->ContentType = static::CONTENT_TYPE_TEXT_HTML;
        } else {
            $this->ContentType = static::CONTENT_TYPE_PLAINTEXT;
        }
    }

    protected function setMessageType()
    {
        $type = [];
        if ($this->alternativeExists()) {
            $type[] = 'alt';
        }
        if ($this->inlineImageExists()) {
            $type[] = 'inline';
        }
        if ($this->attachmentExists()) {
            $type[] = 'attach';
        }
        $this->message_type = implode('_', $type);
        if ('' === $this->message_type) {
            $this->message_type = 'plain';
        }
    }

    public function createHeader()
    {
        $result = '';
        $result .= $this->headerLine('Date', '' === $this->MessageDate ? static::rfcDate() : $this->MessageDate);
        if ('' === $this->MessageID) {
            $this->lastMessageID = sprintf('<%s@%s>', $this->uniqueid, $this->serverHostname());
        } else {
            $this->lastMessageID = $this->MessageID;
        }
        $result .= $this->headerLine('Message-ID', $this->lastMessageID);
        if (null !== $this->Priority) {
            $result .= $this->headerLine('X-Priority', $this->Priority);
        }
        if ('' === $this->XMailer) {
        } elseif ('0' === $this->XMailer || '0 ' === $this->XMailer) {
            $result .= $this->headerLine('X-Mailer', 'PHPMailer ' . self::VERSION);
        } else {
            $myXmailer = trim($this->XMailer);
            if ($myXmailer) {
                $result .= $this->headerLine('X-Mailer', $myXmailer);
            }
        }
        $result .= $this->addrAppend('From', [[$this->From, $this->FromName]]);
        foreach ($this->to as $toaddr) {
            $result .= $this->addrAppend('To', [$toaddr]);
        }
        foreach ($this->cc as $toaddr) {
            $result .= $this->addrAppend('Cc', [$toaddr]);
        }
        if (count($this->ReplyTo) > 0) {
            $result .= $this->addrAppend('Reply-To', $this->ReplyTo);
        }
        $result .= $this->headerLine('Subject', $this->encodeHeader($this->secureHeader($this->Subject)));
        if ('' !== $this->ConfirmReadingTo) {
            $result .= $this->headerLine('Disposition-Notification-To', '<' . $this->ConfirmReadingTo . '>');
        }
        foreach ($this->CustomHeader as $header) {
            $result .= $this->headerLine(trim($header[0]), $this->encodeHeader(trim($header[1])));
        }
        if (!$this->sign_key_file) {
            $result .= $this->headerLine('MIME-Version', '1.0');
            $result .= $this->getMailMIME();
        }
        return $result;
    }

    public function createBody()
    {
        $body = '';
        $this->uniqueid = $this->generateId();
        $this->setBoundaries();

        if ($this->sign_key_file) {
            $body .= $this->getMailMIME() . static::$LE;
        }

        switch ($this->message_type) {
            case 'inline':
                $body .= $this->getBoundary($this->boundary[1], $this->ContentType, '', $this->Encoding);
                $body .= $this->encodeString($this->Body, $this->Encoding);
                $body .= static::$LE;
                $body .= $this->attachAll('inline', $this->boundary[1]);
                break;
            case 'attach':
                $body .= $this->getBoundary($this->boundary[1], $this->ContentType, '', $this->Encoding);
                $body .= $this->encodeString($this->Body, $this->Encoding);
                $body .= static::$LE;
                $body .= $this->attachAll('attachment', $this->boundary[1]);
                break;
            case 'inline_attach':
                $body .= $this->textLine('--' . $this->boundary[1]);
                $body .= $this->headerLine('Content-Type', static::CONTENT_TYPE_MULTIPART_RELATED . ';');
                $body .= $this->textLine("\tboundary=\"" . $this->boundary[2] . '"');
                $body .= static::$LE;
                $body .= $this->getBoundary($this->boundary[2], $this->ContentType, '', $this->Encoding);
                $body .= $this->encodeString($this->Body, $this->Encoding);
                $body .= static::$LE;
                $body .= $this->attachAll('inline', $this->boundary[2]);
                $body .= static::$LE;
                $body .= $this->attachAll('attachment', $this->boundary[1]);
                break;
            case 'alt':
                $body .= $this->getBoundary($this->boundary[1], static::CONTENT_TYPE_PLAINTEXT, '', static::ENCODING_QUOTED_PRINTABLE);
                $body .= $this->encodeString($this->AltBody, static::ENCODING_QUOTED_PRINTABLE);
                $body .= static::$LE;
                $body .= $this->getBoundary($this->boundary[1], static::CONTENT_TYPE_TEXT_HTML, '', $this->Encoding);
                $body .= $this->encodeString($this->Body, $this->Encoding);
                $body .= static::$LE;
                if (!empty($this->Ical)) {
                    $body .= $this->getBoundary($this->boundary[1], static::CONTENT_TYPE_TEXT_CALENDAR . '; method=REQUEST', '', $this->Encoding);
                    $body .= $this->encodeString($this->Ical, $this->Encoding);
                    $body .= static::$LE;
                }
                $body .= $this->endBoundary($this->boundary[1]);
                break;
            case 'alt_inline':
                $body .= $this->getBoundary($this->boundary[1], static::CONTENT_TYPE_PLAINTEXT, '', static::ENCODING_QUOTED_PRINTABLE);
                $body .= $this->encodeString($this->AltBody, static::ENCODING_QUOTED_PRINTABLE);
                $body .= static::$LE;
                $body .= $this->textLine('--' . $this->boundary[1]);
                $body .= $this->headerLine('Content-Type', static::CONTENT_TYPE_MULTIPART_RELATED . ';');
                $body .= $this->textLine("\tboundary=\"" . $this->boundary[2] . '"');
                $body .= static::$LE;
                $body .= $this->getBoundary($this->boundary[2], static::CONTENT_TYPE_TEXT_HTML, '', $this->Encoding);
                $body .= $this->encodeString($this->Body, $this->Encoding);
                $body .= static::$LE;
                $body .= $this->attachAll('inline', $this->boundary[2]);
                $body .= static::$LE;
                $body .= $this->endBoundary($this->boundary[1]);
                break;
            case 'alt_attach':
                $body .= $this->textLine('--' . $this->boundary[1]);
                $body .= $this->headerLine('Content-Type', static::CONTENT_TYPE_MULTIPART_ALTERNATIVE . ';');
                $body .= $this->textLine("\tboundary=\"" . $this->boundary[2] . '"');
                $body .= static::$LE;
                $body .= $this->getBoundary($this->boundary[2], static::CONTENT_TYPE_PLAINTEXT, '', static::ENCODING_QUOTED_PRINTABLE);
                $body .= $this->encodeString($this->AltBody, static::ENCODING_QUOTED_PRINTABLE);
                $body .= static::$LE;
                $body .= $this->getBoundary($this->boundary[2], static::CONTENT_TYPE_TEXT_HTML, '', $this->Encoding);
                $body .= $this->encodeString($this->Body, $this->Encoding);
                $body .= static::$LE;
                $body .= $this->endBoundary($this->boundary[2]);
                $body .= static::$LE;
                $body .= $this->attachAll('attachment', $this->boundary[1]);
                break;
            case 'alt_inline_attach':
                $body .= $this->textLine('--' . $this->boundary[1]);
                $body .= $this->headerLine('Content-Type', static::CONTENT_TYPE_MULTIPART_ALTERNATIVE . ';');
                $body .= $this->textLine("\tboundary=\"" . $this->boundary[2] . '"');
                $body .= static::$LE;
                $body .= $this->getBoundary($this->boundary[2], static::CONTENT_TYPE_PLAINTEXT, '', static::ENCODING_QUOTED_PRINTABLE);
                $body .= $this->encodeString($this->AltBody, static::ENCODING_QUOTED_PRINTABLE);
                $body .= static::$LE;
                $body .= $this->textLine('--' . $this->boundary[2]);
                $body .= $this->headerLine('Content-Type', static::CONTENT_TYPE_MULTIPART_RELATED . ';');
                $body .= $this->textLine("\tboundary=\"" . $this->boundary[3] . '"');
                $body .= static::$LE;
                $body .= $this->getBoundary($this->boundary[3], static::CONTENT_TYPE_TEXT_HTML, '', $this->Encoding);
                $body .= $this->encodeString($this->Body, $this->Encoding);
                $body .= static::$LE;
                $body .= $this->attachAll('inline', $this->boundary[3]);
                $body .= static::$LE;
                $body .= $this->endBoundary($this->boundary[2]);
                $body .= static::$LE;
                $body .= $this->attachAll('attachment', $this->boundary[1]);
                break;
            default:
                $body .= $this->encodeString($this->Body, $this->Encoding);
                break;
        }
        return $body;
    }

    protected function setBoundaries()
    {
        $this->boundary[1] = 'b1_' . $this->uniqueid;
        $this->boundary[2] = 'b2_' . $this->uniqueid;
        $this->boundary[3] = 'b3_' . $this->uniqueid;
    }

    protected function generateId()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', random_int(0, 65535), random_int(0, 65535), random_int(0, 65535), random_int(16384, 20479), random_int(32768, 49151), random_int(0, 65535), random_int(0, 65535), random_int(0, 65535));
    }

    protected function headerLine($name, $value)
    {
        return $name . ': ' . $value . static::$LE;
    }

    protected function textLine($value)
    {
        return $value . static::$LE;
    }

    protected function addrFormat($addr)
    {
        if (empty($addr[1])) {
            return $this->secureHeader($addr[0]);
        }
        return $this->encodeHeader($this->secureHeader($addr[1]), 'phrase') . ' <' . $this->secureHeader($addr[0]) . '>';
    }

    protected function addrAppend($type, $addr)
    {
        $addresses = [];
        foreach ($addr as $address) {
            $addresses[] = $this->addrFormat($address);
        }
        return $type . ': ' . implode(', ', $addresses) . static::$LE;
    }

    protected function getMailMIME()
    {
        $result = '';
        $ismultipart = true;
        switch ($this->message_type) {
            case 'inline':
                $result .= $this->headerLine('Content-Type', static::CONTENT_TYPE_MULTIPART_RELATED . ';');
                $result .= $this->textLine("\tboundary=\"" . $this->boundary[1] . '"');
                break;
            case 'attach':
            case 'inline_attach':
            case 'alt_attach':
            case 'alt_inline_attach':
                $result .= $this->headerLine('Content-Type', static::CONTENT_TYPE_MULTIPART_MIXED . ';');
                $result .= $this->textLine("\tboundary=\"" . $this->boundary[1] . '"');
                break;
            case 'alt':
            case 'alt_inline':
                $result .= $this->headerLine('Content-Type', static::CONTENT_TYPE_MULTIPART_ALTERNATIVE . ';');
                $result .= $this->textLine("\tboundary=\"" . $this->boundary[1] . '"');
                break;
            default:
                $result .= $this->textLine('Content-Type: ' . $this->ContentType . '; charset=' . $this->CharSet);
                $ismultipart = false;
                break;
        }
        if (!$ismultipart) {
            $result .= $this->headerLine('Content-Transfer-Encoding', $this->Encoding);
        }
        return $result;
    }

    protected function getBoundary($boundary, $contentType, $charSet, $encoding)
    {
        $result = '';
        if ('' === $charSet) {
            $charSet = $this->CharSet;
        }
        $result .= $this->textLine('--' . $boundary);
        $result .= sprintf('Content-Type: %s; charset=%s', $contentType, $charSet);
        $result .= static::$LE;
        $result .= $this->headerLine('Content-Transfer-Encoding', $encoding);
        $result .= static::$LE;
        return $result;
    }

    protected function endBoundary($boundary)
    {
        return static::$LE . '--' . $boundary . '--' . static::$LE;
    }

    public function encodeString($str, $encoding = self::ENCODING_BASE64)
    {
        $encoded = '';
        switch (strtolower($encoding)) {
            case static::ENCODING_BASE64:
                $encoded = chunk_split(base64_encode($str), 76, static::$LE);
                break;
            case static::ENCODING_7BIT:
            case static::ENCODING_8BIT:
                $encoded = static::normalizeBreaks($str);
                if (substr($encoded, -(strlen(static::$LE))) !== static::$LE) {
                    $encoded .= static::$LE;
                }
                break;
            case static::ENCODING_BINARY:
                $encoded = $str;
                break;
            case static::ENCODING_QUOTED_PRINTABLE:
                $encoded = $this->encodeQP($str);
                break;
            default:
                $this->setError($this->lang('encoding') . $encoding);
                if ($this->exceptions) {
                    throw new Exception($this->lang('encoding') . $encoding);
                }
                break;
        }
        return $encoded;
    }

    public function encodeQP($string)
    {
        return static::normalizeBreaks(quoted_printable_encode($string));
    }

    public function encodeHeader($str, $position = 'text')
    {
        $matchcount = 0;
        switch (strtolower($position)) {
            case 'phrase':
                if (!preg_match('/[\200-\377]/', $str)) {
                    $encoded = addcslashes($str, "\0..\37\177\\\"");
                    if (($str === $encoded) && !preg_match('/[^A-Za-z0-9!#$%&\'*+\/=?^_`{|}~ -]/', $str)) {
                        return $encoded;
                    }
                    return "\"$encoded\"";
                }
                $matchcount = preg_match_all('/[^\040\041\043-\133\135-\176]/', $str, $matches);
                break;
            case 'comment':
                $matchcount = preg_match_all('/[()"]/', $str, $matches);
            case 'text':
            default:
                $matchcount += preg_match_all('/[\000-\010\013\014\016-\037\177-\377]/', $str, $matches);
                break;
        }

        if (0 === $matchcount) {
            return $str;
        }

        $maxlen = 75 - 7 - strlen($this->CharSet);
        if ($matchcount > strlen($str) / 3) {
            $encoding = 'B';
            $encoded = $this->base64EncodeWrapMB($str, "\n");
        } else {
            $encoding = 'Q';
            $encoded = $this->encodeQ($str, $position);
            $encoded = $this->wrapText($encoded, $maxlen, true);
            $encoded = str_replace('=' . static::$LE, "\n", trim($encoded));
        }

        $encoded = preg_replace('/^(.*)$/m', ' =?' . $this->CharSet . "?$encoding?\\1?=", $encoded);
        return trim(str_replace("\n", static::$LE, $encoded));
    }

    public function encodeQ($str, $position = 'text')
    {
        $pattern = '';
        switch (strtolower($position)) {
            case 'phrase':
                $pattern = '^A-Za-z0-9!*+\/ -';
                break;
            case 'comment':
                $pattern = '\(\)"';
            case 'text':
            default:
                $pattern = '\000-\011\013\014\016-\037\075\077\137\177-\377' . $pattern;
                break;
        }
        $matches = [];
        if (preg_match_all("/[{$pattern}]/", $str, $matches)) {
            foreach (array_unique($matches[0]) as $char) {
                $str = str_replace($char, '=' . sprintf('%02X', ord($char)), $str);
            }
        }
        return str_replace(' ', '_', $str);
    }

    public function base64EncodeWrapMB($str, $linebreak = null)
    {
        $start = '=?' . $this->CharSet . '?B?';
        $end = '?=';
        $encoded = '';
        if (null === $linebreak) {
            $linebreak = static::$LE;
        }
        $mb_length = mb_strlen($str, $this->CharSet);
        $length = 75 - strlen($start) - strlen($end);
        $ratio = $mb_length / strlen($str);
        $avgLength = floor($length * $ratio * .75);

        $offset = 0;
        for ($i = 0; $i < $mb_length; $i += $offset) {
            $lookBack = 0;
            do {
                $offset = $avgLength - $lookBack;
                $chunk = mb_substr($str, $i, $offset, $this->CharSet);
                $chunk = base64_encode($chunk);
                ++$lookBack;
            } while (strlen($chunk) > $length);
            $encoded .= $chunk . $linebreak;
        }
        return substr($encoded, 0, -strlen($linebreak));
    }

    protected function attachAll($disposition, $boundary)
    {
        $mime = [];
        $cidUniq = [];
        $incl = [];
        foreach ($this->attachment as $attachment) {
            if ($attachment[6] === $disposition) {
                $string = '';
                $path = '';
                $bString = $attachment[5];
                if ($bString) {
                    $string = $attachment[0];
                } else {
                    $path = $attachment[0];
                }
                $inclhash = hash('sha256', serialize($attachment));
                if (in_array($inclhash, $incl, true)) {
                    continue;
                }
                $incl[] = $inclhash;
                $name = $attachment[2];
                $encoding = $attachment[3];
                $type = $attachment[4];
                $cid = $attachment[7];
                if ('inline' === $disposition && array_key_exists($cid, $cidUniq)) {
                    continue;
                }
                $cidUniq[$cid] = true;
                $mime[] = sprintf('--%s%s', $boundary, static::$LE);
                if (preg_match('/[ \r\n]/', $name)) {
                    $mime[] = sprintf('Content-Type: %s; name="%s"%s', $type, $this->encodeHeader($this->secureHeader($name), 'text'), static::$LE);
                } else {
                    $mime[] = sprintf('Content-Type: %s; name=%s%s', $type, $this->encodeHeader($this->secureHeader($name), 'text'), static::$LE);
                }
                $mime[] = sprintf('Content-Transfer-Encoding: %s%s', $encoding, static::$LE);
                if ('' !== $cid) {
                    $mime[] = sprintf('Content-ID: <%s>%s', $cid, static::$LE);
                }
                if ('inline' === $disposition) {
                    $mime[] = sprintf('Content-Disposition: %s; filename=%s%s', $disposition, $this->encodeHeader($this->secureHeader($name), 'text'), static::$LE . static::$LE);
                } else {
                    if (preg_match('/[ \r\n]/', $name)) {
                        $mime[] = sprintf('Content-Disposition: %s; filename="%s"%s', $disposition, $this->encodeHeader($this->secureHeader($name), 'text'), static::$LE . static::$LE);
                    } else {
                        $mime[] = sprintf('Content-Disposition: %s; filename=%s%s', $disposition, $this->encodeHeader($this->secureHeader($name), 'text'), static::$LE . static::$LE);
                    }
                }
                if ($bString) {
                    $mime[] = $this->encodeString($string, $encoding);
                } else {
                    $mime[] = $this->encodeFile($path, $encoding);
                }
                $mime[] = static::$LE;
            }
        }
        $mime[] = sprintf('--%s--%s', $boundary, static::$LE);
        return implode('', $mime);
    }

    protected function encodeFile($path, $encoding = self::ENCODING_BASE64)
    {
        if (!static::fileIsAccessible($path)) {
            throw new Exception($this->lang('file_open') . $path, self::STOP_CONTINUE);
        }
        $file_buffer = file_get_contents($path);
        if (false === $file_buffer) {
            throw new Exception($this->lang('file_open') . $path, self::STOP_CONTINUE);
        }
        return $this->encodeString($file_buffer, $encoding);
    }

    protected function wrapText($message, $length, $qp_mode = false)
    {
        if ($qp_mode) {
            $soft_break = sprintf(' =%s', static::$LE);
        } else {
            $soft_break = static::$LE;
        }
        $is_utf8 = 'utf-8' === strtolower($this->CharSet);
        $lelen = strlen(static::$LE);
        $crlflen = strlen(static::$LE);
        $message = static::normalizeBreaks($message);
        if (substr($message, -$lelen) === static::$LE) {
            $message = substr($message, 0, -$lelen);
        }
        $lines = explode(static::$LE, $message);
        $message = '';
        foreach ($lines as $line) {
            $words = explode(' ', $line);
            $buf = '';
            $firstword = true;
            foreach ($words as $word) {
                if ($qp_mode && (strlen($word) > $length)) {
                    $space_left = $length - strlen($buf) - $crlflen;
                    if (!$firstword) {
                        if ($space_left > 20) {
                            $len = $space_left;
                            if ($is_utf8) {
                                $len = $this->utf8CharBoundary($word, $len);
                            } elseif ('=' === substr($word, $len - 1, 1)) {
                                --$len;
                            } elseif ('=' === substr($word, $len - 2, 1)) {
                                $len -= 2;
                            }
                            $part = substr($word, 0, $len);
                            $word = substr($word, $len);
                            $buf .= ' ' . $part;
                            $message .= $buf . sprintf('=%s', static::$LE);
                        } else {
                            $message .= $buf . $soft_break;
                        }
                        $buf = '';
                    }
                    while ($word !== '') {
                        if ($length <= 0) {
                            break;
                        }
                        $len = $length;
                        if ($is_utf8) {
                            $len = $this->utf8CharBoundary($word, $len);
                        } elseif ('=' === substr($word, $len - 1, 1)) {
                            --$len;
                        } elseif ('=' === substr($word, $len - 2, 1)) {
                            $len -= 2;
                        }
                        $part = substr($word, 0, $len);
                        $word = (string) substr($word, $len);
                        if ($word !== '') {
                            $message .= $part . sprintf('=%s', static::$LE);
                        } else {
                            $buf = $part;
                        }
                    }
                } else {
                    $buf_o = $buf;
                    if (!$firstword) {
                        $buf .= ' ';
                    }
                    $buf .= $word;
                    if ('' !== $buf_o && strlen($buf) > $length) {
                        $message .= $buf_o . $soft_break;
                        $buf = $word;
                    }
                }
                $firstword = false;
            }
            $message .= $buf . static::$LE;
        }
        return $message;
    }

    protected function utf8CharBoundary($encodedText, $maxLength)
    {
        $foundSplitPos = false;
        $lookBack = 3;
        while (!$foundSplitPos) {
            $lastChunk = substr($encodedText, $maxLength - $lookBack, $lookBack);
            $encodedCharPos = strpos($lastChunk, '=');
            if (false !== $encodedCharPos) {
                $hex = substr($encodedText, $maxLength - $lookBack + $encodedCharPos + 1, 2);
                $dec = hexdec($hex);
                if ($dec < 128) {
                    if ($encodedCharPos > 0) {
                        $maxLength -= $lookBack - $encodedCharPos;
                    }
                    $foundSplitPos = true;
                } elseif ($dec >= 192) {
                    $maxLength -= $lookBack - $encodedCharPos;
                    $foundSplitPos = true;
                } elseif ($dec < 192) {
                    --$lookBack;
                }
            } else {
                $foundSplitPos = true;
            }
        }
        return $maxLength;
    }

    public function secureHeader($str)
    {
        return trim(str_replace(["\r", "\n"], '', $str));
    }

    public static function normalizeBreaks($text, $breaktype = null)
    {
        if (null === $breaktype) {
            $breaktype = static::$LE;
        }
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        if ("\n" !== $breaktype) {
            $text = str_replace("\n", $breaktype, $text);
        }
        return $text;
    }

    public static function stripTrailingWSP($text)
    {
        return rtrim($text, " \r\n\t");
    }

    public function setLanguage($langcode = 'en', $lang_path = '')
    {
        $this->language = [
            'authenticate' => 'SMTP Error: Could not authenticate.',
            'connect_host' => 'SMTP Error: Could not connect to SMTP host.',
            'data_not_accepted' => 'SMTP Error: data not accepted.',
            'empty_message' => 'Message body empty',
            'encoding' => 'Unknown encoding: ',
            'execute' => 'Could not execute: ',
            'file_access' => 'Could not access file: ',
            'file_open' => 'File Error: Could not open file: ',
            'from_failed' => 'The following From address failed: ',
            'instantiate' => 'Could not instantiate mail function.',
            'invalid_address' => 'Invalid address: ',
            'invalid_hostentry' => 'Invalid hostentry: ',
            'mailer_not_supported' => ' mailer is not supported.',
            'provide_address' => 'You must provide at least one recipient email address.',
            'recipients_failed' => 'SMTP Error: The following recipients failed: ',
            'smtp_connect_failed' => 'SMTP connect() failed.',
            'signing' => 'Signing Error: ',
            'smtp_error' => 'SMTP server error: ',
            'variable_set' => 'Cannot set or reset variable: ',
            'extension_missing' => 'Extension missing: ',
        ];
        return true;
    }

    protected function lang($key)
    {
        if (count($this->language) < 1) {
            $this->setLanguage();
        }
        if (array_key_exists($key, $this->language)) {
            if ('smtp_connect_failed' === $key) {
                return $this->language[$key];
            }
            return $this->language[$key];
        }
        return $key;
    }

    public function getLastMessageID()
    {
        return $this->lastMessageID;
    }

    protected function setError($msg)
    {
        ++$this->error_count;
        $this->ErrorInfo = $msg;
    }

    protected function edebug($str)
    {
        if ($this->SMTPDebug <= 0) {
            return;
        }
        if ($this->Debugoutput instanceof \Psr\Log\LoggerInterface) {
            $this->Debugoutput->debug($str);
            return;
        }
        if (is_callable($this->Debugoutput) && !in_array($this->Debugoutput, ['error_log', 'html', 'echo'])) {
            call_user_func($this->Debugoutput, $str, $this->SMTPDebug);
            return;
        }
        switch ($this->Debugoutput) {
            case 'error_log':
                error_log($str);
                break;
            case 'html':
                echo htmlentities(preg_replace('/[\r\n]+/', '', $str), ENT_QUOTES, 'UTF-8'), "<br>\n";
                break;
            case 'echo':
            default:
                $str = preg_replace('/\r\n|\r/', "\n", $str);
                echo gmdate('Y-m-d H:i:s'), "\t", trim(str_replace("\n", "\n\t", trim($str))), "\n";
        }
    }

    protected function serverHostname()
    {
        $result = '';
        if (!empty($this->Hostname)) {
            $result = $this->Hostname;
        } elseif (isset($_SERVER) && array_key_exists('SERVER_NAME', $_SERVER)) {
            $result = $_SERVER['SERVER_NAME'];
        } elseif (function_exists('gethostname') && gethostname() !== false) {
            $result = gethostname();
        } elseif (php_uname('n') !== false) {
            $result = php_uname('n');
        }
        if (!static::isValidHost($result)) {
            $result = 'localhost.localdomain';
        }
        return $result;
    }

    protected static function isValidHost($host)
    {
        if (empty($host) || !is_string($host) || strlen($host) > 256 || !preg_match('/^([a-zA-Z\d.-]*|\[[a-fA-F\d:]+])$/', $host)) {
            return false;
        }
        if (strlen($host) > 2 && substr($host, 0, 1) === '[' && substr($host, -1, 1) === ']') {
            return filter_var(substr($host, 1, -1), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        }
        if (is_numeric(str_replace('.', '', $host))) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        }
        return filter_var('http://' . $host, FILTER_VALIDATE_URL) !== false;
    }

    public static function validateAddress($address, $patternselect = null)
    {
        if (null === $patternselect) {
            $patternselect = 'php';
        }
        if (is_callable($patternselect)) {
            return call_user_func($patternselect, $address);
        }
        if (false !== strpos($address, "\n") || false !== strpos($address, "\r")) {
            return false;
        }
        switch ($patternselect) {
            case 'pcre':
            case 'pcre8':
                return (bool) preg_match('/^(?!(?>(?1)"?(?>\\\[ -~]|[^"])}*"?(?1)}@)(?!(?:(?>\x0D\x0A)?[\t ])}+(?!(?>\x0D\x0A)?[\t ]))(?>(?>(?>\x0D\x0A)?[\t ])|(?>[\t ]*\x0D\x0A)?[\t ]+)?(?=[!-~&&[^"]&&[^\\(){}]&&[^\\]])|(?>[\t ]*\x0D\x0A)?[\t ]+)?(?>[#-\'*+\/-9=?A-Z^-~-]+(?>\.(?>[#-\'*+\/-9=?A-Z^-~-]+))*|"(?>(?>[\x01-\x08\x0B\x0C\x0E-!#-\[\]-\x7F]|\\\[\x00-\xFF]))*")(?:@(?!(?>(?1)"?(?>\\\[ -~]|[^"])}*"?(?1)}@)(?=(?>[#-\'*+\/-9=?A-Z^-~-]+(?>\.(?>[#-\'*+\/-9=?A-Z^-~-]+))*)?(?:@)?)|(?>[\x01-\x08\x0B\x0C\x0E-!#-\[\]-\x7F]|\\\[\x00-\xFF]))*")?)?(?:(?>(?>(?>(?>\x0D\x0A)?[\t ])+|(?>[\t ]*\x0D\x0A)?[\t ]+)?)|(?>[\t ]*\x0D\x0A)?[\t ]+)?)?$/', $address);
            case 'html5':
                return (bool) preg_match("/^[a-zA-Z0-9.!#$%&'*+\\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/sD", $address);
            case 'php':
            default:
                return filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
        }
    }

    public function has8bitChars($text)
    {
        return (bool) preg_match('/[\x80-\xFF]/', $text);
    }

    protected function idnSupported()
    {
        return function_exists('idn_to_ascii') && function_exists('mb_convert_encoding');
    }

    public function alternativeExists()
    {
        return !empty($this->AltBody);
    }

    public function inlineImageExists()
    {
        foreach ($this->attachment as $attachment) {
            if ('inline' === $attachment[6]) {
                return true;
            }
        }
        return false;
    }

    public function attachmentExists()
    {
        foreach ($this->attachment as $attachment) {
            if ('attachment' === $attachment[6]) {
                return true;
            }
        }
        return false;
    }

    public static function rfcDate()
    {
        date_default_timezone_set(@date_default_timezone_get());
        return date('D, j M Y H:i:s O');
    }

    public static function fileIsAccessible($path)
    {
        if (!static::isPermittedPath($path)) {
            return false;
        }
        $readable = is_file($path);
        if ($readable) {
            $readable = is_readable($path);
        }
        return $readable;
    }

    public static function isPermittedPath($path)
    {
        if ('phar://' === substr($path, 0, 7) || '' === $path) {
            return false;
        }
        return true;
    }

    public static function filenameToType($filename)
    {
        $qpos = strpos($filename, '?');
        if (false !== $qpos) {
            $filename = substr($filename, 0, $qpos);
        }
        $ext = static::mb_pathinfo($filename, PATHINFO_EXTENSION);
        return static::_mime_types($ext);
    }

    public static function mb_pathinfo($path, $options = null)
    {
        $ret = ['dirname' => '', 'basename' => '', 'extension' => '', 'filename' => ''];
        $pathinfo = [];
        if (preg_match('#^(.*?)[\\\\/]*(([^/\\\\]*?)(\.([^.\\\\/]+?)|))[\\\\/.]*$#m', $path, $pathinfo)) {
            if (array_key_exists(1, $pathinfo)) {
                $ret['dirname'] = $pathinfo[1];
            }
            if (array_key_exists(2, $pathinfo)) {
                $ret['basename'] = $pathinfo[2];
            }
            if (array_key_exists(5, $pathinfo)) {
                $ret['extension'] = $pathinfo[5];
            }
            if (array_key_exists(3, $pathinfo)) {
                $ret['filename'] = $pathinfo[3];
            }
        }
        switch ($options) {
            case PATHINFO_DIRNAME:
            case 'dirname':
                return $ret['dirname'];
            case PATHINFO_BASENAME:
            case 'basename':
                return $ret['basename'];
            case PATHINFO_EXTENSION:
            case 'extension':
                return $ret['extension'];
            case PATHINFO_FILENAME:
            case 'filename':
                return $ret['filename'];
            default:
                return $ret;
        }
    }

    public static function _mime_types($ext = '')
    {
        $mimes = [
            'csv' => 'text/csv',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'gif' => 'image/gif',
            'gz' => 'application/gzip',
            'html' => 'text/html',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'json' => 'application/json',
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'tar' => 'application/x-tar',
            'txt' => 'text/plain',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xml' => 'application/xml',
            'zip' => 'application/zip',
        ];
        $ext = strtolower($ext);
        if (array_key_exists($ext, $mimes)) {
            return $mimes[$ext];
        }
        return 'application/octet-stream';
    }

    protected function doCallback($issent, $to, $cc, $bcc, $subject, $body, $from, $extra)
    {
        if (!empty($this->action_function) && is_callable($this->action_function)) {
            call_user_func($this->action_function, $issent, $to, $cc, $bcc, $subject, $body, $from, $extra);
        }
    }

    public static $LE = "\r\n";
    protected $MIMEHeader = '';
    protected $MIMEBody = '';
    public $dsn = '';
}
