<?php

namespace App\Helpers\Aws;

use App\Exceptions\ProcessFailed;
use App\Helpers\Aws;
use Aws\Iam\IamClient;
use Aws\Sts\StsClient;

class Iam
{
    /** @var Aws $aws */
    protected $aws;

    public function __construct(Aws $aws)
    {
        $this->aws = $aws;
    }

    public function listMfaDevices($userArn = null): array
    {
        if (!$userArn) {
            $userArn = $this->getCallerArn();
        }

        $client = new IamClient($this->aws->standardSdkArguments());

        $devices = $client->listVirtualMFADevices([
            'AssignmentStatus' => 'Assigned'
        ]);

        return collect($devices->get('VirtualMFADevices'))
            ->filter(function ($device) use ($userArn) {
                return $device['User']['Arn'] === $userArn;
            })
            ->map(function ($device) {
                return $device['SerialNumber'];
            })
            ->values()
            ->all();
    }

    public function getCallerArn(): string
    {
        $client = new StsClient($this->aws->standardSdkArguments());

        $response = $client->getCallerIdentity();

        return $response->get('Arn');
    }

    public function authenticateWithMfaDevice($mfaDeviceArn, $code): array
    {
        $commandOptions = [
            'sts',
            'get-session-token',
            '--serial-number', $mfaDeviceArn,
            '--token-code', $code
        ];

        try {
            $processOutput = $this->aws->newProcess($commandOptions)->run();
        } catch (ProcessFailed $e) {
            return [];
        }

        $result = json_decode($processOutput, true);

        return [
            'AWS_ACCESS_KEY_ID' => $result['Credentials']['AccessKeyId'],
            'AWS_SECRET_ACCESS_KEY' => $result['Credentials']['SecretAccessKey'],
            'AWS_SESSION_TOKEN' => $result['Credentials']['SessionToken'],
        ];
    }

    public function assumeRole(string $accountId, string $role, string $sessionUser, ?string $mfaDevice = null, ?string $mfaCode = null): array
    {
        $client = new StsClient($this->aws->standardSdkArguments());

        $assumeData = [
            'RoleArn' => "arn:aws:iam::{$accountId}:role/{$role}",
            'RoleSessionName' => "{$sessionUser}-on-{$accountId}",
        ];

        if ($mfaDevice && $mfaCode) {
            $assumeData['SerialNumber'] = $mfaDevice;
            $assumeData['TokenCode'] = $mfaCode;
        }

        $response = $client->assumeRole($assumeData);

        return [
            'AWS_ACCESS_KEY_ID' => $response->get('Credentials')['AccessKeyId'],
            'AWS_SECRET_ACCESS_KEY' => $response->get('Credentials')['SecretAccessKey'],
            'AWS_SESSION_TOKEN' => $response->get('Credentials')['SessionToken'],
        ];
    }
}
