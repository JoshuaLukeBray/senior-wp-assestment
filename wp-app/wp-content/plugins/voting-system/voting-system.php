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

register_activation_hook( __FILE__, 'create_voting_table' );

//create table to store votes and IPs
function create_voting_table() {
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

add_action( 'wp_enqueue_scripts', 'enqueue_voting_scripts' );

//get the scripts ready for jquery AJAX
function enqueue_voting_scripts() {
    wp_enqueue_script( 'voting-system', plugins_url( '/voting-system.js', __FILE__ ), array( 'jquery' ), '1.0', true );
    wp_localize_script( 'voting-system', 'voting_system', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
    ) );
}

add_action( 'wp_enqueue_scripts', 'enqueue_voting_styles' );

//get styles ready for the CSS
function enqueue_voting_styles() {
    wp_enqueue_style( 'voting-system', plugins_url( '/voting-system.css', __FILE__ ), array(), '1.0' );
}

add_action( 'wp_ajax_nopriv_submit_vote', 'submit_vote' );
add_action( 'wp_ajax_submit_vote', 'submit_vote' );

//get vote from front end and deal with the data respsonses 
function submit_vote() {
    global $wpdb;

    $post_id = intval( $_POST['post_id'] );
    $vote = intval( $_POST['vote'] );
    $ip_address = $_SERVER['REMOTE_ADDR'];

    $table_name = $wpdb->prefix . 'voting_system';

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
        $percentages = calculate_voting_results( $post_id );

        wp_send_json_success( array('message' => 'Thank you for your vote!', 'results' => $percentages ));
    }

    wp_die();
}
//Calculate the voting results
function calculate_voting_results( $post_id ) {
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

add_filter( 'the_content', 'add_voting_buttons' );

//html to output in front end for voting buttons
function add_voting_buttons( $content ) {
    if( is_single() ) {
        $post_id = get_the_ID();
        $percentages = calculate_voting_results( $post_id );
        

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


add_action( 'add_meta_boxes', 'add_voting_results_meta_box' );

//display results for article in back end
function add_voting_results_meta_box() {
    add_meta_box(
        'voting_results',
        'Voting Results',
        'render_voting_results_meta_box',
        'post',
        'side',
        'high'
    );
}
 //render the results accordingly 
function render_voting_results_meta_box( $post ) {
    $percentage = calculate_voting_results( $post->ID );

    echo '<p>' . $percentage . '% of people voted yes.</p>';
}