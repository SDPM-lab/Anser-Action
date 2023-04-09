<?php

namespace SDPMlab\Anser\Discovery;

use GuzzleHttp\Client;
use DCarbone\PHPConsulAPI\Config;
use SDPMlab\Anser\Exception\DiscoverException;
use SDPMlab\Anser\Service\ServiceSettings;
use SDPMlab\Anser\Discovery\DiscoverInterface;

class FabioDiscover implements DiscoverInterface
{
    /**
     * fabio 設定
     *
     * @var array<string,string>
     */
    protected static array $fabioConfig;

    /**
     * GuzzleHttp 實體
     *
     * @var Client
     */
    protected static Client $client;

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
        array $fabioConfig
    ) {

        self::$fabioConfig = $fabioConfig;
        self::$client = $httpClient;

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
        $fabioApiHost   = self::$fabioConfig["fabioRouteService"];
        $fabioProxyHost = self::$fabioConfig["fabioProxyService"];

        $response = self::$client->get($fabioApiHost.'/api/routes');

        if ($response !== self::$prototypeServices) {

            self::$prototypeServices = $response;

            $services = json_decode($response->getBody()->getContents());

            foreach ($services as $service) {
                self::$discoveryList[$service->service] = new ServiceSettings(
                    $service->service,
                    parse_url($fabioProxyHost, PHP_URL_HOST),
                    parse_url($fabioProxyHost, PHP_URL_PORT),
                    parse_url($fabioProxyHost, PHP_URL_SCHEME) == 'https' ? true : false,
                    $service->service
                );
            }
        }
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
