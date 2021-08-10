<?php

namespace Tests\Feature;

use App\Exceptions\ProcessFailed;
use App\Helpers\Process;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AwsEc2ListCommandTest extends TestCase
{
    use WithFaker;

    public function testHandlesError()
    {
        $this->mock(Process::class, function ($mock) {
            $mock->shouldReceive('withCommand')->once()->andReturnSelf();
            $mock->shouldReceive('run')->once()->andThrow(ProcessFailed::class, "Something bad happened", 1);
        });

        $this->artisan('aws:ec2:list')
             ->assertExitCode(1);
    }

    public function testHandlesNoInstances()
    {
        $this->mock(Process::class, function ($mock) {
            $mock->shouldReceive('withCommand')->once()->andReturnSelf();
            $mock->shouldReceive('run')->once()->andReturn(null);
        });

        $this->artisan('aws:ec2:list')
             ->assertExitCode(0);
    }

    public function testHandlesInstances()
    {
        $instance = [
            'InstanceId' => $this->faker->iban,
            'Name' => $this->faker->company,
            'PrivateIpAddress' => $this->faker->localIpv4,
            'InstanceType' => $this->faker->creditCardType,
        ];

        $this->mock(Process::class, function ($mock) use ($instance) {
            $mock->shouldReceive('withCommand')->once()->andReturnSelf();
            $mock->shouldReceive('run')->once()->andReturn(json_encode([
                [
                    [
                        'InstanceId' => $instance['InstanceId'],
                        'Name' => $instance['Name'],
                        'PrivateIpAddress' => $instance['PrivateIpAddress'],
                        'InstanceType' => $instance['InstanceType'],
                    ]
                ]
            ]));
        });

        // TODO: Learn how to read output and assert, laravel's implementation is crap
        $this->artisan('aws:ec2:list')
            ->assertExitCode(0);
    }
}
