<?php

namespace SDPMlab\Anser\Exception;

use psr\Http\Message\RequestInterface;
use psr\Http\Message\ResponseInterface;
use SDPMlab\Anser\Exception\AnserException;
use SDPMlab\Anser\Service\ActionInterface;
use SDPMlab\Anser\Service\RequestSettings;

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
     * 初始化　ActionException
     *
     * @param string $message 錯誤訊息
     * @param ResponseInterface|null $response Psr-7 response 物件（如果有）
     */
    public function __construct(string $message, ?ResponseInterface $response = null, ?RequestInterface $request = null, ?ActionInterface $action = null)
    {
        parent::__construct($message);
        $this->response = $response;
        $this->request = $request;
        $this->action = $action;
    }

    public static function forServiceAction5XXError(
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

    public static function forServiceAction4XXError(
        string $serviceName,
        RequestSettings $requestSettings,
        ResponseInterface $response,
        RequestInterface $request,
        ActionInterface $action,
        ?string $alias = null
    ): ActionException {
        $msg = "Action {$serviceName} 在地址 {$requestSettings->url} 以 {$requestSettings->method} 方法呼叫 {$requestSettings->path} 時發生 HTTP {$response->getStatusCode()} 異常。";
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
        ?string $alias = null
    ): ActionException {
        $msg = "Action {$serviceName} 在地址 {$requestSettings->url} 以 {$requestSettings->method} 方法呼叫 {$requestSettings->path} 時發生伺服器連線異常。";
        if ($alias) {
            $msg = "{$alias}-" . $msg;
        }

        return new self(
            $msg,
            null,
            $request,
            $action
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

}
