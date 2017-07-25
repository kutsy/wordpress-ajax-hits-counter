<?php

#error_reporting(-1);
#ini_set('display_errors',1);

///////////////////////////////////////////////////////////////////////////////

// Force SHORT INIT
define( 'SHORTINIT', true );

function sendHTTPHeaders() 
{
    // Set the HTTP Headers
    @header('Content-Type: text/html; charset=' . get_option('blog_charset'));
    @header('X-Robots-Tag: noindex');
    send_nosniff_header();
    nocache_headers();

}

function loadWP( $path ) 
{
    // Load Wordpress via wp-load.php
    require( $path . '/wp-load.php' );
    //error_log( 'increment_hits_rapid.php : PATH to wp-load.php:' . $path . '/wp-load.php' );
    //sendHTTPHeaders()
}

if( !isset($_GET['path']) || empty($_GET['path']) )
{
    die( '0' );
}    


// Load Wordpres Core, so we can use the API
// Include global $wpdb Class for use
$path = urldecode( $_GET['path'] );
loadWP( $path );
global $wpdb;

///////////////////////////////////////////////////////////////////////////////

// TODO: Don't count hits of admin users

///////////////////////////////////////////////////////////////////////////////

if( !isset($_GET['post_id']) || empty($_GET['post_id']) )
{
    die( '0' );
}    

$post_id = intval( filter_var( $_GET['post_id'], FILTER_SANITIZE_NUMBER_INT ) );

if( empty($post_id) )
{
    die( '0' );
}

///////////////////////////////////////////////////////////////////////////////

// get_post_meta
$current_hits = 
    $wpdb->get_var( 
        $wpdb->prepare( 
            "
                SELECT 
                    meta_value 
                FROM 
                    $wpdb->postmeta 
                WHERE 
                    post_id = %d 
                    AND 
                    meta_key = 'hits' 
                LIMIT 
                    1
            ", 
            $post_id 
            ) 
        );

///////////////////////////////////////////////////////////////////////////////

if( empty($current_hits) ) 
{
    $current_hits = 1;
    
    // insert new
    $wpdb->query(
        $wpdb->prepare(
		    "
                INSERT INTO
                    $wpdb->postmeta
                    (
                        post_id,
                        meta_key,
                        meta_value
                    )
                VALUES
                    (
                        %d,
                        'hits',
                        %d
                    )
		    ",
	        $post_id,
		    $current_hits
            )
        );
}
else
{
    $current_hits++;

    // update_post_meta
    $wpdb->query(
        $wpdb->prepare(
		    "
                UPDATE
                    $wpdb->postmeta
                SET
                    meta_value = %d
                WHERE
                    post_id = %d 
                    AND 
                    meta_key = 'hits'
		    ",
		    $current_hits,
	        $post_id
            )
        );
}

///////////////////////////////////////////////////////////////////////////////

//error_log( 'increment_hits_rapid.php : Post ID: ' . $post_id );
//error_log( 'increment_hits_rapid.php : Hits: ' . $current_hits );
die( strval( $current_hits ) );

///////////////////////////////////////////////////////////////////////////////

