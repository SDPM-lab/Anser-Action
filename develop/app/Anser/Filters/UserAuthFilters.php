<?php

namespace App\Anser\Filters;

use SDPMlab\Anser\Service\FilterInterface;
use SDPMlab\Anser\Service\ActionInterface;

class UserAuthFilters implements FilterInterface
{
    public function beforeCallService(ActionInterface $action)
    {
        $options = $action->getOptions();
        isset($options["headers"]) ?: $options["headers"] = [];
        $options["headers"]["X-User-Islgoin"] = "true";
        $options["headers"]["X-User-Key"] = 1;
        $action->setOptions($options);
    }

    public function afterCallService(ActionInterface $action)
    {
        $GLOBALS["filter"] = true;
    }
}
