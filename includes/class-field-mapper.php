<?php
/**
 * Field mapper — resolve WP fields to platform fields based on saved mapping.
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Field_Mapper {

	/**
	 * Get default field mapping for a platform.
	 *
	 * @param string $platform Platform slug.
	 * @return array
	 */
	public static function get_defaults( $platform ) {
		$defaults = array(
			'linkedin'  => array(
				'title' => 'post_title',
				'body'  => 'post_content',
				'url'   => '_permalink',
				'image' => '_featured_image',
			),
			'instagram' => array(
				'caption'   => 'post_excerpt',
				'image_url' => '_featured_image',
				'alt_text'  => '_featured_image_alt',
			),
		);

		return $defaults[ $platform ] ?? array();
	}

	/**
	 * Get saved mapping for a platform and post type.
	 *
	 * @param string $platform  Platform slug.
	 * @param string $post_type Post type slug.
	 * @return array
	 */
	public static function get_mapping( $platform, $post_type ) {
		$all = get_option( 'wpts_field_mapping', array() );
		return $all[ $platform ][ $post_type ] ?? self::get_defaults( $platform );
	}

	/**
	 * Save mapping for a platform and post type.
	 *
	 * @param string $platform  Platform slug.
	 * @param string $post_type Post type slug.
	 * @param array  $mapping   Field mapping array.
	 */
	public static function save_mapping( $platform, $post_type, $mapping ) {
		$all = get_option( 'wpts_field_mapping', array() );
		$all[ $platform ][ $post_type ] = array_map( 'sanitize_text_field', $mapping );
		update_option( 'wpts_field_mapping', $all );
	}

	/**
	 * Resolve a mapping against a specific post, returning actual values.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $platform Platform slug.
	 * @return array Resolved values keyed by platform field name.
	 */
	public static function resolve( $post_id, $platform ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		$mapping = self::get_mapping( $platform, $post->post_type );
		$values  = array();

		foreach ( $mapping as $platform_field => $wp_field ) {
			$values[ $platform_field ] = self::get_field_value( $post, $wp_field );
		}

		return $values;
	}

	/**
	 * Get value of a WP field from a post.
	 *
	 * @param WP_Post $post     Post object.
	 * @param string  $wp_field Field identifier.
	 * @return string
	 */
	private static function get_field_value( $post, $wp_field ) {
		switch ( $wp_field ) {
			case 'post_title':
				return $post->post_title;

			case 'post_content':
				$content = $post->post_content;
				// Decode HTML entities so tags can be stripped properly.
				$content = html_entity_decode( $content, ENT_QUOTES, 'UTF-8' );
				// Remove block comments, shortcodes, and all HTML tags.
				$content = preg_replace( '/<!--.*?-->/', '', $content );
				$content = wp_strip_all_tags( strip_shortcodes( $content ) );
				// Collapse multiple whitespace/newlines into clean paragraphs.
				$content = preg_replace( '/\s*\n\s*\n\s*/', "\n\n", $content );
				return trim( $content );

			case 'post_excerpt':
				$excerpt = $post->post_excerpt;
				if ( empty( $excerpt ) ) {
					$excerpt = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 55 );
				}
				return $excerpt;

			case '_permalink':
				return get_permalink( $post->ID );

			case '_featured_image':
				return get_the_post_thumbnail_url( $post->ID, 'large' ) ?: '';

			case '_featured_image_alt':
				$thumb_id = get_post_thumbnail_id( $post->ID );
				return $thumb_id ? ( get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) ?: '' ) : '';

			default:
				// Custom field / post meta.
				$value = get_post_meta( $post->ID, $wp_field, true ) ?: '';
				// If the value contains HTML, strip it cleanly.
				if ( is_string( $value ) && ( str_contains( $value, '<' ) || str_contains( $value, '&lt;' ) ) ) {
					$value = html_entity_decode( $value, ENT_QUOTES, 'UTF-8' );
					$value = wp_strip_all_tags( $value );
					$value = preg_replace( '/\s*\n\s*\n\s*/', "\n\n", $value );
					$value = trim( $value );
				}
				return $value;
		}
	}

	/**
	 * Get available WP fields for a post type (for the mapping UI dropdown).
	 *
	 * @param string $post_type Post type slug.
	 * @return array Associative array of field_key => label.
	 */
	public static function get_available_fields( $post_type ) {
		$fields = array(
			'post_title'           => __( 'Post Title', 'wp-to-social' ),
			'post_content'         => __( 'Post Content (plain text)', 'wp-to-social' ),
			'post_excerpt'         => __( 'Post Excerpt', 'wp-to-social' ),
			'_permalink'           => __( 'Permalink (auto)', 'wp-to-social' ),
			'_featured_image'      => __( 'Featured Image', 'wp-to-social' ),
			'_featured_image_alt'  => __( 'Featured Image Alt Text', 'wp-to-social' ),
		);

		// Add registered meta keys for this post type.
		global $wpdb;
		$meta_keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_key
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE p.post_type = %s
				AND pm.meta_key NOT LIKE %s
				ORDER BY pm.meta_key
				LIMIT 50",
				$post_type,
				$wpdb->esc_like( '_' ) . '%'
			)
		);

		foreach ( $meta_keys as $key ) {
			$fields[ $key ] = sprintf( __( 'Custom: %s', 'wp-to-social' ), $key );
		}

		return $fields;
	}
}
