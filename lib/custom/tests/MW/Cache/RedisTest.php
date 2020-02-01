<?php

/**
 * @license LGPLv3, http://www.gnu.org/licenses/lgpl.html
 * @copyright Metaways Infosystems GmbH, 2014
 * @copyright Aimeos (aimeos.org), 2015-2020
 */


namespace Aimeos\MW\Cache;


class RedisTest extends \PHPUnit\Framework\TestCase
{
	private $mock;
	private $object;


	protected function setUp() : void
	{
		$methods = array(
			'del', 'execute', 'exists', 'expireat', 'flushdb', 'get',
			'mget', 'mset', 'pipeline', 'sadd', 'set', 'smembers'
		);

		$this->mock = $this->getMockBuilder( '\\Predis\\Client' )->setMethods( $methods )->getMock();
		$this->object = new \Aimeos\MW\Cache\Redis( array( 'siteid' => 1 ), $this->mock );
	}


	protected function tearDown() : void
	{
		unset( $this->object, $this->mock );
	}


	public function testClear()
	{
		$this->mock->expects( $this->once() )->method( 'flushdb' )->will( $this->returnValue( 'OK' ) );
		$this->assertTrue( $this->object->clear() );
	}


	public function testDelete()
	{
		$this->mock->expects( $this->once() )->method( 'del' )->will( $this->returnValue( 'OK' ) )
			->with( $this->equalTo( array( '1-test' ) ) );

		$this->assertTrue( $this->object->delete( 'test' ) );
	}


	public function testDeleteMultiple()
	{
		$this->mock->expects( $this->once() )->method( 'del' )->will( $this->returnValue( 'OK' ) )
			->with( $this->equalTo( array( '1-test' ) ) );

		$this->assertTrue( $this->object->deleteMultiple( array( 'test' ) ) );
	}


	public function testDeleteByTags()
	{
		$this->mock->expects( $this->once() )->method( 'pipeline' )
			->will( $this->returnValue( $this->mock ) );

		$this->mock->expects( $this->exactly( 2 ) )->method( 'smembers' );

		$this->mock->expects( $this->once() )->method( 'execute' )
			->will( $this->returnValue( array( '1-tag:1' => array( '1-key:1', '1-key:2' ) ) ) );

		$this->mock->expects( $this->once() )->method( 'del' )->will( $this->returnValue( 'OK' ) )
			->with( $this->equalTo( array( '1-key:1', '1-key:2', '1-tag:tag1', '1-tag:tag2' ) ) );

		$this->assertTrue( $this->object->deleteByTags( array( 'tag1', 'tag2' ) ) );
	}


	public function testGet()
	{
		$this->mock->expects( $this->once() )->method( 'get' )
			->will( $this->returnValue( 'test' ) );

		$this->assertEquals( 'test', $this->object->get( 't:1' ) );
	}


	public function testGetDefault()
	{
		$this->mock->expects( $this->once() )->method( 'get' );

		$this->assertFalse( $this->object->get( 't:1', false ) );
	}


	public function testGetExpired()
	{
		$this->mock->expects( $this->once() )->method( 'get' );

		$this->assertEquals( null, $this->object->get( 't:1' ) );
	}


	public function testGetMultiple()
	{
		$this->mock->expects( $this->once() )->method( 'mget' )
			->will( $this->returnValue( array( 0 => 'test' ) ) );

		$this->assertEquals( array( 't:1' => 'test' ), $this->object->getMultiple( array( 't:1' ) ) );
	}


	public function testHas()
	{
		$this->mock->expects( $this->once() )->method( 'exists' )->will( $this->returnValue( 1 ) );
		$this->assertTrue( $this->object->has( 'key' ) );
	}


	public function testSet()
	{
		$this->mock->expects( $this->once() )->method( 'pipeline' )
			->will( $this->returnValue( $this->mock ) );

		$this->mock->expects( $this->once() )->method( 'set' )
			->with( $this->equalTo( '1-t:1' ), $this->equalTo( 'test 1' ) );

		$this->mock->expects( $this->exactly( 2 ) )->method( 'sadd' );

		$this->mock->expects( $this->once() )->method( 'execute' )->will( $this->returnValue( 'OK' ) );

		$this->mock->expects( $this->once() )->method( 'expireat' )
			->with( $this->equalTo( '1-t:1' ), $this->greaterThan( 0 ) );

		$this->assertTrue( $this->object->set( 't:1', 'test 1', '2000-01-01 00:00:00', ['tag1', 'tag2'] ) );
	}


	public function testSetMultiple()
	{
		$this->mock->expects( $this->once() )->method( 'pipeline' )
			->will( $this->returnValue( $this->mock ) );

		$this->mock->expects( $this->once() )->method( 'mset' )
			->with( $this->equalTo( array( '1-t:1' => 'test 1' ) ) );

		$this->mock->expects( $this->exactly( 2 ) )->method( 'sadd' );

		$this->mock->expects( $this->once() )->method( 'execute' )->will( $this->returnValue( 'OK' ) );

		$this->mock->expects( $this->once() )->method( 'expireat' )
			->with( $this->equalTo( '1-t:1' ), $this->greaterThan( 0 ) );

		$this->assertTrue( $this->object->setMultiple( ['t:1' => 'test 1'], '2000-01-01 00:00:00', ['tag1', 'tag2'] ) );
	}
}
