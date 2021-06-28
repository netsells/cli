<?php

namespace App\Commands;

class AwsEc2List extends BaseCommand
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

        if ($this->helpers->checks()->checkAndReportMissingBinaries($requiredBinaries)) {
            return 1;
        }

        $columns = ['InstanceId', 'Name', 'PrivateIpAddress', 'InstanceType'];

        $instances = $this->helpers->aws()->ec2()->listInstances(
            "Reservations[*].Instances[*].{InstanceId:InstanceId,Name:Tags[?Key=='Name']|[0].Value,PrivateIpAddress:PrivateIpAddress,InstanceType:InstanceType}"
        );

        if (is_null($instances)) {
            $this->error("Could not get instances.");
            return 1;
        }

        $this->table($columns, $instances->flatten(1));
    }
}
