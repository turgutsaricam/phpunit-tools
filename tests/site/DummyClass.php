<?php

namespace DummySite;

class DummyClass {

    private $dummyValue;

    /**
     * DummyClass constructor.
     */
    public function __construct() {
        $this->dummyValue = 3;
    }

    /**
     * @return int
     */
    public function getDummyValue(): int {
        return $this->dummyValue;
    }

    /**
     * @param int $dummyValue
     * @return DummyClass
     */
    public function setDummyValue(int $dummyValue): DummyClass {
        $this->dummyValue = $dummyValue;
        return $this;
    }

    public function showDummyValue() {
        $temp = $this->getDummyValue();
        error_log('Log from DummyClass. Dummy value is ' . $temp);
        echo $temp;
    }
}