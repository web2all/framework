<?php
/**
 * @package Web2All_ErrorObserver
 * @name Web2All_ErrorObserver_ErrorLog class
 * @author Hans Oostendorp
 * @copyright (c) Copyright 2007-2009 by Web2All
 * @version 0.1
 * @since 2007-05-29
 *
 */
class Web2All_ErrorObserver_ErrorLog extends Web2All_Manager_Plugin implements SplObserver {
  const VERSION = 0.1;
  private $errorobj =  false;

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
      $web2all->debugLog("[Start de errorObserverErrorLog Class]");
    }
    $this->codes=E_ALL;
    parent::__construct($web2all);
    // set config
    if (!is_null($this->config=$this->Web2All->Config->Web2All_ErrorObserver_ErrorLog)) {
      if (array_key_exists("codes",$this->config=$this->Web2All->Config->Web2All_ErrorObserver_ErrorLog)) {
        $codes = $this->config=$this->Web2All->Config->Web2All_ErrorObserver_ErrorLog['codes'];
        eval("\$codes = $codes;");
        $this->codes = $codes;
      }
    }
  }

  public function update(SplSubject $Observerable) {
    $this->errorobj = $Observerable->getState();
    if ( !($this->errorobj->getSeverity() & $this->codes) ) {
      return false;
    }
    $this->logException();
    return true;
  }

  public function logException() {
    $errortxt = "";
    if($this->Web2All->ErrorAddTime){
      $errortxt.=date("Y-m-d H:i:s ");
    }
    switch($this->errorobj->getSeverity()){
        case E_ERROR:               $errortxt.= "Error";                  break;
        case E_WARNING:             $errortxt.= "Warning";                break;
        case E_PARSE:               $errortxt.= "Parse Error";            break;
        case E_NOTICE:              $errortxt.= "Notice";                 break;
        case E_CORE_ERROR:          $errortxt.= "Core Error";             break;
        case E_CORE_WARNING:        $errortxt.= "Core Warning";           break;
        case E_COMPILE_ERROR:       $errortxt.= "Compile Error";          break;
        case E_COMPILE_WARNING:     $errortxt.= "Compile Warning";        break;
        case E_USER_ERROR:          $errortxt.= "User Error";             break;
        case E_USER_WARNING:        $errortxt.= "User Warning";           break;
        case E_USER_NOTICE:         $errortxt.= "User Notice";            break;
        case E_STRICT:              $errortxt.= "Strict Notice";          break;
        case E_RECOVERABLE_ERROR:   $errortxt.= "Recoverable Error";      break;
        case 8192:                  $errortxt.= "Deprecation Warning";    break; // E_DEPRECATED Since PHP 5.3.0
        case 16384:                 $errortxt.= "User Deprecation Warning"; break; // E_USER_DEPRECATED Since PHP 5.3.0
        default:                    $errortxt.= "Unknown error (".$this->errorobj->getSeverity().")"; break;
    }
    $exception_class=get_class($this->errorobj->getException());
    $errortxt.= ": ".($exception_class=='Web2All_Manager_TriggerException' ? '' :" [$exception_class] " ).$this->errorobj->getException()->getMessage()." (code:".$this->errorobj->getException()->getCode().") in ".$this->errorobj->getException()->getFile()." on line ".$this->errorobj->getException()->getLine()."\n";
    error_log(preg_replace('/\s+/',' ',$errortxt));
  }
}

?>