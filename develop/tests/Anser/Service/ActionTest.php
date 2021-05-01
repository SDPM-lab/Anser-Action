<?php

namespace SDPMlab\Anser\Service;

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

    public $testService1 = [
        "name" => "testService1",
        "address" => "localhost",
        "port" => 8080,
        "isHttps" => false
    ];

    public $testService2 = [
        "name" => "testService2",
        "address" => "localhost",
        "port" => 8081,
        "isHttps" => true
    ];

    public $errorService = [
        "name" => "errorService",
        "address" => "localhost",
        "port" => 7000,
        "isHttps" => false
    ];

    protected function setUp(): void
    {
        parent::setUp();
        ServiceList::cleanServiceList();
        ServiceList::setLocalServices([
            $this->testService1, $this->testService2, $this->errorService
        ]);
    }

    public function testNewAction()
    {
        $action = new Action("testService1","GET","/api/v1/user");
        $this->assertInstanceOf(ActionInterface::class, $action);
        $hrttpClient = $action->getHttpClient();
        $this->assertInstanceOf(ClientInterface::class,$hrttpClient);
    }

    public function testRequestSetting()
    {
        $action = new Action("testService1","GET","/api/v1/user");
        $requestSettings = $action->getRequestSetting();
        $this->assertInstanceOf(RequestSettings::class, $requestSettings);
        $checkArray = [
            "method" => "GET",
            "url" => "http://localhost:8080/",
            "path" => "/api/v1/user",
            "options" => [
                "timeout" => 2.0
            ]
        ];
        $this->assertEquals((array)$requestSettings,$checkArray);
    }

    public function testSetOptionsFunction()
    {
        $action = new Action("testService2","POST","/api/v1/user");
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
            "url" => "https://localhost:8081/",
            "path" => "/api/v1/user",
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
        $action = new Action("testService2","POST","/api/v1/user");
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
        $action = new Action("testService1","GET","/api/v1/user");
        $action->do();
        $response = $action->getResponse();
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $data = json_decode($response->getBody()->getContents(),true)["data"];
        $user = $data[0];
        $this->assertEquals($user,[
            "id" => 1,
            "name" => "amos",
            "age" => 24
        ]); 
        $this->assertEquals($action->getNumnerOfDoAction(),1);
        $this->assertEquals($action->isSuccess(),true);
    }

    public function testMeaningDataActionDo()
    {
        $action = new Action("testService1","GET","/api/v1/user");
        $action->doneHandler(function(
            \Psr\Http\Message\ResponseInterface $response,
            ActionInterface $runtimeAction
        ){
            $data = json_decode($response->getBody()->getContents(),true)["data"];
            $meaningData = [];
            foreach ($data as $item) {
                $meaningData[] = [
                    "id" => $item["id"]
                ];
            }
            $runtimeAction->setMeaningData($meaningData);
        });
        $action->do();
        $meaningData = $action->getMeaningData();
        $this->assertEquals($meaningData,[
            ["id"=>1],["id"=>2]
        ]);
    }

    public function testReturnMeaningDataActionDo()
    {
        $action = new Action("testService1","GET","/api/v1/user");
        $action->doneHandler(function(
            \Psr\Http\Message\ResponseInterface $response,
            ActionInterface $runtimeAction
        ){
            $data = json_decode($response->getBody()->getContents(),true)["data"];
            $meaningData = [];
            foreach ($data as $item) {
                $meaningData[] = [
                    "id" => $item["id"]
                ];
            }
            $runtimeAction->setMeaningData($meaningData);
        });
        $action->do();
        $meaningData = $action->getMeaningData();
        $this->assertEquals($meaningData,[
            ["id"=>1],["id"=>2]
        ]);
    }

    public function testRetryException()
    {
        $action = new Action("testService1","GET","/api/v1/fail");
        try {
            $action->setRetry(-1,0.3);
        } catch (\Exception $th) {
            $this->assertInstanceOf(\SDPMlab\Anser\Exception\ActionException::class,$th);
        }
        try {
            $action->setRetry(2,-1.2);
        } catch (\Exception $th) {
            $this->assertInstanceOf(\SDPMlab\Anser\Exception\ActionException::class,$th);
        }
    }

    public function testTimeException()
    {
        $action = new Action("testService1","GET","/api/v1/fail");
        try {
            $action->setTimeout(-0.3);
        } catch (\Exception $th) {
            $this->assertInstanceOf(\SDPMlab\Anser\Exception\ActionException::class,$th);
        }
    }

    public function testRetryActionDo()
    {
        $action = new Action("testService1","GET","/api/v1/fail");
        $action->setRetry(1,0.3);
        try {
            $action->do();
        } catch (\SDPMlab\Anser\Exception\ActionException $th) {
            $errorAction = $th->getAction();
            $numOfDoAction = $errorAction->getNumnerOfDoAction();
            $this->assertEquals($numOfDoAction,2);
            $this->assertEquals($errorAction->isSuccess(),false);
            $this->assertInstanceOf(ResponseInterface::class,$th->getResponse());
            $this->assertInstanceOf(RequestInterface::class,$th->getRequest());
        }

        $action = new Action("testService1","GET","/api/v1/fail/1");
        $action->setRetry(2,0.3);
        try {
            $action->do();
        } catch (\SDPMlab\Anser\Exception\ActionException $th) {
            $errorAction = $th->getAction();
            $numOfDoAction = $errorAction->getNumnerOfDoAction();
            $this->assertEquals($numOfDoAction,3);
            $this->assertEquals($errorAction->isSuccess(),false);
            $this->assertInstanceOf(ResponseInterface::class,$th->getResponse());
            $this->assertInstanceOf(RequestInterface::class,$th->getRequest());
        }

        $action = new Action("testService2","GET","/api/v1/fail/1");
        try {
            $action->do();
        } catch (\SDPMlab\Anser\Exception\ActionException $th) {
            $errorAction = $th->getAction();
            $numOfDoAction = $errorAction->getNumnerOfDoAction();
            $this->assertEquals($numOfDoAction,1);
            $this->assertEquals($errorAction->isSuccess(),false);
            $this->assertInstanceOf(RequestInterface::class,$th->getRequest());
        }
    }

    public function testFailHandler400ActionDo()
    {
        $action = new Action("testService1","GET","/api/v1/fail");
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
        $action = new Action("testService1","GET","/api/v1/fail/1");
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
        $this->assertEquals($errorCode,500);
        $this->assertEquals($action->getMeaningData()["message"],"Internal Server Error");
    }

    public function testConnectionError()
    {
        $action = new Action("errorService","GET","/api/v1/fail/1");
        $action->failHandler(function(ActionException $e){
            if($e->isConnectError()){
                $e->getAction()->setMeaningData("connectError");
            }
        })->setTimeout(1.0)->do();
        $this->assertEquals($action->getMeaningData(),"connectError");

        $action = new Action("errorService","GET","/api/v1/fail/1");
        try {
            $action->setTimeout(1.0)->do();
        } catch (\Exception $e) {
            $this->assertInstanceOf(ActionException::class,$e);
            $this->assertTrue($e->isConnectError());
        }
    }
}
