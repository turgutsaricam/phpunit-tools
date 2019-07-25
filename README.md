# phpunit-tools
This repository contains tools that are commonly needed to perform unit and UI tests using PHPUnit.

# Coverage Package
This package contains `CoverageHandler` and `ReportGenerator` classes. `CoverageHandler` is responsible for listening to `$_POST`, `$_GET`, and `$_POST` arrays for a hint signaling that code coverage should be enabled. When the class retrieves the hint, it starts code coverage and dumps the coverage data into the defined directory. `ReportGenerator` is responsible for generating code coverage reports from the code coverage data dumped by `CoverageHandler`. It can generate HTML and Clover reports.

## CoverageHandler
Note the following items for this class to work properly

- Xdebug extension of PHP must be enabled in the server of the web site in which the tests are performed.
- Create a file named as `start-coverage.php` and create an instance of `CoverageHandler` in this file.
- This file must be injected using `auto_prepend_file`. This can be done by adding the following line into the `.htaccess` file of the web site on which the UI tests are performed:

```
php_value auto_prepend_file "/path/to/start-coverage.php"
```

After these steps are done, code coverage data will be dumped to the defined directory when the code coverage hint is detected.

An example `start-coverage.php` might be the following
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(60);

$appPath = '/path/to/app/';
require_once $appPath . 'vendor/autoload.php';

new CoverageHandler(
    CoverageHandler::COVERAGE_TYPE_XDEBUG,
    'coverageStartHintKey',
    [
        $appPath,
    ],
    [
        $appPath . '/vendor',
        $appPath . '/storage',
        $appPath . '/views',
    ],
    __DIR__ . "/coverages"
);
```

_Based on [https://tarunlalwani.com/post/php-code-coverage-web-selenium/]() Visit this page for more information._

## ReportGenerator
This class generates HTML and Clover reports from the coverage data dumped by `CoverageHandler`. To use this, create a file named as `generate-report.php` that creates an instance of `ReportGenerator` and calls `generate()` method.

An example `generate-report.php` file might be the following:
```php
$appPath = '/path/to/app/';
require_once("$appPath/vendor/autoload.php");

$generator = new ReportGenerator(
    true,
    true,
    [
        $appPath,
    ],
    [
        $appPath . '/vendor',
        $appPath . '/storage',
        $appPath . '/views',
    ],
    'ui-test-report',
    __DIR__ . "/reports",
    __DIR__ . "/coverages",
    'Europe/Istanbul'
);

$generator->generate();
```

Next, to generate a report, just run the following command in the terminal
    
    php /path/to/generate-report.php
    
After running this, the specified reports will be generated and saved into the specified directory.

To learn what parameters are provided in the constructor methods, simply refer to phpDoc.

# WebDriver Package
This package requires [`facebook/webdriver`](https://github.com/facebook/php-webdriver). Basically, this should be used for Selenium tests. This package provides an `AbstractDriverManager` that handles loading different URLs in different tabs, switching to a tab if a URL is already loaded in a tab, adding a parameter for each URL to hint code coverage (the same hint explained in `CoverageHandler`), modifying `window.ajaxurl` JavaScript variable (which is the default variable for WordPress sites) to enable code coverage for AJAX requests, closing excessive browser tabs, setting up the driver and logging into the site-under-test, and other things like refreshing, closing, opening tabs.

To use this package, create a class that extends `AbstractDriverManager`, implement the required methods (or use `DefaultDriverManager`). `AbstractDriverManager` requires an `AbstractSetupStrategy` that will setup the driver and login to the site. To provide a strategy, simply create a class and extend it to `AbstractSetupStrategy`, and implement the required methods. The package comes with `WordPressSetupStrategy`. If you are testing a WordPress site, you can directly use it.

In your tests, instead of using the webdriver directly, perform every driver action through an `AbstractDriverManager`. Otherwise, there is no point using a driver manager.

# TODO
- Write tests
