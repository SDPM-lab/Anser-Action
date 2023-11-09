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
        $rpcResponse = $action->getRpcResponse();
        $this->assertIsArray($rpcResponse);
        $this->assertEquals(count($rpcResponse),1);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\Response::class, $rpcResponse[0]);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ResultResponse::class, $rpcResponse[0]);
        $data = $action->getRpcResult();
        $this->assertEquals($data[0],3); 
        $id   = $action->getRpcId();
        $this->assertEquals($id[0],1);
        $this->assertEquals($action->isSuccess(),true);
    }
    
    public function testBatchRPCActionDo()
    {
        $method = 'add';
        $param  = [1,2]; 
        $id = "1";

        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action->setBatchRpcQuery([
            [$method, $param,$id],
            [$method, $param,$id],
            [$method, $param,$id]
        ]); 
        $action->do();
        $response = $action->getResponse();
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $rpcResponse = $action->getRpcResponse();
        $this->assertIsArray($rpcResponse);
        $this->assertEquals(count($rpcResponse),3);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\Response::class, $rpcResponse[0]);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ResultResponse::class, $rpcResponse[0]);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\Response::class, $rpcResponse[1]);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ResultResponse::class, $rpcResponse[1]);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\Response::class, $rpcResponse[2]);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ResultResponse::class, $rpcResponse[2]);
        $data = $action->getRpcResult();
        $this->assertEquals($data[0],3); 
        $this->assertEquals($data[1],3); 
        $this->assertEquals($data[2],3); 
        $id   = $action->getRpcId();
        $this->assertEquals($id[0],1); 
        $this->assertEquals($id[1],1); 
        $this->assertEquals($id[2],1); 
        $this->assertEquals($action->isSuccess(),true);
    }

    public function testFailHandlerBatchRPCErrorActionDo()
    {
        $method = 'failMethod';
        $param  = [1,2]; 
        $id = "1";

        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action->setBatchRpcQuery([
            [$method, $param,$id],
            [$method, $param,$id],
            [$method, $param,$id]
        ]); 
        $errRpc = [];
        $sucRpc = [];
        $action->failHandler(function(ActionException $e) use (&$errRpc,&$sucRpc){
            if($e->isRpcError()){
                $errRpc = $e->getErrorRpc();
                $sucRpc = $e->getSuccessRpc();
            }
        });
        
        $this->assertIsCallable($action->getFaileHandler());
        $action->do();
        $this->assertIsArray($errRpc);
        $this->assertNull($sucRpc);
        $this->assertEquals(count($errRpc),3);
    }

    public function testFailHandlerBatchRPCSuccessAndErrorActionDo()
    {
        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action->setBatchRpcQuery([
            ["failMethod", [1,2],"1"],
            ["add", [1,2],"1"],
            ["failMethod", [1,2],"1"]
        ]); 
        $action->setTimeout(3);
        $errRpc = [];
        $sucRpc = [];
        $action->failHandler(function(ActionException $e) use (&$errRpc,&$sucRpc){
            if($e->isRpcError()){
                $errRpc = $e->getErrorRpc();
                $sucRpc = $e->getSuccessRpc();
            }
        });
        
        $this->assertIsCallable($action->getFaileHandler());
        $action->do();
        $this->assertIsArray($errRpc);
        $this->assertIsArray($sucRpc);
        $this->assertEquals(count($errRpc),2);
        $this->assertEquals(count($sucRpc),1);
    }

    public function testRPCGetFailDetailsActionDo()
    {
        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action->setBatchRpcQuery([
            ["failMethod", [1,2],"1"],
            ["add", [1,2],"1"],
            ["failMethod", [1,2],"1"]
        ]); 
        $errRpc = [];
        $sucRpc = [];
        $action->failHandler(function(ActionException $e) use (&$errRpc,&$sucRpc){
            if($e->isRpcError()){
                $errRpc = $e->getErrorRpc();
                $sucRpc = $e->getSuccessRpc();
            }
        });
        
        $this->assertIsCallable($action->getFaileHandler());
        $action->do();
        $this->assertIsArray($errRpc);
        $this->assertIsArray($sucRpc);
        $this->assertEquals(count($errRpc),2);
        $this->assertEquals(count($sucRpc),1);
        $this->assertEquals($errRpc[0]->getId(),1);
        $this->assertEquals($errRpc[0]->getMessage(),"Method not found");
        $this->assertEquals($errRpc[0]->getCode(),-32601);
        $this->assertNull($errRpc[0]->getData());
        $this->assertEquals($errRpc[1]->getId(),1);
        $this->assertEquals($errRpc[1]->getMessage(),"Method not found");
        $this->assertEquals($errRpc[1]->getCode(),-32601);
        $this->assertNull($errRpc[1]->getData());
        $this->assertEquals($sucRpc[0]->getId(),1);
        $this->assertEquals($sucRpc[0]->getValue(),3);
    }

    public function testDoneHandlerRpcQueryDoActionWithGetData()
    {
        $method = 'add';
        $param  = [1,2]; 
        $id = "1";

        $action = (new Action(
            "http://localhost:8080",
            "POST",
            "/api/v1/rpcServer"
        ))
        ->setTimeout(5)
        ->setRpcQuery($method,$param,$id)
        ->doneHandler(static function(
            ResponseInterface $response,
            Action $runtimeAction
        ) {
            $rpcResponse = $runtimeAction->getRpcResponse();
            $rpcResultArr = $runtimeAction->getRpcResult();
            $rpcIdArr = $runtimeAction->getRpcId();
            $runtimeAction->setMeaningData([
                "response" => $rpcResponse,
                "rpcResultArr" => $rpcResultArr,
                "rpcIdArr" => $rpcIdArr
            ]);
        });
        
        $data = $action->do()->getMeaningData();
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ResultResponse::class,$data["response"][0]);
        $this->assertEquals($data["rpcResultArr"][0],3);
        $this->assertNotNull($data["rpcIdArr"][0]);
    }

    public function testFailHandlerRpcQueryDoActionWithGetData()
    {
        $method = 'failMethod';
        $param  = [1,2]; 
        $id = "1";

        $action = (new Action(
            "http://localhost:8080",
            "POST",
            "/api/v1/rpcServer"
        ))
        ->setTimeout(5)
        ->setRpcQuery($method,$param,$id)
        ->failHandler(function (
            ActionException $e
        ){
            if ($e->isRpcError()) {
                $errorResArr = $e->getErrorRpc();
                $result = [];
                foreach ($errorResArr as $errorRes) {
                    $result["error"][] = [
                        "response" => $errorRes,
                        "Id" => $errorRes->getId(),
                        "msg" => $errorRes->getMessage(),
                        "code" => $errorRes->getCode(),
                        "data" => $errorRes->getData()
                    ];
                }
                $e->getAction()->setMeaningData([
                    "code" => 400,
                    "result" => $result
                ]);
            }
        });

        $data = $action->do()->getMeaningData();
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class,$data["result"]["error"][0]["response"]);
        $this->assertNotNull($data["result"]["error"][0]["Id"]);
        $this->assertEquals($data["result"]["error"][0]["msg"],"Method not found");
        $this->assertEquals($data["result"]["error"][0]["code"],-32601);
        $this->assertNull($data["result"]["error"][0]["data"]);
    }

    public function testDoneHandlerBatchRpcQueryDoActionWithGetData()
    {
        $action = (new Action(
            "http://localhost:8080",
            "POST",
            "/api/v1/rpcServer"
        ))
        ->setTimeout(5)
        ->setBatchRpcQuery([
            ["add",[1,2]],
            ["add",[1,2]],
        ])
        ->doneHandler(static function(
            ResponseInterface $response,
            Action $runtimeAction
        ) {
            $rpcResponse = $runtimeAction->getRpcResponse();
            $rpcResultArr = $runtimeAction->getRpcResult();
            $rpcIdArr = $runtimeAction->getRpcId();
            $runtimeAction->setMeaningData([
                "response" => $rpcResponse,
                "rpcResultArr" => $rpcResultArr,
                "rpcIdArr" => $rpcIdArr
            ]);
        });

        $data = $action->do()->getMeaningData();
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ResultResponse::class,$data["response"][0]);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ResultResponse::class,$data["response"][1]);
        $this->assertEquals($data["rpcResultArr"][0],3);
        $this->assertEquals($data["rpcResultArr"][1],3);
        $this->assertNotNull($data["rpcIdArr"][0]);
        $this->assertNotNull($data["rpcIdArr"][1]);
    }

    public function testFailHandlerBatchRpcQueryDoActionWithAllFailData()
    {
        $action = (new Action(
            "http://localhost:8080",
            "POST",
            "/api/v1/rpcServer"
        ))
        ->setTimeout(5)
        ->setBatchRpcQuery([
            ["failMethod",[1,2]],
            ["failMethod",[1,2]],
        ])
        ->failHandler(function (
            ActionException $e
        ){
            if ($e->isRpcError()) {
                $errorResArr = $e->getErrorRpc();
                $successResArr = $e->getSuccessRpc();
                $result = [];
                foreach ($errorResArr as $errorRes) {
                    $result["error"][] = [
                        "response" => $errorRes,
                        "Id" => $errorRes->getId(),
                        "msg" => $errorRes->getMessage(),
                        "code" => $errorRes->getCode(),
                        "data" => $errorRes->getData()
                    ];
                }
                $e->getAction()->setMeaningData([
                    "code" => 400,
                    "result" => $result, // fail Result
                    "successResult" => $successResArr
                ]);
            }
        });

        $data = $action->do()->getMeaningData();
        $this->assertEquals(count($data["result"]["error"]),2);
        $this->assertNull($data["successResult"]);

        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class,$data["result"]["error"][0]["response"]);
        $this->assertNotNull($data["result"]["error"][0]["Id"]);
        $this->assertEquals($data["result"]["error"][0]["msg"],"Method not found");
        $this->assertEquals($data["result"]["error"][0]["code"],-32601);
        $this->assertNull($data["result"]["error"][0]["data"]);

        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class,$data["result"]["error"][1]["response"]);
        $this->assertNotNull($data["result"]["error"][1]["Id"]);
        $this->assertEquals($data["result"]["error"][1]["msg"],"Method not found");
        $this->assertEquals($data["result"]["error"][1]["code"],-32601);
        $this->assertNull($data["result"]["error"][1]["data"]);
    }

    public function testFailHandlerBatchRpcQueryDoActionWithSuccessAndFailData()
    {
        $action = (new Action(
            "http://localhost:8080",
            "POST",
            "/api/v1/rpcServer"
        ))
        ->setTimeout(5)
        ->setBatchRpcQuery([
            ["add",[1,2]],
            ["failMethod",[1,2]],
        ])
        ->failHandler(function (
            ActionException $e
        ){
            if ($e->isRpcError()) {
                $errorResArr = $e->getErrorRpc();
                $successResArr = $e->getSuccessRpc();
                $result = [];
                foreach ($errorResArr as $errorRes) {
                    $result["error"][] = [
                        "response" => $errorRes,
                        "Id" => $errorRes->getId(),
                        "msg" => $errorRes->getMessage(),
                        "code" => $errorRes->getCode(),
                        "data" => $errorRes->getData()
                    ];
                }
                foreach ($successResArr as $successRes) {
                    $result["success"][] = [
                        "response" => $successRes,
                        "Id" => $successRes->getId(),
                        "result" => $successRes->getValue(),
                    ];
                }
                $e->getAction()->setMeaningData([
                    "code" => 400,
                    "result" => $result,
                ]);
            }
        });

        $data = $action->do()->getMeaningData();
        $this->assertEquals(count($data["result"]["error"]),1);
        $this->assertEquals(count($data["result"]["success"]),1);

        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ResultResponse::class,$data["result"]["success"][0]["response"]);
        $this->assertNotNull($data["result"]["success"][0]["Id"]);
        $this->assertEquals($data["result"]["success"][0]["result"],3);

        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class,$data["result"]["error"][0]["response"]);
        $this->assertNotNull($data["result"]["error"][0]["Id"]);
        $this->assertEquals($data["result"]["error"][0]["msg"],"Method not found");
        $this->assertEquals($data["result"]["error"][0]["code"],-32601);
        $this->assertNull($data["result"]["error"][0]["data"]);
    }

    public function testFailHandlerBatchRpcQueryDoActionWith4XXError()
    {
        $errorCode = 0;
        $action = (new Action(
            "http://localhost:8080",
            "POST",
            "/api/v1/error429RpcServer"
        ))
        ->setTimeout(5)
        ->setBatchRpcQuery([
            ["add",[1,2]],
            ["failMethod",[1,2]],
        ])
        ->failHandler(function (
            ActionException $e
        ) use (&$errorCode){
            if($e->isClientError()){
                $errorCode = $e->getStatusCode();
                $rpcResponses = $e->getRpcByResponse();
                $sucResponse = $e->getSuccessRpcByResponse();
                $errResponse = $e->getErrorRpcByResponse();
                $e->getAction()->setMeaningData([
                    "code" => 400,
                    "response" => $rpcResponses,
                    "success" => [
                        "id" => $sucResponse[0]->getId(),
                        "result" => $sucResponse[0]->getValue()
                    ],
                    "error" => [
                        "id" => $errResponse[0]->getId(),
                        "msg" => $errResponse[0]->getMessage(),
                        "code" => $errResponse[0]->getCode(),
                        "data" => $errResponse[0]->getData()
                    ]
                ]);
            }
        });

        $data = $action->do()->getMeaningData();
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ResultResponse::class,$data["response"][0]);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class,$data["response"][1]);
        $this->assertNotNull($data["success"]["id"]);
        $this->assertEquals($data["success"]["result"],3);
        $this->assertNotNull($data["error"]["id"]);
        $this->assertEquals($data["error"]["msg"],"Method not found");
        $this->assertEquals($data["error"]["code"],-32601);
        $this->assertNull($data["error"]["data"]);
    }
    
}
