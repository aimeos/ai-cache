<?php

/**
 * @license LGPLv3, http://www.gnu.org/licenses/lgpl.html
 * @copyright Metaways Infosystems GmbH, 2014
 * @copyright Aimeos (aimeos.org), 2015
 * @package MW
 * @subpackage Cache
 */


/**
 * Redis cache class.
 *
 * @package MW
 * @subpackage Cache
 */
class MW_Cache_Redis
	extends MW_Cache_Abstract
	implements MW_Cache_Interface
{
	private $_client;
	private $_siteid;


	/**
	 * Initializes the object instance.
	 *
	 * @param array $config Configuration for Predis client if instance should be created
	 * @param Predis\Client $client Predis client instance
	 */
	public function __construct( array $config, Predis\Client $resource )
	{
		$this->_client = $resource;
		$this->_siteid = ( isset( $config['siteid'] ) ? $config['siteid'] . '-' : null );

		if( isset( $config['auth'] ) && !$this->_client->auth( $config['auth'] ) ) {
			throw new MW_Cache_Exception( 'Authentication failed for Redis' );
		}
	}


	/**
	 * Removes the cache entries identified by the given keys.
	 *
	 * @inheritDoc
	 *
	 * @param array $keys List of key strings that identify the cache entries
	 * 	that should be removed
	 */
	public function deleteList( array $keys )
	{
		foreach( $keys as $idx => $key ) {
			$keys[$idx] = $this->_siteid . $key;
		}

		$this->_client->del( $keys );
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
		$result = $tagKeys = array();
		$pipe = $this->_client->pipeline();

		foreach( $tags as $tag )
		{
			$tag = $this->_siteid . 'tag:' . $tag;
			$pipe->smembers( $tag );
			$tagKeys[] = $tag;
		}

		foreach( $pipe->execute() as $keys )
		{
			foreach( $keys as $key ) {
				$result[$key] = null;
			}
		}

		$this->_client->del( array_merge( array_keys( $result ), $tagKeys ) );
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
	 * @throws MW_Cache_Exception If the cache server doesn't respond
	 */
	public function flush()
	{
		$this->_client->flushdb();
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
		if( ( $result = $this->_client->get( $this->_siteid . $key ) ) === null ) {
			return $default;
		}

		return $result;
	}


	/**
	 * Returns the cached values for the given cache keys if available.
	 *
	 * @inheritDoc
	 *
	 * @param array $keys List of key strings for the requested cache entries
	 * @return array Associative list of key/value pairs for the requested cache
	 * 	entries. If a cache entry doesn't exist, neither its key nor a value
	 * 	will be in the result list
	 */
	public function getList( array $keys )
	{
		$result = $actkeys = array();
		$len = strlen( $this->_siteid );

		foreach( $keys as $idx => $key ) {
			$actkeys[$idx] = $this->_siteid . $key;
		}

		foreach( $this->_client->mget( $actkeys ) as $idx => $value )
		{
			if( $value !== null && isset( $keys[$idx] ) ) {
				$result[ $keys[$idx] ] = $value;
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
	public function getListByTags( array $tags )
	{
		$result = $actkeys = array();
		$len = strlen( $this->_siteid );
		$pipe = $this->_client->pipeline();

		foreach( $tags as $tag ) {
			$pipe->smembers( $this->_siteid . 'tag:' . $tag );
		}

		foreach( $pipe->execute() as $keys )
		{
			foreach( $keys as $key ) {
				$actkeys[$key] = null;
			}
		}

		foreach( $this->_client->mget( array_keys( $actkeys ) ) as $idx => $value )
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
	 * @param array $tags List of tag strings that should be assoicated to the
	 * 	given value in the cache
	 * @param string|null $expires Date/time string in "YYYY-MM-DD HH:mm:ss"
	 * 	format when the cache entry expires
	 */
	public function set( $key, $value, array $tags = array(), $expires = null )
	{
		$key = $this->_siteid . $key;
		$pipe = $this->_client->pipeline();
		$pipe->set( $key, $value );

		foreach( $tags as $tag ) {
			$pipe->sadd( $this->_siteid . 'tag:' . $tag, $key );
		}

		if( $expires !== null && ( $timestamp = strtotime( $expires ) ) !== false ) {
			$pipe->expireat( $key, $timestamp );
		}

		$pipe->execute();
	}


	/**
	 * Adds or overwrites the given key/value pairs in the cache, which is much
	 * more efficient than setting them one by one using the set() method.
	 *
	 * @inheritDoc
	 *
	 * @param array $pairs Associative list of key/value pairs. Both must be
	 * 	a string
	 * @param array $tags Associative list of key/tag or key/tags pairs that should be
	 * 	associated to the values identified by their key. The value associated
	 * 	to the key can either be a tag string or an array of tag strings
	 * @param array $expires Associative list of key/datetime pairs.
	 */
	public function setList( array $pairs, array $tags = array(), array $expires = array() )
	{
		$actpairs = array();
		$pipe = $this->_client->pipeline();

		foreach( $pairs as $key => $value ) {
			$actpairs[ $this->_siteid . $key ] = $value;
		}

		$pipe->mset( $actpairs );

		foreach( $tags as $key => $tagList )
		{
			foreach( (array) $tagList as $tag ) {
				$pipe->sadd( $this->_siteid . 'tag:' . $tag, $this->_siteid . $key );
			}
		}

		foreach( $expires as $key => $datetime ) {
			$pipe->expireat( $this->_siteid . $key, strtotime( $datetime ) );
		}

		$pipe->execute();
	}
}
