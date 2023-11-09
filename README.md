# Anser-Action：A simple API connection library for PHP

<p align="center">
  <img src="https://i.imgur.com/2vRAcI0.png" alt="logo" width="500" />
</p>

Anser-Action is a library based on `Guzzle7`, it provides you the ability to handle HTTP responses and exceptions like using the `callback` design pattern of `jQuery Ajax`.
Besides, Anser-Action is also able to achieve parallel connection swiftly, enables you to handle multiple HTTP connections and responses in a short time.

## Installation

### Requirements

1. PHP 7.2.5↑
1. Composer
2. [Requirements](https://docs.guzzlephp.org/en/stable/overview.html#requirements) for installing Guzzle7

### Composer installation

Use Composer to download the needed dependencies and libraries under your project root directory.

```
composer require sdpmlab/anser-action
```

## Quick Start

### Single HTTP Connection

Through the `Action` object provided by Anser, you can define your HTTP connection straightforwardly.
By setting up the `doneHandler`, you can design the execution logic when the connection succeeded, and then store the needed data inside `Action` object by means of `setMeaningData`.

`Action` must call the `do()` method to execute connection, subsequently, you can take out the processed data through `getMeaningData()`.

```php
require './vendor/autoload.php';

use \SDPMlab\Anser\Service\Action;
use \Psr\Http\Message\ResponseInterface;

$action = (new Action(
    "https://datacenter.taichung.gov.tw",
    "GET",
    "/swagger/OpenData/4d4847f5-4feb-4e9b-897c-508d2cbe1ed8"
))->doneHandler(function(
    ResponseInterface $response,
    Action $runtimeAction
){
    $body = $response->getBody()->getContents();
    $data = json_decode($body, true);
    $runtimeAction->setMeaningData($data);
});

$data = $action->do()->getMeaningData();
var_dump($data[0]);
```

### Error handling

You can set up `failHandler` callback function to define the processing logic when encountering HTTP connection errors, server errors, or the client errors.

```php
<?php
require './vendor/autoload.php';

use \SDPMlab\Anser\Service\Action;
use \Psr\Http\Message\ResponseInterface;
use \SDPMlab\Anser\Exception\ActionException;

$action = (new Action(
    "https://error.endpoint",
    "GET",
    "/dfgdfg"
))->doneHandler(function (
    ResponseInterface $response,
    Action $runtimeAction
) {
    $body = $response->getBody()->getContents();
    $data = json_decode($body, true);
    $runtimeAction->setMeaningData($data);
})->failHandler(function (
    ActionException $e
) {
    if($e->isClientError()){
        $e->getAction()->setMeaningData([
            "code" => $e->getStatusCode(),
            "msg" => "client error"
        ]);
    }else if ($e->isServerError()){
        $e->getAction()->setMeaningData([
            "code" => $e->getStatusCode(),
            "msg" => "server error"
        ]);
    }else if($e->isConnectError()){
        $e->getAction()->setMeaningData([
            "msg" => $e->getMessage()
        ]);
    }
});

$data = $action->do()->getMeaningData();
var_dump($data);
```

### Concurrent Connection

You can directly use the `ConcurrentAction` class provided by the library.
By passing multiple `Action` entities, you will be able to achieve parallel connection fastly, and get the processed results uniformly.

```php
<?php
require './vendor/autoload.php';

use \SDPMlab\Anser\Service\Action;
use \Psr\Http\Message\ResponseInterface;
use \SDPMlab\Anser\Service\ConcurrentAction;

$action1 = (new Action(
    "https://datacenter.taichung.gov.tw",
    "GET",
    "/swagger/OpenData/4d4847f5-4feb-4e9b-897c-508d2cbe1ed8"
))->addOption("query",[
    "limit" => "1"
])->doneHandler(function(
    ResponseInterface $response,
    Action $runtimeAction
){
    $body = $response->getBody()->getContents();
    $data = json_decode($body, true);
    $runtimeAction->setMeaningData($data);
});

$action2 = (new Action(
    "https://datacenter.taichung.gov.tw",
    "GET",
    "/swagger/OpenData/bec13df0-4648-41e9-838d-132705a45308"
))->addOption("query",[
    "limit" => "1"
])->doneHandler(function(
    ResponseInterface $response,
    Action $runtimeAction
){
    $body = $response->getBody()->getContents();
    $data = json_decode($body, true);
    $runtimeAction->setMeaningData($data);
});

$action3 = (new Action(
    "https://datacenter.taichung.gov.tw",
    "GET",
    "/swagger/OpenData/b81b1fc6-a2f0-406a-a78c-654cc0088782"
))->addOption("query",[
    "limit" => "1"
])->doneHandler(function(
    ResponseInterface $response,
    Action $runtimeAction
){
    $body = $response->getBody()->getContents();
    $data = json_decode($body, true);
    $runtimeAction->setMeaningData($data);
});

$concurrent = new ConcurrentAction();
$concurrent->setActions([
    "action1" => $action1,
    "action2" => $action2,
    "action3" => $action3
])->send();
var_dump($concurrent->getActionsMeaningData());
```

### Centralized Connection Management

Inherit `\SDPMlab\Anser\Service\SimpleService` class to manage and make similar connections abstract.
These connections will be able to share basic configurations with each other, and through this design pattern, you can reduce code replication maximally and enhance maintainability as well.

```php
<?php
require './vendor/autoload.php';

use \SDPMlab\Anser\Service\Action;
use \Psr\Http\Message\ResponseInterface;
use \SDPMlab\Anser\Service\SimpleService;

class TaichungService extends SimpleService
{

    protected $serviceName = "https://datacenter.taichung.gov.tw";
    protected $retry = 1;
    protected $retryDelay = 1.0;
    protected $timeout = 2.0;
    protected $options = [
        "query" => [
            "limit" => "1"
        ]
    ];

    public function getAction1(): Action
    {
        return $this->getAction("GET", "/swagger/OpenData/4d4847f5-4feb-4e9b-897c-508d2cbe1ed8")
            ->doneHandler($this->sameDoneHandler());
    }

    public function getAction2(): Action
    {
        return $this->getAction("GET", "/swagger/OpenData/bec13df0-4648-41e9-838d-132705a45308")
            ->doneHandler($this->sameDoneHandler());
    }

    public function getAction3(): Action
    {
        return $this->getAction("GET", "/swagger/OpenData/b81b1fc6-a2f0-406a-a78c-654cc0088782")
            ->doneHandler($this->sameDoneHandler());
    }

    protected function sameDoneHandler(): callable
    {
        return function (
            ResponseInterface $response,
            Action $runtimeAction
        ) {
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);
            $runtimeAction->setMeaningData($data);
        };
    }
}

$taichungService = new TaichungService();
var_dump($taichungService->getAction1()->do()->getMeaningData());
var_dump($taichungService->getAction2()->do()->getMeaningData());
var_dump($taichungService->getAction2()->do()->getMeaningData());
```

### HTTP JSON RPC Single Request

In the `Action` object, you can pass the desired `method`, `params`, and `id` for the `RPC` connection by using the `setRpcQuery()` method.

Once the `setRpcQuery()` method is enabled, the HTTP verb of the `Action` will automatically change to `POST`.

By setting the `doneHandler`, you can define the execution logic when the connection is successful, and you can temporarily store the required data in the `Action` instance using `setMeaningData`.

If the `RPC` response is parsed correctly, you can handle your `RPC` response using the `doneHandler`.

You can use `getRpcResponse()` to obtain the successful RPC response entity, `getRpcResult()` to get the RPC response data (i.e., result), and `getId()` to retrieve the RPC response ID. All of these methods return their results as arrays.[Reference to JSON-RPC format](https://www.jsonrpc.org/specification "Reference to JSON-RPC format")。

```php
require './vendor/autoload.php';
use SDPMlab\Anser\Service\Action;
use Psr\Http\Message\ResponseInterface;
use SDPMlab\Anser\Service\ServiceList;

$id = 1;
$action = (new Action(
    'http://myRpcServer.com',
    "POST",
    "/"
))
->setRpcQuery("/myRpcMethod", [1,2], $id)
->doneHandler(static function(
    ResponseInterface $response,
    Action $runtimeAction
) {
    $rpcResponse = $action->getRpcResponse();
    $result = $action->getRpcResult()[0];
    $id     = $action->getId()[0];
    $runtimeAction->setMeaningData([
        "id" => $id,
        "result" => $result
    ]);
});

$data = $action->do()->getMeaningData();
var_dump($data);
```

### HTTP JSON RPC Batch request

Similar to the JSON RPC single request approach, you can use the `setBatchRpcQuery()` method in the `Action` object to pass the desired `method`, `params`, and `id` as an array to perform batch RPC connections.

Once the `setBatchRpcQuery()` method is enabled, the HTTP verb of the `Action` will automatically change to `POST`.

If all batch `RPC` responses are successfully parsed, you can handle your `RPC` responses using the `doneHandler`.

```php
require './vendor/autoload.php';
use SDPMlab\Anser\Service\Action;
use Psr\Http\Message\ResponseInterface;
use SDPMlab\Anser\Service\ServiceList;

$id = 1;
$action = (new Action(
    'http://myRpcServer.com',
    "POST",
    "/"
))
->setBatchRpcQuery([
    ["/myRpcMethod", [1,2], $id],
    ["/myRpcMethod", [1,2], $id],
    ["/myRpcMethod", [1,2], $id]
]); 
->doneHandler(static function(
    ResponseInterface $response,
    Action $runtimeAction
) {
    $returnData = [];
    $rpcResponse = $action->getRpcResponse();
    $resultArray = $action->getRpcResult();
    $idArray     = $action->getId();
    for ($i = 0; $i<3; $i++ ){
        $returnData[] = [
            "id" => $idArray[$i],
            "result" => $resultArray[$i],
        ]
    }

    $runtimeAction->setMeaningData($returnData);
});

$data = $action->do()->getMeaningData();
var_dump($data);
```

### HTTP JSON RPC Error Handling - RPC Response Error

You can configure a `failHandler` callback function to instruct the `Action` on how to handle the logic when encountering RPC errors, such as using `isRpcError()`,RPC error format ,[reference to JSON-RPC format](https://www.jsonrpc.org/ "Reference to JSON-RPC format")。

You can use `getRpcResponse()` to obtain all RPC responses, and the `Action` has categorized their response statuses into `success` and `error`.

If you only need to retrieve `RPC` responses of a specific status, you can use `getSuccessRpc()` and `getErrorRpc()`, which return arrays containing these responses. Additionally, you can use the data retrieval methods shown in the provided code to handle the data more precisely.

```php
<?php
require './vendor/autoload.php';

use \SDPMlab\Anser\Service\Action;
use \Psr\Http\Message\ResponseInterface;
use \SDPMlab\Anser\Exception\ActionException;

$action = (new Action(
    "http://myRpcServer.com",
    "POST",
    "/"
))
->setRpcQuery("/errorMethod", [1,2], $id)
->doneHandler(function (
    ResponseInterface $response,
    Action $runtimeAction
) {
    $rpcResponse = $action->getRpcResponse();
    $result = $action->getRpcResult()[0];
    $id     = $action->getId()[0];
    $runtimeAction->setMeaningData([
        "id" => $id,
        "result" => $result
    ]);
})->failHandler(function (
    ActionException $e
) {
    if ($e->isRpcError()) {
        $rpcResponse = $e->getRpcResponse()
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
            "result" => $result,
        ]);
    }
});

$data = $action->do()->getMeaningData();
var_dump($data);
```

### HTTP JSON RPC Error Handling - HTTP Response Error

You can use `getRpcByResponse()` to retrieve all RPC responses, and the return value will be an array containing the RPC response entities.

If you only need to obtain RPC responses of a specific status, you can use `getSuccessRpcByResponse()` and `getErrorRpcByResponse()`, which will return arrays containing these responses. Additionally, you can use the data retrieval methods shown in the provided code to handle the data more precisely.

```php
<?php
require './vendor/autoload.php';

use \SDPMlab\Anser\Service\Action;
use \Psr\Http\Message\ResponseInterface;
use \SDPMlab\Anser\Exception\ActionException;

$action = (new Action(
    "http://myRpcServer.com",
    "POST",
    "/"
))
->setBatchRpcQuery("/myRpcMethod", [1,2], $id)
->doneHandler(function (
    ResponseInterface $response,
    Action $runtimeAction
) {
    $rpcResponse = $action->getRpcResponse();
    $result = $action->getRpcResult()[0];
    $id     = $action->getId()[0];
    $runtimeAction->setMeaningData([
        "id" => $id,
        "result" => $result
    ]);
})->failHandler(function (
    ActionException $e
) {
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
});

$data = $action->do()->getMeaningData();
var_dump($data);
```
