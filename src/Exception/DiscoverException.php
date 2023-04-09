<?php

namespace SDPMlab\Anser\Exception;

use SDPMlab\Anser\Exception\AnserException;

class DiscoverException extends AnserException
{
    /**
     * 初始化　SimpleServiceException
     *
     * @param string $message 錯誤訊息
     */
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function NotHealthService($serviceName, $healthStatus): DiscoverException
    {
        return new self("服務名稱 : ".$serviceName." 目前非健康狀態，請確認服務是否可用，當前檢查狀態為 ".$healthStatus." 。");
    }

    public static function NotExistService($serviceName): DiscoverException
    {
        return new self("服務名稱 : ".$serviceName." 不存在，請於Consul Service確認服務狀態。");
    }

    public static function connectException(): DiscoverException
    {
        return new self("連線設定有誤，請確認ServiceList::setServiceDiscovery 方法設定是否正確。");
    }

    public static function settingException(): DiscoverException
    {
        return new self("discoverMode參數設定錯誤，如需設定服務發現服務，請設定discoverMode參數為 'default' 或 'fabio'。");
    }
}
