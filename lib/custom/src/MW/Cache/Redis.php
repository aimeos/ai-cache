<?php

/**
 * @license LGPLv3, http://www.gnu.org/licenses/lgpl.html
 * @copyright Metaways Infosystems GmbH, 2014
 * @copyright Aimeos (aimeos.org), 2015-2017
 * @package MW
 * @subpackage Cache
 */


namespace Aimeos\MW\Cache;


/**
 * Redis cache class.
 *
 * @package MW
 * @subpackage Cache
 */
class Redis
	extends \Aimeos\MW\Cache\Base
	implements \Aimeos\MW\Cache\Iface
{
	private $client;
	private $siteid;


	/**
	 * Initializes the object instance.
	 *
	 * @param array $config Configuration for Predis client if instance should be created
	 * @param Predis\Client $client Predis client instance
	 */
	public function __construct( array $config, \Predis\Client $client )
	{
		$this->client = $client;
		$this->siteid = ( isset( $config['siteid'] ) ? $config['siteid'] . '-' : null );

		if( isset( $config['auth'] ) && !$this->client->auth( $config['auth'] ) ) {
			throw new \Aimeos\MW\Cache\Exception( 'Authentication failed for Redis' );
		}
	}


	/**
	 * Removes all entries of the site from the cache.
	 *
	 * @inheritDoc
	 *
	 * As Redis does only provider up to 20 different databases, this isn't enough
	 * to use one of them for each site. Alternatively, using the KEYS command
	 * to fetch all cache keys of a site and delete them afterwards can take very
	 * long for billions of keys. Therefore, flush() clears the cache entries of
	 * all sites.
	 *
	 * @throws \Aimeos\MW\Cache\Exception If the cache server doesn't respond
	 */
	public function clear()
	{
		$this->client->flushdb();
	}


	/**
	 * Removes the cache entries identified by the given keys.
	 *
	 * @inheritDoc
	 *
	 * @param \Traversable|array $keys List of key strings that identify the cache entries
	 * 	that should be removed
	 */
	public function deleteMultiple( $keys )
	{
		foreach( $keys as $idx => $key ) {
			$keys[$idx] = $this->siteid . $key;
		}

		$this->client->del( $keys );
	}


	/**
	 * Removes the cache entries identified by the given tags.
	 *
	 * @inheritDoc
	 *
	 * @param array $tags List of tag strings that are associated to one or more
	 * 	cache entries that should be removed
	 */
	public function deleteByTags( array $tags )
	{
		$result = $tagKeys = [];
		$pipe = $this->client->pipeline();

		foreach( $tags as $tag )
		{
			$tag = $this->siteid . 'tag:' . $tag;
			$pipe->smembers( $tag );
			$tagKeys[] = $tag;
		}

		foreach( $pipe->execute() as $keys )
		{
			foreach( $keys as $key ) {
				$result[$key] = null;
			}
		}

		$this->client->del( array_merge( array_keys( $result ), $tagKeys ) );
	}


	/**
	 * Returns the cached value for the given key.
	 *
	 * @inheritDoc
	 *
	 * @param string $key Path to the requested value like product/id/123
	 * @param mixed $default Value returned if requested key isn't found
	 * @return mixed Value associated to the requested key. If no value for the
	 *	key is found in the cache, the given default value is returned
	 */
	public function get( $key, $default = null )
	{
		if( ( $result = $this->client->get( $this->siteid . $key ) ) === null ) {
			return $default;
		}

		return $result;
	}


	/**
	 * Returns the cached values for the given cache keys if available.
	 *
	 * @inheritDoc
	 *
	 * @param \Traversable|array $keys List of key strings for the requested cache entries
	 * @param mixed $default Default value to return for keys that do not exist
	 * @return array Associative list of key/value pairs for the requested cache
	 * 	entries. If a cache entry doesn't exist, neither its key nor a value
	 * 	will be in the result list
	 */
	public function getMultiple( $keys, $default = null )
	{
		$result = $actkeys = [];

		foreach( $keys as $idx => $key ) {
			$actkeys[$idx] = $this->siteid . $key;
		}

		foreach( $this->client->mget( $actkeys ) as $idx => $value )
		{
			if( $value !== null && isset( $keys[$idx] ) ) {
				$result[ $keys[$idx] ] = $value;
			}
		}

		foreach( $keys as $key )
		{
			if( !isset( $result[$key] ) ) {
				$result[$key] = $default;
			}
		}

		return $result;
	}


	/**
	 * Returns the cached keys and values associated to the given tags if available.
	 *
	 * @inheritDoc
	 *
	 * @param array $tags List of tag strings associated to the requested cache entries
	 * @return array Associative list of key/value pairs for the requested cache
	 * 	entries. If a tag isn't associated to any cache entry, nothing is returned
	 * 	for that tag
	 */
	public function getMultipleByTags( array $tags )
	{
		$result = $actkeys = [];
		$len = strlen( $this->siteid );
		$pipe = $this->client->pipeline();

		foreach( $tags as $tag ) {
			$pipe->smembers( $this->siteid . 'tag:' . $tag );
		}

		foreach( $pipe->execute() as $keys )
		{
			foreach( $keys as $key ) {
				$actkeys[$key] = null;
			}
		}

		foreach( $this->client->mget( array_keys( $actkeys ) ) as $idx => $value )
		{
			if( isset( $keys[$idx] ) ) {
				$result[ substr( $keys[$idx], $len ) ] = $value;
			}
		}

		return $result;
	}


	/**
	 * Sets the value for the given key in the cache.
	 *
	 * @inheritDoc
	 *
	 * @param string $key Key string for the given value like product/id/123
	 * @param mixed $value Value string that should be stored for the given key
	 * @param int|string|null $expires Date/time string in "YYYY-MM-DD HH:mm:ss"
	 * 	format or as TTL value when the cache entry expires
	 * @param array $tags List of tag strings that should be assoicated to the
	 * 	given value in the cache
	 */
	public function set( $key, $value, $expires = null, array $tags = [] )
	{
		$key = $this->siteid . $key;
		$pipe = $this->client->pipeline();
		$pipe->set( $key, $value );

		foreach( $tags as $tag ) {
			$pipe->sadd( $this->siteid . 'tag:' . $tag, $key );
		}

		if( is_string( $expires ) ) {
			$pipe->expireat( $key, date_create( $expires )->getTimestamp() );
		} elseif( is_int( $expires ) ) {
			$pipe->expireat( $key, $expires );
		}

		$pipe->execute();
	}


	/**
	 * Adds or overwrites the given key/value pairs in the cache, which is much
	 * more efficient than setting them one by one using the set() method.
	 *
	 * @inheritDoc
	 *
	 * @param \Traversable|array $pairs Associative list of key/value pairs. Both must be
	 * 	a string
	 * @param array|int|string|null $expires Associative list of keys and datetime
	 *  string or integer TTL pairs.
	 * @param array $tags Associative list of key/tag or key/tags pairs that
	 *  should be associated to the values identified by their key. The value
	 *  associated to the key can either be a tag string or an array of tag strings
	 */
	public function setMultiple( $pairs, $expires = null, array $tags = [] )
	{
		$actpairs = [];
		$pipe = $this->client->pipeline();

		foreach( $pairs as $key => $value ) {
			$actpairs[ $this->siteid . $key ] = $value;
		}

		$pipe->mset( $actpairs );

		foreach( $pairs as $key => $value )
		{
			$expire = ( is_array( $expires ) && isset( $expires[$key] ) ? $expires[$key] : $expires );

			if( is_string( $expire ) ) {
				$pipe->expireat( $this->siteid . $key, date_create( $expire )->getTimestamp() );
			} elseif( is_int( $expire ) ) {
				$pipe->expire( $this->siteid . $key, $expire );
			}
		}

		foreach( $tags as $key => $tagList )
		{
			foreach( (array) $tagList as $tag ) {
				$pipe->sadd( $this->siteid . 'tag:' . $tag, $this->siteid . $key );
			}
		}

		$pipe->execute();
	}
}
