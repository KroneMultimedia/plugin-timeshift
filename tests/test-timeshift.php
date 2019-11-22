<?php
/**
* @covers \KMM\Timeshift\Core
*/
use KMM\Timeshift\Core;

class TimeshiftTestDB {
    public $prefix = 'wptest';

    public function query($sql) {
    }

    public function get_results($r) {
    }

    public function prepare($data) {
    }
}

class TestTimeshift extends \WP_UnitTestCase {
    public function setUp() {
        // setup a rest server
        parent::setUp();
        $this->core = new Core('i18n');
    }

    /**
     * @test
     */
    public function hasTimeshiftsTrue() {
        $post_id = $this->factory->post->create(['post_type' => 'article']);
        $post_type = get_post_type($post_id);
        $table_name = 'wptesttimeshift_article';
        $objArr = ['amount' => '1'];
        $objArr = (object) $objArr;
        $arr = [$objArr];

        //Mock the DB
        $mock = $this->getMockBuilder('KMM\\Timeshift\\KMM\\TimeshiftTestDB')
            ->setMethods(['get_charset_collate', 'get_results'])
            ->getMock();

        $mock->prefix = 'wptest';

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
        $post_id = $this->factory->post->create(['post_type' => 'article']);
        $post_type = get_post_type($post_id);
        $table_name = 'wptesttimeshift_article';

        //Mock the DB
        $mock = $this->getMockBuilder('KMM\\Timeshift\\KMM\\TimeshiftTestDB')
            ->setMethods(['get_charset_collate', 'get_results'])
            ->getMock();

        $mock->prefix = 'wptest';

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
     * @1test1 - FIXME
     */
    public function timeshift_metabox() {
        $post_id = $this->factory->post->create(['post_type' => 'article']);
        $_GET['post'] = $post_id;
        global $post;
        $post = get_post($post_id);
        $table_name = 'wptesttimeshift_article';
        $obj = new stdClass();

        //Mock the DB
        $mock = $this->getMockBuilder('KMM\\Timeshift\\KMM\\TimeshiftTestDB')
            ->setMethods(['get_results', 'get_var'])
            ->getMock();

        $mock->prefix = 'wptest';

        //Expect query sent
        $mock->expects($this->exactly(2))
            ->method('get_results')
            ->withConsecutive([
              ["select  * from $table_name where post_id=" . $post->ID . ' order by create_date desc limit 0,10', '1'],
              ['aaaa'],
              ])
            ->willReturnOnConsecutiveCalls([$obj, (object) ['cnt' => 1]]);

        //Expect query sent
        $table_name = 'wptestpostmeta';
        $mock->expects($this->once())
            ->method('get_var')
            ->with('select meta_value from ' . $table_name . ' where post_id=' . $post->ID . " AND meta_key='_edit_last'")
            ->willReturn($obj);

        $this->core->wpdb = $mock;

        $this->core->timeshift_metabox();
    }

    /**
     * @test
     */
    public function update_post_metadata() {
        $post_id = $this->factory->post->create(['post_type' => 'article']);
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
        $post_id = $this->factory->post->create(['post_type' => 'article']);
        $post = get_post($post_id);
        $table_name = 'wptesttimeshift_article';

        //Mock the DB
        $mock = $this->getMockBuilder('KMM\\Timeshift\\KMM\\TimeshiftTestDB')
        ->setMethods(['get_results'])
        ->getMock();

        $mock->prefix = 'wptest';

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
        $post_id = $this->factory->post->create(['post_type' => 'article']);
        $post_type = get_post_type($post_id);

        //Mock the DB
        $mock = $this->getMockBuilder('KMM\\Timeshift\\KMM\\TimeshiftTestDB')
            ->setMethods(['get_charset_collate'])
            ->getMock();

        $mock->prefix = 'wptest';

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
        $post_id = $this->factory->post->create(['post_type' => 'article']);
        $post = get_post($post_id);
        $timeshift = ['post' => $post];
        $timeshift = (object) $timeshift;

        //Mock the DB
        $mock = $this->getMockBuilder('KMM\\Timeshift\\KMM\\TimeshiftTestDB')
            ->setMethods(['prepare', 'query'])
            ->getMock();

        $mock->prefix = 'wptest';

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

    /**
     * @test
     */
    public function pre_post_update_auto_draft() {
        $post_id = $this->factory->post->create(['post_type' => 'article', 'post_status' => 'auto_draft']);

        $r = $this->core->pre_post_update($post_id, null);
        $this->assertNull($r);
    }

    public function tearDown() {
        parent::tearDown();
    }

    /**
     * Unit test
     * 
     * @test
     */
    public function add_attachment_unit() {
        // prepare input
        $postID = 777;
        
        // Mock SUT
        $coreMocked = $this->getMockBuilder(Core::class)
                           ->disableOriginalConstructor()
                           ->setMethods(['pre_post_update'])
                           ->getMock();
        $coreMocked->expects($this->once())->method('pre_post_update')->with($postID);

        // Run test
        $coreMocked->add_attachment($postID);
    }

    /**
     * Semi-integration test. Mainly testing pre_post_update() when
     * timeshift stored
     * 
     * @test
     */
    public function add_attachment_integr_store_timeshift() {
        // Prepare input
        $postId = $this->factory->post->create(['post_type' => 'article']);
        $post = get_post($postId);
        // Add two keys to meta which are enough to run storeTimeshift()
        update_post_meta($postId, 'tesKey1', 'testKey1');
        update_post_meta($postId, 'tesKey2', 'testKey2');
        $mdata = get_metadata('post', $postId);

        // Prepare timeshift
        $timeshift = (object) [
            'post' => $post,
            'meta' => $mdata
        ];

        // Mock SUT
        $coreMocked = $this->getMockBuilder(Core::class)->setConstructorArgs(['i18n'])
                           ->setMethods(['storeTimeshift'])->getMock();
        $coreMocked->expects($this->once())->method('storeTimeshift')->with($timeshift);

        // Run test
        $coreMocked->add_attachment($postId);
    }

    /**
     * Semi-integration test. Mainly testing pre_post_update() when
     * timeshift not stored
     * 
     * @test
     */
    public function add_attachment_integr_skip_timeshift() {
        // Prepare input
        $postId = $this->factory->post->create(['post_type' => 'article']);
        // Add just one key to meta which is not enough to run storeTimeshift()
        update_post_meta($postId, 'tesKey1', 'testKey1');

        // Mock SUT
        $coreMocked = $this->getMockBuilder(Core::class)->setConstructorArgs(['i18n'])
                           ->setMethods(['storeTimeshift'])->getMock();
        $coreMocked->expects($this->never())->method('storeTimeshift');

        // Run test
        $coreMocked->add_attachment($postId);
    }
}
