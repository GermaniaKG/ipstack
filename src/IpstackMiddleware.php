<?php
namespace Germania\IpstackClient;

use Germania\IpstackClient\IpstackClientInterface;
use Germania\IpstackClient\IpstackExceptionInterface;

use GuzzleHttp\Psr7\Response as GuzzleResponse;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;


/**
 * This Slim-style "Double Pass" middleware finds out the country where the client comes from
 * and stores the country code (DE or CH) with PSR-7 Request attribute.
 *
 * Requirement:
 *
 *     This middleware requires a ServerRequest attribute called "ip_address"
 *     as provided by akrabat's Slim Client IP address middleware 
 *     - which therefore must be executed before this one!
 *     
 *     https://github.com/akrabat/ip-address-middleware
 * 
 * Basic conecpts:
 *
 * IP to Geolocation:
 *     This class requires an IpstackClient instance provided by germania-kg/ipstack.
 *     which asks the "IP to Geolocation" API from ipstack (https://ipstack.com). 
 *     Since we currently are using the "free plan", usage is limited to 10.000 API calls per month.
 */
class IpstackMiddleware implements MiddlewareInterface
{
    use LoggerAwareTrait;


    /**
     * Maps ipstack response fields to Request attribute Names. 
     * 
     * Array keys are ipstack fields, values are attribute names,
     * for example:
     *
     *     array(
     *          "country_code" => "X-IpstackCountryCode",
     *          "language"     => "X-IpstackLanguage"
     *     );
     * 
     * @var array
     */
    public $ipstack_attributes = array();


    /**
     * Specifies the ipstack response fields that will be requested always.
     * 
     * @see https://ipstack.com/documentation#fields
     * @var array
     */
    public $ipstack_default_fields = array("ip", "country_code", "country_name");


    /**
     * Request attribute with IP address as described here:
     * http://www.slimframework.com/docs/v3/cookbook/ip-address.html
     * 
     * @var string
     */
    public $ip_address_attribute = "ip_address";


    /**
     * Request attribute to store the full ipstack information
     * 
     * @var string
     */
    public $ipstack_attribute = "ipstack";


    /**
     * @var IpstackClientInterface
     */
    public $ipstack_client;


    /**
     * @var integer
     */
    public $reponse_error_code = 400;


    /**
     * @param IpstackClientInterface $ipstack_client       IpstackClient
     * @param string                 $ip_address_attribute Optional: Request attribute name with Client IP address
     * @param array                  $request_attributes   Optional: Map ipstack fields to request attributes
     * @param LoggerInterface|null   $logger               Optional: PSR-3 Logger
     */
    public function __construct( IpstackClientInterface $ipstack_client, string $ip_address_attribute = null, array $ipstack_attributes = array(), LoggerInterface $logger = null )
    {
        $this->ipstack_client       = $ipstack_client;        
        $this->ip_address_attribute = $ip_address_attribute;        
        $this->ipstack_attributes   = $ipstack_attributes;
        
        $this->setLogger( $logger ?: new NullLogger );
    }


    /**
     * PSR-15 "Single pass" pattern
     * 
     * @param  ServerRequestInterface  $request [description]
     * @param  RequestHandlerInterface $handler [description]
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        if (!$this->business( $request)):
            $this->logger->info("Force Status 400 response");
            return new GuzzleResponse( $this->reponse_error_code );
        endif;

        // Call $handler, return response
        return $handler->handle($request);
    }



    /**
     * Slim3-style "Double Pass" pattern
     * 
     * @param  ServerRequestInterface $request
     * @param  ResponseInterface      $response
     * @param  callable               $next
     *
     * @return ResponseInterface
     */
    public function __invoke( ServerRequestInterface $request, ResponseInterface $response, callable $next )
    {

        if (!$this->business( $request)):
            $this->logger->info("Force Status 400 response");
            return $response->withStatus( $this->reponse_error_code  );
        endif;

        // Call $next middleware, return response
        return $next($request, $response);
    }




    protected function business( ServerRequestInterface $request )
    {
        $client_ip = $this->getClientIp( $request );

        if (!$this->assertClientIp( $client_ip )):
            return false;
        endif;

        // Ask IpstackClient and store result in Request
        $ipstack = $this->askIpStack( $client_ip );
        $request = $request->withAttribute( $this->ipstack_attribute, $ipstack);

        // Map certain ipstack fields to custom request attributes
        foreach( $this->ipstack_attributes as $field => $attr_name):
            $request = $request->withAttribute($attr_name, $ipstack[ $field ] ?? null );
        endforeach;

        return true;
    }



    /**
     * Returns the client's IP, either from request attribute name or REMOTE_ADDR.
     * 
     * @param  ServerRequestInterface $request The request
     * @return string                          Client IP address string
     */
	protected function getClientIp(ServerRequestInterface $request) : string
    {
        if (!empty($this->ip_address_attribute)):
        	$client_ip = $request->getAttribute( $this->ip_address_attribute );
        	$log_msg = "Use IP from Request attribute";
        	$ip_src  = $this->ip_address_attribute;
        else:
	    	$serverParams = $request->getServerParams();
	    	$client_ip = $serverParams['REMOTE_ADDR'] ?? "";

        	$log_msg = "Use IP from SERVER";
        	$ip_src  = "REMOTE_ADDR";
        endif;

    	$this->logger->debug($log_msg, [
			'src' => $ip_src,
			'ip' => $client_ip
    	]);        	

        return $client_ip ?: "";
    }



    /**
     * Asks the ipstack API about information for the given IP.
     * 
     * If something goes wrong, an array with default values
     * will be returned.
     *
     * @param  string $client_ip
     * @return array  ipstack response excerpt.
     */
    protected function askIpStack( string $client_ip ) : array
    {
    	// Prepare result set
    	$custom_fields  = array_keys( $this->ipstack_attributes );
    	$fields         = array_merge($custom_fields, $this->ipstack_default_fields);

    	$default_return = array_fill_keys($fields, null);
    	$default_return['ip'] = $client_ip;

    	// The business
        try {
            $ipstack = $this->ipstack_client->get( $client_ip, [
                "fields"     => join(",", $fields),
                "language"   => "de"
            ]);

            // Log things. Make sure to log only default fields
            // See "$ipstack_default_fields"
            $this->logger->notice("Success: ipstack response", [
                'client_ip'     => $ipstack['ip'],
                'country_code'  => $ipstack['country_code'],
                'country_name'  => $ipstack['country_name'],
            ]);

            // Merge ipstack response 
            $result = array_merge($default_return, $ipstack);
            return $result;

        }
        catch (IpstackExceptionInterface $e) {
        	// At least: 
            return $default_return;
        }

    }



    /**
     * Checks wether a given IP address is not empty and valid IPv4 or IP46.
     *
     * @param  string $client_ip
     * @return bool
     */
    protected function assertClientIp( string $client_ip ) : bool
    {

        if (!$client_ip) :
            $this->logger->error("Empty IP given?!");
            return false;
        endif;
        
        if( filter_var($client_ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) :
            $this->logger->debug("Valid IPv4 address");
            return true;
        
        elseif( filter_var($client_ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) :
            $this->logger->debug("Valid IPv6 address");
            return true;

        endif;

        $this->logger->warning("Client IP is neither IPv4 nor IPv6");
        return false;
    }

}
