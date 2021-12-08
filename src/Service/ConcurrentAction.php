<?php

namespace SDPMlab\Anser\Service;

use SDPMlab\Anser\Service\ActionInterface;

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
     * 執行並行連線
     *
     * @return void
     */
    public function send()
    {
        foreach ($this->actionList as $alias => $action) {
            $asyncAction = $action->doAsync($alias);
            $this->promises[$alias] = $asyncAction;
        }
        \GuzzleHttp\Promise\Utils::unwrap($this->promises);
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

    /**
     * 取得所有參與並行連線的 Action meaning data
     *
     * @return array
     */
    public function getActionsMeaningData(): array
    {
        $datas = [];
        foreach ($this->actionList as $alias => $action) {
            $datas[$alias] = $action->getMeaningData();
        }
        return $datas;
    }

}
