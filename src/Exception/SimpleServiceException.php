<?php

namespace SDPMlab\Anser\Exception;

use SDPMlab\Anser\Exception\AnserException;

class SimpleServiceException extends AnserException
{
    /**
     * 初始化　SimpleServiceException
     *
     * @param string $message 錯誤訊息
     */
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forServiceNameNull(): SimpleServiceException
    {
        return new self("必須 overwrite 父類別 SimpleService 的成員變數 serviceName；在 serviceName 變數中宣告你在服務列表中定義的任一服務名稱。");
    }

    public static function forFilterNotFound(): SimpleServiceException
    {
        return new self("成員變數 filters 必須唯 Key/Value 陣列，且必須含有 before 與 after 成員");
    }

    public static function forFilterNotStringArray()
    {
        return new self("成員變數 filters 的 鍵-before 與 鍵-after 必須是以 Filter 類別名稱組成的字串陣列。");
    }

}
