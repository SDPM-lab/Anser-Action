<?php

namespace SDPMlab\Anser\Discovery;

interface DiscoverInterface
{
    public static function getDiscoverServiceList(): ?array;

    public static function getDiscoverService(string $serviceName): ?object;

    public static function updateDiscoverServicesList(): void;

    public static function clearDiscoverServicesList(): void;

}
