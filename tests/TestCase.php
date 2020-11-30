<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    public function arrayOfArraysSearch($array, $keyname, $value)
    {
        if (array_search($value, array_column($array, $keyname)) !== false) {
            return true;
        }
        return false;
    }
}
