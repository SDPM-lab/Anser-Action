<?php

namespace SDPMlab\Anser\Service;

use SDPMlab\Anser\Service\ActionInterface;
use SDPMlab\Anser\Service\FilterInterface;

class ActionFilter
{
    protected static $globalFilter = [
        "before" => [],
        "after" => []
    ];

    /**
     * 傳入 Action 後自動執行全域 Filter。
     *
     * @param ActionInterface $action
     * @return void
     */
    public static function useGlobalFilter(ActionInterface $action, bool $isBefore)
    {
        $type = $isBefore ? "before" : "after";
        foreach (static::$globalFilter[$type] as $className) {
            $filter = static::getFilterInstance($className);
            if($isBefore){
                $filter->beforeCallService($action);
            }else{
                $filter->afterCallService($action);
            }
        }   
    }

    /**
     * 設定全域 Filter，使用這個方法傳入的 Filter 類別的前濾器與後濾器都會被執行到。
     *
     * @param string $className
     * @return void
     */
    public static function setGlobalFilter(string $className)
    {
        static::setGlobalBeforeFilter($className);
        static::setGlobalAfterFilter($className);
    }

    /**
     * 設定全域 Filter，使用這個方法傳入的 Filter 類別的會被執行到前濾器。
     *
     * @param string $className
     * @return void
     */
    public static function setGlobalBeforeFilter(string $className)
    {
        if(!in_array($className,static::$globalFilter["before"])){
            static::$globalFilter["before"][] = $className;
        }
    }

    /**
     * 設定全域 Filter，使用這個方法傳入的 Filter 類別的會被執行到後濾器。
     *
     * @param string $className
     * @return void
     */
    public static function setGlobalAfterFilter(string $className)
    {
        if(!in_array($className,static::$globalFilter["after"])){
            static::$globalFilter["after"][] = $className;
        }
    }

    /**
     * 實體化傳入的類別，並使用前濾器。
     *
     * @param string $className
     * @param ActionInterface $action
     * @return void
     */
    public static function useBeforeFilter(string $className, ActionInterface $action)
    {
        $beforeFilter = static::getFilterInstance($className);
        $beforeFilter->beforeCallService($action);
    }

    /**
     * 實體化傳入的類別，並使用後濾器。
     *
     * @param string $className
     * @param ActionInterface $action
     * @return void
     */
    public static function useAfterFilter(string $className, ActionInterface $action)
    {
        $afterFilter = static::getFilterInstance($className);
        $afterFilter->afterCallService($action);
    }

    /**
     * 重置全域過濾器
     *
     * @return void
     */
    public static function resetGlobalFilter()
    {
        static::$globalFilter = [
            "before" => [],
            "after" => []
        ];
    }

    /**
     * 傳入類別名稱後回傳此類別的實體。
     *
     * @param string $className filter 類別名稱
     * @return FilterInterface
     */
    protected static function getFilterInstance(string $className):FilterInterface
    {
        return new $className();
    }
}
