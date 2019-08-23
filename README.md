# phpunit-tools
This repository contains tools that are commonly needed to perform unit and UI tests using PHPUnit with code coverage.

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

An example can be observed in `examples/start-coverage.php`.

_Based on <https://tarunlalwani.com/post/php-code-coverage-web-selenium/> Visit this page for more information._

## CoverageStarterIncluder
This class finds the PHP files that should be included in `.htaccess` by setting `auto_prepend_file`. A single value can be provided as the value of `auto_prepend_file`. In case that a project contains multiple sub-projects whose `start-coverage.php` should be included in the main `.htaccess` file, a PHP file that has `include` statements in it should be created to prepend multiple PHP files. This class handles this case.

The class searches for PHP files that should be included and includes them by keeping a reference to them. By this way, `__destruct` methods of the `CoverageHandler` instances created in `start-coverage.php` files are not called prematurely. They will be called when the script exits. Therefore, coverage data can be collected and dumped properly.

It is enough to create a PHP file, create an instance of `CoverageStarterIncluder` in it, and call `includeFiles()` to include all `start-coverage.php` files in the project. See `examples/coverage-starter-includer.php` and `examples/.htaccess` for an example.

## ReportGenerator
This class generates HTML and Clover reports from the coverage data dumped by `CoverageHandler`. To use this, create a file named as `generate-report.php` that creates an instance of `ReportGenerator` and calls `generate()` method. An example can be seen in `examples/generate-report.php`. 

Next, to generate a report, just run the following command in the terminal
    
    php /path/to/generate-report.php
    
After running this, the specified reports will be generated and saved into the specified directory.

To learn what parameters are provided in the constructor methods, simply refer to phpDoc.

# PHPUnit Package
Contains the tools directly related to PHPUnit.

## Redirect
This class is responsible for redirecting a command that runs `phpunit` to correct PHPUnit runnable and modifying the command such that it contains the correct `phpunit.xml` file. This is done considering a case that there are unit and UI tests.

Consider the following case. There are two different tests, namely regular plugin tests and user interface tests. These tests require different versions of PHPUnit and different PHPUnit configurations. Selenium WebDriver supports PHPUnit 5.x, while WordPress supports PHPUnit 7.x. PHPStorm does not have an option to define different PHPUnit executables for different test directories. Yet, we want to use different PHPUnit versions.

This class looks for the path of the test files and redirects the command to the correct PHPUnit version by injecting the correct configuration file path. In short, this is just a wrapper that redirects PHPUnit commands to the correct PHPUnit executables.

### How to use this class

- Create a file named as `phpunit`
- Make sure this file's first line is `#!/usr/bin/env php`
- Make sure this file's *absolute* path is entered as the value of `Preferences > Languages & Frameworks > PHP > Test Frameworks > (select or create a test framework) > Path to phpunit.phar`.
- Make sure the value of `Preferences > Languages & Frameworks > PHP > Test Frameworks > (previously set framework) >
  Default configuration file` is the same as the value of `$configPathDefault` (i.e. absolute path of
  *`$relativeDefaultConfigXmlPath`*)
- Create an instance of this class in the created file and call `redirect()` method

After these are correctly set, you can use PHPStorm's buttons (run, debug, run with coverage) to run the tests.

An example `phpunit` file can be observed in `examples/phpunit`. Simply refer to the phpDoc to learn what the parameters are.

# TODO
- Write tests to cover all of the functionality provided by the classes
