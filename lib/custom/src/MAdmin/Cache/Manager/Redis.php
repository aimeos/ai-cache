<?php

/**
 * @license LGPLv3, http://www.gnu.org/licenses/lgpl.html
 * @copyright Metaways Infosystems GmbH, 2014
 * @copyright Aimeos (aimeos.org), 2015
 * @package MAdmin
 * @subpackage Cache
 */


/**
 * Redis cache manager implementation.
 *
 * @package MAdmin
 * @subpackage Cache
 */
class MAdmin_Cache_Manager_Redis
	extends MAdmin_Common_Manager_Abstract
	implements MAdmin_Cache_Manager_Interface
{
	private $_object;
	private $_searchConfig = array(
		'cache.id' => array(
			'code' => 'cache.id',
			'internalcode' => '"id"',
			'label' => 'Cache ID',
			'type' => 'string',
			'internaltype' => MW_DB_Statement_Abstract::PARAM_STR,
		),
	);


	/**
	 * Returns the cache object
	 *
	 * @return MW_Cache_Interface Cache object
	 */
	public function getCache()
	{
		if( !isset( $this->_object ) )
		{
			$context = $this->_getContext();
			$config = $context->getConfig();

			$conn = $config->get( 'resource/cache/redis/connection' );
			$conf = $config->get( 'resource/cache/redis', array() );

			$client = new Predis\Client( $conn, $conf );
			$conf = array( 'siteid' => $context->getLocale()->getSiteId() );

			$this->_object = MW_Cache_Factory::createManager( 'Redis', $conf, $client );
		}

		return $this->_object;
	}


	/**
	 * Create new cache item object.
	 *
	 * @return MAdmin_Cache_Item_Interface
	 */
	public function createItem()
	{
		return $this->_createItem();
	}


	/**
	 * Adds a new cache to the storage.
	 *
	 * @param MAdmin_Cache_Item_Interface $item Cache item that should be saved to the storage
	 * @param boolean $fetch True if the new ID should be returned in the item
	 */
	public function saveItem( MShop_Common_Item_Interface $item, $fetch = true )
	{
		$iface = 'MAdmin_Cache_Item_Interface';
		if( !( $item instanceof $iface ) ) {
			throw new MAdmin_Cache_Exception( sprintf( 'Object is not of required type "%1$s"', $iface ) );
		}

		if( ! $item->isModified() ) {
			return;
		}

		$id = $item->getId();
		$cache = $this->getCache();

		$cache->delete( $id );
		$cache->set( $id, $item->getValue(), $item->getTags(), $item->getTimeExpire() );
	}


	/**
	 * Removes multiple items specified by ids in the array.
	 *
	 * @param array $ids List of IDs
	 */
	public function deleteItems( array $ids )
	{
		$this->getCache()->deleteList( $ids );
	}


	/**
	 * Creates the cache object for the given cache id.
	 *
	 * @param integer $id Cache ID to fetch cache object for
	 * @param array $ref List of domains to fetch list items and referenced items for
	 * @return MAdmin_Cache_Item_Interface Returns the cache item of the given id
	 * @throws MAdmin_Cache_Exception If item couldn't be found
	 */
	public function getItem( $id, array $ref = array() )
	{
		if( ( $value = $this->getCache()->get( $id ) ) === null ) {
			throw new MAdmin_Cache_Exception( sprintf( 'Item with ID "%1$s" not found', $id ) );
		}

		return $this->_createItem( array( 'id' => $id, 'value' => $value ) );
	}


	/**
	 * Search for cache entries based on the given criteria.
	 *
	 * @param MW_Common_Criteria_Interface $search Search object containing the conditions
	 * @param integer &$total Number of items that are available in total
	 *
	 * @return array List of cache items implementing MAdmin_Cache_Item_Interface
	 */
	public function searchItems( MW_Common_Criteria_Interface $search, array $ref = array(), &$total = null )
	{
		/** Not available in a reasonable implemented way by Redis */
		return array();
	}


	/**
	 * Returns the attributes that can be used for searching.
	 *
	 * @param boolean $withsub Return also attributes of sub-managers if true
	 * @return array Returns a list of attribtes implementing MW_Common_Criteria_Attribute_Interface
	 */
	public function getSearchAttributes( $withsub = true )
	{
		$path = 'classes/cache/manager/submanagers';

		return $this->_getSearchAttributes( $this->_searchConfig, $path, array(), $withsub );
	}


	/**
	 * Returns a new manager for cache extensions
	 *
	 * @param string $manager Name of the sub manager type in lower case
	 * @param string|null $name Name of the implementation, will be from configuration (or Default) if null
	 * @return mixed Manager for different extensions, e.g stock, tags, locations, etc.
	 */
	public function getSubManager( $manager, $name = null )
	{
		return $this->_getSubManager( 'cache', $manager, $name );
	}


	/**
	 * Create new admin cache item object initialized with given parameters.
	 *
	 * @param array $values Associative list of key/value pairs of a job
	 * @return MAdmin_Cache_Item_Interface
	 */
	protected function _createItem( array $values = array() )
	{
		$values['siteid'] = $this->_getContext()->getLocale()->getSiteId();

		return new MAdmin_Cache_Item_Default( $values );
	}
}
