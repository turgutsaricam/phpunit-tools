<?php
/**
 * Created by PhpStorm.
 * User: turgutsaricam
 * Date: 23.08.2019
 * Time: 16:09
 */

namespace TurgutSaricam\PHPUnitToolsTest;


class TestUtils {

    /**
     * @return string Absolute path of the project directory without a slash in the end.
     */
    public static function getProjectDirPath() {
        return '/usr/src/phpunit-tools';
    }

    /**
     * @return string Absolute path of the tests directory
     */
    public static function getTestsDirPath() {
        return static::getProjectDirPath() . '/tests';
    }

}