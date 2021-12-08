# Anser-Action：PHP 簡單 API 連線程式庫。

<p align="center">
  <img src="https://i.imgur.com/XxTIxD7.png" alt="logo" width="500" />
</p>

Anser-Action 是一款基於 `Guzzle7` 的程式庫，他能夠讓你以類似於 `jQuery Ajax` 的 `callback` 設計模式處理 HTTP 回應與 HTTP 例外。並且，Anser-Action 也能夠迅速達成並行連線的效果，讓你快速處理多個 HTTP 的連線與回應。

## 安裝

### 需求

1. PHP 7.2.5↑
1. Composer
2. 符合 Guzzle7 所需的 [安裝需求](https://docs.guzzlephp.org/en/stable/overview.html#requirements
)

### Composer 安裝

於專案根目錄下，使用 Composer 下載程式庫與其所需之依賴。

```
composer require sdpmlab/anser-action
```

## 快速開始

### 單個 HTTP 連線

透過 Anser 提供的 `Action` 物件，你可以直觀地定義你的 HTTP 連線。透過 `doneHandler` 的設定，你能夠設定連線成功時的執行邏輯，並透過 `setMeaningData` 將所需的資料暫存在 `Action` 實體中。

`Action` 必須呼叫 `do()` 方法執行連線，隨後即可以透過 `getMeaningData()` 將處理完成的資料取出。

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

### 錯誤處理

你將可以透過設定 `failHandler` 回呼函數，指揮 `Action` 在遇到 HTTP 連線錯誤、伺服器錯誤，以及客戶端錯誤時的處理邏輯。

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

### 並行連線

你可以直接使用程式庫中提供的 `ConcurrentAction` 類別，透過傳入複數 `Action` 實體，將可以快速地進行並行連線，並統一取得處理結果。

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

### 集中管理連線

你可以透過繼承 `\SDPMlab\Anser\Service\SimpleService` 類別來統一管理並抽象化相似的連線，這些連線將可以共享基本組態，透過這種設計模式可以最大程度地降低程式碼的重複，並增加可維護性。

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
