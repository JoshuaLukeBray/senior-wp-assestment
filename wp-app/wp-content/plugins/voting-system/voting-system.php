<?php
/**
 * Plugin Name: Voting System
 * Description: A simple voting system.
 * Version: 1.0
 * Author: Joshua Bray
 * Author URI: findcoexam
 */

// Prevent direct file access
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class VotingSystemPlugin {
    public function __construct() {
        add_action('admin_menu', array($this, 'joshplugin_menu'));
        add_action('admin_init', array($this, 'joshplugin_settings'));
        register_activation_hook( __FILE__, array($this, 'create_voting_table'));
        add_action( 'wp_enqueue_scripts', array($this, 'enqueue_voting_scripts'));
        add_action( 'wp_enqueue_scripts', array($this, 'enqueue_voting_styles'));
        add_action( 'wp_ajax_nopriv_submit_vote', array($this, 'submit_vote'));
        add_action( 'wp_ajax_submit_vote', array($this, 'submit_vote'));
        add_filter( 'the_content', array($this, 'add_voting_buttons'));
        add_action( 'add_meta_boxes', array($this, 'add_voting_results_meta_box'));
    }

    public function joshplugin_menu() {
        add_options_page(
            'My Plugin Settings', // Page title
            'Simple Voting Plugin Settings', // Menu title
            'manage_options', // Capability
            'joshplugin', // Menu slug
            array($this, 'joshplugin_settings_page') // Function to display the settings page
        );
    }

    public function joshplugin_settings_page() {
        ?>
        <div class="wrap">
            <h1>READ ALL ABOUT IT</h1>
            <p>Here you could have various configuration settings for the Simple Voting Plugin.</p>
            <p>Once activated this plugin will appear at the bottom of each article.</p>
            <p>A user can vote whether the article was helpful or not.</p>
            <p>To turn off the plugin simply deactivate it from plugins.</p>       
        </div>
        <?php
    }

    public function joshplugin_settings() {
        register_setting('joshplugin-settings', 'my_setting');
    }

    public function create_voting_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'voting_system';

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id mediumint(9) NOT NULL,
            ip_address varchar(55) NOT NULL,
            vote tinyint(1) NOT NULL,
            UNIQUE KEY id (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    public function enqueue_voting_scripts() {
        wp_enqueue_script( 'voting-system', plugins_url( '/voting-system.js', __FILE__ ), array( 'jquery' ), '1.0', true );
        wp_localize_script( 'voting-system', 'voting_system', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        ) );
    }

    public function enqueue_voting_styles() {
        wp_enqueue_style( 'voting-system', plugins_url( '/voting-system.css', __FILE__ ), array(), '1.0' );
    }

    public function submit_vote() {
        global $wpdb;

        $post_id = intval( $_POST['post_id'] );
        $vote = intval( $_POST['vote'] );
        $ip_address = $_SERVER['REMOTE_ADDR'];

        $table_name = $wpdb->prefix . 'voting_system';

        //REMOVE THIS CODE TO TEST THE VOTING SYSTEM
        // Check if the user has already voted
        $existing_vote = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE post_id = %d AND ip_address = %s",
            $post_id,
            $ip_address
        ) );

        if( $existing_vote ) {
            // The user has already voted
            wp_send_json_error( 'You have already voted.', array( 'vote' => $existing_vote->vote ) );
        } else {
            // Insert the vote into the database
            $wpdb->insert(
                $table_name,
                array(
                    'post_id' => $post_id,
                    'ip_address' => $ip_address,
                    'vote' => $vote
                ),
                array( '%d', '%s', '%d' )
            );

            // Calculate the new voting results
            $percentages = $this->calculate_voting_results($post_id);

            wp_send_json_success( array('message' => 'Thank you for your vote!', 'results' => $percentages ));
        }

        wp_die();
    }

    public function calculate_voting_results( $post_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'voting_system';

        $total_votes = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE post_id = %d",
            $post_id
        ) );

        $positive_votes = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE post_id = %d AND vote = 1",
            $post_id
        ) );

        $negative_votes = $total_votes - $positive_votes;

        if( $total_votes > 0 ) {
            $positive_percentage = round( ( $positive_votes / $total_votes ) * 100 );
            $negative_percentage = 100 - $positive_percentage;
        } else {
            $positive_percentage = 0;
            $negative_percentage = 0;
        }

        return array( 'yes' => $positive_percentage, 'no' => $negative_percentage );
    }

    public function add_voting_buttons( $content ) {
        if( is_single() ) {
            $post_id = get_the_ID();
            $percentages = $this->calculate_voting_results( $post_id );
            
    
            $content .= '<div class="voting-buttons">';
            $content .= '<div class="article-helpful vote-message" data-post-id="' . $post_id . '">WAS THIS ARTICLE HELPFUL?<br><button class="vote-button" data-vote="1">YES</button>';
            $content .= '<button class="vote-button" data-vote="0">NO</button></div>';
            $content .= '<div class="article-helpful">THANK YOU FOR YOUR FEEDBACK! ';
            $content .= '<div class="borderYes"><span class="happysmily"></span><span class="datatarget1">' . $percentages['yes'] .'%</span></div><div class="border"><span class="sadsmily"></span><span class="datatarget2">' . $percentages['no'] . '%</span></div>';
            $content .= '</div>';
            $content .= '<div class="article-helpful">THANK YOU FOR YOUR FEEDBACK! ';
            $content .= '<div class="border"><span class="happysmily"></span><span class="datatarget1">' . $percentages['yes'] .'%</span></div><div class="borderYes"><span class="sadsmily"></span><span class="datatarget2">' . $percentages['no'] . '%</span></div>';
            $content .= '</div>';
        }
    
        return $content;
    }

    public function add_voting_results_meta_box() {
        add_meta_box(
            'voting_results',
            'Voting Results',
            array($this, 'render_voting_results_meta_box'),
            'post',
            'side',
            'high'
        );
    }

    public function render_voting_results_meta_box( $post ) {
        $percentages = $this->calculate_voting_results( $post->ID );

        echo '<p>' . $percentages['yes'] . '% of people voted yes.</p>';
        echo '<p>' . $percentages['no'] . '% of people voted no.</p>';
    }
}

new VotingSystemPlugin();