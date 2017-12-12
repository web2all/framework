<?php
/**
 * @package Web2All_ErrorObserver
 * @name Web2All_ErrorObserver_Display class
 * @author Hans Oostendorp
 * @copyright (c) Copyright 2007-2015 by Web2All
 * @version 0.1
 * @since 2007-05-29
 * 
 * This error observer displays errors (verbose) on the screen. Alternatively it can also
 * display a single enduser message.
 * 
 */
class Web2All_ErrorObserver_Display extends Web2All_Manager_Plugin implements SplObserver {
  const VERSION = 0.1;
  
  /**
   * Al list with all error data (an entry for each error)
   * 
   * @var Web2All_Manager_ErrorData[]
   */
  private $errorlist =  array();

  /**
   * Integer representation for bitwise compare whith occured error
   *
   * @var int
   */
  private $codes;

  protected $config;
  protected $defaultconfig;
  protected $shutdownregistered=false;
  
  /**
   * The number of errors
   * Normally this is the same as count($this->errorlist) unless there were too many
   * and the cutout was activated. In which case this 'real error count' includes ALL errors,
   * and not only the ones displayed.
   * 
   * @var int
   */
  private $realerrorcount=0;
  
  /**
   * Constructor
   */
  public function __construct(Web2All_Manager_Main $web2all) {
    if($web2all->DebugLevel >= Web2All_Manager_Main::DEBUGLEVEL_FULL) {
      $web2all->debugLog("[Start de Web2All_ErrorObserver_Display Class]");
    }
    parent::__construct($web2all);
    
    // init default config
    $this->defaultconfig=array();
    
    // set the display mode. three different modes:
    // COMPAT : this is DEPRECATED, backwards compatibility mode. Now works the same as 
    //          VERBOSE mode!
    // VERBOSE: this is the default, All errors matching the severity set in the 'codes' 
    //          config entry, will be displayed fully, including trace.
    // ENDUSER: If there were errors matching the severity set in the 'codes' config entry,
    //          an enduser notice will be given only once, at the end of the page.
    $this->defaultconfig['mode'] = 'VERBOSE';
    
    // set the bitmask matching all errorcodes that have to be displayed
    $this->defaultconfig['codes'] = 0;// by default show no errors
    
    // Log instantly or log at the end of the script, instantly only works in VERBOSE mode
    $this->defaultconfig['instant_output'] = false;// by default log at the end
    
    // maxerrors defines how many errors will be logged.
    // if more than maxerrors happen, they will not be recorded (to save memory and prevent out of memory errors)
    // only applies if instant_output==false
    $this->defaultconfig['maxerrors']      = 100;
    
    // default enduser notice
    $this->defaultconfig['enduser_notice'] = "Er is een error opgetreden. Excuses voor het ongemak. Probeer later opnieuw.";
    
    $this->config=$this->Web2All->Config->makeConfig('Web2All_ErrorObserver_Display',$this->defaultconfig);
    
    // set config
    $codes = $this->config['codes'];
    eval("\$codes = $codes;");
    $this->codes = $codes;
    
  }
  
  /**
   * @name onRequestTerminate
   * 
   * Method to use with register_shutdown_function, will start the
   * error output.
   */
  public function onRequestTerminate() {
    try {
      $this->displayExceptions();
    } catch (Exception $e) {
      error_log('Caught exception during displaying errors: '.$e->getMessage());
    }
  }
  
  /**
   * @name update
   * @param $Observerable Web2All_Manager_ErrorObserverable
   */
  public function update(SplSubject $Observerable) {
    $errorobj=$Observerable->getState();
    // check if this error matches our error code bitmask
    if ( !($errorobj->getSeverity() & $this->codes) ) {
      // we do not need to mail this error, ignore it
      return;
    }
    // register shutdown function if logging at the end of the script
    if (!$this->shutdownregistered && !$this->config['instant_output']) {
      register_shutdown_function(array($this,"onRequestTerminate"));
      $this->shutdownregistered=true;
    }
    $this->realerrorcount++;
    // only store errors while maxerrors is not yet reached or when outputting instantly
    if($this->config['instant_output'] || $this->realerrorcount<=$this->config['maxerrors']){
      $this->errorlist[] = $errorobj;
    }
    // check if we have to instantly output errors
    if($this->config['instant_output'] && $this->config['mode']=='VERBOSE'){
      $this->displayExceptions();
      // reset array
      $this->errorlist=array();
    }
  }
  
  /**
   * @name displayExceptions
   * 
   * Will display all errors currently stored in $this->errorlist
   */
  public function displayExceptions() {
    $numerrors = 0;
    $display_enduser_notice=false;
    foreach ($this->errorlist AS $errorobj) {
      $exception=$errorobj->getException();
      $numerrors++;
      if($this->config['mode']=='COMPAT' || $this->config['mode']=='VERBOSE'){
        // display verbose error report
      
        $this->debugLog("<div style=\"text-align:left;\"><pre><b>");
        switch($errorobj->getSeverity()){
            case E_ERROR:               $message="Error";                  break;
            case E_WARNING:             $message="Warning";                break;
            case E_PARSE:               $message="Parse Error";            break;
            case E_NOTICE:              $message="Notice";                 break;
            case E_CORE_ERROR:          $message="Core Error";             break;
            case E_CORE_WARNING:        $message="Core Warning";           break;
            case E_COMPILE_ERROR:       $message="Compile Error";          break;
            case E_COMPILE_WARNING:     $message="Compile Warning";        break;
            case E_USER_ERROR:          $message="User Error";             break;
            case E_USER_WARNING:        $message="User Warning";           break;
            case E_USER_NOTICE:         $message="User Notice";            break;
            case E_STRICT:              $message="Strict Notice";          break;
            case E_RECOVERABLE_ERROR:   $message="Recoverable Error";      break;
            case 8192:                  $message="Deprecation Warning";    break; // E_DEPRECATED Since PHP 5.3.0
            case 16384:                 $message="User Deprecation Warning"; break; // E_USER_DEPRECATED Since PHP 5.3.0
            default:                    $message="Unknown error (".$errorobj->getSeverity().")"; break;
        }
        $this->debugLog($message.":</b> <i>".$this->htmlentitiesIfdebugMethodAcceptsHtml($exception->getMessage())."</i> in <b>".$exception->getFile()."</b> on line <b>".$exception->getLine()."</b>");
        $this->debugLog("Exception class: <i>".$this->htmlentitiesIfdebugMethodAcceptsHtml(get_class($exception))."</i>");
        $this->debugLog("Php code: <i>".$this->htmlentitiesIfdebugMethodAcceptsHtml($this->getLine($exception->getFile(),$exception->getLine()))."</i>");
        $this->debugLog("Trace:");
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
            $trace_text = '';
            $trace_text.= "#".$i." ".(array_key_exists('file',$trace) ? $trace['file'] : '')."(".(array_key_exists('line',$trace) ? $trace['line'] : '')."): ";
            if (array_key_exists('class',$trace) && array_key_exists('type',$trace)) {
              $trace_text.= $trace['class'].$trace['type'];
            }
            if(array_key_exists('function',$trace)){
              $trace_text.= $trace['function']."(";
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
                  
                  $trace_text.= $komma.$arg;
                  $komma = ', ';
                }
              }
              $trace_text.= ");";
            }
            $i++;
            $this->debugLog($trace_text,true);
          }
        }else{
          $this->debugLog("trace suppressed");
        }
        $this->debugLog("</pre></div>");
      }
      if($this->config['mode']=='ENDUSER'){
        // display end user error report
        $display_enduser_notice=true;
      }
    }// end error loop
    // see if we hit the curoff, if so inform
    if(!$this->config['instant_output'] && $this->realerrorcount>$this->config['maxerrors']){
      $this->debugLog('MAX ERRORS has been reached, only displayed '.$this->config['maxerrors'].' out of '.$this->realerrorcount.' errors');
    }
    // see if we have to display end-user message
    if ($display_enduser_notice) {
      $this->debugLog($this->config['enduser_notice']);
    }
  }
  
  /**
   * Put $message to debuglog, returns is message is send, false if not
   *
   * @param string $message
   * @param boolean $keeptags  when true, tags will never be stripped
   * @return boolean
   */
  protected function debugLog($message,$keeptags=false)
  {
    // if debug doesn't accept html, remove it
    if (!$this->Web2All->debugMethodAcceptsHtml())
    {
      if(!$keeptags){
        $message = strip_tags($message);
      }
      // do not send empty lines to plain text debugging
      if ($message=='')
      {
        return false;
      }
      // some basic markup so you can see the output is from the display observer
      $message='| '.$message;
    }
    $this->Web2All->debugLog($message);
    return true;
  }
  
  /**
   * htmlentities $message if web2all manager debugmethod accepts html
   *
   * @param string $message
   * @return string
   */
  protected function htmlentitiesIfdebugMethodAcceptsHtml($message)
  {
    if ($this->Web2All->debugMethodAcceptsHtml())
    {
      return htmlentities($message);
    }
    return $message;
  }
  
  /**
   * getLine returns the requested line from the given file
   * 
   * It will detect zend encoded files
   *
   * @param string $file  full filename and path to file
   * @param int $line  the line number to return
   * @return string
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

}

?>