<?php

/**
 * @license LGPLv3, http://www.gnu.org/licenses/lgpl.html
 * @copyright Metaways Infosystems GmbH, 2014
 * @copyright Aimeos (aimeos.org), 2015-2023
 * @package MAdmin
 * @subpackage Cache
 */


namespace Aimeos\MAdmin\Cache\Manager;


/**
 * Redis cache manager implementation.
 *
 * @package MAdmin
 * @subpackage Cache
 */
class Redis
	extends \Aimeos\MAdmin\Common\Manager\Base
	implements \Aimeos\MAdmin\Cache\Manager\Iface
{
	private ?\Aimeos\Base\Cache\Iface $object = null;
	private array $searchConfig = array(
		'cache.id' => array(
			'code' => 'cache.id',
			'internalcode' => '"id"',
			'label' => 'Cache ID',
			'type' => 'string',
		),
	);


	/**
	 * Returns the cache object
	 *
	 * @return \Aimeos\Base\Cache\Iface Cache object
	 */
	public function getCache() : \Aimeos\Base\Cache\Iface
	{
		if( !isset( $this->object ) )
		{
			$context = $this->context();
			$config = $context->config();

			$conn = $config->get( 'resource/cache/redis/connection' );
			$conf = $config->get( 'resource/cache/redis', [] );

			if( !class_exists( '\\Predis\\Client' ) ) {
				throw new \Aimeos\MAdmin\Cache\Exception( sprintf( 'Please install "%1$s" via composer first', 'predis/predis' ) );
			}

			$client = new \Predis\Client( $conn, $conf );
			$this->object = \Aimeos\Base\Cache\Factory::create( 'Redis', $conf, $client );
		}

		return $this->object;
	}


	/**
	 * Removes old entries from the storage.
	 *
	 * @param iterable $siteids List of IDs for sites whose entries should be deleted
	 * @return \Aimeos\MShop\Common\Manager\Iface Manager object for chaining method calls
	 */
	public function clear( iterable $siteids ) : \Aimeos\MShop\Common\Manager\Iface
	{
		return $this;
	}


	/**
	 * Creates a new empty item instance
	 *
	 * @param array $values Values the item should be initialized with
	 * @return \Aimeos\MShop\Common\Item\Iface New item object
	 */
	public function create( array $values = [] ) : \Aimeos\MShop\Common\Item\Iface
	{
		return $this->createItemBase( $values );
	}


	/**
	 * Adds a new cache to the storage.
	 *
	 * @param \Aimeos\MAdmin\Cache\Item\Iface $item Cache item that should be saved to the storage
	 * @param bool $fetch True if the new ID should be returned in the item
	 * @return \Aimeos\MAdmin\Cache\Item\Iface Cache item
	 */
	protected function saveItem( \Aimeos\MAdmin\Cache\Item\Iface $item, bool $fetch = true ) : \Aimeos\MAdmin\Cache\Item\Iface
	{
		if( $item->getId() === null ) {
			throw new \Aimeos\MAdmin\Cache\Exception( 'ID is required for caching' );
		}

		if( $item->isModified() )
		{
			$cache = $this->getCache();
			$cache->delete( $item->getId() );
			$cache->set( $item->getId(), $item->getValue(), $item->getTimeExpire(), $item->getTags() );
		}

		return $item;
	}


	/**
	 * Removes multiple items.
	 *
	 * @param \Aimeos\MShop\Common\Item\Iface[]|string[] $itemIds List of item objects or IDs of the items
	 * @return \Aimeos\MAdmin\Cache\Manager\Iface Manager object for chaining method calls
	 */
	public function delete( $itemIds ) : \Aimeos\MShop\Common\Manager\Iface
	{
		$this->getCache()->deleteMultiple( $itemIds );
		return $this;
	}


	/**
	 * Creates the cache object for the given cache id.
	 *
	 * @param string $id Cache ID to fetch cache object for
	 * @param array $ref List of domains to fetch list items and referenced items for
	 * @param bool|null $default Add default criteria or NULL for relaxed default criteria
	 * @return \Aimeos\MAdmin\Cache\Item\Iface Returns the cache item of the given id
	 * @throws \Aimeos\MAdmin\Cache\Exception If item couldn't be found
	 */
	public function get( string $id, array $ref = [], ?bool $default = false ) : \Aimeos\MShop\Common\Item\Iface
	{
		if( ( $value = $this->getCache()->get( $id ) ) === null ) {
			throw new \Aimeos\MAdmin\Cache\Exception( sprintf( 'Item with ID "%1$s" not found', $id ) );
		}

		return $this->createItemBase( array( 'id' => $id, 'value' => $value ) );
	}


	/**
	 * Search for cache entries based on the given criteria.
	 *
	 * @param \Aimeos\Base\Criteria\Iface $search Search object containing the conditions
	 * @param string[] $ref List of domains to fetch list items and referenced items for
	 * @param int &$total Number of items that are available in total
	 * @return \Aimeos\Map List of cache items implementing \Aimeos\MAdmin\Cache\Item\Iface
	 */
	public function search( \Aimeos\Base\Criteria\Iface $search, array $ref = [], ?int &$total = null ) : \Aimeos\Map
	{
		/** Not available in a reasonable implemented way by Redis */
		return map();
	}


	/**
	 * Returns the available manager types
	 *
	 * @param bool $withsub Return also the resource type of sub-managers if true
	 * @return array Type of the manager and submanagers, subtypes are separated by slashes
	 */
	public function getResourceType( bool $withsub = true ) : array
	{
		$path = 'madmin/cache/manager/submanagers';

		return $this->getResourceTypeBase( 'cache', $path, [], $withsub );
	}


	/**
	 * Returns the attributes that can be used for searching.
	 *
	 * @param bool $withsub Return also attributes of sub-managers if true
	 * @return array Returns a list of attribtes implementing \Aimeos\Base\Criteria\Attribute\Iface
	 */
	public function getSearchAttributes( bool $withsub = true ) : array
	{
		$path = 'madmin/cache/manager/submanagers';

		return $this->getSearchAttributesBase( $this->searchConfig, $path, [], $withsub );
	}


	/**
	 * Returns a new manager for cache extensions
	 *
	 * @param string $manager Name of the sub manager type in lower case
	 * @param string|null $name Name of the implementation, will be from configuration (or Default) if null
	 * @return \Aimeos\MShop\Common\Manager\Iface Manager for different extensions, e.g stock, tags, locations, etc.
	 */
	public function getSubManager( string $manager, ?string $name = null ) : \Aimeos\MShop\Common\Manager\Iface
	{
		return $this->getSubManagerBase( 'cache', $manager, $name );
	}


	/**
	 * Create new admin cache item object initialized with given parameters.
	 *
	 * @param array $values Associative list of key/value pairs of a job
	 * @return \Aimeos\MAdmin\Cache\Item\Iface
	 */
	protected function createItemBase( array $values = [] ) : \Aimeos\MAdmin\Cache\Item\Iface
	{
		$values['siteid'] = $this->context()->locale()->getSiteId();

		return new \Aimeos\MAdmin\Cache\Item\Standard( $values );
	}
}
