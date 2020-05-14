<?php

namespace App;

use Humbug\SelfUpdate\Updater;
use LaravelZero\Framework\Components\Updater\Strategy\StrategyInterface;

class UpdateStrategy implements StrategyInterface
{
    /**
     * @var string
     */
    private $localVersion;

    /**
     * @var string
     */
    private $remoteVersion;

    /**
     * @var string
     */
    private $remoteUrl;

    /**
     * @var string
     */
    private $pharName;

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
        set_error_handler(array($updater, 'throwHttpRequestException'));
        $result = humbug_get_contents($this->remoteUrl);
        restore_error_handler();
        if (false === $result) {
            throw new HttpRequestException(sprintf(
                'Request to URL failed: %s', $this->remoteUrl
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
        set_error_handler(array($updater, 'throwHttpRequestException'));
        $versionUrl = 'https://netsells-cli.now.sh/version';
        $version = json_decode(humbug_get_contents($versionUrl), true);

        restore_error_handler();

        if (null === $version || json_last_error() !== JSON_ERROR_NONE) {
            throw new JsonParsingException(
                'Error parsing JSON package data'
                . (function_exists('json_last_error_msg') ? ': ' . json_last_error_msg() : '')
            );
        }

        $this->remoteVersion = $version['version'];

        /**
         * Setup remote URL if there's an actual version to download
         */
        if (!empty($this->remoteVersion)) {
            $this->remoteUrl = 'https://netsells-cli.now.sh/download/cli';
        }

        return $this->remoteVersion;
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