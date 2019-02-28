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
	 * @param IpstackClientInterface $ipstack_client
	 * @param CacheItemPoolInterface $cache           PSR-6 Cache ItemPool
	 */
	public function __construct( IpstackClientInterface $ipstack_client, CacheItemPoolInterface $cache, LoggerInterface $logger = null )
	{
		$this->ipstack_client = $ipstack_client;
		$this->cache = $cache;
		$this->setLogger( $logger ?: new NullLogger );
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
            $this->logger->info("Write ipstack info to cache", $ipstack);
            $this->cache->save($item->set($ipstack));

        // Valid item found
        else:
            $this->logger->notice("Found ipstack info in cache", $ipstack);
        endif;

        return $ipstack;

	}	

}