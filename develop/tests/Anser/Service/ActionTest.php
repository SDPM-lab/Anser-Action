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
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\Response::class, $rpcResponse[$id]);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ResultResponse::class, $rpcResponse[$id]);
        $rpcResponseById = $action->getRpcResponse($id);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\Response::class, $rpcResponseById);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ResultResponse::class, $rpcResponseById);
        $data = $action->getRpcResult();
        $this->assertEquals($data[$id],3); 
        $dataById = $action->getRpcResult($id);
        $this->assertEquals($dataById,3); 
        $this->assertEquals($action->isSuccess(),true);
    }
    
    public function testBatchRPCActionDo()
    {
        $method = 'add';
        $param  = [1,2]; 
        $id1 = "1";
        $id2 = "2";
        $id3 = "3";
        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action->setBatchRpcQuery([
            [$method, $param,$id1],
            [$method, $param,$id2],
            [$method, $param,$id3]
        ]); 
        $action->do();
        $response = $action->getResponse();
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $rpcResponse = $action->getRpcResponse();
        $this->assertIsArray($rpcResponse);
        $this->assertEquals(count($rpcResponse),3);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\Response::class, $rpcResponse[$id1]);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ResultResponse::class, $rpcResponse[$id1]);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\Response::class, $rpcResponse[$id2]);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ResultResponse::class, $rpcResponse[$id2]);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\Response::class, $rpcResponse[$id3]);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ResultResponse::class, $rpcResponse[$id3]);
        $rpcResponseById1 = $action->getRpcResponse($id1);
        $rpcResponseById2 = $action->getRpcResponse($id2);
        $rpcResponseById3 = $action->getRpcResponse($id3);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\Response::class, $rpcResponseById1);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ResultResponse::class, $rpcResponseById1);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\Response::class, $rpcResponseById2);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ResultResponse::class, $rpcResponseById2);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\Response::class, $rpcResponseById3);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ResultResponse::class, $rpcResponseById3);
        $data = $action->getRpcResult();
        $this->assertEquals($data[$id1],3); 
        $this->assertEquals($data[$id2],3); 
        $this->assertEquals($data[$id3],3); 
        $dataById1 = $action->getRpcResult($id1);
        $dataById2 = $action->getRpcResult($id2);
        $dataById3 = $action->getRpcResult($id3);
        $this->assertEquals($dataById1,3); 
        $this->assertEquals($dataById2,3); 
        $this->assertEquals($dataById3,3); 
        $this->assertEquals($action->isSuccess(),true);
    }

    public function testBatchRPCIdRepeatExceptionActionDo()
    {
        $method = 'add';
        $param  = [1,2]; 
        $id1 = "1";
        $id2 = "1";
        $id3 = "1";

        $this->expectException(\SDPMlab\Anser\Exception\ActionException::class);
        $this->expectExceptionMessage("Action http://localhost:8080 已使用 setBatchRpcQuery() ，但傳入ID重複。");
        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action->setBatchRpcQuery([
            [$method, $param,$id1],
            [$method, $param,$id2],
            [$method, $param,$id3]
        ]); 
        $action->do();
    }

    public function testFailHandlerBatchRPCErrorActionDo()
    {
        $method = 'failMethod';
        $param  = [1,2]; 
        $id1 = "1";
        $id2 = "2";
        $id3 = "3";

        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action->setBatchRpcQuery([
            [$method, $param,$id1],
            [$method, $param,$id2],
            [$method, $param,$id3]
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

    public function testFailHandlerBatchRPCErrorActionDoWithGetRpcResponseById()
    {
        $method = 'failMethod';
        $param  = [1,2]; 
        $id1 = "1";
        $id2 = "2";
        $id3 = "3";

        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action->setBatchRpcQuery([
            [$method, $param,$id1],
            [$method, $param,$id2],
            [$method, $param,$id3]
        ]); 
        $action->failHandler(function(ActionException $e) use ($id1,$id2,$id3){
            if($e->isRpcError()){
                $e->getAction()->setMeaningData([
                    "errRpc1" => $e->getErrorRpc($id1),
                    "sucRpc1" => $e->getSuccessRpc($id1),
                    "errRpc2" => $e->getErrorRpc($id2),
                    "sucRpc2" => $e->getSuccessRpc($id2),
                    "errRpc3" => $e->getErrorRpc($id3),
                    "sucRpc3" => $e->getSuccessRpc($id3)
                ]);
                
            }
        });
        
        $this->assertIsCallable($action->getFaileHandler());
        $action->do();
        $data = $action->getMeaningData();
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class,$data["errRpc1"]);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class,$data["errRpc2"]);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class,$data["errRpc3"]);
        $this->assertNull($data["sucRpc1"]);
        $this->assertNull($data["sucRpc2"]);
        $this->assertNull($data["sucRpc3"]);
    }

    public function testFailHandlerBatchRPCSuccessAndErrorActionDo()
    {
        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action->setBatchRpcQuery([
            ["failMethod", [1,2]],
            ["add", [1,2]],
            ["failMethod", [1,2]]
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
            ["failMethod", [1,2]],
            ["add", [1,2]],
            ["failMethod", [1,2]]
        ]); 
        $errRpc = [];
        $sucRpc = [];
        $rpcResponses = [];
        $action->failHandler(function(ActionException $e) use (&$errRpc,&$sucRpc,&$rpcResponses){
            if($e->isRpcError()){
                $rpcResponses = $e->getRpcResponse();
                $errRpc = $e->getErrorRpc();
                $sucRpc = $e->getSuccessRpc();
            }
        });
        
        $this->assertIsCallable($action->getFaileHandler());
        $action->do();
        $this->assertIsArray($rpcResponses);
        $this->assertIsArray($errRpc);
        $this->assertIsArray($sucRpc);
        
        $this->assertEquals(count($rpcResponses["error"]),2);
        $this->assertEquals(count($rpcResponses["success"]),1);
        $this->assertEquals(count($errRpc),2);
        $this->assertEquals(count($sucRpc),1);
        @list($errId1,$errId2) = array_keys($rpcResponses["error"]);
        @list($sucId1) = array_keys($rpcResponses["success"]);
        $this->assertNotNull($errId1);
        $this->assertNotNull($errId2);
        $this->assertNotNull($sucId1);
        $this->assertEquals($errRpc[$errId1]->getId(),$errId1);
        $this->assertEquals($errRpc[$errId1]->getMessage(),"Method not found");
        $this->assertEquals($errRpc[$errId1]->getCode(),-32601);
        $this->assertNull($errRpc[$errId1]->getData());
        $this->assertEquals($errRpc[$errId2]->getId(),$errId2);
        $this->assertEquals($errRpc[$errId2]->getMessage(),"Method not found");
        $this->assertEquals($errRpc[$errId2]->getCode(),-32601);
        $this->assertNull($errRpc[$errId2]->getData());
        $this->assertEquals($sucRpc[$sucId1]->getId(),$sucId1);
        $this->assertEquals($sucRpc[$sucId1]->getValue(),3);
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
            $runtimeAction->setMeaningData([
                "response" => $rpcResponse,
                "rpcResultArr" => $rpcResultArr,
            ]);
        });
        
        $data = $action->do()->getMeaningData();
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ResultResponse::class,$data["response"]["1"]);
        $this->assertEquals($data["rpcResultArr"][$id],3);
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
            $runtimeAction->setMeaningData([
                "response" => $rpcResponse,
                "rpcResultArr" => $rpcResultArr,
            ]);
        });

        $data = $action->do()->getMeaningData();
        @list($id1,$id2) = array_keys($data["response"]);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ResultResponse::class,$data["response"][$id1]);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ResultResponse::class,$data["response"][$id2]);
        $this->assertEquals($data["rpcResultArr"][$id1],3);
        $this->assertEquals($data["rpcResultArr"][$id2],3);
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
