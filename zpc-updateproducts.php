<?php
/*
Plugin Name: ZPC Update Products
Plugin URI: https://www.zittergie.be/software/wordpress-plugin/zpc-update-products/
Description: Bulk update and bulk edit product prices, stock quantity, product title with percentage changes or fixed amount.  Do inline editing.
Author: Bart Dezitter
Author Email: info@zittergie.be
Author URI: https://www.zittergie.be
Version: 0.65
WC tested up to: 6.1.1
Text Domain: zpc-updateproducts
*/

/*
TODO:
    - Make Reload function to reload all products (instead of reloading page)
    - Chosen Products
    - More Stock (availability) changes
    - Visibility
    - Filter
    - Change settings page to products ?
    - More extensive save function
    - Put javascripts is seperate .js files
*/

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
include_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
require_once(plugin_dir_path( __FILE__ ).'includes/functions.php');

if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$zpc_updateproducts_version = 'Version 0.65';

class zpc_updateproducts_MySettingsPage
{
    /* Holds the values to be used in the fields callback */
    private $options;

    /* Start up */
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
	}

    /* Add options page */
    public function add_plugin_page()
    {
        add_submenu_page( 'edit.php?post_type=product', 'zpc-updateproducts', 'ZPC Update Products', 'manage_options', 'zpc-updateproducts', array( $this, 'create_admin_page' ) ); 
    }
    
    /* Options page callback */
    public function create_admin_page()
    {
        global $zpc_updateproducts_version;
        global $wpdb;
        $license_type = 0;
        $license_valid_until = 0;
        $license_purchase_date = 0;
        $license_ok = 0;
        $times_activated = 0;
            
    	ini_set( 'memory_limit', '2048M' );
		defined( 'WP_MEMORY_LIMIT' ) or define( 'WP_MEMORY_LIMIT', '2048M' );
		$categories = get_terms('product_cat', array('post_type' => array('product'),'hide_empty' => true,'orderby' => 'name','order' => 'ASC'));
		$products = wc_get_products( array( 'limit' => -1 ) );
	    $plugin_zpcpath =  plugin_dir_url( __FILE__ ); 
	    
	    wp_enqueue_style('zpc_updateproducts', $plugin_zpcpath.'css/zpc_updateproducts.css'); 

        /* 
         * When the table does not yet exist, create it 
         * For Pro version: save license in table	
         */
		  $table_name = $wpdb->prefix.'zpcup_updateproducts';
		  if ( $wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name ) {
            $firsttime_running = 1;
            $table_name = 'zpcup_updateproducts';
            $wpdb->query('CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix.$table_name . ' (`id` int(8) NOT NULL AUTO_INCREMENT PRIMARY KEY, `expDate` INT DEFAULT 0, `purchaseDate` INT DEFAULT 0, `timesUsed` INT DEFAULT 0, `licenseKey` varchar(30) NULL, `url` varchar(255) NULL)');
         } else { 
             $firsttime_running = 0; 
             $results = $wpdb->get_results( "SELECT * FROM $table_name"); 
             if(!empty($results))  {
                 foreach($results as $row){
                     $nu = time();
                     $license_valid_until = esc_attr( $row->expDate );
                     $license_purchase_date = esc_attr( $row->purchaseDate );
                     $times_activated = esc_attr( $row->timesUsed );
                     if ( $nu < $license_valid_until ) {
                         $license_ok = 1;
                     } 
                     $license_key = esc_attr( $row->licenseKey );
                     if ( $license_key = '' ) { $license_ok = 0; }
                     if ( !isset($license_key) ) { $license_ok = 0; }
                 }
             }
         }

         /* Set class property */
         if ( isset( $_GET['tab'] ) ) {
            $active_tab = wp_kses_post( $_GET['tab'] );
         }

         if ( $active_tab == 'plugin_options') {
            $this->options = get_option( 'zpc_updateproducts_my_option_name_value' );
         }
            
         if ( $active_tab == 'value_options') {
            $this->options = get_option( 'zpc_updateproducts_my_option_name_value' );
         }

        
        echo '<div class="wrap">';
        echo '<form method="post" action="options.php">';
        echo '<h1>My Settings</h1>';
        
        /* Message if it is the first time the plugin gets started */
          if ( $firsttime_running == 1 ) {
            echo '<div class="notice notice-info">' . "This is the first run of <strong>ZPC Update Products</strong>.<br>The necessary preparation is being done in the background.<br>This notice will not be visible the next time you start <strong>ZPC Update Products</strong>.  If it still shows, it is possible that the plugin won't work properly.  If so, contact us.</div>";
          }
    	  echo '<h2 class="nav-tab-wrapper">';
    	  echo '<a href="?post_type=product&page=zpc-updateproducts&tab=welcome_options" class="nav-tab ' . ( $active_tab == 'welcome_options' ? 'nav-tab-active' : '' ) . '">Welcome</a>';
    	  echo '<a href="?post_type=product&page=zpc-updateproducts&tab=plugin_options" class="nav-tab ' . ( $active_tab == 'plugin_options' ? 'nav-tab-active' : '' ) . '">Edit Products</a>';
    	  echo '<a href="?post_type=product&page=zpc-updateproducts&tab=value_options" class="nav-tab ' . ( $active_tab == 'value_options' ? 'nav-tab-active' : '' ) . '">Value Options</a>';
    	  echo '<a href="?post_type=product&page=zpc-updateproducts&tab=register_options" class="nav-tab ' . ( $active_tab == 'register_options' ? 'nav-tab-active' : '' ) . '">License</a>';
 	  	  echo '</h2>';

    /* Get URLS */    
    $images_dir = plugins_url( 'images/' , __FILE__ );
    $admin_dir = get_admin_url();
    $home_url = get_home_url();
    
    echo '<div class="result"></div>';
    echo '<a target="_blank" href="https://www.zittergie.be/software/wordpress-plugin/zpc-update-products/"><div class="headerimage"><img width="80%" src="' . esc_html( $images_dir ) . 'header-zittergie2.png" width=100%></div></a>';

/*
    WELCOME TAB
                */
if( $active_tab == 'welcome_options' or $active_tab == '' ) {
   echo '<br><em><small>' . esc_html( $zpc_updateproducts_version ) . '</small></em><br><br>';
   echo '<p><h2>Thank you for using Zittergies Update Products Plugin. <em><small>(<a target="_blank" href="https://www.zittergie.be/software/wordpress-plugin/zpc-update-products/">visit website</a>)</small></em></h2></p>';
   echo 'It is the perfect compagnion for the WooCommerce plugin.<br>You can update the price of <em>simple</em> or <variant> products with a given percentage or a given amount.<br>';
   echo '<br><strong>Features:</strong>';
   echo '<div class="myli">';
   echo '<ul>';
   echo '<li>Bulk adjust prices by percentage or fixed value</li>';
   echo '<li>Use different round options</li>';
   echo '<li>Edit stock quantity and product title</li>';
   echo '<li>Filter on products or categories</li>';
   echo '<li>Adjust prices for seperate products</li>';
   echo '<li>Preview changes before applying</li>';
   echo '<li>Visit product page</li>';
   echo '<li>Visit product edit page</li>';
   
   echo '</ul>';
   echo '</div>';
   echo '<br><strong>Todo:</strong>';
   echo '<div class="myli">';
   echo '<ul>';
   echo '<li>Filter on more than categories</li>';
   echo '<li>Change sale/regular price & date</li>';
   echo '<li>apply all functionality to variations too</li>';
   echo '</ul>';
   echo '</div><br>';
   echo '<h2>This plugin is free for basic use.</h2>The only difference with a paid license is that there is no waiting cycle before changes are applied.<br>Please consider a paid license to support the development.';
}

/*
    EDIT PRODUCTS TAB
                */
if( $active_tab == 'plugin_options') {
    settings_fields( 'zpc_updateproducts_my_option_group_value' );
    /* Default value round decimals, or read saved settings */
    $use_rounddecimal = 1; 
    $rounddecimal = 2;
    if ( isset( $this->options['zpc_rounddecimal'] ) ) {  $rounddecimal = esc_attr( $this->options['zpc_rounddecimal'] ); }
    if ( isset( $this->options['zpc_cbrounddecimal'] ) ) {
        if ( esc_attr( $this->options['zpc_cbrounddecimal'] ) == 'YES' ) { $use_rounddecimal = 1; } else { $use_rounddecimal = 0; }
    }
    /* End Default values */
  	echo '<h2>Plugin Options</h2>';
	echo '<p>Choose how you want to manipulate/update the prices with the options below.';
	if ( $use_rounddecimal == 0 ) { echo "<br>New prices will not be rounded."; } else { echo "<br>New prices will be rounded on " . esc_html( $rounddecimal ) . " decimals."; } 
	
	if ( esc_attr($this->options['zpc_cb_roundto_values'] ) == 'YES' ) { 
	    $roundto_values = 1; 
	    $sb_roundto_how = 4;
	    if ( isset($this->options['zpc_rounddecimal_to'] ) ) { $rounddecimalto = esc_attr( $this->options['zpc_rounddecimal_to'] ); } else { $rounddecimalto = '0.95'; }
	    if ( isset($this->options['zpc_sb_roundto_how'] ) ) { $sb_roundto_how = esc_attr( $this->options['zpc_sb_roundto_how'] ); } else { $sb_roundto_how  = 0; }
	    if  ( $sb_roundto_how  == 0 ) { $howto_string = 'Keep Integer'; }  /* Keep the full integer, and add round_to value */
	    if  ( $sb_roundto_how  == 1 ) { $howto_string = 'Always Up'; }	  /* When rounding price have to be the same or higher */	
	    if  ( $sb_roundto_how  == 2 ) { $howto_string = 'Always Down'; }   /* When rounding price have to be the same or lower */
	    if  ( $sb_roundto_how  == 3 ) { $howto_string = 'Nearest'; }		  /* Round to the nearest value */
	    if  ( $sb_roundto_how  == 4 ) { $howto_string = 'Factor'; }		  /* Round to the factor chosen, Does round up */
	    echo " Rounding will follow <code>" . esc_attr( $howto_string )  . "</code> to " . esc_html( $rounddecimalto ) . "."; 
	} else { 
	    echo " No extra rounding options will be applied.";
	    $roundto_values = 0;
	}
	echo ' <small><em>(This behaviour can be changed in the <a href="?page=zpc-updateproducts-setting-admin&tab=value_options"><strong>VALUE OPTIONS</strong></a>-tab.)</em></small>';
    echo '</p>';
    echo '<div class="zpc-info">';
    echo '<table class="zpctable">';
    echo '<tr>';
    echo '<td scope="row"><strong>Method for Changing Price:</strong></td>';
    echo '<td><select name="priceMethodChange" id="priceMethodChange"><option value="by_percent" selected>Percentage</option><option value="by_fixed">Fixed</option></select></td>';
    echo '<td><strong>Percentage:</strong> Default option.  Changes the prices with the chosen percentage.<br><strong>Fixed:</strong> Changes the price with the given amount.</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td scope="row"><strong>Use value to change:</strong></td>';
    echo '<td><input type="number" name="pctChange" id="pctChange" value="0" step="0.01"></td>';
    echo '<td>Enter the amount the price needs to be changed using the method chosen above.  Use negative numbers for a decrease in price. Use positive numbers for an increase in price.</td>';
    echo '</tr>';
    echo '</table>';
    echo '<hr>';
    echo '<table class="zpctable">';
    echo '<tr>';
    echo '<td><strong>Select products:</strong></td>';
 	 echo '<td><input type="radio" checked value="by_categories" name="price_change_method" id="by_categories">';
    echo '<label for="by_categories">Categories</label> &nbsp;&nbsp;';
	 echo ' <input type="radio" value="by_products" name="price_change_method" id="by_products">';
    echo '<label for="by_products">Specific Products</label></td>';
    echo '<td></td>';
    echo '</tr>';
    echo '<tr id="method_by_categories" class="methodby">';
	echo '<td  valign="top"> <br><strong>Select categories:<strong></td>';
	echo '<td>';
	echo '<div class="tablediv" id="testing">';
	/*Create select box with all catagories */
/*	echo' <select id="zpc_selectedProducts" name="zpc_selectedProducts[]" multiple="multiple">';
	foreach ($categories as $key => $category) {
	    $term = get_term_by( 'id', $category->parent, 'product_cat', 'ARRAY_A' );
	    $product_category = $term['name']; 
		echo '<option value="' . esc_attr( $category->term_id ) . '">' . esc_html($category->name) . ' (' . esc_html( $product_category ) . ')</option>';
	} 
	echo '</select>';*/
	
	/*
	 * CATEGORY
	 */
	 
	echo '<div id="gekozencategories"></div>';
    echo '<input class="filteredit" id="Ei_filter_category" type="text" name="search" placeholder=" Search category ...."><br>';
    echo ' <label class="submit button button-primary" id="closecategorylist" onclick="zpc_updateproducts_closecategorylist();">Hide/Show list</label> &nbsp;&nbsp;'; 
    echo ' <label class="submit button button-primary" id="clearcategorylist" onclick="zpc_updateproducts_clearcategorylist();">Clear all selected</label> '; 
    
    /* Create a searchable/filter table with all categories */
    echo '<div class="selectdiv">';
	    echo '<table id="Ti_category">';
	    $i = 0;  
        foreach ( $categories as $category ) {
            $term = get_term_by( 'id', $category->parent, 'product_cat', 'ARRAY_A' );
	        $product_category = $term['name']; 
	        $category_name[ $category->term_id ] = esc_html( $category->name ) ;
            echo '<tr><td><input id="cb_category_' . esc_html( $i ) . '" class="cb-category" type="checkbox" name="cb_category[]" value="' . esc_html( $category->term_id )  . '" onClick="checkbox_category();"><label for="cb_category_' . esc_html( $i ) . '">' . esc_html( $category->name ) . ' (' . esc_html( $product_category ) . ')</label></td></tr>'; 
            $i++;
        }
        echo '</table>';
    echo '</div>';
    /* END CATEGORIES */
	
	echo '</div>';
	echo '</td>';
	echo '<td></td>';
	echo '</tr>';
	
	/*
	 * SINGLE PRODUCTS
	 */
	 
	echo '<tr id="method_by_products" class="methodby" style="display: none;">';
	echo '<td valign="top"> <br><strong>Please select Products: <strong></td>';
	echo '<td>';
	echo '<div class="tablediv">';
	echo '<div id="gekozenproducten"></div>';
    echo '<input class="filteredit" id="Ei_filter" type="text" name="search" placeholder=" Search products ...."><br>';
    echo ' <label class="submit button button-primary" id="closeproductlist" onclick="zpc_updateproducts_closeproductlist();">Hide/Show list</label> &nbsp;&nbsp;'; 
    echo ' <label class="submit button button-primary" id="clearproductlist" onclick="zpc_updateproducts_clearproductlist();">Clear all selected</label> '; 
    /* Create a searchable/filter table with all products */
    echo '<div class="selectdiv">';
        echo '<table id="Ti_dossiers">';
        $i = 0;  
        foreach ( $products as $product ) {
            echo '<tr><td><input id="cb_dossier_' . esc_html( $i ) . '" class="cb-dossiers"  type="checkbox" name="cb_dossiers[]" value="' . esc_attr( $product->id ) . '" onClick="checkbox();"><label for="cb_dossier_' . esc_html( $i ) . '">' . esc_html( $product->name ) . '</label></td></tr>';
            $i++;
        }
        echo '</table>';
    echo '</div>';
    echo '</td>';
    echo '</div>';
    
    /* END SINGLE PRODUCTS */
    
	echo '</tr>';
    echo '</table>';
    echo '</div>';


   echo '<br><label class="submit button button-primary" id="percentage_submit" onclick="zpc_updateproducts_preview();">Preview</label>';  
   if ( $license_ok != 1 ) { echo '<br><small>Free version. Result will be delayed.  If you want to remove these delays, please get a <a target="_blank" href="https://www.zittergie.be/software/wordpress-plugin/zpc-update-products/">license</a>.</small>'; }
   
   echo '<hr>';
   
     echo '<div id="progress" width="99%" style="display:none">';
   echo '<div class="barcontainer"><div class="bar"></div></div>';
   echo '<code id="progressmelding">Update bezig ...</code>';
   echo "</div>";	
   
   echo '<form method="POST">';
   echo '<div id="ph_Table_allProducts"></div>';
   echo '<br><label class="submit button button-primary" id="save_changes" style="display: none" onclick="zpc_updateproducts_save();">Save Changes</label> '; 
   echo ' <label class="submit button button-primary" id="reload" style="display: none" onclick="zpc_updateproducts_reload();">Reload Changes</label> '; 
   echo '</form>';
   
   /* Create an array with all products */
   $i=0; $zpc_productvariations[] = '';
   foreach ( $products as $product) {
        /*
         *  No need to escape $product -> $products should be escaped during wc_get_products() and the individual values get escaped when saved as new value
         *  TODO:   Change the way products are saved in arrays:
         *          Now we use a numbered array 0 .. x for all products, and for individual products we use a associative array
         *          This should be changed to 1 array (pref. associative ?)
         */
       $zpc_products[ $i ]['name'] = $product->name;
       $zpc_products[ $i ]['id'] = $product->id;
       $zpc_products[ $i ]['sku'] = $product->sku;
       $zpc_products[ $i ]['regular_price'] = $product->regular_price;
       $zpc_products[ $i ]['sale_price'] = $product->sale_price;
       $zpc_products[ $i ]['price'] = $product->price;
       $zpc_products[ $i ]['category_ids'] = $product->category_ids;
       $zpc_products[ $i ]['image_id'] = $product->image_id;
       $zpc_products[ $i ]['manage_stock'] = $product->manage_stock;
       $zpc_products[ $i ]['stock_quantity'] = $product->stock_quantity;
       $zpc_products[ $i ]['slug'] = $product->slug;
       
       if( $product->is_type('variable') ){
            $zpc_products[ $i ]['myvar'] = 1;
            foreach( $product->get_available_variations() as $variation_values ) {
                if ( isset( $variation_values['attributes']) ) {
                    $temp = $variation_values['attributes'];
                    $tempkeys = array_keys($temp);
                    $tempname = '';
                    foreach ( $tempkeys as $tempkey) {
                        $tempname = $tempname . substr($tempkey, 10) . ': ' . $temp[ $tempkey ] . ' ';
                    }
                }
                $i++;
                $zpc_products[ $i ]['name'] = $tempname;
                $zpc_products[ $i ]['id'] = $product->id;
                $zpc_products[ $i ]['sku'] = $product->sku;
                $zpc_products[ $i ]['regular_price'] = $variation_values['display_regular_price'];
                $zpc_products[ $i ]['sale_price'] = $variation_values['display_price'];
                $zpc_products[ $i ]['price'] = $variation_values['display_regular_price'];
                $zpc_products[ $i ]['category_ids'] = $product->category_ids;
                $zpc_products[ $i ]['image_id'] = $product->image_id;
                $zpc_products[ $i ]['manage_stock'] = $product->manage_stock;
             //   $zpc_products[ $i ]['stock_quantity'] = $variation_values->get_stock_quantity();//$product->stock_quantity;
                $zpc_products[ $i ]['stock_quantity'] = $variation_values['max_qty'];
                $zpc_products[ $i ]['slug'] = $product->slug;
                $zpc_products[ $i ]['myvar'] = 2;
                $zpc_products[ $i ]['var_id'] = $variation_values['variation_id'];
                $zpc_products[ $i ]['variation_description'] = $variation_values['variation_description'];
                $zpc_products[ $i ]['display_regular_price'] = $variation_values['display_regular_price'];
                $zpc_products[ $i ]['display_price'] = $variation_values['display_price'];
           }
       } else { $zpc_products[ $i ]['myvar'] = 0; }
       
       
       $i++;
   }
   
   /* Create an associative array with all products 
	*	TODO: Look how we can use just one array
	*			-> Maybe with a lookup function   
	*			-> Not urgent
	*			-> See TODO first array
    */
   foreach ($products as $product) {
       $nid = $product->id;
       $zpc_nproducts[ $nid ]['name'] = $product->name;
       $zpc_nproducts[ $nid ]['id'] = $product->id;
       $zpc_nproducts[ $nid ]['sku'] = $product->sku;
       $zpc_nproducts[ $nid ]['regular_price'] = $product->regular_price;
       $zpc_nproducts[ $nid ]['sale_price'] = $product->sale_price;
       $zpc_nproducts[ $nid ]['price'] = $product->price;
       $zpc_nproducts[ $nid ]['category_ids'] = $product->category_ids;
       $zpc_nproducts[ $nid ]['image_id'] = $product->image_id;
       $zpc_nproducts[ $nid ]['manage_stock'] = $product->manage_stock;
       $zpc_nproducts[ $nid ]['stock_quantity'] = $product->stock_quantity;
       $zpc_nproducts[ $nid ]['slug'] = $product->slug;
       $zpc_nproducts[ $nid ]['allvars'] = '';
       $oldnid = $nid;
       
       if( $product->is_type('variable') ) {        
            foreach( $product->get_available_variations() as $variation_values ){
                if ( isset( $variation_values['attributes']) ) {
                    $temp = $variation_values['attributes'];
                    $tempkeys = array_keys($temp);
                    $tempname = '';
                    foreach ( $tempkeys as $tempkey) {
                        $tempname = $tempname . substr($tempkey, 10) . ': ' . $temp[ $tempkey ] . ' ';
                    }
                }
                $nid = $variation_values['variation_id'];
                $zpc_nproducts[ $oldnid ]['myvar'] = 1;
                $zpc_nproducts[ $oldnid ]['allvars'] = $zpc_nproducts[$oldnid]['allvars'] . $nid . ',' ;
                $zpc_nproducts[ $nid ]['name'] = $tempname;
                $zpc_nproducts[ $nid ]['id'] = $product->id;
                $zpc_nproducts[ $nid ]['sku'] = $product->sku;
                $zpc_nproducts[ $nid ]['regular_price'] = $variation_values['display_regular_price'];
                $zpc_nproducts[ $nid ]['sale_price'] = $variation_values['display_price'];
                $zpc_nproducts[ $nid ]['price'] = $variation_values['display_regular_price'];
                $zpc_nproducts[ $nid ]['category_ids'] = $product->category_ids;
                $zpc_nproducts[ $nid ]['image_id'] = $product->image_id;
                $zpc_nproducts[ $nid ]['manage_stock'] = $product->manage_stock;
                $zpc_nproducts[ $nid ]['stock_quantity'] = $variation_values['max_qty'];
                $zpc_nproducts[ $nid ]['slug'] = $product->slug;
                $zpc_nproducts[ $nid ]['myvar'] = 2;
                $zpc_nproducts[ $nid ]['var_id'] = $variation_values['variation_id'];
                $zpc_nproducts[ $nid ]['variation_description'] = $variation_values['variation_description'];
                $zpc_nproducts[ $nid ]['display_regular_price'] = $variation_values['display_regular_price'];
                $zpc_nproducts[ $nid ]['display_price'] = $variation_values['display_price'];
           }
       }
   }
   
   echo '<br>';
?>

<script>
	    var ajaxurl = "<?php echo admin_url('admin-ajax.php')?>";
	    var products = <?php echo json_encode( $zpc_products ); ?>;
	    var nproducts = <?php echo json_encode( $zpc_nproducts ); ?>;
	    var categories = <?php echo json_encode( $category_name ); ?>;
	    var home_url = "<?php echo esc_html ( $home_url ) ?>";
	    var image_url = "<?php echo esc_html( $images_dir ) ?>";
	    var admin_url = "<?php echo esc_html( $admin_dir ) ?>";
	    var plugin_url = "<?php echo esc_html( $plugin_zpcpath ) ?>";
	    var chosen_category_id = 0;
	    var numberofchosencat = 0;
	    var newPrice = 0.00;
	    var previousRowID = 0;
	    var previousColID = 0;
	    var currentColID = 0;
	    var previousRow = '1';
	    var originalPricePreviousRow = 'F';
	    var originalPriceCurrentRow = 'F';
	    var currentRowID = 1;
	    var usedCloseButton = 0;
	    var currentRow = '';
	    var licenseValid = 0;
	    var timesPreview = 0;
	    var editON = 0;

		jQuery( document ).ready( function() {
			jQuery( 'input[name="price_change_method"]' ).change( function(e) {
				jQuery( '.methodby' ).hide();
				jQuery( '#method_' + jQuery( this ).val() ).show();
			});
			/* FILTER for the category table*/
			jQuery( "#Ei_filter_category" ).on( "keyup", function() {
			    jQuery( "#Ti_category" ).show();
         	    var value = jQuery( this ).val().toLowerCase();
                jQuery( "#Ti_category tr" ).filter( function() {
            	   jQuery( this ).toggle( jQuery( this ).text().toLowerCase().indexOf( value ) > -1);
                });
			});
			/* FILTER for the product table*/
			jQuery( "#Ei_filter" ).on( "keyup", function() {
			    jQuery( "#Ti_dossiers" ).show();
         	    var value = jQuery( this ).val().toLowerCase();
                jQuery( "#Ti_dossiers tr" ).filter( function() {
            	    jQuery( this ).toggle( jQuery( this ).text().toLowerCase().indexOf( value ) > -1);
                });
            });
		});
		/* Close the category table  to search for products */
		function zpc_updateproducts_closecategorylist(){
		    jQuery( "#Ti_category" ).toggle( 'fast' );
		}
		
		/* Close the product table  to search for products */
		function zpc_updateproducts_closeproductlist(){
		    jQuery( "#Ti_dossiers" ).toggle( 'fast' );
		}
		
		/* Clear the category list to search for */
		function zpc_updateproducts_clearcategorylist(){
		    jQuery( ".cb-category" ).prop( 'checked', false);
		    document.getElementById( "gekozencategories" ).innerHTML = '<span class="selectedspan"><span>';
		}
		
		/* Clear the product list to search for */
		function zpc_updateproducts_clearproductlist(){
		    jQuery( ".cb-dossiers" ).prop( 'checked', false);
		    document.getElementById( "gekozenproducten" ).innerHTML = '<span class="selectedspan"><span>';
		}
		
		/* Put all selected category in an array */
		function checkbox_category(){
      	    var checkboxes = document.getElementsByClassName( "cb-category" );
            var checkboxesChecked = [];
            for ( var i = 0; i < checkboxes.length; i++) {
         	    if (checkboxes[ i ].checked) {
         	        console.log(checkboxes[i].value);
                    var temp = '<label class="selectedproduct">' + categories[ checkboxes[i].value ] + '</label>';
                    checkboxesChecked.push( temp );
                }
            }
            document.getElementById( "gekozencategories" ).innerHTML = '<span class="selectedspan">' + checkboxesChecked + '<span>';
        }
        
		/* Put all selected products in an array */
		function checkbox(){
      	    var checkboxes = document.getElementsByClassName( "cb-dossiers" );
            var checkboxesChecked = [];
            for ( var i = 0; i < checkboxes.length; i++) {
         	    if (checkboxes[ i ].checked) {
            	    // checkboxesChecked.push(checkboxes[i].value);
                    var temp = '<label class="selectedproduct">' + products[ i ]['name'] + '</label>';
                    checkboxesChecked.push( temp );
                }
            }
            document.getElementById( "gekozenproducten" ).innerHTML = '<span class="selectedspan">' + checkboxesChecked + '<span>';
        }
            
       /* Funtion to round the values */
       function roundValue( price, how, value ) {
            var myprice = price;
            /* Just keep the integer and add value */
            if ( how == 0 ) {
                myprice = Math.trunc( myprice ) + value;
            }
            /* Round up */
            if ( how == 1 ) {
                mytempprice = Math.trunc( myprice ) + value;
                if ( mytempprice > myprice ) { myprice = mytempprice; }
                if ( mytempprice < myprice ) { myprice = Math.trunc( myprice ) + 1.00 + value; }
                if ( mytempprice == myprice ) { myprice = mytempprice }
            }
            /* Round down */
            if ( how == 2 ) {
                mytempprice = Math.trunc( myprice ) + value;
                if ( mytempprice > myprice ) { myprice = Math.trunc( myprice ) - 1.00 + value; }
                if ( mytempprice < myprice ) { myprice = mytempprice; }
                if ( mytempprice == myprice ) { myprice = mytempprice; }
            }
            /* Round to the nearest */
            if ( how == 3 ) {
                mytempprice = Math.trunc( myprice ) + value;
                mydifference = Math.abs( myprice - mytempprice ) ;
                if ( mydifference > 0.50 ) { myprice = Math.trunc( myprice ) - 1.00 + value; }
                if ( mydifference < 0.50 ) { myprice = Math.trunc( myprice ) + value; }
                if ( mydifference = 0.50 ) { myprice = Math.trunc( myprice ) + value;  }
            }
            /* Round and got to same/upper factor */
            if ( how == 4 ) {
                oldprice = myprice;
                myprice = Math.round( myprice / value ) * value;
                /* Default UP */
                if ( myprice < oldprice )  { myprice = Math.round( ( myprice + value ) / value ) * value; }
            }
            return myprice;
        }
		
		/* Go thru all chosen products and see if they are changed */
		function zpc_getAllProducts(){
		    var isCategory = 0;
		    if ( jQuery( "#by_categories" ).is(":checked") ) { isCategory = 1; } else { isCategory = 0; }
		    
		    jQuery( "#progress" ).hide('fast');
		    
		    var klasse = "";
          var klassenummer = ' class="cellnumber"';
          priceChange = Number( jQuery( "#pctChange" ).val() );
		  priceMethodChange = jQuery( "#priceMethodChange" ).val();
		  var use_RoundDecimal = <?php echo esc_html( $use_rounddecimal ) ?>;
          var rounddecimal = Number(<?php echo esc_html( $rounddecimal ) ?>);
          var rounddecimalto = Number(<?php echo esc_html( $rounddecimalto ) ?>);
          var sb_roundto_how = <?php echo esc_html( $sb_roundto_how ) ?>;
          var RoundtoValues = "<?php echo esc_html( $roundto_values ) ?>";
          if ( sb_roundto_how == '' ) { sb_roundto_how = 0; }

		  /* 
		    Category 
		             */
		  if ( isCategory == 1 ) {
		  //  chosen_category_id = jQuery( "#zpc_selectedProducts" ).val();
		    var checkboxes = document.getElementsByClassName( "cb-category" );
            chosen_category_id = [];
            for ( var i = 0; i < checkboxes.length; i++) {
         	    if (checkboxes[ i ].checked) {
                    chosen_category_id.push( checkboxes[i].value );
                }
            }
		    numberofchosencat = chosen_category_id.length;
		    maxProducts = products.length;
  
		    jQuery( "#ph_Table_allProducts" ).empty();
		    
		    var $table = jQuery( '<table class="tableproducts" id="T_allProducts" width="99%" />' ); 
		    $table.append('<thead><tr><th>ID</th><th width="24px"></th><th>SKU</th><th>Title</th><th>Stock</th><th>Regular Price</th><th>Sale Price</th><th>Price</th><th>New Price</th><th></th></tr></thead>' );
      
		    for ( let i = 0; i < maxProducts; i++ ) {
		        for ( let j = 0; j < numberofchosencat; j++ ) {
                    var all_ids = products[ i ]['category_ids'];
                    var my_id = parseInt( chosen_category_id[ j ] );
                    
                    const propertyValues = Object.values( all_ids );
                    if ( all_ids.includes( my_id ) ) {
                        
                        newPrice = Number( products[ i ]['regular_price'] );
                        oldPrice = newPrice;
                        if ( priceMethodChange == "by_fixed" ) { newPrice = newPrice + priceChange }
                            else { newPrice = newPrice + ( newPrice / 100 * priceChange ) }
                        if ( RoundtoValues == 1 ) { newPrice = roundValue( newPrice, sb_roundto_how, rounddecimalto ); }
                        if ( use_RoundDecimal == 1 ) {
                            newPrice = newPrice.toFixed( rounddecimal );
                        }
                        products[ i ]['new_price'] = newPrice;    
                        $temp_id = products[ i ]['id'];
                        
                        if ( products[ i ]['myvar'] == 2 ) { $myvar = ' var'; $temp_id = products[ i ]['var_id']; } else  { $myvar = ''} 
                        if ( products[ i ]['myvar'] == 1 ) { $myvar = ' hasvar'}
                        if ( newPrice > oldPrice ) { rowClass= ' class="green' + $myvar + '"'; }
		                if ( newPrice < oldPrice ) { rowClass = ' class="red' + $myvar + '"'; }
		                if ( newPrice == oldPrice ) { rowClass = ' class="rij' + $myvar + '"'; }
                        
                        $table.append( '<tr' + rowClass + '><td' + klasse + '><a target="_blank" href="' + home_url + '/products/' + products[i]['slug'] + '">' + $temp_id+ '</a></td><td' + klasse + '><a target="_blank" href="' + admin_url + 'post.php?post=' + products[i]['id']  + '&action=edit"><img height="16" src=' + image_url + 'edit.png"></a></td><td' + klasse + '>' + products[i]['sku'] + '</td><td' + klasse + '>' + products[i]['name'] + '</td><td' + klassenummer + '>' + products[i]['stock_quantity'] + '</td><td' + klassenummer + '>' + products[i]['regular_price'] + '</td><td' + klassenummer + '>' + products[i]['sale_price'] + '</td><td' + klassenummer + '>' + products[i]['price'] + '</td><td' + klassenummer + '>' + newPrice +'</td><td><img class="zpc-button-undo" height="16" width="16"src=' + image_url + 'undo.png"></td></tr>' );
                    } 
		        }
            }
            jQuery( "#ph_Table_allProducts" ).append( $table );
		  }
		  
		  /* 
		    Product 
		            */
		  if ( isCategory == 0 ) {
		      var checkboxes = document.getElementsByClassName( "cb-dossiers" );
              var productsChecked = [];
              for ( var i = 0; i < checkboxes.length; i++ ) {
                if ( checkboxes[ i ].checked ) {
                        productsChecked.push( checkboxes[ i ].value );
                        //console.log(checkboxes[i].value);
                        var temp = checkboxes[ i ].value;
                        if ( nproducts[ temp ]['myvar'] == 1 ) {
                            var nameArr = nproducts[ temp ]['allvars'].split( ',' );
                            for (var y = 0; y < tempArr.length; y++ ) {
                               if ( tempArr[ y ] != '' ) { productsChecked.push( tempArr[ y ] ); } 
                            }
                        }
                    }
                }
                
		    
		      jQuery( "#ph_Table_allProducts" ).empty();
		    
		      var $table = jQuery('<table class="tableproducts" id="T_allProducts" width="99%" />'); 
		      $table.append('<thead><tr><th>ID</th><th width="24px"></th><th>SKU</th><th>Title</th><th>Stock</th><th>Regular Price</th><th>Sale Price</th><th>Price</th><th>New Price</th><th></th></tr></thead>' );
		    
              var numberofchosencat = productsChecked.length;   
              for ( let j = 0; j < numberofchosencat; j++ ) {
                  
                  nid = productsChecked[ j ];
                  //console.log(nid);
                  newPrice = Number( nproducts[ nid ]['regular_price'] );
                  oldPrice = newPrice;
                        if (priceMethodChange == "by_fixed") { newPrice = newPrice + priceChange }
                            else { newPrice = newPrice + ( newPrice / 100 * priceChange ) }
                        if (RoundtoValues == 1 ) { newPrice = roundValue( newPrice, sb_roundto_how, rounddecimalto ); }
                        if ( use_RoundDecimal == 1 ) {
                            newPrice = newPrice.toFixed( rounddecimal );
                        }
                        nproducts[ nid ]['new_price'] = newPrice;    
                        $temp_id = nproducts[ nid ]['id'];
                        
                        if ( nproducts[ nid ]['myvar'] == 2 ) { $myvar = ' var'; $temp_id = nproducts[ nid ]['var_id']; } else  { $myvar = ''} 
                        if ( nproducts[ nid ]['myvar'] == 1 ) { $myvar = ' hasvar'}
                        if ( newPrice > oldPrice ) { rowClass=' class="green' + $myvar + '"'; }
		                  if ( newPrice < oldPrice ) { rowClass=' class="red' + $myvar + '"'; }
		                  if ( newPrice == oldPrice ) { rowClass=' class="rij' + $myvar + '"'; }
                        
                        $table.append( '<tr' + rowClass + '><td' + klasse + '><a target="_blank" href="' + home_url + '/products/' + nproducts[nid]['slug'] + '">' + $temp_id + '</a></td><td' + klasse + '><a target="_blank" href="' + admin_url + 'post.php?post=' + nproducts[nid]['id']  + '&action=edit"><img height="16" src=' + image_url + 'edit.png"></a></td><td' + klasse + '>' + nproducts[nid]['sku'] + '</td><td' + klasse + '>' + nproducts[nid]['name'] + '</td><td' + klassenummer + '>' + nproducts[nid]['stock_quantity'] + '</td><td' + klassenummer + '>' + nproducts[nid]['regular_price'] + '</td><td' + klassenummer + '>' + nproducts[nid]['sale_price'] + '</td><td' + klassenummer + '>' + nproducts[nid]['price'] + '</td><td' + klassenummer + '>' + newPrice +'</td><td><img class="zpc-button-undo" height="16" width="16"src=' + image_url + 'undo.png"></td></tr>' );
              }
              
              jQuery( "#ph_Table_allProducts" ).append( $table );
		  }
		}
		
		/* Sleep function */
		function sleep( t ) {
            return new Promise( resolve => setTimeout( resolve, t ) );
        }
		
		/* Make a preview table of chosen products and prices to be updated */
	    async function zpc_updateproducts_preview() {
	        jQuery( "#Ti_category" ).hide( 'fast' );
	        jQuery( "#Ti_dossiers" ).hide( 'fast' );
	        licenseValid = "<?php echo esc_html( $license_ok ); ?>";
	        
	        console.log('valid:' + licenseValid );
		    timesPreview++;
		    total = 50 * timesPreview;
		    if ( total > 200 ) { total = 200; } 
		    if ( licenseValid != 1 ) {
		        jQuery( "#percentge_submit" ).hide();
		        jQuery( "#progressmelding" ).text( 'Free version payload ... If you want to remove this waiting cycle, please register.' );
		        jQuery( "#progress" ).show( 'fast' );
		        for ( let x = 0; x < total; x++ ) {
                  waar = 100 / total * x;
                  waar = Math.round( waar );
                  setStatusBar( waar );
                  await sleep( 100 );
		        }
                jQuery( "#progress" ).hide( 'fast' );
                jQuery( "#progressmelding" ).text('');
		    }
			zpc_getAllProducts();
			jQuery( "#percentge_submit" ).show();
			jQuery( "#save_changes" ).show( "fast" );
		}
		
		/* Progress bar
			TODO: See if this is needed feedback */
		var progress_i = 0;
        function setStatusBar( waar ) {
            jQuery( ".bar" ).css({
                width: waar + "%",
                height: "100%"
            });
            if ( waar == 100 ) {
                jQuery( "#progressmelding" ).text( 'Update done.' );
            } 
        }
        
        /* Reload page so all saved changes are loaded
        	  TODO: Do this a nicer way */
        function zpc_updateproducts_reload() {
            location.reload();
        }
		
		/* Save the changed products 
		
			green: Price has changed up
			red: Price has changed down
			productchanged: stock or title has been changed
			
			TODO: Save all changes in one line at once (Nicer))
		*/
		function zpc_updateproducts_save() {
		    total = jQuery( ".green" ).length + jQuery( ".red" ).length + jQuery( ".productchanged" ).length;
		    if ( total == 0 ) {
		        alert( 'No changed product info found' );
		        return false;
		    }
		    
		    jQuery( "#reload" ).show( 'fast' );
		   
		    jQuery( "#progress" ).show( 'fast' );
          jQuery( ".bar" ).css({
             width: "1%",
             height: "20px"
          });
            
		   i = 0; 
		   waar = 0;
		    
			jQuery( ".green" ).each(function( index, tr ) { 
                id = jQuery( this ).find( "td:eq(0)" ).text();
                newPrice = jQuery( this ).find( "td:eq(8)" ).text();
                jQuery( this ).find( "td:eq(5)" ).text( newPrice );
                jQuery( this ).find( "td:eq(7)" ).text( newPrice );
                jQuery( this ).removeClass( 'green' );
                if ( jQuery( this ).hasClass( "var" ) ) {
                    //console.log('Opslaan als variant');
                    setProductPriceVar( id, newPrice );
                } else {
                    //console.log('Opslaan als hoofdartikel');
                    setProductItems( id, newPrice );
                }
                i++;
                waar = 100 / total * i;
                waar = Math.round( waar );
                setStatusBar( waar );
                
            });
            jQuery( ".red" ).each( function( index, tr ) { 
                id = jQuery( this ).find( "td:eq(0)" ).text();
                newPrice = jQuery( this ).find( "td:eq(8)" ).text();
                jQuery( this ).find( "td:eq(5)" ).text( newPrice );
                jQuery( this ).find( "td:eq(7)" ).text( newPrice );
                jQuery( this ).removeClass( 'red' );
                if ( jQuery( this ).hasClass( "var" ) ) {
                    //console.log('Opslaan als variant');
                    setProductPriceVar( id, newPrice );
                } else {
                    //console.log('Opslaan als hoofdartikel');
                    setProductItems( id, newPrice );
                }
                i++;
                waar = 100 / total * i;
                waar = Math.round( waar );
                setStatusBar( waar );
            });
            
            jQuery( ".productchanged" ).each( function( index, tr ) { 
                id = jQuery( this ).find( "td:eq(0)" ).text();
                newOMS = jQuery( this ).find( "td:eq(3)" ).text();
                newStock = jQuery( this ).find( "td:eq(4)" ).text();
                jQuery( this ).removeClass( 'productchanged' );
                jQuery( this ).find( "td:eq(3)" ).removeClass('changed');
                jQuery( this ).find( "td:eq(4)" ).removeClass('changed');
                if ( jQuery( this ).hasClass( "var" ) ) {
                    // console.log('Opslaan als variant');
                    setProductStockVar( id, newStock );
                } else {
                    // console.log('Opslaan als hoofdartikel');
                    setProductOMS( id, newOMS, newStock );
                }
                i++;
                waar = 100 / total * i;
                waar = Math.round( waar );
                setStatusBar( waar );
            });
		}
		
		jQuery( "#ph_Table_allProducts" ).on("click", "td", function(){
		    currentColID = jQuery( this ).index();
		    if ( currentColID != 8  && currentColID != 9  &&  currentColID != 4 &&  currentColID != 3) {
		        return 0;
		    } 
		    
		    currentRow = jQuery( this ).closest( "tr" );
		    currentRowID = currentRow.index( "tr" );
		    currentProductID = currentRow.find( "td:eq(0)" ).text();

		    if ( currentRowID == previousRowID ) {
		        if ( currentColID != previousColID ) { 
		            if ( editON == 1  ) { 
		            	zpcButtonClose(); 
		            	previousRowID = 0;
		            } 
		        }
		    }

		    if ( currentColID == 9 ) {
                    var temp = currentRow.find( "td:eq(7)" ).text();
                    currentRow.find( "td:eq(3)" ).text( nproducts[ currentProductID ]['name'] ); 
                    currentRow.find( "td:eq(4)" ).text( nproducts[ currentProductID ]['stock_quantity'] ); 
                    currentRow.find( "td:eq(8)" ).text( temp ); 
                    currentRow.removeClass( 'green' );
                    currentRow.removeClass( 'red' );
                    return 0;
		    }
		    
		    if ( currentColID == 3 ) {
		        if ( currentRow.hasClass ("var" ) ) { return 0; }
		    }

		    if ( originalPriceCurrentRow == 'F' ) { originalPriceCurrentRow = currentRow.find( "td:eq(8)" ).text(); }
		    if ( originalPricePreviousRow == 'F' ) {
		        originalPricePreviousRow = currentRow.find( "td:eq(8)" ).text(); 
              previousRow = currentRow;
		    }
		   
		    /* currentRowID and previousRowID is different */
		    if (currentRowID != previousRowID) {
		        
                /* Get the new prices */  
                originalPricePreviousRow = originalPriceCurrentRow;
                originalPriceCurrentRow = currentRow.find( "td:eq(8)" ).text();
                
                /* stock, id */
                var id = currentRow.find( "td:eq(0)" ).text();
                var stock = currentRow.find( "td:eq(4)" ).text();
                
                if ( currentColID == 3 ) {
                    currentRow.find( "td:eq(3)" ).empty();
                    currentRow.find( "td:eq(3)" ).append('<span><input type="text" id="editNewOMS" class="fullwidth-min" value="' + nproducts[id]['name']  + '"></span><span class="savebutton"> | <img class="zpc-button-accept" height="18" width="18"src=' + image_url + 'accept.png"> <img class="zpc-button-close" height="18" width="18"src=' + image_url + 'close.png"></span>');
                    editON = 1;
                    //<i class="fa fa-check fa-fw zpc-button-accept"></i> <i class="fa fa-times fa-fw zpc-button-close"></i>
                }
                
                if ( currentColID == 4 ) {
                    currentRow.find( "td:eq(4)" ).empty();
                    currentRow.find( "td:eq(4)" ).append('<input type="number" id="editNewStock" class="cellnumber fullwidth-min" value="' + nproducts[id]['stock_quantity'] + '" step="1"><span class="savebutton"> | <img class="zpc-button-accept" height="18" width="18"src=' + image_url + 'accept.png"> <img class="zpc-button-close" height="18" width="18"src=' + image_url + 'close.png"></span>');
                    editON = 1;
                }
                
                if ( currentColID == 8 ) {
                    currentRow.find( "td:eq(8)" ).empty();
                    currentRow.find( "td:eq(8)" ).append('<input type="number" id="editNewPrice" class="cellnumber" value="' + originalPriceCurrentRow + '" min="0" step="0.01"><span class="savebutton"> | <img class="zpc-button-accept" height="18" width="18"src=' + image_url + 'accept.png"> <img class="zpc-button-close" height="18" width="18"src=' + image_url + 'close.png"></span>');
                    editON = 1;
                }

                /* De nieuwe prijs van de vorig aangeklikte lijn ophalen en in grid zetten */
                if ( previousRowID != 0 ) {
                    if ( !previousRow.hasClass( "var" ) ) {
                        previousRow.find( "td:eq(3)" ).empty();
		                  previousRow.find( "td:eq(3)" ).text( nproducts[ previousProductID ]['name'] );
                    }
		              previousRow.find( "td:eq(4)" ).empty();
		              previousRow.find( "td:eq(4)" ).text( nproducts[ previousProductID ]['stock_quantity'] );
                    previousRow.find( "td:eq(8)" ).empty();
		              previousRow.find( "td:eq(8)" ).text( originalPricePreviousRow );
                }
                
                /* Update the previous lines */
                previousProductID = currentProductID;
                previousRowID = currentRowID;
                previousRow = currentRow;
                previousColID = currentColID;

		    } else {
                /* Clicked the same row */
		        if ( usedCloseButton == 1 ) { 
		             previousRowID = 0;
		             usedCloseButton = 0;
		        }
		    }
        });
        
       jQuery( "#ph_Table_allProducts" ).on("click","input",function(){ 
           jQuery( ".savebutton" ).show('fast');
           editVisible = 1;
       });
       
       function zpcButtonClose(){
           jQuery( ".savebutton" ).hide('fast');
           if ( !currentRow.hasClass( "var" ) ) {
                currentRow.find( "td:eq(3)" ).empty();
		          currentRow.find( "td:eq(3)" ).text( nproducts[ currentProductID ]['name'] );
           }
		     currentRow.find( "td:eq(4)" ).empty();
		     currentRow.find( "td:eq(4)" ).text( nproducts[ currentProductID ]['stock_quantity'] );
           currentRow.find( "td:eq(8)" ).empty();
		     currentRow.find( "td:eq(8)" ).text( originalPriceCurrentRow );
		     originalPricePreviousRow = originalPriceCurrentRow;
		     usedCloseButton = 1; 
		     editON = 0;
       }
       
       /* Close button on change pressed -> do not use change */
       jQuery( "#ph_Table_allProducts" ).on("click",".zpc-button-close",function(){ 
           zpcButtonClose()
       });
     
       /* Accept button on change pressed -> add class so the change will get saved */
       jQuery( "#ph_Table_allProducts" ).on("click",".zpc-button-accept",function(){ 
            if (currentColID == 8) {
                newPrice = jQuery( "#editNewPrice" ).val();
                iNewPrice = Number( newPrice );
                originalPrice = currentRow.find( "td:eq(5)" ).text();
                iOriginalPrice = Number( originalPrice );
                if (iNewPrice > iOriginalPrice) { currentRow.addClass('green'); currentRow.removeClass('red'); }
                if (iNewPrice < iOriginalPrice) { currentRow.addClass('red'); currentRow.removeClass('green'); }
                if (iNewPrice == iOriginalPrice) { currentRow.removeClass('green'); currentRow.removeClass('red'); }
                currentRow.find( "td:eq(8)" ).empty();
		          currentRow.find( "td:eq(8)" ) .text( newPrice );
		          originalPricePreviousRow = newPrice;
		          originalPriceCurrentRow = newPrice;
            }
            if (currentColID == 3) {
                if ( !currentRow.hasClass("var") ) {
                    newOMS = jQuery( "#editNewOMS" ).val();
                    nproducts[ currentProductID ]['name']=newOMS;
                    currentRow.find( "td:eq(3)" ).empty();
		              currentRow.find( "td:eq(3)" ).text(newOMS);
		              currentRow.addClass( 'productchanged' );
		              currentRow.find( "td:eq(3)" ).addClass('changed');
                }
            }
            if (currentColID == 4) {
                newStock = jQuery( "#editNewStock" ).val();
                nproducts[ currentProductID ]['stock_quantity']=newStock;
                currentRow.find( "td:eq(4)" ).empty();
		        currentRow.find( "td:eq(4)" ).text(newStock);
		        currentRow.addClass( 'productchanged' );
		        currentRow.find( "td:eq(4)" ).addClass('changed');
            }

           jQuery( ".savebutton" ).hide( 'fast' );
		   usedCloseButton=1;
		   editON=0;
       });
       
    /* Save new price using ajax callback */
    function setProductItems( fid, fnewPrice ){
           jQuery.ajax({
              url: ajaxurl,
              data: { 
                'action': 'update_products', 
                'f_id': fid,
                'f_newPrice' : fnewPrice
            },
            success: function( data ){
                console.log('Success update_products');
            }
        });
    }
    
    /* Save new var price using ajax callback */
    function setProductPriceVar( fid, fnewPrice ){
           jQuery.ajax({
              url: ajaxurl,
              data: { 
                'action': 'update_products_var', 
                'f_id': fid,
                'f_newPrice' : fnewPrice
            },
            success: function( data ){
                console.log('Success update_products_var');
            }
        });
    }
        
    /* Save new title and stock using ajax callback */
    function setProductOMS( fid, fnewOMS, fnewStock ){
           jQuery.ajax({
              url: ajaxurl,
              data: { 
                'action': 'update_productsOMS', 
                'f_id': fid,
                'f_newOMS' : fnewOMS,
                'f_newStock' : fnewStock
            },
            success: function( data ){
                console.log('Success update_products');
            }
        });
    };
    
    /* Save new stock var variant using ajax callback */
    function setProductStockVar( fid, fnewStock ){
           jQuery.ajax({
              url: ajaxurl,
              data: { 
                'action': 'update_products_stockvar', 
                'f_id': fid,
                'f_newStock' : fnewStock
            },
            success: function( data ){
                console.log('Success update_products_stockvar');
            }
        });
    };
</script>

<?php
 }
 
 /*
    OPTIONS TAB
                */
 if( $active_tab == 'value_options') {
    echo '<h2>Value Options</h2>';

    settings_fields( 'zpc_updateproducts_my_option_group_value' );
    echo '<div class="zpc-info">';
    echo '<br><h2><strong>How to ROUND prices:</strong></h2>';
    echo '<p>These settings have a direct impact on how the price will be rounded <small>(if set)</small>.</p>';
    echo '<br><table class="zpctable">';
    echo '<tr valign="middle">';
    echo '<td><strong>Round values:</strong></td>';
    $cb_checked = "";
    if ( isset( $this->options['zpc_cbrounddecimal'] ) ) {
        if ( $this->options['zpc_cbrounddecimal']  == 'YES' ) { $cb_checked = " checked"; }
    }
    echo '<td colspan=5><input type="checkbox" id="zpc_cbrounddecimal" name="zpc_updateproducts_my_option_name_value[zpc_cbrounddecimal]" value="YES" ' . esc_attr( $cb_checked ) . '/></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td><strong>Use decimals:</strong></td>';
    printf('<td colspan=5><input type="number" id="zpc_rounddecimal" name="zpc_updateproducts_my_option_name_value[zpc_rounddecimal]" value="%s" /></td>',isset( $this->options['zpc_rounddecimal'] ) ? esc_attr( $this->options['zpc_rounddecimal']) : '2');
    echo '</tr>';
    echo '<tr><td colspan="2"><hr></td></tr>';
    echo '<tr>';
    $cb_checked = "";
    if ( isset( $this->options['zpc_cb_roundto_values'] )) {
        if ( esc_attr( $this->options['zpc_cb_roundto_values'] ) == 'YES' ) { $cb_checked = " checked"; }
    }
    echo '<td><input type="checkbox" id="zpc_cb_roundto_values" name="zpc_updateproducts_my_option_name_value[zpc_cb_roundto_values]" value="YES" ' . esc_html( $cb_checked ) . '><strong> Round to: </strong></td>';
    $selectedValue = $this->options['zpc_sb_roundto_how']; 
    echo '<td><select id="zpc_sb_roundto_how" name="zpc_updateproducts_my_option_name_value[zpc_sb_roundto_how]" >';
    if ( $selectedValue == 0 ) { $selected = 'selected'; } else { $selected = ''; }
    echo '<option ' . esc_attr( $selected ) . ' value="0">Keep integer</option>';
    if ( $selectedValue == 1 ) { $selected = 'selected'; } else { $selected = ''; }
    echo '<option ' . esc_attr( $selected ) . ' value="1">Always up</option>';
    if ( $selectedValue == 2 ) { $selected = 'selected'; } else { $selected = ''; }
    echo '<option ' . esc_attr( $selected ) . ' value="2">Always down</option>';
    if ( $selectedValue == 3 ) { $selected = 'selected'; } else { $selected = ''; }
    echo '<option ' . esc_attr( $selected ) . ' value="3">Nearest</option>';
    if ( $selectedValue == 4 ) { $selected = 'selected'; } else { $selected = ''; }
    echo '<option ' . esc_attr( $selected ) . ' value="4">Factor</option>';
    echo '</select>';
    printf(' to <input type="number" id="zpc_rounddecimal_to" name="zpc_updateproducts_my_option_name_value[zpc_rounddecimal_to]" value="%s" step="0.01" min="0.00" max="0.99">',isset( $this->options['zpc_rounddecimal_to'] ) ? esc_attr( $this->options['zpc_rounddecimal_to']) : '0.95');
    echo " <small><em>(eg. if 'Always up' and '0.95' is selected, then a price of <code>9.56</code>' will be rounded to <code>9.95</code>)</em></small><td>";
    echo '</td>';
    echo '</tr>';
    echo '</table><br>';   
    submit_button();
    echo '</div>';
    echo '<br>';
 }
 

/*
    REGISTER TAB
                */
if( $active_tab == 'register_options') {  
    
   echo '<h2>License Options</h2>';
	echo '<br>';
	if ( $license_ok == 1 ) { $showme = 'style="display: none"'; } else { $showme = ''; } 
	echo '<div id="notregistered" ' . esc_attr( $showme ) . '>';
	echo 'You are using a FREE version of <strong>ZPC Update Price</strong>.<br>';
	echo 'if you do not have a license key for <strong>ZPC Update Price</strong>, and want to obtain one, you can visit <a target="_blank" href="https://www.zittergie.be/software/wordpress-plugin/zpc-update-products/">ZPC Update Products homepage</a>';
	echo '<br>';
	echo 'You can keep using the FREE version, but the nagging screen will take longer every time you use <strong>ZPC Update Price</strong>.  Development cost time and effort.  By buying a license you are showing your gratitude and keeps it possible to work on this plugins.';
	echo  '</div>';
	if ( $license_ok != 1 ) { $showme = 'style="display: none"'; } else { $showme = ''; } 
	echo '<div id="registered" ' . esc_attr( $showme ) . '>';
	echo 'Thank you for supporting the developer by using a registered version of <strong>ZPC Update Price</strong>.<br>';
	echo  '</div>';
	
	echo '<br>';
	
	echo '<div id="sendkey" style="display: none">';
	echo '<strong>License KEY: </strong><input style="background-color:#f1f1f1;width:240px;border: 1px solid transparant; padding: 5px; font-size: 13px;" id="E_LicenseKey" type="text" name="licensekey" placeholder=" place your license key here" value="'. esc_html( $license_key ) . '">';
	echo ' <label class="submit button button-primary" id="license_submit" onclick="zpcup_updateproducts_checknewlicensekey();"> Save </label> <label id="statusupdate"></label>';
	echo '<br><br><br>';
	
	echo '<div class="zpc-info">';
	echo '<strong>License Type:</strong> ' . esc_html( $license_type ) . '<br>';
	echo '<strong>Purchase Date:</strong> ' . date( "d-m-Y", $license_purchase_date ) . '<br>';
	echo '<strong>Valid until:</strong> ' . date( "d-m-Y", $license_valid_until ) . '<br>';
	echo '</div>';
	echo '</div>';
}
	/* Function to check license.
		It is not included in the free version because it uses the database from www.zittergie.be to check the license
		and I think it is not allowed when publishing on wordpress. (Although most plugins do)
		TODO: Get to know how a licensevalidator can be added in the plugin according to the wordpress rules
	*/		
    include(plugin_dir_path( __FILE__ ).'includes/pro.php');
    
    echo '</form>';
    echo '</div>';
        
}
    /* Register and add settings */
    public function page_init()
    {        
 
    /* Plugin Options */
        register_setting(
            'zpc_updateproducts_my_option_group_value', // Option group
            'zpc_updateproducts_my_option_name_value', // Option name
            array( $this, 'sanitize_value' ) // Sanitize
        );
        add_settings_field(
            'zpc_cbrounddecimal', // ID
            'Include PDF:', // Title 
             array( $this, 'zpc_cbrounddecimal_callback' ), // Callback
            'zpc-updateproducts-setting-admin_2', // Page
            'setting_section_id_2' // Section           
        );   
        add_settings_field(
            'zpc_rounddecimal',
            'Include PDF:',
            array( $this, 'zpc_value_callback' ), 
            'zpc-updateproducts-setting-admin_2', 
            'setting_section_id_2'           
        );    
        add_settings_field(
            'zpc_cb_roundto_values',
            'Include PDF:',
            array( $this, 'zpc_cb_roundto_values_callback' ), 
            'zpc-updateproducts-setting-admin_2', 
            'setting_section_id_2'           
        );  
        add_settings_field(
            'zpc_rounddecimal_to',
            'Include PDF:',
            array( $this, 'zpc_value_callback' ), 
            'zpc-updateproducts-setting-admin_2', 
            'setting_section_id_2'           
        );  
        add_settings_field(
            'zpc_sb_roundto_how',
            'Include PDF:',
            array( $this, 'zpc_sb_roundto_how_callback' ), 
            'zpc-updateproducts-setting-admin_2', 
            'setting_section_id_2'           
        ); 
    
    }

    /*
    Sanitize each setting field as needed
    @param array $input Contains all settings fields as array keys
    */
    public function sanitize_value( $input )
    {
        $new_input = array();
        if ( isset( $input['zpc_cbrounddecimal'] ) ) { $new_input['zpc_cbrounddecimal'] = sanitize_text_field( $input['zpc_cbrounddecimal'] ); }
        if ( isset( $input['zpc_rounddecimal'] ) ) { $new_input['zpc_rounddecimal'] = sanitize_text_field( $input['zpc_rounddecimal'] ); }
        if ( isset( $input['zpc_cb_roundto_values'] ) ) { $new_input['zpc_cb_roundto_values'] = sanitize_text_field( $input['zpc_cb_roundto_values'] ); }
        if ( isset( $input['zpc_rounddecimal_to'] ) ) { $new_input['zpc_rounddecimal_to'] = sanitize_text_field( $input['zpc_rounddecimal_to'] ); }
        if ( isset( $input['zpc_sb_roundto_how'] ) ) { $new_input['zpc_sb_roundto_how'] = sanitize_text_field( $input['zpc_sb_roundto_how'] ); }
        return $new_input;
    }

}

if ( is_admin() ) { $zpc_updateproducts_my_settings_page = new zpc_updateproducts_MySettingsPage(); }

?>
