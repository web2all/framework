<?php
/**
 * @package Web2All_ErrorObserver
 * @name Web2All_ErrorObserver_Email class
 * 
 * This is a errorobserver and it can be linked to the web2all error handling mechanism.
 * This specific error observer will send e-mails when errors occur. 
 * 
 * @author Hans Oostendorp
 * @copyright (c) Copyright 2007-2012 by Web2All
 * @since 2007-05-30
 */
class Web2All_ErrorObserver_Email extends Web2All_Manager_Plugin implements SplObserver {
  const VERSION = 0.1;// no longer used
  
  /**
   * Al list with all error data (an entry for each error)
   * 
   * @var Web2All_Manager_ErrorData[]
   */
  private $errorlist =  array();
  
  /**
   * The number of errors
   * Normally this is the same as count($this->errorlist) unless there were too many
   * and the cutout was activated. In which case this 'real error count' includes ALL errors,
   * and not only the ones displayed in this mail.
   * 
   * @var int
   */
  private $realerrorcount=0;
  
  /*
   * These properties store e-mail addresses (set in config)
   */
  private $mailto;
  private $mailfrom;
  
  /*
   * These properties store template filenames (set in config)
   * 
   * These are special template files which have some 'variables' defined in brackets.
   */
  private $attachment_template;
  private $sub_template_plain;
  private $sub_template_html;
  private $main_template_plain;
  private $main_template_html;
  
  protected $config;
  protected $defaultconfig;
  
  /**
   * Should be true when an error has been registered and the register_shutdown_function
   * has been registered.
   * 
   * @var boolean
   */
  protected $shutdownregistered=false;

  /**
   * Integer representation for bitwise compare whith occured error
   *
   * @var int
   */
  private $codes;

  /**
   * Constructor
   */
  public function __construct(Web2All_Manager_Main $web2all) {
    if($web2all->DebugLevel >= Web2All_Manager_Main::DEBUGLEVEL_FULL) {
      $web2all->debugLog("[Start de Web2All_ErrorObserver_Email Class]");
    }
    parent::__construct($web2all);
    
    // init default config
    // fixme: this should be local var, not class property
    $this->defaultconfig=array();
    
    // email templates (placeholders get replaced)
    $this->defaultconfig['attachment_template'] = dirname(__FILE__).'/Email/attachment.tpl';
    $this->defaultconfig['sub_template_plain']  = dirname(__FILE__).'/Email/sub.plain.tpl';
    $this->defaultconfig['sub_template_html']   = dirname(__FILE__).'/Email/sub.html.tpl';
    $this->defaultconfig['main_template_plain'] = dirname(__FILE__).'/Email/main.plain.tpl';
    $this->defaultconfig['main_template_html']  = dirname(__FILE__).'/Email/main.html.tpl';
    
    $this->defaultconfig['codes']               = 'E_ERROR&E_USER_ERROR';
    
    // maxerrors defines how many errors will be logged.
    // if more than maxerrors happen, they will not be recorded (to save memory and prevent out of memory errors)
    $this->defaultconfig['maxerrors']           = 100;
    
    // e-mail address is required (where to sent errormail)
    // each e-mail address should be the bare address like this: test@example.com
    // $requiredconfig=array(
    //   'mailfrom' => true,
    //   'mailto' => true
    // );
    // but don't actually validate so the framework can be used without setting e-mail addresses
    // $this->Web2All->Config->validateConfig('Web2All_ErrorObserver_Email',$requiredconfig);
    
    $this->defaultconfig['mailto'] = 'errormessages@example.com';
    $this->defaultconfig['mailfrom'] = 'errormessages@example.com';
    
    // set config
    $this->config=$this->Web2All->Config->makeConfig('Web2All_ErrorObserver_Email',$this->defaultconfig);
    
    // init object based on config
    $this->mailto = $this->config['mailto'];
    $this->mailfrom = '"'.(array_key_exists('HTTP_HOST',$_SERVER) ? $_SERVER['HTTP_HOST'] : 'shell script').'" <'.$this->config['mailfrom'].'>';
    $codes = $this->config['codes'];
    eval("\$codes = $codes;");// this will 'convert' the string to a constant name
    $this->codes = $codes;
    $this->attachment_template = $this->config['attachment_template'];
    $this->sub_template_plain = $this->config['sub_template_plain'];
    $this->sub_template_html = $this->config['sub_template_html'];
    $this->main_template_plain = $this->config['main_template_plain'];
    $this->main_template_html = $this->config['main_template_html'];
    
  }
  
  /**
   * flush all errors if any
   * 
   * If there are any errors they will be e-mailed and the errorstate will be
   * reset. This is useful for long running processes like daemons.
   */
  public function flushErrors() {
    if(count($this->errorlist)>0){
      // send errors by mail
      $this->emailExceptions();
      // reset errors
      $this->errorlist=array();
      $this->realerrorcount=0;
    }
  }
  
  public function onRequestTerminate() {
    try {
      $this->emailExceptions();
    } catch (Exception $e) {
      error_log('Caught exception during sending error e-mail: '.$e->getMessage());
    }
  }
  
  /**
   * @name update
   * @param $Observerable Web2All_Manager_ErrorObserverable
   */
  public function update( SplSubject $Observerable) {
    $errorobj=$Observerable->getState();
    // check if this error matches our error code bitmask
    if ( !($errorobj->getSeverity() & $this->codes) ) {
      // we do not need to mail this error, ignore it
      return;
    }
    if (!$this->shutdownregistered) {
      // we now have at least one e-mail error, so we need to send the error mail on script termination
      register_shutdown_function(array($this,"onRequestTerminate"));
      $this->shutdownregistered=true;
    }
    $this->realerrorcount++;
    // only store errors while maxerrors is not yet reached
    if($this->realerrorcount<=$this->config['maxerrors']){
      $this->errorlist[] = $errorobj;
    }
  }

  public function emailExceptions() {
    $time=date("D j F Y H:i:s");
    
    // a basic error report with one error needs 500k memory. A big report with 40 (small) errors needs 1M memory.
    $memlim=Web2All_PHP_INI::getBytes(ini_get('memory_limit'));
    // check if enough memory available
    if($memlim!=-1 && (memory_get_usage()+1000000)>$memlim){
      // upgrade max memory a bit (1M), to make sure we can at least e-mail the errors
      error_log('Web2All_ErrorObserver_Email->emailExceptions: low on memory, increasing limit to '.($memlim+1000000));
      ini_set('memory_limit',$memlim+1000000);
    }
    
    $str_attachments = "";
    $attachments[] = array("content"=>$this->get_phpinfo(),"name"=>"phpinfo.html", 'ctype' => 'text/html');
    $sub_plain = "";
    $sub_html = "";
    // $num_errors is the number of errors included in this mail (the realerrorcount could be higher)
    $num_errors=0;
    foreach ($this->errorlist AS $errorobj) {
      $exception=$errorobj->getException();
      
      $num_errors++;
      $error = "";
      switch($errorobj->getSeverity()){
          case E_ERROR:               $error =  "Error";                  break;
          case E_WARNING:             $error =  "Warning";                break;
          case E_PARSE:               $error =  "Parse Error";            break;
          case E_NOTICE:              $error =  "Notice";                 break;
          case E_CORE_ERROR:          $error =  "Core Error";             break;
          case E_CORE_WARNING:        $error =  "Core Warning";           break;
          case E_COMPILE_ERROR:       $error =  "Compile Error";          break;
          case E_COMPILE_WARNING:     $error =  "Compile Warning";        break;
          case E_USER_ERROR:          $error =  "User Error";             break;
          case E_USER_WARNING:        $error =  "User Warning";           break;
          case E_USER_NOTICE:         $error =  "User Notice";            break;
          case E_STRICT:              $error =  "Strict Notice";          break;
          case E_RECOVERABLE_ERROR:   $error =  "Recoverable Error";      break;
          case 8192:                  $error =  "Deprecation Warning";    break; // E_DEPRECATED Since PHP 5.3.0
          case 16384:                 $error =  "User Deprecation Warning"; break; // E_USER_DEPRECATED Since PHP 5.3.0
          default:                    $error =  "Unknown error (".$errorobj->getSeverity().")"; break;
      }
      $trace_text = "";
      $i=0;
      // if exception is caused by trigger_error, use property triggertrace in stead of method getTrace()
      // getTrace() contains invalid traces when invoked by trigger_error
      $traces = $exception->getTrace();
      if ($exception instanceof Web2All_Manager_TriggerException)
      {
        if($exception->triggertrace){
          $traces = $exception->triggertrace;
        }
      }
      if(!$errorobj->getTraceSuppression()){
        foreach ($traces AS $trace) {
          $trace_text .= "#".$i." ".(array_key_exists('file',$trace) ? $trace['file'] : '')."(".(array_key_exists('line',$trace) ? $trace['line'] : '')."): ";
          if (array_key_exists('class',$trace) && array_key_exists('type',$trace)) {
            $trace_text .= $trace['class'].$trace['type'];
          }
          if(array_key_exists('function',$trace)){
            $trace_text .= $trace['function']."(";
            $komma='';
            if (array_key_exists('args',$trace) && count($trace['args'])>0) {
              foreach ($trace['args'] AS $arg) {
                if (is_array($arg)) {
                  $arg='Array('.count($arg).')';
                }elseif (is_object($arg)) {
                  $arg='Object '.get_class($arg);
                }elseif (is_bool($arg)) {
                  $arg=$arg ? 'true' : 'false';
                }else{
                  $arg="'".$arg."'";
                }
                
                $trace_text .= $komma.$arg;
                $komma = ', ';
              }
            }
            $trace_text .= ");\n";
          }
          $i++;
        }
      }else{
        $trace_text.='suppressed';
      }
      $trace_text .= "\n";
      $vars_plain = array(  "regelnr"    =>  $exception->getLine(),
                            "errorno"    =>  $exception->getCode(),
                            "errortype"  =>  $error,
                            "filename"  =>  $exception->getFile(),
                            "message"    =>  $exception->getMessage(),
                            "trace"      =>  $trace_text,
                            "exception"  =>  get_class($exception),
                            "phpcode"    =>  $this->getLine($exception->getFile(),$exception->getLine())
                         );
      $vars_html = $vars_plain;
      $vars_html['message'] = htmlentities($vars_html['message']);
      $vars_html['trace'] =   htmlentities($vars_html['trace']);
      $vars_html['phpcode'] = htmlentities($vars_html['phpcode']);
      $sub_plain.= $this->load_template($this->sub_template_plain,$vars_plain);
      $sub_html .= $this->load_template($this->sub_template_html,$vars_html);
    }
    if ($num_errors==0) {
      return false;
    }
    // check if this script is run commandline (cron) or from apache
    $website_script=array_key_exists('HTTP_HOST',$_SERVER);
    if ($website_script) {
      // run from apache
      $mailSubject = "Website error".(($this->realerrorcount>1)?'s':'')." (".$this->realerrorcount.") op ".$_SERVER['HTTP_HOST'];
      // if only one error 
      if($num_errors==1 && array_key_exists(0,$this->errorlist)){
        $thisexception=$this->errorlist[0]->getException();
        if($thisexception && $thisexception instanceof Exception){
          $mailSubject .= ' in '.basename($thisexception->getFile()).':'.$thisexception->getLine().' ';
        }
      }
    }else{
      // run from commandline (or cron)
      $mailSubject = "PHP shell script error".(($this->realerrorcount>1)?'s':'')." (".$this->realerrorcount.") ";
      $hostname=$this->getHostName();
      if(!empty($hostname)){
        $mailSubject .= 'op ['.$hostname.'] ';
      }
      // append script name
      if(array_key_exists('SCRIPT_FILENAME',$_SERVER)){
        $mailSubject .= 'in '.basename($_SERVER['SCRIPT_FILENAME']).' ';
      }
    }
    for ($i=0;$i<count($attachments);$i++) {
      $vars = array("name"=>$attachments[$i]["name"],"nr"=>($i+1));
      $str_attachments .= $this->load_template($this->attachment_template,$vars);
    }
    $fouten = "is een fout";
    if ($num_errors>1) {
      $fouten = "zijn ".$this->realerrorcount.($this->realerrorcount!=$num_errors? ' fouten (waarvan maar '.$num_errors.' in deze mail geregistreerd)' : ' fouten');
    }
    $strpath='';
    if ($website_script && array_key_exists('REQUEST_URI',$_SERVER)) {
      $strpath=$_SERVER['REQUEST_URI'];
    }else{
      $strpath=$_SERVER['SCRIPT_FILENAME'];
    }
    $vars_plain = array(  "website"      =>  ($website_script ? $_SERVER['HTTP_HOST'] : 'shell script'),
                          "datetime"    =>  $time,
                          "attachments"  =>  $str_attachments,
                          "browser"      =>  (array_key_exists('HTTP_USER_AGENT',$_SERVER) ? $_SERVER['HTTP_USER_AGENT'] : 'N/A'),
                          "path"         => $strpath,
                          "fouten"      =>  $fouten
                       );
    $vars_html = $vars_plain;
    $vars_html['sub'] = $sub_html;
    $vars_plain['sub'] = $sub_plain;
    $mailHTML = '';
    $mailText = '';
    $mailHTML = $this->load_template($this->main_template_html,$vars_html);
    $mailText = $this->load_template($this->main_template_plain,$vars_plain);
    $this->Web2All->Plugin->Web2All_Email_Main->send($this->mailto,$this->mailfrom,$mailSubject,$mailText,$mailHTML,$attachments);
  }

  /**
   * Get the source code line from the script.
   * It will detect if a file is zend encoded (in which case the
   * source code cannot be retrieved)
   * 
   * might break on files with very long lines (>4096) in which case 
   * the wrong line might be returned.
   * 
   * @param string $file  the full filename and path to the php file
   * @param int $line  the line number
   * @return string  code on the given line in the file
   */
  private function getLine($file,$line=1) {
    $phpcode = "";
    if (!file_exists($file)) return $phpcode;
    $fp = @fopen($file, "rb");
    if (!$fp) return $phpcode;
    $i=0;
    while (!@feof($fp) && $i<$line) {
      if($i==1){
        if(strpos($phpcode,'@Zend')!==false){
          @fclose($fp);
          return 'file is Zend encoded, cannot read PHP code';
        }
      }
      $i++;
      $phpcode = fgets($fp, 4096);
    }
    @fclose($fp);
    return trim($phpcode);
  }

  private function load_template($file,$vars=array()) {
    if (!file_exists($file)) {
      throw new Exception("Can't find tempate $file",E_USER_ERROR);
    }
    $handle = fopen($file,"r");
    $contents = fread($handle, filesize($file));
    fclose($handle);
    reset($vars);
    while(list($var,$value)=each($vars)) {
      $contents=str_replace("[".$var."]",$value,$contents);
    }
    return $contents;
  }

  /**
   * get the phpinfo information in a string
   *
   * @return string  the full phpinfo html page as a string
   */
  private function get_phpinfo() {
    ob_start();
    phpinfo();
    $info=ob_get_contents();
    ob_end_clean();
    return $info;
  }

  /**
   * find the servers hostname
   *
   * @return string  the hostname or empty string if not found
   */
  protected function getHostName() {
    if (count($_ENV)==0) {
      // somtimes on request termination, the $_ENV is empty but getenv works
      // check our custom hostname var
      if (getenv('W2A_HOSTNAME')!==false) {
        return getenv('W2A_HOSTNAME');
      }
      // fallback to generic hostname var
      if (getenv('HOSTNAME')!==false) {
        return getenv('HOSTNAME');
      }
    }else{
      // check environment $_ENV
      // check our custom hostname var
      if(array_key_exists('W2A_HOSTNAME',$_ENV) && !empty($_ENV['W2A_HOSTNAME'])){
        return $_ENV['W2A_HOSTNAME'];
      }
      // fallback to generic hostname var
      if(array_key_exists('HOSTNAME',$_ENV) && !empty($_ENV['HOSTNAME'])){
        return $_ENV['HOSTNAME'];
      }
    }
    // still nothing, theres a small chance its a SERVER var, lets try that
    if(array_key_exists('HOSTNAME',$_SERVER)){
      return $_SERVER['HOSTNAME'];
    }
    return "";
  }
}

?>