<?php
/**
 * Web2All Framework
 *
 * This file contains many core classes of the Web2All Framework.
 * 
 * Web2All_Manager_Main
 *   Core class of the framework, every script should instantiate one.
 * Web2All_Manager_Config
 *   Config class is used for all configurations in framework classes
 * 
 * Web2All_Manager_Plugin / Web2All_Manager_PluginInterface / Web2All_Manager_PluginTrait
 *   Base class/interface/trait of all framework classes. It exposes the instance of
 *   the Web2All_Manager_Main as the Web2All property inside the Plugin class.
 *   Every plugin has this Main object as the first constructor param OR has it set 
 *   right after with the setWeb2All() method.
 * 
 * Web2All_Manager_EncapsulatedPassword
 *   Put passwords in this class so they do not leak in stacktraces.
 * 
 * This package has a dependency on the Web2All_ErrorObserver package so
 * be sure to include it in your project as well.
 * 
 * @package Web2All_Manager
 * @copyright (c) Copyright 2007-2017 by Web2All
 * @since 2007-05-16
 */

// make sure we at least search the directory localion where Manager_Main is located
Web2All_Manager_Main::registerIncludeRoot();

/**
 * Web2All Manager PluginInterface interface
 *
 * The interface for Web2All_Manager_Plugin
 *
 * @author Merijn van den Kroonenberg
 * @copyright (c) Copyright 2017 Web2All BV
 * @since 2017-06-26 
 */
interface Web2All_Manager_PluginInterface {
  
  /**
   * Get the Web2All_Manager_Main object
   *
   * @return Web2All_Manager_Main
  */
  public function getWeb2All();
  
  /**
   * Set the Web2All_Manager_Main object
   *
   * @param Web2All_Manager_Main $web2all
  */
  public function setWeb2All($web2all);
  
}

/**
 * Web2All Manager TPlugin trait
 *
 * This trait implements Web2All_Manager_PluginInterface.
 *
 * @author Merijn van den Kroonenberg
 * @copyright (c) Copyright 2017 Web2All BV
 * @since 2017-06-26 
 */
trait Web2All_Manager_PluginTrait {
  
  /**
   * @var Web2All_Manager_Main
   */
  protected $Web2All;
  
  /**
   * Get the Web2All_Manager_Main object
   *
   * @internal ignore
   * @return Web2All_Manager_Main
  */
  public function getWeb2All()
  {
    return $this->Web2All;
  }
  
  /**
   * Set the Web2All_Manager_Main object
   *
   * @internal ignore
   * @param Web2All_Manager_Main $web2all
  */
  public function setWeb2All($web2all)
  {
    $this->Web2All = $web2all;
  }
  
}

/**
 * The base class of all Web2All framework classes
 * 
 * @name Web2All_Manager_Plugin class
 * @author Hans Oostendorp
 * @copyright (c) Copyright 2007 by Web2All
 * @version 0.1
 * @since 2007-06-13
 */
abstract class Web2All_Manager_Plugin implements Web2All_Manager_PluginInterface {
  use Web2All_Manager_PluginTrait;
  
  /**
   * constructor
   *
   * @param Web2All_Manager_Main $web2all
   */
  public function __construct(Web2All_Manager_Main $Web2All) {
    if($Web2All->DebugLevel >= Web2All_Manager_Main::DEBUGLEVEL_HIGH) {
      $Web2All->debugLog(get_class($this)."-> [Start de Web2All_Manager_Plugin Class]");
    }
    $this->Web2All = $Web2All;
  }
  
  /**
   * Destructor (placeholder)
   * 
   * its here, so Web2All_Manager_Plugin derived classes can
   * always call a parent destructor
   */
  public function __destruct() {
    // do nothing
  }
}

/**
 * @name Web2All_Manager_Main class
 *
 * This class is the core of the Web2All PHP framework.
 * 
 * Every PHP script at Web2All will instantiate this class as one of the first
 * things it does. There are several ways to do this, but the latest and rec-
 * commended way is the following:
 * 
 *   require_once(dirname(__FILE__) . '/../include/Web2All/Manager/Main.class.php');
 *   $web2all = Web2All_Manager_Main::newInstance();
 * 
 * Above: this class is included relative to the script location, so this include
 * statement also works when called command line. Using the newInstance() method
 * to create an instance has a few prequisites:
 * - the include file /include/include.php must exist
 * - this include file must have the WEB2ALL_CONFIG_CLASS constant defined
 * - the WEB2ALL_CONFIG_CLASS constant must contain the classname of a config class 
 *   which extends Web2All_Manager_Config
 *
 * @package Web2All_Manager
 * @author Hans Oostendorp
 * @copyright (c) Copyright 2007-2017 by Web2All
 * @version 0.2
 * @since 2007-05-16
 */
class Web2All_Manager_Main {
  /**
   * Version: OBSOLETE
   * 
   * The version is not actually used and never updated. Do not use.
   */
  const VERSION = 0.2;
  
  /**
   * Debug levels [0-5]
   * From no debug logging (0) to full logging (5)
   *
   */
  const DEBUGLEVEL_NONE     = 0;
  const DEBUGLEVEL_MINIMAL  = 1;
  const DEBUGLEVEL_LOW      = 2;
  const DEBUGLEVEL_MEDIUM   = 3;
  const DEBUGLEVEL_HIGH     = 4;
  const DEBUGLEVEL_FULL     = 5;
  
  /**
   * Array of root directories for the framework.
   * 
   * Each directory will be searched by the autoloader for classes.
   *
   * @var array
   */
  protected static $frameworkRoots=array();
  
  /**
   * Was the autoloader added
   *
   * @var boolean
   */
  protected static $autoloaderAdded=false;
  
  /**
   * Observerable who handles list of obsersers that should be notified when error occures
   */
  private $errorObserverable;

  /**
   * Sets if plugin loader should only include class php file from plugin
   * directory, of should also check enviroment path if plugin does not exists
   */
  private $pluginloaderChecksEnviromentPath = false;

  /**
   * Global storage voor pointers naar classes die automatisch door de
   * Web2All_Manager_Main class aangemaakt worden. De global storage is een
   * singelton registry class zodat alle classes die de Web2All_Manager_Main
   * class extenden deze global storage delen.
   * Voorbeeld1:  Initialiseer class test voor global gebruik.
   *               $Web2All->test();
   *              De pointer naar class test wordt in de global_data opgeslagen.
   *               Vanaf nu kunnen alle classes die de Web2All_Manager_Main
   *               class extenden gebruik maken van $Web2All->test
   */
  private $global_data;

  /**
   * The default config array for this class
   *
   * @var Array
   */
  protected $DefaultConfig=array();
  
  /**
   * The config array for this class
   *
   * @var Array
   */
  protected $ManagerConfig=Array();
  
  /**
   * Local Web2All_Manager_Plugin Access
   * 
   * This property is used to autoload (instantiate) an
   * Plugin object. The returned object is always new and not
   * singleton.
   * 
   * Use Factory instead of Plugin where possible. You only need
   * Plugin when the class does not extend Web2All_Manager_Plugin
   * but still requires the Web2All_Manager_Main object as first
   * constructor param.
   *
   * @var Web2All_Manager_PluginLoaderPrivate
   */
  public $Plugin;

  /**
   * Global Web2All_Manager_Plugin Access
   * 
   * This property is used to autoload (instantiate) an
   * Plugin object. The returned object is shared and singleton  
   * when created through this property. A second call on the same class
   * will return a pointer to the existing object.
   * 
   * Use of PluginGlobal is discouraged, sopport might be dropped in the 
   * future.
   *
   * @var Web2All_Manager_PluginLoaderGlobal
   */
  public $PluginGlobal;
  
  /**
   * Global Web2All_Manager_ClassInclude Access
   * This property is used to autoload and not (instantiate) an
   * object.
   *
   * @var Web2All_Manager_ClassInclude
   */
  public $ClassInclude;

  /**
   * Factory Access to all web2all classes
   * 
   * This property will create an instance of the required class.
   * Classfiles will be automatically included.
   *
   * @var Web2All_Manager_Factory
   */
  public $Factory;

  /**
   * The config object used by this web2all class
   *
   * @var Web2All_Manager_Config  or extension hereof
   */
  public $Config;
  
  /**
   * Number of includes
   *
   * @var int
   */
  public $includes;

  /**
   * The debug method for debugLog()
   * 
   * when outputting debuglogs use the following method (echo|echoplain|error_log), default echo
   *
   * @var string
   */
  public $DebugMethod='echo';
  
  /**
   * The debug level [0-5] where 0 is no debugging
   *
   * @var int
   */
  public $DebugLevel=0;
  
  /**
   * Set to true if datetime has to be prepended to log message
   *
   * @var boolean
   */
  public $DebugAddTime=false;
  
  /**
   * Set to true if debugLog should recognise multiline messages
   * 
   * Will allow putting each line in the message in its own lone in the error
   * log if logging to error_log. or will add DateTime to each line if 
   * DebugAddTime is true and DebugMethod is echo(plain).
   *
   * @var boolean
   */
  public $DebugMultiLine=true;
  
  /**
   * Set to true if datetime has to be prepended to error(log) message
   *
   * @var boolean
   */
  public $ErrorAddTime=false;
  
  /**
   * The User CPU usage when this class is started.
   *
   * @var integer
   */
  public $startUserCPU=0;
  
  /**
   * The System CPU usage when this class is started.
   *
   * @var integer
   */
  public $startSystemCPU=0;
  
  /**
   * Constructor
   * Initialise everything that Web2All_Manager_Main always needs
   *
   * @param Web2All_Manager_Config $config
   * @param int $debuglevel  [optional int [0-5] set when debugging core Web2All_Manager functionality]
   */
  public function __construct(Web2All_Manager_Config $config = null, $debuglevel = 0) {
    // set initial debuglevel.
    // this is only set when debugging Web2All_Manager core functionality which is
    // loaded before config settings are available.
    $this->DebugLevel=$debuglevel;
    
    if($this->DebugLevel >= self::DEBUGLEVEL_HIGH) {
      $this->debugLog(get_class($this)."-> [Start de Web2All_Manager_Main Class]");
      if(!is_null($config)){
        $this->debugLog( get_class($this)."-> [using config ".get_class($config)."]");
      }
    }
    
    // record the initial CPU usage
    // not portable to windows
    $dat = getrusage();
    $this->startUserCPU=$dat["ru_utime.tv_sec"]*1e6+$dat["ru_utime.tv_usec"];
    $this->startSystemCPU=$dat["ru_stime.tv_sec"]*1e6+$dat["ru_stime.tv_usec"];
    
    /*
     * initiate storages
     */
    Web2All_Manager_Registry::$DebugLevel=$this->DebugLevel;
    $this->global_data = Web2All_Manager_Registry::getInstance();

    /*
     * initiate Web2All_Manager_Plugin Loaders
     */
    $this->Plugin        = new Web2All_Manager_PluginLoaderPrivate($this);
    $this->PluginGlobal  = new Web2All_Manager_PluginLoaderGlobal($this);
    $this->Factory       = new Web2All_Manager_Factory($this);
    $this->ClassInclude  = new Web2All_Manager_ClassInclude($this);

    /*
     * initiate config
     * the config is only local accessable
     */
    if(is_null($config)){
      $config = new Web2All_Manager_Config;
    }
    if (!(is_subclass_of($config,'Web2All_Manager_Config') || (get_class($config)=='Web2All_Manager_Config')) ) {
      throw new Exception("Config class must extend Web2All_Manager_Config",E_USER_ERROR);
    }
    $this->Config=$config;

    /*
     * start errorObserverable
     * global accessable
     */
    $this->PluginGlobal->Web2All_Manager_ErrorObserverable();
    
    /*
     * start default error catcher
     */
    if (!Web2All_Manager_Error::getInstance()) {
      Web2All_Manager_Error::newInstance($this);
    }
    
    register_shutdown_function(array($this,"onRequestTerminate"));
    
    /*
     * set the default config
     */
    // add_includepath_to_path: define if the include path (relative ../../ from this location) is added to the php include path. Usefull for PEAR modules.
    $this->DefaultConfig['add_includepath_to_path']=false;
    // default no debugging, unless passed as constructor param
    $this->DefaultConfig['debuglevel']=$debuglevel;
    // when outputting debuglogs use the following method (echo|echoplain|error_log), default echo
    $this->DefaultConfig['debugmethod']='echo';
    // when outputting debug logs, do we add a timestamp in front of each log entry?
    $this->DefaultConfig['debug_add_timestamp']=false;
    // when debugmethod==error_log, should we split the message on newlines? (multiline log)
    $this->DefaultConfig['error_log_multiline']=true;
    // when debugmethod==error_log, should we wrap too long lines?
    $this->DefaultConfig['error_log_wrap']=true;
    // Set error_reporting_level (int) to a error_reporting integer (or use predefined constants)
    // defaults to E_ALL, but this can mean different things in different PHP versions, 
    // so you can use this to force consistent behaviour.
    // you cannot binary operators in the config, so you will have to specify an integer if you
    // want to combine constants.
    $this->DefaultConfig['error_reporting_level']=E_ALL;
    // default do not allow notice/warning/error messages to be suppressed (@ operator)
    // when you set this to true, your default error_reporting level must be non-zero.
    // because if it is zero, ALL errors will be suppressed, even if no @ is used
    $this->DefaultConfig['allow_error_suppression']=false;
    // enable or disable assertion
    $this->DefaultConfig['assertionsenabled']=false;
    // possibly override the assertion callback method (not reccommended)
    $this->DefaultConfig['assertion_callback_method']=array('Web2All_Manager_Main','assertHandler');
    // Do not get ip from xforwarded, use REMOTE_ADDR
    $this->DefaultConfig['get_ip_from_xforwarded']=false;
    // Only useful if get_ip_from_xforwarded is true: it defines the amount of trusted proxies
    $this->DefaultConfig['trusted_proxy_amount']=1;
    
    $this->ManagerConfig=$this->Config->makeConfig('Web2All_Manager_Main',$this->DefaultConfig);
    
    /*
     * set error handlers
     */
    $this->setErrorHandlers();
    
    /*
     * set the debug method
     */
    $this->DebugMethod=$this->ManagerConfig['debugmethod'];
    
    /*
     * do we add timestamp?
     */
    $this->DebugAddTime=$this->ManagerConfig['debug_add_timestamp'];
    
    /*
     * set the debug level
     */
    $this->DebugLevel= $this->ManagerConfig['debuglevel'];
    
    /*
     * set the debug multiline
     */
    $this->DebugMultiLine=$this->ManagerConfig['error_log_multiline'];
    
    // assign the debuglevel to the error classes.
    // but override debuglevel to zero if the debugmethod isn't echo or echoplain (because they only support echo method)
    Web2All_Manager_ErrorHandler::$DebugLevel=(($this->DebugMethod=='echo' || $this->DebugMethod=='echoplain') ? $this->DebugLevel : 0);
    Web2All_Manager_ExceptionHandler::$DebugLevel=(($this->DebugMethod=='echo' || $this->DebugMethod=='echoplain') ? $this->DebugLevel : 0);
    Web2All_Manager_ErrorHandler::$allowErrorSuppression=$this->ManagerConfig['allow_error_suppression'];
    
    /*
     * if needed add the include path to the php include path
     */
    if(!defined('WEB2ALL_MANAGER_MAIN_INCLUDEPATH_APPENDED') && $this->ManagerConfig['add_includepath_to_path']){
      define('WEB2ALL_MANAGER_MAIN_INCLUDEPATH_APPENDED', true);
      $include_path=dirname(__FILE__).'/../../';
      set_include_path($include_path . PATH_SEPARATOR . get_include_path() );
    }
    
    // configure assertion handling
    assert_options(ASSERT_ACTIVE,    $this->ManagerConfig['assertionsenabled']);
    assert_options(ASSERT_BAIL,      false);
    assert_options(ASSERT_WARNING,   false);
    assert_options(ASSERT_CALLBACK,  $this->ManagerConfig['assertion_callback_method']);
  }
  
  /**
   * Gets executed when request terminates
   * 
   * WARNING: do not call exit() or errorhandling will break
   */
  public function onRequestTerminate() 
  {
    // lets see if there were unhandled fatal errors
    $lasterror=error_get_last();
    if(!is_null($lasterror)){
      if($lasterror['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR)){
        // fatal error occured
        // increase memory limit so we we can at least complete our error handling (in case it was Allowed memory size exhausted)
        // (adding 1MB, unless already unlimited)
        $memlim=Web2All_PHP_INI::getBytes(ini_get('memory_limit'));
        if($memlim!=-1){
          ini_set('memory_limit',$memlim+1000000);
        }
        // then add this error to the observers
        $error = Web2All_Manager_Error::getInstance();
        $exception = new Web2All_Manager_TriggerException($lasterror['type'], $lasterror['message'], $lasterror['file'], $lasterror['line'], array());
        if ($error) {
          $error->setState($exception,$lasterror['type']);
        }
      }
    }
  }
  
  /**
   * Set custom error handlers
   */
  public function setErrorHandlers() 
  {
    set_error_handler(array("Web2All_Manager_ErrorHandler", "errorHandlerCallback"), $this->ManagerConfig['error_reporting_level']);
    set_exception_handler(array("Web2All_Manager_ExceptionHandler", 'errorHandlerCallback'));
  }
  
  /**
   * Restore original error handlers
   */
  public function restoreErrorHandlers()
  {
    restore_error_handler();
    restore_exception_handler();
  }
  
  /**
   * flush e-mail errors if any
   * 
   * If there are any errors queued for emailing they will be e-mailed and the 
   * errorstate will be reset. This is useful for long running processes like daemons.
   */
  public function flushEmailErrors() {
    $this->PluginGlobal->Web2All_Manager_ErrorObserverable()->flushEmailErrors();
  }
  
  /**
   * Suppress trace information when error occurs
   *
   */
  public function suppressTrace()
  {
    $errhnd=Web2All_Manager_Error::getInstance();
    $errhnd->suppressTrace();
  }
  
  /**
   * Enable trace information when error occurs (default)
   *
   */
  public function enableTrace()
  {
    $errhnd=Web2All_Manager_Error::getInstance();
    $errhnd->enableTrace();
  }
  

  /**
   * Log the debug message to STDOUT (html) or STDERR
   * Automaticly adds BR and newline when outputting to STDOUT.
   * 
   * depends on config key 'debugmethod' which can be 'echo' or 'echoplain' or 'error_log'
   *
   * @param string $message
   */
  public function debugLog($message)
  {
    if($this->DebugAddTime){
      // adding datetime to message (useful when DebugMethod=='echoplain')
      if($this->DebugMultiLine){
        // multiline is enabled, so add the time for each line in the $message
        $message_changed='';
        foreach(explode("\n",$message) as $sub_msg){
          if($message_changed!==''){
            // only add a newline for each newline we lost due to the explode
            $message_changed.="\n";
          }
          $message_changed.=date("Y-m-d H:i:s ").$sub_msg;
        }
        $message=$message_changed;
      }else{
        $message=date("Y-m-d H:i:s ").$message;
      }
    }
    if($this->DebugMethod=='error_log'){
      // error_log entries are automatically truncated at ~8130 chars.
      // by default the message is displayed as one single line where newlines are replaced by literal \n
      // in fact, all non ascii characters are replaced by escaped values (often \xnn)
      // we support two config settings to change this behaviour: error_log_multiline, error_log_wrap
      // both are enabled by default
      if($this->DebugMultiLine){
        foreach(explode("\n",$message) as $sub_msg){
          if($this->ManagerConfig['error_log_wrap']){
            self::error_log_wrap($sub_msg);
          }else{
            error_log($sub_msg);
          }
        }
      }else{
        if($this->ManagerConfig['error_log_wrap']){
          self::error_log_wrap($message);
        }else{
          error_log($message);
        }
      }
    } else if($this->DebugMethod=='echoplain'){
      echo $message."\n";
    }else{
      echo $message."<br />\n";
    }
  }
  
  /**
   * Log the message to the error_log, but wrap at 8000 bytes 
   * to prevent automatic cutoff by the PHP error_log function
   * 
   * @param string $message
   */
  public static function error_log_wrap($message)
  {
    // msglen is the remain length of the message that needs to be written.
    $msglen=strlen($message);
    $i=0;
    while($msglen>0){
      if($msglen>8000){
        // the remaining message that needs to be written is still longer than 8000 bytes
        // so error_log only a chunk (start at offset: number of itereations * 8000)
        error_log(substr($message,$i*8000,8000));
        $msglen=$msglen-8000;
      }else{
        // less than 8000 bytes left, write the remainder (end)
        error_log(substr($message,$i*8000));
        $msglen=0;
      }
      $i++;
    }
  }
  
  /**
   * Returns true if debug method accepts html, or false if not
   *
   * @return boolean
   */
  public function debugMethodAcceptsHtml()
  {
    if ($this->DebugMethod=='echo')
    {
      return true;
    }
    return false;
  }
  
  /**
   * Debug log the CPU used by the script
   * 
   * Please note its only updated every 10ms.
   * 
   * output: <user message>[u: <user time>ms / s:<system time>ms]
   *
   * @param string $message  [optional message to prepend]
   */
  public function debugLogCPU($message='')
  {
    // get resource usage
    $dat = getrusage();
    $message.='[u:'.substr(($dat["ru_utime.tv_sec"]*1e6+$dat["ru_utime.tv_usec"])-$this->startUserCPU,0,-3).'ms / s:'.substr(($dat["ru_stime.tv_sec"]*1e6+$dat["ru_stime.tv_usec"])-$this->startSystemCPU,0,-3).'ms]';
    $this->debugLog($message);
  }
  
  /**
   * Debug log the memory used by the script
   * 
   * output is in bytes
   *
   * @param string $message  [optional message to prepend]
   */
  public function debugLogMemory($message='')
  {
    $this->debugLog($message.memory_get_usage());
  }
  
  /**
   * Functie om een global pointer naar een class uit te vegen. Alleen de
   * pointer wordt verwijderd! De class blijft geinitialiseerd, en wordt niet
   * gedestruct. 
   * 
   * @param string $classname
   */
  final public function removeGlobalPointer($classname) {
    $this->global_data->remove($classname);
  }

  /**
   * Retrieve from Global storage
   *
   * @param string $name
   * @return mixed  pointer to Plugin or false when not found
   */
  public function getGlobalStorage($name) {
    if (!$this->GlobalStorageExists($name)) {
      return false;
    }
    return $this->global_data->get($name);
  }

  /**
   * Set Global storage
   *
   * @param string $name
   * @param unknown_type $value
   */
  public function setGlobalStorage($name,$value) {
    $this->global_data->set($name,$value);
  }

  /**
   * Checks if $name exists in Global Storage
   *
   * @param string $name
   * @return boolean
   */
  final public function globalStorageExists($classname) {
    if (!isset($this->global_data)) {
      throw new Exception("Global storare is not initialised, can't check if object ".$classname." exists, forget to load parent::__construct?");
    }
    return $this->global_data->offsetExists($classname);
  }
  
  /**
   * Load the classfile for the given class
   * 
   * This method will blindly include the first php file which it finds
   * for the given classname. It will not throw exceptions and won't indicate if the
   * operation succeeded. It is used by both the autoloader and the loadClass() method,
   * which is historically the method used to load a class.
   *
   * @param string  $classname
   * @param string  $loadscheme     [optional (Web2All|PEAR|INC|PLAIN) defaults to Web2All]
   * @param string  $package        [optional packagename]
   * @param boolean $set_includedir [optional bool, set true to add the package dir to include path]
   */
  public static function includeClass($classname, $loadscheme='Web2All', $package='', $set_includedir=false) {
    // $path will be the relative path to the classfile by exploding the namespace
    $path = '';
    $filename = $classname;
    // support namespaces
    if(strpos($classname,'\\')){
      // ok, contains namespace
      $path_parts = explode('\\',$classname);
      $part_count=count($path_parts);
      $filename = $path_parts[$part_count-1];
      $classname_without_namespaces = $filename;
      for ($i=0;($i<$part_count-1);$i++) {
        $path.=$path_parts[$i].DIRECTORY_SEPARATOR;
      }
    }else{
      $classname_without_namespaces = $classname;
    }
    
    if ($loadscheme!='PLAIN')
    {
      $path_parts = explode("_",$classname_without_namespaces);
      $part_count=count($path_parts);
      $filename = $path_parts[$part_count-1];
      for ($i=0;($i<$part_count-1);$i++) {
        $path.=$path_parts[$i].DIRECTORY_SEPARATOR;
      }
    }
    
    // depending on the scheme, select the suffix
    // PEAR class files do not have the .class in the name.
    // The Web2All scheme historically used ".class.php", but for 
    // better compatibility we now also support the plain ".php".
    $classfilesuffixes=array('.class.php','.php');
    switch($loadscheme){
      case 'PEAR':
      case 'PLAIN':
        $classfilesuffixes=array('.php');
        break;
      case 'INC':
        $classfilesuffixes=array('.inc.php');
        break;
    }
    
    foreach(self::$frameworkRoots as $include_path){
      if ($package) {
        $include_path.=$package.DIRECTORY_SEPARATOR;
      }
      foreach($classfilesuffixes as $classfilesuffix){
        if(is_readable($include_path.$path.$filename.$classfilesuffix)){
          // if set_includedir is true, then we have the include path of the package to
          // the PHP environment include path
          if ($set_includedir) {
            $pathArray = explode( PATH_SEPARATOR, get_include_path() );
            // only add the path if its not already in the include path
            if (!in_array($include_path,$pathArray)) {
              $pathArray[]=$include_path;
              set_include_path(implode(PATH_SEPARATOR,$pathArray));
            }
          }
          include_once($include_path.$path.$filename.$classfilesuffix);
          // ok once we found a file, we are done
          return;
        }
      }
    }
  }

  /**
   * Include php file for Web2All_Manager_Plugin
   *
   * @param string  $classname
   * @param string  $loadscheme     [optional (Web2All|PEAR|INC|PLAIN) defaults to Web2All]
   * @param string  $package        [optional packagename]
   * @param boolean $set_includedir [optional bool, set true to add the package dir to include path]
   */
  public static function loadClass($classname, $loadscheme='Web2All', $package='', $set_includedir=false) {
    $class_exists=class_exists($classname) || interface_exists($classname);
    if ($class_exists && !$set_includedir) {
      // if class already exists, we don't need to do a thing
      // but one CAVEAT: if the class was loaded with $set_includedir==false and now the $set_includedir==true
      // then we do not add the path to the include path. 
      return;
    }
    
    self::includeClass($classname, $loadscheme, $package, $set_includedir);
  }

  /**
   * Initialize Web2All_Manager_Plugin
   * returns Web2All_Manager_Plugin object
   *
   * @param string $classname
   * @param array $arguments
   * @param boolean $isplugin  Force the first constructor param to be the 
   *                           Web2All_Manager_Main object. Set false for 
   *                           automatically detection if this is required.
   * @return object
   */
  public function initClass($classname,$arguments=array(),$isplugin=true) 
  {
    // when we no longer have any PHP 5.2 we can replace below with static::loadClass($classname);
    // we cannot use self::loadClass($classname); because it will break extending Main classes which
    // redefine the loadClass method. (we need late static binding)
    /*
     * If class cannot be found, include corresponding file
     */
    $this->loadClass($classname);
    
    /*
     * If $classname still doesn't exists, the class cannot be started
     */
    if (!class_exists($classname)) {
      throw new Exception("Class whith name '$classname' does not exists. Cannot initialise $classname",E_USER_ERROR);
    }
    
    /*
     * Start class and return object
     */
    $reflectionObj = new ReflectionClass($classname);
    if($reflectionObj->isSubclassOf('Web2All_Manager_Plugin') || $isplugin){
      // directly extends Web2All_Manager_Plugin so it expects the Web2All_Manager_Main object
      // as first constructor param. Or the $isplugin is set true so force first param to
      // Web2All_Manager_Main object.
      array_unshift($arguments,$this);
    }elseif($reflectionObj->implementsInterface('Web2All_Manager_PluginInterface')){
      // class does not extend Web2All_Manager_Plugin, but the class still implements 
      // the Web2All_Manager_PluginInterface so we can call the setWeb2All method to 
      // init the object after construction.
      $obj=call_user_func_array(array(&$reflectionObj, 'newInstance'), $arguments);
      $obj->setWeb2All($this);
      return $obj;
    }
    return call_user_func_array(array(&$reflectionObj, 'newInstance'), $arguments);
  }
  
  /**
   * This function handles failed assertions (callback method)
   *
   * @param string $script
   * @param int $line
   * @param string $message
   */
  public static function assertHandler($script, $line, $message)
  {
    trigger_error('Assertion failed: in '."$script, on line $line, $message",E_USER_WARNING);
  }
  
  /**
   * Returns the IP address from which the user is viewing the current page.
   * Defaults to $_SERVER['REMOTE_ADDR'], unless the get_ip_from_xforwarded 
   * config is set to true, then $ _SERVER["HTTP_X_FORWARDED_FOR"] is used 
   * if available.
   * 
   * Use get_ip_from_xforwarded only in a situation where we can trust 
   * HTTP_X_FORWARDED_FOR. Like when we generate this field ourself on our own
   * proxy server. 
   * 
   * For logging purposes its perhaps better to both log REMOTE_ADDR and HTTP_X_FORWARDED_FOR
   * when don't use our own proxy server.
   * 
   * X-Forwarded-For: http://en.wikipedia.org/wiki/X-Forwarded-For
   * 
   * @return string
   */
  public function getIP() {

    if ($this->ManagerConfig['get_ip_from_xforwarded'] && isset($_SERVER["HTTP_X_FORWARDED_FOR"]) && trim(strtolower($_SERVER["HTTP_X_FORWARDED_FOR"]))!='unknown' ) {
      // When viewed through an anonymous proxy, the address string can contain multiple ip's separated by commas.
      // http://www.jamescrowley.co.uk/2007/06/19/gotcha-http-x-forwarded-for-returns-multiple-ip-addresses/
      $ip_array = explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"]);
      // Use last ip addres, the last ip address is from the previous proxy, or from the user itself if there 
      // was no previous proxy, HTTP_X_FORWARDED_FOR can't be trusted, only the last entry added by our (first) 
      // trusted proxy server is the most trusted one we know. 
      // The trusted_proxy_amount config defines how many proxies we can trust, defaults to 1
      for($i=0;$i<$this->ManagerConfig['trusted_proxy_amount'];$i++){
        if(count($ip_array)==0){
          // no more ips in HTTP_X_FORWARDED_FOR
          // this means there are less actual proxies than our trusted_proxy_amount setting.
          // so people are bypassing our proxy of the trusted_proxy_amount setting is wrong.
          break;
        }
        $ip = array_pop($ip_array);
      }
      return trim($ip);
    }
    if (!isset($_SERVER["REMOTE_ADDR"])) {
      return '';
    }
    return $_SERVER["REMOTE_ADDR"];
  }
  
  /**
   * Instantiates Web2All_Manager_Main
   * 
   * This shorthand instantiation has a few prequisites:
   * - the include file /include/include.php must exist
   * - this include file must have the WEB2ALL_CONFIG_CLASS constant defined
   * - the WEB2ALL_CONFIG_CLASS constant must contain the classname of a config class which
   *   extends Web2All_Manager_Config
   * 
   * Will generate E_COMPILE_ERROR if include.php does not exist
   * Will throw Exception if classname is not defined or cannot be found
   * 
   * This is a replacement for the auto_prepend_file mechanism we used in the
   * .htaccess file.
   * 
   * Usage: 
   *   $web2all=Web2All_Manager_Main::newInstance();
   * 
   * @param string $includefile  [optional, defaults to include.php] include file relative to
   *                             the include directory.
   * @return Web2All_Manager_Main
   */
  public static function newInstance($includefile='include.php')
  {
    // throws compile error if not found
    require_once(dirname(__FILE__).'/../../'.$includefile);
    
    if(!defined('WEB2ALL_CONFIG_CLASS')){
      throw new Exception('WEB2ALL_CONFIG_CLASS not defined!');
    }
    
    // load config class
    Web2All_Manager_Main::loadClass(WEB2ALL_CONFIG_CLASS);
    
    // instantiate config class
    $classname=WEB2ALL_CONFIG_CLASS;
    $config = new $classname();
    
    // instantiate Web2All_Manager_Main
    return new Web2All_Manager_Main($config);
  }
  
  /**
   * Registers an include root directory
   * 
   * Used by autoloader and loadClass()
   * 
   * @param string $root  directory path
   * @return boolean  was the directory added
   */
  public static function registerIncludeRoot($root=null)
  {
    if(is_null($root)){
      $root = dirname(__FILE__).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR;
    }
    if(!in_array($root, self::$frameworkRoots)){
      self::$frameworkRoots[] = $root;
      return true;
    }
    return false;
  }
  
  /**
   * Unregisters an include root directory
   * 
   * @param string $root  directory path
   * @return boolean  was the directory removed
   */
  public static function unregisterIncludeRoot($root)
  {
    $found_key=array_search($root, self::$frameworkRoots, true);
    if($found_key!==false){
      unset(self::$frameworkRoots[$found_key]);
      return true;
    }
    return false;
  }
  
  /**
   * Get all registered include root directories
   * 
   * @return array
   */
  public static function getRegisteredIncludeRoots()
  {
    return self::$frameworkRoots;
  }
  
  /**
   * Registers an autoloader for the Web2All framework
   * 
   * it will call the Web2All_Manager_Main::loadClass
   * 
   * @return boolean
   */
  public static function registerAutoloader($root=null)
  {
    self::registerIncludeRoot($root);
    if(!self::$autoloaderAdded){
      self::$autoloaderAdded = true;
      return spl_autoload_register(array('Web2All_Manager_Main','loadClass'));
    }
    return true;
  }
  
}

/**
 * @name Web2All_Manager_ErrorObserverable class
 * 
 * Warning: this class could use some refactoring, it implements the Observer Design Pattern,
 * but not very logically. For some reason the interface methods are not used. Also it actually
 * contains two separate lists of observers: 
 * - observer_names : list of classnames, the observers are stored in PluginGlobal storage and
 *                    instantiated on demand. Three observers are added by default here.
 * - observers      : list of observer objects. Not actually used unless someone adds custom
 *                    observers with addObserver() or attach() method. As far as I know this
 *                    was never actually done.
 * 
 * @author Hans Oostendorp
 * @copyright (c) Copyright 2007-2014 by Web2All
 * @version 0.2
 * @since 2007-05-29
 **/
class Web2All_Manager_ErrorObserverable extends Web2All_Manager_Plugin implements SplSubject {

  /**
   * list of uninilialised observer objects by name
   *
   * @var array
   */
  protected $observer_names = array();

  /**
   * list of initialised observer objects
   *
   * @var array
   */
  protected $observers = array();

  /**
   * Store for occured Exception/Error
   *
   * @var Web2All_Manager_ErrorData
   */
  protected $errordata;

  /**
   * Constructor, add Observers, depending on enviroment
   */
  public function __construct(Web2All_Manager_Main $Web2All) {
    if($Web2All->DebugLevel >= Web2All_Manager_Main::DEBUGLEVEL_FULL) {
      echo "[Start de errorObserverable Class]<br />\n";
    }
    parent::__construct($Web2All);
    /**
     * attach default errorObservers
     */
    $this->addObserver_name('Web2All_ErrorObserver_ErrorLog');
    $this->addObserver_name('Web2All_ErrorObserver_Email');
    $this->addObserver_name('Web2All_ErrorObserver_Display');
  }

  /**
   * Adds an observer to the set of observers for this object, provided that it is not the same as some observer already in the set.
   *
   * @param $observer SplObserver, Object of an observer to be added.
   */
  public function addObserver(SplObserver $observer) {
    if(!$this->containObserver($observer)) {
      $this->observers[] = $observer;
    }
  }

  /**
   * Adds an observer to the set of observers for this object, provided that it is not the same as some observer already in the set.
   *
   * @param $classname String, classname of an observer to be added.
   */
  public function addObserver_name($classname) {
    if(!$this->containObserver_name($classname)) {
      $this->observer_names[] = $classname;
    }
  }

  /**
   * Deletes an observer from the set of observers of this object.
   *
   * @param $observer SplObserver, Object of the observer to be deleted.
   */
  public function deleteObserver(SplObserver $observer) {
    $this->observers = array_diff($this->observers, array($observer));
  }

  /**
   * Deletes an observer from the set of observers of this object.
   *
   * @param $classname String, classname of the observer to be deleted.
   */
  public function deleteObserver_name($classname) {
    $this->observer_names = array_diff($this->observer_names, array($classname));
  }

  /**
   * Clears the observer list so that this object no longer has any observers.
   */
  public function deleteObservers() {
    unset($this->observers);
    $this->observers = array();
    unset($this->observer_names);
    $this->observer_names = array();
  }

  /**
   * If this object has changed, as indicated by the hasChanged method, then
   * start observers as needed and notify them, and then call the clearChanged
   * method to indicate that this object has no longer changed.
   */
  public function notifyObservers() {
    /**
     * restore original error handler, to avoid problems when errors occurres
     * in handling other errors
     */
    $this->Web2All->restoreErrorHandlers();
    foreach($this->observers as $observer) {
      $observer->update($this);
    }
    foreach($this->observer_names as $classname) {
      $this->Web2All->PluginGlobal->$classname->update($this);
    }
    // set custom error handlers back
    $this->Web2All->setErrorHandlers();
  }
  
  /**
   * flush e-mail errors if any
   * 
   * If there are any errors queued for emailing they will be e-mailed and the 
   * errorstate will be reset. This is useful for long running processes like daemons.
   */
  public function flushEmailErrors() {
    foreach($this->observers as $observer) {
      if(get_class($observer)=='Web2All_ErrorObserver_Email'){
        $observer->flushErrors();
      }
    }
    foreach($this->observer_names as $classname) {
      if($classname=='Web2All_ErrorObserver_Email'){
        $this->Web2All->PluginGlobal->$classname->flushErrors();
      }
    }
  }
  
  /**
   * Returns the number of observers of this Observable object.
   *
   * @return integer the number of observers of this object.
   */
  public function countObservers() {
    return count($this->observers)+count($this->observer_names);
  }

  /**
   * Check if observer already in the list
   *
   * @param $observer SplObserver
   * @return bool
   */
  public function containObserver(SplObserver $observer) {
    return in_array($observer, $this->observers);
  }

  /**
   * Check if observer already in the list
   *
   * @param $classname
   * @return bool
   */
  public function containObserver_name($classname) {
    return in_array($classname, $this->observer_names);
  }

  /**
   * Add observer
   *
   * @param $observer SplObserver
   */
  public function attach(SplObserver $observer) {
    $this->addObserver($observer);
  }

  /**
   * Add observer
   *
   * @param $classname
   */
  public function attach_name($classname) {
    $this->addObserver_name($classname);
  }

  /**
   * Remove observer
   *
   * @param SplObserver $observer
   */
  public function detach(SplObserver $observer) {
    $this->deleteObserver($observer);
  }

  /**
   * Remove observer
   *
   * @param String $classname
   */
  public function detach_name($classname) {
    $this->deleteObserver_name($classname);
  }

  /**
   * Notify observers
   */
  public function notify() {
    $this->notifyObservers();
  }

  /**
   * @return Web2All_Manager_ErrorData
   */
  public function getState() {
    return $this->errordata;
  }

  /**
   * @param Web2All_Manager_ErrorData
   */
  public function setState($errordata) {
    $this->errordata = $errordata;
    $this->notify();
  }
}

/**
 * Custom Exception, used by errorhandler when encountering a "trigger_error"
 *
 * @package Web2All_Manager
 * @name Web2All_Manager_TriggerException
 * @author Merijn van den Kroonenberg
 */
class Web2All_Manager_TriggerException extends Exception {
  public $context;
  
  public $triggertrace;
  
  public function __construct($code, $string, $file, $line, $context, $triggertrace=array()) {
    parent::__construct($string, $code);
    
    if ($this->getCode()==0) {
      $this->code = E_USER_ERROR;
    }
    $this->line = $line;
    $this->file = $file;
    $this->context = $context;
    // store own trace, filled by debug_backtrace(), if param is available
    // getTrace() contains invalid traces when invoked by trigger_error, so we store our own trace
    // first entry contains calling errorHandlerCallback, is not needed, pop it off
    if (count($triggertrace)>0)
    {
      unset($triggertrace[0]);
      $this->triggertrace = $triggertrace;
    }
  }

}

/**
 * @name Web2All_Manager_ErrorHandler class
 * @author Hans Oostendorp
 * @copyright (c) Copyright 2007 by Web2All
 * @version 0.2
 * @since 2007-05-29
 *
 * Catch all standard PHP error's and throw exception instead, when needed
 */
class Web2All_Manager_ErrorHandler {
  public static $DebugLevel=0;
  public static $allowErrorSuppression=false;
  
  public static function errorHandlerCallback($code, $string, $file, $line, $context) {
    if(self::$DebugLevel >= Web2All_Manager_Main::DEBUGLEVEL_FULL) {
      echo "[Start de Web2All_Manager_ErrorHandler->errorHandlerCallback]<br />\n";
    }
    
    // check if error is suppressed
    // please note this only works if the error_reporting level is not 0 by default 
    if ( self::$allowErrorSuppression && error_reporting() == 0 ) {
      if(self::$DebugLevel >= Web2All_Manager_Main::DEBUGLEVEL_FULL) {
        echo "Web2All_Manager_ErrorHandler->errorHandlerCallback: error was suppressed<br />\n";
      }
      return;
    }

    $exception = new Web2All_Manager_TriggerException($code, $string, $file, $line, $context, debug_backtrace());
    
    /*
     * Web2All_Manager_ExceptionHandler cannot extend
     * Web2All_Manager_Main class, and needs a seperate class for
     * triggering error
     */
    $error = Web2All_Manager_Error::getInstance();
    if (!$error) {
      die("Error class not started yet, cannot handle error!");
    }
    $error->setState($exception,$exception->getCode());
    
    // now, for some error codes, we have to halt execution (most below cannot be catched anyway)
    if($code & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR)){
      exit(1);
    }
  }
}

/**
 * @name Web2All_Manager_ExceptionHandler class
 * @author Hans Oostendorp
 * @copyright (c) Copyright 2007 by Web2All
 * @version 0.2
 * @since 2007-05-29
 *
 * Used to catch all PHP exceptions and trigger Web2All error handling
 */
class Web2All_Manager_ExceptionHandler {
  public static $DebugLevel=0;
  
  public static function errorHandlerCallback($exception) {
    if(self::$DebugLevel >= Web2All_Manager_Main::DEBUGLEVEL_FULL) {
      echo "[Start de Web2All_Manager_ExceptionHandler->errorHandlerCallback]<br />\n";
    }
    /*
     * Web2All_Manager_ExceptionHandler cannot extend Web2All class, and
     * needs a seperate class for triggering error
     */
    $error = Web2All_Manager_Error::getInstance();
    if (!$error) {
      die("Error class not started yet, cannot handle error!");
    }
    $error->setState($exception,E_USER_ERROR);
    // PHP will exit after this callback, but will do so with status 0
    // as it is an unhandled exception we want a non-zero exit status
    exit(1);
  }
}

/**
 * @name Web2All_Manager_ErrorData class
 * @author Merijn van den Kroonenberg
 * @copyright (c) Copyright 2007 Web2All B.V.
 * @version 0.1
 * @since 2007-07-06
 *
 * Used to store error information for the errorobservables
 */
class Web2All_Manager_ErrorData {
  public $severity;
  public $exception;
  public $suppresstrace;
  
  public function __construct($exception, $severity, $suppresstrace=false) {
    $this->setSeverity($severity);
    $this->setException($exception);
    $this->suppresstrace=$suppresstrace;
  }

  public function getSeverity() {
    return $this->severity;
  }
  
  public function setSeverity($severity) {
    $this->severity=$severity;
  }
  
  public function getException() {
    return $this->exception;
  }
  
  public function setException($exception) {
    $this->exception=$exception;
  }
  
  /**
   * check if error trace information must be suppressed
   * 
   * returns true if it must be suppressed, false otherwise.
   *
   * @return boolean
   */
  public function getTraceSuppression()
  {
    return $this->suppresstrace;
  }
  
}

/**
 * @name Web2All_Manager_Registry class
 * @author Hans Oostendorp
 * @copyright (c) Copyright 2007 by Web2All
 * @version 0.1
 * @since 2007-05-16
 */
class Web2All_Manager_Registry Implements ArrayAccess, Iterator, Countable {
  const VERSION = 0.1;
  static $instance = false;
  private $vars = array();
  public static $DebugLevel=0;

  /**
   * Constructor, can only be triggerd one time, by
   * Web2All_Manager_Registry class only
   */
  private function __construct() {
    if(self::$DebugLevel >= Web2All_Manager_Main::DEBUGLEVEL_FULL) {
      echo "[Start de Web2All_Manager_Registry Class]<br />\n";
    }
    $this->vars = array();
  }

  /**
   * factory method to return the singleton instance
   *
   * @return Web2All_Manager_Registry object
  */
  public static function getInstance() {
    if(self::$DebugLevel >= Web2All_Manager_Main::DEBUGLEVEL_FULL) {
      echo "[Vraag een instantie van de Web2All_Manager_Registry Class op]<br />\n";
    }
    if (!Web2All_Manager_Registry::$instance) {
      Web2All_Manager_Registry::$instance = new Web2All_Manager_Registry;
    }
    return Web2All_Manager_Registry::$instance;
  }

  /**
   * Triggered when Web2All_Manager_Registry class is accessed directly
   * Get value for registry $key, returns false if not found
   *
   * @param string $key
   * @return unknown
   */
  public function __get($key) {
    return $this->offsetGet($key);
  }

  /**
   * Triggered when Web2All_Manager_Registry class is accessed directly
   * Set registry variable $key whith value $var
   * Returns if succeeded or not
   *
   * @param string $key
   * @param unknown_type $value
   * @return boolean
   */
  public function __set($key,$value) {
    return $this->offsetSet($key,$value);
  }

  /**
   * Set registry variable $key whith value $var
   * Returns if succeeded or not
   *
   * @param string $key
   * @param unknown_type $value
   * @return boolean
   */
  public function set($key,$value) {
    return $this->offsetSet($key,$value);
  }

  /**
   * Get value for registry $key, returns false if not found
   *
   * @param string $key
   * @return unknown
   */
  public function get($key) {
    return $this->offsetGet($key);
  }

  /**
   * Remove variable $key from registry, returns false if not found
   *
   * @param unknown_type $var
   * @return boolean
   */
  public function remove($key) {
    return $this->offsetUnset($key);
  }

  /**
   * Echo's content of Web2All_Manager_Registry
   *
   * @return unknown
   */
  public function show_reg() {
?>
<pre><?php print_r($this->vars); ?></pre>
<?php
    return;
  }

  /**
   * -- ArrayAccess
   * Checks whether offset exists in Web2All_Manager_Registry
   *
   * @param string $offset
   * @return boolean
   */
  public function offsetExists($offset) {
    return isset($this->vars[$offset]);
  }

  /**
   * -- ArrayAccess
   * Get value for registry $key, returns false if not found
   *
   * @param string $offset
   * @return unknown
   */
  public function offsetGet($offset) {
    if (!$this->offsetExists($offset)) {
      throw new RangeException("Var $offset doesn't exists in Web2All_Manager_Registry");
      return false;
    }
    return $this->vars[$offset];
  }

  /**
   * -- ArrayAccess
   * Set registry variable $offset whith value $var
   * Returns if succeeded or not
   *
   * @param string $offset
   * @param unknown_type $value
   * @return boolean
   */
  public function offsetSet($offset,$value) {
    if ($this->offsetExists($offset)) {
      throw new RangeException("Var $offset allready exists in Web2All_Manager_Registry");
      return false;
    }
    $this->vars[$offset]=&$value;
    return true;
  }

  /**
   * Remove variable $key from registry, returns false if not found
   *
   * @param unknown_type $var
   * @return boolean
   */
  public function offsetUnset($offset) {
    if (!$this->offsetExists($offset)) {
      throw new RangeException("Var $offset doesn't exists in Web2All_Manager_Registry");
      return false;
    }
    unset($this->vars[$offset]);
    return true;
  }

  /**
   * -- Iterator
   * Get value for current registry position
   *
   * @return unknown
   */
  public function current() {
    return current($this->vars);
  }

  /**
   * -- Iterator
   * Advance the internal array pointer of $vars
   * Returns the array value in the next place that's pointed to by the internal
   * array pointer, or FALSE if there are no more elements.
   *
   * @return mixed
   */
  public function next() {
    return next($this->vars);
  }

  /**
   * -- Iterator
   * Returns the index element of the current array position
   *
   * @return mixed
   */
  public function key() {
    return key($this->vars);
  }

  /**
   * -- Iterator
   * Sets the internal array pointer of $vars to the beginning of array
   * Returns TRUE on success or FALSE on failure.
   *
   * @return boolean
   */
  public function rewind() {
    reset($this->vars);
  }

  /**
   * -- Iterator
   * Checks if there is a current element in the array
   * Returns TRUE on success or FALSE on failure.
   *
   * @return boolean
   */
  public function valid() {
    return ($this->current()!==false);
  }

  /**
   * -- Countable
   * Count elements in array
   *
   * @return int
   */
  public function count() {
    return count($this->vars);
  }

  /**
   * Function to stop cloning of Web2All_Manager_Registry class
   */
  public function __clone() {
    throw new Exception('Clone of registry class is not allowed.', E_USER_ERROR);
  }
}

/**
 * Web2All_Manager_Config
 * 
 * This is the baseclass for all plugin configs.
 * Every project can extend this class. The extended class can
 * specify a protected property for every plugin which needs a config entry.
 *
 */
class Web2All_Manager_Config {

  /**
   * constructor
   *
   * allow calling of parent constructor in extending classes
   */
  public function __construct() {
    
  }
  
  /**
   * prevent setting of properties
   *
   */
  public final function __set($member, $value) {
    throw new Exception('You cannot set a constant. ('.$member.')',E_USER_ERROR);
  }
  
  /**
   * Allow getting of protected properties
   */
  public final function __get($member) {
    if (!isset($this->$member)) {
      return NULL;
    }
    return $this->$member;
  }
  
  /**
   * Build a config array for a specific plugin, if config settings are not set,
   * use the default config value. All keys in $overrulingconfig will always
   * override the values in this config and the defaultconfig.
   *
   * @param string $pluginname
   * @param array $defaultpluginconfig
   * @param array $overrulingconfig  [optional] all key/values will be leading
   * @return array
   */
  public function makeConfig($pluginname,$defaultpluginconfig,$overrulingconfig=null)
  {
    $pluginconfig=array();
    
    $customconfig=array();
    if (isset($this->$pluginname) && is_array($this->$pluginname)) {
      $customconfig=$this->$pluginname;
    }
    $pluginconfig=array_merge($defaultpluginconfig,$customconfig);
    
    // override config values
    if (isset($overrulingconfig) && is_array($overrulingconfig)) {
      $pluginconfig=array_merge($pluginconfig,$overrulingconfig);
    }
    
    return $pluginconfig;
  }
  
  /**
   * Validate a specific plugin config against the given array
   * with config keys. The pluginconfig must exist and each configkey
   * in requiredconfig must exist also. When not valid, an exception will be thrown
   * (can be catched in calling method)
   *
   * @param string $pluginname
   * @param array $requiredconfig
   * @param array $overrulingconfig  [optional] overruled config values do not need to be present in config
   * @return boolean  (always true) throws Exception on error
   */
  function validateConfig($pluginname,$requiredconfig=array(),$overrulingconfig=null)
  {
    // first check if the pluginconfig is available at all
    if (!(isset($this->$pluginname) && is_array($this->$pluginname))) {
      throw new Exception('No config defined for plugin '.$pluginname);
    }
    // then for each key in $requiredconfig check if it is available in this config and
    // validate it
    // *TODO* implement different validation types.
    foreach ($requiredconfig as $configkey => $validation_type) {
      if (!array_key_exists($configkey,$this->$pluginname) && !(is_array($overrulingconfig) && array_key_exists($configkey,$overrulingconfig))) {
        // config key doesn't exist, raise error
        throw new Exception('Required config key '.$configkey.' for plugin '.$pluginname.' is not defined');
      }
      
    }
    return true;
  }
  
  
}

/**
 * Web2All error handling.
 * This class must be initialised in the beginning of the script, usally by the
 * Web2All_Manager_Main class. The errorHandler and exceptionHandler
 * (who are triggered by PHP of the user), trigger this class. This error class
 * has access to the Web2All_Manager_Main class and thus the
 * ErrorOverServerable.
 * All we have todo is change the state of the error Observerable
 *
 * @name Web2All_Manager_Error class
 * @author Hans Oostendorp
 * @copyright (c) Copyright 2007 by Web2All
 * @version 0.1
 * @since 2007-05-29
 */
class Web2All_Manager_Error extends Web2All_Manager_Plugin {
  const VERSION = 0.1;
  static $instance = false;
  
  protected $suppresstrace=false;
  
  /**
   * Override the web2all property so it becomes public and
   * can be accessed from the static methods.
   *
   * @var Web2All_Manager_Main
   */
  public $Web2All;

  /**
   * Constructor
   */
  public function __construct(Web2All_Manager_Main $Web2All) {
    if($Web2All->DebugLevel >= Web2All_Manager_Main::DEBUGLEVEL_FULL) {
      $Web2All->debugLog( "[Start de Web2All_Manager_Error Class]");
    }
    parent::__construct($Web2All);
  }
  
  /**
   * factory method to return the singleton instance
   *
   * @return Web2All_Manager_Error object
  */
  public static function getInstance() {
    if(self::$instance && self::$instance->Web2All->DebugLevel >= Web2All_Manager_Main::DEBUGLEVEL_FULL) {
      // we can only log if $instance is set, because this a static method and
      // we don't have a reference to the web2all class.
      self::$instance->Web2All->debugLog( "[Vraag een instantie van de Web2All_Manager_Error Class op]");
    }
    return self::$instance;
  }

  public static function newInstance(Web2All_Manager_Main $Web2All) {
    if($Web2All->DebugLevel >= Web2All_Manager_Main::DEBUGLEVEL_FULL) {
      $Web2All->debugLog(  "[Set een instantie van de Web2All_Manager_Error Class]");
    }
    if (Web2All_Manager_Error::getInstance()) {
      die("Can't start new instance of Web2All_Manager_Error class twice");
    }
    Web2All_Manager_Error::$instance = new Web2All_Manager_Error($Web2All);
    return  Web2All_Manager_Error::$instance;
  }

  public function setState($exception,$severity) {
    if($this->Web2All->DebugLevel >= Web2All_Manager_Main::DEBUGLEVEL_FULL) {
      $this->Web2All->debugLog( "Web2All_Manager_Error->setState");
    }
    $errobj=$this->Web2All->Factory->Web2All_Manager_ErrorData($exception,$severity,$this->suppresstrace);
    $this->Web2All->PluginGlobal->Web2All_Manager_ErrorObserverable->setState($errobj);
  }

  public function suppressTrace()
  {
    $this->suppresstrace=true;
  }
  
  public function enableTrace()
  {
    $this->suppresstrace=false;
  }
  
  
  /**
   * Returns version number of this class
   *
   * @return Double, version number
   */
  public function getVersion() {
    return (double)self::VERSION;
  }
}

/**
 * Global Web2All_Manager_Plugin Loader
 * Auto include and initialise Web2All_Manager_Plugins for global use
 * DOES NOT SUPPORT classes with constructor params (they get lost)
 *
 * @name Web2All_Manager_PluginLoaderGlobal class
 * @author Hans Oostendorp
 * @copyright (c) Copyright 2007-2009 by Web2All
 * @version 0.1
 * @since 2007-06-14
 */
class Web2All_Manager_PluginLoaderGlobal extends Web2All_Manager_Plugin {

  /**
   * Deze functie wordt automatisch aangeroepen als direct een method van een
   * class wordt aangeroepen.
   * (bijvoorbeeld $Web2All->Web2All_Manager_PluginLoaderPrivate->test->print();
   * Deze functie krijgt dan test mee als parameter.
   */
  public function __get($classname) {
    if (!$this->Web2All->globalStorageExists($classname)) {
      $class = $this->Web2All->initClass($classname);
      $this->Web2All->setGlobalStorage($classname,$class);
    }
    return $this->Web2All->getGlobalStorage($classname);
  }

  /**
   * Deze functie wordt automatisch aangeroepen als direct een class
   * wordt aangeroepen zonder method. Bijvoorbeeld $Web2All->test(4);
   * Dit betekend dat een request wordt gedaan om die class te initialiseren.
   */
  public function __call($classname,$arguments) {
    // this could be adjusted to serialize and hash the arguments and use them 
    // as storage key with the classname. right now the arguments are (silently) ignored.
    // and i consider this bad behaviour. :merijn
    if ($this->Web2All->globalStorageExists($classname)) {
      if (count($arguments)>0) {
        throw new exception("Class with name '$classname' allready exists in global storage of Web2All_Manager_Main class, can't initialize with new constructor params");
      }
      return $this->Web2All->getGlobalStorage($classname);
    }
    $class = $this->Web2All->initClass($classname);
    $this->Web2All->setGlobalStorage($classname,$class);
    return $this->Web2All->getGlobalStorage($classname);
  }

}

/**
 * Private Web2All_Manager_Plugin Loader
 * Auto include en initialise Web2All_Manager_Plugins for private use
 *
 * @name Web2All_Manager_PluginLoaderPrivate class
 * @author Hans Oostendorp
 * @copyright (c) Copyright 2007 by Web2All
 * @version 0.1
 * @since 2007-06-14
 */
class Web2All_Manager_PluginLoaderPrivate extends Web2All_Manager_Plugin {

  /**
   * Deze functie wordt automatisch aangeroepen als direct een method van een
   * class wordt aangeroepen.
   * (bijvoorbeeld $Web2All->Web2All_Manager_PluginLoaderPrivate->test->print();
   * Deze functie krijgt dan test mee als parameter. Het gaat altijd om een
   * nieuwe class die geinitialiseerd moet worden, aangezien de pointers van
   * classen bij private geinitialiseerde plugins niet opgeslagen worden. De
   * client side moet dat zelf regelen. De __get wordt eigenlijk alleen
   * getriggerd als eenmalig een methos van een class aangeroepen wordt en de
   * class daarna niet meer nodig is.
   */
  public function __get($classname) {
    return $this->Web2All->initClass($classname);
  }

  /**
   * Deze functie wordt automatisch aangeroepen als direct een class
   * wordt aangeroepen zonder method. Bijvoorbeeld $Web2All->test(4);
   * Dit betekend dat een request wordt gedaan om die class te initialiseren.
   * Bij private aangeroepen Web2All_Manager_Plugins zal voornamelijk __call getriggerd worden
   * en niet __get.
   */
  public function __call($classname,$arguments) {
    return $this->Web2All->initClass($classname,$arguments);
  }

}

/**
 * Private Web2All_Manager_Factory Loader
 * Auto include en initialise non plugins for private use
 *
 * @name Web2All_Manager_Factory class
 * @author Hans Oostendorp
 * @copyright (c) Copyright 2007 by Web2All
 * @version 0.1
 * @since 2007-06-15
 */
class Web2All_Manager_Factory extends Web2All_Manager_Plugin {

  /**
   * Deze functie wordt automatisch aangeroepen als direct een method van een
   * class wordt aangeroepen.
   * (bijvoorbeeld $Web2All->Web2All_Manager_Factory->test->print();
   * Deze functie krijgt dan test mee als parameter. Het gaat altijd om een
   * nieuwe class die geinitialiseerd moet worden, aangezien de pointers van
   * classen bij private geinitialiseerde plugins niet opgeslagen worden. De
   * client side moet dat zelf regelen. De __get wordt eigenlijk alleen
   * getriggerd als eenmalig een method van een class aangeroepen wordt en de
   * class daarna niet meer nodig is.
   */
  public function __get($classname) {
    return $this->Web2All->initClass($classname,array(),false);
  }

  /**
   * Deze functie wordt automatisch aangeroepen als direct een class
   * wordt aangeroepen zonder method. Bijvoorbeeld $Web2All->test(4);
   * Dit betekend dat een request wordt gedaan om die class te initialiseren.
   * Bij private aangeroepen Web2All_Manager_Plugins zal voornamelijk __call
   * getriggerd worden en niet __get.
   */
  public function __call($classname,$arguments) {
    return $this->Web2All->initClass($classname,$arguments,false);
  }

}

/**
 * Private Web2All_Manager_ClassInclude Loader
 * Auto include non plugins for private use
 *
 * @name Web2All_Manager_ClassInclude class
 * @author Hans Oostendorp
 * @copyright (c) Copyright 2007 by Web2All
 * @version 0.2
 * @since 2007-07-05
 */
class Web2All_Manager_ClassInclude extends Web2All_Manager_Plugin {
  
  /**
   * Include php file 
   *
   * @param string  $classname
   * @param string  $loadscheme     [optional (Web2All|PEAR|INC|PLAIN) defaults to Web2All]
   * @param string  $package        [optional packagename]
   * @param boolean $set_includedir [optional bool, set true to add the package dir to include path]
   */
  public static function loadClassname($classname,$loadscheme='Web2All', $package='', $set_includedir=false) {
    Web2All_Manager_Main::loadClass($classname,$loadscheme,$package,$set_includedir);
  }

}

/**
 * Class for encapsualting a password string
 * 
 * Encapsulated passwords are not shown in the error trace
 * when an error occurs.
 * 
 * Please not this class does not extend Web2All_Manager_Plugin,
 * use the Factory to construct, instead of Plugin.
 *
 */
class Web2All_Manager_EncapsulatedPassword {
  
  /**
   * the password
   *
   * @var string
   */
  public $password;
  
  /**
   * Constructor
   * @param string $password  [optional password]
   */
  public function __construct($password='') {
    if (!is_string($password)) {
      throw new InvalidArgumentException('Web2All_Manager_EncapsulatedPassword: constructor param must be a string');
    }
    
    $this->password=$password;
  }
  
  /**
   * return the password string
   *
   * @return string
   */
  public function __toString()
  {
    return $this->password;
  }
  
}

/**
 * Class for handling PHP ini settings
 * 
 * The method getBytes is used in the shutdown function of
 * manager main, thats the reason this class is included here.
 * 
 * Please not this class does not extend Web2All_Manager_Plugin,
 * use the Factory to construct, instead of Plugin.
 *
 */
class Web2All_PHP_INI {
  
  /**
   * converts possible shorthand notations from the php ini
   * to bytes.
   * 
   * see: http://www.php.net/manual/en/faq.using.php#faq.using.shorthandbytes
   * 
   * original code from: http://nl2.php.net/manual/en/function.ini-get.php#96996
   *
   * @return int
   */
  public static function getBytes($size_str)
  {
    switch (substr ($size_str, -1))
    {
      case 'M': case 'm': return (int)$size_str * 1048576;
      case 'K': case 'k': return (int)$size_str * 1024;
      case 'G': case 'g': return (int)$size_str * 1073741824;
      default: return $size_str;
    }
  }
  
}
?>