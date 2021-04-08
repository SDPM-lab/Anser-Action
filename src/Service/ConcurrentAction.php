<?php

namespace SDPMlab\Anser\Service;

use SDPMlab\Anser\Service\ActionInterface;
use GuzzleHttp\Pool;

class ConcurrentAction
{

    /**
     * action 列表。其組成結構為<別名,Action實體>
     *
     * @var array<string,\SDPMlab\Anser\Service\ActionInterface>
     */
    protected $actionList = [];

    /**
     * 由非同步 request 組合而成的 promise 列表
     *
     * @var array<string,\GuzzleHttp\Promise\PromiseInterface>
     */
    protected $promises = [];

    /**
     * 設定參與並行連線的 Actions
     *
     * @param array<string,\SDPMlab\Anser\Service\ActionInterface> $actionList 傳入由<別名,Action實體> 組成的鍵值陣列
     * @return ConcurrentAction 
     */
    public function setActions(array $actionList): ConcurrentAction
    {
        foreach ($actionList as $alias => $action) {
            $this->addAction($alias, $action);
        }
        return $this;
    }

    /**
     * 將單個 Action 加入參與並行連線的列表中。
     *
     * @param string $alias
     * @param ActionInterface $action
     * @return ConcurrentAction
     */
    public function addAction(string $alias, ActionInterface $action): ConcurrentAction
    {
        if (isset($this->actionList[$alias])); //拋出例外;
        $this->actionList[$alias] = $action;
        return $this;
    }

    /**
     * 將在列表中被設定的 Action 以 
     *
     * @return void
     */
    public function send()
    {
        $promises = [];
        foreach ($this->actionList as $alias => $action) {
            $asyncAction = $action->doAsync($alias);
            $promises[$alias] = $asyncAction;
        }
        \GuzzleHttp\Promise\Utils::unwrap($promises);
    }

    /**
     * 取得存在於列表中的 action 實體。
     *
     * @param string $alias
     * @return ActionInterface
     */
    public function getAction(string $alias): ActionInterface
    {
        if (!isset($this->actionList[$alias])); //拋出例外;
        return $this->actionList[$alias];
    }

}
