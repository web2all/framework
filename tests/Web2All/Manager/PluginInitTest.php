<?php
use PHPUnit\Framework\TestCase;

class Web2All_Manager_PluginTest extends TestCase
{
  /**
   * Test manager main creation
   * 
   * @return Web2All_Manager_Main
   */
  public function testManagerCreate()
  {
    $web2all = new Web2All_Manager_Main();
    Web2All_Manager_Main::registerIncludeRoot(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR);
    $this->assertTrue($web2all instanceof Web2All_Manager_Main);
    return $web2all;
  }

  /**
   * Test instantiate a Web2All_Manager_Plugin using ->Plugin
   * 
   * @param Web2All_Manager_Main
   * @depends testManagerCreate
   */
  public function testPluginExists($web2all)
  {
    $obj = $web2all->Plugin->Web2All_Manager_PluginInitTest_PluginExt();
    $this->assertTrue($obj->returnTrue());
    $this->assertTrue(is_string($obj->getIP()));
  }

  /**
   * Test instantiate a Web2All_Manager_Plugin using ->Factory
   * 
   * @param Web2All_Manager_Main
   * @depends testManagerCreate
   */
  public function testFactoryPlugin($web2all)
  {
    $obj = $web2all->Factory->Web2All_Manager_PluginInitTest_PluginExt();
    $this->assertTrue($obj->returnTrue());
    $this->assertTrue(is_string($obj->getIP()));
  }
  
  /**
   * Test instantiate a Web2All_Manager_Plugin with constructor, using ->Factory
   * 
   * @param Web2All_Manager_Main
   * @depends testManagerCreate
   */
  public function testFactoryPluginCons($web2all)
  {
    $obj = $web2all->Factory->Web2All_Manager_PluginInitTest_PluginExtCons('teststring');
    $this->assertTrue($obj->returnTrue());
    $this->assertTrue(is_string($obj->getIP()));
    $this->assertEquals('teststring', $obj->getTest());
  }
  
  /**
   * Test instantiate a Web2All_Manager_Plugin with constructor, using ->Plugin
   * 
   * @param Web2All_Manager_Main
   * @depends testManagerCreate
   */
  public function testPluginCons($web2all)
  {
    $obj = $web2all->Plugin->Web2All_Manager_PluginInitTest_PluginExtCons('teststring');
    $this->assertTrue($obj->returnTrue());
    $this->assertTrue(is_string($obj->getIP()));
    $this->assertEquals('teststring', $obj->getTest());
  }
  
  /**
   * Test instantiate a simple class (non plugin) using ->Factory
   * 
   * @param Web2All_Manager_Main
   * @depends testManagerCreate
   */
  public function testNonPlugin($web2all)
  {
    $obj = $web2all->Factory->Web2All_Manager_PluginInitTest_A();
    $this->assertTrue($obj->returnTrue());
  }
  
  /**
   * Test instantiate a Web2All_Manager_PluginInterface using ->Factory
   * 
   * @param Web2All_Manager_Main
   * @depends testManagerCreate
   */
  public function testFactoryPluginTrait($web2all)
  {
    $obj = $web2all->Factory->Web2All_Manager_PluginInitTest_PluginTrait();
    $this->assertTrue($obj->returnTrue());
    $this->assertTrue(is_string($obj->getIP()));
  }
  
  /**
   * Test instantiate a class using ->PluginGlobal
   * 
   * @param Web2All_Manager_Main
   * @depends testManagerCreate
   */
  public function testPluginGlobal($web2all)
  {
    $obj = $web2all->PluginGlobal->Web2All_Manager_PluginInitTest_PluginExt();
    $this->assertTrue($obj->returnTrue());
    $obj->some_property='A';
    unset($obj);
    $obj2 = $web2all->PluginGlobal->Web2All_Manager_PluginInitTest_PluginExt();
    $this->assertEquals('A', $obj2->some_property);
    $obj2->some_property='B';
    $obj3 = $web2all->PluginGlobal->Web2All_Manager_PluginInitTest_PluginExt();
    $this->assertEquals('B', $obj3->some_property);
    $obj4 = $web2all->Factory->Web2All_Manager_PluginInitTest_PluginExt();
    $this->assertEquals(null, $obj4->some_property);
  }
  
}
?>