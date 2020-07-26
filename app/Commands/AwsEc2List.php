<?php

namespace App\Commands;

use App\Helpers\Helpers;
use LaravelZero\Framework\Commands\Command;

class AwsEc2List extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'aws:ec2:list';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'List the instances available';

    /** @var Helpers $helpers */
    protected $helpers;

    public function __construct(Helpers $helpers)
    {
        $this->helpers = $helpers;
        parent::__construct();
    }

    public function configure()
    {
        $this->setDefinition($this->helpers->aws()->commonConsoleOptions());
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $columns = ['InstanceId', 'Name', 'PrivateIpAddress', 'InstanceType'];

        $instances = $this->helpers->aws()->ec2()->listInstances(
            $this,
            "Reservations[*].Instances[*].{InstanceId:InstanceId,Name:Tags[?Key=='Name']|[0].Value,PrivateIpAddress:PrivateIpAddress,InstanceType:InstanceType}"
        )->flatten(1);

        if (is_null($instances)) {
            $this->error("Could not get instances.");
        }

        $this->table($columns, $instances);
    }
}
