<?php

namespace SDPMlab\Anser\Discovery;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Workerman\Worker;

class DiscoverRunner extends BaseCommand
{
    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'SDPM';

    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'SDPMlab:Anser:Discovery';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = '';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'SDPMlab:Anser:Discovery [arguments] [options]';

    /**
     * The Command's Arguments
     *
     * @var array
     */
    protected $arguments = [];

    /**
     * The Command's Options
     *
     * @var array
     */
    protected $options = [];

    /**
     * Actually execute a command.
     *
     * @param array $params
     */
    public function run(array $params)
    {
        echo "123";
    }
}
