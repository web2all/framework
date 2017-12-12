<?php
use PHPUnit\Framework\TestCase;

class Web2All_Manager_ClassLoaderNoAutoloadTest extends TestCase
{
  /**
   * Test loadClass
   * 
   */
  public function testLoadclassErrorObserver()
  {
    //print_r(Web2All_Manager_Main::getRegisteredIncludeRoots());
    //print_r(get_declared_classes());
    
    // it is extremely difficult to test loading because it depends on a global state,
    // once another test registered an autoloader or included a file there is no way
    // to go back.
    // So to really test this, run as separate test
    if(!class_exists('Web2All_ErrorObserver_Display', false)){
      Web2All_Manager_Main::loadClass('Web2All_ErrorObserver_Display');
      $this->assertTrue(class_exists('Web2All_ErrorObserver_Display', true));
    }else{
      $this->markTestSkipped('Cannot run this test because other tests made it impossible, run this test separate');
    }
  }

  /**
   * Test loadClass with .inc files
   * 
   */
  public function testLoadclassInc()
  {
    Web2All_Manager_Main::unregisterIncludeRoot(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR);
    Web2All_Manager_Main::loadClass('Web2All_Manager_ClassLoaderTest_B','INC');
    $this->assertFalse(class_exists('Web2All_Manager_ClassLoaderTest_B', false));
    Web2All_Manager_Main::registerIncludeRoot(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR);
    Web2All_Manager_Main::loadClass('Web2All_Manager_ClassLoaderTest_B','INC');
    $this->assertTrue(class_exists('Web2All_Manager_ClassLoaderTest_B', false));
  }

  /**
   * Test loadClass with namespaces
   * 
   */
  public function testLoadclassNamespace()
  {
    Web2All_Manager_Main::unregisterIncludeRoot(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR);
    Web2All_Manager_Main::loadClass('Web2All\\Manager\\ClassLoaderTest\\E');
    $this->assertFalse(class_exists('Web2All\\Manager\\ClassLoaderTest\\E', false));
    Web2All_Manager_Main::registerIncludeRoot(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR);
    Web2All_Manager_Main::loadClass('Web2All\\Manager\\ClassLoaderTest\\E');
    $this->assertTrue(class_exists('Web2All\\Manager\\ClassLoaderTest\\E', false));
  }

  /**
   * Test loadClass with mixed namspaces and the underscore classnames
   * 
   */
  public function testLoadclassNamespaceMixed()
  {
    Web2All_Manager_Main::unregisterIncludeRoot(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR);
    Web2All_Manager_Main::loadClass('Web2All\\Manager\\ClassLoaderTest_F');
    $this->assertFalse(class_exists('Web2All\\Manager\\ClassLoaderTest_F', false));
    Web2All_Manager_Main::registerIncludeRoot(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR);
    Web2All_Manager_Main::loadClass('Web2All\\Manager\\ClassLoaderTest_F');
    $this->assertTrue(class_exists('Web2All\\Manager\\ClassLoaderTest_F', false));
  }

}
?>