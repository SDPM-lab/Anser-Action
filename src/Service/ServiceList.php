<?php

namespace SDPMlab\Anser\Service;

use SDPMlab\Anser\Service\ServiceSettings;
use GuzzleHttp\HandlerStack;
use SDPMlab\Anser\Exception\ActionException;
class ServiceList
{

    /**
     * Local Service List
     *
     * @var array
     */
    protected static $localServiceList = [];

    /**
     * Global HandlerStack callback
     *
     * @var null|callable
     */
    protected static $globalHandlerCallback = null;
    
    /**
     * ServiceList Update callback From Anser-Gateway 
     *
     * @var null|callable
     */
    protected static $serviceDataHandlerCallback = null;

    /**
     * Guzzle7 HTTP Client Instance
     *
     * @var \GuzzleHttp\Client
     */
    protected static $client;

    /**
     * Set local service list
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
     * Guzzel will use this HandlerStack to handle the Request.
     *
     * @param HandlerStack $handlerStack
     * @return void
     */
    public static function setGlobalHandlerStack(callable $handler)
    {
        static::$globalHandlerCallback = $handler;
    }

    /**
     * Action will use this Handler to handle the Service Data.
     *
     * @param callable $handler
     * @return void
     */
    public static function setServiceDataHandler(callable $handler)
    {
        static::$serviceDataHandlerCallback = $handler;
    }

    /**
     * Add a new service to local service list.
     *
     * @param string $name Service Name
     * @param string $address Service Address
     * @param integer $port Service Port
     * @param boolean $isHttps Is HTTPS or not
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
     * Get local service list
     *
     * @return array
     */
    public static function getServiceList(): array
    {
        return static::$localServiceList;
    }

    /**
     * get single service data
     *
     * @param string $serviceName 服務名稱
     * @return ServiceSettings|null
     */
    public static function getServiceData(string $serviceName): ?ServiceSettings
    {
        if(static::$serviceDataHandlerCallback === null){
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
            
            if (isset(static::$localServiceList[$serviceName])) {
                return static::$localServiceList[$serviceName];
            } else {
                return null;
            }
        } else {
            $callableResult = call_user_func(static::$serviceDataHandlerCallback, $serviceName);
            if (!$callableResult instanceof ServiceSettings) {
                throw ActionException::forServiceDataCallbackTypeError($serviceName);
            }
            return $callableResult; 
        }
    }

    /**
     * Clear the list of local services
     *
     * @return void
     */
    public static function cleanServiceList(): void
    {
        static::$localServiceList = [];
    }

    /**
     * Remove a service from local service list
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
     * Get Guzzle7 HTTP Client shared instance
     *
     * @return \GuzzleHttp\Client
     */
    public static function getHttpClient(): \GuzzleHttp\Client
    {
        if(!static::$client instanceof \GuzzleHttp\Client){
            if(static::$globalHandlerCallback === null){
                static::$client = new \GuzzleHttp\Client();
            } else {
                $stack = HandlerStack::create(self::$globalHandlerCallback); // Wrap w/ middleware
                static::$client = new \GuzzleHttp\Client(['handler' => $stack]);
            }
        }
        return static::$client;
    }
}
