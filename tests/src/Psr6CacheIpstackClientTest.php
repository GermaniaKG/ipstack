<?php
namespace tests;

use Germania\IpstackClient\IpstackClientPsr6CacheDecorator;
use Germania\IpstackClient\IpstackClient;
use Germania\IpstackClient\IpstackExceptionInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;

class Psr6CacheIpstackClientTest extends \PHPUnit\Framework\TestCase
{
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


	public function provideValidCredentials()
	{

		$ip       = $GLOBALS['IPSTACK_DUMMY_IP'];
		$endpoint = $GLOBALS['IPSTACK_ENDPOINT'];
		$apikey   = $GLOBALS['IPSTACK_APIKEY'];

		$response = $this->prophesize( ResponseInterface::class);
		$response->getBody()->willReturn( json_encode( array("ip" => $ip, "country_code" => "cc", "country_name" => "Country" )) );
		$response_stub = $response->reveal();

		$client = $this->prophesize( ClientInterface::class );
		$client->request(Argument::type('string'), Argument::type('string'), Argument::type('array'))->willReturn( $response_stub );
		$client_stub = $client->reveal();


		$params_set = array(
			[ $client_stub, "foo",     "bar",   $ip ]
		);

		if (empty($apikey)):
			return $params_set;
		endif;

		return array_merge($params_set, array(
			[ new Client,   $endpoint, $apikey, $ip ],
			[ null,         $endpoint, $apikey, $ip ],
		));
	}



}