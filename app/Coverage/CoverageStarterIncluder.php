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

    /**
     * CoverageStarterIncluder constructor.
     *
     * @param string   $rootDirPath
     * @param string[] $excludedDirParts
     * @param string   $includeFileName
     */
    public function __construct(string $rootDirPath, array $excludedDirParts = [],
                                string $includeFileName = 'start-coverage.php') {
        $this->rootDirPath      = $rootDirPath;
        $this->excludedDirParts = $excludedDirParts;
        $this->includeFileName  = $includeFileName;

        $this->includeFiles();
    }

    /**
     * Finds the files that should be included and includes them.
     */
    private function includeFiles() {
        $iterator = $this->createIterator();

        // We have to retrieve the coverage handlers and store them to keep a reference to the variables so that their
        // destruct methods are not called prematurely.
        foreach (new RecursiveIteratorIterator($iterator) as $file) {
            /** @var $file SplFileInfo */
            $this->dumpers[] = include_once($file->getPathname());
        }
    }

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
}