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
        } catch (\SDPMlab\Anser\Exception\ActionException $e) {
            $this->assertInstanceOf(ActionException::class,$e);
            $this->assertTrue($e->isConnectError());
        }
    }

    public function testUrlServiceNameAction()
    {
        $action = new Action("http://localhost:8080", "GET", "/api/v1/user");
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
    public function testSetRpcQueryFunction()
    {
        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");

        $method = 'add';
        $param  = [1,2]; 
        $id = "1";

        $rpcClient = ServiceList::getRpcClient();
        $testRpcRequest = $rpcClient->query($id, $method, $param)->encode();
        $testRpcDecode = json_decode($testRpcRequest,true);

        $action->setRpcQuery($method, $param, $id);
        $rpcRequest = $action->getRpcRequest();
        $rpcDecode = json_decode($rpcRequest,true);

        $this->assertNotNull($rpcRequest);
        $this->assertEquals($rpcDecode["method"],$testRpcDecode["method"]);
        $this->assertEquals($rpcDecode["params"],$testRpcDecode["params"]);
        $this->assertEquals($rpcDecode["id"],$testRpcDecode["id"]);
    }

    public function testRPCActionDo()
    {
        $method = 'add';
        $param  = [1,2]; 
        $id = "1";

        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action->setRpcQuery($method, $param,$id); 
        $action->do();
        $response = $action->getResponse();
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $data = ServiceList::getRpcClient()->decode($response->getBody())[0];
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ResultResponse::class, $data);
        $this->assertEquals($data->getValue(),3); 
        $this->assertEquals($data->getId(),$id);
        $this->assertEquals($action->isSuccess(),true);
    }

    public function testFailRPCHandlerMethodNotFoundActionDo()
    {
        $method = 'failMethod';
        $param  = [1,2]; 
        $id = "1";

        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action->setRpcQuery($method, $param,$id); 
        $errorCode = -1;
        $response = null;
        $action->failHandler(function(ActionException $e) use (&$errorCode,&$response){
            if($e->isRpcMethodError()){
                $response = $e->getRpcResponse();
                $action = $e->getAction();
                $errorCode = $e->getRpcCode();
                $action->setMeaningData([
                    "code" => 404,
                    "msg" => $e->getRpcMsg()
                ]);    
            }
        });
        
        $this->assertIsCallable($action->getFaileHandler());
        $action->do();
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class, $response);
        $this->assertEquals($errorCode,-32601);
        $this->assertEquals($action->getMeaningData()["msg"],"Method not found");
    }

    public function testFailRPCHandlerInvalidParamsActionDo()
    {
        $method = 'add';
        $param  = []; 
        $id = "1";

        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action->setRpcQuery($method, $param,$id); 
        $errorCode = -1;
        $response = null;
        $action->failHandler(function(ActionException $e) use (&$errorCode,&$response){
            if($e->isRpcInvalidParams()){
                $response = $e->getRpcResponse();
                $action = $e->getAction();
                $errorCode = $e->getRpcCode();
                $action->setMeaningData([
                    "code" => 500,
                    "msg" => $e->getRpcMsg()
                ]);    
            }
        });
        
        $this->assertIsCallable($action->getFaileHandler());
        $action->do();
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class, $response);
        $this->assertEquals($errorCode,-32602);
        $this->assertEquals($action->getMeaningData()["msg"],"Invalid params");
    }

    public function testFailRPCHandlerInvalidRequestActionDo()
    {
        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $closure = function () use ($action) {
            $action->rpcRequest = '[1,2,3]';
        };
        $binding = $closure->bindTo($action , get_class($action ));
        $binding();

        $errorCode = -1;
        $response = null;
        $action->failHandler(function(ActionException $e) use (&$errorCode,&$response){

            if($e->isRpcInvalidRequest()){
                $response = $e->getRpcResponse();
                $action = $e->getAction();
                $errorCode = $e->getRpcCode();
                $action->setMeaningData([
                    "code" => 400,
                    "msg" => $e->getRpcMsg()
                ]);    
            }
        });
        
        $this->assertIsCallable($action->getFaileHandler());
        $action->do();
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class, $response);
        $this->assertEquals($errorCode,-32600);
        $this->assertEquals($action->getMeaningData()["msg"],"Invalid Request");
    }

    public function testFailRPCHandlerParseErrorActionDo()
    {
        $method = 'add';
        $param  = [1,2]; 
        $id = "1";

        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action->setRpcQuery($method, $param,$id); 
        $closure = function () use ($action) {
            $action->rpcRequest = '"{"jsonrpc":"2.0","method":"add","params":[1,}"';
        };
        $binding = $closure->bindTo($action , get_class($action ));
        $binding();

        $errorCode = -1;
        $response = null;
        $action->failHandler(function(ActionException $e) use (&$errorCode,&$response){
            if($e->isRpcParseError()){
                $response = $e->getRpcResponse();
                $action = $e->getAction();
                $errorCode = $e->getRpcCode();
                $action->setMeaningData([
                    "code" => 500,
                    "msg" => $e->getRpcMsg()
                ]);    
            }
        });
        
        $this->assertIsCallable($action->getFaileHandler());
        $action->do();
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class, $response);
        $this->assertEquals($errorCode,-32700);
        $this->assertEquals($action->getMeaningData()["msg"],"Parse error");
    }

    public function testFailRPCHandlerServerErrorActionDo()
    {
        $method = 'implementationError';
        $param  = [1,2]; 
        $id = "1";

        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action->setRpcQuery($method, $param,$id); 
        $errorCode = -1;
        $response = null;
        $action->failHandler(function(ActionException $e) use (&$errorCode,&$response){
            if($e->isRpcInternalServerError()){
                $response = $e->getRpcResponse();
                $action = $e->getAction();
                $errorCode = $e->getRpcCode();
                $action->setMeaningData([
                    "code" => 500,
                    "msg" => $e->getRpcMsg()
                ]);    
            }
        });
        
        $this->assertIsCallable($action->getFaileHandler());
        $action->do();
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class, $response);
        $this->assertEquals($errorCode,-32099);
        $this->assertEquals($action->getMeaningData()["msg"],"Server error");
    }

    public function testFailRPCHandlerInternalErrorActionDo()
    {
        $method = 'InternalError';
        $param  = [1,2]; 
        $id = "1";

        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action->setRpcQuery($method, $param,$id); 
        $errorCode = -1;
        $response = null;
        $action->failHandler(function(ActionException $e) use (&$errorCode,&$response){
            if($e->isRpcInternalError()){
                $response = $e->getRpcResponse();
                $action = $e->getAction();
                $errorCode = $e->getRpcCode();
                $action->setMeaningData([
                    "code" => 500,
                    "msg" => $e->getRpcMsg()
                ]);    
            }
        });
        
        $this->assertIsCallable($action->getFaileHandler());
        $action->do();
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class, $response);
        $this->assertEquals($errorCode,-32603);
        $this->assertEquals($action->getMeaningData()["msg"],"Internal error");
    }

    public function testFailRpcHandler400ActionDo()
    {
        $method = 'error429RpcServer';
        $param  = [1,2]; 
        $id = "1";

        $action = new Action("http://localhost:8080","POST","/api/v1/error429RpcServer");
        $action->setRpcQuery($method, $param,$id); 
        $errorCode = 0;
        $action->failHandler(function(ActionException $e) use (&$errorCode){
            if($e->isClientError()){
                $response = $e->getResponse();
                $action = $e->getAction();
                $errorCode = $e->getStatusCode();
                $action->setMeaningData([
                    "code" => $errorCode,
                    "rpcCode" => $e->getRpcCode(),
                    "rpcMsg" => $e->getRpcMsg(),
                    "rpcData" => $e->getRpcData(),
                    "rpcId"     => $e->getRpcId()
                ]);   
            }
        });
        $this->assertIsCallable($action->getFaileHandler());
        $action->do();
        $this->assertEquals($errorCode,429);
        $this->assertNull($action->getMeaningData()["rpcCode"]);
        $this->assertEquals($action->getMeaningData()["rpcMsg"],"Too Many Requests");
        $this->assertNull($action->getMeaningData()["rpcData"]);
        $this->assertEquals($action->getMeaningData()["rpcId"],"1");
    }

    public function testFailRpcHandler500ActionDo()
    {
        $method = 'error500RpcServer';
        $param  = [1,2]; 
        $id = "1";

        $action = new Action("http://localhost:8080","POST","/api/v1/error500RpcServer");
        $action->setRpcQuery($method, $param,$id); 
        $errorCode = 0;
        $action->failHandler(function(ActionException $e) use (&$errorCode){
            if($e->isServerError()){
                $response = $e->getResponse();
                $action = $e->getAction();
                $errorCode = $e->getStatusCode();
                $action->setMeaningData([
                    "code" => $errorCode,
                    "rpcCode" => $e->getRpcCode(),
                    "rpcMsg" => $e->getRpcMsg(),
                    "rpcData" => $e->getRpcData(),
                    "rpcId"     => $e->getRpcId()
                ]);       
            }
        });
        $this->assertIsCallable($action->getFaileHandler());
        $action->do();
        $this->assertEquals($errorCode,500);
        $this->assertNull($action->getMeaningData()["rpcCode"]);
        $this->assertEquals($action->getMeaningData()["rpcMsg"],"Internal Server Error");
        $this->assertNull($action->getMeaningData()["rpcData"]);
        $this->assertEquals($action->getMeaningData()["rpcId"],"1");
    }

    public function testRpcConnectionError()
    {
        $method = 'add';
        $param  = [1,2]; 
        $id = "1";

        $action = new Action("errorService","POST","/api/v1/rpcServer");
        $action->setRpcQuery($method, $param,$id); 
        $action->failHandler(function(ActionException $e){
            if($e->isConnectError()){
                $e->getAction()->setMeaningData("connectError");
            }
        })->setTimeout(1.0)->do();
        $this->assertEquals($action->getMeaningData(),"connectError");

        $action = new Action("errorService","POST","/api/v1/rpcServer");
        $action->setRpcQuery($method, $param,$id); 
        try {
            $action->setTimeout(1.0)->do();
        } catch (\SDPMlab\Anser\Exception\ActionException $e) {
            $this->assertInstanceOf(ActionException::class,$e);
            $this->assertTrue($e->isConnectError());
        }
    }

    public function testFailRpcHandler400WithMethodNotExistActionDo()
    {
        $method = 'a';
        $param  = [1,2]; 
        $id = "1";

        $action = new Action("http://localhost:8080","POST","/api/v1/error429RpcServer");
        $action->setRpcQuery($method, $param,$id); 
        $errorCode = 0;
        $action->failHandler(function(ActionException $e) use (&$errorCode){
            if($e->isClientError()){
                if ($e->isRpcMethodError()) {
                    $response = $e->getResponse();
                    $action = $e->getAction();
                    $errorCode = $e->getStatusCode();
                    $action->setMeaningData([
                        "code" => $errorCode,
                        "rpcCode" => $e->getRpcCode(),
                        "rpcMsg" => $e->getRpcMsg(),
                        "rpcData" => $e->getRpcData(),
                        "rpcId"     => $e->getRpcId()
                    ]);  
                }
            }
        });
        $this->assertIsCallable($action->getFaileHandler());
        $action->do();
        $this->assertEquals($errorCode,429);
        $this->assertEquals($action->getMeaningData()["rpcCode"],-32601);
        $this->assertEquals($action->getMeaningData()["rpcMsg"],"Method not found");
        $this->assertNull($action->getMeaningData()["rpcData"]);
        $this->assertEquals($action->getMeaningData()["rpcId"],"1");
    }

    public function testFailRpcHandler500WithMethodNotExistActionDo()
    {
        $method = 'a';
        $param  = [1,2]; 
        $id = "1";

        $action = new Action("http://localhost:8080","POST","/api/v1/error500RpcServer");
        $action->setRpcQuery($method, $param,$id); 
        $errorCode = 0;
        $action->failHandler(function(ActionException $e) use (&$errorCode){
            if($e->isServerError()){
                if ($e->isRpcMethodError()) {
                    $response = $e->getResponse();
                    $action = $e->getAction();
                    $errorCode = $e->getStatusCode();
                    $action->setMeaningData([
                        "code" => $errorCode,
                        "rpcCode" => $e->getRpcCode(),
                        "rpcMsg" => $e->getRpcMsg(),
                        "rpcData" => $e->getRpcData(),
                        "rpcId"     => $e->getRpcId()
                    ]);  
                }    
            }
        });
        $this->assertIsCallable($action->getFaileHandler());
        $action->do();
        $this->assertEquals($errorCode,500);
        $this->assertEquals($action->getMeaningData()["rpcCode"],-32601);
        $this->assertEquals($action->getMeaningData()["rpcMsg"],"Method not found");
        $this->assertNull($action->getMeaningData()["rpcData"]);
        $this->assertEquals($action->getMeaningData()["rpcId"],"1");
    }

    public function testFailRpcHandler400WithParseErrorActionDo()
    {
        $method = 'add';
        $param  = [1,2]; 
        $id = "1";

        $action = new Action("http://localhost:8080","POST","/api/v1/error429RpcServer");
        $action->setRpcQuery($method, $param,$id); 
        $closure = function () use ($action) {
            $action->rpcRequest = '"{"jsonrpc":"2.0","method":"add","params":[1,}"';
        };
        $binding = $closure->bindTo($action , get_class($action ));
        $binding();

        $errorCode = 0;
        $action->failHandler(function(ActionException $e) use (&$errorCode){
            if($e->isClientError()){
                if ($e->isRpcParseError()) {
                    $response = $e->getResponse();
                    $action = $e->getAction();
                    $errorCode = $e->getStatusCode();
                    $action->setMeaningData([
                        "code" => $errorCode,
                        "rpcCode" => $e->getRpcCode(),
                        "rpcMsg" => $e->getRpcMsg(),
                        "rpcData" => $e->getRpcData(),
                        "rpcId"     => $e->getRpcId()
                    ]);  
                }
            }
        });
        $this->assertIsCallable($action->getFaileHandler());
        $action->do();
        $this->assertEquals($errorCode,429);
        $this->assertEquals($action->getMeaningData()["rpcCode"],-32700);
        $this->assertEquals($action->getMeaningData()["rpcMsg"],"Parse error");
        $this->assertNull($action->getMeaningData()["rpcData"]);
        $this->assertNull($action->getMeaningData()["rpcId"]);
    }

    public function testFailRpcHandler500WithParseErrorActionDo()
    {
        $method = 'add';
        $param  = [1,2]; 
        $id = "1";

        $action = new Action("http://localhost:8080","POST","/api/v1/error500RpcServer");
        $action->setRpcQuery($method, $param,$id); 
        $closure = function () use ($action) {
            $action->rpcRequest = '"{"jsonrpc":"2.0","method":"add","params":[1,}"';
        };
        $binding = $closure->bindTo($action , get_class($action ));
        $binding();

        $errorCode = 0;
        $action->failHandler(function(ActionException $e) use (&$errorCode){
            if($e->isServerError()){
                if ($e->isRpcParseError()) {
                    $response = $e->getResponse();
                    $action = $e->getAction();
                    $errorCode = $e->getStatusCode();
                    $action->setMeaningData([
                        "code" => $errorCode,
                        "rpcCode" => $e->getRpcCode(),
                        "rpcMsg" => $e->getRpcMsg(),
                        "rpcData" => $e->getRpcData(),
                        "rpcId"     => $e->getRpcId()
                    ]);  
                }
            }
        });
        $this->assertIsCallable($action->getFaileHandler());
        $action->do();
        $this->assertEquals($errorCode,500);
        $this->assertEquals($action->getMeaningData()["rpcCode"],-32700);
        $this->assertEquals($action->getMeaningData()["rpcMsg"],"Parse error");
        $this->assertNull($action->getMeaningData()["rpcData"]);
        $this->assertNull($action->getMeaningData()["rpcId"]);
    }

    public function testFailRpcHandler400WithInvalidParamsActionDo()
    {
        $method = 'add';
        $param  = []; 
        $id = "1";

        $action = new Action("http://localhost:8080","POST","/api/v1/error429RpcServer");
        $action->setRpcQuery($method, $param,$id); 
        $errorCode = 0;
        $action->failHandler(function(ActionException $e) use (&$errorCode){
            if($e->isClientError()){
                if ($e->isRpcInvalidParams()) {
                    $response = $e->getResponse();
                    $action = $e->getAction();
                    $errorCode = $e->getStatusCode();
                    $action->setMeaningData([
                        "code" => $errorCode,
                        "rpcCode" => $e->getRpcCode(),
                        "rpcMsg" => $e->getRpcMsg(),
                        "rpcData" => $e->getRpcData(),
                        "rpcId"     => $e->getRpcId()
                    ]);  
                }
            }
        });
        $this->assertIsCallable($action->getFaileHandler());
        $action->do();
        $this->assertEquals($errorCode,429);
        $this->assertEquals($action->getMeaningData()["rpcCode"],-32602);
        $this->assertEquals($action->getMeaningData()["rpcMsg"],"Invalid params");
        $this->assertNull($action->getMeaningData()["rpcData"]);
        $this->assertEquals($action->getMeaningData()["rpcId"],"1");
    }

    public function testFailRpcHandler500WithInvalidParamsActionDo()
    {
        $method = 'add';
        $param  = []; 
        $id = "1";

        $action = new Action("http://localhost:8080","POST","/api/v1/error500RpcServer");
        $action->setRpcQuery($method, $param,$id); 
        $errorCode = 0;
        $action->failHandler(function(ActionException $e) use (&$errorCode){
            if($e->isServerError()){
                if ($e->isRpcInvalidParams()) {
                    $response = $e->getResponse();
                    $action = $e->getAction();
                    $errorCode = $e->getStatusCode();
                    $action->setMeaningData([
                        "code" => $errorCode,
                        "rpcCode" => $e->getRpcCode(),
                        "rpcMsg" => $e->getRpcMsg(),
                        "rpcData" => $e->getRpcData(),
                        "rpcId"     => $e->getRpcId()
                    ]);  
                }    
            }
        });
        $this->assertIsCallable($action->getFaileHandler());
        $action->do();
        $this->assertEquals($errorCode,500);
        $this->assertEquals($action->getMeaningData()["rpcCode"],-32602);
        $this->assertEquals($action->getMeaningData()["rpcMsg"],"Invalid params");
        $this->assertNull($action->getMeaningData()["rpcData"]);
        $this->assertEquals($action->getMeaningData()["rpcId"],"1");
    }

    public function testFailRpcHandler400WithInvalidRequestActionDo()
    {
        $method = 'add';
        $param  = [1,2]; 
        $id = "1";

        $action = new Action("http://localhost:8080","POST","/api/v1/error429RpcServer");
        $action->setRpcQuery($method, $param,$id); 
        $closure = function () use ($action) {
            $action->rpcRequest = '[1,2,3]';
        };
        $binding = $closure->bindTo($action , get_class($action ));
        $binding();

        $errorCode = 0;
        $action->failHandler(function(ActionException $e) use (&$errorCode){
            if($e->isClientError()){
                if ($e->isRpcInvalidRequest()) {
                    $response = $e->getResponse();
                    $action = $e->getAction();
                    $errorCode = $e->getStatusCode();
                    $action->setMeaningData([
                        "code" => $errorCode,
                        "rpcCode" => $e->getRpcCode(),
                        "rpcMsg" => $e->getRpcMsg(),
                        "rpcData" => $e->getRpcData(),
                        "rpcId"     => $e->getRpcId()
                    ]);  
                }
            }
        });
        $this->assertIsCallable($action->getFaileHandler());
        $action->do();
        $this->assertEquals($errorCode,429);
        $this->assertEquals($action->getMeaningData()["rpcCode"],-32600);
        $this->assertEquals($action->getMeaningData()["rpcMsg"],"Invalid Request");
        $this->assertNull($action->getMeaningData()["rpcData"]);
        $this->assertNull($action->getMeaningData()["rpcId"]);
    }

    public function testFailRpcHandler500WithInvalidRequestActionDo()
    {
        $method = 'add';
        $param  = [1,2]; 
        $id = "1";

        $action = new Action("http://localhost:8080","POST","/api/v1/error500RpcServer");
        $action->setRpcQuery($method, $param,$id); 
        $closure = function () use ($action) {
            $action->rpcRequest = '[1,2,3]';
        };
        $binding = $closure->bindTo($action , get_class($action ));
        $binding();

        $errorCode = 0;
        $action->failHandler(function(ActionException $e) use (&$errorCode){
            if($e->isServerError()){
                if ($e->isRpcInvalidRequest()) {
                    $response = $e->getResponse();
                    $action = $e->getAction();
                    $errorCode = $e->getStatusCode();
                    $action->setMeaningData([
                        "code" => $errorCode,
                        "rpcCode" => $e->getRpcCode(),
                        "rpcMsg" => $e->getRpcMsg(),
                        "rpcData" => $e->getRpcData(),
                        "rpcId"     => $e->getRpcId()
                    ]);  
                }
            }
        });
        $this->assertIsCallable($action->getFaileHandler());
        $action->do();
        $this->assertEquals($errorCode,500);
        $this->assertEquals($action->getMeaningData()["rpcCode"],-32600);
        $this->assertEquals($action->getMeaningData()["rpcMsg"],"Invalid Request");
        $this->assertNull($action->getMeaningData()["rpcData"]);
        $this->assertNull($action->getMeaningData()["rpcId"]);
    }

    public function testFailRpcHandler400WithInternalErrorActionDo()
    {
        $method = 'InternalError';
        $param  = [1,2]; 
        $id = "1";

        $action = new Action("http://localhost:8080","POST","/api/v1/error429RpcServer");
        $action->setRpcQuery($method, $param,$id); 
        $errorCode = 0;
        $action->failHandler(function(ActionException $e) use (&$errorCode){
            if($e->isClientError()){
                if ($e->isRpcInternalError()) {
                    $response = $e->getResponse();
                    $action = $e->getAction();
                    $errorCode = $e->getStatusCode();
                    $action->setMeaningData([
                        "code" => $errorCode,
                        "rpcCode" => $e->getRpcCode(),
                        "rpcMsg" => $e->getRpcMsg(),
                        "rpcData" => $e->getRpcData(),
                        "rpcId"     => $e->getRpcId()
                    ]);  
                }
            }
        });
        $this->assertIsCallable($action->getFaileHandler());
        $action->do();
        $this->assertEquals($errorCode,429);
        $this->assertEquals($action->getMeaningData()["rpcCode"],-32603);
        $this->assertEquals($action->getMeaningData()["rpcMsg"],"Internal error");
        $this->assertNull($action->getMeaningData()["rpcData"]);
        $this->assertEquals($action->getMeaningData()["rpcId"],"1");
    }

    public function testFailRpcHandler500WithInternalErrorActionDo()
    {
        $method = 'InternalError';
        $param  = [1,2]; 
        $id = "1";

        $action = new Action("http://localhost:8080","POST","/api/v1/error500RpcServer");
        $action->setRpcQuery($method, $param,$id); 
        $errorCode = 0;
        $action->failHandler(function(ActionException $e) use (&$errorCode){
            if($e->isServerError()){
                if ($e->isRpcInternalError()) {
                    $response = $e->getResponse();
                    $action = $e->getAction();
                    $errorCode = $e->getStatusCode();
                    $action->setMeaningData([
                        "code" => $errorCode,
                        "rpcCode" => $e->getRpcCode(),
                        "rpcMsg" => $e->getRpcMsg(),
                        "rpcData" => $e->getRpcData(),
                        "rpcId"     => $e->getRpcId()
                    ]);  
                }    
            }
        });
        $this->assertIsCallable($action->getFaileHandler());
        $action->do();
        $this->assertEquals($errorCode,500);
        $this->assertEquals($action->getMeaningData()["rpcCode"],-32603);
        $this->assertEquals($action->getMeaningData()["rpcMsg"],"Internal error");
        $this->assertNull($action->getMeaningData()["rpcData"]);
        $this->assertEquals($action->getMeaningData()["rpcId"],"1");
    }

    public function testFailRpcHandler400WithInternalServerErrorActionDo()
    {
        $method = 'implementationError';
        $param  = [1,2]; 
        $id = "1";

        $action = new Action("http://localhost:8080","POST","/api/v1/error429RpcServer");
        $action->setRpcQuery($method, $param,$id); 
        $errorCode = 0;
        $action->failHandler(function(ActionException $e) use (&$errorCode){
            if($e->isClientError()){
                if ($e->isRpcInternalServerError()) {
                    $response = $e->getResponse();
                    $action = $e->getAction();
                    $errorCode = $e->getStatusCode();
                    $action->setMeaningData([
                        "code" => $errorCode,
                        "rpcCode" => $e->getRpcCode(),
                        "rpcMsg" => $e->getRpcMsg(),
                        "rpcData" => $e->getRpcData(),
                        "rpcId"     => $e->getRpcId()
                    ]);  
                }
            }
        });
        $this->assertIsCallable($action->getFaileHandler());
        $action->do();
        $this->assertEquals($errorCode,429);
        $this->assertEquals($action->getMeaningData()["rpcCode"],-32099);
        $this->assertEquals($action->getMeaningData()["rpcMsg"],"Server error");
        $this->assertEquals($action->getMeaningData()["rpcData"],"1");
        $this->assertEquals($action->getMeaningData()["rpcId"],"1");
    }
    public function testFailRpcHandler500WithInternalServerErrorActionDo()
    {
        $method = 'implementationError';
        $param  = [1,2]; 
        $id = "1";

        $action = new Action("http://localhost:8080","POST","/api/v1/error500RpcServer");
        $action->setRpcQuery($method, $param,$id); 
        $errorCode = 0;
        $action->failHandler(function(ActionException $e) use (&$errorCode){
            if($e->isServerError()){
                if ($e->isRpcInternalServerError()) {
                    $response = $e->getResponse();
                    $action = $e->getAction();
                    $errorCode = $e->getStatusCode();
                    
                    $action->setMeaningData([
                        "code" => $errorCode,
                        "rpcCode" => $e->getRpcCode(),
                        "rpcMsg" => $e->getRpcMsg(),
                        "rpcData" => $e->getRpcData(),
                        "rpcId"     => $e->getRpcId()
                    ]);  
                }    
            }
        });
        $this->assertIsCallable($action->getFaileHandler());
        $action->do();
        $this->assertEquals($errorCode,500);
        $this->assertEquals($action->getMeaningData()["rpcCode"],-32099);
        $this->assertEquals($action->getMeaningData()["rpcMsg"],"Server error");
        $this->assertEquals($action->getMeaningData()["rpcData"],"1");
        $this->assertEquals($action->getMeaningData()["rpcId"],"1");
    }
}
