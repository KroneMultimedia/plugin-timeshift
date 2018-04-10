<?php
/**
* @covers KMM\Timeshift\Core
*/
use KMM\Timeshift\Core;
use phpmock\MockBuilder;

class TimeshiftTestDB
{
    public $prefix = "wptest";

    public function query($sql)
    {
    }
    public function get_results($r)
    {
    }
    public function prepare($data)
    {
    }
}

class TestTimeshift extends \WP_UnitTestCase
{
    public function setUp()
    {
        # setup a rest server
        parent::setUp();
        $this->core = new Core('i18n');
    }


    public function tearDown()
    {
        parent::tearDown();
    }
}
