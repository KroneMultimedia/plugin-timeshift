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
    private $last_author = false;
    private $timeshift_cached_meta;

    public function __construct($i18n)
    {
        global $wpdb;
        $this->i18n = $i18n;
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

    public function timeshiftVisible()
    {
        $check = apply_filters('krn_timeshift_visible', true);

        return $check;
    }

    public function add_metabox()
    {
        $cl = $this;
        if (! $this->timeshiftVisible()) {
            return;
        }
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
        $prod_post = get_post($_GET['post']);
        $table_name = $this->wpdb->prefix . 'timeshift_' . $post->post_type;
        $sql = "select * from $table_name where post_id=" . $post->ID . ' order by create_date desc';

        $last_editor = get_post_meta($prod_post->ID, '_edit_last', true);
        $row = $this->wpdb->get_results($sql);
        echo '<table class="widefat fixed">';
        echo '<thead>';
        echo '<tr>';
        echo '<th width=30></th>';
        echo '<th width="40%" id="columnname" class="manage-column column-columnname"  scope="col">' . __('Title', 'kmm-timeshift') . '</th>';
        echo '<th width="30%" id="columnname" class="manage-column column-columnname"  scope="col">' . __('Snapshot Date', 'kmm-timeshift') . '</th>';
        echo '<th width="10%" id="columnname" class="manage-column column-columnname"  scope="col">' . __('Author', 'kmm-timeshift') . '</th>';
        echo '<th width="10%" id="columnname" class="manage-column column-columnname"  scope="col">' . __('Actions', 'kmm-timeshift') . '</th>';
        echo '</tr>';
        echo ' </thead>';
        echo '<tbody>';
        echo '<tr style="font-weight: 800;">';
        echo '<td>' . get_avatar($last_editor, 30) . '</td>';
        echo '<td>' . $prod_post->post_title . '</td>';
        echo '<td>' . $prod_post->post_date . '</td>';
        echo '<td>' . get_the_author_meta('display_name', $last_editor) . '</td>';
        echo "<td><a href='post.php?post=" . $_GET['post'] . "&action=edit'><span class='dashicons dashicons-admin-site'></span></A></td>";
        echo '</tr>';

        foreach ($row as $rev) {
            $timeshift = unserialize($rev->post_payload);
            $style = '';
            if (isset($_GET['timeshift']) && $_GET['timeshift'] == $rev->id) {
                $style = 'style="font-style:italic;background-color: lightblue;"';
            }
            echo '<tr ' . $style . '>';
            echo '<td>' . get_avatar($timeshift->meta['_edit_last'][0], 30) . '</td>';
            echo '<td>' . $timeshift->post->post_title . '</td>';
            echo '<td>' . $rev->create_date . '</td>';
            echo '<td>' . get_the_author_meta('display_name', $timeshift->meta['_edit_last'][0]) . '</td>';
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
        add_filter('update_post_metadata', [$this, 'update_post_metadata'], 1, 5);
    }

    public function update_post_metadata($check, int $object_id, string $meta_key, $meta_value, $prev_value)
    {
        if ($meta_key == '_edit_last') {
            $lo = get_post_meta($object_id, '_edit_last', true);
            $this->last_author = $lo;
        }

        return null;
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
        if ($single) {
            return null;
        }

        return [];
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
                         <p style="font-weight: 800; color: red">' . __('You are editing a historical version! if you save or publish, this will replace the current live one', 'kmm-timeshift') . '</p>
                                  </div>';
            }
        });
                add_action('krn_timeshift_create_snapshot', [$this, 'create_snapshot'], 1, 1);
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


    public function create_snapshot($postID)
    {
            $this->pre_post_update($postID);
    }
    public function pre_post_update(int $post_ID, array $data = null)
    {
        if (wp_is_post_autosave($post_ID)) {
            return;
        }
        if (get_post_status($post_ID) == 'auto-draft') {
            return;
        }
        $post_type = get_post_type($post_ID);
        $this->checkTable($post_type);

        $mdata = get_metadata('post', $post_ID);
        $post = get_post($post_ID);

        if ($this->last_author) {
            $mdata['_edit_last'][0] = $this->last_author;
        }
        unset($mdata['_edit_lock']);

        $timeshift = (object) ['post' => $post, 'meta' => $mdata];
        $this->storeTimeshift($timeshift);
    }
}
