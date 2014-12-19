<?php

#error_reporting(-1);
#ini_set('display_errors',1);

///////////////////////////////////////////////////////////////////////////////

// Force SHORT INIT
define( 'SHORTINIT', true );

// Require the wp-load.php file
require( realpath( __DIR__ .'/../../../') . '/wp-load.php' );

// Include global $wpdb Class for use
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

die( strval( $current_hits ) );

///////////////////////////////////////////////////////////////////////////////

