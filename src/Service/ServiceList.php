<?php

namespace SDPMlab\Anser\Service;

use SDPMlab\Anser\Service\ServiceSettings;

class ServiceList
{

    /**
     * 本地服務清單
     *
     * @var array
     */
    protected static $localServiceList = [];
    
    /**
     * Guzzle7 HTTP Client 實體
     *
     * @var \GuzzleHttp\Client
     */
    protected static $client;

    /**
     * 設定 Local Service List
     *
     * @return void
     */
    public static function setLocalServices(array $services): void
    {
        foreach ($services as $service) {
            static::$localServiceList[$service['name']] = new ServiceSettings(
                $service['name'],
                $service['address'],
                $service['port'],
                $service['isHttps'] ?? false
            );
        }
    }

    /**
     * 新增一筆 Service 資料至 service list
     *
     * @param string $name 服務名稱
     * @param string $address 服務地址
     * @param integer $port 服務埠號
     * @param boolean $isHttps 是否為 Https 連線
     * @return void
     */
    public static function addLocalService(string $name, string $address, int $port, bool $isHttps): void
    {
        static::$localServiceList[$name] = new ServiceSettings(
            $name,
            $address,
            $port,
            $isHttps ?? false
        );
    }

    /**
     * 取得本地服務清單陣列
     *
     * @return array
     */
    public static function getServiceList(): array
    {
        return static::$localServiceList;
    }

    /**
     * 取得單一服務設定
     *
     * @param string $serviceName 服務名稱
     * @return ServiceSettings|null
     */
    public static function getServiceData(string $serviceName): ?ServiceSettings
    {
        //如果 Service Name 是 URL
        if (filter_var($serviceName, FILTER_VALIDATE_URL) !== false) {
            $parseUrl = parse_url($serviceName);
            if(isset($parseUrl["port"])){
                $port = (int)$parseUrl["port"];
            }else{
                $port = $parseUrl["scheme"] === "https" ? 443 : 80;
            }
            return new \SDPMlab\Anser\Service\ServiceSettings(
                $parseUrl["host"],
                $parseUrl["host"],
                $port,
                $parseUrl["scheme"] === "https"
            );
        }
        
        //如果 Service Name 已被全域紀錄
        if (isset(static::$localServiceList[$serviceName])) {
            return static::$localServiceList[$serviceName];
        } else {
            return null;
        }
    }

    /**
     * 清空服務清單陣列
     *
     * @return void
     */
    public static function cleanServiceList(): void
    {
        static::$localServiceList = [];
    }

    /**
     * 移除服務清單中的某個服務設定
     *
     * @param string $serviceName 服務名稱
     * @return void
     */
    public static function removeService(string $serviceName): void
    {
        if (isset(static::$localServiceList[$serviceName])) {
            unset(static::$localServiceList[$serviceName]);
        }
    }

    /**
     * 回傳共享服務實體
     *
     * @return \GuzzleHttp\Client
     */
    public static function getHttpClient(): \GuzzleHttp\Client
    {
        if(!static::$client instanceof \GuzzleHttp\Client){
            static::$client = new \GuzzleHttp\Client();
        }
        return static::$client;
    }
}
