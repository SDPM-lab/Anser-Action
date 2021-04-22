<?php

namespace SDPMlab\Anser\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;
use SDPMlab\Anser\Service\RequestSettings;

interface ActionInterface
{

    /**
     * 執行 Action 所定義的內容
     *
     * @return \SDPMlab\Anser\Service\ActionInterface
     */
    function do(): ActionInterface;

    /**
     * 執行非同步 Request
     *
     * @param string $alias action 別稱
     * @param boolean $isRetry
     * @return PromiseInterface
     */
    public function doAsync(string $alias, bool $isRetry = false): PromiseInterface;

    /**
     * 設定 Action 內的 Response 成員。
     *
     * @param ResponseInterface|null $response 傳入響應實體，若無則可傳入 null
     * @param boolean $isSuccess 請求是否成功
     * @return void
     */
    public function setActionResponse(?ResponseInterface $response, bool $isSuccess);

    /**
     * 定義 Action 完成時的處理器。
     * 所傳入的處理器將會在 Action 請求成功(status 2XX)且執行完後濾器後自動執行。
     *
     * @param callable(\Psr\Http\Message\ResponseInterface ,\SDPMlab\Anser\Service\ActionInterface):mixed $handler
     * @return ActionInterface
     */
    public function failHandler(callable $handler): ActionInterface;

    /**
     * 處理伺服器回傳例外
     *
     * @param \GuzzleHttp\Exception\ServerException $th
     * @param string|null $alias
     * @return void
     */
    public function processFailHandler(\GuzzleHttp\Exception\BadResponseException $th, ?string $alias = null);

    /**
     * 取得錯誤處理程序 callable。
     *
     * @return callable|null 若無設定則回傳 null
     */
    public function getFaileHandler(): ?callable ;

    /**
     * 設定重試規則
     *
     * @param integer $retry 重試最大次數，預設為 0 (不重試)
     * @param float $retryDelay 重試間隔秒數，預設為 0.2
     * @return ActionInterface
     */
    public function setRetry(int $retry, float $retryDelay = 0.2): ActionInterface;

    /**
     * 取得 Retry 相關設定
     *
     * @return array<integer,float> 重試次數與等待下次重試時間
     */
    public function getRetrySetting(): array;

    /**
     * Action 實際連執行數遞增 1
     *
     * @return void
     */
    public function addNumOfActionDo();

    /**
     * 設定最長請求時間
     *
     * @param float $timeout 時間不可低於 0.0 秒
     * @return ActionInterface
     */
    public function setTimeout(float $timeout): ActionInterface;

    /**
     * 回傳 action 請求是否執行成功（Http 2XX）
     *
     * @return boolean
     */
    public function isSuccess(): bool;

    /**
     * 通常，你不會使用到這個方法。isSuccess 的判斷是在 Action-Do 之後自動執行的。
     * 若是你所溝通的端點不論是成功或失敗都會回傳 Http status code 200。
     * 那麼你就需要透過這個方法自行切換 Action 的最終狀態。
     * 
     * @param boolean $isSuccess True 為執行成功 False 則為失敗
     * @return ActionInterface
     */
    public function setSuccess(bool $isSuccess): ActionInterface;

    /**
     * 回傳 action 總執行次數（不論成功與否）。
     *
     * @return integer
     */
    public function getNumnerOfDoAction(): int;

    /**
     * 取得 Request 設定物件
     *
     * @return RequestSettings
     */
    public function getRequestSetting(): RequestSettings;

    /**
     * 取得 Action 執行後的 Response 內容
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function getResponse(): ResponseInterface;

    /**
     * 取得 GuzzleHttp Client 實體
     *
     * @return ClientInterface
     */
    public function getHttpClient(): ClientInterface;

    /**
     * 定義 Action 完成時的處理器。
     * 所傳入的處理器將會在 Action 請求成功(status 2XX)且執行完後濾器後自動執行。
     *
     * @param callable(\SDPMlab\Anser\Service\ActionInterface):void $handler
     * @return ActionInterface
     */
    public function doneHandler(callable $handler): ActionInterface;

    /**
     * 使 done handler 處理器生效。
     *
     * @return void
     */
    public function useDoneHandler();

    /**
     * 將傳入的變數設為 action 的 meaning data。
     *
     * @param mixed $meaningData
     * @return ActionInterface
     */
    public function setMeaningData($meaningData): ActionInterface;

    /**
     * 若有定義 Meaning Data Handler 或是透過 setMeaningData 方法宣告 Meaning Data，則會回傳 Meaning Data 變數。
     *
     * @return mixed|null 回傳定義的 Meaning Data 變數或是 Null
     */
    public function getMeaningData();

    /**
     * 使用 Action 中定義的過濾器
     *
     * @return void
     */
    public function useFilters(bool $isBefore);

    /**
     * 設定 Action 所需的過濾器。
     *
     * @return ActionInterface
     */
    public function setFilter(string $className): ActionInterface;

    /**
     * 設定 Action 所使用的前濾器
     *
     * @param string $className
     * @return ActionInterface
     */
    public function addAfterFilter(string $className): ActionInterface;

    /**
     * 設定 Action 所使用的後濾器
     *
     * @param string $className
     * @return ActionInterface
     */
    public function addBeforeFilter(string $className): ActionInterface;

    /**
     * 設定 Action 執行請求時所使用的設定，其規則與 Guzzle7 的 Option 一致。
     * 使用此方法將會覆蓋目前所有的 Options 設定。
     *
     * @return ActionInterface
     */
    public function setOptions(array $options): ActionInterface;

    /**
     * 新增一筆 Option 設定，其規則與 Guzzle7 的 Option 一致。
     * 若已有同名之設定，將會覆蓋過去。
     *
     * @param string $optionName 選項名稱
     * @param mixed $value 設定值
     * @return ActionInterface
     */
    public function addOption(string $optionName, mixed $value): ActionInterface;

    /**
     * 刪除目前存在的 Option。
     *
     * @param string $optionName
     * @return ActionInterface
     */
    public function removeOption(string $optionName): ActionInterface;

    /**
     * 將會回傳目前 Action 被設定的 Options 陣列。
     *
     * @return array
     */
    public function getOptions(): array;

    /**
     * 將會回傳單個 Option 的設定值。
     *
     * @param string $optionName Option 名稱
     * @return mixed 設定值
     */
    public function getOption(string $optionName);

}
