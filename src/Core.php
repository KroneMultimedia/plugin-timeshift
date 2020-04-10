<?php
/*
 *
 *
 * inspired by https://github.com/adamsilverstein/wp-post-meta-revisions/blob/master/wp-post-meta-revisions.php
 * many thx @adamsilverstein
 *
 */

namespace KMM\Timeshift;

class Core {
    private $plugin_dir;
    private $last_author = false;
    private $timeshift_cached_meta;

    public function __construct($i18n) {
        global $wpdb;
        $this->i18n = $i18n;
        $this->wpdb = $wpdb;
        $this->plugin_dir = plugin_dir_url(__FILE__) . '../';
        $this->timeshift_posts_per_page = 5;
        $this->pagination_ajax_action = 'pagination_timeshift';
        $this->add_filters();
        $this->add_actions();
        $this->add_metabox();
        // Disable WP's own revision system
        remove_post_type_support('post', 'revisions');
    }

    public function hasTimeshifts($post_id) {
        $post_type = get_post_type($post_id);
        $table_name = $this->wpdb->prefix . 'timeshift_' . $post_type;
        $this->checkTable($post_type);
        $sql = "select count(1) as amount from $table_name where post_id=" . $post_id;
        $r = $this->wpdb->get_results($sql);

        if ($r && 1 == count($r)) {
            if (intval($r[0]->amount) > 0) {
                return true;
            }
        }

        return false;
    }

    public function timeshiftVisible() {
        $check = apply_filters('krn_timeshift_visible', true);

        return $check;
    }

    public function add_metabox() {
        $cl = $this;
        if (! $this->timeshiftVisible()) {
            return;
        }
        // Keep adding the metabox even if no timeshifts available, i.e. will render timeshift box with only live version
        if (! isset($_GET['post'])) {
            return;
        }
        add_action('add_meta_boxes', function () use ($cl) {
            add_meta_box('krn-timeshift', __('Timeshift', 'kmm-timeshift'), [$cl, 'timeshift_metabox'], null, 'normal', 'core');
        });
    }

    public function timeshift_metabox() {
        if (! isset($_GET['post'])) {
            return;
        }
        $prod_post = get_post($_GET['post']);

        $start = 0;
        $timeshift_page = 1;
        if (isset($_GET['timeshift_page'])) {
            $start = ($_GET['timeshift_page'] - 1) * $this->timeshift_posts_per_page;
            $timeshift_page = $_GET['timeshift_page'];
        }

        // pagination
        $pagination = $this->get_paginated_links($prod_post, $timeshift_page);
        echo $pagination;

        // load first few & render
        $rows = $this->get_next_rows($prod_post, $start);
        $timeshift_table = $this->render_metabox_table($prod_post, $rows);
        echo $timeshift_table;

        if (isset($_GET['action']) && $_GET['action'] == $this->pagination_ajax_action) {
            wp_die();
        }
    }

    public function add_filters() {
        // When revisioned post meta has changed, trigger a revision save.
        //add_filter('wp_save_post_revision_post_has_changed', [$this, '_wp_check_revisioned_meta_fields_have_changed'], 10, 3);

        add_filter('get_post_metadata', [$this, 'inject_metadata_timeshift'], 1, 4);
        add_filter('update_post_metadata', [$this, 'update_post_metadata'], 1, 5);
    }

    public function update_post_metadata($check, int $object_id, string $meta_key, $meta_value, $prev_value) {
        if ('_edit_last' == $meta_key) {
            $lo = get_post_meta($object_id, '_edit_last', true);
            $this->last_author = $lo;
        }

        return null;
    }

    public function inject_metadata_timeshift($value, $post_id, $key, $single) {
        if (! isset($_GET['timeshift'])) {
            return;
        }
        // Load timeshift
        if (! $this->timeshift_cached_meta) {
            $post_type = get_post_type($post_id);
            $table_name = $this->wpdb->prefix . 'timeshift_' . $post_type;
            $sql = "select * from $table_name where id=" . intval($_GET['timeshift']);
            $r = $this->wpdb->get_results($sql);
            if ($r && 1 == count($r)) {
                $payload = unserialize($r[0]->post_payload);
                $this->timeshift_cached_meta = $payload->meta;
            }
        }
        // is the requested meta data in the stored snapshot
        if ($this->timeshift_cached_meta && isset($this->timeshift_cached_meta[$key])) {
            return $this->timeshift_cached_meta[$key];
        } else {
            // Otherwise return default value, like acf core fields.
            return $value;
        }
    }

    public function inject_timeshift($p) {
        global $post;
        if (! isset($_GET['timeshift'])) {
            return;
        }
        // Load timeshift
        $table_name = $this->wpdb->prefix . 'timeshift_' . $post->post_type;
        $sql = "select * from $table_name where id=" . intval($_GET['timeshift']);
        $r = $this->wpdb->get_results($sql);
        if ($r && 1 == count($r)) {
            $payload = unserialize($r[0]->post_payload);
            $post = $payload->post;
        }
    }

    public function add_actions() {
        add_action('edit_form_top', [$this, 'inject_timeshift'], 1, 1);
        add_action('pre_post_update', [$this, 'pre_post_update'], 2, 1);
        add_action('add_attachment', [$this, 'add_attachment'], 1, 1);
        add_action('admin_notices', [$this, 'admin_notice']);
        add_action('krn_timeshift_create_snapshot', [$this, 'create_snapshot'], 1, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_pagination_timeshift', [$this, 'timeshift_metabox']);
    }

    public function admin_notice() {
        if (isset($_GET['timeshift']) && $_GET['timeshift']) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p style="font-weight: 800; color: red">' . __('You are editing a historical version! if you save or publish, this will replace the current live one', 'kmm-timeshift') . '</p>';
            echo '</div>';
        }
    }

    public function enqueue_scripts() {
        wp_enqueue_script('krn-timeshift-pagination-ajax', plugin_dir_url(__FILE__) . '../assets/js/pagination-ajax.js', ['jquery']);
        wp_localize_script('krn-timeshift-pagination-ajax', 'krn_timeshift', [
            'action' => $this->pagination_ajax_action,
            'post' => isset($_GET['post']) ? $_GET['post'] : false,
            'timeshift' => isset($_GET['timeshift']) ? $_GET['timeshift'] : false,
        ]);
    }

    public function checkTable($postType) {
        $table_name = $this->wpdb->prefix . 'timeshift_' . $postType;

        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id int(12) NOT NULL AUTO_INCREMENT,
                post_id int(12) NOT NULL,
                create_date datetime default CURRENT_TIMESTAMP,
                post_payload LONGTEXT,
                PRIMARY KEY (id)
            ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $a = dbDelta($sql);

        return true;
    }

    public function storeTimeshift($timeshift) {
        $table_name = $this->wpdb->prefix . 'timeshift_' . $timeshift->post->post_type;
        $sql = "insert into $table_name (post_id, post_payload) VALUES(%d, '%s')";
        $query = $this->wpdb->prepare($sql, $timeshift->post->ID, serialize($timeshift));
        $this->wpdb->query($query);
    }

    private function updateTimeshiftVersion($postID, $mdata): int {
        $verToSave = 0;
        if (is_array($mdata) && isset($mdata['_timeshift_version'][0]) &&
        is_numeric($mdata['_timeshift_version'][0])) {
            // Increment version for post's meta
            $verToSave = (int) $mdata['_timeshift_version'][0] + 1;
        }
        update_post_meta($postID, '_timeshift_version', $verToSave);
        if ($verToSave > 1) {
            // Previous version for when timeshift existed before
            return $verToSave - 1;
        } else {
            // When this is the first timeshift
            return 0;
        }
    }

    public function create_snapshot($postID, $editSource) {
        $this->krn_pre_post_update($postID, null, $editSource);
    }

    public function add_attachment($postID) {
        $post = get_post($postID);
        update_post_meta($postID, '_edit_last', $post->post_author);
        $this->krn_pre_post_update($postID, null, 'Backend', false);
    }

    public function pre_post_update(int $post_ID, array $data = null) {
        $this->krn_pre_post_update($post_ID, $data);
    }

    public function krn_pre_post_update(int $post_ID, array $data = null, $editSource = 'Backend', $recordTimeshift = true) {
        if (true == apply_filters('krn_timeshift_skip', false, $post_ID, $data, $editSource)) {
            return;
        }
        if (wp_is_post_autosave($post_ID)) {
            return;
        }
        if ('auto-draft' == get_post_status($post_ID)) {
            return;
        }
        $post_type = get_post_type($post_ID);
        $this->checkTable($post_type);

        // Get previous save initiator
        $prevSaveInit = get_post_meta($post_ID, 'save_initiator');

        // When executed by Cron
        if (defined('DOING_CRON')) {
            $editSource = 'Kron';
        }

        // Update live save initiator
        update_post_meta($post_ID, 'save_initiator', $editSource);

        $mdata = get_metadata('post', $post_ID);
        $post = get_post($post_ID);

        if ($this->last_author) {
            $mdata['_edit_last'][0] = $this->last_author;
        } else {
            // For unknown last author, clear it. It is a current user now
            $mdata['_edit_last'][0] = '';
            if ('Frontend' == $editSource) {
                $lo = get_post_meta($post_ID, '_edit_last', true);
                $mdata['_edit_last'][0] = $lo;
            }
        }
        unset($mdata['_edit_lock']);

        // Store timeshift version to post's meta
        $timeshiftVer = $this->updateTimeshiftVersion($post_ID, $mdata);

        // Don't save timeshift record when the article was just created
        if ('article' == $post->post_type && 'auto-draft' == $post->post_status) {
            $recordTimeshift = false;
        }

        if ($recordTimeshift) {
            $mdata['_timeshift_version'][0] = $timeshiftVer;
            $mdata['save_initiator'] = $prevSaveInit;
            $timeshift = (object) ['post' => $post, 'meta' => $mdata];
            $this->storeTimeshift($timeshift);
        }
    }

    public function get_paginated_links($prod_post, $paged = 1) {
        if (is_null($prod_post)) {
            return;
        }

        // count timeshift-versions
        $table_name = $this->wpdb->prefix . 'timeshift_' . $prod_post->post_type;
        $sql = "select  count(1) as cnt from $table_name where post_id=" . $prod_post->ID;
        $maxrows = $this->wpdb->get_results($sql);
        $allrows = (int) $maxrows[0]->{'cnt'};

        // max. number of pages
        $max_page = ceil($allrows / $this->timeshift_posts_per_page);

        // create pagination links
        $output = paginate_links([
            'current' => max(1, $paged),
            'total' => $max_page,
            'mid_size' => 1,
            'prev_text' => __('«'),
            'next_text' => __('»'),
        ]);

        return $output;
    }

    public function get_next_rows($prod_post, $start = 0) {
        if (! isset($prod_post)) {
            return;
        }

        $table_name = $this->wpdb->prefix . 'timeshift_' . $prod_post->post_type;
        $sql = "select  * from $table_name where post_id=" . $prod_post->ID . ' order by create_date desc limit ' . $start . ', ' . $this->timeshift_posts_per_page;
        $rows = $this->wpdb->get_results($sql);

        return $rows;
    }

    public function render_metabox_table($prod_post, $rows = []) {
        if (! isset($prod_post)) {
            return;
        }

        // get last editor
        $table_postmeta = $this->wpdb->prefix . 'postmeta';
        $sql_last_editor = 'select meta_value from ' . $table_postmeta . ' where post_id=' . $prod_post->ID . " AND meta_key='_edit_last'";
        $last_editor = $this->wpdb->get_var($sql_last_editor);

        // check save initiator
        if (get_post_meta($prod_post->ID, 'save_initiator')) {
            $save_initiator_live = get_post_meta($prod_post->ID, 'save_initiator')[0];
        } else {
            $save_initiator_live = __('unknown', 'kmm-timeshift');
        }

        $output = '<table class="widefat fixed">';
        $output .= '<thead>';
        $output .= '<tr>';
        $output .= '<th width=30></th>';
        $output .= '<th width="35%" id="columnname" class="manage-column column-columnname"  scope="col">' . __('Title', 'kmm-timeshift') . '</th>';
        $output .= '<th width="25%" id="columnname" class="manage-column column-columnname"  scope="col">' . __('Snapshot Date', 'kmm-timeshift') . '</th>';
        $output .= '<th width="10%" id="columnname" class="manage-column column-columnname"  scope="col">' . __('Author', 'kmm-timeshift') . '</th>';
        $output .= '<th width="10%" id="columnname" class="manage-column column-columnname"  scope="col">' . __('Save-initiator', 'kmm-timeshift') . '</th>';
        $output .= '<th width="10%" id="columnname" class="manage-column column-columnname"  scope="col">' . __('Actions', 'kmm-timeshift') . '</th>';
        $output .= '</tr>';
        $output .= '</thead>';
        $output .= '<tbody>';

        // live-version
        $output .= '<tr style="font-weight: 800;">';
        $output .= '<td>' . get_avatar($last_editor, 30) . '</td>';
        $output .= '<td>' . $prod_post->post_title . '</td>';
        $output .= '<td>' . $prod_post->post_modified . '</td>';
        $output .= '<td>' . get_the_author_meta('display_name', $last_editor) . '</td>';
        $output .= '<td>' . $save_initiator_live . '</td>';
        $output .= '<td><a href="post.php?post=' . $prod_post->ID . '&action=edit"><span class="dashicons dashicons-admin-site"></span></A></td>';
        $output .= '</tr>';

        foreach ($rows as $rev) {
            $timeshift = unserialize($rev->post_payload);
            $style = '';

            // highlight currently loaded version
            if (isset($_GET['timeshift']) && $_GET['timeshift'] == $rev->id) {
                $style = 'style="font-style:italic;background-color: lightblue;"';
            }

            // some images don't have _edit_last field
            if (! isset($timeshift->meta['_edit_last']) || is_null($timeshift->meta['_edit_last'])) {
                $timeshift->meta['_edit_last'] = 0;
            }

            // sometimes _edit_last is defined in a wrong way
            if (is_array($timeshift->meta['_edit_last']) && count($timeshift->meta['_edit_last']) > 0) {
                $avatar = get_avatar($timeshift->meta['_edit_last'][0], 30);
                $authorName = get_the_author_meta('display_name', $timeshift->meta['_edit_last'][0]);
            } else {
                $avatar = 'unknown';
                $authorName = __('unknown', 'kmm-timeshift');
            }

            // check save initiator
            if (array_key_exists('save_initiator', $timeshift->meta) && count($timeshift->meta['save_initiator']) > 0) {
                $save_initiator_timeshift = $timeshift->meta['save_initiator'][0];
            } else {
                $save_initiator_timeshift = __('unknown', 'kmm-timeshift');
            }

            $output .= '<tr ' . $style . '>';
            $output .= '<td>' . $avatar . '</td>';
            $output .= '<td>' . $timeshift->post->post_title . '</td>';
            $output .= '<td>' . $timeshift->post->post_modified . '</td>';
            $output .= '<td>' . $authorName . '</td>';
            $output .= '<td>' . $save_initiator_timeshift . '</td>';
            $output .= '<td><a href="post.php?post=' . $prod_post->ID . '&action=edit&timeshift=' . $rev->id . '"><span class="dashicons dashicons-backup"></span></a></td>';
            $output .= '</tr>';
        }

        $output .= '</tbody>';
        $output .= '</table>';

        return $output;
    }
}
