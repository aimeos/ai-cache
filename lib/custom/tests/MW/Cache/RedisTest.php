<?php

/**
 * @license LGPLv3, http://www.gnu.org/licenses/lgpl.html
 * @copyright Metaways Infosystems GmbH, 2014
 * @copyright Aimeos (aimeos.org), 2015-2018
 */


namespace Aimeos\MW\Cache;


class RedisTest extends \PHPUnit\Framework\TestCase
{
	private $mock;
	private $object;


	protected function setUp()
	{
		$methods = array(
			'del', 'execute', 'expireat', 'flushdb',
			'get', 'mget', 'mset', 'pipeline',
			'sadd', 'set', 'smembers'
		);

		$this->mock = $this->getMockBuilder( '\\Predis\\Client' )->setMethods( $methods )->getMock();
		$this->object = new \Aimeos\MW\Cache\Redis( array( 'siteid' => 1 ), $this->mock );
	}


	protected function tearDown()
	{
		unset( $this->object, $this->mock );
	}


	public function testDelete()
	{
		$this->mock->expects( $this->once() )->method( 'del' )
			->with( $this->equalTo( array( '1-test' ) ) );

		$this->object->delete( 'test' );
	}


	public function testDeleteMultiple()
	{
		$this->mock->expects( $this->once() )->method( 'del' )
			->with( $this->equalTo( array( '1-test' ) ) );

		$this->object->deleteMultiple( array( 'test' ) );
	}


	public function testDeleteByTags()
	{
		$this->mock->expects( $this->once() )->method( 'pipeline' )
			->will( $this->returnValue( $this->mock ) );

		$this->mock->expects( $this->exactly( 2 ) )->method( 'smembers' );

		$this->mock->expects( $this->once() )->method( 'execute' )
			->will( $this->returnValue( array( '1-tag:1' => array( '1-key:1', '1-key:2' ) ) ) );

		$this->mock->expects( $this->once() )->method( 'del' )
			->with( $this->equalTo( array( '1-key:1', '1-key:2', '1-tag:tag1', '1-tag:tag2' ) ) );

		$this->object->deleteByTags( array( 'tag1', 'tag2' ) );
	}


	public function testClear()
	{
		$this->mock->expects( $this->once() )->method( 'flushdb' );
		$this->object->clear();
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


	public function testGetMultipleByTags()
	{
		$this->mock->expects( $this->once() )->method( 'pipeline' )
			->will( $this->returnValue( $this->mock ) );

		$this->mock->expects( $this->exactly( 2 ) )->method( 'smembers' );

		$this->mock->expects( $this->once() )->method( 'execute' )
			->will( $this->returnValue( array( '1-tag:1' => array( '1-t:1', '1-t:2' ) ) ) );

		$this->mock->expects( $this->once() )->method( 'mget' )
			->will( $this->returnValue( array( 0 => 'test1', 1 => 'test2' ) ) );

		$expected = array( 't:1' => 'test1', 't:2' => 'test2' );
		$result = $this->object->getMultipleByTags( array( 'tag1', 'tag2' ) );

		$this->assertEquals( $expected, $result );
	}


	public function testSet()
	{
		$this->mock->expects( $this->once() )->method( 'pipeline' )
			->will( $this->returnValue( $this->mock ) );

		$this->mock->expects( $this->once() )->method( 'set' )
			->with( $this->equalTo( '1-t:1' ), $this->equalTo( 'test 1' ) );

		$this->mock->expects( $this->exactly( 2 ) )->method( 'sadd' );

		$this->mock->expects( $this->once() )->method( 'execute' );

		$this->mock->expects( $this->once() )->method( 'expireat' )
			->with( $this->equalTo( '1-t:1' ), $this->greaterThan( 0 ) );

		$this->object->set( 't:1', 'test 1', '2000-01-01 00:00:00', array( 'tag1', 'tag2' ) );
	}


	public function testSetMultiple()
	{
		$this->mock->expects( $this->once() )->method( 'pipeline' )
			->will( $this->returnValue( $this->mock ) );

		$this->mock->expects( $this->once() )->method( 'mset' )
			->with( $this->equalTo( array( '1-t:1' => 'test 1' ) ) );

		$this->mock->expects( $this->exactly( 2 ) )->method( 'sadd' );

		$this->mock->expects( $this->once() )->method( 'execute' );

		$this->mock->expects( $this->once() )->method( 'expireat' )
			->with( $this->equalTo( '1-t:1' ), $this->greaterThan( 0 ) );

		$this->object->setMultiple( array( 't:1' => 'test 1' ), array( 't:1' => '2000-01-01 00:00:00' ), array( 'tag1', 'tag2' ) );
	}
}
