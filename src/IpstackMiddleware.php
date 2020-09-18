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
     * @var string
     */
    public $success_loglevel = "notice";


    /**
     * @var string
     */
    public $invalid_ip_loglevel = "error";


    /**
     * @var string
     */
    public $ipstack_error_loglevel = "error";


    /**
     * @param IpstackClientInterface $ipstack_client       IpstackClient
     * @param string                 $ip_address_attribute Optional: Request attribute name with Client IP address
     * @param array                  $request_attributes   Optional: Map ipstack fields to request attributes
     * @param LoggerInterface|null   $logger               Optional: PSR-3 Logger
     */
    public function __construct( IpstackClientInterface $ipstack_client, string $ip_address_attribute = null, array $ipstack_attributes = array(), LoggerInterface $logger = null, string $success_loglevel = null , string $invalid_ip_loglevel = null , string $ipstack_error_loglevel = null )
    {
        $this->ipstack_client         = $ipstack_client;
        $this->ip_address_attribute   = $ip_address_attribute;
        $this->ipstack_attributes     = $ipstack_attributes;
        $this->success_loglevel       = $success_loglevel ?: $this->success_loglevel;
        $this->invalid_ip_loglevel    = $invalid_ip_loglevel ?: $this->invalid_ip_loglevel;
        $this->ipstack_error_loglevel = $ipstack_error_loglevel ?: $this->ipstack_error_loglevel;

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
        $ipstack = $this->business( $request);
        if (false === $ipstack):
            $m = sprintf("Could not determine client IP, force status '%s' response", $this->reponse_error_code);
            $this->logger->log($this->invalid_ip_loglevel, $m);
            return new GuzzleResponse( $this->reponse_error_code );
        endif;

        $request = $request->withAttribute( $this->ipstack_attribute, $ipstack);
        // Map certain ipstack fields to custom request attributes
        foreach( $this->ipstack_attributes as $field => $attr_name):
            $request = $request->withAttribute($attr_name, $ipstack[ $field ] ?? null );
        endforeach;

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
        $ipstack = $this->business( $request);
        if (false === $ipstack):
            $m = sprintf("Could not determine client IP, force status '%s' response", $this->reponse_error_code);
            $this->logger->log($this->invalid_ip_loglevel, $m);
            return $response->withStatus( $this->reponse_error_code  );
        endif;

        $request = $request->withAttribute( $this->ipstack_attribute, $ipstack);
        // Map certain ipstack fields to custom request attributes
        foreach( $this->ipstack_attributes as $field => $attr_name):
            $request = $request->withAttribute($attr_name, $ipstack[ $field ] ?? null );
        endforeach;


        // Call $next middleware, return response
        return $next($request, $response);
    }



    /**
     * Perfoms the middelware action
     *
     * @param  ServerRequestInterface $request
     * @return bool
     */
    protected function business( ServerRequestInterface $request )
    {
        $client_ip = $this->getClientIp( $request );

        if (!$this->assertClientIp( $client_ip )):
            return false;
        endif;

        // Ask IpstackClient and store result in Request
        $ipstack = $this->askIpStack( $client_ip );

        return $ipstack;
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
            'clientIp' => $client_ip
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
            $this->logger->log($this->success_loglevel, "Success: ipstack response", [
                'clientIp'     => $ipstack['ip'],
                'countryCode'  => $ipstack['country_code'],
                'countryName'  => $ipstack['country_name'],
            ]);

            // Merge ipstack response
            $result = array_merge($default_return, $ipstack);
            return $result;

        }
        catch (IpstackExceptionInterface $e) {
            $this->logger->log($this->ipstack_error_loglevel, "Asking ipstack failed", [
                'clientIp' => $client_ip,
                'exceptionClass' => get_class($e),
                'exceptionMessage' => $e->getMessage()
            ]);

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
