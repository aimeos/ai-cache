<?php

/**
 * @license LGPLv3, http://www.gnu.org/licenses/lgpl.html
 * @copyright Metaways Infosystems GmbH, 2014
 * @copyright Aimeos (aimeos.org), 2015-2025
 */


namespace Aimeos\MAdmin\Cache\Manager;


class RedisTest extends \PHPUnit\Framework\TestCase
{
	private $context;
	private $object;


	protected function setUp() : void
	{
		$this->context = \TestHelper::context();
		$this->object = new \Aimeos\MAdmin\Cache\Manager\Redis( $this->context );
	}


	protected function tearDown() : void
	{
		unset( $this->object, $this->context );
	}


	public function testClear()
	{
		$this->assertInstanceOf( \Aimeos\MAdmin\Cache\Manager\Iface::class, $this->object->clear( array( -1 ) ) );
	}


	public function testCreateItem()
	{
		$this->assertInstanceOf( \Aimeos\MAdmin\Cache\Item\Iface::class, $this->object->create() );
	}


	public function testGetSearchAttributes()
	{
		foreach( $this->object->getSearchAttributes() as $attr ) {
			$this->assertInstanceOf( '\\Aimeos\\Base\\Criteria\\Attribute\\Iface', $attr );
		}
	}


	public function testGetSubManager()
	{
		$this->expectException( \LogicException::class );
		$this->object->getSubManager( 'unknown' );
	}


	public function testSearchItems()
	{
		$search = $this->object->filter();
		$search->setConditions( $search->compare( '==', 'cache.id', 'unittest' ) );

		$this->assertEquals( [], $this->object->search( $search )->toArray() );
	}


	public function testGetItem()
	{
		$context = \TestHelper::context();

		$mockRedis = $this->getMockBuilder( '\\Aimeos\\Base\\Cache\\Redis' )
			->disableOriginalConstructor()->onlyMethods( array( 'get' ) )->getMock();

		$mockRedis->expects( $this->once() )->method( 'get' )->willReturn( 'test value' );

		$mock = $this->getMockBuilder( '\\Aimeos\\MAdmin\\Cache\\Manager\\Redis' )
			->setConstructorArgs( array( $context ) )->onlyMethods( array( 'getCache' ) )->getMock();

		$mock->expects( $this->once() )->method( 'getCache' )->willReturn( $mockRedis );

		$this->assertInstanceOf( '\\Aimeos\\MAdmin\\Cache\\Item\\Iface', $mock->get( 'test' ) );
	}


	public function testGetItemException()
	{
		$context = \TestHelper::context();

		$mockRedis = $this->getMockBuilder( '\\Aimeos\\Base\\Cache\\Redis' )
			->disableOriginalConstructor()->onlyMethods( array( 'get' ) )->getMock();

		$mock = $this->getMockBuilder( '\\Aimeos\\MAdmin\\Cache\\Manager\\Redis' )
			->setConstructorArgs( array( $context ) )->onlyMethods( array( 'getCache' ) )->getMock();

		$mock->expects( $this->once() )->method( 'getCache' )->willReturn( $mockRedis );

		$this->expectException( '\\Aimeos\\MAdmin\\Cache\\Exception' );
		$mock->get( 'test' );
	}


	public function testSaveItem()
	{
		$context = \TestHelper::context();

		$mockRedis = $this->getMockBuilder( '\\Aimeos\\Base\\Cache\\Redis' )
			->disableOriginalConstructor()->onlyMethods( array( 'delete', 'set' ) )->getMock();

		$mockRedis->expects( $this->once() )->method( 'delete' );
		$mockRedis->expects( $this->once() )->method( 'set' );

		$mock = $this->getMockBuilder( '\\Aimeos\\MAdmin\\Cache\\Manager\\Redis' )
			->setConstructorArgs( array( $context ) )->onlyMethods( array( 'getCache' ) )->getMock();

		$mock->expects( $this->once() )->method( 'getCache' )->willReturn( $mockRedis );

		$item = $mock->create();
		$item->setId( 'test' );

		$mock->save( $item );
	}


	public function testDeleteItems()
	{
		$mockRedis = $this->getMockBuilder( '\\Aimeos\\Base\\Cache\\Redis' )
			->disableOriginalConstructor()->onlyMethods( array( 'deleteMultiple' ) )->getMock();

		$mock = $this->getMockBuilder( '\\Aimeos\\MAdmin\\Cache\\Manager\\Redis' )
			->disableOriginalConstructor()->onlyMethods( array( 'getCache' ) )->getMock();

		$mock->expects( $this->once() )->method( 'getCache' )->willReturn( $mockRedis );

		$mock->delete( [] );
	}


	public function testGetCache()
	{
		try {
			$this->assertInstanceOf( '\\Aimeos\\Base\\Cache\\Iface', $this->object->getCache() );
		} catch( \Aimeos\MAdmin\Cache\Exception $e ) {
			$this->markTestSkipped( 'Please install Predis client first' );
		}
	}
}
