<?php

namespace SDPMlab\Anser\Exception;

use psr\Http\Message\RequestInterface;
use psr\Http\Message\ResponseInterface;
use SDPMlab\Anser\Exception\AnserException;
use SDPMlab\Anser\Service\ActionInterface;
use SDPMlab\Anser\Service\RequestSettings;

use function PHPUnit\Framework\isNull;

class ActionException extends AnserException
{

    /**
     * psr 響應實體
     *
     * @var \psr\Http\Message\ResponseInterface
     */
    protected $response;

    /**
     * psr 請求實體
     *
     * @var \psr\Http\Message\RequestInterface
     */
    protected $request;

    /**
     * action 實體
     *
     * @var \SDPMlab\Anser\Service\ActionInterface
     */
    protected $action;

    /**
     * 是否是連線錯誤
     *
     * @var boolean
     */
    protected $isConnectError = false;

    /**
     * 是否是RPC錯誤
     *
     * @var boolean
     */
    protected $isRpcError = false;

    /**
     * RPC Response
     *
     * @var array
     */
    protected $rpcResponses;

    /**
     * 初始化　ActionException
     *
     * @param string $message 錯誤訊息
     * @param ResponseInterface|null $response Psr-7 response 物件（如果有）
     */
    public function __construct(string $message, ?ResponseInterface $response = null, ?RequestInterface $request = null, ?ActionInterface $action = null, $isConnectError = false ,$isRpcError = false, ?array $rpcResponses = null)
    {
        parent::__construct($message);
        $this->response = $response;
        $this->request = $request;
        $this->action = $action;
        $this->isConnectError = $isConnectError;
        $this->isRpcError = $isRpcError;
        $this->rpcResponses = $rpcResponses;
    }

    public static function forServiceActionFailError(
        string $serviceName,
        RequestSettings $requestSettings,
        ResponseInterface $response,
        RequestInterface $request,
        ActionInterface $action,
        ?string $alias = null
    ): ActionException {
        $msg = "Action {$serviceName} 在地址 {$requestSettings->url} 以 {$requestSettings->method} 方法呼叫 {$requestSettings->path} 發生 HTTP  {$response->getStatusCode()}  異常。";
        if ($alias) {
            $msg = "{$alias}-" . $msg;
        }

        return new self(
            $msg,
            $response,
            $request,
            $action
        );
    }

    public static function forServiceActionConnectError(
        string $serviceName,
        RequestSettings $requestSettings,
        RequestInterface $request,
        ActionInterface $action,
        ?string $alias = null,
        string $guzzleMsg = ""
    ): ActionException {
        $msg = "Action {$serviceName} 在地址 {$requestSettings->url} 以 {$requestSettings->method} 方法呼叫 {$requestSettings->path} 時發生伺服器連線異常。Guzzle7: {$guzzleMsg}";
        if ($alias) {
            $msg = "{$alias}-" . $msg;
        }

        return new self(
            $msg,
            null,
            $request,
            $action,
            true
        );
    }

    public static function forRpcResponseError(
        string $serviceName,
        RequestSettings $requestSettings,
        ActionInterface $action,
        ?string $alias = null,
        $rpcResponses
    ): ActionException {
        $msg = "Action {$serviceName} 在地址 {$requestSettings->url} 以 {$requestSettings->method} 方法呼叫 {$requestSettings->path} 時發生錯誤。JSON-RPC 響應錯誤";
        if ($alias) {
            $msg = "{$alias}-" . $msg;
        }

        return new self(
            $msg,
            $action->getResponse(),
            null,
            $action,
            false,
            true,
            $rpcResponses
        );
    }

    public static function forRetryNumber(string $serviceName): ActionException
    {
        return new self("Action {$serviceName} Retry次數必須大於 0 。");
    }

    public static function forRetryDelayFloat(string $serviceName): ActionException
    {
        return new self("Action {$serviceName} Retry Delay 秒數必須大於 0.0 。");
    }

    public static function forTimeFloat(string $serviceName): ActionException
    {
        return new self("Action {$serviceName} Time Out 秒數必須大於 0.0 。");
    }

    public static function forServiceDataNotFound(string $serviceName): ActionException
    {
        return new self("尚未定義 {$serviceName} 服務進服務列表內，請檢查服務的 serviceName 是否正確。");
    }

    public static function forServiceDataCallbackTypeError(string $serviceName): ActionException
    {
        return new self("Action {$serviceName} 定義的回呼函數回傳型別錯誤，請檢查回傳型別是否為 \SDPMlab\Anser\Service\ServiceSettings 或 null。");
    }

    public static function forSetBatchRpcQueryBtDataNotExist(string $serviceName): ActionException
    {
        return new self("Action {$serviceName} 已使用 setBatchRpcQuery() ，但未傳入任何資料於陣列中。");
    }

    public static function forRpcInvalidResponse(string $serviceName): ActionException
    {
        return new self("Action {$serviceName} RPC Response 格式錯誤。");
    }


    public static function forRpcResponseErrorType(string $serviceName): ActionException
    {
        return new self("Action {$serviceName} RPC Response 包含錯誤 Response。");
    }

    /**
     * 取得發生錯誤的 Restponse 實體
     *
     * @return ResponseInterface|null
     */
    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    /**
     * 取得發生錯誤的 Request 實體
     *
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * 取得發生錯誤的 Action 實體
     *
     * @return ActionInterface
     */
    public function getAction(): ActionInterface
    {
        return $this->action;
    }

    /**
     * 回傳 HTTP 狀態碼
     *
     * @return integer
     */
    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    /**
     * 回傳解構後RPC Response 
     * 內部包裹success與error case的RPC Response物件
     *
     * @return array
     */
    public function getRpcResponse(): array
    {
        return $this->rpcResponses;
    }

    public function getSuccessRpc()
    {
        return $this->getRpcResponse()["success"] ?? null;
    }

    public function getErrorRpc()
    {
        return $this->getRpcResponse()["error"] ?? null;
    }

    /**
     * 回傳解構後RPC Response 
     * 內部為RPC Response 實體
     * @param string|null $responseBody
     * @return array|null
     */
    public function getRpcByResponse(): ?array
    {
        if (!is_null($this->getResponse())) {
            return \SDPMlab\Anser\Service\ServiceList::getRpcClient()->decode($this->getResponse()->getBody());
        }
        return null;
    }

    /**
     * 回傳解構後RPC Response 
     * 內部success RPC Response
     * @param string|null $responseBody
     * @return array|null
     */
    public function getSuccessRpcByResponse(): ?array
    {
        if (!is_null(!is_null($this->getResponse()))) {
            $rpcResponses = [];
            $rpcNativeResponses = $this->getRpcByResponse();
            foreach ($rpcNativeResponses as $rpcResponse) {
                if ($rpcResponse instanceof \Datto\JsonRpc\Responses\ResultResponse) {
                    $rpcResponses[] = $rpcResponse;
                }
            }
            return $rpcResponses;
        }
        return null;
    }

    /**
     * 回傳解構後RPC Response 
     * 內部error RPC Response
     * @param string|null $responseBody
     * @return array|null
     */
    public function getErrorRpcByResponse(): ?array
    {
        if (!is_null(!is_null($this->getResponse()))) {
            $rpcResponses = [];
            $rpcNativeResponses = $this->getRpcByResponse();
            foreach ($rpcNativeResponses as $rpcResponse) {
                if ($rpcResponse instanceof \Datto\JsonRpc\Responses\ErrorResponse) {
                    $rpcResponses[] = $rpcResponse;
                }
            }
            return $rpcResponses;
        }
        return null;
    }


    /**
     * 是否為 Client Error (Client code 4XX)
     *
     * @return boolean
     */
    public function isClientError(): bool
    {
        if(is_null($this->response)){
            return false;
        }
        $statusCode = $this->response->getStatusCode();
        return $statusCode >= 400 && $statusCode < 500;
    }

    /**
     * 是否為 Server Error (Status code 5XX)
     *
     * @return boolean
     */
    public function isServerError(): bool
    {
        if(is_null($this->response)){
            return false;
        }
        $statusCode = $this->response->getStatusCode();
        return $statusCode >= 500;
    }

    /**
     * 判斷是否為伺服器連線錯誤
     *
     * @return boolean
     */
    public function isConnectError(): bool
    {
        return $this->isConnectError;
    }

    public function isRpcError(): bool
    {
        return $this->isRpcError;
    }
}
