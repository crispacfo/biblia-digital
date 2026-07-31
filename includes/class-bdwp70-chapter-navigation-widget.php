<?php
/**
 * Chapter navigation widget for Bíblia Digital.
 *
 * @package BibliaDigitalWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'BDWP70_Chapter_Navigation_Widget', false ) ) {
	return;
}

/**
 * Widget that shows the current Bible book title and chapter grid.
 */
class BDWP70_Chapter_Navigation_Widget extends WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'bdwp70_chapter_navigation',
			__( 'Bíblia Digital - Capítulos do livro atual', 'estudobiblico-biblia-digital' ),
			array(
				'classname'                   => 'bdwp70_chapter_navigation_widget',
				'description'                 => __( 'Exibe um card com o título do livro, o capítulo atual, grade de capítulos e tradução ativa nas páginas da Bíblia.', 'estudobiblico-biblia-digital' ),
				'show_instance_in_rest'       => true,
				'customize_selective_refresh' => true,
			)
		);
	}

	/**
	 * Outputs the widget.
	 *
	 * @param array $args Widget args.
	 * @param array $instance Widget instance.
	 * @return void
	 */
	public function widget( $args, $instance ) {
		if ( ! class_exists( 'BDWP70_Plugin', false ) ) {
			return;
		}

		$plugin = BDWP70_Plugin::instance();
		$state  = $plugin->read_request_state();
		if ( empty( $state['book'] ) ) {
			return;
		}

		wp_enqueue_style( 'bdwp70-frontend' );

		$books          = $plugin->get_books( (int) $state['bible_id'] );
		$chapter_counts = $plugin->get_chapter_counts( (int) $state['bible_id'] );
		$card           = $plugin->render_chapter_navigation_card( $state, $books, $chapter_counts );
		if ( '' === $card ) {
			return;
		}

		$title = ! empty( $instance['title'] ) ? apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base ) : '';

		echo isset( $args['before_widget'] ) ? wp_kses_post( $args['before_widget'] ) : '';
		if ( '' !== $title ) {
			echo isset( $args['before_title'] ) ? wp_kses_post( $args['before_title'] ) : '';
			echo esc_html( $title );
			echo isset( $args['after_title'] ) ? wp_kses_post( $args['after_title'] ) : '';
		}
		echo $card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is escaped in render_chapter_navigation_card().
		echo isset( $args['after_widget'] ) ? wp_kses_post( $args['after_widget'] ) : '';
	}

	/**
	 * Widget form.
	 *
	 * @param array $instance Saved instance.
	 * @return void
	 */
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? (string) $instance['title'] : '';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Título opcional:', 'estudobiblico-biblia-digital' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p class="description"><?php esc_html_e( 'O card aparece automaticamente apenas nas páginas de livro ou capítulo da Bíblia Digital.', 'estudobiblico-biblia-digital' ); ?></p>
		<?php
	}

	/**
	 * Sanitizes widget options.
	 *
	 * @param array $new_instance New values.
	 * @param array $old_instance Old values.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance          = is_array( $old_instance ) ? $old_instance : array();
		$instance['title'] = isset( $new_instance['title'] ) ? sanitize_text_field( wp_unslash( $new_instance['title'] ) ) : '';
		return $instance;
	}
}
