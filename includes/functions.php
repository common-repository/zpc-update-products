<?php
/* 
 *  Prefix zpc + u(pdate) + p(roducts)
 */


function zpcup_validate_plugin(){
    if ( isset($_REQUEST) ){
    }
    die();
}
add_action( 'wp_ajax_validate_plugin', 'zpcup_validate_plugin' );


function zpcup_update_products(){
    if (isset($_REQUEST)){
        $my_id = sanitize_text_field( $_REQUEST['f_id'] );
        $my_newPrice = sanitize_text_field( $_REQUEST['f_newPrice'] );
        $myproduct = wc_get_product( $my_id );
        $myproduct->set_sale_price( $my_newPrice );
        $myproduct->set_regular_price( $my_newPrice );
        $myproduct->save();
    }
    die();
}
add_action('wp_ajax_update_products', 'zpcup_update_products');


function zpcup_update_products_var(){
    if ( isset($_REQUEST) ){
        $my_id = sanitize_text_field( $_REQUEST['f_id'] );
        $my_newPrice = sanitize_text_field( $_REQUEST['f_newPrice'] );
        $myproduct = wc_get_product( $my_id );
        $myproduct->set_sale_price( $my_newPrice );
        $myproduct->set_regular_price( $my_newPrice );
        $myproduct->save();
    }
    die();
}
add_action( 'wp_ajax_update_products_var', 'zpcup_update_products_var' );


function zpcup_update_productsOMS(){
    if ( isset($_REQUEST) ){
        $my_id = sanitize_text_field ($_REQUEST['f_id'] );
        $my_newOMS = sanitize_text_field( $_REQUEST['f_newOMS'] );
        $my_newStock = sanitize_text_field( $_REQUEST['f_newStock'] );
        $myproduct = wc_get_product( $my_id );
        $myproduct->set_name( $my_newOMS );
        $myproduct->set_stock_quantity( $my_newStock );
        $myproduct->save();
    }
    die();
}
add_action('wp_ajax_update_productsOMS', 'zpcup_update_productsOMS');

function zpcup_update_products_stockvar(){
    if ( isset($_REQUEST) ){
        $my_id = sanitize_text_field( $_REQUEST['f_id'] );
        $my_newStock = sanitize_text_field( $_REQUEST['f_newStock'] );
        $myproduct = wc_get_product( $my_id );
        $myproduct->set_stock_quantity( $my_newStock );
        $myproduct->save();
    }
    die();
}
add_action( 'wp_ajax_update_products_stockvar', 'zpcup_update_products_stockvar' );


function zpcup_activate_license(){
    global $wpdb;
    $licenseOK = 0;
    if ( isset($_REQUEST) ){
        $my_lic = sanitize_text_field( $_REQUEST['f_lic'] );
        
     /* REGISTER */
        $mysuccess = 0; $id = 0;
        $orderId = 0; $productId = 0;
        $licenseKey = 0; $expiresAt = 0;
        $timesActivated = 0; $createdAt = 0;
        
        $remote_url = "https://www.zittergie.be/php/checklicense.php?lic=$my_lic";  
        $response = wp_remote_get( $remote_url );
        $message = wp_remote_retrieve_body( $response );
        $amessage = json_decode( $message, true );

        if ( isset( $amessage['success'] ) ) { $mysuccess = sanitize_text_field( $amessage['success'] ); }
        if ( $mysuccess == 1 ) {
            
            $adata = $amessage['data'];
            $id = sanitize_text_field( $adata['id'] );
            $orderId = sanitize_text_field( $adata['orderId'] );
            $productId = sanitize_text_field( $adata['productId'] );
            $licenseKey = sanitize_text_field( $adata['licenseKey'] );
            $expiresAt = sanitize_text_field( $adata['expiresAt'] );
            $createdAt = sanitize_text_field( $adata['createdAt'] );
            $timesActivated = sanitize_text_field( $adata['timesActivated'] );
            $expired = strtotime( $expiresAt );
            $created = strtotime( $createdAt );

            $table_name = 'zpcup_updateproducts';
            $wpdb->query('DELETE FROM ' . $wpdb->prefix.$table_name);
            $wpdb->query('INSERT INTO '. $wpdb->prefix.$table_name .' (`expDate`, `purchaseDate`, `timesUsed`, `licenseKey`) VALUES (' . sanitize_text_field( $expired ) . ', ' . sanitize_text_field( $created) . ', ' . sanitize_text_field( $timesActivated ) . ', "' . sanitize_text_field( $licenseKey ) . '")');
        }
        
    }
    wp_send_json_success( array( 
        'licenseFound' => "$mysuccess", 
        'expiresAt' => "$expired", 
        'createdAt' => "$created", 
        'timesActivated' => "$timesActivated",
    ), 200 );
    die();
}
add_action( 'wp_ajax_activate_license', 'zpcup_activate_license' );


function zpcup_controle_license(){
    global $wpdb;
    $licenseOK = 0;
    if ( isset( $_REQUEST ) ){
        $my_lic = sanitize_text_field( $_REQUEST['f_lic'] );
        
     /* REGISTER */
        $mysuccess = 0; $id = 0;
        $orderId = 0; $productId = 0;
        $licenseKey = 0; $expiresAt = 0;
        $timesActivated = 0; $createdAt = 0;
        
        $remote_url = "https://www.zittergie.be/php/checklicense.php?lic=$my_lic";  
        $response = wp_remote_get( $remote_url );
        $message = wp_remote_retrieve_body( $response );
        $amessage = json_decode( $message, true );
        
        if ( isset( $amessage['success'] ) ) { $mysuccess = sanitize_text_field( $amessage['success'] ); }
        if ( $mysuccess == 1 ) {
            
            $adata = $amessage['data'];
            $id = sanitize_text_field( $adata['id'] );
            $orderId = sanitize_text_field( $adata['orderId'] );
            $productId = sanitize_text_field( $adata['productId'] );
            $licenseKey = sanitize_text_field( $adata['licenseKey'] );
            $expiresAt = sanitize_text_field( $adata['expiresAt'] );
            $createdAt = sanitize_text_field( $adata['createdAt'] );
            $timesActivated = sanitize_text_field( $adata['timesActivated'] );
            $expired = strtotime( $expiresAt );
            $created = strtotime( $createdAt );
        }
        
    }
    wp_send_json_success( array( 
        'licenseFound' => "$mysuccess", 
        'expiresAt' => "$expired", 
        'createdAt' => "$created", 
        'timesActivated' => "$timesActivated",
    ), 200 );
    die();
}
add_action( 'wp_ajax_controle_license', 'zpcup_controle_license' );

?>
