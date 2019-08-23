<?php
/**
 * Created by PhpStorm.
 * User: turgutsaricam
 * Date: 23.08.2019
 * Time: 16:03
 */

namespace TurgutSaricam\PHPUnitToolsTest\Coverage;

use TurgutSaricam\PHPUnitTools\Coverage\CoverageStarterIncluder;
use TurgutSaricam\PHPUnitToolsTest\TestCase;
use TurgutSaricam\PHPUnitToolsTest\TestUtils;

class CoverageStarterIncluderTest extends TestCase {

    /*
     * SETUP
     */

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
    }

    protected function setUp(): void {
        parent::setUp();

        static::deleteCacheDir();
    }

    /*
     * TESTS
     */

    public function testThatCacheFileIsCreatedWhenFilePathIsSpecified() {
        $includer = new CoverageStarterIncluder($this->getSearchRootDirPath());
        $includer
            ->setCacheFilePath($this->getCacheFilePath())
            ->includeFiles();

        $cacheFile = $includer->getCacheFilePath();
        $this->assertTrue(file_exists($cacheFile));
    }

    public function testThatCacheFileContentIsCorrectWhenFilePathIsSpecified() {
        $includer = new CoverageStarterIncluder($this->getSearchRootDirPath());
        $includer
            ->setCacheFilePath($this->getCacheFilePath())
            ->includeFiles();

        $cacheFile = $includer->getCacheFilePath();
        $contents = file_get_contents($cacheFile);

        $file1 = $this->getSearchRootDirPath() . '/start-coverage.php';
        $file2 = $this->getSearchRootDirPath() . '/inner-dir/start-coverage.php';
        $expected = "{$file1}\n{$file2}";

        $this->assertSame($expected, $contents);
    }

    public function testThatCacheFileIsCreatedWhenFilePathIsNotSpecified() {
        $includer = new CoverageStarterIncluder($this->getSearchRootDirPath());
        $includer->includeFiles();

        $cacheFile = $includer->getCacheFilePath();
        $this->assertTrue(file_exists($cacheFile));

        @unlink($cacheFile);
    }

    public function testThatFilePathsAreRetrievedFromCacheFile() {
        $cacheFilePath = static::getCacheFilePath();

        $includer1 = new CoverageStarterIncluder($this->getSearchRootDirPath());
        $includer1->setCacheFilePath($cacheFilePath)->includeFiles();
        $fromCache1 = $includer1->isFromCache();
        $filePaths1 = $includer1->getFilePaths();

        $includer2 = new CoverageStarterIncluder($this->getSearchRootDirPath());
        $includer2->setCacheFilePath($cacheFilePath)->includeFiles();
        $fromCache2 = $includer2->isFromCache();
        $filePaths2 = $includer2->getFilePaths();

        $includer3 = new CoverageStarterIncluder($this->getSearchRootDirPath());
        $includer3->setCacheFilePath($cacheFilePath)->includeFiles();
        $fromCache3 = $includer3->isFromCache();
        $filePaths3 = $includer3->getFilePaths();

        $this->assertFalse($fromCache1);
        $this->assertTrue($fromCache2);
        $this->assertTrue($fromCache3);
        $this->assertSame($filePaths1, $filePaths2);
        $this->assertSame($filePaths2, $filePaths3);
    }

    /*
     * PRIVATE STATIC HELPERS
     */

    private static function deleteCacheDir() {
        @unlink(static::getCacheFilePath());

        if (file_exists(static::getCacheDirPath())) {
            rmdir(static::getCacheDirPath());
        }
    }

    private static function getSearchRootDirPath() {
        return TestUtils::getTestsDirPath() . '/files';
    }

    private static function getCacheFilePath() {
        return static::getCacheDirPath() . '/file-paths.txt';
    }

    private static function getCacheDirPath() {
        return TestUtils::getTestsDirPath() . '/files/cache';
    }
}