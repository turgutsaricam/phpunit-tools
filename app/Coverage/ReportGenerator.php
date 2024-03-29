<?php
/**
 * Created by PhpStorm.
 * User: turgutsaricam
 * Date: 25.07.2019
 * Time: 14:53
 */

namespace TurgutSaricam\PHPUnitTools\Coverage;


use DateTime;
use DateTimeZone;
use ReflectionException;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Report\Clover;
use SebastianBergmann\CodeCoverage\Report\Html\Facade;
use SebastianBergmann\CodeCoverage\Report\PHP;

class ReportGenerator {

    /** @var bool True if an HTML report should be generated. */
    private $generateHtml = false;

    /** @var bool True if a Clover report should be generated. */
    private $generateClover = false;

    /** @var bool True if a PHP report should be generated. */
    private $generatePHP = false;

    /**
     * @var array The paths that will be whitelisted for Xdebug. Code analysis will be performed just for the files
     *      under these paths. The paths should be absolute.
     */
    private $whitelistPaths = [];

    /**
     * @var array The paths that exist inside {@link $whitelistPaths} but should not be covered. The paths should be
     *            absolute.
     */
    private $excludedWhitelistPaths = [];

    /** @var string The ID that will be used in {@link CodeCoverage::start()} */
    private $id;

    /**
     * @var string Time zone string that will be used when retrieving the current time. See
     *      {@link DateTimeZone::__construct()}
     */
    private $timeZone;

    /**
     * @var string Path of the directory that will store the generated reports. The path does not end with the directory
     *      separator.
     */
    private $reportsDirectoryPath;

    /**
     * @var string|string[] Absolute path of the directory into which the coverage dumps will be saved. The path does
     *      not end with the directory separator. This can also be an array of paths in which coverage dump files exist.
     */
    private $coverageDumpDirPaths;

    /*
     *
     */

    const EXT_JSON  = 'json';
    const EXT_PHP   = 'php';

    /** @var CodeCoverage */
    private $finalCoverage;

    private $isInitialized = false;
    private $isPrepared = false;

    /**
     * @param array           $whitelistPaths         See {@link $whitelistPaths}
     * @param array           $excludedWhitelistPaths See {@link $excludedWhitelistPaths}
     * @param string          $id                     See {@link $id}
     * @param string          $reportsDirectoryPath   See {@link $reportsDirectoryPath}
     * @param string|string[] $coverageDumpDirPath    See {@link $coverageDumpDirPath}
     * @param string          $timeZone               See {@link $timeZone}
     */
    public function __construct($whitelistPaths, $excludedWhitelistPaths, $id, $reportsDirectoryPath,
                                $coverageDumpDirPath, $timeZone = '') {

        $this->reportsDirectoryPath = rtrim($reportsDirectoryPath, DIRECTORY_SEPARATOR);
        $this->coverageDumpDirPaths = is_array($coverageDumpDirPath) ? $coverageDumpDirPath : [$coverageDumpDirPath];

        $this->id = $id;
        $this->timeZone = $timeZone;
        $this->whitelistPaths = $whitelistPaths;
        $this->excludedWhitelistPaths = $excludedWhitelistPaths;

        # Increase the memory in multiples of 128M in case of memory error
        ini_set('memory_limit', '12800M');
    }

    /*
     * PUBLIC HELPERS
     */

    /**
     * Generates the specified reports from the coverage dumps.
     *
     * @throws ReflectionException
     */
    public function generate() {
        // Prepare the fina coverage
        $this->prepareFinalCoverage();

        // Generate the reports
        $dateStr = $this->getCurrentDateString();
        if ($this->generateHtml)    $this->generateHtmlReport($dateStr);
        if ($this->generateClover)  $this->generateCloverReport($dateStr);
        if ($this->generatePHP)     $this->generatePHPReport($dateStr);
    }

    /*
     * SETTERS
     */

    /**
     * @param bool $generateHtml See {@link $generateHtml}
     * @return ReportGenerator
     */
    public function setGenerateHtml(bool $generateHtml) {
        $this->generateHtml = $generateHtml;
        return $this;
    }

    /**
     * @param bool $generateClover See {@link $generateClover}
     * @return ReportGenerator
     */
    public function setGenerateClover(bool $generateClover) {
        $this->generateClover = $generateClover;
        return $this;
    }

    /**
     * @param bool $generatePHP See {@link $generatePHP}
     * @return ReportGenerator
     */
    public function setGeneratePHP(bool $generatePHP) {
        $this->generatePHP = $generatePHP;
        return $this;
    }

    /*
     * PRIVATE METHODS
     */

    /**
     * Generates PHP report from {@link $finalCoverage}
     *
     * @param string $dateStr The date that will be appended to the name of the report
     */
    private function generatePHPReport($dateStr) {
        $this->inform("Generating final report in PHP ({$dateStr})...");

        $writer = new PHP();
        $writer->process($this->finalCoverage, $this->reportsDirectoryPath . "/php-{$dateStr}.php");

        $this->inform("PHP report generated successfully.");
    }

    /**
     * Generates Clover report from {@link $finalCoverage}
     *
     * @param string $dateStr The date that will be appended to the name of the report
     */
    private function generateCloverReport($dateStr) {
        $this->inform("Generating final report in Clover ({$dateStr})...");

        $writer = new Clover();
        $writer->process($this->finalCoverage, $this->reportsDirectoryPath . "/clover-{$dateStr}.xml");

        $this->inform("Clover report generated successfully.");
    }

    /**
     * Generates HTML report from {@link $finalCoverage}
     *
     * @param string $dateStr The date that will be used as the directory name for the report
     */
    private function generateHtmlReport($dateStr) {
        $this->inform("Generating final report in HTML ({$dateStr})...");

        $report = new Facade();
        $report->process($this->finalCoverage, $this->reportsDirectoryPath . "/html/{$dateStr}");

        $this->inform("HTML report generated successfully.");
    }

    /**
     * Prepares the final coverage by appending the dumped coverage files.
     *
     * @throws ReflectionException
     */
    private function prepareFinalCoverage() {
        if ($this->isPrepared) return;
        $this->isPrepared = true;

        // Initialize
        $this->initFinalCoverage();

        $this->inform("Preparing final coverage...");

        // Call start and stop method so that $finalCoverage->initializeData() method is called. That method is private and
        // we cannot call it outside of its class. So, this is just a workaround. See the following issue for more information:
        //      https://github.com/sebastianbergmann/php-code-coverage/issues/685
        $this->finalCoverage->start($this->id);
        $this->finalCoverage->stop();

        $coverages = $this->getCoverageFilePaths();
        $count = count($coverages);
        $i = 0;

        foreach ($coverages as $coverageFile) {
            $i++;
            $this->inform("Processing coverage ($i/$count) from $coverageFile", false);

            $coverageFileExtension = strtolower(pathinfo($coverageFile, PATHINFO_EXTENSION));
            $testName = str_ireplace("coverage-", "", pathinfo($coverageFile, PATHINFO_FILENAME));

            // Handle JSON dumps
            if ($coverageFileExtension === 'json') {
                $codeCoverageData = json_decode(file_get_contents($coverageFile), JSON_OBJECT_AS_ARRAY);
                $this->finalCoverage->append($codeCoverageData, $testName);

                $this->inform(" - Done.", true, false);

                // Handle PHP dumps
            } else if ($coverageFileExtension === 'php') {
                $coverage = include($coverageFile);
                $this->finalCoverage->merge($coverage);

                $this->inform(" - Done.", true, false);
            }

        }

        $this->inform("Final coverage has been prepared.");
    }

    /**
     * Initializes {@link $finalCoverage}
     */
    private function initFinalCoverage() {
        if ($this->isInitialized) return;
        $this->isInitialized = true;

        $this->inform("Initializing final coverage...");

        $this->finalCoverage = new CodeCoverage();

        $this->finalCoverage->setAddUncoveredFilesFromWhitelist(true);
        $this->finalCoverage->setProcessUncoveredFilesFromWhitelist(true);
        $this->finalCoverage->setCheckForUnexecutedCoveredCode(true);

        foreach ($this->whitelistPaths as $directoryPath) {
            $this->finalCoverage->filter()->addDirectoryToWhitelist($directoryPath);
        }

        foreach ($this->excludedWhitelistPaths as $directoryPath) {
            $this->finalCoverage->filter()->removeDirectoryFromWhitelist($directoryPath);
        }

        $this->inform("Final coverage has been initialized.");
    }

    /**
     * @return array Absolute paths of all the supported coverage dump files
     */
    private function getCoverageFilePaths() {
        $allExtensions = [
            static::EXT_JSON,
            static::EXT_PHP,
        ];

        $paths = [];
        foreach($allExtensions as $ext) {
            $paths = array_merge($paths, $this->getCoverageFilePathsWithExt($ext));
        }

        return $paths;
    }

    /**
     * @param string $ext Extension of the needed coverage files.
     * @return array Absolute paths of the coverage dump files having the specified extension
     */
    private function getCoverageFilePathsWithExt($ext) {
        // Create a glob pattern for each path.
        $patterns = array_map(function($path) use (&$ext) {
            $path = rtrim($path, DIRECTORY_SEPARATOR);
            return sprintf('%1$s/*.%2$s', $path, $ext);
        }, $this->coverageDumpDirPaths);

        // Create the final pattern that can be used to match files in all the directories in glob function.
        $finalPattern = sprintf('{%1$s}', implode(',', $patterns));

        return glob($finalPattern, GLOB_BRACE) ?: [];
    }

    /**
     * @return string Current date as a string in a format that will be used in file names.
     */
    private function getCurrentDateString() {
        return $this->getCurrentDateStringWithFormat('Y-m-d_H.i.s');
    }

    /**
     * Echoes the text with a new line in the end.
     *
     * @param string $text
     * @param bool   $appendNewLine True if a new line character should be appended.
     * @param bool   $prependDate True if current date should be prepended.
     */
    private function inform($text, $appendNewLine = true, $prependDate = true) {
        $msg = $text . ($appendNewLine ? "\n" : "");

        if ($prependDate) {
            $dateStr = $this->getCurrentDateStringWithFormat('Y-m-d H:i:s');
            $msg = $dateStr . " - " . $msg;
        }

        echo $msg;
    }

    /**
     * @param string $format See {@link DateTime::format()}
     * @return string Current date with the given format.
     */
    private function getCurrentDateStringWithFormat($format) {
        $dateTime = new DateTime('now', new DateTimeZone($this->timeZone));
        return $dateTime->format($format);
    }
}