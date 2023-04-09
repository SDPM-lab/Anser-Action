<?php

namespace SDPMlab\Anser\Service;

use SDPMlab\Anser\Service\Action;
use SDPMlab\Anser\Exception\SimpleServiceException;

abstract class SimpleService
{
    /**
     * 服務名稱或端點網址
     *
     * @var string|null
     */
    protected $serviceName;

    /**
     * 統一宣告此類別 Action 過濾器
     *
     * @var array<string,array<string>>
     */
    protected $filters = [
        "before" => [],
        "after" => [],
    ];

    /**
     * 失敗重試次數
     *
     * @var integer
     */
    protected $retry = 0;

    /**
     * 失敗重試間隔秒數
     *
     * @var float
     */
    protected $retryDelay = 0.2;

    /**
     * 服務請求逾時時間
     *
     * @var float
     */
    protected $timeout = 2.0;

    /**
     * Guzzle7 option array
     *
     * @var array
     */
    protected $options = [];

    /**
     * 取得 Action 實體
     *
     * @param string $method Http 請求方法
     * @param string $path 資源路徑
     * @param array $options Guzzle7 option 陣列
     * @return Action
     */
    final protected function getAction(
        string $method,
        string $path
    ): Action {

        if($this->serviceName == null) {
            throw SimpleServiceException::forServiceNameNull();
        }

        //實體化 action
        $action = new Action($this->serviceName, $method, $path);

        //判斷是否有統一設定 retry 規則
        $action->setRetry($this->retry, $this->retryDelay);

        //判斷是否有統一設定 timeout 規則
        $action->setTimeout($this->timeout);

        //判斷是否有統一設定 option 規則
        $action->setOptions($this->options);

        //將統一設定的 Filters 宣告進 action 中
        $this->setFilters($action);

        return $action;
    }

    private function setFilters(Action &$action)
    {
        //判斷是否有 before after 成員存在
        if(!isset($this->filters["before"]) || !isset($this->filters["after"])) {
            throw SimpleServiceException::forFilterNotFound();
        }

        //判斷 before after 成員是否為陣列
        if(!is_array($this->filters["before"]) || !is_array($this->filters["after"])) {
            throw SimpleServiceException::forFilterNotStringArray();
        }

        $before = $this->filters["before"];
        $after = $this->filters["after"];
        foreach ($before as $filterClassName) {
            $action->addBeforeFilter($filterClassName);
        }
        foreach ($after as $filterClassName) {
            $action->addAfterFilter($filterClassName);
        }
    }

}
