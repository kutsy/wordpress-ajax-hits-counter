<?php

/**
 * Plugin Name: AJAX Hits Counter + Popular Posts Widget
 * Plugin URI: http://wordpress.org/plugins/ajax-hits-counter/
 * Description: Counts page/posts hits via AJAX and display it in admin panel. Ideal for nginx whole-page-caching. Popular Posts Widget included.
 * Version: 0.9.9
 * Author: Roman Telychko
 * Author URI: http://romantelychko.com
*/

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * AJAX_Hits_Counter
 */
class AJAX_Hits_Counter
{
    ///////////////////////////////////////////////////////////////////////////

    protected $settings = array(
        'use_rapid_incrementer'         => 0,
        'dont_count_admins'             => 0,
    );
    
    ///////////////////////////////////////////////////////////////////////////
    
    protected $plugin_title = 'AJAX Hits Counter';
    protected $plugin_alias = 'ajax-hits-counter';

    ///////////////////////////////////////////////////////////////////////////

	/**
	 * AJAX_Hits_Counter::init()
	 *
	 * @return      bool
	 */
    public function init()
    {
        if( is_admin() )
        {
            // load translation
        	load_plugin_textdomain( $this->plugin_alias, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
        
            // admin posts table
            add_filter( 'manage_posts_columns',                         array( $this, 'adminTableColumn' ) );
            add_filter( 'manage_posts_custom_column',                   array( $this, 'adminTableRow' ), 10, 2 );
            add_filter( 'manage_edit-post_sortable_columns',            array( $this, 'adminTableSortable' ) );
            add_filter( 'request',                                      array( $this, 'adminTableOrderBy' ) );    
            
            // admin pages table
            add_filter( 'manage_pages_columns',                         array( $this, 'adminTableColumn' ) );
            add_filter( 'manage_pages_custom_column',                   array( $this, 'adminTableRow' ), 10, 2 );
            add_filter( 'manage_edit-page_sortable_columns',            array( $this, 'adminTableSortable' ) );
            
            // remove cached data on every post save & update hits count
            add_action( 'save_post',                                    array( $this, 'adminSave' ) );
            
            // add in admin menu
            add_filter( 'admin_menu',                                   array( $this, 'adminMenu' ) );
            
            // init admin            
            add_action('admin_init',                                    array( $this, 'adminInit' ) );
                        
            // register importer
            require_once( ABSPATH.'wp-admin/includes/import.php' );
            
            register_importer( 
                __CLASS__.'_Importer',
                $this->plugin_title.': '.__( 'Import from', $this->plugin_alias ).' WP-PostViews',
                __( 'Imports views count (hits) from plugin', $this->plugin_alias ).' <a href="http://wordpress.org/plugins/wp-postviews">WP-PostViews</a> '.__( 'to hits of', $this->plugin_alias ).' <a href="http://wordpress.org/plugins/'.$this->plugin_alias.'/">'.$this->plugin_title.'</a>.',
                array( $this, 'adminImporter' )
                );
        }
        else
        {
            // append script to content
            add_filter( 'the_content',                                  array( $this, 'appendScript' ),       100);
        }

        // register AJAX Hits Counter: Popular Posts Widget
        add_action( 'widgets_init',                                     array( $this, 'register' ) );

        // AJAX increment hits init    
        add_action( 'wp_ajax_nopriv_'.$this->plugin_alias.'-increment', array( $this, 'incrementHits' ) );
        add_action( 'wp_ajax_'.$this->plugin_alias.'-increment',        array( $this, 'incrementHits' ) );
        
        // shortcode
        add_shortcode( 'hits',                                          array( $this, 'getHitsShortcode' ) );
        
        return true;
    }
    
    ///////////////////////////////////////////////////////////////////////////
    
	/**
	 * AJAX_Hits_Counter::register()
	 *
	 * @return      bool
	 */
    public function register()
    {
        register_widget( 'AJAX_Hits_Counter_Popular_Posts_Widget' );
        return true;
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * AJAX_Hits_Counter::getOption()
     *
     * @param       string      $name
     * @return      integer
     */
    public function getOption( $name )
    {
        $temp = intval( preg_replace( '#[^01]#', '', get_option( 'ajaxhc_'.$name, $this->settings[$name]) ) );
        return in_array( $temp, array( 0, 1 ) ) ? $temp : 0;
    }
    
    ///////////////////////////////////////////////////////////////////////////

	/**
	 * AJAX_Hits_Counter::appendScript()
	 *
	 * @param       string      $content
	 * @return      string
	 */
    public function appendScript( $content )
    {
        global $post;
    
        if( is_single() || is_page() ) 
        {
            if( $this->getOption('use_rapid_incrementer')==1 )          // use rapid incrementer
            {
                $incrementer_url = plugin_dir_url( __FILE__ ).'increment-hits.rapid.php?post_id='.$post->ID.'&t=';
            }
            else                                                // use simple incrementer
            {
                $incrementer_url = admin_url( 'admin-ajax.php' ).'?action='.$this->plugin_alias.'-increment&post_id='.$post->ID.'&t=';
            }
        
            $content .=
                '<script type="text/javascript">'.
                    'function ahc_getXmlHttp(){var e;try{e=new ActiveXObject("Msxml2.XMLHTTP")}catch(t){try{e=new ActiveXObject("Microsoft.XMLHTTP")}catch(n){e=false}}if(!e&&typeof XMLHttpRequest!="undefined"){e=new XMLHttpRequest}return e};'.
                    'var ahc_xmlhttp=ahc_getXmlHttp();'.
                    'ahc_xmlhttp.open('.
                        '"GET", "'.$incrementer_url.'"+(parseInt(new Date().getTime()))+"&r="+(parseInt(Math.random()*100000))'.
                        ');'.
                    'ahc_xmlhttp.send(null);'.
                '</script>';
        }
        
        return $content;
    }
    
    ///////////////////////////////////////////////////////////////////////////
    
	/**
	 * AJAX_Hits_Counter::incrementHits()
	 *
	 * @return      void
	 */
    public function incrementHits()
    {
        // Don't count hits of admin users
        #if( is_user_logged_in() && is_admin() && $this->getOption('dont_count_admins')==1 )
        if( is_user_logged_in() && current_user_can( 'manage_options' ) && $this->getOption('dont_count_admins')==1 )
        {
            die( '0' );
        }

        if( !isset($_GET['post_id']) || empty($_GET['post_id']) )
        {
            die( '0' );
        }
        
        if( function_exists('filter_var') )
        {
            $post_id = intval( filter_var( $_GET['post_id'], FILTER_SANITIZE_NUMBER_INT ) );
        }
        else
        {
            $post_id = intval( preg_replace( '#[^0-9]#', '', $_GET['post_id'] ) );
        }
        
        if( empty($post_id) )
        {
            die( '0' );
        }

        $current_hits = get_post_meta( $post_id, 'hits', true );
        
        if( empty($current_hits) ) 
        {
            $current_hits = 0;
        }
        
        $current_hits++;
            
        update_post_meta( $post_id, 'hits', $current_hits );
        
        die( strval( $current_hits ) );
    }
    
    ///////////////////////////////////////////////////////////////////////////

	/**
	 * AJAX_Hits_Counter::getHits()
	 *
	 * @param       integer     $post_id
	 * @param       integer     $hits_count_format
	 * @return      integer
	 */
    public function getHits( $post_id, $hits_count_format = 1 )
    {
        if( function_exists('filter_var') )
        {
            $post_id = intval( filter_var( $post_id, FILTER_SANITIZE_NUMBER_INT ) );
        }
        else
        {
            $post_id = intval( preg_replace( '#[^0-9]#', '', $post_id ) );
        }

        if( empty($post_id) )
        {
            return 0;
        }
        
        $hits = get_post_meta( $post_id, 'hits', true );

        if( empty($hits) ) 
        {
            return 0;
        }

        return $this->hitsCountFormat( intval($hits), $hits_count_format );
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * AJAX_Hits_Counter::hitsCountFormat()
     *
     * @param       integer           $number
     * @param       integer           $format
     * @return      string
     */
    public function hitsCountFormat( $number, $format = 1 )
    {
        $number = intval($number);

        switch( $format )
        {
            default:
            case 1:     // 12345
                return $number;
                break;
            case 2:     // 12,345
                return number_format( $number, 0, '', ',' );
                break;
            case 3:     // 12 345
                return number_format( $number, 0, '', ' ' );
                break;
            case 4:     // 12.345
                return number_format( $number, 0, '', '.' );
                break;
            case 5:     // 12k
            case 6:     // 12K
                $unitElements   = ( $format == 5 ) ? array( '', 'K', 'M', 'G', 'T', 'P' ) : array( '', 'k', 'm', 'g', 't', 'p' );
                $unitItem       = floor( log( intval($number), 1000 ) );

                if( !isset($unitElements[$unitItem]) )
                {
                    $unitItem = count($unitElements);
                }

                return round( ( $number / pow( 1000, $unitItem ) ), 0 ).$unitElements[$unitItem];
                break;
        }
    }
    
    ///////////////////////////////////////////////////////////////////////////

	/**
	 * AJAX_Hits_Counter::getHitsShortcode()
	 *
	 * @param       array           $atts
	 * @return      integer
	 */
    public function getHitsShortcode( $atts )
    {
        $post_id = isset($atts['id']) && !empty($atts['id']) ? $atts['id'] : get_the_ID();
    
        return $this->getHits( $post_id );
    }
    
    ///////////////////////////////////////////////////////////////////////////
    
	/**
	 * AJAX_Hits_Counter::adminInit()
	 *
	 * @return      bool
	 */
    public function adminInit()
    {
        global $current_user;

        if( isset($current_user->roles) && !empty($current_user->roles) && in_array( 'administrator', $current_user->roles ) )
        {
            // add meta box
            add_action( 'add_meta_boxes',                               array( $this, 'adminAddMetaBox' ) );            
        }
    }

    ///////////////////////////////////////////////////////////////////////////
    
	/**
	 * AJAX_Hits_Counter::adminMenu()
	 *
	 * @return      bool
	 */
    public function adminMenu()
    {
	    // create new top-level menu
	    add_menu_page( $this->plugin_title, $this->plugin_title, 'administrator', __FILE__, array( $this, 'adminSettingsPage' ) );

	    // call register settings function
	    add_action( 'admin_init', array( $this, 'adminSettingsRegister' ) );
    }
    
    ///////////////////////////////////////////////////////////////////////////
    
	/**
	 * AJAX_Hits_Counter::adminSettingsPage()
	 *
	 * @return      bool
	 */
    public function adminSettingsPage()
    {
        ///////////////////////////////////////////////////////////////////////

        $this->settings['use_rapid_incrementer']    = $this->getOption('use_rapid_incrementer');
        $this->settings['dont_count_admins']        = $this->getOption('dont_count_admins');

        ///////////////////////////////////////////////////////////////////////
    
        echo(
            '<div class="wrap">'.
                '<h2>'.$this->plugin_title.': '.__( 'Settings', $this->plugin_alias ).'</h2>'.
                    '<form method="post" action="options.php">'
            );
            
        settings_fields( 'ajaxhc' );
        
        echo(
            '<table class="form-table">'.
                '<tr valign="top">'.
                    '<td colspan="2">'.
                        '<input type="checkbox" '.( $this->settings['use_rapid_incrementer']==1 ? ' checked="checked"' : '' ).' name="ajaxhc_use_rapid_incrementer" id="ajaxhc_use_rapid_incrementer" value="1" />'.
                        '<label for="ajaxhc_use_rapid_incrementer">&nbsp;'.__( 'Use very fast (rapid) Hits Counter Script (ATTENTION! Required Wordpress version 3.4 or higher', $this->plugin_alias ).')</label>'.
                    '</td>'.
                '</tr>'.
                '<tr valign="top">'.
                    '<td colspan="2">'.
                        '<input type="checkbox" '.( $this->settings['dont_count_admins']==1 ? ' checked="checked"' : '' ).' name="ajaxhc_dont_count_admins" id="ajaxhc_dont_count_admins" value="1" />'.
                        '<label for="ajaxhc_dont_count_admins">&nbsp;'.__( 'Don\'t count hits of admin users', $this->plugin_alias ).'</label>'.
                    '</td>'.
                '</tr>'.
            '</table>'
            );
            
        submit_button();

        echo(
                '</form>'.
            '</div>'
            );
    }
    
    ///////////////////////////////////////////////////////////////////////////
    
	/**
	 * AJAX_Hits_Counter::adminSettingsPage()
	 *
	 * @return      bool
	 */
    public function adminSettingsRegister()
    {
        // register settings
        register_setting( 'ajaxhc', 'ajaxhc_use_rapid_incrementer' );
        register_setting( 'ajaxhc', 'ajaxhc_dont_count_admins' );
    }
    
    ///////////////////////////////////////////////////////////////////////////

	/**
	 * AJAX_Hits_Counter::adminSave()
	 *
	 * @param       integer     $post_id
	 * @return      bool
	 */
    public function adminSave( $post_id )
    {
        // skip for autosave
        if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
        {
            return;
        }

        // update hits count
        if( isset($_POST['post_type']) && in_array( $_POST['post_type'], array( 'post', 'page' ) ) )
        {    
            $hits = ( isset($_POST['hits']) && !empty($_POST['hits']) ? intval( preg_replace( '/[^0-9]/', '', $_POST['hits'] ) ) : 0 );
            
            if( $hits > 0 )
            {
                $hits_exists = get_post_meta( $post_id, 'hits', true );
                
                if( $hits_exists===false )
                {
                    add_post_meta( $post_id, 'hits', $hits, true );
                }
                else
                {
                    update_post_meta( $post_id, 'hits', $hits );
                }
            }
        }
    
        // clear Popular Posts Widget
        $ahc_ppw = new AJAX_Hits_Counter_Popular_Posts_Widget();
        $ahc_ppw->clearCache();
        
        return true;
    }
    
    ///////////////////////////////////////////////////////////////////////////

	/**
	 * AJAX_Hits_Counter::adminTableColumn()
	 *
	 * @param       array     $column
	 * @return      array
	 */
    public function adminTableColumn( $column )
    {
        $column['hits'] = __( 'Hits', $this->plugin_alias );    

        return $column;
    }
    
    ///////////////////////////////////////////////////////////////////////////

	/**
	 * AJAX_Hits_Counter::adminTableRow()
	 *
	 * @param       string      $column_name
	 * @param       integer     $post_id
	 * @return      string
	 */
    public function adminTableRow( $column_name, $post_id )
    {
        if( $column_name=='hits' )
        {
            $current_hits = get_post_meta( $post_id, 'hits', true );
            
            if( empty($current_hits) ) 
            {
                $current_hits = 0;
            }
            
            echo( $current_hits );
        }
    }
    
    ///////////////////////////////////////////////////////////////////////////

	/**
	 * AJAX_Hits_Counter::adminTableSortable()
	 *
	 * @param       array       $column
	 * @return      array
	 */
    public function adminTableSortable( $column )
    {
        $column['hits'] = 'hits';    

        return $column;
    }
        
    ///////////////////////////////////////////////////////////////////////////

	/**
	 * AJAX_Hits_Counter::adminTableOrderBy()
	 *
	 * @param       array       $vars
	 * @return      array
	 */
    public function adminTableOrderBy( $vars )
    {
	    if( isset($vars['orderby']) && $vars['orderby']=='hits' ) 
	    {
		    $vars = array_merge( 
        		    $vars, 
        		    array(
			            'meta_key'  => 'hits',
			            'orderby'   => 'meta_value_num'
            		    ) 
        		    );
	    }
     
	    return $vars;
    }
    
    ///////////////////////////////////////////////////////////////////////////
    
	/**
	 * AJAX_Hits_Counter::adminAddMetaBox()
	 *
	 * @return      bool
	 */
    public function adminAddMetaBox()
    {
        add_meta_box(
            'hits',
            'Hits count',
            array( $this, 'adminAddMetaBoxPrint' ),
            'post',
            'side',
            'default'
            );
            
        add_meta_box(
            'hits',
            'Hits count',
            array( $this, 'adminAddMetaBoxPrint' ),
            'page',
            'side',
            'default'
            );
            
        return true;
    }
    
    ///////////////////////////////////////////////////////////////////////////
    
	/**
	 * AJAX_Hits_Counter::adminAddMetaBoxPrint()
	 *
	 * @param       string          $post
	 * @param       string          $metabox	 
	 * @return      void
	 */
    public function adminAddMetaBoxPrint( $post, $metabox ) 
    {
        wp_nonce_field( plugin_basename( __FILE__ ), 'ajax_hits_counter_nonce' );
        
        $hits = get_post_meta( $post->ID, 'hits', true );

        echo( 
            '<label for="hits">'.__( 'Hits count', $this->plugin_alias ).'</label>&nbsp;&nbsp;'.
            '<input type="text" name="hits" id="hits" value="'.( !empty($hits) ? $hits : '0' ).'" />' 
            );
    }

    ///////////////////////////////////////////////////////////////////////////
    
	/**
	 * AJAX_Hits_Counter::adminImporter()
	 *
	 * @return      html
	 */
    public function adminImporter() 
    {
        ///////////////////////////////////////////////////////////////////////
	    
	    $html = 
		    '<div class="wrap">'.
		        '<h2>'.$this->plugin_title.': '.__( 'Import from', $this->plugin_alias ).' WP-PostViews</h2>'.
		        '<div class="clear"></div>';
    
        ///////////////////////////////////////////////////////////////////////
    
	    global $wpdb;
	    
        ///////////////////////////////////////////////////////////////////////
        
        if( !isset($_POST['start']) || empty($_POST['start']) )
        {
            ///////////////////////////////////////////////////////////////////

            $q = '
                SELECT
	                count(post_id) as c
                FROM
                    '.$wpdb->postmeta.'
                WHERE	
                    meta_key = \'views\'';

            $total = $wpdb->get_var($q);

            ///////////////////////////////////////////////////////////////////
            
            if( empty($total) )
            {
	            $html .= 
	                '<p>'.__( 'We found', $this->plugin_alias ).' <strong>'.__( 'no items', $this->plugin_alias ).'</strong> '.__( 'to import from WP-PostViews plugin', $this->plugin_alias ).'.</p>'.
	                '<p>'.__( 'Have I hice day', $this->plugin_alias ).' ;-)</p>';
            }
            else
            {
                $html .= 
                    '<p>'.__( 'We found', $this->plugin_alias ).' <strong>'.$total.' '.__( 'items', $this->plugin_alias ).'</strong> '.__( 'to import from WP-PostViews plugin', $this->plugin_alias ).'.</p>'.
                    '<p>'.__( 'To start import please click "Start import" button.', $this->plugin_alias ).'</p>'.
                    '<form method="post">'.
                        wp_nonce_field( plugin_basename( __FILE__ ), 'ajax_hits_counter_nonce', true, false ).
                        '<input type="submit" value="'.__( 'Start import', $this->plugin_alias ).'" class="button" name="start" />'.
                    '</form>';
            }
            
            ///////////////////////////////////////////////////////////////////
        }
        else
        {
            ///////////////////////////////////////////////////////////////////
            
            $q = '
                SELECT
	                post_id,
	                meta_value      as views
                FROM
                    '.$wpdb->postmeta.'
                WHERE	
                    meta_key = \'views\'';

            $results = $wpdb->get_results($q);

            ///////////////////////////////////////////////////////////////////

            if( !empty($results) )
            {
                $status = array(
                    'total'         => count($results),
                    'inserted'      => 0,
                    'updated'       => 0,
                    'skipped'       => 0,
                    );
            
                foreach( $results as $r )
                {                            
                    $hits = get_post_meta( $r->post_id, 'hits', true );

                    if( $hits===false )
                    {
                        add_post_meta( $r->post_id, 'hits', $r->views, true );
                        
                        $status['inserted']++;
                    }
                    else
                    {
                        if( $hits < $r->views )
                        {
                            update_post_meta( $r->post_id, 'hits', $r->views );
                            
                            $status['updated']++;
                        }
                        else
                        {
                            $status['skipped']++;                    
                        }
                    }
                }
                
	            $html .= 
	                '<p>'.__( 'Imported', $this->plugin_alias ).' <strong>'.$status['total'].' '.__( 'items', $this->plugin_alias ).'</strong> '.
	                     '('.__( 'inserted', $this->plugin_alias ).': <strong>'.$status['inserted'].'</strong>, '.__( 'updated', $this->plugin_alias ).': <strong>'.$status['updated'].'</strong>, '.__( 'skipped', $this->plugin_alias ).': <strong>'.$status['skipped'].'</strong>)</p>'.
	                '<p>'.__( 'Thank you for choosing our plugin.', $this->plugin_alias ).'</p>';
            }
            else
            {
	            $html .= 
	                '<p>'.__( 'We found', $this->plugin_alias ).' <strong>'.__( 'no items', $this->plugin_alias ).'</strong> '.__( 'to import from WP-PostViews plugin', $this->plugin_alias ).'.</p>'.
	                '<p>'.__( 'Have I hice day', $this->plugin_alias ).' ;-)</p>';
            }
            
            ///////////////////////////////////////////////////////////////////
        }

        ///////////////////////////////////////////////////////////////////////
        
        $html .= 
	        '</div>';
        
        die( $html );

        ///////////////////////////////////////////////////////////////////////
    }

    ///////////////////////////////////////////////////////////////////////////
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * AJAX_Hits_Counter_Popular_Posts_Widget
 */
class AJAX_Hits_Counter_Popular_Posts_Widget extends WP_Widget 
{
    ///////////////////////////////////////////////////////////////////////////
    
    protected $defaults = array(
        'widget_id'                 => 'ajax_hits_counter_popular_posts_widget',
        'sorting_algorithm'         => 1,               // hits only
        'sorting_coefficient_n'     => 1,               // sorting coefficient of hits
        'sorting_coefficient_k'     => 10,              // sorting coefficient of comments
        'count'                     => 5,               // limit
        'cache_lifetime'            => 3600,            // 1 hour as default
        'date_range'                => 7,               // all time (no time limit)
        'one_element_html'          => "<span class=\"entry-content\">\n  <a href=\"{permalink}\" title=\"{post_title}\">{post_title} ({post_hits})</a>\n</span>",
        'post_type'                 => 1,               // posts only
        'post_category'             => -1,              // any
        'post_category_exclude'     => -3,              // none
        'post_categories_separator' => ', ',
        'post_date_format'          => 'd.m.Y',
        'hits_count_format'         => 1,               // hits count format: "12345"
        );

    ///////////////////////////////////////////////////////////////////////////

    protected $plugin_title = 'AJAX Hits Counter';
    protected $plugin_alias = 'ajax-hits-counter';

    ///////////////////////////////////////////////////////////////////////////

	/**
	 * AJAX_Hits_Counter_Popular_Posts_Widget::__construct()
	 * ( Register widget with WordPress )
	 *
	 * @return      void
	 */
	public function __construct() 
	{	
		///////////////////////////////////////////////////////////////////////
		
		parent::__construct(
	 		$this->defaults['widget_id'],
			$this->plugin_title.': '.__( 'Popular Posts', $this->plugin_alias ),
			array(
			    'description'   => __( 'Displays popular posts/pages counted by', $this->plugin_alias ).' '.$this->plugin_title.'.', 
			    'classname'     => $this->defaults['widget_id'],
			    ),
		    array(
			    'width'     => 800,
			    'height'    => 600,
		    )
		);

		///////////////////////////////////////////////////////////////////////
	}
	
    ///////////////////////////////////////////////////////////////////////////

	/**
	 * AJAX_Hits_Counter_Popular_Posts_Widget::widget()
	 * ( Front-end display of widget. )
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param       array       $args               Widget arguments.
	 * @param       array       $instance           Saved values from database.
     * @return      html
	 */
	public function widget( $args, $instance ) 
	{
		///////////////////////////////////////////////////////////////////////

	    // args
	    $args = array_merge( $this->defaults, $args );
		
        // cache key
        $cache_key = 'ajax_hits_counter_'.dechex(crc32( $args['widget_id'] ));

        // try to get cached data from transient cache
        $cache = get_transient( $cache_key );

        if( !is_array($cache) && !empty($cache) )
        #if( false )
        {
            // cache exists, return cached data
            echo( $cache );            
            return true;
        }
        
        // get popular posts
	    $popular_posts = $this->getPopularPosts( $instance );
	    
		if( empty($popular_posts) )
		{
		    return false;
		}
		
		$title = apply_filters( 'widget_title', $instance['title'] );
 
        $output =
            ( isset($instance['custom_css']) && strlen($instance['custom_css'])>5 ? '<style type="text/css">'.$instance['custom_css'].'</style>' : '' ).
            $args['before_widget'];

		if( !empty( $title ) )
		{
			$output .= $args['before_title'].$title.$args['after_title'];
		}
		
		$output .= $this->getHTML( $popular_posts, $instance );

		$output .= $args['after_widget'];
	
        // store result to cache
        set_transient( $cache_key, $output, $instance['cache_lifetime'] );	
		
		echo( $output );
		
		///////////////////////////////////////////////////////////////////////
	}
	
    ///////////////////////////////////////////////////////////////////////////
	
	/**
	 * AJAX_Hits_Counter_Popular_Posts_Widget::getPopularPosts()
	 * ( Returns Popular Posts )
	 *
	 * @param       array       $args
	 * @return      array
	 */
	protected function getPopularPosts( $args = array() )
	{
		///////////////////////////////////////////////////////////////////////
	
	    global $wpdb;
	    
	    if( isset($args['sorting_algorithm']) )
	    {
            switch( $args['sorting_algorithm'] )
            {
                case 1:         // hits only
                default:
                    $sql_sorting_algorithm = '( m.meta_value + 0 ) DESC,';
                    break;
                
                case 2:         // comments only
                    $sql_sorting_algorithm = '( p.comment_count + 0 ) DESC,';
                    break;
                
                case 3:         // N * hits + K * comments
                    $sql_sorting_algorithm = 
                        '( ( ( m.meta_value + 0 ) * '.( isset($args['sorting_coefficient_n']) && !empty($args['sorting_coefficient_n']) ? $args['sorting_coefficient_n'] : $this->defaults['sorting_coefficient_n'] ).' ) '.
                        '+ ( p.comment_count + 0 ) * '.( isset($args['sorting_coefficient_k']) && !empty($args['sorting_coefficient_k']) ? $args['sorting_coefficient_k'] : $this->defaults['sorting_coefficient_k'] ).' ) DESC,';
                    break;
            }
        }
        else
        {
            $sql_sorting_algorithm = '( m.meta_value + 0 ) DESC,';
        }

		///////////////////////////////////////////////////////////////////////

        // SELECT, FROM, WHERE
        $q = '
            SELECT
	            DISTINCT p.ID,
	            p.post_title,
	            p.post_content,
	            p.post_author,
	            p.post_date,
	            m.meta_value        as post_hits,
	            p.comment_count     as post_comments_count
            FROM
	            '.$wpdb->posts.' p
            JOIN
                '.$wpdb->postmeta.' m ON ( p.ID = m.post_id )
            WHERE	
	            p.post_date_gmt < \''.date( 'Y-m-d H:i:s' ).'\'';

        // date range
        if( isset($args['date_range']) && $args['date_range']<7 )
        {
            switch( $args['date_range'] )
            {
                case 1:
                    $temp_post_date_shift = '-1 day';
                    break;
                    
                case 2:
                    $temp_post_date_shift = '-1 week';
                    break;
                    
                case 3:
                    $temp_post_date_shift = '-1 month';
                    break;
                    
                case 4:
                    $temp_post_date_shift = '-3 months';
                    break;
                    
                case 5:
                    $temp_post_date_shift = '-6 months';
                    break;
                   
                case 6:
                    $temp_post_date_shift = '-1 year';
                    break;
                    
                default:
                    $temp_post_date_shift = false;
            }
            
            if( !empty($temp_post_date_shift) )
            {        
                $q .= '
                    AND
                    p.post_date_gmt >= \''.date( 'Y-m-d H:i:s', strtotime( $temp_post_date_shift ) ).'\'';
            }
        }
        
        // posts status & meta key = hits
        $q .= '
            AND
            p.post_status = \'publish\'
            AND
            m.meta_key = \'hits\'';

        // post type
        if( isset($args['post_type']) )
        {
            switch($args['post_type'])
            {
                case 0:
                    // all types
                    break;
                    
                case 1:
                default:
                    $q .= '
                        AND
                        p.post_type = \'post\'';
                    break;
                
                case 2:
                    $q .= '
                        AND
                        p.post_type = \'page\'';
                    break;
            }
        }
        else
        {
            $q .= '
                AND
                p.post_type = \'post\'';
        }

        // post_category
        if( isset($args['post_category']) )
        {
            $temp_post_category = false;
        
            if( $args['post_category']>0 )
            {
                $temp_post_category = $args['post_category'];
            }
            elseif( $args['post_category']==-2 )
            {
                $temp_post_category = intval( get_query_var('cat') );
            }
            
            if( !empty($temp_post_category) )
            {
                $q .= '
                    AND
                    p.ID IN
                    (
                        SELECT
                            DISTINCT t_r.object_id
                        FROM
                            '.$wpdb->term_relationships.' t_r
                        WHERE
                            t_r.term_taxonomy_id = '.$temp_post_category.'
                    )';
            }
        }

        if( isset($args['post_category_exclude']) && $args['post_category_exclude']>0 )
        {
            $q .= '
                AND
                p.ID NOT IN
                (
                    SELECT
                        DISTINCT t_r.object_id
                    FROM
                        '.$wpdb->term_relationships.' t_r
                    WHERE
                        t_r.term_taxonomy_id = '.$args['post_category_exclude'].'
                )';
        }

        if( isset($args['exclude_postspages_ids']) && strlen($args['exclude_postspages_ids'])>0 )
        {
            $q .= '
                AND
                p.ID NOT IN ( '.$args['exclude_postspages_ids'].' )';
        }

        // ORDER, LIMIT
        $q .= '
            ORDER BY '.
                $sql_sorting_algorithm.
	            'p.post_date_gmt DESC
            LIMIT
                '.$args['count'];

	    return 
	        $wpdb->get_results($q);	
	        
		///////////////////////////////////////////////////////////////////////
	}
	
    ///////////////////////////////////////////////////////////////////////////
	
	/**
	 * AJAX_Hits_Counter_Popular_Posts_Widget::getHTML()
	 * ( Returns HTML of Popular Posts )
	 *
	 * @param       array       $popular_posts
	 * @param       array       $args
	 * @return      string
	 */
	protected function getHTML( $popular_posts = array(), $args = array() )
	{	
		///////////////////////////////////////////////////////////////////////
	
	    if( empty($popular_posts) )
	    {
	        return false;
	    }
	    
		///////////////////////////////////////////////////////////////////////
	    
	    // fix bug in Wordpress :-)
        global $post;
        $tmp_post = $post;
	    
	    // args
	    $args = array_merge( $this->defaults, $args );
	    
	    $excerpt_length_isset = false;
	    
		///////////////////////////////////////////////////////////////////////
	    
	    $html = '<ul>';
	    $c = 1;
	    
	    foreach( $popular_posts as $post )
	    {
	        $post_author_obj = get_userdata( $post->post_author );
	        
	        $post_author_name = $post_author_obj->display_name;
	        $post_author_link = get_author_posts_url( $post_author_obj->ID, $post_author_obj->user_nicename );
	        
	        setup_postdata($post);

	        $temp_html = 
                str_ireplace(
                    array(
	                    '{post_id}',
	                    '{post_title}',
	                    '{post_author}',
	                    '{post_author_link}',
	                    '{permalink}',
	                    '{post_date}',	             
	                    '{post_hits}',       
	                    '{post_comments_count}',
	                    ),
                    array(
                        $post->ID,
                        //$post->post_title,
                        get_the_title(),
                        $post_author_name,
                        $post_author_link,
                        get_permalink($post->ID),
                        date( $args['post_date_format'], strtotime($post->post_date) ),
                        $this->hitsCountFormat( $post->post_hits, $args['hits_count_format'] ),
                        $post->post_comments_count,
                        ),
                    $args['one_element_html']
                    );
                    
            if( preg_match_all( '#(\{thumbnail\-([^\}]+)\})#sim', $temp_html, $matches ) )
            {
                if( isset($matches['2']) && !empty($matches['2']) )
                {
                    foreach( $matches['2'] as $m )
                    {
                        $size = $m;
                    
                        if( preg_match( '#([0-9]+)x([0-9]+)#i', $m, $sizes ) )
                        {
                            if( isset($sizes['1']) && isset($sizes['2']) )
                            {
                                $size = array( $sizes['1'], $sizes['2'] );
                            }
                        }
                        
                        $temp_html = str_ireplace( '{thumbnail-'.$m.'}', get_the_post_thumbnail( $post->ID, $size ), $temp_html );
                    }
                }
            }
            
            if( stripos( $args['one_element_html'], '{post_categories}' )!==false )
            {
                $categories = get_the_category( $post->ID );
                
                if( !empty($categories) )
                {
                    $temp = array();
                
                    foreach( $categories as $category )
                    {
                        $temp[] = '<a href="'.get_category_link( $category->term_id ).'" title="'.esc_attr( $category->cat_name ).'">'.$category->cat_name.'</a>';
                    }
                    
	                $temp_html = str_ireplace( '{post_categories}', join( $args['post_categories_separator'], $temp ), $temp_html );
                }
            }
            
            if( preg_match( '#(\{post\_title\_([0-9]+)\})#sim', $temp_html, $matches ) )
            {
                if( isset($matches['2']) && !empty($matches['2']) )
                {
                    $temp_title_excerpt = get_the_title();
                    $temp_title_excerpt_length = intval($matches['2']);

                    if( $temp_title_excerpt_length > 0 )
                    {
                        $temp_title_excerpt_arr = explode( ' ', $temp_title_excerpt );
                        
                        $temp_title_excerpt = 
                            join( 
                                ' ', 
                                array_slice( 
                                    $temp_title_excerpt_arr, 
                                    0, 
                                    $temp_title_excerpt_length 
                                )
                            );
                        
                        if( count($temp_title_excerpt_arr) > $temp_title_excerpt_length )
                        {
                            $temp_title_excerpt .= '...';
                        }
                    }
                    
                    $temp_html = str_ireplace( $matches['1'], $temp_title_excerpt, $temp_html );
                }
            }

            if( preg_match( '#(\{post\_excerpt\_([0-9]+)\})#sim', $temp_html, $matches ) )
            {
                if( isset($matches['2']) && !empty($matches['2']) )
                {
                    /*
                    $excerpt_length = intval($matches['2']);

                    if( $excerpt_length > 0 )
                    {
                        if( $excerpt_length_isset===false )
                        {
                            add_filter( 'excerpt_length', create_function( '', 'return '.$excerpt_length.';' ), 1024 );
                            
                            $excerpt_length_isset = true;
                        }
                    }
                    
                    $temp_html = str_ireplace( $matches['1'], get_the_excerpt(), $temp_html );
                    */
                    
                    $temp_excerpt = get_the_content();
                    $temp_excerpt_length = intval($matches['2']);

                    if( $temp_excerpt_length > 0 )
                    {
                        $temp_excerpt_arr = explode( ' ', $temp_excerpt );
                        
                        $temp_excerpt = 
                            join( 
                                ' ', 
                                array_slice( 
                                    $temp_excerpt_arr, 
                                    0, 
                                    $temp_excerpt_length 
                                )
                            );
                        
                        if( count($temp_excerpt_arr) > $temp_excerpt_length )
                        {
                            $temp_excerpt .= '...';
                        }
                    }
                    
                    $temp_html = str_ireplace( $matches['1'], $temp_excerpt, $temp_html );
                }            
            }

	        $html .= '<li class="item-num-'.$c.' item-id-'.$post->ID.'">'.$temp_html.'</li>';
	        
	        $c++;
	    }

	    $html .= '</ul>';
	    
		///////////////////////////////////////////////////////////////////////
	    
	    // restore $post (Wordpress bug fixing)
	    wp_reset_postdata();
	    $post = $tmp_post;
	    
		///////////////////////////////////////////////////////////////////////
	    
	    return $html;
	    
		///////////////////////////////////////////////////////////////////////
	}

    ///////////////////////////////////////////////////////////////////////////

    /**
     * AJAX_Hits_Counter_Popular_Posts_Widget::hitsCountFormat()
     *
     * @param       integer           $number
     * @param       integer           $format
     * @return      string
     */
    public function hitsCountFormat( $number, $format = 1 )
    {
        $number = intval($number);

        switch( $format )
        {
            default:
            case 1:     // 12345
                return $number;
                break;
            case 2:     // 12,345
                return number_format( $number, 0, '', ',' );
                break;
            case 3:     // 12 345
                return number_format( $number, 0, '', ' ' );
                break;
            case 4:     // 12.345
                return number_format( $number, 0, '', '.' );
                break;
            case 5:     // 12k
            case 6:     // 12K
                $unitElements   = ( $format == 5 ) ? array( '', 'K', 'M', 'G', 'T', 'P' ) : array( '', 'k', 'm', 'g', 't', 'p' );
                $unitItem       = floor( log( intval($number), 1000 ) );

                if( !isset($unitElements[$unitItem]) )
                {
                    $unitItem = count($unitElements);
                }

                return round( ( $number / pow( 1000, $unitItem ) ), 0 ).$unitElements[$unitItem];
                break;
        }
    }
	
	///////////////////////////////////////////////////////////////////////////
	
	/**
	 * AJAX_Hits_Counter_Popular_Posts_Widget::clearCache()
	 * ( Clear transient widget cache )
	 *
	 * @return      bool
	 */
	public function clearCache()
	{
		///////////////////////////////////////////////////////////////////////
	
	    global $wpdb;
	
	    $q = '
	        SELECT
		        option_name     as name
	        FROM
		        '.$wpdb->options.'
	        WHERE	
	            option_name LIKE \'_transient_ajax_hits_counter_%\'';

	    $transients = $wpdb->get_results($q);
	    
	    if( !empty($transients) )
	    {
	        foreach( $transients as $transient )
	        {
	            delete_transient( str_replace( '_transient_', '', $transient->name ) );
	        }
	    }
	    
	    return true;
	    
		///////////////////////////////////////////////////////////////////////
	}
	
    ///////////////////////////////////////////////////////////////////////////

	/**
	 * AJAX_Hits_Counter_Popular_Posts_Widget::update()
	 * ( Sanitize widget form values as they are saved. )
	 *
	 * @see WP_Widget::update()
	 *
	 * @param       array       $new_instance       Values just sent to be saved.
	 * @param       array       $old_instance       Previously saved values from database.
	 * @return      array                           Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) 
	{
		///////////////////////////////////////////////////////////////////////
	
	    // drop cache
	    $this->clearCache();
	    
		///////////////////////////////////////////////////////////////////////

        $data = array(
            'title'                         => trim( strip_tags( $new_instance['title'] ) ),
            'sorting_algorithm'             => intval( preg_replace( '#[^0-9]#', '', $new_instance['sorting_algorithm'] ) ),
            'sorting_coefficient_n'         => intval( preg_replace( '#[^0-9]#', '', $new_instance['sorting_coefficient_n'] ) ),
            'sorting_coefficient_k'         => intval( preg_replace( '#[^0-9]#', '', $new_instance['sorting_coefficient_k'] ) ),
            'count'                         => intval( preg_replace( '#[^0-9]#', '', $new_instance['count'] ) ),
            'cache_lifetime'                => intval( preg_replace( '#[^0-9]#', '', $new_instance['cache_lifetime'] ) ),
            'date_range'                    => intval( preg_replace( '#[^1-9]#', '', $new_instance['date_range'] ) ),
            'one_element_html'              => trim( $new_instance['one_element_html'] ),
            'post_type'                     => intval( preg_replace( '#[^012]#', '', $new_instance['post_type'] ) ),
            'post_category'                 => intval( preg_replace( '#[^\-0-9]#', '', $new_instance['post_category'] ) ),
            'post_category_exclude'         => intval( preg_replace( '#[^\-0-9]#', '', $new_instance['post_category_exclude'] ) ),
            'post_categories_separator'     => $new_instance['post_categories_separator'],
            'post_date_format'              => trim( strip_tags( $new_instance['post_date_format'] ) ),
            'custom_css'                    => trim(
                                                    strip_tags(
                                                        str_ireplace(
                                                            '#'.$this->id_base.'-__i__',
                                                            '#'.$this->id_base.'-'.$this->number,
                                                            $new_instance['custom_css']
                                                        )
                                                    )
                                                ),
            'exclude_postspages_ids'        => preg_replace( '#[^0-9\,]#', '', $new_instance['exclude_postspages_ids'] ),
            'hits_count_format'             => intval( preg_replace( '#[^12345]#', '', $new_instance['hits_count_format'] ) ),
        );

        if( strlen($data['exclude_postspages_ids'])>0 )
        {
            $temp_ids   = explode( ',', $data['exclude_postspages_ids'] );
            $temp_ids2  = array();

            $data['exclude_postspages_ids'] = '';

            foreach( $temp_ids as $temp_id )
            {
                if( strlen($temp_id)>0 )
                {
                    $temp_id2[] = intval($temp_id);
                }
            }

            if( !empty($temp_id2) )
            {
                $temp_id2 = array_unique($temp_id2);
                sort($temp_id2);

                $data['exclude_postspages_ids'] = join( ',', $temp_id2 );
            }
        }

		///////////////////////////////////////////////////////////////////////

	    // return sanitized data
		return $data;
		
		///////////////////////////////////////////////////////////////////////
	}
	
    ///////////////////////////////////////////////////////////////////////////

	/**
	 * AJAX_Hits_Counter_Popular_Posts_Widget::form()
	 * ( Back-end widget form. )
	 *
	 * @see WP_Widget::form()
	 *
	 * @param       array       $instance           Previously saved values from database.
     * @return      html
	 */
	public function form( $instance ) 
	{
		///////////////////////////////////////////////////////////////////////
	
	    // defaults
	    $title                      = __('Popular Posts');
	    $sorting_algorithm          = $this->defaults['sorting_algorithm'];
	    $sorting_coefficient_n      = $this->defaults['sorting_coefficient_n'];
	    $sorting_coefficient_k      = $this->defaults['sorting_coefficient_k'];
	    $count                      = $this->defaults['count'];
	    $cache_lifetime             = $this->defaults['cache_lifetime'];
	    $date_range                 = $this->defaults['date_range'];
	    $one_element_html           = $this->defaults['one_element_html'];
	    $post_type                  = $this->defaults['post_type'];
	    $post_category              = $this->defaults['post_category'];
        $post_category_exclude      = $this->defaults['post_category_exclude'];
	    $post_categories_separator  = $this->defaults['post_categories_separator'];
	    $post_date_format           = $this->defaults['post_date_format'];
        $custom_css                 = '';
        $exclude_postspages_ids     = '';
        $hits_count_format          = $this->defaults['hits_count_format'];

		///////////////////////////////////////////////////////////////////////
	
		if( isset($instance['title']) && strlen($instance['title'])>1 ) 
		{
			$title = $instance[ 'title' ];
		}

		if( isset($instance['sorting_algorithm']) && intval($instance['sorting_algorithm'])>0 ) 
		{
			$sorting_algorithm = intval($instance['sorting_algorithm']);
		}
		
		if( isset($instance['sorting_coefficient_n']) && intval($instance['sorting_coefficient_n'])>0 ) 
		{
			$sorting_coefficient_n = intval($instance['sorting_coefficient_n']);
		}
		
		if( isset($instance['sorting_coefficient_k']) && intval($instance['sorting_coefficient_k'])>0 ) 
		{
			$sorting_coefficient_k = intval($instance['sorting_coefficient_k']);
		}
		
		if( isset($instance['count']) && intval($instance['count'])>0 ) 
		{
			$count = intval($instance['count']);
		}
		
		if( isset($instance['cache_lifetime']) && intval($instance['cache_lifetime'])>0 ) 
		{
			$cache_lifetime = intval($instance['cache_lifetime']);
		}
		
		if( isset($instance['date_range']) && intval($instance['date_range'])>0 ) 
		{
			$date_range = intval($instance['date_range']);
		}

		if( isset($instance['post_type']) ) 
		{
			$post_type = intval($instance['post_type']);
		}

		if( isset($instance['post_category']) ) 
		{
			$post_category = intval($instance['post_category']);
		}

        if( isset($instance['post_category_exclude']) )
        {
            $post_category_exclude = intval($instance['post_category_exclude']);
        }

        if( isset($instance['exclude_postspages_ids']) && strlen($instance['exclude_postspages_ids'])>0 )
        {
            $exclude_postspages_ids = $instance['exclude_postspages_ids'];
        }

        if( isset($instance['custom_css']) )
        {
            $custom_css = $instance['custom_css'];
        }
        else
        {
            $temp_widget_id = $this->id_base.'-'.$this->number;

            $custom_css =
                '#'.$temp_widget_id.' { /* block style */ }'."\n".
                '#'.$temp_widget_id.' .widget-title { /* widget title style */ }'."\n".
                '#'.$temp_widget_id.' ul li .entry-content { /* one item style */ }';
        }

		if( isset($instance['one_element_html']) && strlen($instance['one_element_html'])>1 ) 
		{
			$one_element_html = $instance['one_element_html'];
		}

        if( isset($instance['post_date_format']) && strlen($instance['post_date_format'])>0 )
        {
            $post_date_format = $instance['post_date_format'];
        }

        if( isset($instance['post_categories_separator']) && strlen($instance['post_categories_separator'])>0 )
        {
            $post_categories_separator = $instance['post_categories_separator'];
        }

        if( isset($instance['hits_count_format']) && intval($instance['hits_count_format'])>0 )
        {
            $hits_count_format = intval($instance['hits_count_format']);
        }

		///////////////////////////////////////////////////////////////////////
				
		echo(
            '<style type="text/css">
            .'.$this->defaults['widget_id'].'_div {
                display:block;
                clear:both;
            }
                .'.$this->defaults['widget_id'].'_div textarea {
                    font-family: "Courier New", Courier, monospace, "Lucida Console", Monaco;
                }
                .'.$this->defaults['widget_id'].'_div .'.$this->defaults['widget_id'].'_div_left,
                .'.$this->defaults['widget_id'].'_div .'.$this->defaults['widget_id'].'_div_right {
                    width:388px;
                    float:left;
                    margin:0 20px 0 0;
                    display: block;
                }
                    .'.$this->defaults['widget_id'].'_div .'.$this->defaults['widget_id'].'_div_left div.sorting_coefficient_div {
                        margin:0 0 0 5px;
                        padding:0;
                    }
                .'.$this->defaults['widget_id'].'_div .'.$this->defaults['widget_id'].'_div_right {
                    margin:0;
                    zoom: 1;
                }
                .'.$this->defaults['widget_id'].'_div .'.$this->defaults['widget_id'].'_div_right:after {
                    content: ".";
                    display: block;
                    height: 0;
                    clear: both;
                    visibility: hidden;
                }
                    .'.$this->defaults['widget_id'].'_div .'.$this->defaults['widget_id'].'_div_right ul li {
                        margin:0;
                        padding:0 0 2px 0;
                    }
            </style>'.            
            
		    '<div class="'.$this->defaults['widget_id'].'_div">'.
                '<div class="'.$this->defaults['widget_id'].'_div_left">'.
                    '<p>'.
                        '<label for="'.$this->get_field_id('title').'">'.__( 'Widget title', $this->plugin_alias ).':</label>'.
                        '<input class="widefat" id="'.$this->get_field_id('title').'" name="'.$this->get_field_name('title').'" type="text" value="'.esc_attr($title).'" />'.
                    '</p>'.
                    '<p>'.
                        '<label for="'.$this->get_field_id('sorting_algorithm').'">'.__( 'Sorting algorithm (order by)', $this->plugin_alias ).':</label>'.
                        '<select class="widefat" id="'.$this->get_field_id('sorting_algorithm').'" name="'.$this->get_field_name('sorting_algorithm').'" onChange="return '.$this->defaults['widget_id'].'_sortingAlgorithmOnChange(this.value, \''.$this->get_field_id('sorting_coefficient_div').'\');">'.
                            '<option value="1"'.( $sorting_algorithm<2 || $sorting_algorithm>3 ? ' selected="selected"' : '' ).'>'.__( 'Hits count', $this->plugin_alias ).'</option>'.
                            '<option value="2"'.( $sorting_algorithm==2 ? ' selected="selected"' : '' ).'>'.__( 'Comments count', $this->plugin_alias ).'</option>'.
                            '<option value="3"'.( $sorting_algorithm==3 ? ' selected="selected"' : '' ).'>'.__( 'N * Hits count + K * Comments count', $this->plugin_alias ).'</option>'.
                        '</select>'.
                        '<div '.( $sorting_algorithm==3 ? 'style="display:block;"' : 'style="display:none;"' ).' id="'.$this->get_field_id('sorting_coefficient_div').'" class="sorting_coefficient_div">'.
                            'N = <input id="'.$this->get_field_id('sorting_coefficient_n').'" name="'.$this->get_field_name('sorting_coefficient_n').'" type="text" value="'.esc_attr($sorting_coefficient_n).'" /><br />'.
                            'K = <input id="'.$this->get_field_id('sorting_coefficient_k').'" name="'.$this->get_field_name('sorting_coefficient_k').'" type="text" value="'.esc_attr($sorting_coefficient_k).'" />'.
                        '</div>'.
                    '</p>'.
                    '<p>'.
                        '<label for="'.$this->get_field_id('count').'">'.__( 'Display count', $this->plugin_alias ).':</label>'.
                        '<input class="widefat" id="'.$this->get_field_id('count').'" name="'.$this->get_field_name('count').'" type="text" value="'.esc_attr($count).'" />'.
                    '</p>'.
                    '<p>'.
                        '<label for="'.$this->get_field_id('cache_lifetime').'">'.__( 'Cache lifetime (in seconds)', $this->plugin_alias ).':</label>'.
                        '<input class="widefat" id="'.$this->get_field_id('cache_lifetime').'" name="'.$this->get_field_name('cache_lifetime').'" type="text" value="'.esc_attr($cache_lifetime).'" />'.
                    '</p>'.
                    '<p>'.
                        '<label for="'.$this->get_field_id('post_type').'">'.__( 'Posts types', $this->plugin_alias ).':</label>'.
                        '<select class="widefat" id="'.$this->get_field_id('post_type').'" name="'.$this->get_field_name('post_type').'" onChange="return '.$this->defaults['widget_id'].'_postTypeOnChange(this.value, \''.$this->get_field_id('post_category_include').'_p\', \''.$this->get_field_id('post_category_exclude').'_p\');">'.
                            '<option value="0"'.( $post_type==0 ? ' selected="selected"' : '' ).'>'.__( 'Posts & Pages', $this->plugin_alias ).'</option>'.
                            '<option value="1"'.( $post_type==1 ? ' selected="selected"' : '' ).'>'.__( 'Posts only', $this->plugin_alias ).'</option>'.
                            '<option value="2"'.( $post_type==2 ? ' selected="selected"' : '' ).'>'.__( 'Pages only', $this->plugin_alias ).'</option>'.
                            //  TODO: add custom types
                        '</select>'.
                    '</p>'.
                    '<p '.( $post_type==1 ? 'style="display:block;"' : 'style="display:none;"' ).' id="'.$this->get_field_id('post_category_include').'_p">'.
                        '<label for="'.$this->get_field_id('post_category_include').'">'.__( 'Include category', $this->plugin_alias ).':</label>'.
                        $this->_dropdownCategories(
                            array(
                                'id'                => $this->get_field_id('post_category_include'),
                                'name'              => $this->get_field_name('post_category'),
                                'selected'          => $post_category,
                                )
                            ).
                    '</p>'.
                    '<p '.( $post_type==1 ? 'style="display:block;"' : 'style="display:none;"' ).' id="'.$this->get_field_id('post_category_exclude').'_p">'.
                        '<label for="'.$this->get_field_id('post_category_exclude').'">'.__( 'Exclude posts from this category', $this->plugin_alias ).':</label>'.
                        $this->_dropdownCategories(
                            array(
                                'id'                => $this->get_field_id('post_category_exclude'),
                                'name'              => $this->get_field_name('post_category_exclude'),
                                'selected'          => $post_category_exclude,
                                'display_any'       => false,
                                'display_current'   => false,
                                'display_none'      => true,
                            )
                        ).
                    '</p>'.
                    '<p>'.
                        '<label for="'.$this->get_field_id('date_range').'">'.__( 'Posts publish date range', $this->plugin_alias ).':</label>'.
                        '<select class="widefat" id="'.$this->get_field_id('date_range').'" name="'.$this->get_field_name('date_range').'">'.
                            '<option value="1"'.( $date_range<=1 ? ' selected="selected"' : '' ).'>'.__( 'Day', $this->plugin_alias ).'</option>'.
                            '<option value="2"'.( $date_range==2 ? ' selected="selected"' : '' ).'>'.__( 'Week', $this->plugin_alias ).'</option>'.
                            '<option value="3"'.( $date_range==3 ? ' selected="selected"' : '' ).'>'.__( 'Month', $this->plugin_alias ).'</option>'.
                            '<option value="4"'.( $date_range==4 ? ' selected="selected"' : '' ).'>'.__( '3 Months', $this->plugin_alias ).'</option>'.
                            '<option value="5"'.( $date_range==5 ? ' selected="selected"' : '' ).'>'.__( '6 Months', $this->plugin_alias ).'</option>'.
                            '<option value="6"'.( $date_range==6 ? ' selected="selected"' : '' ).'>'.__( 'Year', $this->plugin_alias ).'</option>'.
                            '<option value="7"'.( $date_range>=7 ? ' selected="selected"' : '' ).'>'.__( 'All time', $this->plugin_alias ).'</option>'.
                        '</select>'.
                    '</p>'.
                    '<p>'.
                        '<label for="'.$this->get_field_id('exclude_postspages_ids').'">'.__( 'Exclude Posts/Pages IDs (sep. by comma)', $this->plugin_alias ).':</label>'.
                        '<input class="widefat" id="'.$this->get_field_id('exclude_postspages_ids').'" name="'.$this->get_field_name('exclude_postspages_ids').'" type="text" value="'.esc_attr($exclude_postspages_ids).'" />'.
                    '</p>'.
                    '<p>'.
                        '<label for="'.$this->get_field_id('custom_css').'">'.__( 'Custom CSS (remove if unneeded)', $this->plugin_alias ).':</label>'.
                        '<textarea class="widefat" cols="20" rows="6" id="'.$this->get_field_id('custom_css').'" name="'.$this->get_field_name('custom_css').'">'.$custom_css.'</textarea>'.
                    '</p>'.
                '</div>'.
                '<div class="'.$this->defaults['widget_id'].'_div_right">'.
                    '<p>'.
                        '<label for="'.$this->get_field_id('one_element_html').'">'.__( 'HTML of one element/item', $this->plugin_alias ).' ('.__( 'inside tag', $this->plugin_alias ).' <code>&lt;LI&gt;</code>):</label>'.
                        '<textarea class="widefat" cols="20" rows="8" id="'.$this->get_field_id('one_element_html').'" name="'.$this->get_field_name('one_element_html').'">'.$one_element_html.'</textarea>'.
                        __( 'You can use this placeholders', $this->plugin_alias ).':'.
                        '<ul>'.
                            '<li><code>{post_id}</code> - '.__( 'Post ID', $this->plugin_alias ).'</li>'.
                            '<li><code>{post_title}</code> - '.__( 'Post title', $this->plugin_alias ).'</li>'.
                            '<li><code>{post_title_N}</code> - '.__( 'Post title, where', $this->plugin_alias ).' <code>N</code> - '.__( 'is words count', $this->plugin_alias ).'<br />&nbsp;&nbsp;'.__( 'For example', $this->plugin_alias ).': <code>{post_title_16}</code></li>'.
                            '<li><code>{post_excerpt_N}</code> - '.__( 'Post excerpt, where', $this->plugin_alias ).' <code>N</code> - '.__( 'is words count', $this->plugin_alias ).'<br />&nbsp;&nbsp;'.__( 'For example', $this->plugin_alias ).': <code>{post_excerpt_10}</code> '.__( 'or', $this->plugin_alias ).' <code>{post_excerpt_255}</code></li>'.
                            '<li><code>{post_author}</code> - '.__( 'Post author name', $this->plugin_alias ).'</li>'.
                            '<li><code>{post_author_link}</code> - '.__( 'Post author link', $this->plugin_alias ).'</li>'.
                            '<li><code>{permalink}</code> - '.__( 'Post link', $this->plugin_alias ).'</li>'.
                            '<li><code>{post_date}</code> - '.__( 'Post date', $this->plugin_alias ).'</li>'.
                            '<li><code>{thumbnail-[medium|...|64x64]}</code> - '.__( 'Post thumbnail with size', $this->plugin_alias ).'<br />&nbsp;&nbsp;'.__( 'For example', $this->plugin_alias ).': <code>{thumbnail-large}</code> '.__( 'or', $this->plugin_alias ).' <code>{thumbnail-320x240}</code>'.
                            '<li><code>{post_categories}</code> - '.__( 'Links to post categories with', $this->plugin_alias ).' <code>'.$post_categories_separator.'</code> '.__( 'as separator', $this->plugin_alias ).'</li>'.
                            '<li><code>{post_hits}</code> - '.__( 'Post hits, counted by this plugin', $this->plugin_alias ).'</li>'.
                            '<li><code>{post_comments_count}</code> - '.__( 'Post comments count', $this->plugin_alias ).'</li>'.
                        '</ul>'.
                    '</p>'.
                    '<p>'.
                        '<label for="'.$this->get_field_id('post_date_format').'">'.__( 'Date format', $this->plugin_alias ).' ('.__( 'for more info see', $this->plugin_alias ).' <a href="http://php.net/manual/en/function.date.php" target="_BLANK">'.__( 'date() manual', $this->plugin_alias ).'</a>):</label>'.
                        '<input class="widefat" id="'.$this->get_field_id('post_date_format').'" name="'.$this->get_field_name('post_date_format').'" type="text" value="'.esc_attr($post_date_format).'" />'.
                    '</p>'.
                    '<p>'.
                        '<label for="'.$this->get_field_id('post_categories_separator').'">'.__( 'Categories separator (if more than one)', $this->plugin_alias ).':</label>'.
                        '<input class="widefat" id="'.$this->get_field_id('post_categories_separator').'" name="'.$this->get_field_name('post_categories_separator').'" type="text" value="'.esc_attr($post_categories_separator).'" />'.
                    '</p>'.
                    '<p>'.
                        '<label for="'.$this->get_field_id('hits_count_format').'">'.__( 'Hits count format', $this->plugin_alias ).':</label>'.
                        '<select class="widefat" id="'.$this->get_field_id('hits_count_format').'" name="'.$this->get_field_name('hits_count_format').'">'.
                            '<option value="1"'.( $hits_count_format<=1 ? ' selected="selected"' : '' ).'>12345</option>'.
                            '<option value="2"'.( $hits_count_format==2 ? ' selected="selected"' : '' ).'>12,345</option>'.
                            '<option value="3"'.( $hits_count_format==3 ? ' selected="selected"' : '' ).'>12 345</option>'.
                            '<option value="4"'.( $hits_count_format==4 ? ' selected="selected"' : '' ).'>12.345</option>'.
                            '<option value="5"'.( $hits_count_format==5 ? ' selected="selected"' : '' ).'>12k</option>'.
                            '<option value="6"'.( $hits_count_format>=6 ? ' selected="selected"' : '' ).'>12K</option>'.
                        '</select>'.
                    '</p>'.
                '</div>'.
		    '</div>'.
            '<script type="text/javascript">                       
                function '.$this->defaults['widget_id'].'_sortingAlgorithmOnChange( val, div_id )
                {
                    if( val==3 )
                    {
                        document.getElementById(div_id).style.display = "block";
                    }
                    else
                    {
                        document.getElementById(div_id).style.display = "none";
                    }
                    
                    return true;
                }
                
                function '.$this->defaults['widget_id'].'_postTypeOnChange( val, p_category_include_id, p_category_exclude_id )
                {
                    if( val==1 )
                    {
                        document.getElementById(p_category_include_id).style.display = "block";
                        document.getElementById(p_category_exclude_id).style.display = "block";
                    }
                    else
                    {
                        document.getElementById(p_category_include_id).style.display = "none";
                        document.getElementById(p_category_exclude_id).style.display = "none";
                    }
                    
                    return true;
                }
            </script>'
		    );
		    
		///////////////////////////////////////////////////////////////////////
	}

    ///////////////////////////////////////////////////////////////////////////
	
	/**
	 * AJAX_Hits_Counter_Popular_Posts_Widget::_dropdownCategories()
	 * ( Dropdown categories )
     *
	 * @param       array       $args
	 * @return      string      $html
	 */
	private function _dropdownCategories( $args = array() )
	{
        $args = array_merge(
            array(
                'id'                => 'categories_'.uniqid(),
                'name'              => 'categories_'.uniqid(),
                'selected'          => false,
                'class'             => 'widefat',
                'display_any'       => true,
                'display_current'   => true,
                'display_none'      => false,
                ),
            $args
            );
            
		///////////////////////////////////////////////////////////////////////

        $html = 
            '<select id="'.$args['id'].'" name="'.$args['name'].'" class="'.$args['class'].'">'.
                ( $args['display_any']          ? '<option value="-1"'.( $args['selected']==-1 ? ' selected="selected"' : '' ).'>'.__( 'Any', $this->plugin_alias ).'</option>'                          : '' ).
                ( $args['display_current']      ? '<option value="-2"'.( $args['selected']==-2 ? ' selected="selected"' : '' ).'>'.__( 'Current Category / Any', $this->plugin_alias ).'</option>'       : '' ).
                ( $args['display_none']         ? '<option value="-3"'.( $args['selected']==-3 ? ' selected="selected"' : '' ).'>'.__( 'None', $this->plugin_alias ).'</option>'                         : '' );
                
		///////////////////////////////////////////////////////////////////////

        $categories_levels = array();

        $categories_sortbyid = get_categories(
            array(  
                'type'                     => 'post',
	            'orderby'                  => 'id',
	            'order'                    => 'ASC',
	            'hide_empty'               => 0,
	            'hierarchical'             => 1,
            )
        );

        foreach( $categories_sortbyid as $c )
        {
            $categories_levels[ $c->cat_ID ] = ( isset($categories_levels[ $c->category_parent ]) ? ( $categories_levels[ $c->category_parent ] + 1 ) : 1 );
        }
        
        unset( $categories_sortbyid );

		///////////////////////////////////////////////////////////////////////

        $categories = get_categories(
            array(  
                'type'                     => 'post',
	            'orderby'                  => 'name',
	            'order'                    => 'ASC',
	            'hide_empty'               => 0,
	            'hierarchical'             => 1,
            )
        );

		///////////////////////////////////////////////////////////////////////
            
        foreach( $categories as $c )
        {
            $html .= 
                '<option value="'.$c->cat_ID.'"'.( $args['selected']==$c->cat_ID ? ' selected="selected"' : '' ).'>'.
                    ( $categories_levels[$c->cat_ID]>1 ? str_repeat( '-', $categories_levels[$c->cat_ID] ).' ' : '' ).$c->cat_name.
                '</option>';
        }
        
        $html .=
            '</select>';
                
        return $html;
	}

    ///////////////////////////////////////////////////////////////////////////
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

// init Ajax Hits Counter
$ahc = new AJAX_Hits_Counter();
$ahc->init();

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function ajax_hits_counter_get_hits( $post_id, $hits_count_format = 1 )
{
    $ahc = new AJAX_Hits_Counter();
    
    return 
        $ahc->getHits( $post_id, $hits_count_format );
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
