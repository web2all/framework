<?php
use PHPUnit\Framework\TestCase;

class Web2All_Manager_ClassLoaderTest extends TestCase
{
  public static $_remembered_IncludeRoots;

  /**
   * set up test environmemt
   */
  public static function setUpBeforeClass()
  {
    self::$_remembered_IncludeRoots = Web2All_Manager_Main::getRegisteredIncludeRoots();
    foreach(self::$_remembered_IncludeRoots as $root){
      Web2All_Manager_Main::unregisterIncludeRoot($root);
    }
  }

  /**
   * set up test environmemt
   */
  public static function tearDownAfterClass()
  {
    foreach(self::$_remembered_IncludeRoots as $root){
      Web2All_Manager_Main::registerIncludeRoot($root);
    }
  }

  /**
   * Test autoloader
   * 
   */
  public function testAutoloader()
  {
    Web2All_Manager_Main::includeClass('Web2All_Manager_Main_Nonexisting');
    $this->assertFalse(class_exists('Web2All_Manager_ClassLoaderTest_A', true), 'Autoloading Web2All_Manager_ClassLoaderTest_A should fail');
    Web2All_Manager_Main::registerAutoloader(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR);
    $this->assertTrue(class_exists('Web2All_Manager_ClassLoaderTest_A', true), 'Autoloading Web2All_Manager_ClassLoaderTest_A should succeed');
    $this->assertTrue(Web2All_Manager_ClassLoaderTest_A::returnTrue());
  }

  /**
   * Test loadClass
   * 
   */
  public function testLoadClass()
  {
    Web2All_Manager_Main::loadClass('Web2All_Manager_ClassLoaderTest_noclass','INC');
    $this->assertTrue(class_exists('Web2All_Manager_ClassLoaderTest_noclass_A', true));
    $this->assertTrue(class_exists('Web2All_Manager_ClassLoaderTest_noclass_B', true));
  }

  /**
   * Test includeClass
   * 
   */
  public function testIncludeClassC()
  {
    $this->assertFalse(class_exists('Web2All_Manager_ClassLoaderTest_C', false));
    Web2All_Manager_Main::includeClass('Web2All_Manager_ClassLoaderTest_C');
    $this->assertTrue(class_exists('Web2All_Manager_ClassLoaderTest_C', false));
  }

  /**
   * Test includeClass
   * 
   */
  public function testIncludeClassD()
  {
    $this->assertFalse(class_exists('Web2All_Manager_ClassLoaderTest_D', false));
    Web2All_Manager_Main::includeClass('Web2All_Manager_ClassLoaderTest_D');
    $this->assertTrue(class_exists('Web2All_Manager_ClassLoaderTest_D', false));
  }

  /**
   * Test loadClass with interface
   * 
   */
  public function testLoadclassInterface()
  {
    Web2All_Manager_Main::loadClass('Web2All_Manager_ClassLoaderTest_TestInterface');
    $this->assertTrue(interface_exists('Web2All_Manager_ClassLoaderTest_TestInterface', false));
    // test double loading
    Web2All_Manager_Main::loadClass('Web2All_Manager_ClassLoaderTest_TestInterface');
  }

}
?>