<?php
/**
 * Created by PhpStorm.
 * User: turgutsaricam
 * Date: 25.07.2019
 * Time: 14:15
 */

namespace TurgutSaricam\PHPUnitTools\WebDriver\SetupStrategy\Base;


use Facebook\WebDriver\Remote\RemoteWebDriver;
use TurgutSaricam\PHPUnitTools\WebDriver\Base\AbstractDriverManager;

abstract class AbstractSetupStrategy {

    private $isSetup = false;

    /** @var AbstractDriverManager */
    private $manager;

    /** @var string Host URL to connect to Selenium */
    private $seleniumHostUrl;

    /** @var string The URL that will be loaded in the browser initially. */
    private $initialUrl;

    /**
     * @param string                $seleniumHostUrl See {@link $seleniumHostUrl}
     * @param string                $initialUrl      See {@link $initialUrl}
     */
    public function __construct(string $seleniumHostUrl, string $initialUrl) {
        $this->seleniumHostUrl  = $seleniumHostUrl;
        $this->initialUrl       = $initialUrl;
    }

    /*
     * ABSTRACT METHODS
     */

    /**
     * @return RemoteWebDriver
     */
    abstract protected function handleSetUpAndLogin();

    /*
     * PUBLIC METHODS
     */

    /**
     * Sets the driver up and logs into the WP admin.
     */
    public function setUpDriverAndLogin() {
        // If the driver is already set-up, no need to do it again.
        if ($this->isSetup) return;

        $driver = $this->handleSetUpAndLogin();

        $this->setDriverAsInitialized($driver);
    }

    /*
     * PROTECTED HELPERS
     */

    /**
     * @param RemoteWebDriver $driver
     */
    protected function setDriverAsInitialized($driver) {
        $this->isSetup = true;

        $this->manager->setDriverAsInitialized($driver);
    }

    /**
     * @return string Host URL to connect to Selenium
     */
    protected function getSeleniumHostUrl(): string {
        return $this->seleniumHostUrl;
    }

    /**
     * @return string The URL that will be loaded in the browser initially.
     */
    protected function getInitialUrl(): string {
        return $this->initialUrl;
    }

    /*
     * GETTERS AND SETTERS
     */

    /**
     * @return AbstractDriverManager
     */
    public function getManager() {
        return $this->manager;
    }

    /**
     * @param AbstractDriverManager $manager
     * @return AbstractSetupStrategy
     */
    public function setManager($manager) {
        $this->manager = $manager;

        return $this;
    }

}