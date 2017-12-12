<?php
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * Web2All Manager PrsLogger class
 * 
 * This is a PSR-3 compatible logger for the Web2All framework
 * @see http://www.php-fig.org/psr/psr-3/
 * 
 * Requires php-fig/log (composer) or https://github.com/php-fig/log
 * 
 * @author Merijn van den Kroonenberg
 * @copyright (c) Copyright 2017 Web2All BV
 * @since 2017-08-18
 */
class Web2All_Manager_PrsLogger extends Web2All_Manager_Plugin implements LoggerInterface {
  use LoggerTrait;
  
  /**
   * Logs with an arbitrary level.
   *
   * @param mixed  $level
   * @param string $message
   * @param array  $context
   *
   * @return void
   */
  public function log($level, $message, array $context = array())
  {
    $this->Web2All->debugLog('['.$level.'] '.$this->interpolate($message, $context));
  }
  
  /**
   * Interpolates context values into the message placeholders.
   * 
   * @param string $message
   * @param array  $context
   * @return string
   */
  function interpolate($message, array $context = array())
  {
    // build a replacement array with braces around the context keys
    $replace = array();
    foreach ($context as $key => $val) {
      // check that the value can be casted to string
      if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
        $replace['{' . $key . '}'] = $val;
      }
    }
    
    // interpolate replacement values into the message and return
    return strtr($message, $replace);
  }

}
?>