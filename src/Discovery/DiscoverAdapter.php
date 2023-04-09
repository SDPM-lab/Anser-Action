<?php

namespace SDPMlab\Anser\Discovery;

use SDPMlab\Anser\Discovery\DiscoverInterface;
use SDPMlab\Anser\Discovery\AnserDiscover;
use SDPMlab\Anser\Discovery\FabioDiscover;
use SDPMlab\Anser\Exception\DiscoverException;
use SDPMlab\Anser\Service\ServiceSettings;

class DiscoverAdapter implements DiscoverInterface
{
    /**
     * 服務探索驅動
     *
     * @var AnserDiscover|FabioDiscover
     */
    public static $discoverDriver;

    public function __construct(\GuzzleHttp\Client $httpClient, array $config)
    {

        if (!array_key_exists("discoverMode", $config)) {
            throw DiscoverException::settingException();
        }

        if ($config["discoverMode"] === "default" && array_key_exists("default", $config)) {

            self::$discoverDriver = new AnserDiscover($httpClient, $config['default']);

        } elseif ($config["discoverMode"] === "fabio" && array_key_exists("fabio", $config)) {

            self::$discoverDriver = new FabioDiscover($httpClient, $config['fabio']);

            self::updateDiscoverServicesList();

        } else {
            throw DiscoverException::connectException();
        }

        return $this;
    }

    /**
     * 映射方法 - 取得服務探索列表
     *
     * @return array
     */
    public static function getDiscoverServiceList(): array
    {
        return self::$discoverDriver->getDiscoverServiceList();
    }

    /**
     * 映射方法 - 取得服務探索驅動
     *
     * @return AnserDiscover|FabioDiscover
     */
    public static function getDiscover(): AnserDiscover|FabioDiscover
    {
        return self::$discoverDriver;
    }

    /**
     * 映射方法 - 取得服務探索中的單一服務
     *
     * @param string $serviceName
     * @return ServiceSettings|null
     */
    public static function getDiscoverService(string $serviceName): ?ServiceSettings
    {
        return self::$discoverDriver->getDiscoverService($serviceName);
    }

    /**
     * 映射方法 - 更新服務探索列表
     *
     * @return void
     */
    public static function updateDiscoverServicesList(): void
    {
        self::$discoverDriver->updateDiscoverServicesList();
    }

    /**
     * 映射方法 - 清空服務探索列表
     *
     * @return void
     */
    public static function clearDiscoverServicesList(): void
    {
        self::$discoverDriver->clearDiscoverServicesList();
    }
}
