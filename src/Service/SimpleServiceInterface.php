<?php
namespace SDPMlab\Anser\Service;

use SDPMlab\Anser\Service\ActionInterface;

interface SimpleServiceInterface
{
    /**
     * 取得 Action 實體
     *
     * @return SDPMlab\Anser\Service\ActionInterface
     */
    public function getAction(): ActionInterface;
}