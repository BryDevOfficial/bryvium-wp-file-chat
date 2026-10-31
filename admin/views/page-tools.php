<?php
/**
 * Import and export screen.
 *
 * @package Bryvium_WP_File_Chat
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap bwfc-admin bwfc-tools">
	<h1><?php echo esc_html__( 'Knowledge Import / Export', 'bryvium-wp-file-chat' ); ?></h1>
	<p><?php echo esc_html__( 'Download a backup of your knowledge entries or restore entries from a Bryvium JSON file.', 'bryvium-wp-file-chat' ); ?></p>
	<div id="bwfc-tools-notice" class="notice" role="status" aria-live="polite" hidden></div>

	<section class="bwfc-tools-section" aria-labelledby="bwfc-export-title">
		<h2 id="bwfc-export-title"><?php echo esc_html__( 'Export knowledge', 'bryvium-wp-file-chat' ); ?></h2>
		<p><?php echo esc_html__( 'Export all current entries as a JSON file. Keep this file somewhere safe.', 'bryvium-wp-file-chat' ); ?></p>
		<button id="bwfc-export-button" class="button button-primary" type="button"><?php echo esc_html__( 'Download JSON backup', 'bryvium-wp-file-chat' ); ?></button>
	</section>

	<section class="bwfc-tools-section" aria-labelledby="bwfc-import-title">
		<h2 id="bwfc-import-title"><?php echo esc_html__( 'Import knowledge', 'bryvium-wp-file-chat' ); ?></h2>
		<p><?php echo esc_html__( 'Import a JSON file up to 1 MB, or paste its contents below. Only valid Bryvium knowledge documents are accepted.', 'bryvium-wp-file-chat' ); ?></p>
		<form id="bwfc-import-form" enctype="multipart/form-data">
			<p>
				<label for="bwfc-import-file"><?php echo esc_html__( 'JSON file', 'bryvium-wp-file-chat' ); ?></label>
				<input id="bwfc-import-file" type="file" accept=".json,application/json">
			</p>
			<p>
				<label for="bwfc-import-content"><?php echo esc_html__( 'Or paste JSON content', 'bryvium-wp-file-chat' ); ?></label>
				<textarea id="bwfc-import-content" class="large-text code" rows="10" spellcheck="false"></textarea>
			</p>
			<p>
				<label for="bwfc-import-mode"><?php echo esc_html__( 'Import method', 'bryvium-wp-file-chat' ); ?></label>
				<select id="bwfc-import-mode">
					<option value="merge"><?php echo esc_html__( 'Merge — add new entries and update matching IDs', 'bryvium-wp-file-chat' ); ?></option>
					<option value="replace"><?php echo esc_html__( 'Replace — replace all entries (creates a backup first)', 'bryvium-wp-file-chat' ); ?></option>
				</select>
			</p>
			<p>
				<button id="bwfc-import-button" class="button button-primary" type="submit"><?php echo esc_html__( 'Import JSON', 'bryvium-wp-file-chat' ); ?></button>
			</p>
		</form>
		<div id="bwfc-import-summary" class="bwfc-import-summary" aria-live="polite" hidden>
			<h3><?php echo esc_html__( 'Import summary', 'bryvium-wp-file-chat' ); ?></h3>
			<p id="bwfc-import-summary-counts"></p>
			<p id="bwfc-import-backup" hidden></p>
			<ul id="bwfc-import-errors"></ul>
		</div>
	</section>
</div>
