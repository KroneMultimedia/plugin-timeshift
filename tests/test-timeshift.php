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
    public function add_metabox_no_post() {
        $r = $this->core->add_metabox();
        $this->assertNull($r);
    }

    /**
    * @test
    */
    public function timeshift_metabox_no_post() {
        $r = $this->core->timeshift_metabox();
        $this->assertNull($r);
    }

    /**
    * @test
    */
    public function timeshift_metabox() {
        $post_id = $this->factory->post->create(['post_type' => "article"]);
        $_GET['post'] = $post_id;
        global $post;
        $post = get_post($post_id);
        $table_name = 'wptesttimeshift_article';
        $obj = new stdClass();

        //Mock the DB
        $mock = $this->getMockBuilder('KMM\\Timeshift\\KMM\\TimeshiftTestDB')
            ->setMethods(array( 'get_results' ))
            ->getMock();

        $mock->prefix = "wptest";

        //Expect query sent
        $mock->expects($this->once())
            ->method('get_results')
            ->with("select * from $table_name where post_id=" . $post->ID . ' order by create_date desc')
            ->willReturn($obj);

        $this->core->wpdb = $mock;

        $this->core->timeshift_metabox();
    }

    /**
    * @test
    */
    public function update_post_metadata() {
        $post_id = $this->factory->post->create(['post_type' => "article"]);
        $post = get_post_type($post_id);
        add_post_meta($post_id, '_edit_last', 'Author Name');

        $lo = get_post_meta($post_id, '_edit_last', true);
        $r = $this->core->update_post_metadata(true, $post_id, '_edit_last', '', '');
        $this->assertEquals('Author Name', $lo);
        $this->assertNull($r);
    }

    /**
    * @test
    */
    public function inject_timeshift_no_timeshift() {
        $r = $this->core->inject_timeshift(null);
        $this->assertNull($r);
    }

    /**
    * @test
    */
    public function inject_timeshift() {
        $_GET['timeshift'] = 23;
        global $post;
        $post_id = $this->factory->post->create(['post_type' => "article"]);
        $post = get_post($post_id);
        $table_name = 'wptesttimeshift_article';

        //Mock the DB
        $mock = $this->getMockBuilder('KMM\\Timeshift\\KMM\\TimeshiftTestDB')
        ->setMethods(array( 'get_results' ))
        ->getMock();

        $mock->prefix = "wptest";

        //Expect query sent
        $mock->expects($this->once())
            ->method('get_results')
            ->with("select * from $table_name where id=" . intval($_GET['timeshift']));

        $this->core->wpdb = $mock;

        $this->core->inject_timeshift($post);
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

    /**
    * @test
    */
    public function storeTimeshift() {
        $table_name = 'wptesttimeshift_article';
        $post_id = $this->factory->post->create(['post_type' => "article"]);
        $post = get_post($post_id);
        $timeshift = array('post' => $post);
        $timeshift = (object) $timeshift;

        // var_dump($timeshift->post->ID); exit;

        //Mock the DB
        $mock = $this->getMockBuilder('KMM\\Timeshift\\KMM\\TimeshiftTestDB')
            ->setMethods(array( 'prepare', 'query' ))
            ->getMock();

        $mock->prefix = "wptest";

        //Expect query sent
        $mock->expects($this->once())
        ->method('prepare')
        ->with("insert into $table_name (post_id, post_payload) VALUES(%d, '%s')", $timeshift->post->ID, serialize($timeshift));

        //Expect query sent
        $mock->expects($this->once())
            ->method('query');

        $this->core->wpdb = $mock;

        $this->core->storeTimeshift($timeshift);
    }

    public function tearDown()
    {
        parent::tearDown();
    }
}
