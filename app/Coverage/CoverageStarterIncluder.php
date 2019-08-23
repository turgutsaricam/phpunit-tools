<?php


namespace TurgutSaricam\PHPUnitTools\Coverage;


use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class CoverageStarterIncluder {

    /**
     * @var string Path of the directory in which {@link includeFileName}s should be searched for. Keeping this as
     *      specific as possible improves the performance.
     */
    private $rootDirPath;

    /**
     * @var string[] An array of path parts that will not be searched for {@link $includeFileName}. For example, if
     *               'vendor' and 'node_modules' directories should not be searched for, ['/vendor', '/node_modules']
     *               can be provided. This also matches directories with names like 'vendor_dir' or 'node_modules_custom'.
     *               If a path contains any of the strings given in this array, that directory will not be searched for.
     */
    private $excludedDirParts = [];

    /**
     * @var string Name of the file that will be included. The files that are included must return a coverage handler
     *      instance, to keep a reference to them. This is mandatory. Otherwise, things do not work as intended. For
     *      example, there will not be any coverage results if they are not returned. An example of a file name could
     *      be 'coverage-starter.php'
     */
    private $includeFileName;

    /**
     * @var CoverageHandler[] Stores {@link CoverageHandler} instances returned by the included files. They are stored
     *                        in an instance variable, because we want to keep a reference to them, in order not to make
     *                        PHP call their __destruct methods prematurely.
     */
    private $dumpers = [];

    /** @var string[]|null Paths of the files that should be included. */
    private $filePaths = null;

    /**
     * @var string The file that contains the list of to-be-included file paths. The includer will cache the results
     *      into this file in order not to search for the files again and again.
     */
    private $cacheFilePath;

    /** @var bool True if the file paths are retrieved from cache. */
    private $fromCache = false;

    /**
     * CoverageStarterIncluder constructor.
     *
     * @param string     $rootDirPath
     * @param string[]   $excludedDirParts
     * @param string     $includeFileName
     * @param null|array $filePaths Absolute paths of the files that should be included. If this is null, the files
     *                              will be either searched for or read from cache.
     */
    public function __construct(string $rootDirPath, array $excludedDirParts = [],
                                string $includeFileName = 'start-coverage.php', ?array $filePaths = null) {
        $this->rootDirPath      = $rootDirPath;
        $this->excludedDirParts = $excludedDirParts;
        $this->includeFileName  = $includeFileName;
        $this->filePaths        = $filePaths;
    }

    /**
     * Finds the files that should be included and includes them.
     */
    public function includeFiles() {
        $filePaths = $this->getFilePaths();

        // We have to retrieve the coverage handlers and store them to keep a reference to the variables so that their
        // destruct methods are not called prematurely.
        foreach ($filePaths as $path) {
            $this->dumpers[] = include_once($path);
        }

        // If the files were not retrieved from the cache, cache the file paths.
        if (!$this->fromCache) {
            $this->cacheFilePaths();
        }
    }

    /*
     * PRIVATE METHODS
     */

    /**
     * Create the directory iterator that finds the files that should be included.
     *
     * @return RecursiveCallbackFilterIterator
     */
    private function createIterator() {
        $dirIterator = new RecursiveDirectoryIterator($this->rootDirPath);

        $iterator = new RecursiveCallbackFilterIterator($dirIterator, function ($current, $key, $iterator) {
            /** @var $current SplFileInfo */
            /** @var $iterator RecursiveDirectoryIterator */
            return $this->isValid($current, $key, $iterator);
        });

        return $iterator;
    }

    /**
     * This method is the callback that is given as parameter to {@link RecursiveCallbackFilterIterator::__construct()}.
     *
     * @param SplFileInfo                $current Current file/directory
     * @param string                     $key
     * @param RecursiveDirectoryIterator $iterator The iterator
     * @return bool
     */
    private function isValid($current, $key, $iterator) {
        // Allow recursion
        if ($iterator->hasChildren()) {
            return $this->isValidDirectoryPath($current);
        }

        // Check for the coverage file
        if ($current->isFile()) {
            return $this->isValidFile($current);
        }

        return false;
    }

    /**
     * Determines if a directory should be searched for the files.
     *
     * @param SplFileInfo $file
     * @return bool True if this is a directory that should be searched for the files. Otherwise, false.
     */
    private function isValidDirectoryPath($file): bool {
        $path = $file->getPathname();

        // If this directory should be excluded, return false.
        foreach($this->excludedDirParts as $excludedDirPart) {
            if (strpos($path, $excludedDirPart) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param SplFileInfo $file
     * @return bool True if the file should be included. Otherwise, false.
     */
    private function isValidFile($file): bool {
        return $file->getFilename() == $this->includeFileName;
    }

    /**
     * Get the file paths from cache, if it exists.
     *
     * @return string[]|null Null, if the cache file does not exist. Otherwise, a string array.
     */
    private function getFilePathsFromCache() {
        $cacheFilePath = $this->getCacheFilePath();

        // If no cache file, return null.
        if (!file_exists($cacheFilePath)) return null;

        $this->fromCache = true;

        $contents = file_get_contents($cacheFilePath);
        $paths = explode("\n", $contents);

        // Remove empty values
        return array_filter($paths);
    }

    /**
     * Caches the file paths returned by {@link getFilePaths()}
     */
    private function cacheFilePaths() {
        // If there is no cache file path specified, specify one.
        if (!$this->getCacheFilePath()) {
            $this->setCacheFilePath(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'include-files.txt');
        }

        $contents = implode("\n", $this->getFilePaths());
        file_put_contents($this->getCacheFilePath(), $contents);
    }

    /*
     * GETTERS AND SETTERS
     */

    /**
     * @return string
     */
    public function getRootDirPath(): string {
        return $this->rootDirPath;
    }

    /**
     * @return string[]
     */
    public function getExcludedDirParts(): array {
        return $this->excludedDirParts;
    }

    /**
     * @return string
     */
    public function getIncludeFileName(): string {
        return $this->includeFileName;
    }

    /**
     * @return CoverageHandler[]
     */
    public function getDumpers(): array {
        return $this->dumpers;
    }

    /**
     * If {@link $filePaths} is null, finds the paths of the files to be included and assigns them to {@link $filePaths}.
     *
     * @return string[] Paths of the files to be included ({@link $filePaths})
     */
    public function getFilePaths(): array {
        // If there are file paths, return them instead of searching for the files.
        if ($this->filePaths !== null) return $this->filePaths;

        $this->filePaths = $this->getFilePathsFromCache();
        if ($this->filePaths !== null) return $this->filePaths;

        $this->filePaths = [];
        $iterator = $this->createIterator();

        // We have to retrieve the coverage handlers and store them to keep a reference to the variables so that their
        // destruct methods are not called prematurely.
        foreach (new RecursiveIteratorIterator($iterator) as $file) {
            /** @var $file SplFileInfo */
            $this->filePaths[] = $file->getPathname();
        }

        return $this->filePaths;
    }

    /**
     * @return string|null
     */
    public function getCacheFilePath(): ?string {
        return $this->cacheFilePath;
    }

    /**
     * @param string $cacheFilePath
     * @return $this
     */
    public function setCacheFilePath(string $cacheFilePath) {
        $this->cacheFilePath = $cacheFilePath;

        $dir = dirname($this->cacheFilePath);
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function isFromCache(): bool {
        return $this->fromCache;
    }

}