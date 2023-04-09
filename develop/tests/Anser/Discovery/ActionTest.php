<?php

namespace SDPMlab\Anser\Discovery;

use CodeIgniter\Test\CIUnitTestCase;
use SDPMlab\Anser\Service\ServiceList;
use SDPMlab\Anser\Service\Action;
use SDPMlab\Anser\Service\ActionInterface;
use SDPMlab\Anser\Service\RequestSettings;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\RequestInterface;
use SDPMlab\Anser\Exception\ActionException;

class ActionTest extends CIUnitTestCase
{
    public $fabioRouteService = '';
    public $fabioProxyService = '';
    public $consulService = '';

    protected function setUp(): void
    {
        parent::setUp();
        ServiceList::setDiscoverConfig([
            "discoverMode" => "fabio",           // Anser load balance provides "none"、"default" and "fabio" options to implement service discovery.
            "default" => [
                'HttpClient' => ServiceList::getHttpClient(),
                'Address'    => $this->consulService,     // [required]
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
                'fabioRouteService' => $this->fabioRouteService,
                'fabioProxyService' => $this->fabioProxyService,
            ]
        ]);
        ServiceList::updateDiscoverServicesList();
    }

    public function testNewAction()
    {
        $action = new Action("CI5","GET","/");
        $this->assertInstanceOf(ActionInterface::class, $action);
        $httpClient = $action->getHttpClient();
        $this->assertInstanceOf(ClientInterface::class,$httpClient);
    }

    public function testRequestSetting()
    {
        $action = new Action("FakeRest","GET","/post");
        $requestSettings = $action->getRequestSetting();
        $this->assertInstanceOf(RequestSettings::class, $requestSettings);
        $checkArray = [
            "method" => "GET",
            "url" => $this->fabioProxyService."FakeRest/",
            "path" => "/post",
            "options" => [
                "timeout" => 2.0
            ]
        ];
        $this->assertEquals((array)$requestSettings,$checkArray);
    }

    public function testSetOptionsFunction()
    {
        $action = new Action("FakeRest","POST","/comments");
        $options = [
            'headers' => [
                'User-Agent' => 'testing/1.0',
                'Accept'     => 'application/json',
                'X-Foo'      => ['Bar', 'Baz']
            ],
            'json' => ['foo' => 'bar']
        ];
        $checkArray = [
            "method" => "POST",
            "url" =>$this->fabioProxyService."FakeRest/",
            "path" => "/comments",
            "options" => $options
        ];
        $action->setOptions($options)
               ->setTimeout(3.14);
        $checkArray["options"]["timeout"] = 3.14;
        $this->assertEquals((array)$action->getRequestSetting(),$checkArray);
        $this->assertEquals($action->getOptions(),$options);
    }

    public function testOptionsFunctions()
    {
        $action = new Action("FakeRest","POST","/comments");
        $options = [
            'json' => ['foo' => 'bar']
        ];
        $action->setOptions($options);
        $this->assertEquals($action->getOptions(),$options);

        $options["headers"] = [
            "Content-Type" => 'application/json'
        ];
        $action->addOption("headers",[
            "Content-Type" => 'application/json'
        ]);
        $this->assertEquals($action->getOptions(),$options);
        
        $action->removeOption("json");
        unset($options["json"]);
        $this->assertEquals($action->getOptions(),$options);
        $this->assertEquals($action->getOption("headers"),[
            "Content-Type" => 'application/json'
        ]);
        $this->assertNull($action->getOption("nullOption"));
    }

    public function testActionDo()
    {
        $action = new Action("FakeRest","GET","/profile");
        $action->do();
        $response = $action->getResponse();
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $data = json_decode($response->getBody()->getContents(),true);
        $this->assertEquals($data,[
            "u_key" => 1
        ]); 
        $this->assertEquals($action->getNumnerOfDoAction(),1);
        $this->assertEquals($action->isSuccess(),true);
    }
    public function testFailHandler400ActionDo()
    {
        $action = new Action("CI5","GET","/fail");
        $errorCode = 0;
        $action->failHandler(function(ActionException $e) use (&$errorCode){
            if($e->isClientError()){
                $response = $e->getResponse();
                $action = $e->getAction();
                $errorCode = $e->getStatusCode();
                $body = json_decode($response->getBody()->getContents(), true);
                $action->setMeaningData( ["message"=>$body["messages"]["error"]]);    
            }
        });
        $this->assertIsCallable($action->getFaileHandler());
        $action->do();
        $this->assertEquals($errorCode,429);
        $this->assertEquals($action->getMeaningData()["message"],"Too Many Requests");
    }

    public function testFailHandler500ActionDo()
    {
        $action = new Action("CI5","GET","/fail/1");
        $errorCode = 0;
        $action->failHandler(function(ActionException $e) use (&$errorCode){
            if($e->isServerError()){
                $response = $e->getResponse();
                $action = $e->getAction();
                $errorCode = $e->getStatusCode();
                
                $body = json_decode($response->getBody()->getContents(), true);
                $action->setMeaningData( ["message"=>$body["messages"]["error"]]);    
            }
        });
        $this->assertIsCallable($action->getFaileHandler());
        $action->do();
        var_dump($errorCode);
        $this->assertEquals($errorCode,500);
        $this->assertEquals($action->getMeaningData()["message"],"Internal Server Error");
    }

    public function testUrlServiceNameAction()
    {
        $action = new Action($this->fabioProxyService, "GET", "/FakeRest/profile");
        $action->do();
        $response = $action->getResponse();
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $data = json_decode($response->getBody()->getContents(),true);
        $this->assertEquals($data,[
            "u_key" => 1
        ]); 
        $this->assertEquals($action->getNumnerOfDoAction(),1);
        $this->assertEquals($action->isSuccess(),true);
    }
}
