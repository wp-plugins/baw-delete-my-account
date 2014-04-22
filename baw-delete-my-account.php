<?php
/*
Plugin Name: BAW Delete My Account
Description: This plugin permits your members to delete their own account. 
Author: Julio Potier
Version: 1.0
Author URI: http://boiteaweb.fr
PLugin URI: http://boiteaweb.fr
*/

/* TODO: Comment this mess :) */

add_action( 'init', '__bawdma_load_text_domain' );
function __bawdma_load_text_domain()
{
	if ( 'wp-login.php' == $GLOBALS['pagenow'] && isset( $_GET['action'] ) && in_array( $_GET['action'], array( 'delete-account', 'delete-confirm'  )) ) {
		load_textdomain( 'default', WP_LANG_DIR . '/admin-' . get_locale() . '.mo' ); 
	}
}

add_action( 'show_user_profile', 'bawdma_personal_options' );
function bawdma_personal_options()
{
	printf( '<a href="%s" class="redlink">%s %s</a>', wp_nonce_url( site_url( 'wp-login.php?action=delete-account', 'login_post' ), 'delete-account' ), __( 'Remove' ), __( 'My Account' ) );
}

add_action( 'admin_print_styles-profile.php', 'bawdma_add_css' );
add_action( 'login_head', 'bawdma_add_css' );
function bawdma_add_css()
{
?>
<style>
	.redlink{color:#f00}
	.redlink:hover{color:#c00}
	.button-deletion{background:#CC2E2E !important;border-color:#A20000 !important;color:#fff !important}
	.button-deletion:hover{background:#BE1E1E !important}
	.userinfo{list-style-position:inside}
</style>
<?php
}

add_action( 'login_form_delete-account', '__bawdma_cb_delete_account' );
function __bawdma_cb_delete_account() {
	if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete-account' ) ) {
		if ( is_user_logged_in() ) {
			global $current_user;
			$userdata = get_userdata( $current_user->ID );
			$back = wp_get_referer() ? wp_get_referer() : home_url();
			$DP = 'fr_FR' != get_locale() ? ':' : '&nbsp;:';
			$userinfos = '';
			$userinfos .= wp_sprintf( '<li>%s%s %s (%d)</li>', __( 'Login Name' ), $DP, $userdata->user_login, $userdata->ID );
			$userinfos .= wp_sprintf( '<li>%s %s</li>', __( 'E-mail:' ), $userdata->user_email );
			if ( $userdata->user_url ) {
				$userinfos .= wp_sprintf( '<li>%s %s</li>', __( 'URL:' ), esc_html( $userdata->user_url ) );
			}
			$userinfos .= wp_sprintf( '<li>%s%s %s</li>', _x( 'Registered', 'user' ), $DP, $userdata->user_registered );
			require( ABSPATH . '/wp-admin/includes/user.php' );
			$roles = array_intersect_key( wp_list_pluck( get_editable_roles(), 'name' ), array_flip( $userdata->roles ) );
			$userinfos .= wp_sprintf( '<li>%s%s %s</li>', __( 'Role' ), $DP, wp_sprintf( '%l', array_map( 'translate_user_role', $roles ) ) );
			$content = wp_sprintf( '%s<br>&raquo; <b>%s %s</b> (%s)<br><br>%s<br>', __( 'You have specified this user for deletion:' ), $userdata->first_name, $userdata->last_name, $userdata->user_nicename, __( 'About the user' ) );
			login_header( __( 'Confirm Deletion' ), '<div class="message" id="login_error">' . $content . '<ul class="userinfo">' . $userinfos . '</ul></div>', null ); 
			?>
			<form name="deletionform" id="deletionform" action="<?php echo esc_url( site_url( 'wp-login.php?action=delete-confirm', 'login_post' ) ); ?>" method="post">
				<?php wp_nonce_field( 'delete-confirm', '_wpnonce', false ); ?>
				<p class="submit" style="padding-top:20px">
					<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-deletion large-text" value="<?php _e( 'Confirm Deletion' ); ?>" />
					<?php 
					$uid = apply_filters( 'attribute_all_content_to_user_id', null );
					if ( ! $uid || ! $attr_user = get_user_by( 'id', $uid ) ) {
						$count_user_posts = count_user_posts( $current_user->ID );
					?>
						<div style="text-align:center"><em>(<?php echo __( 'Caution:' ) . ' ' . wp_sprintf( __( 'You are about to delete <strong>%s</strong>.' ), wp_sprintf( _n( '%s Post', '%s Posts', $count_user_posts ), $count_user_posts ) ); ?>)</em></div>
					<?php } else { 
						?>
						<div style="text-align:center"><em>(<?php echo wp_sprintf( '%s %s <b>%s %s</b> (%s)', wp_sprintf( __( '%s and %s' ), '', '' ), __( 'Attribute all content to:' ), $attr_user->first_name, $attr_user->last_name , $attr_user->user_nicename ); ?>)</em></div>
					<?php }?>
				</p>
				<p class="cancel" style="margin-top:15px;text-align:center">
					<i><a href="<?php echo $back; ?>"><?php _e( 'Go back' ); ?></a></i>
				</p>
			</form>
			<?php
			login_footer( 'delete-account' );
			die();
		} else {
			wp_redirect( wp_login_url( site_url( 'wp-login.php?action=delete-account', 'login_post' ) ) );
			die();
		}
	}
}

add_action( 'login_form_delete-confirm', '__bawdma_cb_delete_confirm' );
function __bawdma_cb_delete_confirm() {
	if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'delete-confirm' ) ) {
		global $current_user;
		$admins = get_users( array( 'role' => 'administrator' ) );
		if ( in_array( 'administrator', $current_user->roles ) && 1 === count( $admins ) ) {
			$message = wp_sprintf( __( 'ERROR: %s' ), __( 'You can&#8217;t delete that user.' ) );
			$title = __( 'Error in deleting.' );
			$back = wp_sprintf( __( '&larr; Back to %s' ), get_bloginfo( 'title', 'display' ) );
			$link = wp_sprintf( '<a href="%s" title="%s">%s</a>', esc_url( home_url( '/' ) ), esc_attr( 'Are you lost?' ), $back );
			wp_die( wp_sprintf( '<p>%s</p><p id="backtoblog">%s</p>', $message, $link ), $title );
		} else {
			$uid = apply_filters( 'attribute_all_content_to_user_id', null );
			require( ABSPATH . '/wp-admin/includes/user.php' );
			if ( wp_delete_user( $current_user->ID, $uid ) ) {
				wp_clear_auth_cookie();
				login_header( __( 'User deleted.' ), null, null ); 
				echo wp_sprintf( '<form>%s</form>', __( 'User deleted.' ) );
			} else {
				login_header( __( 'Error' ), null, null ); 
				echo wp_sprintf( __( 'ERROR: %s' ), __( 'You can&#8217;t delete that user.' ) );
			}
			login_footer( 'deleted-account' );
			die();
		}
	}
}