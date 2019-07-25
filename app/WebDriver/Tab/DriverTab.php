<?php
/**
 * Created by PhpStorm.
 * User: turgutsaricam
 * Date: 13/05/2019
 * Time: 14:12
 *
 * @since 1.8.1
 */

namespace TurgutSaricam\PHPUnitTools\WebDriver\Tab;


use TurgutSaricam\PHPUnitTools\WebDriver\Base\AbstractDriverManager;

class DriverTab {

    /** @var string Handle of the tab. This is used to interact with the tab. */
    private $handle;

    /** @var float Stores the date at which this tab is last accessed */
    private $lastAccessed;

    /** @var string URL shown in the tab */
    private $url;

    /** @var AbstractDriverManager Driver manager that will be used to retrieve the driver and interact with it. */
    private $driverManager;

    /**
     * DriverTab constructor.
     *
     * @param AbstractDriverManager $driverManager
     * @param string                $handle
     * @param string                $url
     * @param float|null            $lastAccessed Last accessed time. If null, current micro time is used.
     * @since 1.8.1
     */
    public function __construct($driverManager, string $handle, string $url, ?float $lastAccessed = null) {
        $this->driverManager = $driverManager;
        $this->handle = $handle;
        $this->url = $url;

        $this->setLastAccessed($lastAccessed);
    }

    /**
     * Switches the browser's tab to this tab. In other words, activates this tab.
     *
     * @return $this
     * @since 1.8.1
     */
    public function activate() {
        $this->driverManager->getDriver()->switchTo()->window($this->handle);
        $this->updateLastAccessed();
        return $this;
    }

    /**
     * @return string
     * @since 1.8.1
     */
    public function getHandle(): string {
        return $this->handle;
    }

    /**
     * @return float
     * @since 1.8.1
     */
    public function getLastAccessed(): float {
        return $this->lastAccessed;
    }

    /**
     * @param float $lastAccessed
     * @since 1.8.1
     */
    public function setLastAccessed(?float $lastAccessed): void {
        $this->lastAccessed = $lastAccessed ?: microtime(true);
    }

    /**
     * @return string
     * @since 1.8.1
     */
    public function getUrl(): string {
        return $this->url;
    }

    /**
     * @return AbstractDriverManager
     * @since 1.8.1
     */
    public function getDriverManager() {
        return $this->driverManager;
    }

    /*
     * PRIVATE METHODS
     */

    /**
     * Updates the last accessed time for this tab.
     *
     * @return $this
     * @since 1.8.1
     */
    private function updateLastAccessed() {
        $this->setLastAccessed(null);
        return $this;
    }

}