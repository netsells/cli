<?php

namespace App;

use Humbug\SelfUpdate\Exception\HttpRequestException;
use Humbug\SelfUpdate\Updater;
use LaravelZero\Framework\Components\Updater\Strategy\StrategyInterface;

class UpdateStrategy implements StrategyInterface
{
    private const GITHUB_LATEST_RELEASE_URL = 'https://github.com/netsells/cli/releases/latest/download/netsells.phar';
    private const GITHUB_SPECIFIC_RELEASE_PATTERN = '/download\/([^\/]+)\/netsells\.phar/';

    /**
     * @var string
     */
    private $localVersion;

    /**
     * @var string
     */
    private $remoteUrl;

    /**
     * @var string
     */
    private $packageName;

    /**
     * Download the remote Phar file.
     *
     * @param Updater $updater
     * @return void
     */
    public function download(Updater $updater)
    {
        /** Switch remote request errors to HttpRequestExceptions */
        set_error_handler([$updater, 'throwHttpRequestException']);
        $result = file_get_contents($this->remoteUrl);
        restore_error_handler();
        if (false === $result) {
            throw new HttpRequestException(sprintf(
                'Request to URL failed: %s',
                $this->remoteUrl
            ));
        }

        file_put_contents($updater->getTempPharFile(), $result);
    }

    /**
     * Retrieve the current version available remotely.
     *
     * @param Updater $updater
     * @return string|bool
     */
    public function getCurrentRemoteVersion(Updater $updater)
    {
        /** Switch remote request errors to HttpRequestExceptions */
        set_error_handler([$updater, 'throwHttpRequestException']);

        // we want to make a HEAD request
        // just to get the location header from github to the specific latest version
        $context = stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'follow_location' => 0,
            ],
        ]);

        $headers = get_headers(self::GITHUB_LATEST_RELEASE_URL, true, $context);

        restore_error_handler();

        if (!is_array($headers) || !isset($headers['Location'])) {
            return false;
        }
        
        $this->remoteUrl = $headers['Location'];

        if (preg_match(self::GITHUB_SPECIFIC_RELEASE_PATTERN, $this->remoteUrl, $matches) !== 1 || count($matches) !== 2 || empty($matches[1])) {
            return false;
        }

        // contains the matched version, e.g. v2.3.1
        return $matches[1];
    }

    /**
     * Retrieve the current version of the local phar file.
     *
     * @param Updater $updater
     * @return string
     */
    public function getCurrentLocalVersion(Updater $updater)
    {
        return $this->localVersion;
    }

    /**
     * Set version string of the local phar
     *
     * @param string $version
     */
    public function setCurrentLocalVersion($version)
    {
        $this->localVersion = $version;
    }

    /**
     * Set Package name
     *
     * @param string $name
     */
    public function setPackageName($name)
    {
        $this->packageName = $name;
    }

    /**
     * Get Package name
     *
     * @return string
     */
    public function getPackageName()
    {
        return $this->packageName;
    }
}