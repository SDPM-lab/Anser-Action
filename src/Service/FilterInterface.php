<?php

namespace SDPMlab\Anser\Service;

use SDPMlab\Anser\Service\ActionInterface;

interface FilterInterface
{
    /**
     * 執行 Action Do 方法後，在實際發出 Http Request 前將會執行此前濾器
     *
     * @param ActionInterface $action
     * @return void
     */
    public function beforeCallService(ActionInterface $action);

    /**
     * 在 Http Request 獲得響應，且狀態碼為 2XX 成功時將會執行此後濾器。
     * 後濾器會在 Meaning Data Handler 執行前運作。
     *
     * @param ActionInterface $action
     * @return void
     */
    public function afterCallService(ActionInterface $action);
}
