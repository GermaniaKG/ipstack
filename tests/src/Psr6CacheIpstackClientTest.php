<?php
namespace tests;

use Germania\IpstackClient\IpstackClientPsr6CacheDecorator;
use Germania\IpstackClient\IpstackClient;

class Psr6CacheIpstackClientTest extends \PHPUnit\Framework\TestCase
{
	use CredentialsTrait;

	public $cache_itempool;

	public function setUp()
	{
		$options = array('path' => $GLOBALS['IPSTACK_CACHE_PATH'] );
		$driver = new \Stash\Driver\Sqlite( $options );
		$this->cache_itempool = new \Stash\Pool( $driver );

		parent::setUp();
	}


	/**
	 * @dataProvider provideValidCredentials
	 */
	public function testValidRequest( $client, $endpoint, $apikey, $client_ip)
	{
		$ipstack_client = new IpstackClient( $endpoint, $apikey, $client );


		$sut = new IpstackClientPsr6CacheDecorator($ipstack_client, $this->cache_itempool);

		$result = $sut->get( $client_ip );
		print_r( $result );
		$this->assertInternalType( "array", $result );
		$this->assertArrayHasKey( "ip", $result );
		$this->assertArrayHasKey( "country_code", $result );
		$this->assertArrayHasKey( "country_name", $result );
	}




}