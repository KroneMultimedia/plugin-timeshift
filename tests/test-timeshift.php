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
     * @preserveGlobalState disabled
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
     * @preserveGlobalState disabled
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
     * @preserveGlobalState disabled
     */
    public function timeshiftVisible() {
        $r = $this->core->timeshiftVisible();
        $this->assertTrue($r);
    }

    public function provideTimeshiftVisibleFalse() {
        yield [true];
        yield [false];
    }

    /**
     * Integration test checking that filter krn_timeshift_visible is triggered
     *
     * @test
     * @preserveGlobalState disabled
     * @dataProvider provideTimeshiftVisibleFalse
     */
    public function timeshiftVisibleIntegr($expectedResult) {
        // Register the filter
        add_filter('krn_timeshift_visible', function() use ($expectedResult) {
            return $expectedResult;
        });

        // Run the test
        $result = $this->core->timeshiftVisible();
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @test
     * @preserveGlobalState disabled
     */
    public function add_metabox_no_post() {
        $r = $this->core->add_metabox();
        $this->assertNull($r);
    }

    /**
     * @test
     * @preserveGlobalState disabled
     */
    public function timeshift_metabox_no_post() {
        $r = $this->core->timeshift_metabox();
        $this->assertNull($r);
    }

    /**
     * @1test1 - FIXME
     * @preserveGlobalState disabled
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
     * @preserveGlobalState disabled
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
     * @preserveGlobalState disabled
     */
    public function inject_timeshift_no_timeshift() {
        $r = $this->core->inject_timeshift(null);
        $this->assertNull($r);
    }

    /**
     * @test
     * @preserveGlobalState disabled
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
     * @preserveGlobalState disabled
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
     * @preserveGlobalState disabled
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
     * @preserveGlobalState disabled
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
     * @preserveGlobalState disabled
     */
    public function add_attachment_unit() {
        // prepare input
        $postID = $this->factory->post->create(['post_type' => 'article']);
        // Mock SUT
        $coreMocked = $this->getMockBuilder(Core::class)
                           ->disableOriginalConstructor()
                           ->setMethods(['krn_pre_post_update'])
                           ->getMock();
        $coreMocked->expects($this->once())->method('krn_pre_post_update')->with($postID);

        // Run test
        $coreMocked->add_attachment($postID);
    }

    /**
     * Semi-integration test. Testing that timeshift not stored
     *
     * @test
     * @preserveGlobalState disabled
     */
    public function add_attachment_integr_skip_timeshift() {
        // Prepare input
        $postId = $this->factory->post->create(['post_type' => 'article']);

        // Mock SUT
        $coreMocked = $this->getMockBuilder(Core::class)->setConstructorArgs(['i18n'])
                           ->setMethods(['storeTimeshift'])->getMock();
        $coreMocked->expects($this->never())->method('storeTimeshift');

        // Run test
        $coreMocked->add_attachment($postId);
    }

    /**
     * Unit test when first version of timeshift is assigned
     *
     * @test
     * @preserveGlobalState disabled
     * @covers KMM\Timeshift\Core
     */
    public function updateTimeshiftVersionFirst() {
        // Prepare post
        $postId = $this->factory->post->create(['post_type' => 'article']);

        // Instantiate SUT
        $core = new Core('i18n');

        // Run the test
        $core->pre_post_update($postId);

        // Check result
        $mdata = get_metadata('post', $postId);
        $expectedTimeshiftVer = 0;
        $this->assertEquals($expectedTimeshiftVer, $mdata['_timeshift_version'][0]);
    }

    /**
     * Unit test when incrementing previous valid version
     *
     * @test
     * @preserveGlobalState disabled
     * @covers KMM\Timeshift\Core
     */
    public function updateTimeshiftVersionIncrement() {
        // Prepare post
        $postId = $this->factory->post->create(['post_type' => 'article']);
        $oldVersion = 2;
        update_post_meta($postId, '_timeshift_version', $oldVersion);

        // Instantiate SUT
        $core = new Core('i18n');

        // Run the test
        $core->pre_post_update($postId);

        // Check result
        $mdata = get_metadata('post', $postId);
        $expectedTimeshiftVer = ++$oldVersion;
        $this->assertEquals($expectedTimeshiftVer, $mdata['_timeshift_version'][0]);
    }

    /**
     * Unit test when version number not numeric
     *
     * @test
     * @preserveGlobalState disabled
     * @covers KMM\Timeshift\Core
     */
    public function updateTimeshiftVersionBad() {
        // Prepare post
        $postId = $this->factory->post->create(['post_type' => 'article']);
        update_post_meta($postId, '_timeshift_version', 'not numeric');

        // Instantiate SUT
        $core = new Core('i18n');

        // Run the test
        $core->pre_post_update($postId);

        // Check result
        $expectedTimeshiftVer = 0;
        $mdata = get_metadata('post', $postId);
        $this->assertEquals($expectedTimeshiftVer, $mdata['_timeshift_version'][0]);
    }

    /**
     * Helper method
     */
    public function cleanHtml($html) {
        // Remove dates
        $html = preg_replace('/<td>\d{4}-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})<\/td>/', '', $html);
        // Remove Gravatar IDs
        $html = preg_replace('/(?=\d+.gravatar.com)\d+/', '', $html);
        // Remove timeshift version
        $html = preg_replace('/(?<=timeshift=)\d+/', '', $html);
        // Remove post ID
        $html = preg_replace('/(?<=\?post=)\d+/', '', $html);
        // Replace Gravatar hash
        $html = preg_replace('/(?<=gravatar.com\/avatar\/)\w{32}/', '', $html);

        return $html;
    }

    /**
     * Integration test when post saved from backend and frontend
     *
     * @test
     * @preserveGlobalState disabled
     * @covers KMM\Timeshift\Core
     */
    public function savePostIntegration() {
        // Prepare user A
        $displayNameA = 'Test User A';
        $userA = $this->factory->user->create(
            [
                'role' => 'administrator',
                'display_name' => $displayNameA
            ]
        );
        wp_set_current_user($userA);

        // Prepare post
        $postTitle = 'Test title';
        $postId = $this->factory->post->create(
            [
                'post_type' => 'article',
                'post_title' => $postTitle
            ]
        );

        // Instantiate SUT
        $core = new Core('i18n');

        // Set last author who edited the post
        update_post_meta($postId, '_edit_last', $userA);
        // Update post by first user
        $core->krn_pre_post_update($postId);

        // Required to generate different creation dates for timeshift records
        // Records are sorted by creation dates. If dates same, sorting inconsistent
        sleep(1);

        // Prepare another user
        $displayNameB = 'Test User B';
        $userB = $this->factory->user->create(
            [
                'role' => 'administrator',
                'display_name' => $displayNameB
            ]
        );
        wp_set_current_user($userB);

        // Update post by second user via frontend
        update_post_meta($postId, '_edit_last', $userB);
        $editSourceB = 'Frontend';
        $core->krn_pre_post_update($postId, null, $editSourceB);

        $post = get_post($postId);
        // Get timeshift records
        $rows = $core->get_next_rows($post);
        // Run SUT and get HTML to check
        $output = $core->render_metabox_table($post, $rows);

        $output = $this->cleanHtml($output);

        // Compare output

        $expectedOutput = file_get_contents(__DIR__ . '/fixtures/box-rendered.html');
        $this->assertEquals($expectedOutput, $output);
    }

    /**
     * Unit test to check getting undefined avatar and author name from meta
     *
     * @test
     * @preserveGlobalState disabled
     * @covers KMM\Timeshift\Core
     */
    public function render_metabox_table_bad_edit_last() {
        // Prepare user
        $displayName = 'Test User';
        $user = $this->factory->user->create(
            [
                'role' => 'administrator',
                'display_name' => $displayName
            ]
        );
        wp_set_current_user($user);

        // Prepare post
        $postTitle = 'Test title';
        $postId = $this->factory->post->create(
            [
                'post_type' => 'article',
                'post_title' => $postTitle
            ]
        );

        // Instantiate SUT
        $core = new Core('i18n');

        // Save timeshift record
        $core->krn_pre_post_update($postId);

        $post = get_post($postId);
        // Get timeshift records
        $rows = $core->get_next_rows($post);

        // Essence of test. Break _edit_last for the first timeshift entry
        $payload = unserialize($rows[0]->post_payload);
        $payload->meta['_edit_last'] = null;
        $rows[0]->post_payload = serialize($payload);

        // Run SUT and get HTML to check
        $output = $core->render_metabox_table($post, $rows);

        // Remove variable data
        $output = $this->cleanHtml($output);

        $expectedOutput = file_get_contents(__DIR__ . '/fixtures/box-rendered-bad-edit-last.html');
        $this->assertEquals($expectedOutput, $output);
    }
}
