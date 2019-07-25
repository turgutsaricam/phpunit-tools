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

class ReportGenerator {

    /** @var bool True if an HTML report should be generated. */
    private $generateHtml = false;

    /** @var bool True if a Clover report should be generated. */
    private $generateClover = false;

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
     * @var string Absolute path of the directory into which the coverage dumps will be saved. The path does not end
     *             with the directory separator.
     */
    private $coverageDumpDirPath;

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
     * @param bool   $generateHtml           See {@link $generateHtml}
     * @param bool   $generateClover         See {@link $generateClover}
     * @param array  $whitelistPaths         See {@link $whitelistPaths}
     * @param array  $excludedWhitelistPaths See {@link $excludedWhitelistPaths}
     * @param string $id                     See {@link $id}
     * @param string $reportsDirectoryPath   See {@link $reportsDirectoryPath}
     * @param string $coverageDumpDirPath    See {@link $coverageDumpDirPath}
     * @param string $timeZone               See {@link $timeZone}
     * 
     */
    public function __construct($generateHtml, $generateClover, $whitelistPaths, $excludedWhitelistPaths,
                                $id, $reportsDirectoryPath, $coverageDumpDirPath, $timeZone = '') {

        $this->generateHtml = $generateHtml;
        $this->generateClover = $generateClover;
        $this->reportsDirectoryPath = rtrim($reportsDirectoryPath, DIRECTORY_SEPARATOR);
        $this->coverageDumpDirPath  = rtrim($coverageDumpDirPath, DIRECTORY_SEPARATOR);

        $this->id = $id;
        $this->timeZone = $timeZone;
        $this->whitelistPaths = $whitelistPaths;
        $this->excludedWhitelistPaths = $excludedWhitelistPaths;

        # Increase the memory in multiples of 128M in case of memory error
        ini_set('memory_limit', '12800M');
    }

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
        if ($this->generateClover)  $this->generateClover($dateStr);
    }

    /*
     * PRIVATE METHODS
     */

    /**
     * Generates Clover report from {@link $finalCoverage}
     *
     * @param string $dateStr The date that will be appended to the name of the report
     */
    private function generateClover($dateStr) {
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
            $paths = array_merge($paths, $this->getCoverFilePathsWithExt($ext));
        }

        return $paths;
    }

    /**
     * @param string $ext Extension of the needed coverage files.
     * @return array Absolute paths of the coverage dump files having the specified extension
     */
    private function getCoverFilePathsWithExt($ext) {
        return glob($this->coverageDumpDirPath . "/*.{$ext}") ?: [];
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