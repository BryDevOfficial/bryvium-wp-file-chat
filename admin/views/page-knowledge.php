<?php
/**
 * Knowledge manager screen.
 *
 * @package Bryvium_WP_File_Chat
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap bwfc-admin">
	<h1><?php echo esc_html__( 'Knowledge', 'bryvium-wp-file-chat' ); ?></h1>
	<p><?php echo esc_html__( 'Add the questions and answers your chat widget can use. Keep answers clear and helpful.', 'bryvium-wp-file-chat' ); ?></p>

	<div id="bwfc-knowledge-notice" class="notice" role="status" aria-live="polite" hidden></div>

	<section class="bwfc-knowledge-list" aria-labelledby="bwfc-knowledge-list-title">
		<h2 id="bwfc-knowledge-list-title"><?php echo esc_html__( 'Knowledge entries', 'bryvium-wp-file-chat' ); ?></h2>
		<form id="bwfc-knowledge-filters" class="bwfc-knowledge-filters">
			<div class="bwfc-knowledge-filter-field">
				<label for="bwfc-knowledge-search"><?php echo esc_html__( 'Search entries', 'bryvium-wp-file-chat' ); ?></label>
				<input id="bwfc-knowledge-search" type="search" placeholder="<?php echo esc_attr__( 'Search titles, answers, or keywords', 'bryvium-wp-file-chat' ); ?>">
			</div>
			<div class="bwfc-knowledge-filter-field">
				<label for="bwfc-knowledge-category"><?php echo esc_html__( 'Category', 'bryvium-wp-file-chat' ); ?></label>
				<input id="bwfc-knowledge-category" type="search" maxlength="60" placeholder="<?php echo esc_attr__( 'Filter by category', 'bryvium-wp-file-chat' ); ?>">
			</div>
			<button class="button" type="submit"><?php echo esc_html__( 'Search', 'bryvium-wp-file-chat' ); ?></button>
		</form>

		<div class="bwfc-knowledge-table-wrap">
			<table class="widefat striped" aria-describedby="bwfc-knowledge-list-title">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Title', 'bryvium-wp-file-chat' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Category', 'bryvium-wp-file-chat' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Keywords', 'bryvium-wp-file-chat' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Status', 'bryvium-wp-file-chat' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Actions', 'bryvium-wp-file-chat' ); ?></th>
					</tr>
				</thead>
				<tbody id="bwfc-knowledge-entries"></tbody>
			</table>
		</div>
		<p id="bwfc-knowledge-empty" class="bwfc-knowledge-empty" hidden><?php echo esc_html__( 'No knowledge entries found yet. Add your first question and answer using the form below.', 'bryvium-wp-file-chat' ); ?></p>
		<nav class="bwfc-knowledge-pagination" aria-label="<?php echo esc_attr__( 'Knowledge entry pages', 'bryvium-wp-file-chat' ); ?>">
			<button id="bwfc-knowledge-previous" class="button" type="button" disabled><?php echo esc_html__( 'Previous', 'bryvium-wp-file-chat' ); ?></button>
			<span id="bwfc-knowledge-page" aria-live="polite"></span>
			<button id="bwfc-knowledge-next" class="button" type="button" disabled><?php echo esc_html__( 'Next', 'bryvium-wp-file-chat' ); ?></button>
		</nav>
	</section>

	<section class="bwfc-knowledge-editor" aria-labelledby="bwfc-knowledge-form-title">
		<h2 id="bwfc-knowledge-form-title"><?php echo esc_html__( 'Add a knowledge entry', 'bryvium-wp-file-chat' ); ?></h2>
		<form id="bwfc-knowledge-form">
			<input id="bwfc-knowledge-id" type="hidden" value="">
			<p>
				<label for="bwfc-knowledge-title"><?php echo esc_html__( 'Question or title', 'bryvium-wp-file-chat' ); ?> <span aria-hidden="true">*</span></label>
				<input id="bwfc-knowledge-title" class="regular-text" type="text" maxlength="200" required>
			</p>
			<p>
				<label for="bwfc-knowledge-answer"><?php echo esc_html__( 'Answer', 'bryvium-wp-file-chat' ); ?> <span aria-hidden="true">*</span></label>
				<textarea id="bwfc-knowledge-answer" class="large-text" rows="5" maxlength="4000" required></textarea>
			</p>
			<p>
				<label for="bwfc-knowledge-keywords"><?php echo esc_html__( 'Keywords', 'bryvium-wp-file-chat' ); ?></label>
				<input id="bwfc-knowledge-keywords" class="regular-text" type="text" maxlength="1000" aria-describedby="bwfc-knowledge-keywords-help">
				<span id="bwfc-knowledge-keywords-help" class="description"><?php echo esc_html__( 'Separate keywords with commas (up to 20 keywords).', 'bryvium-wp-file-chat' ); ?></span>
			</p>
			<p>
				<label for="bwfc-knowledge-category-input"><?php echo esc_html__( 'Category', 'bryvium-wp-file-chat' ); ?></label>
				<input id="bwfc-knowledge-category-input" class="regular-text" type="text" maxlength="60">
			</p>
			<p>
				<label for="bwfc-knowledge-enabled">
					<input id="bwfc-knowledge-enabled" type="checkbox" checked>
					<?php echo esc_html__( 'Use this entry when answering visitors', 'bryvium-wp-file-chat' ); ?>
				</label>
			</p>
			<p class="bwfc-knowledge-form-actions">
				<button id="bwfc-knowledge-submit" class="button button-primary" type="submit"><?php echo esc_html__( 'Add entry', 'bryvium-wp-file-chat' ); ?></button>
				<button id="bwfc-knowledge-cancel" class="button" type="button" hidden><?php echo esc_html__( 'Cancel edit', 'bryvium-wp-file-chat' ); ?></button>
			</p>
		</form>
	</section>
</div>
