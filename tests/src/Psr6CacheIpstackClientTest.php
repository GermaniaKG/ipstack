<?php
namespace tests;

use Germania\IpstackClient\IpstackClientPsr6CacheDecorator;
use Germania\IpstackClient\IpstackClient;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Prophecy\PhpUnit\ProphecyTrait;

class Psr6CacheIpstackClientTest extends \PHPUnit\Framework\TestCase
{
	use CredentialsTrait,
        ProphecyTrait;

	public $cache_itempool;
	public $nocache_itempool;

	public function setUp() : void
	{
		$this->cache_itempool = new FilesystemAdapter;

		$this->nocache_itempool = new ArrayAdapter();

		parent::setUp();
	}


	/**
	 * @dataProvider provideValidCredentials
	 */
	public function testCacheLifeTimeInterceptors( $client, $endpoint, $apikey, $client_ip)
	{
		$ipstack_client = new IpstackClient( $endpoint, $apikey, $client );

		$sut = new IpstackClientPsr6CacheDecorator($ipstack_client, $this->cache_itempool);
		$this->assertNull( $sut->getCacheLifeTime());

		$lifetime = 99;
		$this->assertEquals($lifetime, $sut->setCacheLifeTime($lifetime)->getCacheLifeTime());
	}


	/**
	 * @dataProvider provideValidCredentials
	 */
	public function testValidRequestOnCache( $client, $endpoint, $apikey, $client_ip)
	{
		$ipstack_client = new IpstackClient( $endpoint, $apikey, $client );


		$sut = new IpstackClientPsr6CacheDecorator($ipstack_client, $this->cache_itempool);
		$this->assertNull( $sut->getCacheLifeTime());

		$result = $sut->get( $client_ip );
		print_r( $result );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( "ip", $result );
		$this->assertArrayHasKey( "country_code", $result );
		$this->assertArrayHasKey( "country_name", $result );
	}


	/**
	 * @dataProvider provideValidCredentials
	 */
	public function testValidRequestOnNoCache( $client, $endpoint, $apikey, $client_ip)
	{
		$ipstack_client = new IpstackClient( $endpoint, $apikey, $client );


		$sut = new IpstackClientPsr6CacheDecorator($ipstack_client, $this->nocache_itempool);

		$result = $sut->get( $client_ip );
		print_r( $result );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( "ip", $result );
		$this->assertArrayHasKey( "country_code", $result );
		$this->assertArrayHasKey( "country_name", $result );
	}




}
