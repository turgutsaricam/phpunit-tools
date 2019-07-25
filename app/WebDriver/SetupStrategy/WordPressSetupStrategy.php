<?php
/**
 * Created by PhpStorm.
 * User: turgutsaricam
 * Date: 25.07.2019
 * Time: 14:23
 */

namespace TurgutSaricam\PHPUnitTools\WebDriver\SetupStrategy;


use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Cookie;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\TimeOutException;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use TurgutSaricam\PHPUnitTools\WebDriver\SetupStrategy\Base\AbstractSetupStrategy;

class WordPressSetupStrategy extends AbstractSetupStrategy {

    /** @var string */
    private $loginUserName;

    /** @var string */
    private $loginPassword;

    /** @var string */
    private $loginCookieName;

    /** @var string */
    private $loginCookieValue;

    /**
     * @param string $seleniumHostUrl  See {@link $seleniumHostUrl}
     * @param string $initialUrl       See {@link $initialUrl}
     * @param string $loginUserName    See {@link $loginUserName}
     * @param string $loginPassword    See {@link $loginPassword}
     * @param string $loginCookieName  See {@link $loginCookieName}
     * @param string $loginCookieValue See {@link $loginCookieValue}
     */
    public function __construct(string $seleniumHostUrl, string $initialUrl, string $loginUserName,
                                string $loginPassword, string $loginCookieName = '', string $loginCookieValue = '') {
        parent::__construct($seleniumHostUrl, $initialUrl);

        $this->loginUserName    = $loginUserName;
        $this->loginPassword    = $loginPassword;
        $this->loginCookieName  = $loginCookieName;
        $this->loginCookieValue = $loginCookieValue;
    }

    /**
     * @return RemoteWebDriver
     * @throws \Exception
     */
    protected function handleSetUpAndLogin() {
        // WP admin login credentials
        $loginUser          = $this->getLoginUserName();
        $loginPassword      = $this->getLoginPassword();
        $loginCookieName    = $this->getLoginCookieName();
        $loginCookieValue   = $this->getLoginCookieValue();
        $selectorAdminBar   = WebDriverBy::cssSelector('#wpadminbar');

        $options = new ChromeOptions();

        // Available options:
        // http://peter.sh/experiments/chromium-command-line-switches/
        $options->addArguments(array(
            '--window-size=1366,768',
        ));

        $caps = DesiredCapabilities::chrome();
        $caps->setCapability(ChromeOptions::CAPABILITY, $options);

        // Create the driver.
        $driver = RemoteWebDriver::create($this->getSeleniumHostUrl(), $caps);

        $siteListUrl = $this->getManager()->maybeCreateCoverageEnabledUrl($this->getInitialUrl());

        // If the login cookie is set, add it to the driver.
        if ($loginCookieName && $loginCookieValue) {
            // Go the site list page, so that the cookies are set to that URL. Setting the cookies without going to the
            // target URL will not work, since the driver does not know for what URL the cookies should be set.
            $driver->get($siteListUrl);
            $cookie = new Cookie($loginCookieName, $loginCookieValue);
            $driver->manage()->addCookie($cookie);
        }

        // Go to the site list page again. If the admin bar exists, we have logged in using the cookies. Otherwise, we
        // need to login with the user name and the password.
        $driver->get($siteListUrl);

        try {
            // Try to locate the admin bar. If there is no admin bar, an exception will be thrown.
            $driver->findElement($selectorAdminBar);

            // No exception is thrown. We are logged in. Assign the variables and stop.
            $this->setDriverAsInitialized($driver);

            echo "Login with cookies is successful.\n";
            return $driver;

        } catch(NoSuchElementException $e) {
            // Do nothing.
            echo "Login with cookies did not succeed. Trying to login with user name and password...\n";
        }

        $driver->findElement(WebDriverBy::cssSelector('#user_login'))->sendKeys($loginUser);
        $driver->findElement(WebDriverBy::cssSelector('#user_pass'))->sendKeys($loginPassword);
        $driver->findElement(WebDriverBy::cssSelector('#rememberme'))->click();
        $driver->findElement(WebDriverBy::cssSelector('#wp-submit'))->click();

        // Make sure we are logged in. Admin bar is shown only if the login was successful.
        try {
            // Try to locate the admin bar. If there is no admin bar, an exception will be thrown.
            $driver->findElement($selectorAdminBar);

        } catch(\Exception $e) {
            // Wait until the admin bar becomes available.
            try {
                $driver->wait(5)->until(
                    WebDriverExpectedCondition::presenceOfElementLocated($selectorAdminBar)
                );
            } catch (NoSuchElementException $e) {
                $driver->takeScreenshot(__DIR__ . 'ss-no-such-el.png');
                exit("Login was not successful.");

            } catch (TimeOutException $e) {
                $driver->takeScreenshot(__DIR__ . 'ss-timeout.png');
                exit("Login was not successful.");
            }
        }

        return $driver;
    }

    /*
     * GETTERS
     */

    /**
     * @return string
     */
    public function getLoginUserName(): string {
        return $this->loginUserName;
    }

    /**
     * @return string
     */
    public function getLoginPassword(): string {
        return $this->loginPassword;
    }

    /**
     * @return string
     */
    public function getLoginCookieName(): string {
        return $this->loginCookieName;
    }

    /**
     * @return string
     */
    public function getLoginCookieValue(): string {
        return $this->loginCookieValue;
    }

}