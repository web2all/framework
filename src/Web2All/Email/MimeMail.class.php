<?php
/**
 * @package Web2All_Email
 * @name Class Web2All_Email_MimeMail (v1.1 2005-03-04)
 *
 *  Original implementation by Sascha Schumann <sascha@schumann.cx>
 *  Modified by Tobias Ratschiller <tobias@dnet.it>:
 *      - General code clean-up
 *      - separate body- and from-property
 *      - killed some mostly un-necessary stuff
 *  Modified by Merijn van den Kroonenberg <merijn@e-factory.nl>:
 *      - code cleanup
 *  Modified by Merijn van den Kroonenberg <merijn@e-factory.nl>:
 *      - expanded and fixed, added quoted printable suppport
 *        and multipart alternative. Also possible to add
 *        Content-id part header (inline images) 2005-02-16
 *        encode_qp has been taken from Brent R. Matzelle's
 *        phpmailer - PHP email class
 *  Modified by Merijn van den Kroonenberg <merijn@e-factory.nl>:
 *      - 2005-03-04: fixed send() return value
 *  Modified by Hans Oostendorp <hans@web2all.nl>:
 *       - 2007-05-30: port to php5
 *
 *  Example:
 *    $mail = new Web2All_Email_MimeMail();
 *    $mail->from    = $mailFrom;
 *    $mail->headers = "Errors-To: $mailTo";
 *    $mail->to      = $mailTo;
 *    $mail->subject = $mailSubject;
 *
 *    if (file_exists($file_att)) {
 *      $fd = fopen ($file_att, "r");
 *      $file_content = fread ($fd, filesize ($file_att));
 *      fclose ($fd);
 *      $mail->add_attachment($file_content, $file_att_name);
 *    }
 *    $mail->add_alternative_part($mailHTML, "", "text/html", "quoted-printable");
 *    $mail->add_alternative_part($mailText, "", "text/plain", "quoted-printable");
 *    $mail->send();
 * 
 *  Content encodings of 'base64' and 'quoted-printable' are automatically executed on the
 *  content of parts. All other encodings you need to perform on the content in advance (yourself).
 *
 *  The order in which the parts are added is the inverted order in which they are
 *  placed in the mail. Note: text/plain must be the first part in the mail
 *  (so add it last).
 */
class Web2All_Email_MimeMail {
  const VERSION = 1.2;
  public $parts;
  public $to;
  public $from;
  public $headers;
  public $subject;
  public $body;
  
  /**
   * Line-Ending characters
   * 
   * This is used on message parts which are 'quoted-printable'. All line endings in
   * these message parts will be converted to LE.
   * 
   * Please note this whole class uses \n lineendings everywhere, as sendmail requires
   * linux lineendings on mails on its input. (because the message is piped through shell?)
   * Sendmail will then convert all \n lineendings to \r\n in order to conform to RFC.
   * 
   * @var string
   */
  public $LE;
  
  /**
   * Set this property if the subject is in a non ascii encoding
   *
   * @var string
   */
  public $subject_charset;
  
  public static $DebugLevel=0;

  /**
  *     void Web2All_Email_MimeMail()
  *     class constructor
  */
  public function __construct() {
    if(self::$DebugLevel >= Web2All_Manager_Main::DEBUGLEVEL_HIGH) {
      echo "&nbsp;&nbsp;&nbsp;&nbsp;[&nbsp;Start de Web2All_Email_MimeMail Class&nbsp;]<br />\n";
    }
    $this->parts = array();
    $this->altparts = array();
    $this->to =  "";
    $this->from =  "";
    $this->subject =  "";
    $this->body =  "";
    $this->headers =  "";
    $this->LE =  "\n";
  }

  /**
   * Returns version number of this class
   *
   * @return Double, version number
   */
  public function getVersion() {
    return (double)self::VERSION;
  }

  /**
  *     void add_attachment(string message, [string name], [string ctype], [string encode], [string charset], [string content-id])
  *     Add an attachment to the mail object
  *
  *     NOTE: be sure to encode all content types other than base64 and quoted
  *           printable yourself
  */
  public function add_attachment($message, $name =  "", $ctype =  "application/octet-stream", $encode =  "base64", $charset='', $contentid='') {
    $this->parts[] = array ( "ctype" => $ctype,
                             "message" => $message,
                             "encode" => $encode,
                             "name" => $name,
                             "charset" => $charset,
                             "contentid" => $contentid
                            );
  }

  /**
  *     void add_alternative_part(string message, [string name], [string ctype], [string encode], [string charset], [string content-id])
  *     Add an alternative part to the mail object
  *
  *     NOTE: be sure to encode all content types other than base64 and
  *           quoted-printable yourself
  */
  public function add_alternative_part($message, $name =  "", $ctype =  "plain/text", $encode =  "8bit", $charset='', $contentid='') {
    $this->altparts[] = array (
                             "ctype" => $ctype,
                             "message" => $message,
                             "encode" => $encode,
                             "name" => $name,
                             "charset" => $charset,
                             "contentid" => $contentid
                            );
  }

  /**
   *      void build_message(array part=
   *      Build message parts of an multipart mail
   *
   *      NOTE: base64 encoding messages will be encoded
   *            automaticly my the class. all others will not.
   */
  private function build_message($part) {
    $message = $part[ "message"];
    // #### only base64 encoding supported
    // #### other encodings you have to encode yourself
    if ($part[ "encode"] == "base64"){
      $message = chunk_split(base64_encode($message));
    }else if ($part[ "encode"] == "quoted-printable"){
      $message = $this->encode_qp($message);
    }
    return  "Content-Type: ".$part[ "ctype"].
            ($part[ "charset"]? "; charset = \"".$part[ "charset"]. "\"" :  "").
            ($part[ "name"]? "; name = \"".$part[ "name"]. "\"" :  "").
            (empty($part[ "encode"])? "" : "\nContent-Transfer-Encoding: ".$part[ "encode"] ).
            (empty($part[ "contentid"])? "" : "\nContent-ID: ".$part[ "contentid"] ).
            ((empty($part["contentid"])&&($part["name"]))? "\nContent-Disposition: attachment; filename=\"".$part["name"].'"' : '' ).
            "\n\n$message\n";
  }

  /**
   *      string build_multipart()
   *      Build a multipart mail
   */
  private function build_multipart() {
    $boundary =  "b".md5(uniqid(time()));

    $multipart =  "Content-Type: multipart/mixed; boundary = $boundary\n\nThis is a MIME encoded message.\n";

    if(count($this->altparts)>0){
      $multipart .=  "\n--$boundary\n".$this->build_alternative_multipart();
    }

    for($i = sizeof($this->parts)-1; $i >= 0; $i--)
    {
      $multipart .=  "\n--$boundary\n".$this->build_message($this->parts[$i]);
    }
    return $multipart.=  "\n--"."$boundary"."--";
  }

  /**
   *      string build_alternative_multipart()
   *      Build a build_alternative_multipart mail
   */
  private function build_alternative_multipart() {
    $boundary =  "ba".md5(uniqid(time()));

    $multipart =  "Content-Type: multipart/alternative; boundary = $boundary\n\n";

    for($i = sizeof($this->altparts)-1; $i >= 0; $i--)
    {
      $multipart .=  "\n--$boundary\n".$this->build_message($this->altparts[$i]);
    }
    return $multipart.=  "\n--"."$boundary"."--\n";
  }

  /**
   * buildRawMime()
   * 
   * This method is used by send(), but can also be called 
   * directly if you want to send email by other means and need the
   * raw mime message as a string.
   * 
   * @param boolean $addAllHeaders
   * @return string  the raw mime mail
   */
  public function buildRawMime($addAllHeaders=true) {
    $mime =  "";
    if (!empty($this->from))
      $mime .=  "From: ".$this->from. "\n";
    if (!empty($this->headers))
      $mime .= $this->headers. "\n";
    if($addAllHeaders){
      // add all headers, even the ones automatically added by the php mail function
      $mime .=  "To: ".$this->to. "\n";
      $subject = $this->subject;
      if ($this->subject_charset) {
        $subject=$this->encodeSubject($subject,$this->subject_charset);
      }
      $mime .=  "Subject: ".$subject. "\n";
    }

    if (!empty($this->body)){
      $this->add_attachment($this->body,  "",  "text/plain");
    }
    // lets build the simpliest message possible
    if (count($this->parts)==1 && count($this->altparts)==0) {
      // single part mail
      $mime .= $this->build_message($this->parts[0]);
    }elseif (count($this->parts)==0 && count($this->altparts)==1) {
      // single part mail
      $mime .= $this->build_message($this->altparts[0]);
    }elseif (count($this->parts)==0 && count($this->altparts)>0) {
      // multi part alternative mail (without attachments)
      $mime .= "MIME-Version: 1.0\n".$this->build_alternative_multipart();
    }else{
      // its a multipart mail with attachments
      $mime .= "MIME-Version: 1.0\n".$this->build_multipart();
    }
    return $mime;
  }
  
  /**
   *      void send()
   *      Send the mail (last class-function to be called)
   *
   *      NOTE: if ->body is set it will be base64 encoded
   *            do not use body in combination with other parts
   *            its just a lazy shortcut for simple mails
   */
  public function send() {
    $mime=$this->buildRawMime(false);
    
    // possibly convert the subject
    $subject = $this->subject;
    if ($this->subject_charset) {
      $subject=$this->encodeSubject($subject,$this->subject_charset);
    }
    
    // workaround on fix for PHP bug #68776
    // we have to split the headers from the body
    list($header_str, $body_str) = explode("\n\n",$mime,2);
    
    if (!empty($this->from)) {
      $extra_param = "-f". self::extractMailAddress($this->from);
      return mail($this->to, $subject, $body_str, $header_str, $extra_param);
    } else {
      return mail($this->to, $subject, $body_str, $header_str);
    }
  }
  
  /**
   * extract the name@domain part from a full e-mail address
   * (full name <name@domain.com> returns name@domain.com)
   * 
   * when unable to extract something between <> it will return 
   * the original $full_mail
   *
   * @param string $full_mail
   * @return string
   */
  public static function extractMailAddress($full_mail)
  {
    $matches=array();
    if(preg_match('/<([^>]+)>/',$full_mail,$matches)){
      return $matches[1];
    }
    return $full_mail;
  }
  

  /**
   * Changes every end of line from CR or LF to $this->LE.  Returns string.
   * @private
   * @returns string
   */
  private function fix_eol($str) {
    $str = str_replace("\r\n", "\n", $str);
    $str = str_replace("\r", "\n", $str);
    if($this->LE != "\n"){
      $str = str_replace("\n", $this->LE, $str);
    }
    return $str;
  }

  /**
   * Callback for preg_replace_callback replacing every high ascii, 
   * control and = characters
   * 
   * @param array $matches
   * @returns string
   */
  public function qp_replace_high_ascii($matches)
  {
    return '='.sprintf('%02X', ord($matches[1]));
  }

  /**
   * Callback for preg_replace_callback replacing every spaces 
   * and tabs when it's the last character on a line
   * 
   * @param array $matches
   * @returns string
   */
  public function qp_replace_end_whitespace($matches)
  {
    return '='.sprintf('%02X', ord($matches[1])).$this->LE;
  }

  /**
   * Encode string to quoted-printable.  Returns a string.
   * 
   * @returns string
   */
  public function encode_qp ($str) {
    $encoded = $this->fix_eol($str);
    if (substr($encoded, -2) != $this->LE)
        $encoded .= $this->LE;

    // Replace every high ascii, control and = characters
    $encoded = preg_replace_callback("/([\001-\010\013\014\016-\037\075\177-\377])/",
              array($this,'qp_replace_high_ascii'), $encoded);
    // Replace every spaces and tabs when it's the last character on a line
    $encoded = preg_replace_callback("/([\011\040])".$this->LE."/",
              array($this,'qp_replace_end_whitespace'), $encoded);

    // Maximum line length of 76 characters before CRLF (74 + space + '=')
    $encoded = $this->word_wrap($encoded, 74, true);

    return $encoded;
  }

  /**
   * Wraps message for use with mailers that do not
   * automatically perform wrapping and for quoted-printable.
   * Original written by philippe.  Returns string.
   * 
   * @returns string
   */
  public function word_wrap($message, $length, $qp_mode = false) {
    if ($qp_mode)
      $soft_break = sprintf(" =%s", $this->LE);
    else
      $soft_break = $this->LE;

    $message = $this->fix_eol($message);

    if (substr($message, -1) == $this->LE)
      $message = substr($message, 0, -1);

    $line = explode($this->LE, $message);
    $message = "";
    for ($i=0 ;$i < count($line); $i++)
    {
      $line_part = explode(" ", $line[$i]);
      $buf = "";
      for ($e = 0; $e<count($line_part); $e++)
      {
          $word = $line_part[$e];
          if ($qp_mode and (strlen($word) > $length))
          {
            $space_left = $length - strlen($buf) - 1;
            if ($e != 0)
            {
                if ($space_left > 20)
                {
                    $len = $space_left;
                    if (substr($word, $len - 1, 1) == "=")
                      $len--;
                    elseif (substr($word, $len - 2, 1) == "=")
                      $len -= 2;
                    $part = substr($word, 0, $len);
                    $word = substr($word, $len);
                    $buf .= " " . $part;
                    $message .= $buf . sprintf("=%s", $this->LE);
                }
                else
                {
                    $message .= $buf . $soft_break;
                }
                $buf = "";
            }
            while (strlen($word) > 0)
            {
                $len = $length;
                if (substr($word, $len - 1, 1) == "=")
                    $len--;
                elseif (substr($word, $len - 2, 1) == "=")
                    $len -= 2;
                $part = substr($word, 0, $len);
                $word = substr($word, $len);

                if (strlen($word) > 0)
                    $message .= $part . sprintf("=%s", $this->LE);
                else
                    $buf = $part;
            }
          }
          else
          {
            $buf_o = $buf;
            if ($e == 0)
                $buf .= $word;
            else
                $buf .= " " . $word;
            if (strlen($buf) > $length and $buf_o != "")
            {
                $message .= $buf_o . $soft_break;
                $buf = $word;
            }
          }
      }
      $message .= $buf . $this->LE;
    }

    return ($message);
  }
  
  /**
   * The method can be used to encode the subject which is
   * in a non ascii encoding.
   * 
   * encoding style: =?charset?Q?the encoded text?=
   *
   * @param string $input
   * @param string $charset  eg. UTF-8 or ISO-8859-1
   * @return string  the encoded subject
   */
  function encodeSubject($input, $charset = 'ISO-8859-1')
  {
    // set the internal encoding to the encoding of the given input
    // this way no conversion is done, because we only need the 'Q' encoding
    $old_enc=mb_internal_encoding();
    mb_internal_encoding($charset);
    $output=mb_encode_mimeheader($input,$charset, 'Q', $this->LE);
    // and when ready, restore the internal encoding again
    mb_internal_encoding($old_enc);
    return $output;
    // below is a fallback mechanism which seems to work also
    /*
    preg_match_all('/(\\w*[\\x80-\\xFF]+\\w*)/', $input, $matches);
    foreach ($matches[1] as $value) {
      $replacement = preg_replace('/([\\x80-\\xFF])/e', '"=" . strtoupper(dechex(ord("\\1")))', $value);
      $input = str_replace($value, '=?' . $charset . '?Q?' . $replacement . '?=', $input);
    }
    return $input;
    */
  }
  

};  // end of class Web2All_Email_MimeMail

?>