<?php

/**
 * @license LGPLv3, http://www.gnu.org/licenses/lgpl.html
 * @copyright Metaways Infosystems GmbH, 2014
 * @copyright Aimeos (aimeos.org), 2015-2020
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
	 * @return bool True on success and false on failure
	 */
	public function clear() : bool
	{
		return $this->client->flushdb() === 'OK' ? true : false;
	}


	/**
	 * Removes the cache entries identified by the given keys.
	 *
	 * @inheritDoc
	 *
	 * @param iterable $keys List of key strings that identify the cache entries that should be removed
	 * @return bool True if the items were successfully removed. False if there was an error.
	 * @throws \Psr\SimpleCache\InvalidArgumentException
	 */
	public function deleteMultiple( iterable $keys ) : bool
	{
		if( empty( $keys ) ) {
			return true;
		}

		foreach( $keys as $idx => $key ) {
			$keys[$idx] = $this->siteid . $key;
		}

		return $this->client->del( $keys ) === 'OK' ? true : false;
	}


	/**
	 * Removes the cache entries identified by the given tags.
	 *
	 * @inheritDoc
	 *
	 * @param iterable $tags List of tag strings that are associated to one or more cache entries that should be removed
	 * @return bool True if the items were successfully removed. False if there was an error.
	 * @throws \Psr\SimpleCache\InvalidArgumentException
	 */
	public function deleteByTags( iterable $tags ) : bool
	{
		if( empty( $tags ) ) {
			return true;
		}

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

		return $this->client->del( array_merge( array_keys( $result ), $tagKeys ) ) === 'OK' ? true : false;
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
	 * @throws \Psr\SimpleCache\InvalidArgumentException
	 */
	public function get( string $key, $default = null )
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
	 * @param iterable $keys List of key strings for the requested cache entries
	 * @param mixed $default Default value to return for keys that do not exist
	 * @return iterable A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
	 * @throws \Psr\SimpleCache\InvalidArgumentException
	 */
	public function getMultiple( iterable $keys, $default = null ) : iterable
	{
		$result = $actkeys = [];

		foreach( $keys as $idx => $key ) {
			$actkeys[$idx] = $this->siteid . $key;
		}

		foreach( $this->client->mget( $actkeys ) as $idx => $value )
		{
			if( $value !== null && isset( $keys[$idx] ) ) {
				$result[$keys[$idx]] = $value;
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
	 * Determines whether an item is present in the cache.
	 *
	 * @inheritDoc
	 *
	 * @param string $key The cache item key
	 * @return bool True if cache entry is available, false if not
	 * @throws \Psr\SimpleCache\InvalidArgumentException
	 */
	public function has( string $key ) : bool
	{
		return (bool) $this->client->exists();
	}


	/**
	 * Sets the value for the given key in the cache.
	 *
	 * @inheritDoc
	 *
	 * @param string $key Key string for the given value like product/id/123
	 * @param mixed $value Value string that should be stored for the given key
	 * @param \DateInterval|int|string|null $expires Date interval object,
	 *  date/time string in "YYYY-MM-DD HH:mm:ss" format or as integer TTL value
	 *  when the cache entry will expiry
	 * @param iterable $tags List of tag strings that should be assoicated to the cache entry
	 * @return bool True on success and false on failure.
	 * @throws \Psr\SimpleCache\InvalidArgumentException
	 */
	public function set( string $key, $value, $expires = null, iterable $tags = [] ) : bool
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

		return $pipe->execute() !== [] ? true : false;
	}


	/**
	 * Adds or overwrites the given key/value pairs in the cache, which is much
	 * more efficient than setting them one by one using the set() method.
	 *
	 * @inheritDoc
	 *
	 * @param iterable $pairs Associative list of key/value pairs. Both must be a string
	 * @param \DateInterval|int|string|null $expires Date interval object,
	 *  date/time string in "YYYY-MM-DD HH:mm:ss" format or as integer TTL value
	 *  when the cache entry will expiry
	 * @param iterable $tags List of tags that should be associated to the cache entries
	 * @return bool True on success and false on failure.
	 * @throws \Psr\SimpleCache\InvalidArgumentException
	 */
	public function setMultiple( iterable $pairs, $expires = null, iterable $tags = [] ) : bool
	{
		$actpairs = [];
		$pipe = $this->client->pipeline();

		foreach( $pairs as $key => $value ) {
			$actpairs[$this->siteid . $key] = $value;
		}

		$pipe->mset( $actpairs );

		foreach( $pairs as $key => $value )
		{
			if( $expires instanceof \DateInterval ) {
				$pipe->expireat( $this->siteid . $key, date_create()->add( $expires )->getTimestamp() );
			} elseif( is_string( $expires ) ) {
				$pipe->expireat( $this->siteid . $key, date_create( $expires )->getTimestamp() );
			} elseif( is_int( $expires ) ) {
				$pipe->expire( $this->siteid . $key, $expires );
			}
		}

		foreach( $tags as $key => $tagList )
		{
			foreach( (array) $tagList as $tag ) {
				$pipe->sadd( $this->siteid . 'tag:' . $tag, $this->siteid . $key );
			}
		}

		return $pipe->execute() !== [] ? true : false;
	}
}
