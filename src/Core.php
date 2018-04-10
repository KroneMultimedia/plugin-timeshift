<?php
/*
 *
 *
 * inspired by https://github.com/adamsilverstein/wp-post-meta-revisions/blob/master/wp-post-meta-revisions.php
 * many thx @adamsilverstein
 *
 */

namespace KMM\Timeshift;

class Core
{
    private $plugin_dir;
    private $timeshift_cached_meta;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->plugin_dir = plugin_dir_url(__FILE__) . '../';
        $this->add_filters();
        $this->add_actions();
        $this->add_metabox();
    }

    public function hasTimeshifts($post_id)
    {

        $post_type = get_post_type($post_id);
        $table_name = $this->wpdb->prefix . 'timeshift_' . $post_type;
        $this->checkTable($post_type);
        $sql = "select count(1) as amount from $table_name where post_id=" . $post_id;
        $r = $this->wpdb->get_results($sql);

        if ($r && count($r) == 1) {
            if (intval($r[0]->amount) > 0) {
                return true;
            }
        }

        return false;
    }

    public function add_metabox()
    {
        $cl = $this;
        if (! isset($_GET['post']) || ! $this->hasTimeshifts($_GET['post'])) {
            return;
        }
        add_action('add_meta_boxes', function () use ($cl) {
            add_meta_box('krn-timeshift', __('Timeshift', 'kmm-timeshift'), [$cl, 'timeshift_metabox'], null, 'normal', 'core');
        });
    }

    public function timeshift_metabox()
    {
        global $post;
        if (! isset($_GET['post'])) {
            return;
        }
        $table_name = $this->wpdb->prefix . 'timeshift_' . $post->post_type;
        $sql = "select * from $table_name where post_id=" . $post->ID . ' order by create_date desc';

        $row = $this->wpdb->get_results($sql);
        echo '<table class="widefat fixed">';
        echo '<thead>';
        echo '<tr>';
        echo '<th width="40%" id="columnname" class="manage-column column-columnname"  scope="col">Title</th>';
        echo '<th width="30%" id="columnname" class="manage-column column-columnname"  scope="col">Date</th>';
        echo '<th width="10%" id="columnname" class="manage-column column-columnname"  scope="col">Author</th>';
        echo '<th width="10%" id="columnname" class="manage-column column-columnname"  scope="col">Actions</th>';
        echo '</tr>';
        echo ' </thead>';
        echo '<tbody>';
        echo '<tr style="font-weight: 800;">';
        echo '<td>' . $post->post_title . '</td>';
        echo '<td>' . $post->post_date . '</td>';
        echo '<td>' . get_the_author_meta('display_name', $post->post_author) . '</td>';
        echo "<td><a href='post.php?post=" . $_GET['post'] . "&action=edit'><span class='dashicons dashicons-admin-site'></span></A></td>";
        echo '</tr>';

        foreach ($row as $rev) {
            $timeshift = unserialize($rev->post_payload);
            $style = '';
            if (isset($_GET['timeshift']) && $_GET['timeshift'] == $rev->id) {
                $style = 'style="font-style:italic;background-color: lightblue;"';
            }
            echo '<tr ' . $style . '>';
            echo '<td>' . $timeshift->post->post_title . '</td>';
            echo '<td>' . $rev->create_date . '</td>';
            echo '<td>' . get_the_author_meta('display_name', $timeshift->post->post_author) . '</td>';
            echo "<td><a href='post.php?post=" . $_GET['post'] . '&action=edit&timeshift=' . $rev->id . "'><span class='dashicons dashicons-backup'></span></a></td>";
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }

    public function add_filters()
    {
        // When revisioned post meta has changed, trigger a revision save.
        //add_filter('wp_save_post_revision_post_has_changed', [$this, '_wp_check_revisioned_meta_fields_have_changed'], 10, 3);

        add_filter('get_post_metadata', [$this, 'inject_metadata_timeshift'], 1, 4);
    }

    public function inject_metadata_timeshift($value, $post_id, $key, $single)
    {
        if (! isset($_GET['timeshift'])) {
            return;
        }
        //Load timeshift
        if (! $this->timeshift_cached_meta) {
            $post_type = get_post_type($post_id);
            $table_name = $this->wpdb->prefix . 'timeshift_' . $post_type;
            $sql = "select * from $table_name where id=" . intval($_GET['timeshift']);
            $r = $this->wpdb->get_results($sql);
            if ($r && count($r) == 1) {
                $payload = unserialize($r[0]->post_payload);
                $this->timeshift_cached_meta = $payload->meta;
            }
        }
        if ($this->timeshift_cached_meta && isset($this->timeshift_cached_meta[$key])) {
            return $this->timeshift_cached_meta[$key];
        }
    }

    public function inject_timeshift($p)
    {
        global $post;
        if (! isset($_GET['timeshift'])) {
            return;
        }
        //Load timeshift
        $table_name = $this->wpdb->prefix . 'timeshift_' . $post->post_type;
        $sql = "select * from $table_name where id=" . intval($_GET['timeshift']);
        $r = $this->wpdb->get_results($sql);
        if ($r && count($r) == 1) {
            $payload = unserialize($r[0]->post_payload);
            $post = $payload->post;
        }
    }

    public function add_actions()
    {
        add_action('edit_form_top', [$this, 'inject_timeshift'], 1, 1);
        add_action('pre_post_update', [$this, 'pre_post_update'], 2, 1);
        add_action('admin_notices', function () {
            if (isset($_GET['timeshift']) && $_GET['timeshift']) {
                echo '<div class="notice notice-warning is-dismissible">
                         <p>You are editing a historical version! if you save or publish, this will replace the current live one</p>
                                  </div>';
            }
        });
    }

    public function checkTable($postType)
    {
        $table_name = $this->wpdb->prefix . 'timeshift_' . $postType;

        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
		              id int(12) NOT NULL AUTO_INCREMENT,
                  post_id int(12) NOT NULL,
								  create_date datetime default CURRENT_TIMESTAMP,
									post_payload TEXT
		              ,PRIMARY KEY  (id)
	        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $a = dbDelta($sql);

        return true;
    }

    public function storeTimeshift($timeshift)
    {
        $table_name = $this->wpdb->prefix . 'timeshift_' . $timeshift->post->post_type;
        $sql = "insert into $table_name (post_id, post_payload) VALUES(%d, '%s')";
        $query = $this->wpdb->prepare($sql, $timeshift->post->ID, serialize($timeshift));
        $this->wpdb->query($query);
    }

    public function pre_post_update(int $post_ID, array $data = null)
    {
        header('X-XSS-Protection: 0');
        if (wp_is_post_autosave($post_ID)) {
            return;
        }
        $post_type = get_post_type($post_ID);
        $this->checkTable($post_type);

        $mdata = get_metadata('post', $post_ID);
        $post = get_post($post_ID);

        $timeshift = (object) ['post' => $post, 'meta' => $mdata];
        $this->storeTimeshift($timeshift);
    }
}
