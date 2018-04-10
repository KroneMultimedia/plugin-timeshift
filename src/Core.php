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

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->plugin_dir = plugin_dir_url(__FILE__) . '../';
        $this->add_filters();
        $this->add_actions();
    }

    public function add_filters()
    {
        // When revisioned post meta has changed, trigger a revision save.
        //add_filter('wp_save_post_revision_post_has_changed', [$this, '_wp_check_revisioned_meta_fields_have_changed'], 10, 3);
    }

    public function add_actions()
    {
        // When restoring a revision, also restore that revisions's revisioned meta.
        //add_action('wp_restore_post_revision', [$this, '_wp_restore_post_revision_meta'], 10, 2);
        // When creating or updating an autosave, save any revisioned meta fields.
        //add_action('wp_creating_autosave', [$this, '_wp_autosave_post_revisioned_meta_fields']);
        //add_action('wp_before_creating_autosave', [$this, '_wp_autosave_post_revisioned_meta_fields']);
        // When creating a revision, also save any revisioned meta.
        //add_action('_wp_put_post_revision', [$this, 'create_revision']);

        add_action('pre_post_update', [$this, 'pre_post_update'], 2, 1);
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
