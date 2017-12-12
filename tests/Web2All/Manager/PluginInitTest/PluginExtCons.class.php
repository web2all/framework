<?php
class Web2All_Manager_PluginInitTest_PluginExtCons extends Web2All_Manager_Plugin {

  protected $test;
  
  /**
   * constructor
   *
   * @param Web2All_Manager_Main $web2all
   * @param string $test
   */
  public function __construct(Web2All_Manager_Main $web2all,$test) {
    parent::__construct($web2all);
    $this->test=$test;
  }
  
  public function returnTrue()
  {
    return true;
  }
  
  public function getTest()
  {
    return $this->test;
  }
  
  public function getIP()
  {
    return $this->Web2All->getIP();
  }
}
?>