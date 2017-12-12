<?
/**
 * Send an e-mail
 * 
 * This is the main class for sending e-mails in the Web2All framework. All mail
 * sent by this class can (and must) be regulated by config settings.
 * These config keys must exists in the Web2All_Email_Main section:
 *   send_mail           : Set true if mails have to be send at all. Setting 
 *                         to false disables all mail sending.
 *   override_to_address : When set to empty string then the mail is delivered to
 *                         the correct recipients. If it is not empty then all mails
 *                         will be sent to the content of this config key instead.
 *
 * Example usage:
 *  $mailer = $this->Web2All->Plugin->Web2All_Email_Main();
 *  $mailer->charset = 'utf-8';// optional
 *  $mailer->send('<destination@example.com>', '<sender@example.com>', 'Example subject can contain utf-8', 'plaintext message', null);
 *
 * @package Web2All_Email
 * @name Web2All_Email_Main class
 * @author Hans Oostendorp
 * @copyright (c) Copyright 2007-2013 Web2All B.V.
 * @version 0.3
 * @since 2007-05-30
 */
class Web2All_Email_Main extends Web2All_Manager_Plugin {
  const VERSION = 0.3;

  /**
   * Mail config (key value pairs)
   *
   * @var Array
   */
  protected $config;
  
  /**
   * @var Web2All_Email_MimeMail  object
   */
  public $mimemail;
  
  public $charset="";

  /**
   * Constructor, initialises Web2All_Email_MimeMail object
   * 
   * @return Web2All_Email_MimeMail Object
   */
  public function __construct(Web2All_Manager_Main $web2all) {
    if($web2all->DebugLevel >= Web2All_Manager_Main::DEBUGLEVEL_FULL) {
      $web2all->debugLog("[Start de email Class]");
    }
    parent::__construct($web2all);
    
    // load config
    $defaultconfig=array(
      // any extra mime headers which need to be added to the mail
      // use \n to separate multiple headers, do not use a trailing newline
      // by default we add Auto-Submitted: auto-generated, this indicates its a computer generated mail
      'extra_mime_headers' => 'Auto-Submitted: auto-generated'
    );
    $requiredconfig=array(
      'send_mail' => true,
      'override_to_address' => true
    );
    
    $this->Web2All->Config->validateConfig('Web2All_Email_Main',$requiredconfig);
    
    $this->config=$this->Web2All->Config->makeConfig('Web2All_Email_Main',$defaultconfig);
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
   * @name send, send an e-mail
   * 
   * @param $mailTo String special mail-email format, see below for more info
   * @param $mailFrom String special mail-email format, see below for more info
   * @param $mailSubject String
   * @param $mailText String  set to null if you don't want a plaintext part
   * @param $mailHTML String  set to null if you don't want a html part
   * @param $attachments Array  array containing assoc array with following keys (for each attachment):
   *                            'path'   : optional path to attachment (including filename)
   *                            'content': optional content of the file
   *                            'name'   : required attachment name
   *                            'ctype'  : optional content-type of the file
   *                            'contentid' : optional content id (for inline images)
   * 
   * Special mail-email format;
   * The correct email format is "[username] <[email]>" this can result in "Noreply Web2All <noreply@web2all.nl>"
   * When using this format the email will be more trusted accepted, this can result in the accepting of external images for example
   * 
   * @return boolean  true if sent successfully
   */
  public function send($mailTo,$mailFrom,$mailSubject,$mailText,$mailHTML,$attachments=array()) {
    // create the mimemail object
    $this->buildMime($mailTo,$mailFrom,$mailSubject,$mailText,$mailHTML,$attachments);
    
    // check if we actually have to send the mail
    if($this->config['send_mail']){
      return $this->mimemail->send();
    }else{
      return true;
    }
  }
  
  /**
   * getRawMail(), get the raw mime mail as a string instead of sending it.
   * 
   * params are the same as the send() method
   * 
   * @param string $mailTo  destination e-mail address
   * @param string $mailFrom  sender e-mail address
   * @param string $mailSubject  mail subject line
   * @param string $mailText  plaintext message or null if it has to be left out
   * @param string $mailHTML  html message or null if it has to be left out
   * @param array $attachments  optional array of attachments (files), assoc array with following keys (for each attachment):
   *                            'path'   : optional path to attachment (including filename)
   *                            'content': optional content of the file
   *                            'name'   : required attachment name
   *                            'ctype'  : optional content-type of the file
   *                            'contentid' : optional content id (for inline images)
   * @return string  the raw mime mail string
   */
  public function getRawMail($mailTo,$mailFrom,$mailSubject,$mailText,$mailHTML,$attachments=array()) {
    // create the mimemail object
    $this->buildMime($mailTo,$mailFrom,$mailSubject,$mailText,$mailHTML,$attachments);
    
    return $this->mimemail->buildRawMime();
  }
  
  /**
   * buildMime()
   * 
   * Basically this method prepares the mimemail object, ready to send an email
   * but without sending the e-mail itself, so the raw mime can be retrieved
   * and sent by other means if needed.
   * 
   * params are the same as the send() method
   * 
   * @param string $mailTo
   * @param string $mailFrom
   * @param string $mailSubject
   * @param string $mailText
   * @param string $mailHTML
   * @param array $attachments
   */
  public function buildMime($mailTo,$mailFrom,$mailSubject,$mailText,$mailHTML,$attachments) {
    $this->mimemail = $this->Web2All->Factory->Web2All_Email_MimeMail();
    $this->mimemail->from    = $mailFrom;
    $this->mimemail->headers = "Errors-To: $mailFrom";
    if($this->config['extra_mime_headers']){
      $this->mimemail->headers .= "\n".$this->config['extra_mime_headers'];
    }
    // check if we have to override the recipients
    if($this->config['override_to_address']){
      $this->mimemail->to      = $this->config['override_to_address'];
    }else{
      $this->mimemail->to      = $mailTo;
    }
    $this->mimemail->subject = $mailSubject;
    foreach ($attachments AS $attachment) {
      $file_content = "";
      $file_att_name = "";
      if (array_key_exists("path",$attachment) && file_exists($attachment["path"])) {
        // Er is een path opgegeven waar de file staat
        $fd = fopen ($attachment["path"], "r");
        $file_content = fread ($fd, filesize ($attachment["path"]));
        fclose ($fd);
      }
      if (array_key_exists("content",$attachment)) {
        // De inhoud van de file is meegegeven
        $file_content = $attachment["content"];
      }
      if ($file_content!="" && array_key_exists("name",$attachment)) {
        // attachment default properties (from mimemail class)
        $att_ctype="application/octet-stream";
        $att_encode="base64";
        $att_charset='';
        $att_contentid='';
        // and now override the defaults
        if(array_key_exists("ctype",$attachment)){
          $att_ctype=$attachment["ctype"];
        }
        if(array_key_exists("contentid",$attachment)){
          $att_contentid=$attachment["contentid"];
        }
        // and now add the attachment
        $this->mimemail->add_attachment($file_content,$attachment["name"], $att_ctype, $att_encode, $att_charset, $att_contentid);
      }
    }
    // only send plaintext or html parts, if they are actually given.
    if(!is_null($mailHTML)){
      $this->mimemail->add_alternative_part($mailHTML, "", "text/html", "quoted-printable",$this->charset);
    }
    if(!is_null($mailText)){
      $this->mimemail->add_alternative_part($mailText, "", "text/plain", "quoted-printable",$this->charset);
    }
    
    // set charset of subject
    $this->mimemail->subject_charset=$this->charset;
    
  }
  
}
?>