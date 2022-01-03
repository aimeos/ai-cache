<?php

namespace Aimeos\MW\Cache;


/**
 * Real test against server for class \Aimeos\MW\Cache\Redis.
 *
 * @license LGPLv3, http://www.gnu.org/licenses/lgpl.html
 * @copyright Metaways Infosystems GmbH, 2014
 * @copyright Aimeos (aimeos.org), 2015-2022
 */
class RedisServerTest extends \PHPUnit\Framework\TestCase
{
	public function testRun()
	{
		if( !class_exists( '\\Predis\\Client' ) ) {
			$this->markTestSkipped( 'Predis library not available' );
		}

		try
		{
			$predis = new \Predis\Client();

			$client = new \Aimeos\MW\Cache\Redis( [], $predis );
			$client->clear();
		}
		catch( \Exception $e )
		{
			$this->markTestSkipped( 'Predis server not available' );
		}


		$client->set( 'arc-single-key', 'single-value' );
		$valSingle = $client->get( 'arc-single-key' );
		$valNone = $client->get( 'arc-no-key', 'none' );

		$client->set( 'arc-mkey3', 'mvalue3', '2000-01-01 00:00:00' );
		$valExpired = $client->get( 'arc-mkey3' );

		$client->setMultiple( array( 'arc-mkey1' => 'mvalue1', 'arc-mkey2' => 'mvalue2' ) );
		$listNormal = $client->getMultiple( array( 'arc-mkey1', 'arc-mkey2' ) );

		$pairs = array( 'arc-mkey4' => 'mvalue4', 'arc-mkey5' => 'mvalue5' );
		$tags = array( 'arc-mkey4' => 'arc-mtag4', 'arc-mkey5' => 'arc-mtag5' );
		$expires = array( 'arc-mkey5' => '2000-01-01 00:00:00' );
		$client->setMultiple( $pairs, $expires, $tags );
		$listExpired = $client->getMultiple( array( 'arc-mkey4', 'arc-mkey5' ) );

		$client->deleteByTags( array( 'arc-mtag4', 'arc-mtag5' ) );
		$listDelByTags = $client->getMultiple( array( 'arc-mkey4', 'arc-mkey5' ) );

		$client->deleteMultiple( array( 'arc-mkey1', 'arc-mkey2' ) );
		$listDelList = $client->getMultiple( array( 'arc-mkey1', 'arc-mkey2' ) );

		$client->delete( 'arc-single-key' );
		$valDelSingle = $client->get( 'arc-single-key' );


		$this->assertEquals( 'single-value', $valSingle );
		$this->assertEquals( 'none', $valNone );
		$this->assertEquals( null, $valExpired );
		$this->assertEquals( array( 'arc-mkey1' => 'mvalue1', 'arc-mkey2' => 'mvalue2' ), $listNormal );
		$this->assertEquals( array( 'arc-mkey4' => 'mvalue4' ), $listExpired );
		$this->assertEquals( [], $listDelByTags );
		$this->assertEquals( [], $listDelList );
		$this->assertEquals( null, $valDelSingle );
	}
}
