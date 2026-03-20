<?php
/**
 * Help tab partial — explains how the plugin works with examples.
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wpts-help">

	<!-- Overview -->
	<div class="wpts-help-section">
		<h2><?php esc_html_e( 'How WP to Social works', 'wp-to-social' ); ?></h2>
		<p><?php esc_html_e( 'WP to Social lets you share your WordPress content to social media platforms whenever you publish a post. Here is the general workflow:', 'wp-to-social' ); ?></p>
		<ol class="wpts-help-steps">
			<li><?php esc_html_e( 'Activate a social media module (e.g. LinkedIn or Instagram) and enter your API credentials.', 'wp-to-social' ); ?></li>
			<li><?php esc_html_e( 'Choose which post types (Posts, Pages, or your custom ones like "News" or "Portfolio") should be available for sharing.', 'wp-to-social' ); ?></li>
			<li><?php esc_html_e( 'Optionally customize which WordPress fields map to each platform\'s fields.', 'wp-to-social' ); ?></li>
			<li><?php esc_html_e( 'When editing a post, check the "Post to ..." checkbox and publish. The plugin handles the rest.', 'wp-to-social' ); ?></li>
			<li><?php esc_html_e( 'Check the Activity page to see what was posted, and retry anything that failed.', 'wp-to-social' ); ?></li>
		</ol>
	</div>

	<!-- Tabs explained -->
	<div class="wpts-help-section">
		<h2><?php esc_html_e( 'Settings tabs explained', 'wp-to-social' ); ?></h2>

		<div class="wpts-help-card">
			<h3><?php esc_html_e( 'Modules', 'wp-to-social' ); ?></h3>
			<p><?php esc_html_e( 'This is where you connect your social media accounts. Each platform is a separate module. To get started, enter your API credentials (you get these from the platform\'s developer portal), save them, and then click the "Connect" button to authorize your account.', 'wp-to-social' ); ?></p>
			<p><?php esc_html_e( 'Each module card has a "How to connect" guide that walks you through the setup step by step.', 'wp-to-social' ); ?></p>
		</div>

		<div class="wpts-help-card">
			<h3><?php esc_html_e( 'Post Types', 'wp-to-social' ); ?></h3>
			<p><?php esc_html_e( 'WordPress has different content types: Posts, Pages, and any Custom Post Types (CPTs) your theme or plugins may have registered, such as "News", "Portfolio", "Products", or "Events".', 'wp-to-social' ); ?></p>
			<p><?php esc_html_e( 'On this tab, you select which of these types should be eligible for sharing to each platform. Only selected types will show the "Post to ..." checkbox in the editor.', 'wp-to-social' ); ?></p>
		</div>

		<div class="wpts-help-card">
			<h3><?php esc_html_e( 'Field Mapping', 'wp-to-social' ); ?></h3>
			<p><?php esc_html_e( 'Each social media platform expects certain pieces of information — for example, LinkedIn needs a title, body text, a URL, and optionally an image. Instagram needs an image and a caption.', 'wp-to-social' ); ?></p>
			<p><?php esc_html_e( 'Field Mapping lets you control which WordPress field fills each platform field. By default, the plugin uses sensible mappings (e.g. Post Title for the title, Featured Image for the image). But if your custom post types use custom fields, you can point the platform fields to those instead.', 'wp-to-social' ); ?></p>
		</div>

		<div class="wpts-help-card">
			<h3><?php esc_html_e( 'Activity (submenu)', 'wp-to-social' ); ?></h3>
			<p><?php esc_html_e( 'Found under WP to Social in the admin menu. This page shows a log of every social media post attempt — successful or failed. If something went wrong, you can see the error message and retry directly from here.', 'wp-to-social' ); ?></p>
		</div>
	</div>

	<!-- LinkedIn example -->
	<div class="wpts-help-section">
		<h2><?php esc_html_e( 'Example: LinkedIn with a "News" post type', 'wp-to-social' ); ?></h2>
		<p>
			<?php esc_html_e( 'Imagine your WordPress site has a Custom Post Type called "News". You want every news article to also be shared on your company\'s LinkedIn profile. Here is how you would set it up:', 'wp-to-social' ); ?>
		</p>

		<ol class="wpts-help-steps">
			<li><?php esc_html_e( 'Go to the Modules tab and connect your LinkedIn account (follow the "How to connect" guide on the module card).', 'wp-to-social' ); ?></li>
			<li><?php esc_html_e( 'Go to the Post Types tab, select "LinkedIn" from the dropdown, and check "News" in the list.', 'wp-to-social' ); ?></li>
			<li><?php esc_html_e( 'Go to the Field Mapping tab, select "LinkedIn" and "News". You will see a table like this:', 'wp-to-social' ); ?></li>
		</ol>

		<table class="wpts-help-mapping-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'LinkedIn field', 'wp-to-social' ); ?></th>
					<th><?php esc_html_e( 'Maps to', 'wp-to-social' ); ?></th>
					<th><?php esc_html_e( 'What it does', 'wp-to-social' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><strong><?php esc_html_e( 'Title', 'wp-to-social' ); ?></strong></td>
					<td><code>post_title</code></td>
					<td><?php esc_html_e( 'Uses your news headline as the LinkedIn share title.', 'wp-to-social' ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Body Text', 'wp-to-social' ); ?></strong></td>
					<td><code>post_content</code></td>
					<td><?php esc_html_e( 'Uses the full article text. You could also choose "Post Excerpt" for a shorter version.', 'wp-to-social' ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Share URL', 'wp-to-social' ); ?></strong></td>
					<td><code>_permalink</code></td>
					<td><?php esc_html_e( 'Automatically links back to the article on your website.', 'wp-to-social' ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Image', 'wp-to-social' ); ?></strong></td>
					<td><code>_featured_image</code></td>
					<td><?php esc_html_e( 'Uses the featured image of the news article as a thumbnail.', 'wp-to-social' ); ?></td>
				</tr>
			</tbody>
		</table>

		<p><?php esc_html_e( 'If your "News" post type has a custom field called "news_subtitle" that you would rather use as the body text, simply change the Body Text mapping from "Post Content" to "Custom: news_subtitle" in the dropdown.', 'wp-to-social' ); ?></p>
		<p><?php esc_html_e( 'Now, when you create a new News post and check "Post to LinkedIn" before publishing, the article will be shared to your LinkedIn profile automatically.', 'wp-to-social' ); ?></p>
	</div>

	<!-- Instagram example -->
	<div class="wpts-help-section">
		<h2><?php esc_html_e( 'Example: Instagram with a "Portfolio" post type', 'wp-to-social' ); ?></h2>
		<p>
			<?php esc_html_e( 'Say you run a design agency and have a "Portfolio" post type for your projects. You want to share each new project as an Instagram post. Here is how:', 'wp-to-social' ); ?>
		</p>

		<ol class="wpts-help-steps">
			<li><?php esc_html_e( 'Make sure your Instagram account is set to Business or Creator and is linked to a Facebook Page.', 'wp-to-social' ); ?></li>
			<li><?php esc_html_e( 'Go to the Modules tab and connect your Instagram account (follow the "How to connect" guide).', 'wp-to-social' ); ?></li>
			<li><?php esc_html_e( 'Go to the Post Types tab, select "Instagram", and check "Portfolio".', 'wp-to-social' ); ?></li>
			<li><?php esc_html_e( 'Go to the Field Mapping tab, select "Instagram" and "Portfolio":', 'wp-to-social' ); ?></li>
		</ol>

		<table class="wpts-help-mapping-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Instagram field', 'wp-to-social' ); ?></th>
					<th><?php esc_html_e( 'Maps to', 'wp-to-social' ); ?></th>
					<th><?php esc_html_e( 'What it does', 'wp-to-social' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><strong><?php esc_html_e( 'Caption', 'wp-to-social' ); ?></strong></td>
					<td><code>post_excerpt</code></td>
					<td><?php esc_html_e( 'Uses the excerpt as the Instagram caption. Keep it concise — max 2,200 characters.', 'wp-to-social' ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Image URL', 'wp-to-social' ); ?></strong></td>
					<td><code>_featured_image</code></td>
					<td><?php esc_html_e( 'Uses the featured image. This is required — Instagram does not allow text-only posts.', 'wp-to-social' ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Alt Text', 'wp-to-social' ); ?></strong></td>
					<td><code>_featured_image_alt</code></td>
					<td><?php esc_html_e( 'Uses the alt text from your featured image for accessibility on Instagram.', 'wp-to-social' ); ?></td>
				</tr>
			</tbody>
		</table>

		<div class="wpts-help-notice">
			<strong><?php esc_html_e( 'Good to know:', 'wp-to-social' ); ?></strong>
			<?php esc_html_e( 'Instagram requires every post to have an image, and that image must be publicly accessible on the internet. If your site is password-protected or running on localhost, Instagram will not be able to fetch the image and the post will fail.', 'wp-to-social' ); ?>
		</div>

		<p><?php esc_html_e( 'If your "Portfolio" post type has a custom field for a project description (e.g. "project_summary"), you can map the Caption to that field instead of the excerpt.', 'wp-to-social' ); ?></p>
	</div>

	<!-- Tips -->
	<div class="wpts-help-section">
		<h2><?php esc_html_e( 'Tips', 'wp-to-social' ); ?></h2>
		<ul class="wpts-help-tips">
			<li><?php esc_html_e( 'The "Post to ..." checkbox only appears for post types you have enabled on the Post Types tab.', 'wp-to-social' ); ?></li>
			<li><?php esc_html_e( 'The plugin only triggers on the first publish. Updating an already-published post will not re-share it.', 'wp-to-social' ); ?></li>
			<li><?php esc_html_e( 'If a social post fails, you can retry it from the Activity page — no need to re-publish the WordPress post.', 'wp-to-social' ); ?></li>
			<li><?php esc_html_e( 'All your API credentials and tokens are encrypted before being stored in the database.', 'wp-to-social' ); ?></li>
		</ul>
	</div>

</div>
