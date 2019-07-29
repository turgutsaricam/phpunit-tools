<?php
/**
 * Created by PhpStorm.
 * User: turgutsaricam
 * Date: 25.07.2019
 * Time: 15:31
 */

namespace TurgutSaricam\PHPUnitTools\Coverage;


use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Report\PHP;

/**
 * Enables code coverage if a test name is provided as a parameter of POST, GET or COOKIE. See {@link initTestName()}
 * for more information about how the test name is retrieved.
 *
 * NOTE THESE:
 *      - Xdebug extension of PHP must be enabled in the server of the web site in which the tests are performed.
 *      - This file must be injected using auto_prepend_file. This can be done by adding the following line into the
 *        .htaccess file of the web site:
 *
 *        php_value auto_prepend_file
 *        "/var/www/html/wp-content/plugins/wp-content-crawler/tests/tests-ui/coverage/start-coverage.php"
 *
 * Based on https://tarunlalwani.com/post/php-code-coverage-web-selenium/ Visit this page for more information.
 */
class CoverageHandler {

    const COVERAGE_TYPE_XDEBUG          = 'xdebug';
    const COVERAGE_TYPE_CODE_COVERAGE   = 'code-coverage';

    /**
     * @var string One of the constants starting with "COVERAGE_TYPE_" E.g. {@link
     *      CoverageHandler::COVERAGE_TYPE_XDEBUG} Note that {@link CoverageHandler::COVERAGE_TYPE_CODE_COVERAGE} takes
     *      too long to generate. We are talking about around 10 times slower a process.
     */
    private $coverageType;

    /**
     * @var string The key that stores the test name. See {@link initTestName()} to learn how the key is utilized to
     *      determine the test name.
     */
    private $testNameKey;

    /**
     * @var array The paths that will be whitelisted for Xdebug. Code analysis will be performed just for the files
     *      under these paths. The paths should be absolute.
     */
    private $whitelistPaths = [];

    /**
     * @var array The paths that exist inside {@link $whitelistPaths} but should not be covered. These will be used only
     *            if the coverage type is {@link CoverageHandler::COVERAGE_TYPE_CODE_COVERAGE}. The paths should be
     *            absolute.
     */
    private $excludedWhitelistPaths = [];

    /**
     * @var string Absolute path of the directory into which the coverage dumps will be saved. The path does not end
     *             with the directory separator.
     */
    private $coverageDumpDirPath;

    /*
     *
     */

    /**
     * @var string Name of the test that will be used in the coverage file's name.
     */
    private $testName = null;

    /** @var bool True if code coverage is enabled. Otherwise, false. */
    private $coverageEnabled = false;

    /** @var CodeCoverage */
    private $codeCoverage;

    /** @var bool True if debug mode is on. When this is true, debug messages will be written to error log. */
    private $debug;

    /**
     * @param string $coverageType           See {@link $coverageType}
     * @param string $testNameKey            See {@link $testNameKey}
     * @param array  $whitelistPaths         See {@link $whitelistPaths}
     * @param array  $excludedWhitelistPaths See {@link $excludedWhitelistPaths}
     * @param string $coverageDumpDirPath    See {@link $coverageDumpDirPath}
     * @param bool   $debug                  See {@link $debug}
     */
    public function __construct($coverageType, $testNameKey, $whitelistPaths, $excludedWhitelistPaths,
                                $coverageDumpDirPath, $debug = false) {
        $this->coverageType         = $coverageType;
        $this->testNameKey          = $testNameKey;
        $this->coverageDumpDirPath  = rtrim($coverageDumpDirPath, DIRECTORY_SEPARATOR);

        // Set whitelisted and excluded paths.
        $this->whitelistPaths           = $whitelistPaths;
        $this->excludedWhitelistPaths   = $excludedWhitelistPaths;

        $this->debug = $debug;

        /*
         *
         */

        // Initialize the test name
        $this->initTestName();

        // If there is no test name, we will not perform a coverage analysis.
        $this->coverageEnabled = !!$this->testName;

        // Start the coverage.
        $this->maybeStartCoverage();
    }

    /**
     * 
     */
    public function __destruct() {
        $this->debugMessage("__destruct() is called.");
        try {
            // End the coverage.
            $this->maybeEndCoverage();

        } catch (\Exception $ex) {
            $this->debugMessage((string) $ex);
        }
    }

    /*
     * PRIVATE HELPERS
     */

    /**
     * Starts collecting coverage data if {@link $coverageEnabled} is true.
     */
    private function maybeStartCoverage() {
        $this->debugMessage("maybeStartCoverage() is called.");

        if (!$this->coverageEnabled) return;

        $this->debugMessage("Coverage is enabled. Proceeding with starting the coverage...");

        if ($this->coverageType === static::COVERAGE_TYPE_XDEBUG) {
            $this->debugMessage("Starting Xdebug code coverage...");

            // Set whitelist filter for Xdebug if a whitelist is provided. Note that providing both a whitelist and a
            // blacklist is not possible in Xdebug. See: https://xdebug.org/docs/code_coverage
            if ($this->whitelistPaths) {
                xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_WHITELIST, $this->whitelistPaths);
            }

            xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);

        } else if ($this->coverageType === static::COVERAGE_TYPE_CODE_COVERAGE) {
            $this->debugMessage("Starting PHP code coverage...");

            $this->codeCoverage = new CodeCoverage();
            foreach ($this->whitelistPaths as $directoryPath) {
                $this->codeCoverage->filter()->addDirectoryToWhitelist($directoryPath);
            }

            foreach ($this->excludedWhitelistPaths as $directoryPath) {
                $this->codeCoverage->filter()->removeDirectoryFromWhitelist($directoryPath);
            }

            $this->codeCoverage->start($this->testName);
        }

    }

    /**
     * Collects coverage data and dumps it if {@link $coverageEnabled} is true.
     */
    private function maybeEndCoverage() {
        $this->debugMessage("maybeEndCoverage() is called.");
        if (!$this->coverageEnabled) return;

        $this->debugMessage("Coverage was enabled. Proceeding with dumping the coverage into a file...");

        try {
            if ($this->coverageType === static::COVERAGE_TYPE_XDEBUG) {
                $this->debugMessage("Getting Xdebug coverage data...");

                xdebug_stop_code_coverage(false);
                $coverageData = xdebug_get_code_coverage();

                // If there is no coverage data, no need to dump anything.
                if (!$coverageData) return;

                $this->debugMessage("There is coverage data. Dumping it...");

                $this->dumpCoverage('json', json_encode($coverageData));

            } else if ($this->coverageType === static::COVERAGE_TYPE_CODE_COVERAGE) {
                $this->debugMessage("Getting PHP coverage data...");

                $this->codeCoverage->stop();
                $writer = new PHP();

                $coverageFilePath = $this->getCoverageFilePath('php');
                $this->debugMessage("Dumping to {$coverageFilePath}...");

                $writer->process($this->codeCoverage, $coverageFilePath);
                $this->debugMessage("Dumped.");
            }

        } catch (\Exception $ex) {
            $this->debugMessage("There was an error. Dumping the error into a file...");
            $this->dumpCoverage('ex', $ex);
        }
    }

    /**
     * @param string $extension See {@link getCoverageFilePath()}
     * @param mixed  $data      Data to be written into the coverage file
     */
    private function dumpCoverage(string $extension, $data) {
        // Get the dump file path
        $path = $this->getCoverageFilePath($extension);

        // Make sure all the directories exist
        @mkdir(dirname($path), 0777, true);

        // Write the coverage data into the coverage file.
        file_put_contents($path, $data);
    }

    /**
     * @param string $extension Extension of the coverage file, e.g. "json"
     * @return string Full path of the coverage file
     */
    private function getCoverageFilePath(string $extension): string {
        $time = microtime(true);
        return $this->coverageDumpDirPath . "/coverage-{$this->testName}-{$time}.{$extension}";
    }

    /**
     * Initializes {@link $testName}. This method searches for the test name in {@link $_GET}, {@link $_POST}, and
     * {@link $_COOKIE}, respectively. See {@link getTestName()} for more information.
     */
    private function initTestName() {
        $dataItems = [$_GET, $_POST, $_COOKIE];

        // Search for the test name
        foreach ($dataItems as $data) {
            $this->testName = $this->getTestName($data);

            // If a valid test name is found, stop.
            if ($this->testName) break;
        }
    }

    /**
     * Get the test name from an array. The test name must exist under {@link $testNameKey} key.
     *
     * @param array $data The data in which the test name will be searched for.
     * @return string|null If there is a test name in the data, the test name as a string. Otherwise, null.
     */
    private function getTestName(array $data) {
        $key = $this->testNameKey;
        return isset($data[$key]) && $data[$key] ? (string)$data[$key] : null;
    }

    /**
     * Writes the given message via {@link error_log()}
     * @param string $message
     */
    private function debugMessage(string $message) {
        if (!$this->debug) return;
        error_log(sprintf('CoverageHandler: "%1$s" - %2$s', $this->testName, $message));
    }
}