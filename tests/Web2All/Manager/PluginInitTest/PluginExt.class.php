<?php
class Web2All_Manager_PluginInitTest_PluginExt extends Web2All_Manager_Plugin {
  public $some_property;
  
  public function returnTrue()
  {
    return true;
  }
  public function getIP()
  {
    return $this->Web2All->getIP();
  }
}
?>