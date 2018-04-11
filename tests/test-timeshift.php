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

    /**
    * @test
    */
    public function hasTimeshiftsTrue() {
        $post_id = $this->factory->post->create(['post_type' => "article"]);
        $post_type = get_post_type($post_id);
        $table_name = 'wptesttimeshift_article';
        $objArr = array('amount' => '1');
        $objArr = (object) $objArr;
        $arr = array($objArr);

        //Mock the DB
        $mock = $this->getMockBuilder('KMM\\Timeshift\\KMM\\TimeshiftTestDB')
            ->setMethods(array( 'get_charset_collate', 'get_results' ))
            ->getMock();

        $mock->prefix = "wptest";

        //Expect query sent
        $mock->expects($this->once())
            ->method('get_results')
            ->with('select count(1) as amount from ' . $table_name . ' where post_id=' . $post_id)
            ->willReturn($arr);

        $this->core->wpdb = $mock;

        $r = $this->core->hasTimeshifts($post_id);

        $this->assertTrue($r);
    }

    /**
    * @test
    */
    public function hasTimeshiftsFalse() {
        $post_id = $this->factory->post->create(['post_type' => "article"]);
        $post_type = get_post_type($post_id);
        $table_name = 'wptesttimeshift_article';

        //Mock the DB
        $mock = $this->getMockBuilder('KMM\\Timeshift\\KMM\\TimeshiftTestDB')
            ->setMethods(array( 'get_charset_collate', 'get_results' ))
            ->getMock();

        $mock->prefix = "wptest";

        //Expect query sent
        $mock->expects($this->once())
            ->method('get_results')
            ->with('select count(1) as amount from ' . $table_name . ' where post_id=' . $post_id);

        $this->core->wpdb = $mock;

        $r = $this->core->hasTimeshifts($post_id);

        $this->assertFalse($r);
    }

    /**
    * @test
    */
    public function timeshiftVisible() {
        $r = $this->core->timeshiftVisible();
        $this->assertTrue($r);
    }

    /**
    * @test
    */
    public function checkTable() {
        $post_id = $this->factory->post->create(['post_type' => "article"]);
        $post_type = get_post_type($post_id);

        //Mock the DB
        $mock = $this->getMockBuilder('KMM\\Timeshift\\KMM\\TimeshiftTestDB')
            ->setMethods(array( 'get_charset_collate' ))
            ->getMock();

        $mock->prefix = "wptest";

        //Expect query sent
        $mock->expects($this->once())
            ->method('get_charset_collate');

        $this->core->wpdb = $mock;

        $r = $this->core->checkTable($post_type);

        $this->assertTrue($r);
    }

    public function tearDown()
    {
        parent::tearDown();
    }
}
