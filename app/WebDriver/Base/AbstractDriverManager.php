<?php
/**
 * Created by PhpStorm.
 * User: turgutsaricam
 * Date: 25.07.2019
 * Time: 13:01
 */

namespace TurgutSaricam\PHPUnitTools\WebDriver\Base;


use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\TimeOutException;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use TurgutSaricam\PHPUnitTools\WebDriver\SetupStrategy\Base\AbstractSetupStrategy;
use TurgutSaricam\PHPUnitTools\WebDriver\Tab\DriverTab;

abstract class AbstractDriverManager {

    /** @var AbstractSetupStrategy */
    private $setupStrategy;

    /** @var bool */
    protected $isSetup = false;

    /** @var RemoteWebDriver */
    protected $driver;

    /** @var DriverTab[] Stores the tabs that are open in the browser */
    protected $tabs = [];

    /** @var int Maximum number of tabs that can be open at the same time */
    protected $maxTabCount;

    /**
     * @param AbstractSetupStrategy $setupStrategy
     * @param int                   $maxTabCount See {@link $maxTabCount}
     * 
     */
    protected function __construct($setupStrategy, int $maxTabCount = 8) {
        $this->setupStrategy    = $setupStrategy;
        $this->maxTabCount      = $maxTabCount;

        // Set up the driver and login when this class is constructed.
        $this->setUpDriverAndLogin();
    }

    public function __destruct() {
        // Quit from the browser when this object is destroyed. This object will probably be destroyed when all the
        // tests are finished. So, the browser will not be needed after this object is destructed. If the get method of
        // this class is called at a later time, a new browser session will be initialized without any errors. So,
        // quitting here is safe to do.
        $this->driver->quit();
    }

    /*
     * PUBLIC METHODS
     */

    /**
     * Make the driver wait until an element is present in the page
     *
     * @param string $cssSelector CSS selector for the target element
     *
     * @return $this
     * @throws NoSuchElementException
     * @throws TimeOutException
     */
    public function waitUntilPresenceOfElement($cssSelector) {
        $this->getDriver()->wait(10)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector($cssSelector))
        );

        return $this;
    }

    /**
     * @return RemoteWebDriver
     */
    public function getDriver(): RemoteWebDriver {
        return $this->driver;
    }

    /**
     * Get to a URL in the browser. This opens the URL in a new tab. If there is a tab already showing the given URL,
     * this method just switches to that tab.
     *
     * @param string $url The URL that will be opened in the browser.
     */
    public function get(string $url) {
        $url = $this->maybeCreateCoverageEnabledUrl($url);

        // Check if there is a browser tab showing this URL. If so, just activate it and stop.
        foreach($this->tabs as $driverTab) {
            if ($driverTab->getUrl() !== $url) continue;

            $driverTab->activate();
            return;
        }

        // There is no tab found. Open a new tab via JavaScript and make sure the tab is activated. Then, load the URL
        // in the newly-added tab.
        $this->openNewTab();

        $this->getDriver()->get($url);
        $this->maybeModifyAjaxUrlForCoverage();

        // Add the current window as a tab
        $this->addCurrentWindowAsTab();
    }

    /**
     * Calls {@link RemoteWebDriver::get()} directly, without opening a new tab or checking if the URL is already
     * loaded in a new tab. This method must be used instead of directly calling the driver's get method.
     *
     * @param string $url
     */
    public function getNonManaged(string $url) {
        $this->getDriver()->get($this->maybeCreateCoverageEnabledUrl($url));
    }

    /**
     * Do something in a new tab. This method closes the tab after the operation is finished and reactivates the
     * previously active tab.
     *
     * @param callable $callback A callback that performs the actions that should be done in a new tab. Returns nothing.
     *                           E.g. <b><i>function() { }</i></b>
     * @return $this
     */
    public function doInNewTab(callable $callback) {
        // Get the current window's handle and store it since we need it to activate the current window again
        $prevWindowHandle = $this->getDriver()->getWindowHandle();

        // Open a new tab. It will be activated after it is opened.
        $this->openNewTab();
        $newTabHandle = $this->getDriver()->getWindowHandle();

        // Do the thing in the current tab.
        $callback();

        // Close the tab and activate the previously-active tab
        $this->getDriver()
            ->switchTo()->window($newTabHandle)
            ->close()
            ->switchTo()->window($prevWindowHandle);

        return $this;
    }

    /**
     * Closes the currently active tab/window.
     *
     * @return $this
     */
    public function closeCurrentTab() {
        // Find the active tab and close it
        $handle = $this->getDriver()->getWindowHandle();
        $activeTab = null;
        foreach($this->tabs as $tab) {
            if ($tab->getHandle() !== $handle) continue;

            $this->closeTab($tab);
            break;
        }

        return $this;
    }

    /**
     * Refreshes the current tab.
     *
     * @return $this
     */
    public function refreshCurrentTab() {
        $this->getDriver()->navigate()->refresh();
        $this->maybeModifyAjaxUrlForCoverage();
        return $this;
    }

    /**
     * Refresh all open tabs.
     *
     * @return $this
     */
    public function refreshAllTabs() {
        foreach($this->tabs as $tab) {
            $tab->activate();
            $this->refreshCurrentTab();
        }

        return $this;
    }

    /**
     * Get the tab created latest.
     *
     * @return DriverTab
     */
    public function getLastTab() {
        $tabCount = $this->getTabCount();
        if ($tabCount === 0) throw new \Exception('There is no tab.');

        return $this->tabs[$tabCount - 1];
    }

    /**
     * Closes all tabs, and hence, the browser.
     *
     * @throws \Exception See {@link closeTab()}
     */
    public function closeBrowser() {
        // Quit
        $this->driver->quit();

        // Invalidate the instance of this class
        $this->onInvalidateInstance();
    }

    /**
     * Modifies a URL to make it a coverage-enabled URL if code coverage is enabled.
     *
     * @param string $url Original URL
     * @return string Test URL
     */
    public function maybeCreateCoverageEnabledUrl(string $url) {
        // If coverage is not enabled, return the given URL.
        if (!$this->isCoverageEnabled()) return $url;

        // Add test name as a URL parameter
        $paramName = $this->getCoverageHintingKey();

        // Get query parameters of the URL
        $existingQueryParams = parse_url($url, PHP_URL_QUERY);
        if ($existingQueryParams) {
            parse_str($existingQueryParams, $parsedQuery);

            // If test parameter name is already set, do not set it again.
            if (isset($parsedQuery[$paramName])) {
                return $url;
            }
        }

        $testName = preg_replace('/[?.\/\\:;=&]/', '_', basename($url));

        // Recreate the URL
        $testNameQuery = "{$paramName}={$testName}";

        $url .= strpos($url, '?') !== false ? '&' : '?';
        $url .= $testNameQuery;

        return $url;
    }

    /*
     * PROTECTED METHODS
     */

    /**
     * Sets the driver up and logs into the WP admin.
     */
    protected function setUpDriverAndLogin() {
        $this->setupStrategy
            ->setManager($this)
            ->setUpDriverAndLogin();
    }

    /**
     * Set the driver as initialized. This assigns the given driver to an instance variable and sets {@link $isSetup} as
     * true. Also, adds the current window as a tab.
     *
     * @param RemoteWebDriver $driver
     */
    public function setDriverAsInitialized($driver) {
        $this->isSetup = true;
        $this->driver = $driver;

        // Store the current window handle and its URL as a tab
        $this->addCurrentWindowAsTab();
    }

    /**
     * Add current window/tab to {@link $tabs}.
     *
     * @return DriverTab Newly added {@link DriverTab}
     * @throws \Exception If the current window is already among the registered tabs.
     */
    protected function addCurrentWindowAsTab() {
        $handle = $this->getDriver()->getWindowHandle();

        $existingTab = $this->getTabByHandle($handle);
        if ($existingTab !== null) {
            throw new \Exception("This tab already exists among the registered tabs.");
        }

        $currentTab = new DriverTab($this, $handle, $this->getDriver()->getCurrentURL());
        $this->tabs[] = $currentTab;
        $this->tabListModified();

        $this->closeExcessiveTabs();

        return $currentTab;
    }

    /**
     * Close the tabs if the number of open tabs is greater than {@link maxTabCount}.
     */
    protected function closeExcessiveTabs() {
        // If there are not at least two tabs, stop. Since we do not want to close the browser.
        if ($this->getTabCount() < 2) return;

        // Get the currently active tab. There must be at least one tab by the design of this class
        $currentActiveTab = $this->getTabByHandle($this->getDriver()->getWindowHandle());

        // Close all excessive tabs
        while($this->getTabCount() > $this->maxTabCount) {
            // Close the oldest tab
            $this->closeTab($this->getOldestActiveTab());
        }

        // Active the current tab again
        $currentActiveTab->activate();
    }

    /**
     * @param DriverTab $tab
     * @throws \Exception If a tab could not be closed.
     */
    protected function closeTab($tab) {
        // Close it. First, activate. Then, close the last active window.
        $tab->activate();
        $this->getDriver()->close();

        // Make sure the tab is closed by checking its handle
        $handles = $this->getDriver()->getWindowHandles();
        if (isset($handles[$tab->getHandle()])) {
            throw new \Exception(sprintf(
                'Tab with handle %1$s and URL %2$s could not be closed.',
                $tab->getHandle(),
                $tab->getUrl()
            ));
        }

        // Remove the tab from the tabs array
        unset($this->tabs[array_search($tab, $this->tabs)]);
        $this->tabListModified();

        // Activate the last tab
        $this->getLastTab()->activate();
    }

    /**
     * Get tab by its handle
     *
     * @param string $handle Tab/window handle
     * @return null|DriverTab
     */
    protected function getTabByHandle(string $handle) {
        foreach($this->tabs as $tab) {
            if ($tab->getHandle() !== $handle) continue;

            return $tab;
        }

        return null;
    }

    /**
     * Get the number of tabs
     *
     * @return int
     */
    protected function getTabCount(): int {
        return sizeof($this->tabs);
    }

    /**
     * Get the tab that is accessed the most further in the past.
     *
     * @return null|DriverTab Last accessed tab
     */
    protected function getOldestActiveTab() {
        $minDate = null;
        $lastAccessedTab = null;

        foreach($this->tabs as $tab) {
            // Get the last access date of this tab
            $lastAccessDate = $tab->getLastAccessed();

            // If it is smaller than the minimum, than this is the oldest active tab.
            if ($minDate === null || $lastAccessDate < $minDate) {
                $minDate = $lastAccessDate;
                $lastAccessedTab = $tab;
            }
        }

        return $lastAccessedTab;
    }

    /**
     * Opens a new browser tab and activates it.
     *
     * @return $this
     */
    protected function openNewTab() {
        // Activate the last tab. This is a fix. When a tab is closed, another tab is not activated by the webdriver.
        // In that case, there is no currently active window. It results in a fatal error, NoSuchDriverException, when
        // the driver is tried to be accessed. So, just activate the latest tab so that there is an active window for
        // sure.
        $this->getLastTab()->activate();

        $this->getDriver()->executeScript("window.open('about:blank','_blank');", []);

        $handles = $this->getDriver()->getWindowHandles();
        $this->getDriver()->switchTo()->window(end($handles));

        return $this;
    }

    /**
     * Handles the things that should be done after modification of {@link tabs}
     */
    protected function tabListModified() {
        // Reset the keys of the array so that the keys become sequential.
        $this->tabs = array_values($this->tabs);
    }

    /**
     * If code coverage is enabled, modifies window.ajaxurl so that coverage of the code run by AJAX calls are analyzed
     * as well.
     *
     * @return $this
     */
    protected function maybeModifyAjaxUrlForCoverage() {
        // Add the test name query to the AJAX url existing in the page as well
        $ajaxUrl = $this->getDriver()->executeScript("return window.ajaxurl;");
        $this->getDriver()->executeScript(sprintf('window.ajaxurl = "%1$s";', $this->maybeCreateCoverageEnabledUrl($ajaxUrl)));

        return $this;
    }

    /**
     * Invalidate the instance if this is a singleton.
     */
    protected function onInvalidateInstance() {

    }

    /*
     * ABSTRACT METHODS
     */

    /**
     * @return bool True if code coverage is enabled.
     * 
     */
    abstract protected function isCoverageEnabled(): bool;

    /**
     * Get the key that will be used to hint that code coverage is enabled. This key is appended to the URLs as a
     * parameter with the value of test name. E.g. if this method returns 'testName', the URLs will be appended
     * '&testName=nameOfCurrentTest'
     *
     * @return string The key that will be used when defining test names
     * 
     */
    abstract protected function getCoverageHintingKey(): string;

}