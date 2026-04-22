<?php

/**
 * @license LGPLv3, http://www.gnu.org/licenses/lgpl.html
 * @copyright Metaways Infosystems GmbH, 2014
 * @copyright Aimeos (aimeos.org), 2015-2026
 */


namespace Aimeos\Base\Cache;


abstract class PredisClientStub extends \Predis\Client
{
	abstract public function del( $keys );
	abstract public function execute();
	abstract public function exists( ...$keys );
	abstract public function expireat( $key, $timestamp );
	abstract public function flushdb();
	abstract public function get( $key );
	abstract public function mget( $keys );
	abstract public function mset( $values );
	abstract public function sadd( $key, ...$members );
	abstract public function set( $key, $value, ...$options );
	abstract public function smembers( $key );
}


class RedisTest extends \PHPUnit\Framework\TestCase
{
	private $mock;
	private $object;


	protected function setUp() : void
	{
		$this->mock = $this->createMock( PredisClientStub::class );

		$this->object = new \Aimeos\Base\Cache\Redis( [], $this->mock );
	}


	protected function tearDown() : void
	{
		unset( $this->object, $this->mock );
	}


	public function testClear()
	{
		$this->mock->expects( $this->once() )->method( 'flushdb' )->willReturn( 'OK' );
		$this->assertTrue( $this->object->clear() );
	}


	public function testDelete()
	{
		$this->mock->expects( $this->once() )->method( 'del' )->willReturn( 1 )
			->with( $this->equalTo( array( 'test' ) ) );

		$this->assertTrue( $this->object->delete( 'test' ) );
	}


	public function testDeleteMultiple()
	{
		$this->mock->expects( $this->once() )->method( 'del' )->willReturn( 1 )
			->with( $this->equalTo( array( 'test' ) ) );

		$this->assertTrue( $this->object->deleteMultiple( array( 'test' ) ) );
	}


	public function testDeleteByTags()
	{
		$this->mock->expects( $this->once() )->method( 'pipeline' )
			->willReturn( $this->mock );

		$this->mock->expects( $this->exactly( 2 ) )->method( 'smembers' );

		$this->mock->expects( $this->once() )->method( 'execute' )
			->willReturn( array( 'tag:1' => array( 'key:1', 'key:2' ) ) );

		$this->mock->expects( $this->once() )->method( 'del' )->willReturn( 4 )
			->with( $this->equalTo( array( 'key:1', 'key:2', 'tag:tag1', 'tag:tag2' ) ) );

		$this->assertTrue( $this->object->deleteByTags( array( 'tag1', 'tag2' ) ) );
	}


	public function testGet()
	{
		$this->mock->expects( $this->once() )->method( 'get' )
			->willReturn( 'test' );

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
			->willReturn( array( 0 => 'test' ) );

		$this->assertEquals( array( 't:1' => 'test' ), $this->object->getMultiple( array( 't:1' ) ) );
	}


	public function testHas()
	{
		$this->mock->expects( $this->once() )->method( 'exists' )->willReturn( 1 );
		$this->assertTrue( $this->object->has( 'key' ) );
	}


	public function testSet()
	{
		$this->mock->expects( $this->once() )->method( 'pipeline' )
			->willReturn( $this->mock );

		$this->mock->expects( $this->once() )->method( 'set' )
			->with( $this->equalTo( 't:1' ), $this->equalTo( 'test 1' ) );

		$this->mock->expects( $this->exactly( 2 ) )->method( 'sadd' );

		$this->mock->expects( $this->once() )->method( 'execute' )->willReturn( 'OK' );

		$this->mock->expects( $this->once() )->method( 'expireat' )
			->with( $this->equalTo( 't:1' ), $this->greaterThan( 0 ) );

		$this->assertTrue( $this->object->set( 't:1', 'test 1', '2000-01-01 00:00:00', ['tag1', 'tag2'] ) );
	}


	public function testSetMultiple()
	{
		$this->mock->expects( $this->once() )->method( 'pipeline' )
			->willReturn( $this->mock );

		$this->mock->expects( $this->once() )->method( 'mset' )
			->with( $this->equalTo( array( 't:1' => 'test 1' ) ) );

		$this->mock->expects( $this->exactly( 2 ) )->method( 'sadd' );

		$this->mock->expects( $this->once() )->method( 'execute' )->willReturn( 'OK' );

		$this->mock->expects( $this->once() )->method( 'expireat' )
			->with( $this->equalTo( 't:1' ), $this->greaterThan( 0 ) );

		$this->assertTrue( $this->object->setMultiple( ['t:1' => 'test 1'], '2000-01-01 00:00:00', ['tag1', 'tag2'] ) );
	}
}
