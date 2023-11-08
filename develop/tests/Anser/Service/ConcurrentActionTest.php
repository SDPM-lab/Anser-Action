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
            "rpc1" => 3,
            "rpc2" => 3
        ];

        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action->setRpcQuery($method, $param,$id); 
        $action->doneHandler(static function(
            ResponseInterface $response,
            Action $runtimeAction
        ) {
            $body = ServiceList::getRpcClient()->decode($response->getBody())[0]->getValue();
            $runtimeAction->setMeaningData($body);
        });

        $action2 = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action2->setRpcQuery($method, $param,$id); 
        $action2->doneHandler(static function(
            ResponseInterface $response,
            Action $runtimeAction
        ) {
            $body = ServiceList::getRpcClient()->decode($response->getBody())[0]->getValue();
            $runtimeAction->setMeaningData($body);
        });

        $this->concurrent->setActions([
            "rpc1" => $action,
            "rpc2" => $action2,
        ]);

        $this->concurrent->send();
        $data = $this->concurrent->getActionsMeaningData();
        $this->assertEquals($data, $assertData);
    }

    public function testRpcMethodNotFoundException()
    {
        $method = 'notExistMethod';
        $param  = [1,2]; 
        $id     = 1;

        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action->setRpcQuery($method, $param,$id); 
        $action1 = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action1->setRpcQuery($method, $param,$id); 
    
        $this->concurrent->setActions([
            "rpc1" => $action,
            "rpc12" => $action1,
        ]);
        try {
            $this->concurrent->send();
        } catch (\SDPMlab\Anser\Exception\ActionException $th) {
            $this->assertNotNull($th->getResponse());
            $this->assertNotNull($th->getRpcResponse());
            $this->assertInstanceOf(ResponseInterface::class, $th->getResponse());
            $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class, $th->getRpcResponse());
            $this->assertEquals($th->getRpcCode(), -32601);
            $this->assertEquals($th->getRpcMsg(),"Method not found");
            $errorAction = $th->getAction();
            $this->assertEquals($errorAction->isSuccess(), false);
        }
    }

    public function testRpcInvalidParamsException()
    {
        $method = 'add';
        $param  = []; 
        $id     = 1;

        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action->setRpcQuery($method, $param,$id); 
        $action1 = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action1->setRpcQuery($method, $param,$id); 
    
        $this->concurrent->setActions([
            "rpc1" => $action,
            "rpc12" => $action1,
        ]);
        try {
            $this->concurrent->send();
        } catch (\SDPMlab\Anser\Exception\ActionException $th) {
            $this->assertNotNull($th->getResponse());
            $this->assertNotNull($th->getRpcResponse());
            $this->assertInstanceOf(ResponseInterface::class, $th->getResponse());
            $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class, $th->getRpcResponse());
            $this->assertEquals($th->getRpcCode(), -32602);
            $this->assertEquals($th->getRpcMsg(),"Invalid params");
            $errorAction = $th->getAction();
            $this->assertEquals($errorAction->isSuccess(), false);
        }
    }

    public function testRpcInvalidRequestException()
    {
        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $closure = function () use ($action) {
            $action->rpcRequest = '[1,2,3]';
        };
        $binding = $closure->bindTo($action , get_class($action));
        $binding();

        $action1 = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $closure = function () use ($action1) {
            $action1->rpcRequest = '[1,2,3]';
        };
        $binding = $closure->bindTo($action1 , get_class($action1));
        $binding();
    
        $this->concurrent->setActions([
            "rpc1" => $action,
            "rpc12" => $action1,
        ]);
        try {
            $this->concurrent->send();
        } catch (\SDPMlab\Anser\Exception\ActionException $th) {
            $this->assertNotNull($th->getResponse());
            $this->assertNotNull($th->getRpcResponse());
            $this->assertInstanceOf(ResponseInterface::class, $th->getResponse());
            $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class, $th->getRpcResponse());
            $this->assertEquals($th->getRpcCode(), -32600);
            $this->assertEquals($th->getRpcMsg(),"Invalid Request");
            $errorAction = $th->getAction();
            $this->assertEquals($errorAction->isSuccess(), false);
        }
    }

    public function testRpcParseErrorException()
    {
        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $closure = function () use ($action) {
            $action->rpcRequest = '"{"jsonrpc":"2.0","method":"add","params":[1,}"';
        };
        $binding = $closure->bindTo($action , get_class($action));
        $binding();


        $action1 = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $closure = function () use ($action1) {
            $action1->rpcRequest = '"{"jsonrpc":"2.0","method":"add","params":[1,}"';
        };
        $binding = $closure->bindTo($action , get_class($action1));
        $binding();

    
        $this->concurrent->setActions([
            "rpc1" => $action,
            "rpc12" => $action1,
        ]);
        try {
            $this->concurrent->send();
        } catch (\SDPMlab\Anser\Exception\ActionException $th) {
            $this->assertNotNull($th->getResponse());
            $this->assertNotNull($th->getRpcResponse());
            $this->assertInstanceOf(ResponseInterface::class, $th->getResponse());
            $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class, $th->getRpcResponse());
            $this->assertEquals($th->getRpcCode(), -32700);
            $this->assertEquals($th->getRpcMsg(),"Parse error");
            $errorAction = $th->getAction();
            $this->assertEquals($errorAction->isSuccess(), false);
        }
    }

    public function testRpcServerErrorException()
    {
        $method = 'implementationError';
        $param  = [1,2]; 
        $id     = 1;

        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action->setRpcQuery($method, $param,$id); 
        $action1 = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action1->setRpcQuery($method, $param,$id); 
    
        $this->concurrent->setActions([
            "rpc1" => $action,
            "rpc12" => $action1,
        ]);
        try {
            $this->concurrent->send();
        } catch (\SDPMlab\Anser\Exception\ActionException $th) {
            $this->assertNotNull($th->getResponse());
            $this->assertNotNull($th->getRpcResponse());
            $this->assertInstanceOf(ResponseInterface::class, $th->getResponse());
            $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class, $th->getRpcResponse());
            $this->assertEquals($th->getRpcCode(), -32099);
            $this->assertEquals($th->getRpcMsg(),"Server error");
            $errorAction = $th->getAction();
            $this->assertEquals($errorAction->isSuccess(), false);
        }
    }

    public function testRpcInternalErrorException()
    {
        $method = 'InternalError';
        $param  = [1,2]; 
        $id     = 1;

        $action = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action->setRpcQuery($method, $param,$id); 
        $action1 = new Action("http://localhost:8080", "POST", "/api/v1/rpcServer");
        $action1->setRpcQuery($method, $param,$id); 
    
        $this->concurrent->setActions([
            "rpc1" => $action,
            "rpc12" => $action1,
        ]);
        try {
            $this->concurrent->send();
        } catch (\SDPMlab\Anser\Exception\ActionException $th) {
            $this->assertNotNull($th->getResponse());
            $this->assertNotNull($th->getRpcResponse());
            $this->assertInstanceOf(ResponseInterface::class, $th->getResponse());
            $this->assertInstanceOf(\Datto\JsonRpc\Responses\ErrorResponse::class, $th->getRpcResponse());
            $this->assertEquals($th->getRpcCode(), -32603);
            $this->assertEquals($th->getRpcMsg(),"Internal error");
            $errorAction = $th->getAction();
            $this->assertEquals($errorAction->isSuccess(), false);
        }
    }
}
