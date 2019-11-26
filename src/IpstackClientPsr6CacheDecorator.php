<?php
namespace Germania\IpstackClient;

use Germania\IpstackClient\IpstackClientInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\Log\LoggerAwareTrait;

class IpstackClientPsr6CacheDecorator implements IpstackClientInterface
{
	use LoggerAwareTrait;


	/**
	 * @var CacheItemPoolInterface
	 */
	public $cache;

	/**
	 * @var int|null
	 */
	public $cache_lifetime;

	/**
	 * @var string
	 */
	public $loglevel_name;


	/**
	 * @param IpstackClientInterface $ipstack_client
	 * @param CacheItemPoolInterface $cache           PSR-6 Cache ItemPool
	 */
	public function __construct( IpstackClientInterface $ipstack_client, CacheItemPoolInterface $cache, LoggerInterface $logger = null, string $loglevel_name = "info" )
	{
		$this->ipstack_client = $ipstack_client;
		$this->loglevel_name = $loglevel_name;
		$this->cache = $cache;
		$this->setLogger( $logger ?: new NullLogger );
	}


	/**
	 * @param int|null $lifetime Cache Lifetime in seconds (or NULL)
	 * @return self
	 */
	public function setCacheLifeTime( $lifetime )
	{
		$this->cache_lifetime = $lifetime;
		return $this;
	}

	/**
	 * @return int|null
	 */
	public function getCacheLifeTime()
	{
		return $this->cache_lifetime;
	}


	public function get( string $client_ip, array $custom_query = array() ) : array
    {

        $item = $this->cache->getItem( sha1($client_ip) );

        $ipstack = $item->get();

        // No valid item?
        if(!$item->isHit()):
            $this->logger->debug("No ipstack info in cache");

            // Run intensive code: Ask ipstack directly
            $ipstack = $this->ipstack_client->get( $client_ip, $custom_query );

            // Store data for future use.
            $this->logger->log( $this->loglevel_name, "Write ipstack info to cache", $ipstack);
            $item->set($ipstack);
            
            $lifetime = $this->getCacheLifeTime();
          	$item->expiresAfter($lifetime);

            $this->cache->save($item);

        // Valid item found
        else:
            $this->logger->log($this->loglevel_name, "Found ipstack info in cache", $ipstack);
        endif;

        return $ipstack;

	}	

}