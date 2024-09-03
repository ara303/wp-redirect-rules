<?php
/*
Plugin Name: WP Redirect Rules
Description: A WordPress plugin for .htacccess management of HTTP 301 redirects. Go to Admin Dashboard > Tools > Redirect Rules.
Version: 1
Author: ara303
*/

function redirect_rules_menu() {
    add_submenu_page(
        'tools.php',
        'Redirect Rules',
        'Redirect Rules',
        'manage_options',
        'redirect-rules',
        'redirect_rules_page'
    );
}
add_action( 'admin_menu', 'redirect_rules_menu' );

function redirect_rules_page() {
    if( ! current_user_can( 'manage_options' ) ){
        wp_die( "Access denied." );
    }

    if( isset( $_POST['submit'] ) ){
        $from = sanitize_text_field( $_POST['from_url'] );
        $to = sanitize_text_field( $_POST['to_url'] );
        add_redirect_rule( $from, $to );
    }

    if( isset( $_POST['delete'] ) ){
        $rule_id = intval( $_POST['rule_id'] );
        delete_redirect_rule( $rule_id );
    }

    $rules = get_redirect_rules();
    ?>
    <div class="wrap">
        <style>
            .wprr-form-wrapper {
                margin: 2em 0 2.5em;
                display: flex;
                gap: 1em;
            }

            .wprr-form-input {
                font-size: 13px;
                flex-grow: 1;
            }
        </style>
        <h1>WP Redirect Rules</h1>
        <h2>New rule</h2>
        <form method="post" action="" class="wprr-form-wrapper">
            <input class="wprr-form-input" type="text" name="from_url" placeholder="From URL" required>
            <input class="wprr-form-input" type="text" name="to_url" placeholder="To URL" required>
            <input type="submit" name="submit" value="Add new" class="button button-primary">
        </form>
        <h2>Rules</h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>From</th>
                    <th>To</th>
                    <th style="width: 53px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach( $rules as $id => $rule ): ?>
                <tr>
                    <td><?php echo esc_html($rule['from']); ?></td>
                    <td><?php echo esc_html($rule['to']); ?></td>
                    <td>
                        <form method="post" action="">
                            <input type="hidden" name="rule_id" value="<?php echo $id; ?>">
                            <input type="submit" name="delete" value="Delete" class="button button-secondary button-small" style="color: #a00; border-color: #a00;">
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function get_redirect_rules() {
    $rules = get_option( 'redirect_rules', [] );
    return $rules;
}

function add_redirect_rule($from, $to) {
    $rules = get_redirect_rules();;
    $rules[] = [ 'from' => $from, 'to' => $to ];

    update_option( 'redirect_rules', $rules );

    update_htaccess();
}

function delete_redirect_rule( $id ){
    $rules = get_redirect_rules();
    if( isset( $rules[$id] ) ){
        unset( $rules[$id] );

        update_option( 'redirect_rules', $rules );

        update_htaccess();
    }
}

function update_htaccess(){
    $htaccess_path = ABSPATH . '.htaccess';
    $htaccess = file_get_contents( $htaccess_path );
    $rules = get_redirect_rules();

    $redirect_block = "\n\n# START WP-Redirect-Rules\n";
    foreach( $rules as $rule ) {
        $redirect_block .= "Redirect 301 {$rule['from']} {$rule['to']}\n";
    }
    $redirect_block .= "# CLOSE WP-Redirect-Rules\n";

    $regex = '/\n\n# START WP-Redirect-Rules\n.*# CLOSE WP-Redirect-Rules\n/s';
    if( preg_match( $regex, $htaccess ) ){
        $new_block = preg_replace( $regex, $redirect_block, $htaccess );
    } else {
        $new_block = $htaccess . $redirect_block;
    }

    file_put_contents( $htaccess_path, $new_block );
}

/**
 * Create an empty rules block in .htaccess upon plugin activation, unless one already exists.
 */
function wprr_activate(){
    $htaccess = file_get_contents( ABSPATH . '.htaccess' );
    if( ! preg_match( '/\n\n# START WP-Redirect-Rules\n.*# CLOSE WP-Redirect-Rules\n/s', $htaccess) ) {
        $rules_block = $htaccess . "\n\n# START WP-Redirect-Rules\n# CLOSE WP-Redirect-Rules\n";

        file_put_contents( $htaccess, $rules_block );
    }
}
register_activation_hook( __FILE__, 'wprr_activate' );

/**
 * Remove the start/close comments, along with rules in between, upon plugin deactivation.
 */
function wprr_deactivate() {
    $htaccess = file_get_contents( ABSPATH . '.htaccess' );
    $remove_blocks = preg_replace( '/\n\n# START WP-Redirect-Rules\n.*# CLOSE WP-Redirect-Rules\n/s', '', $htaccess );

    file_put_contents( $htaccess, $remove_blocks );
}
register_deactivation_hook(__FILE__, 'wprr_deactivate');
