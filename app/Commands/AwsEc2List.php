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
        $requiredBinaries = ['aws'];

        if ($this->helpers->checks()->checkAndReportMissingBinaries($this, $requiredBinaries)) {
            return 1;
        }

        $columns = ['InstanceId', 'Name', 'PrivateIpAddress', 'InstanceType'];

        $instances = $this->helpers->aws()->ec2()->listInstances(
            $this,
            "Reservations[*].Instances[*].{InstanceId:InstanceId,Name:Tags[?Key=='Name']|[0].Value,PrivateIpAddress:PrivateIpAddress,InstanceType:InstanceType}"
        );

        if (is_null($instances)) {
            $this->error("Could not get instances.");
            return 1;
        }

        $this->table($columns, $instances->flatten(1));
    }
}
