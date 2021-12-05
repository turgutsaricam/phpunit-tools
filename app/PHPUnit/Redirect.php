<?php
/**
 * Created by PhpStorm.
 * User: turgutsaricam
 * Date: 25.07.2019
 * Time: 16:26
 */

namespace TurgutSaricam\PHPUnitTools\PHPUnit;


/**
 *      _WHY DOES THIS CLASS EXIST?_
 *
 * Consider the following case. There are two different tests, namely regular plugin tests and user interface tests.
 * These tests require different versions of PHPUnit and different PHPUnit configurations. Selenium Webdriver supports
 * PHPUnit 5.x, while WordPress supports PHPUnit 7.x. PHPStorm does not have an option to define different PHPUnit
 * executables for different test directories. Yet, we want to use different PHPUnit versions.
 *
 * This file looks for the path of the test files and redirects the command to the correct PHPUnit version by injecting
 * the correct configuration file path. In short, this is just a wrapper that redirects PHPUnit commands to the correct
 * PHPUnit executables.
 *
 *
 *      _HOW TO USE THIS CLASS_
 *
 *  - Create a file named as "phpunit"
 *  - Make sure this file's first line is "#!/usr/bin/env php"
 *  - Make sure this file's *absolute* path is entered as the value of "Preferences > Languages & Frameworks > PHP >
 *      Test Frameworks > (select or create a test framework) > Path to phpunit.phar".
 *  - Make sure the value of "Preferences > Languages & Frameworks > PHP > Test Frameworks > (previously set framework) >
 *      Default configuration file" is the same as {@link configPathDefault} (i.e. absolute path of
 *      *$relativeDefaultConfigXmlPath*)
 *  - Create an instance of this class in the created file and call redirect() method
 *
 * After these are correctly set, you can use PHPStorm's buttons (run, debug, run with coverage) to run the tests.
 */
class Redirect {

    /** @var array Stores {@link $_SERVER}['argv'] */
    private $args = [];

    /**
     * @var string Absolute root directory of the plugin/app/product, ending with directory separator. This will be
     *             used to create absolute paths. This is just for writing other paths relatively, for convenience.
     */
    private $baseDir;

    /** @var string Absolute path of default PHPUnit */
    private $phpunitPathDefault;
    /** @var string Absolute path of default phpunit.xml file */
    private $configPathDefault;

    /** @var string Absolute path of PHPUnit that will be used for UI tests */
    private $phpunitPathUi;
    /** @var string Absolute path of phpunit.xml file that will be used for UI tests */
    private $configPathUi;

    /** @var string Absolute path of the directory that stores the unit tests. */
    private $unitTestsDirPath;
    /** @var string Absolute path of the directory that stores the user interface tests. */
    private $uiTestsDirPath;

    /**
     * @var string A string that will be used to define the coverage status in {@link $_SERVER}, i.e. Value of
     *      $_SERVER[{@link keyForHintingUiCoverageStatus}] will be set to 1, if the original command enables coverage.
     *      Otherwise, the key will not be assigned to {@link $_SERVER}. You can use this value in your tests to
     *      understand if the tests should be run with coverage and make your changes accordingly.
     */
    private $keyForHintingUiCoverageStatus;

    /** @var bool True if the command is run for testing. */
    private $isForTest = false;
    /** @var bool True if the command is run for testing UI. */
    private $isUiTest = false;

    /** @var null|string phpunit.xml path to be used, decided by analyzing the command */
    private $finalConfigPath = null;
    /** @var null|string phpunit path to be used, decided by analyzing the command */
    private $finalPhpunitPath = null;

    /** @var null|array Stores the parameters of the command */
    private $cmdParts = null;

    /**
     * @param string $baseDirPath                   See {@link baseDir}
     * @param string $relativeDefaultConfigXmlPath  Default (for unit tests) phpunit.xml path relative to
     *                                              {@link baseDir}
     * @param string $relativeDefaultPhpunitPath    Default (for unit tests) phpunit file path relative to
     *                                              {@link baseDir}
     * @param string $relativeUiConfigXmlPath       UI (for user interface tests) phpunit.xml path relative to
     *                                              {@link baseDir}
     * @param string $relativeUiPhpunitPath         UI (for user interface tests) phpunit file path relative to
     *                                              {@link baseDir}
     * @param string $keyForHintingUiCoverageStatus See {@link keyForHintingUiCoverageStatus}
     * @param string $relativeUnitTestsDirPath      Path of the directory that stores the unit tests, relative to
     *                                              {@link baseDir}
     * @param string $relativeUiTestsDirPath        Path of the directory that stores the user interface tests,
     *                                              relative to {@link baseDir}
     */
    public function __construct($baseDirPath,
                                $relativeUnitTestsDirPath, $relativeDefaultConfigXmlPath, $relativeDefaultPhpunitPath,
                                $relativeUiTestsDirPath, $relativeUiConfigXmlPath, $relativeUiPhpunitPath,
                                $keyForHintingUiCoverageStatus) {

        $this->args = $_SERVER['argv'];
        $this->baseDir = $baseDirPath;

        $this->configPathDefault    = $this->makeAbsolutePath($relativeDefaultConfigXmlPath);
        $this->phpunitPathDefault   = $this->makeAbsolutePath($relativeDefaultPhpunitPath);

        $this->configPathUi         = $this->makeAbsolutePath($relativeUiConfigXmlPath);
        $this->phpunitPathUi        = $this->makeAbsolutePath($relativeUiPhpunitPath);

        $this->unitTestsDirPath     = $this->makeAbsolutePath($relativeUnitTestsDirPath);
        $this->uiTestsDirPath       = $this->makeAbsolutePath($relativeUiTestsDirPath);

        $this->keyForHintingUiCoverageStatus = $keyForHintingUiCoverageStatus;
    }

    /**
     * Analyzes the existing command and makes the suitable PHPUnit handle it.
     */
    public function redirect() {
        $this->decideTestType()
            ->handleIfNotForTesting()
            ->decidePhpunitAndConfigPaths()
            ->setModifiedCommandParts()
            ->setUiTestCoverageHint()
            ->printModifiedCommand()
            ->runNewCommand();
    }

    /*
     * PRIVATE METHODS
     */

    /**
     * Override the argv of $_SERVER so that PHPUnit does its job using the modified arguments. Then, require the
     * suitable PHPUnit and let it handle the rest.
     *
     * @return $this
     */
    private function runNewCommand() {
        $_SERVER['argv'] = array_values($this->cmdParts);

        // The original phpunit file's first two lines are:
        //      #!/usr/bin/env php
        //      <?php declare(strict_types=1);
        // Because the "declare" call is in the second line, we cannot directly include this file. It must be in the
        // first line. Here, we remove the first line from the phpunit file so that the "declare" call is in the first
        // line. Then, we save this modified file under another name and include it. This is just a workaround.
        $phpUnitFileContents = file_get_contents($this->finalPhpunitPath);
        $phpUnitFileContents = str_replace("#!/usr/bin/env php\n", '', $phpUnitFileContents);
        $preparedPhpUnitPath = $this->finalPhpunitPath . '.prepared';
        file_put_contents($preparedPhpUnitPath, $phpUnitFileContents);

        require_once $preparedPhpUnitPath;

        return $this;
    }

    /**
     * Prints out the modified command to the terminal
     *
     * @return $this
     */
    private function printModifiedCommand() {
        echo "Modified command is\n'" . join(' ', $this->cmdParts) . "'\n";

        return $this;
    }

    /**
     * If this is for UI tests and a coverage report is requested by IDE, hint the test suite about this. We will not
     * use PHPUnit's coverage since this is a UI test. We use our custom coverage generation process.
     *
     * @return $this
     */
    private function setUiTestCoverageHint() {

        if ($this->isUiTest && ($index = array_search('--coverage-clover', $this->cmdParts)) !== false) {
            // Get the index of the command
            unset($this->cmdParts[$index + 1]);
            unset($this->cmdParts[$index]);

            // Hint the test suite by inserting a variable
            $_SERVER[$this->keyForHintingUiCoverageStatus] = 1;
        }

        return $this;
    }

    /**
     * Modify the command parts to create a test-running command.
     *
     * @return $this
     */
    private function setModifiedCommandParts() {
        $thisPath = $this->args[0];

        $this->cmdParts = array_map(function($v) use (&$thisPath) {

            // Change phpunit executable path
            if ($v == $thisPath) {
                $v = $this->finalPhpunitPath;

                // Change test configuration file path
            } else if ($v == $this->configPathDefault) {
                $v = $this->finalConfigPath;
            }

            if ($this->isUiTest) {
                // Remove the command starting with --cache-result-file for UI tests since PHPUnit version of UI tests
                // does not support this command, resulting in an error.
                if (strpos($v, '--cache-result-file') === 0) {
                    $v = null;
                }
            }

            return $v;

        }, $this->args);

        return $this;
    }

    /**
     * If it is for testing the user interface, change config and PHPUnit executable paths accordingly, since Selenium
     * tests require a different PHPUnit version.
     *
     * @return $this
     */
    private function decidePhpunitAndConfigPaths() {

        if ($this->isUiTest) {
            $this->finalPhpunitPath    = $this->phpunitPathUi;
            $this->finalConfigPath     = $this->configPathUi;

            echo "UI testing...\n";

        } else {
            $this->finalPhpunitPath    = $this->phpunitPathDefault;
            $this->finalConfigPath     = $this->configPathDefault;

            echo "Unit testing...\n";
        }

        return $this;
    }

    /**
     * If the command is not executed for running a test, then run the command with the regular PHPUnit executable and exit.
     *
     * @return $this
     */
    private function handleIfNotForTesting() {
        // If the command is run for running a test, do nothing.
        if ($this->isForTest) return $this;

        // The command is NOT run for running a test. For example, PHPStorm might be checking if the command works
        // properly. If that is the case, just run the command with the default phpunit, return what it returns, and
        // stop.
        $cmd = $this->phpunitPathDefault . ' ' . implode(' ', array_slice($this->args, 1));
        exit(shell_exec($cmd));
    }

    /**
     * Finds out if the command is for running a test and, if so, if it is for UI tests.
     *
     * @return $this
     */
    private function decideTestType() {
        // Find out if the command is for running a test and, if so, if it is for UI tests.
        foreach($this->args as $arg) {
            // If any of the args starts with path of one of the test directories, then this command is for testing.
            if (!$this->isForTest && (
                    $this->startsWith($arg, $this->uiTestsDirPath) || $this->startsWith($arg, $this->unitTestsDirPath)
                )) {
                $this->isForTest = true;
            }

            // If any of the args starts with the UI test directory path, then this command is for running UI tests.
            if ($this->startsWith($arg, $this->uiTestsDirPath)) {
                $this->isUiTest = true;
                break;
            }
        }

        return $this;
    }

    /*
     *
     */

    /**
     * Makes a path that is relative to {@link baseDir} an absolute path.
     *
     * @param string $pathRelativeToBaseDir A path that is relative to {@link baseDir}
     * @return bool|string Absolute path
     */
    private function makeAbsolutePath($pathRelativeToBaseDir) {
        return realpath($this->baseDir . $pathRelativeToBaseDir);
    }

    /**
     * Tests if the $haystack starts with $needle
     *
     * @param string $haystack
     * @param string $needle
     * @return bool True if the haystack starts with the needle.
     */
    private function startsWith($haystack, $needle) {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }

}