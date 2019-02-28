<?php
namespace Germania\IpstackClient;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\Log\LoggerAwareTrait;

class IpstackClient implements IpstackClientInterface{
	
	use LoggerAwareTrait;

	/**
	 * @var ClientInterface
	 */
	public $guzzle_client;

	/**
	 * @var string
	 */
	public $http_method = "GET";

    /**
     * The ipstack API endpoint
     * @var string
     */
    public $ipstack_endpoint;

    /**
     * The ipstack API Key
     * @var string
     */
    public $ipstack_api_key;



    /**
     * @var array
     */
    public $ipstack_query_defaults = array(
        "output"     => "json",
        #"fields"     => "kkol,ip,country_code,country_name,latitude,longitude,region_name", #hostname
	);


    /**
     * @param string               $ipstack_endpoint
     * @param string               $ipstack_api_key
     * @param ClientInterface      $guzzle_client    Optional: Custom Guzzle Client
     * @param LoggerInterface|null $logger           Optional: PSR-3 Logger
     */
	public function __construct( string $ipstack_endpoint, string $ipstack_api_key, ClientInterface $guzzle_client = null, LoggerInterface $logger = null )
	{
		$this->ipstack_endpoint = $ipstack_endpoint;
		$this->ipstack_api_key  = $ipstack_api_key;

		$this->guzzle_client    = $guzzle_client ?: new Client;
		$this->setLogger( $logger ?: new NullLogger );

		$this->ipstack_query_defaults  = array_merge($this->ipstack_query_defaults, [
			"access_key" => $this->ipstack_api_key
		]);
	}


	/**
	 * @param  string $client_ip    [description]
	 * @param  array  $custom_query [description]
	 * @return array
	 *
	 * @throws IpstackExceptionInterface
	 */
	public function get( string $client_ip, array $custom_query = array() ) : array
	{
        $url = join("", [ $this->ipstack_endpoint, urlencode($client_ip) ]);
	    $query_params = array_merge($this->ipstack_query_defaults, $custom_query);

	    $logger_info = [
            'client_ip' => $client_ip,
            'method'    => $this->http_method,
            'endpoint'  => $this->ipstack_endpoint
        ];

    
    	// Request
	    try {
	    	$this->logger->debug("Requesting ipstack", $logger_info);
			$ipstack_response = $this->guzzle_client->request( 
				$this->http_method, 
				$url, 
				array('query' => $query_params)
			);		
	    } 
	    catch (GuzzleException $e) {
            $this->logger->error("GuzzleException", array_merge([
            	'exception' => get_class( $e ),
                'message'   => $e->getMessage()
            ], $logger_info));
            throw new IpstackRequestException("Request failed", 0, $e);
	    };


        // Evaluate response
        try {
	        $ipstack_response_body = $ipstack_response->getBody();
	        $ipstack = $this->decode( $ipstack_response_body );
        }
        catch (IpstackResponseException $e) {
            $this->logger->error("Ipstack ResponseException", array_merge([
                'message'   => $e->getMessage()
            ], $logger_info));
			throw $e;        	
        }

		return $ipstack;

	}


	/**
	 * @param  string $body Reponse body string
	 * @return array
	 *
	 * @throws IpstackResponseException
	 */
	public function decode( string $body ): array
	{
        // Evaluate response
        $ipstack = json_decode( $body, "as_array" );


        // Check for errors
        // https://ipstack.com/documentation#errors

        if ( array_key_exists("success", $ipstack) 
        and  $ipstack['success'] === false
    	and  array_key_exists("error", $ipstack)
    	and  is_array($ipstack['error'])):

    		$msg = sprintf("%s: %s", 
    			$ipstack['error']['type'] ?? 'Error',
    			$ipstack['error']['info'] ?? 'No description provided' );
			throw new IpstackResponseException($msg, $ipstack['error']['code'] ?? 0);

		endif;


		return $ipstack;
	} 


}