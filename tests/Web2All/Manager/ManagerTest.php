<?php
use PHPUnit\Framework\TestCase;

class Web2All_Manager_ManagerTest extends TestCase
{

    public function testManagerInstance()
    {

      $m = new Web2All_Manager_Main();
      $this->assertEquals(true, ($m instanceof Web2All_Manager_Main), 'Web2All_Manager_Main is present and instantiable');
      $this->assertEquals(0, $m->DebugLevel, 'Web2All_Manager_Main debug level should be 0 by default');

      $c = new Web2All_Manager_Config();
      $m = new Web2All_Manager_Main($c);
      $this->assertEquals(0, $m->DebugLevel, 'Web2All_Manager_Main debug level should be 0 by default');

      $m = new Web2All_Manager_Main($c, 1);
      $this->assertEquals(1, $m->DebugLevel, 'Web2All_Manager_Main debug level should be 1 by default');

      $this->assertEquals('', $m->getIP(), 'When run from cli the getIP should return empty string');
    }

}
?>