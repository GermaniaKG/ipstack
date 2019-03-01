<?php
namespace tests;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Prophecy\Argument;

trait CredentialsTrait
{

	public function provideValidCredentials( )
	{
		$endpoint = $GLOBALS['IPSTACK_ENDPOINT'];
		$apikey   = $GLOBALS['IPSTACK_APIKEY'];

		$ip4      = $GLOBALS['IPSTACK_DUMMY_IP4'];
		$ip6      = $GLOBALS['IPSTACK_DUMMY_IP6'];

		$response = $this->prophesize( ResponseInterface::class);
		$response->getBody()->willReturn( json_encode( array("ip" => $ip4, "country_code" => "cc", "country_name" => "Country" )) );
		$response_stub = $response->reveal();

		$client = $this->prophesize( ClientInterface::class );
		$client->request(Argument::type('string'), Argument::type('string'), Argument::type('array'))->willReturn( $response_stub );
		$client_stub = $client->reveal();


		$params_set = array(
			[ $client_stub, "foo",     "bar",   $ip4 ],
			[ $client_stub, "foo",     "bar",   $ip6 ]
		);

		if (empty($apikey)):
			return $params_set;
		endif;

		return array_merge($params_set, array(
			[ new Client,   $endpoint, $apikey, $ip4 ],
			[ null,         $endpoint, $apikey, $ip4 ],
			[ new Client,   $endpoint, $apikey, $ip6 ],
			[ null,         $endpoint, $apikey, $ip6 ]
		));
	}	
}