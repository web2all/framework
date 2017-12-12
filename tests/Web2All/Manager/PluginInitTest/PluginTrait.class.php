<?php
class Web2All_Manager_PluginInitTest_PluginTrait extends Web2All_Manager_PluginInitTest_A implements Web2All_Manager_PluginInterface {
  use Web2All_Manager_PluginTrait;
  
  public function getIP()
  {
    return $this->Web2All->getIP();
  }
}
?>