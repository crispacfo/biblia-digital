<?php
/**
 * Search widget for Bíblia Digital.
 *
 * @package BibliaDigital
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_Widget' ) || class_exists( 'BDWP70_Search_Widget', false ) ) {
	return;
}

/**
 * Displays a Bible search form in widget areas.
 */
class BDWP70_Search_Widget extends WP_Widget {
	/**
	 * Widget constructor.
	 */
	public function __construct() {
		parent::__construct(
			'bdwp70_search',
			__( 'Bíblia Digital - Search', 'estudobiblico-biblia-digital' ),
			array(
				'classname'   => 'bdwp70_search_widget',
				'description' => __( 'Displays a separate search form for Bíblia Digital.', 'estudobiblico-biblia-digital' ),
			)
		);
	}

	/**
	 * Outputs the widget content.
	 *
	 * @param array $args     Display arguments.
	 * @param array $instance Saved values.
	 * @return void
	 */
	public function widget( $args, $instance ) {
		if ( ! class_exists( 'BDWP70_Plugin' ) ) {
			return;
		}

		wp_enqueue_style( 'bdwp70-frontend' );
		$title       = isset( $instance['title'] ) ? (string) $instance['title'] : __( 'Search the Bible', 'estudobiblico-biblia-digital' );
		$placeholder = isset( $instance['placeholder'] ) ? (string) $instance['placeholder'] : __( 'Enter a word or phrase', 'estudobiblico-biblia-digital' );
		$show_book   = isset( $instance['show_book'] ) ? (int) $instance['show_book'] : 1;

		echo isset( $args['before_widget'] ) ? wp_kses_post( $args['before_widget'] ) : '';
		echo wp_kses(
			BDWP70_Plugin::instance()->render_search_form(
				array(
					'title'       => $title,
					'placeholder' => $placeholder,
					'show_book'   => $show_book,
				)
			),
			function_exists( 'bdwp70_allowed_form_html' ) ? bdwp70_allowed_form_html() : wp_kses_allowed_html( 'post' )
		);
		echo isset( $args['after_widget'] ) ? wp_kses_post( $args['after_widget'] ) : '';
	}

	/**
	 * Outputs the widget form in the admin.
	 *
	 * @param array $instance Saved values.
	 * @return void
	 */
	public function form( $instance ) {
		$title       = isset( $instance['title'] ) ? (string) $instance['title'] : __( 'Search the Bible', 'estudobiblico-biblia-digital' );
		$placeholder = isset( $instance['placeholder'] ) ? (string) $instance['placeholder'] : __( 'Enter a word or phrase', 'estudobiblico-biblia-digital' );
		$show_book   = isset( $instance['show_book'] ) ? (int) $instance['show_book'] : 1;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'estudobiblico-biblia-digital' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'placeholder' ) ); ?>"><?php esc_html_e( 'Field text:', 'estudobiblico-biblia-digital' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'placeholder' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'placeholder' ) ); ?>" type="text" value="<?php echo esc_attr( $placeholder ); ?>">
		</p>
		<p>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'show_book' ) ); ?>" value="1" <?php checked( 1, $show_book ); ?>>
				<?php esc_html_e( 'Allow choosing the book in the search', 'estudobiblico-biblia-digital' ); ?>
			</label>
		</p>
		<p><em><?php esc_html_e( 'Search always uses the active Bible set in Settings > Bíblia Digital.', 'estudobiblico-biblia-digital' ); ?></em></p>
		<?php
	}

	/**
	 * Saves widget options.
	 *
	 * @param array $new_instance New values.
	 * @param array $old_instance Old values.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		return array(
			'title'       => isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : __( 'Search the Bible', 'estudobiblico-biblia-digital' ),
			'placeholder' => isset( $new_instance['placeholder'] ) ? sanitize_text_field( $new_instance['placeholder'] ) : __( 'Enter a word or phrase', 'estudobiblico-biblia-digital' ),
			'show_book'   => ! empty( $new_instance['show_book'] ) ? 1 : 0,
		);
	}
}
