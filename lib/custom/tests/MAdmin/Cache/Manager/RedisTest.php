<?php

/**
 * @license LGPLv3, http://www.gnu.org/licenses/lgpl.html
 * @copyright Metaways Infosystems GmbH, 2014
 * @copyright Aimeos (aimeos.org), 2015
 */

class MAdmin_Cache_Manager_RedisTest extends MW_Unittest_Testcase
{
	private $_object;


	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 *
	 * @access protected
	 */
	protected function setUp()
	{
		$this->_context = TestHelper::getContext();
		$this->_object = new MAdmin_Cache_Manager_Redis( $this->_context );
	}


	/**
	 * Tears down the fixture, for example, closes a network connection.
	 * This method is called after a test is executed.
	 *
	 * @access protected
	 */
	protected function tearDown()
	{
		$this->_object = null;
	}


	public function testCleanup()
	{
		$this->_object->cleanup( array( -1 ) );
	}


	public function testCreateItem()
	{
		$this->assertInstanceOf( 'MAdmin_Cache_Item_Interface', $this->_object->createItem() );
	}


	public function testGetSearchAttributes()
	{
		foreach( $this->_object->getSearchAttributes() as $attr ) {
			$this->assertInstanceOf('MW_Common_Criteria_Attribute_Interface', $attr );
		}
	}


	public function testGetSubManager()
	{
		$this->setExpectedException('MAdmin_Exception');
		$this->_object->getSubManager( 'unknown' );
	}


	public function testSearchItems()
	{
		$search = $this->_object->createSearch();
		$search->setConditions( $search->compare( '==', 'cache.id', 'unittest' ) );

		$this->assertEquals( array(), $this->_object->searchItems( $search ) );
	}


	public function testGetItem()
	{
		$context = TestHelper::getContext();

		$mockRedis = $this->getMockBuilder( 'MW_Cache_Redis' )
			->disableOriginalConstructor()->setMethods( array( 'get' ) )->getMock();

		$mockRedis->expects( $this->once() )->method( 'get' )->will( $this->returnValue( 'test value' ) );

		$mock = $this->getMockBuilder( 'MAdmin_Cache_Manager_Redis' )
			->setConstructorArgs( array( $context ) )->setMethods( array( 'getCache' ) )->getMock();

		$mock->expects( $this->once() )->method( 'getCache' )->will( $this->returnValue( $mockRedis ) );

		$this->assertInstanceOf( 'MAdmin_Cache_Item_Interface', $mock->getItem( 'test' ) );
	}


	public function testGetItemException()
	{
		$context = TestHelper::getContext();

		$mockRedis = $this->getMockBuilder( 'MW_Cache_Redis' )
			->disableOriginalConstructor()->setMethods( array( 'get' ) )->getMock();

		$mock = $this->getMockBuilder( 'MAdmin_Cache_Manager_Redis' )
			->setConstructorArgs( array( $context ) )->setMethods( array( 'getCache' ) )->getMock();

		$mock->expects( $this->once() )->method( 'getCache' )->will( $this->returnValue( $mockRedis ) );

		$this->setExpectedException( 'MAdmin_Cache_Exception' );
		$mock->getItem( 'test' );
	}


	public function testSaveItem()
	{
		$context = TestHelper::getContext();

		$mockRedis = $this->getMockBuilder( 'MW_Cache_Redis' )
			->disableOriginalConstructor()->setMethods( array( 'delete', 'set' ) )->getMock();

		$mockRedis->expects( $this->once() )->method( 'delete' );
		$mockRedis->expects( $this->once() )->method( 'set' );

		$mock = $this->getMockBuilder( 'MAdmin_Cache_Manager_Redis' )
			->setConstructorArgs( array( $context ) )->setMethods( array( 'getCache' ) )->getMock();

		$mock->expects( $this->once() )->method( 'getCache' )->will( $this->returnValue( $mockRedis ) );

		$item = $mock->createItem();
		$item->setId( 'test' );

		$mock->saveItem( $item );
	}


	public function testSaveItemNotModified()
	{
		$context = TestHelper::getContext();

		$mock = $this->getMockBuilder( 'MAdmin_Cache_Manager_Redis' )
			->setConstructorArgs( array( $context ) )->setMethods( array( 'getCache' ) )->getMock();

		$mock->saveItem( $mock->createItem() );
	}


	public function testSaveItemInvalid()
	{
		$mock = $this->getMockBuilder( 'MAdmin_Cache_Manager_Redis' )
			->disableOriginalConstructor()->setMethods( array( 'getCache' ) )->getMock();

		$this->setExpectedException( 'MAdmin_Cache_Exception' );
		$mock->saveItem( new MAdmin_Log_Item_Default() );
	}


	public function testDeleteItems()
	{
		$mockRedis = $this->getMockBuilder( 'MW_Cache_Redis' )
			->disableOriginalConstructor()->setMethods( array( 'deleteList' ) )->getMock();

		$mock = $this->getMockBuilder( 'MAdmin_Cache_Manager_Redis' )
			->disableOriginalConstructor()->setMethods( array( 'getCache' ) )->getMock();

		$mock->expects( $this->once() )->method( 'getCache' )->will( $this->returnValue( $mockRedis ) );

		$mock->deleteItems( array() );
	}


	public function testGetCache()
	{
		$this->setExpectedException( 'MAdmin_Cache_Exception' );
		$this->_object->getCache();
	}
}
