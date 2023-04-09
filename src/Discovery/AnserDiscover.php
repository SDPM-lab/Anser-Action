<?php

namespace SDPMlab\Anser\Discovery;

use DCarbone\PHPConsulAPI\Consul;
use GuzzleHttp\Client;
use DCarbone\PHPConsulAPI\Config;
use SDPMlab\Anser\Exception\DiscoverException;
use SDPMlab\Anser\Service\ServiceSettings;
use SDPMlab\Anser\Discovery\DiscoverInterface;

class AnserDiscover
{
    /**
    * consul 實體
    *
    * @var Consul
    */
    protected static Consul $consul;

    /**
     * GuzzleHttp 實體
     *
     * @var Client
     */
    protected static Client $client;

    /**
     * consul 設定
     *
     * @var array
     */
    protected static array $cousulConfig;

    /**
     * 服務發現列表
     *
     * @var array
     */
    public static $discoveryList = [];

    /**
     * 初始登記的服務列表
     *
     * @var string
     */
    public static $prototypeServices;

    public function __construct(
        \GuzzleHttp\Client $httpClient,
        array $consulConfig,
    ) {
        self::$cousulConfig = $consulConfig;
        self::$client = $httpClient;
        self::$consul = new Consul(new Config(self::$cousulConfig));

        return $this;
    }

    /**
     * 取得服務列表
     *
     * @return array
     */
    public static function getDiscoverServiceList(): array
    {
        return self::$discoveryList;
    }

    /**
     * 取得單一服務
     *
     * @param string $serviceName
     * @return object
     */
    public static function getDiscoverService(string $serviceName): ?ServiceSettings
    {
        if (!array_key_exists($serviceName, self::$discoveryList)) {
            throw DiscoverException::NotExistService($serviceName);
        }

        return self::$discoveryList[$serviceName];
    }

    /**
     * 更新服務列表
     *
     * @return void
     */
    public static function updateDiscoverServicesList(): void
    {

    }

    /**
     * 清空服務列表
     *
     * @return void
     */
    public static function clearDiscoverServicesList(): void
    {
        self::$discoveryList = [];
    }
}
