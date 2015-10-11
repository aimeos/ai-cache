<?php

namespace Aimeos\MAdmin\Cache\Manager;


/**
 * @license LGPLv3, http://www.gnu.org/licenses/lgpl.html
 * @copyright Metaways Infosystems GmbH, 2014
 * @copyright Aimeos (aimeos.org), 2015
 */
class RedisTest extends \PHPUnit_Framework_TestCase
{
	private $object;


	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 *
	 * @access protected
	 */
	protected function setUp()
	{
		$this->context = \TestHelper::getContext();
		$this->object = new \Aimeos\MAdmin\Cache\Manager\Redis( $this->context );
	}


	/**
	 * Tears down the fixture, for example, closes a network connection.
	 * This method is called after a test is executed.
	 *
	 * @access protected
	 */
	protected function tearDown()
	{
		$this->object = null;
	}


	public function testCleanup()
	{
		$this->object->cleanup( array( -1 ) );
	}


	public function testCreateItem()
	{
		$this->assertInstanceOf( '\\Aimeos\\MAdmin\\Cache\\Item\\Iface', $this->object->createItem() );
	}


	public function testGetSearchAttributes()
	{
		foreach( $this->object->getSearchAttributes() as $attr ) {
			$this->assertInstanceOf('\\Aimeos\\MW\\Criteria\\Attribute\\Iface', $attr );
		}
	}


	public function testGetSubManager()
	{
		$this->setExpectedException('\\Aimeos\\MAdmin\\Exception');
		$this->object->getSubManager( 'unknown' );
	}


	public function testSearchItems()
	{
		$search = $this->object->createSearch();
		$search->setConditions( $search->compare( '==', 'cache.id', 'unittest' ) );

		$this->assertEquals( array(), $this->object->searchItems( $search ) );
	}


	public function testGetItem()
	{
		$context = \TestHelper::getContext();

		$mockRedis = $this->getMockBuilder( '\\Aimeos\\MW\\Cache\\Redis' )
			->disableOriginalConstructor()->setMethods( array( 'get' ) )->getMock();

		$mockRedis->expects( $this->once() )->method( 'get' )->will( $this->returnValue( 'test value' ) );

		$mock = $this->getMockBuilder( '\\Aimeos\\MAdmin\\Cache\\Manager\\Redis' )
			->setConstructorArgs( array( $context ) )->setMethods( array( 'getCache' ) )->getMock();

		$mock->expects( $this->once() )->method( 'getCache' )->will( $this->returnValue( $mockRedis ) );

		$this->assertInstanceOf( '\\Aimeos\\MAdmin\\Cache\\Item\\Iface', $mock->getItem( 'test' ) );
	}


	public function testGetItemException()
	{
		$context = \TestHelper::getContext();

		$mockRedis = $this->getMockBuilder( '\\Aimeos\\MW\\Cache\\Redis' )
			->disableOriginalConstructor()->setMethods( array( 'get' ) )->getMock();

		$mock = $this->getMockBuilder( '\\Aimeos\\MAdmin\\Cache\\Manager\\Redis' )
			->setConstructorArgs( array( $context ) )->setMethods( array( 'getCache' ) )->getMock();

		$mock->expects( $this->once() )->method( 'getCache' )->will( $this->returnValue( $mockRedis ) );

		$this->setExpectedException( '\\Aimeos\\MAdmin\\Cache\\Exception' );
		$mock->getItem( 'test' );
	}


	public function testSaveItem()
	{
		$context = \TestHelper::getContext();

		$mockRedis = $this->getMockBuilder( '\\Aimeos\\MW\\Cache\\Redis' )
			->disableOriginalConstructor()->setMethods( array( 'delete', 'set' ) )->getMock();

		$mockRedis->expects( $this->once() )->method( 'delete' );
		$mockRedis->expects( $this->once() )->method( 'set' );

		$mock = $this->getMockBuilder( '\\Aimeos\\MAdmin\\Cache\\Manager\\Redis' )
			->setConstructorArgs( array( $context ) )->setMethods( array( 'getCache' ) )->getMock();

		$mock->expects( $this->once() )->method( 'getCache' )->will( $this->returnValue( $mockRedis ) );

		$item = $mock->createItem();
		$item->setId( 'test' );

		$mock->saveItem( $item );
	}


	public function testSaveItemNotModified()
	{
		$context = \TestHelper::getContext();

		$mock = $this->getMockBuilder( '\\Aimeos\\MAdmin\\Cache\\Manager\\Redis' )
			->setConstructorArgs( array( $context ) )->setMethods( array( 'getCache' ) )->getMock();

		$mock->saveItem( $mock->createItem() );
	}


	public function testSaveItemInvalid()
	{
		$mock = $this->getMockBuilder( '\\Aimeos\\MAdmin\\Cache\\Manager\\Redis' )
			->disableOriginalConstructor()->setMethods( array( 'getCache' ) )->getMock();

		$this->setExpectedException( '\\Aimeos\\MAdmin\\Cache\\Exception' );
		$mock->saveItem( new \Aimeos\MAdmin\Log\Item\Standard() );
	}


	public function testDeleteItems()
	{
		$mockRedis = $this->getMockBuilder( '\\Aimeos\\MW\\Cache\\Redis' )
			->disableOriginalConstructor()->setMethods( array( 'deleteList' ) )->getMock();

		$mock = $this->getMockBuilder( '\\Aimeos\\MAdmin\\Cache\\Manager\\Redis' )
			->disableOriginalConstructor()->setMethods( array( 'getCache' ) )->getMock();

		$mock->expects( $this->once() )->method( 'getCache' )->will( $this->returnValue( $mockRedis ) );

		$mock->deleteItems( array() );
	}


	public function testGetCache()
	{
		$this->assertInstanceOf( '\\Aimeos\\MW\\Cache\\Iface', $this->object->getCache() );
	}
}
