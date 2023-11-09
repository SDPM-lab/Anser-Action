<?php

namespace SDPMlab\Anser\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use Psr\Http\Message\ResponseInterface;
use SDPMlab\Anser\Exception\ActionException;
use SDPMlab\Anser\Exception\AnserException;
use SDPMlab\Anser\Service\ActionFilter;
use SDPMlab\Anser\Service\ActionInterface;
use SDPMlab\Anser\Service\RequestSettings;
use SDPMlab\Anser\Service\ServiceList;

class Action implements ActionInterface
{
    /**
     * 請求方法
     *
     * @var string
     */
    protected $method;

    /**
     * 實際服務地址
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * 資源位置
     *
     * @var string
     */
    protected $path;

    /**
     * 服務名稱
     *
     * @var string
     */
    protected $serviceName;

    /**
     * 請求參數，定義與 Guzzle7 相符
     *
     * @var array
     */
    protected $requestOption = [];

    /**
     * filter 管理器
     *
     * @var array
     */
    protected $filters = [
        "before" => [],
        "after" => [],
    ];

    /**
     * Action 完成時的處理器
     *
     * @var callable(\SDPMlab\Anser\Service\ActionInterface):mixed|null
     */
    protected $doneHandler = null;

    /**
     * meaning data 內容
     *
     * @var mixed|null
     */
    protected $meaningData;

    /**
     * 發生 HTTP 錯誤之處理程序
     *
     * @var callable|null
     */
    protected $failHandler = null;

    /**
     * Acction 執行是否成功
     *
     * @var boolean
     */
    protected $isSuccess = false;

    /**
     * Acction 執行次數
     *
     * @var boolean
     */
    protected $numOfAction = 0;

    /**
     * 重試次數
     *
     * @var integer
     */
    protected $retry = 0;

    /**
     * 重試間隔（秒）
     *
     * @var float
     */
    protected $retryDelay = 0.2;

    /**
     * 最長等待響應時間(秒)
     *
     * @var float
     */
    protected $timeout = 2.0;

    /**
     * Action 執行完畢後的響應結果
     *
     * @var \Psr\Http\Message\ResponseInterface
     */
    protected $response;

    /**
     * Action 所執行的 Request 實體
     *
     * @var \Psr\Http\Message\RequestInterface
     */
    protected $request;

    /**
     * Guzzle7 HTTP 實體
     *
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * JSONRPC 請求
     *
     * @var string|null
     */
    protected $rpcRequest = null;

    /**
     * 是否啟用JSONRPC請求
     *
     * @var boolean
     */
    protected $isSetRpcRequest = false;

    /**
     * JSONRPC 響應
     *
     * @var array
     */
    protected $rpcResponses = null;

    /**
     * RPC Client
     *
     * @var \Datto\JsonRpc\Client
     */
    protected $rpcClient = null;

    public function __construct(string $serviceName, string $method, string $path)
    {
        $this->serviceName = $serviceName;
        $this->method = $method;
        $this->path = $path;
        $this->rpcClient = new \Datto\JsonRpc\Client();

        if (is_null(ServiceList::getServiceData($this->serviceName))) {
            throw ActionException::forServiceDataNotFound($this->serviceName);
        }

        $this->baseUrl = ServiceList::getServiceData($this->serviceName)->getBaseUrl();
        $this->client = ServiceList::getHttpClient();
    }

    /**
     * 序列化儲存之成員變數。
     *
     * @return array
     */
    public function __sleep()
    {
        return [
            'method',
            'baseUrl',
            'path',
            'serviceName',
            'requestOption',
            'filters',
            'doneHandler',
            'meaningData',
            'failHandler',
            'isSuccess',
            'numOfAction',
            'retry',
            'retryDelay',
            'timeout',
            'request'
        ];
    }

    /**
     * 反序列化後，針對 client 重新賦值。
     */
    public function __wakeup()
    {
        $this->client = ServiceList::getHttpClient();
    }

    /**
     * 執行 Action 所定義的內容（同步執行）
     *
     * @return \SDPMlab\Anser\Service\ActionInterface
     */
    function do(): ActionInterface
    {
        //執行前濾器
        $this->useFilters(true);

        try {
            //至少執行一次，若有設定重試次數將會重試。
            while ($this->numOfAction <= $this->retry) {
                $this->addNumOfActionDo();
                try {
                    //如果 numOfAction 大於 1 就代表是 retry
                    $response = $this->sendRequest($this->numOfAction > 1);
                    // 將原本的setActionResponse放至verifyResponse呼叫
                    $this->verifyResponse($response);
                    break;
                } catch (\Exception $th) {
                    if ($this->numOfAction > $this->retry) {
                        throw $th;
                    }
                    continue;
                }
            }
        } catch (\GuzzleHttp\Exception\TransferException $th){
            $this->processFailHandler($th);
        } catch (ActionException $th) {
            $this->processFailHandlerForRpc($th);
        }

        //執行後濾器
        $this->useFilters(false);

        //判斷是否需要過濾意義資料
        if ($this->isSuccess) $this->useDoneHandler();

        return $this;
    }

    /**
     * 執行非同步 Request
     *
     * @param string $alias action 別稱
     * @param boolean $isRetry
     * @return PromiseInterface
     */
    public function doAsync(string $alias, bool $isRetry = false): PromiseInterface
    {
        $runtimeAction = $this;

        //執行前濾器
        $this->useFilters(true);
        $options = $this->getFinallyRequestOption($isRetry);
        $promise = $this->client->requestAsync(
            $this->method,
            $this->baseUrl . $this->getRequestPath(),
            $options
        )->then(
            function (ResponseInterface $res) use (&$runtimeAction, $alias) {
                $runtimeAction->addNumOfActionDo();
                
                try {
                    // 將原本的setActionResponse放至verifyResponse呼叫
                    $runtimeAction->verifyResponse($res);
                } catch (ActionException $th) {
                    return $runtimeAction->processFailHandlerForRpc($th ,$alias);
                }
                //執行後濾器
                $runtimeAction->useFilters(false);
                //判斷是否需要過濾意義資料
                $runtimeAction->useDoneHandler();
            },
            function ($th) use (&$runtimeAction, $alias) {
                //判斷是否需要重試
                $runtimeAction->addNumOfActionDo();
                $retrySetting = $runtimeAction->getRetrySetting();
                $nowNum = $runtimeAction->getNumnerOfDoAction();
                if ($nowNum <= $retrySetting[0]) {
                    $promise = $runtimeAction->doAsync($alias, true);
                    Utils::unwrap([$promise]);
                }

                //錯誤處理
                if ($th instanceof \GuzzleHttp\Exception\TransferException) {
                    $runtimeAction->processFailHandler($th, $alias);
                }
            }
        );
        return $promise;
    }

    /**
     * 執行 Request 並獲得回傳值。
     *
     * @param boolean $isRetry
     * @return ResponseInterface
     */
    protected function sendRequest(bool $isRetry = false): ResponseInterface
    {
        $options = $this->getFinallyRequestOption($isRetry);
        $response = $this->client->request(
            $this->method,
            $this->baseUrl . $this->getRequestPath(),
            $options
        );
        return $response;
    }

    /**
     * 取得正確規格的 Path
     *
     * @param string $path
     * @return string
     */
    protected function getRequestPath():string
    {
        if (substr($this->path, 0, 1) === '/') {
            $path = substr($this->path, 1);
        } else {
            $path = $this->path;
        }
        return $path;
    }

    /**
     * 設定 Action 內的 Response 成員。
     *
     * @param ResponseInterface|null $response 傳入響應實體，若無則可傳入 null
     * @param boolean $isSuccess 請求是否成功
     * @return void
     */
    public function setActionResponse(?ResponseInterface $response, bool $isSuccess)
    {
        $this->response = $response;
        $this->isSuccess = $isSuccess;
    }

    /**
     * 設定執行 Action 若遇到 Http 400~500 錯誤以及連線錯誤時的處理程序。
     * 若未設定這個選項，將會在執行失敗時拋出錯誤。
     *
     * @param callable(\SDPMlab\Anser\Exception\ActionException):void $handler
     * @return ActionInterface
     */
    public function failHandler(callable $handler): ActionInterface
    {
        $this->failHandler = $handler;
        return $this;
    }

    /**
     * 取得錯誤處理程序 callable。
     *
     * @return callable|null 若無設定則回傳 null
     */
    public function getFaileHandler(): ?callable
    {
        return $this->failHandler;
    }

    /**
     * 處理伺服器回傳例外
     *
     * @param \GuzzleHttp\Exception\TransferException $th
     * @param string|null $alias
     * @return void
     */
    public function processFailHandler(\GuzzleHttp\Exception\TransferException $th, ?string $alias = null)
    {
        if ($th instanceof \GuzzleHttp\Exception\ConnectException) {
            $this->setActionResponse(null, false);
            $exception = ActionException::forServiceActionConnectError($this->serviceName, $this->getRequestSetting(), $th->getRequest(), $this, $alias, $th->getMessage(),$this->rpcResponses);
            if (is_callable($this->failHandler)) {
                call_user_func($this->failHandler, $exception);
            } else {
                throw $exception;
            }    
        }else if($th instanceof \GuzzleHttp\Exception\BadResponseException){
            $this->setActionResponse($th->getResponse(), false);
            $exception = ActionException::forServiceActionFailError($this->serviceName, $this->getRequestSetting(), $th->getResponse(), $th->getRequest(), $this, $alias, $this->rpcResponses);
            if (is_callable($this->failHandler)) {
                $this->response = $th->getResponse();
                call_user_func($this->failHandler, $exception);
            } else {
                throw $exception;
            }    
        }
    }

    public function processFailHandlerForRpc(ActionException $th,?string $alias = null)
    {
        $this->setActionResponse($this->response, false);
        $exception = ActionException::forRpcResponseError($this->serviceName, $this->getRequestSetting(), $this, $alias, $this->rpcResponses);
        if (is_callable($this->failHandler)) {
            call_user_func($this->failHandler, $exception);
        } else {
            throw $exception;
        }    
    }

    /**
     * 設定重試規則。
     *
     * @param integer $retry 重試最大次數，預設為 0 (不重試)
     * @param float $retryDelay 重試間隔秒數，預設為 0.2
     * @return ActionInterface
     */
    public function setRetry(int $retry, float $retryDelay = 0.2): ActionInterface
    {
        if ($retry < 0) {
            throw ActionException::forRetryNumber($this->serviceName);
        }

        if ($retryDelay < 0.0) {
            throw ActionException::forRetryDelayFloat($this->serviceName);
        }

        $this->retry = $retry;
        $this->retryDelay = $retryDelay;
        return $this;
    }

    /**
     * 取得 Retry 相關設定
     *
     * @return array<integer,float> 重試次數與等待下次重試時間
     */
    public function getRetrySetting(): array
    {
        return [$this->retry, $this->retryDelay];
    }

    /**
     * 設定最長請求時間
     *
     * @param float $timeout 時間不可低於 0.0 秒
     * @return ActionInterface
     */
    public function setTimeout(float $timeout): ActionInterface
    {
        if ($timeout < 0.0) {
            throw ActionException::forTimeFloat($this->serviceName);
        }

        $this->timeout = $timeout;
        return $this;
    }

    /**
     * 回傳 action 請求是否執行成功（Http 2XX）
     *
     * @return boolean
     */
    public function isSuccess(): bool
    {
        return $this->isSuccess;
    }

    /**
     * 通常，你不會使用到這個方法。isSuccess 的判斷是在 Action-Do 之後自動執行的。
     * 若是你所溝通的端點不論是成功或失敗都會回傳 Http status code 200。
     * 那麼你就需要透過這個方法自行切換 Action 的最終狀態。
     * 
     * @param boolean $isSuccess True 為執行成功 False 則為失敗
     * @return ActionInterface
     */
    public function setSuccess(bool $isSuccess): ActionInterface
    {
        $this->isSuccess = $isSuccess;
        return $this;
    }

    /**
     * 回傳 action 總執行次數（不論成功與否）。
     *
     * @return integer
     */
    public function getNumnerOfDoAction(): int
    {
        return $this->numOfAction;
    }

    /**
     * Action 實際連執行數遞增 1
     *
     * @return void
     */
    public function addNumOfActionDo()
    {
        $this->numOfAction++;
    }

    /**
     * 取得 Request 設定物件
     *
     * @return RequestSettings
     */
    public function getRequestSetting(): RequestSettings
    {
        return new RequestSettings(
            $this->method,
            $this->baseUrl,
            $this->path,
            $this->getFinallyRequestOption()
        );
    }

    /**
     * 取得 Request 設定陣列
     *
     * @param boolean $isRetry 預設為 false，若傳入 true ，則會新增 delay 時間
     * @return array
     */
    protected function getFinallyRequestOption(bool $isRetry = false): array
    {
        $finallyOptions = $this->requestOption;
        $finallyOptions["timeout"] = $this->timeout;
        if ($isRetry) {
            $finallyOptions["delay"] = ($this->retryDelay * 1000);
        }

        // 如果rpc請求存在，則加入body
        if ($this->isSetRpcRequest && is_null($this->getRpcRequest())) {
            $this->rpcRequest = $this->rpcClient->encode();
            $finallyOptions["body"] = $this->rpcRequest;
            $this->method = "POST";
        }
        
        return $finallyOptions;
    }

    /**
     * 取得 Action 執行後的 Response 內容
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * 取得 GuzzleHttp Client 實體
     *
     * @return ClientInterface
     */
    public function getHttpClient(): ClientInterface
    {
        return $this->client;
    }

    /**
     * 定義 Action 完成時的處理器。
     * 所傳入的處理器將會在 Action 請求成功(status 2XX)且執行完後濾器後自動執行。
     *
     * @param callable(\Psr\Http\Message\ResponseInterface ,\SDPMlab\Anser\Service\ActionInterface):mixed $handler
     * @return ActionInterface
     */
    public function doneHandler(callable $handler): ActionInterface
    {
        $this->doneHandler = $handler;
        return $this;
    }

    /**
     * 使 Meaning Data 處理器生效。
     *
     * @return void
     */
    public function useDoneHandler()
    {
        if (is_callable($this->doneHandler)) {
            call_user_func($this->doneHandler, $this->response, $this);
        }
    }

    /**
     * 將傳入的變數設為 action 的 meaning data。
     *
     * @param mixed $meaningData
     * @return ActionInterface
     */
    public function setMeaningData($meaningData): ActionInterface
    {
        $this->meaningData = $meaningData;
        return $this;
    }

    /**
     * 若有定義 Meaning Data Handler 或是透過 setMeaningData 方法宣告 Meaning Data，則會回傳 Meaning Data 變數。
     *
     * @return mixed|null 回傳定義的 Meaning Data 變數或是 Null
     */
    public function getMeaningData()
    {
        return $this->meaningData;
    }

    /**
     * 使用 Action 中定義的過濾器
     *
     * @return void
     */
    public function useFilters(bool $isBefore)
    {
        ActionFilter::useGlobalFilter($this, $isBefore);
        $type = $isBefore ? "before" : "after";
        foreach ($this->filters[$type] as $className) {
            if ($isBefore) {
                ActionFilter::useBeforeFilter($className, $this);
            } else {
                ActionFilter::useAfterFilter($className, $this);
            }
        }
    }

    /**
     * 設定 Action 所需的過濾器
     *
     * @return ActionInterface
     */
    public function setFilter(string $className): ActionInterface
    {
        $this->addAfterFilter($className);
        $this->addBeforeFilter($className);
        return $this;
    }

    /**
     * 設定 Action 所使用的前濾器
     *
     * @param string $className
     * @return ActionInterface
     */
    public function addAfterFilter(string $className): ActionInterface
    {
        if (!in_array($className, $this->filters["after"])) {
            $this->filters["after"][] = $className;
        }
        return $this;
    }

    /**
     * 設定 Action 所使用的後濾器
     *
     * @param string $className
     * @return ActionInterface
     */
    public function addBeforeFilter(string $className): ActionInterface
    {
        if (!in_array($className, $this->filters["before"])) {
            $this->filters["before"][] = $className;
        }
        return $this;
    }

    /**
     * 設定 Action 執行請求時所使用的設定，其規則與 Guzzle7 的 Option 一致。
     * 使用此方法將會覆蓋目前所有的 Options 設定。
     *
     * @return ActionInterface
     */
    public function setOptions(array $options): ActionInterface
    {
        $this->requestOption = $options;
        return $this;
    }

    /**
     * 新增一筆 Option 設定，其規則與 Guzzle7 的 Option 一致。
     * 若已有同名之設定，將會覆蓋過去。
     *
     * @param string $optionName 選項名稱
     * @param mixed $value 設定值
     * @return ActionInterface
     */
    public function addOption(string $optionName, $value): ActionInterface
    {
        $this->requestOption[$optionName] = $value;
        return $this;
    }

    /**
     * 刪除目前存在的 Option。
     *
     * @param string $optionName
     * @return ActionInterface
     */
    public function removeOption(string $optionName): ActionInterface
    {
        if (isset($this->requestOption[$optionName])) {
            unset($this->requestOption[$optionName]);
        }
        return $this;
    }

    /**
     * 將會回傳目前 Action 被設定的 Options 陣列。
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->requestOption;
    }

    /**
     * 將會回傳單個 Option 的設定值。
     *
     * @param string $optionName Option 名稱
     * @return mixed 設定值
     */
    public function getOption(string $optionName)
    {
        if (isset($this->requestOption[$optionName])) {
            return $this->requestOption[$optionName];
        } else {
            return null;
        }
    }

    /**
     * 設定JSON RPC 呼叫參數
     *
     * @param string|null $method
     * @param array $arguments
     * @param string|null $id
     * @return ActionInterface
     */
    public function setRpcQuery(string $method = null, array $arguments = [], ?string $id = null): ActionInterface
    {
        // the rpc unique id
        if (is_null($id)) {
            $id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
          );
        }
        $this->rpcClient->query($id, $method, $arguments);
        $this->isSetRpcRequest = true;

        return $this;
    }

    /**
     * 設定JSON RPC Batch呼叫參數
     * 陣列規範 
     * [
     *   [method,[arguments],id],
     *   [method,[arguments],id],
     * ]
     *
     * @param array $batchRpcQuery
     * 
     * @return ActionInterface
     */
    public function setBatchRpcQuery(array $batchRpcQuery): ActionInterface
    {
        if (count($batchRpcQuery) == 0 | empty($batchRpcQuery)) {
            throw  ActionException::forSetBatchRpcQueryBtDataNotExist($this->serviceName);
        }

        if (count($batchRpcQuery) == 1) {
            $batchRpcQuery[0][2] = $batchRpcQuery[0][2] ?? null;
            $this->setRpcQuery($batchRpcQuery[0][0],$batchRpcQuery[0][1], $batchRpcQuery[0][2]);
        }else {
            foreach ($batchRpcQuery as $rpcQuery) {
                $rpcQuery[2] = $rpcQuery[2] ?? null;
                $this->setRpcQuery($rpcQuery[0], $rpcQuery[1], $rpcQuery[2]);
            }
        }
        $this->isSetRpcRequest = true;
        return $this;
    }

    /**
     * 取得當前RPC請求json
     *
     * @return ?string
     */
    public function getRpcRequest(): ?string
    {
        return $this->rpcRequest;
    }

    /**
     * 判斷Response是否為RPC Response，並解析是否有錯誤
     *
     * @param ResponseInterface $response
     */
    public function verifyResponse(ResponseInterface $response)
    {
        if (!is_null($this->getRpcRequest())) {
            $rpcResults = $this->rpcClient->decode($response->getBody());
            if (!is_array($rpcResults)) {
                $this->setActionResponse($response,false);
                throw  ActionException::forRpcInvalidResponse($this->serviceName);
            }

            foreach ($rpcResults as $rpcResponse) {
                if ($rpcResponse instanceof \Datto\JsonRpc\Responses\ResultResponse) {
                    $this->rpcResponses["success"][] = $rpcResponse;
                } elseif ($rpcResponse instanceof \Datto\JsonRpc\Responses\ErrorResponse) {
                    $this->rpcResponses["error"][] = $rpcResponse;
                }
            }
            if (!empty($this->rpcResponses["error"])) {
                $this->setActionResponse($response,false);
                throw ActionException::forRpcResponseErrorType($this->serviceName);
            }

        }
        // 無使用RPC的請求或成功的RPC請求則從此執行
        $this->setActionResponse($response,true);
        return ;
    }

    /**
     * 取得RPC響應實體
     * 只回傳success的RPC響應
     *
     * @return array<\Datto\JsonRpc\Responses\Response>|null
     */
    public function getRpcResponse(): ?array
    {
        return $this->rpcResponses["success"] ?? null;
    }

    /**
     * 取得RPC Result
     *
     * @return array<mixed>|null
     */
    public function getRpcResult(): ?array
    {
        if (!is_null($this->getRpcResponse())) {
            $result = [];
            foreach ($this->getRpcResponse() as $rpcRequest) {
                $result[] =  $rpcRequest->getValue();
            }
            return $result;
        }
        return null;
    }

    /**
     * 取得RPC id
     *
     * @return array<string>|null
     */
    public function getRpcId(): ?array
    {
        if (!is_null($this->getRpcResponse())) {
            $result = [];
            foreach ($this->getRpcResponse() as $rpcResponse) {
                $result[] =  $rpcResponse->getId();
            }
            return $result;
        }
        return null;
    }
}
