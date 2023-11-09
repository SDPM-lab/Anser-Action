<?php

namespace SDPMlab\Anser\Service;

use CodeIgniter\Test\CIUnitTestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SDPMlab\Anser\Service\Action;
use SDPMlab\Anser\Service\ConcurrentAction;
use SDPMlab\Anser\Service\ServiceList;

class ConcurrentActionTest extends CIUnitTestCase
{

    public $testService1 = [
        "name" => "testService1",
        "address" => "localhost",
        "port" => 8080,
        "isHttps" => false,
    ];

    public $testService2 = [
        "name" => "testService2",
        "address" => "localhost",
        "port" => 8081,
        "isHttps" => false,
    ];

    public $testService3 = [
        "name" => "testService3",
        "address" => "erroraddress",
        "port" => 8081,
        "isHttps" => false,
    ];

    /**
     * @var \SDPMlab\Anser\Service\ConcurrentAction
     */
    public $concurrent;

    protected function setUp(): void
    {
        parent::setUp();
        ServiceList::cleanServiceList();
        ServiceList::setLocalServices([
            $this->testService1, $this->testService2, $this->testService3,
        ]);
        $this->concurrent = new ConcurrentAction();
    }

    public function testConcurrentAction()
    {
        $orderID = 25;
        $action = new Action("testService1", "GET", "/api/v1/order/{$orderID}");
        $action2 = new Action("testService2", "GET", "/api/v1/payment/{$orderID}");
        $this->concurrent->setActions([
            "order" => $action,
            "payment" => $action2,
        ]);
        $benchmark = \CodeIgniter\Config\Services::timer();
        $benchmark->start('concurrent');
        $this->concurrent->send();
        $benchmark->stop('concurrent');
        $this->concurrentTime = $benchmark->getElapsedTime("concurrent");
        $actionResponse = $this->concurrent->getAction("payment")->getResponse();
        $action2Response = $this->concurrent->getAction("order")->getResponse();
        $this->assertInstanceOf(ResponseInterface::class, $actionResponse);
        $this->assertInstanceOf(ResponseInterface::class, $action2Response);

        $syncAction = new Action("testService1", "GET", "/api/v1/order/{$orderID}");
        $syncAction2 = new Action("testService2", "GET", "/api/v1/payment/{$orderID}");
        $benchmark->start('sync');
        $syncAction->do();
        $syncAction2->do();
        $benchmark->stop('sync');
        $syncTime = $benchmark->getElapsedTime("sync");
        $this->assertLessThan($syncTime, $this->concurrentTime);
    }

    public function testMeaningDataActionDo()
    {
        $orderID = 25;
        $action = new Action("testService1", "GET", "/api/v1/order/{$orderID}");
        $action2 = new Action("testService2", "GET", "/api/v1/payment/{$orderID}");
        $meaningDataHandler = function(
            \Psr\Http\Message\ResponseInterface $response,
            ActionInterface $runtimeAction
        ){
            $data = json_decode($response->getBody()->getContents(), true)["data"];
            $runtimeAction->setMeaningData($data);
        };
        $action->doneHandler($meaningDataHandler);
        $action2->doneHandler($meaningDataHandler);
        $this->concurrent->setActions([
            "order" => $action,
            "payment" => $action2,
        ])->send();
        $this->assertArrayHasKey("products_id", $action->getMeaningData());
        $this->assertArrayHasKey("handling_fee", $action2->getMeaningData());
    }

    public function testRetryActionDo()
    {
        $orderID = 25;
        $action = new Action("testService1", "GET", "/api/v1/order/{$orderID}");
        $action2 = new Action("testService2", "GET", "/api/v1/payment/{$orderID}");
        $action3 = new Action("testService1", "GET", "/api/v1/fail");
        $action3->setRetry(1, 0.3);
        $this->concurrent->setActions([
            "order" => $action,
            "payment" => $action2,
            "4XXfail" => $action3,
        ]);
        try {
            $this->concurrent->send();
        } catch (\SDPMlab\Anser\Exception\ActionException $th) {
            $this->assertInstanceOf(ResponseInterface::class, $th->getResponse());
            $this->assertInstanceOf(RequestInterface::class, $th->getRequest());
            $errorAction = $th->getAction();
            $numOfDoAction = $errorAction->getNumnerOfDoAction();
            $errorCode = $errorAction->getResponse()->getStatusCode();
            $this->assertEquals($numOfDoAction, 2);
            $this->assertEquals($errorAction->isSuccess(), false);
            $this->assertEquals($errorCode, 429);
        }
    }

    public function testServerErrorException()
    {
        $orderID = 25;
        $action = new Action("testService1", "GET", "/api/v1/order/{$orderID}");
        $action2 = new Action("testService2", "GET", "/api/v1/payment/{$orderID}");
        $action3 = new Action("testService1", "GET", "/api/v1/fail/1");
        $this->concurrent->setActions([
            "order" => $action,
            "payment" => $action2,
            "5XXfail" => $action3,
        ]);
        try {
            $this->concurrent->send();
        } catch (\SDPMlab\Anser\Exception\ActionException $th) {
            $this->assertInstanceOf(ResponseInterface::class, $th->getResponse());
            $this->assertInstanceOf(RequestInterface::class, $th->getRequest());
            $errorAction = $th->getAction();
            $numOfDoAction = $errorAction->getNumnerOfDoAction();
            $errorCode = $errorAction->getResponse()->getStatusCode();
            $this->assertEquals($numOfDoAction, 1);
            $this->assertEquals($errorAction->isSuccess(), false);
            $this->assertEquals($errorCode, 500);
        }
    }

    public function testConnecttErrorException()
    {
        $orderID = 25;
        $action = new Action("testService1", "GET", "/api/v1/order/{$orderID}");
        $action2 = new Action("testService2", "GET", "/api/v1/payment/{$orderID}");
        $action3 = new Action("testService3", "GET", "/api/v1/connect_error");
        $this->concurrent->setActions([
            "order" => $action,
            "payment" => $action2,
            "connectError" => $action3,
        ]);
        try {
            $this->concurrent->send();
        } catch (\SDPMlab\Anser\Exception\ActionException $th) {
            $this->assertNull($th->getResponse());
            $this->assertInstanceOf(RequestInterface::class, $th->getRequest());
            $errorAction = $th->getAction();
            $numOfDoAction = $errorAction->getNumnerOfDoAction();
            $this->assertEquals($numOfDoAction, 1);
            $this->assertEquals($errorAction->isSuccess(), false);
        }
    }

    public function testRpcActionDo()
    {
        $method = 'add';
        $param  = [1,2]; 
        $id     = 1;

        $assertData = [
            "rpc1" => [3,"1"],
            "rpc2" => [3,"1"]
        ];

        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action->setRpcQuery($method, $param,$id); 
        $action->doneHandler(static function(
            ResponseInterface $response,
            Action $runtimeAction
        ) {
            $idArr = $runtimeAction->getRpcId();
            $resultArr = $runtimeAction->getRpcResult(); 
            $res = array_merge($resultArr, $idArr);
            $runtimeAction->setMeaningData($res);
        });

        $action2 = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action2->setRpcQuery($method, $param,$id); 
        $action2->doneHandler(static function(
            ResponseInterface $response,
            Action $runtimeAction
        ) {
            $idArr = $runtimeAction->getRpcId();
            $resultArr = $runtimeAction->getRpcResult(); 
            $res = array_merge($resultArr, $idArr);
            $runtimeAction->setMeaningData($res);
        });

        $this->concurrent->setActions([
            "rpc1" => $action,
            "rpc2" => $action2,
        ]);

        $this->concurrent->send();
        $data = $this->concurrent->getActionsMeaningData();
        $this->assertEquals($data, $assertData);
    }

    public function testRpcErrorException()
    {
        $method = 'notExistMethod';
        $param  = [1,2]; 
        $id     = 1;

        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action->setRpcQuery($method, $param,$id); 
        $action->setTimeout(5);
        $action1 = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action1->setRpcQuery($method, $param,$id); 
        $action1->setTimeout(5);
        $meaningDataHandler = function(\SDPMlab\Anser\Exception\ActionException $e){
            if($e->isRpcError()){
                $errRpc = $e->getErrorRpc();
                $errorMsg = $errRpc[0]->getMessage();
                $e->getAction()->setMeaningData([
                    "res" => $e->getRpcResponse(),
                    "msg" => $errorMsg
                ]);
            }
        };

        $action->failHandler($meaningDataHandler);
        $action1->failHandler($meaningDataHandler);

        $this->concurrent->setActions([
            "rpc1" => $action,
            "rpc12" => $action1,
        ]);
        $this->concurrent->send();
        $this->assertEquals($action->isSuccess(), false);
        $this->assertEquals($action1->isSuccess(), false);
        $this->assertNotNull($action->getMeaningData()["res"]["error"]);
        $this->assertNotNull($action1->getMeaningData()["res"]["error"]);
        $this->assertArrayNotHasKey("success",$action1->getMeaningData()["res"]);
        $this->assertArrayNotHasKey("success",$action->getMeaningData()["res"]);
        $this->assertEquals($action->getMeaningData()["msg"],"Method not found");
        $this->assertEquals($action1->getMeaningData()["msg"],"Method not found");
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class, $action->getMeaningData()["res"]["error"][0]);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class, $action1->getMeaningData()["res"]["error"][0]);
    }

    // undo
    public function testRpcBatchActionDo()
    {
        $method = 'add';
        $param  = [1,2]; 
        $id     = 1;

        $assertData = [
            "rpc1" => [3,3,"1","1"],
            "rpc2" => [3,3,"1","1"]
        ];

        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action->setBatchRpcQuery([
            [$method, $param,$id],
            [$method, $param,$id],
        ]); 
        $action->setTimeout(7);
        $action->doneHandler(static function(
            ResponseInterface $response,
            Action $runtimeAction
        ) {
            $idArr = $runtimeAction->getRpcId();
            $resultArr = $runtimeAction->getRpcResult(); 
            $res = array_merge($resultArr, $idArr);
            $runtimeAction->setMeaningData($res);
        });

        $action2 = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action2->setBatchRpcQuery([
            [$method, $param,$id],
            [$method, $param,$id],
        ]); 
        $action2->setTimeout(7);
        $action2->doneHandler(static function(
            ResponseInterface $response,
            Action $runtimeAction
        ) {
            $idArr = $runtimeAction->getRpcId();
            $resultArr = $runtimeAction->getRpcResult(); 
            $res = array_merge($resultArr, $idArr);
            $runtimeAction->setMeaningData($res);
        });

        $this->concurrent->setActions([
            "rpc1" => $action,
            "rpc2" => $action2,
        ]);

        $this->concurrent->send();
        $data = $this->concurrent->getActionsMeaningData();
        $this->assertEquals($data, $assertData);
    }

    public function testBatchRpcErrorException()
    {
        $method = 'notExistMethod';
        $param  = [1,2]; 
        $id     = 1;

        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action->setBatchRpcQuery([
            [$method, $param,$id],
            [$method, $param,$id],
        ]); 
        $action->setTimeout(5);
        $action1 = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action1->setBatchRpcQuery([
            [$method, $param,$id],
            [$method, $param,$id],
        ]); 
        $action1->setTimeout(5);
        $meaningDataHandler = function(\SDPMlab\Anser\Exception\ActionException $e){
            if($e->isRpcError()){
                $errRpc = $e->getErrorRpc();
                $errorMsg = $errRpc[0]->getMessage();
                $e->getAction()->setMeaningData([
                    "res" => $e->getRpcResponse(),
                    "msg" => $errorMsg
                ]);
            }
        };

        $action->failHandler($meaningDataHandler);
        $action1->failHandler($meaningDataHandler);

        $this->concurrent->setActions([
            "rpc1" => $action,
            "rpc12" => $action1,
        ]);
        $this->concurrent->send();
        $this->assertEquals($action->isSuccess(), false);
        $this->assertEquals($action1->isSuccess(), false);
        $this->assertNotNull($action->getMeaningData()["res"]["error"]);
        $this->assertNotNull($action1->getMeaningData()["res"]["error"]);
        $this->assertArrayNotHasKey("success",$action1->getMeaningData()["res"]);
        $this->assertArrayNotHasKey("success",$action->getMeaningData()["res"]);
        $this->assertEquals($action->getMeaningData()["msg"],"Method not found");
        $this->assertEquals($action1->getMeaningData()["msg"],"Method not found");
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class, $action->getMeaningData()["res"]["error"][0]);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class, $action->getMeaningData()["res"]["error"][1]);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class, $action1->getMeaningData()["res"]["error"][0]);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class, $action1->getMeaningData()["res"]["error"][1]);
    }

    public function testBatchRpcSuccessAndErrorException()
    {
        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action->setBatchRpcQuery([
            ["failMethod", [1,2],"1"],
            ["add", [1,2],"1"],
        ]); 
        $action->setTimeout(5);
        $action1 = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action1->setBatchRpcQuery([
            ["failMethod", [1,2],"1"],
            ["add", [1,2],"1"],
        ]); 
        $action1->setTimeout(5);
        $meaningDataHandler = function(\SDPMlab\Anser\Exception\ActionException $e){
            if($e->isRpcError()){
                $errRpc = $e->getErrorRpc();
                $sucRpc = $e->getSuccessRpc();
                $e->getAction()->setMeaningData([
                    "res" => $e->getRpcResponse(),
                    "err" => $errRpc,
                    "suc" => $sucRpc
                ]);
            }
        };

        $action->failHandler($meaningDataHandler);
        $action1->failHandler($meaningDataHandler);

        $this->concurrent->setActions([
            "rpc1" => $action,
            "rpc12" => $action1,
        ]);
        $this->concurrent->send();
        $this->assertEquals($action->isSuccess(), false);
        $this->assertEquals($action1->isSuccess(), false);
        $this->assertArrayHasKey("success",$action->getMeaningData()["res"]);
        $this->assertArrayHasKey("error",$action->getMeaningData()["res"]);
        $this->assertArrayHasKey("success",$action1->getMeaningData()["res"]);
        $this->assertArrayHasKey("error",$action1->getMeaningData()["res"]);
        $this->assertNotNull($action->getMeaningData()["res"]["error"]);
        $this->assertNotNull($action1->getMeaningData()["res"]["error"]);
        $this->assertNotNull($action->getMeaningData()["res"]["success"]);
        $this->assertNotNull($action1->getMeaningData()["res"]["success"]);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class, $action->getMeaningData()["res"]["error"][0]);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ResultResponse::class, $action->getMeaningData()["res"]["success"][0]);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class, $action1->getMeaningData()["res"]["error"][0]);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ResultResponse::class, $action1->getMeaningData()["res"]["success"][0]);
        $this->assertEquals($action->getMeaningData()["err"][0]->getMessage(),"Method not found");
        $this->assertEquals($action1->getMeaningData()["err"][0]->getMessage(),"Method not found");
    }

    public function testFailHandlerBatchRpcQueryDoActionWith4XXError()
    {
        $action = new Action("http://localhost:8080", "POST", "/api/v1/error429RpcServer");
        $action->setBatchRpcQuery([
            ["failMethod", [1,2],"1"],
            ["add", [1,2],"1"],
        ]); 
        $action1 = new Action("http://localhost:8080", "POST", "/api/v1/error429RpcServer");
        $action1->setBatchRpcQuery([
            ["failMethod", [1,2],"1"],
            ["add", [1,2],"1"],
        ]); 
        $meaningDataHandler = function(\SDPMlab\Anser\Exception\ActionException $e){
            if($e->isClientError()){
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
        };

        $action->failHandler($meaningDataHandler);
        $action1->failHandler($meaningDataHandler);

        $this->concurrent->setActions([
            "rpc1" => $action,
            "rpc12" => $action1,
        ]);
        $this->concurrent->send();
        $this->assertEquals($action->isSuccess(), false);
        $this->assertEquals($action1->isSuccess(), false);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class,$action->getMeaningData()["response"][0]);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ResultResponse::class,$action->getMeaningData()["response"][1]);
        $this->assertNotNull($action->getMeaningData()["success"]["id"]);
        $this->assertEquals($action->getMeaningData()["success"]["result"],3);
        $this->assertNotNull($action->getMeaningData()["error"]["id"]);
        $this->assertEquals($action->getMeaningData()["error"]["msg"],"Method not found");
        $this->assertEquals($action->getMeaningData()["error"]["code"],-32601);
        $this->assertNull($action->getMeaningData()["error"]["data"]);

        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class,$action1->getMeaningData()["response"][0]);
        $this->assertInstanceOf(\Datto\JsonRpc\Responses\ResultResponse::class,$action1->getMeaningData()["response"][1]);
        $this->assertNotNull($action1->getMeaningData()["success"]["id"]);
        $this->assertEquals($action1->getMeaningData()["success"]["result"],3);
        $this->assertNotNull($action1->getMeaningData()["error"]["id"]);
        $this->assertEquals($action1->getMeaningData()["error"]["msg"],"Method not found");
        $this->assertEquals($action1->getMeaningData()["error"]["code"],-32601);
        $this->assertNull($action1->getMeaningData()["error"]["data"]);
    }
}
