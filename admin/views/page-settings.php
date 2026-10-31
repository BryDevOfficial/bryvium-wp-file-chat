<?php
/**
 * Plugin settings screen.
 *
 * @package Bryvium_WP_File_Chat
 */

defined( 'ABSPATH' ) || exit;

$bryvium_wfc_settings = Bryvium_Settings::get();
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Bryvium WP File Chat Settings', 'bryvium-wp-file-chat' ); ?></h1>
	<?php settings_errors(); ?>
	<p><?php echo esc_html__( 'Configure the chat widget defaults. These settings will be used when the public chat widget is added.', 'bryvium-wp-file-chat' ); ?></p>

	<form action="options.php" method="post">
		<?php settings_fields( Bryvium_Settings::OPTION_GROUP ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="bryvium-wfc-widget-title"><?php echo esc_html__( 'Widget title', 'bryvium-wp-file-chat' ); ?></label>
					</th>
					<td>
						<input id="bryvium-wfc-widget-title" class="regular-text" type="text" maxlength="80" name="bryvium_wfc_settings[widget_title]" value="<?php echo esc_attr( $bryvium_wfc_settings['widget_title'] ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bryvium-wfc-greeting"><?php echo esc_html__( 'Greeting', 'bryvium-wp-file-chat' ); ?></label>
					</th>
					<td>
						<textarea id="bryvium-wfc-greeting" class="large-text" rows="3" maxlength="300" name="bryvium_wfc_settings[greeting]"><?php echo esc_textarea( $bryvium_wfc_settings['greeting'] ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bryvium-wfc-fallback"><?php echo esc_html__( 'Fallback message', 'bryvium-wp-file-chat' ); ?></label>
					</th>
					<td>
						<textarea id="bryvium-wfc-fallback" class="large-text" rows="3" maxlength="500" name="bryvium_wfc_settings[fallback_message]"><?php echo esc_textarea( $bryvium_wfc_settings['fallback_message'] ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bryvium-wfc-placeholder"><?php echo esc_html__( 'Input placeholder', 'bryvium-wp-file-chat' ); ?></label>
					</th>
					<td>
						<input id="bryvium-wfc-placeholder" class="regular-text" type="text" maxlength="120" name="bryvium_wfc_settings[placeholder]" value="<?php echo esc_attr( $bryvium_wfc_settings['placeholder'] ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bryvium-wfc-primary-color"><?php echo esc_html__( 'Primary color', 'bryvium-wp-file-chat' ); ?></label>
					</th>
					<td>
						<input id="bryvium-wfc-primary-color" type="color" name="bryvium_wfc_settings[primary_color]" value="<?php echo esc_attr( $bryvium_wfc_settings['primary_color'] ); ?>">
						<p class="description"><?php echo esc_html__( 'The widget automatically chooses a readable text color for the selected primary color.', 'bryvium-wp-file-chat' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bryvium-wfc-position"><?php echo esc_html__( 'Widget position', 'bryvium-wp-file-chat' ); ?></label>
					</th>
					<td>
						<select id="bryvium-wfc-position" name="bryvium_wfc_settings[position]">
							<option value="bottom-right" <?php selected( $bryvium_wfc_settings['position'], 'bottom-right' ); ?>><?php echo esc_html__( 'Bottom right', 'bryvium-wp-file-chat' ); ?></option>
							<option value="bottom-left" <?php selected( $bryvium_wfc_settings['position'], 'bottom-left' ); ?>><?php echo esc_html__( 'Bottom left', 'bryvium-wp-file-chat' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Display options', 'bryvium-wp-file-chat' ); ?></th>
					<td>
						<label for="bryvium-wfc-auto-display">
							<input type="hidden" name="bryvium_wfc_settings[auto_display]" value="0">
							<input id="bryvium-wfc-auto-display" type="checkbox" name="bryvium_wfc_settings[auto_display]" value="1" <?php checked( $bryvium_wfc_settings['auto_display'] ); ?>>
							<?php echo esc_html__( 'Display the widget automatically on site pages', 'bryvium-wp-file-chat' ); ?>
						</label>
						<br>
						<p class="description"><?php echo esc_html__( 'To show the widget only on selected pages, leave automatic display off and add the [bryvium_chat] shortcode to the page content.', 'bryvium-wp-file-chat' ); ?></p>
						<br>
						<label for="bryvium-wfc-save-history">
							<input type="hidden" name="bryvium_wfc_settings[save_history]" value="0">
							<input id="bryvium-wfc-save-history" type="checkbox" name="bryvium_wfc_settings[save_history]" value="1" <?php checked( $bryvium_wfc_settings['save_history'] ); ?>>
							<?php echo esc_html__( 'Keep chat history in this browser tab', 'bryvium-wp-file-chat' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bryvium-wfc-threshold"><?php echo esc_html__( 'Answer confidence threshold', 'bryvium-wp-file-chat' ); ?></label>
					</th>
					<td>
						<input id="bryvium-wfc-threshold" type="number" min="0" max="1" step="0.01" name="bryvium_wfc_settings[confidence_threshold]" value="<?php echo esc_attr( (string) $bryvium_wfc_settings['confidence_threshold'] ); ?>">
						<p class="description"><?php echo esc_html__( 'A value from 0 to 1. Higher values require a stronger knowledge match.', 'bryvium-wp-file-chat' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bryvium-wfc-rate-limit"><?php echo esc_html__( 'Requests per visitor per minute', 'bryvium-wp-file-chat' ); ?></label>
					</th>
					<td>
						<input id="bryvium-wfc-rate-limit" type="number" min="1" max="120" step="1" name="bryvium_wfc_settings[rate_limit_per_minute]" value="<?php echo esc_attr( (string) $bryvium_wfc_settings['rate_limit_per_minute'] ); ?>">
						<p class="description"><?php echo esc_html__( 'Choose between 1 and 120 requests per minute.', 'bryvium-wp-file-chat' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Save Settings', 'bryvium-wp-file-chat' ) ); ?>
	</form>
</div>
