<?php
namespace App;
require './vendor/autoload.php';
use SDPMlab\Anser\Service\ServiceList;

use \SDPMlab\Anser\Service\SimpleService;
use \SDPMlab\Anser\Exception\ActionException;
use \Psr\Http\Message\ResponseInterface;
use \SDPMlab\Anser\Service\Action;
use \SDPMlab\Anser\Service\ActionInterface;

$fabioRouteService = '';   // http://fabioIP:9998
$fabioProxyService = '';   // http://fabioIP:9999
$consulService = '';       // http://consulIP:8500

ServiceList::setDiscoverConfig([
    "discoverMode" => "fabio",           // Anser load balance provides "none"、"default" and "fabio" options to implement service discovery.
    "default" => [
        'HttpClient' => ServiceList::getHttpClient(),
        'Address'    => $consulService,     // [required]
        'Scheme'     => 'http',                    // [optional] defaults to "http"  [option: HTTP | HTTPS]
        // 'Datacenter' => 'name of datacenter',   // [optional]
        // 'HttpAuth' => 'user:pass',              // [optional]
        // 'WaitTime' => '0s',                     // [optional] amount of time to wait on certain blockable endpoints.  go time duration string format. 
        // 'Token' => 'auth token',                // [optional] default auth token to use
        // 'TokenFile' => 'file with auth token',  // [optional] file containing auth token string
        // 'InsecureSkipVerify' => false,          // [optional] if set to true, ignores all SSL validation
        // 'CAFile' => '',                         // [optional] path to ca cert file, see http://docs.guzzlephp.org/en/latest/request-options.html#verify
        // 'CertFile' => '',                       // [optional] path to client public key.  if set, requires KeyFile also be set
        // 'KeyFile' => '',                        // [optional] path to client private key.  if set, requires CertFile also be set
        // 'JSONEncodeOpts'=> 0,                   // [optional] php json encode opt value to use when serializing requests
    ],
    "fabio" => [
        'fabioRouteService' => $fabioRouteService,
        'fabioProxyService' => $fabioProxyService,
    ]
]);

class TestService extends SimpleService
{
    protected $serviceName = "CI5";

    protected $retry      = 1;
    protected $retryDelay = 1;
    protected $timeout    = 10.0;

    /**
     * Get all product
     *
     * @param integer|null $limit
     * @param integer|null $offset
     * @param string|null $isDesc
     * @return ActionInterface
     */
    public function getAllProduct(): ActionInterface
    {
        $action = $this->getAction("GET", "/");

        $action->doneHandler(
            function (
                ResponseInterface $response,
                Action $action
            ) {
                $resBody = $response->getBody()->getContents();
                $data    = json_decode($resBody, true);
                $action->setMeaningData($data);
                // $header = $response->getHeaders();
                // $action->setMeaningData($header);
            }
        )
        ->failHandler(
            function (
                ActionException $e
            ) {
                $e->getAction()->setMeaningData(["message" => $e->getMessage()]);
            }
        );
        return $action;
    }
}
class Test1Service extends SimpleService
{
    protected $serviceName = "test_service";

    protected $retry      = 1;
    protected $retryDelay = 1;
    protected $timeout    = 10.0;

    /**
     * Get all product
     *
     * @param integer|null $limit
     * @param integer|null $offset
     * @param string|null $isDesc
     * @return ActionInterface
     */
    public function getAllProduct(): ActionInterface
    {
        $action = $this->getAction("GET", "/api/v1/user");

        $action->doneHandler(
            function (
                ResponseInterface $response,
                Action $action
            ) {
                $resBody = $response->getBody()->getContents();
                $data    = json_decode($resBody, true);
                $action->setMeaningData($data);
                // $header = $response->getHeaders();
                // $action->setMeaningData($header);
            }
        )
        ->failHandler(
            function (
                ActionException $e
            ) {
                $e->getAction()->setMeaningData(["message" => $e->getMessage()]);
            }
        );
        return $action;
    }
}
$a = new TestService();
$b = $a->getAllProduct()->do()->getMeaningData();
var_dump($b);

$c = new Test1Service();
$d = $c->getAllProduct()->do()->getMeaningData();
var_dump($d);
