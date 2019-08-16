<?php
/**
 * Plugin Name:     WP Virtual Summit Workshop
 * Plugin URI:      https://jonathanbossenger.com
 * Description:     Simple Shortcode based newsletter subscribe form that connects to the MailChimp API to subscribe a user
 * Author:          Jonathan Bossenger
 * Author URI:      https://jonathanbossenger.com
 * Text Domain:     wp-virtual-summit-workshop
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         WP_Virtual_Summit
 */

define( 'WPVS_MAILCHIMP_KEY', '' );
define( 'WPVS_MAILCHIMP_LIST_ID', '' );

require 'debugger.php';

add_shortcode( 'wpvs_subscribers_shortcode', 'wpvs_subscribers_shortcode' );
function wpvs_subscribers_shortcode() {
	ob_start();
	$subscriber_emails = wpvs_get_mailchimp_subscribers();
	if ( empty( $subscriber_emails ) ) {
		$html = ob_get_clean();

		return $html;
	}
	?>
	<h1>Subscriber List</h1>
	<?php
	if ( isset( $subscriber_emails['error'] ) ) {
		echo esc_attr( $subscriber_emails['error'] );
	} else {
		?>
		<table>
			<tr>
				<td>Email</td>
			</tr>
			<?php foreach ( $subscriber_emails as $subscriber_email ) { ?>
				<tr>
					<td><?php echo esc_html( $subscriber_email ); ?></td>
				</tr>
			<?php } ?>
		</table>
		<?php
	}
	$html = ob_get_clean();

	return $html;
}

/**
 * Get MailChimp Subscriber Lists
 *
 * @return array
 */
function wpvs_get_mailchimp_subscribers() {
	$api_key = WPVS_MAILCHIMP_KEY;
	$list_id = WPVS_MAILCHIMP_LIST_ID;

	$api_parts = explode( '-', $api_key );
	$dc        = $api_parts[1];

	$args = array(
		'headers' => array(
			'Authorization' => 'Basic ' . base64_encode( 'user:' . $api_key ),
		),
		'timeout' => '30',
	);

	$api_url = 'https://' . $dc . '.api.mailchimp.com/3.0/lists/' . $list_id . '/members/';

	$response = wp_remote_get( $api_url, $args );

	if ( is_wp_error( $response ) ) {
		return array( 'error' => 'An error occurred connecting the MailChimp API.' );
	}

	$response_object = json_decode( wp_remote_retrieve_body( $response ) );
	if ( empty( $response_object ) ) {
		return array( 'error' => 'An error occurred retrieving the subscriber lists.' );
	}
	$response = array();
	foreach ( $response_object->members as $member ) {
		$response[] = $member->email_address;
	}
	return $response;
}

add_shortcode( 'wpvs_form_shortcode', 'wpvs_form_shortcode' );
function wpvs_form_shortcode() {
	ob_start();
	?>
	<form method="post">
		<input type="hidden" name="wpvs_form" value="submit">
		<div>
			<label for="email">Email address</label>
			<input type="text" id="email" name="email" placeholder="Email address">
		</div>
		<div>
			<input type="submit" id="submit" name="submit" value="Submit">
		</div>
	</form>
	<?php
	$form = ob_get_clean();

	return $form;
}

/**
 * Step 2: Let's process the form data
 * https://developer.wordpress.org/reference/hooks/wp/
 */
add_action( 'wp', 'wpvs_maybe_process_form' );
function wpvs_maybe_process_form() {
	//@todo homework: learn about and implement nonce checking
	if ( ! isset( $_POST['wpvs_form'] ) ) {
		return;
	}
	$wpvs_form = $_POST['wpvs_form']; //phpcs:ignore WordPress.Security.NonceVerification
	if ( ! empty( $wpvs_form ) && 'submit' === $wpvs_form ) {
		$email = $_POST['email']; //phpcs:ignore WordPress.Security.NonceVerification

		$subscribe_data = array(
			'status'        => 'subscribed',
			'email_address' => $email,
		);
		$subscribed     = subscribe_email_to_mailchimp_list( $subscribe_data );
		if ( $subscribed ) {
			update_option( 'wpvs_email', $email );
		}
	}
}

function subscribe_email_to_mailchimp_list( $subscribe_data ) {
	$api_key = WPVS_MAILCHIMP_KEY;
	$list_id = WPVS_MAILCHIMP_LIST_ID;

	$api_parts = explode( '-', $api_key );
	$dc        = $api_parts[1];

	$args = array(
		'headers' => array(
			'Authorization' => 'Basic ' . base64_encode( 'user:' . $api_key ), //phpcs:ignore
		),
		'body'    => json_encode( $subscribe_data ), //phpcs:ignore
		'timeout' => '30',
	);

	$api_url = 'https://' . $dc . '.api.mailchimp.com/3.0/lists/' . $list_id . '/members/';

	$response = wp_remote_post( $api_url, $args );

	if ( is_wp_error( $response ) ) {
		return false;
	}

	$response_object = json_decode( wp_remote_retrieve_body( $response ) );

	if ( empty( $response_object || ! isset( $response_object->status ) || 'subscribed' !== $response_object->status ) ) {
		return false;
	}

	return true;
}

