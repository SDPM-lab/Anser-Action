# Anser-Action：A simple API connection library for PHP

<p align="center">
  <img src="https://i.imgur.com/XxTIxD7.png" alt="logo" width="500" />
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

### Parallel Connection

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
