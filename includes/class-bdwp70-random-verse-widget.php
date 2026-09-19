<?php
/**
 * Random verse widget.
 *
 * @package BibliaDigital
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_Widget' ) ) {
	return;
}

if ( class_exists( 'BDWP70_Random_Verse_Widget', false ) ) {
	return;
}

/**
 * Displays a random Bible verse in any widget area.
 */
class BDWP70_Random_Verse_Widget extends WP_Widget {
	/**
	 * Widget constructor.
	 */
	public function __construct() {
		parent::__construct(
			'bdwp70_random_verse',
			__( 'Bíblia Digital - Random verse', 'estudobiblico-biblia-digital' ),
			array(
				'classname'                   => 'bdwp70_random_verse_widget',
				'description'                 => __( 'Displays a random Bible verse in a widget area.', 'estudobiblico-biblia-digital' ),
				'customize_selective_refresh' => true,
			)
		);
	}

	/**
	 * Outputs the widget content.
	 *
	 * @param array $args     Display arguments.
	 * @param array $instance Saved values.
	 */
	public function widget( $args, $instance ) {
		$title          = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Verse of the moment', 'estudobiblico-biblia-digital' );
		$show_reference = isset( $instance['show_reference'] ) ? (bool) $instance['show_reference'] : true;
		$link_reference = isset( $instance['link_reference'] ) ? (bool) $instance['link_reference'] : true;
		$show_button    = isset( $instance['show_button'] ) ? (bool) $instance['show_button'] : false;
		$button_text    = ! empty( $instance['button_text'] ) ? sanitize_text_field( $instance['button_text'] ) : __( 'Read the chapter', 'estudobiblico-biblia-digital' );
		$book_seq       = isset( $instance['book_seq'] ) ? absint( $instance['book_seq'] ) : 0;
		$bible_id       = class_exists( 'BDWP70_Plugin' ) ? BDWP70_Plugin::instance()->site_active_bible_id() : BDWP70_Activator::get_active_bible_id();

		if ( wp_style_is( 'bdwp70-frontend', 'registered' ) ) {
			wp_enqueue_style( 'bdwp70-frontend' );
		}

		echo isset( $args['before_widget'] ) ? wp_kses_post( $args['before_widget'] ) : '';

		if ( ! empty( $title ) ) {
			echo isset( $args['before_title'] ) ? wp_kses_post( $args['before_title'] ) : '<h2 class="widget-title">';
			echo esc_html( $title );
			echo isset( $args['after_title'] ) ? wp_kses_post( $args['after_title'] ) : '</h2>';
		}

		$plugin = BDWP70_Plugin::instance();
		$verse  = $plugin->get_random_verse( $book_seq, $bible_id );
		$books  = $plugin->get_books( $bible_id );

		echo '<div class="bdwp70-random-widget">';

		if ( ! $verse ) {
			echo '<p class="bdwp70-random-widget__empty">' . esc_html__( 'No verses available. Make sure the Bible data has been imported.', 'estudobiblico-biblia-digital' ) . '</p>';
			echo '</div>';
			echo isset( $args['after_widget'] ) ? wp_kses_post( $args['after_widget'] ) : '';
			return;
		}

		$book_name = $plugin->book_name_from_seq( $books, (int) $verse->livroseq, (string) $verse->livro );
		$reference = $book_name . ' ' . (int) $verse->capitulo . ':' . (int) $verse->versiculo;
		$url       = $plugin->verse_url( (int) $verse->livroseq, (int) $verse->capitulo, (int) $verse->versiculo, $books );

		echo '<blockquote class="bdwp70-random-widget__quote">';
		echo '<p>' . esc_html( trim( (string) $verse->palavra ) ) . '</p>';

		if ( $show_reference ) {
			echo '<footer class="bdwp70-random-widget__ref">';
			if ( $link_reference ) {
				echo '<a href="' . esc_url( $url ) . '">' . esc_html( $reference ) . '</a>';
			} else {
				echo esc_html( $reference );
			}
			echo '</footer>';
		}
		echo '</blockquote>';

		if ( $show_button ) {
			echo '<p class="bdwp70-random-widget__more"><a href="' . esc_url( $url ) . '">' . esc_html( $button_text ) . '</a></p>';
		}

		echo '</div>';
		echo isset( $args['after_widget'] ) ? wp_kses_post( $args['after_widget'] ) : '';
	}

	/**
	 * Saves widget options.
	 *
	 * @param array $new_instance New values.
	 * @param array $old_instance Old values.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance                   = array();
		$instance['title']          = isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['book_seq']       = isset( $new_instance['book_seq'] ) ? absint( $new_instance['book_seq'] ) : 0;
		$instance['bible_id']       = 0; // Always follows the active Bible selected in this site settings.
		$instance['show_reference'] = ! empty( $new_instance['show_reference'] ) ? 1 : 0;
		$instance['link_reference'] = ! empty( $new_instance['link_reference'] ) ? 1 : 0;
		$instance['show_button']    = ! empty( $new_instance['show_button'] ) ? 1 : 0;
		$instance['button_text']    = isset( $new_instance['button_text'] ) ? sanitize_text_field( $new_instance['button_text'] ) : __( 'Read the chapter', 'estudobiblico-biblia-digital' );
		return $instance;
	}

	/**
	 * Outputs the widget form in the admin.
	 *
	 * @param array $instance Saved values.
	 */
	public function form( $instance ) {
		$title          = isset( $instance['title'] ) ? $instance['title'] : __( 'Verse of the moment', 'estudobiblico-biblia-digital' );
		$book_seq       = isset( $instance['book_seq'] ) ? absint( $instance['book_seq'] ) : 0;
		$show_reference = isset( $instance['show_reference'] ) ? (bool) $instance['show_reference'] : true;
		$link_reference = isset( $instance['link_reference'] ) ? (bool) $instance['link_reference'] : true;
		$show_button    = isset( $instance['show_button'] ) ? (bool) $instance['show_button'] : false;
		$button_text    = ! empty( $instance['button_text'] ) ? sanitize_text_field( $instance['button_text'] ) : __( 'Read the chapter', 'estudobiblico-biblia-digital' );
		$bible_id       = class_exists( 'BDWP70_Plugin' ) ? BDWP70_Plugin::instance()->site_active_bible_id() : BDWP70_Activator::get_active_bible_id();
		$versions       = class_exists( 'BDWP70_Plugin' ) ? BDWP70_Plugin::instance()->get_bible_versions() : array();
		$active_label   = class_exists( 'BDWP70_Plugin' ) ? BDWP70_Plugin::instance()->get_bible_version_label( $bible_id ) : __( 'Active Bible', 'estudobiblico-biblia-digital' );
		$books          = class_exists( 'BDWP70_Plugin' ) ? BDWP70_Plugin::instance()->get_books( $bible_id ) : array();
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'estudobiblico-biblia-digital' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>

		<p>
			<strong><?php esc_html_e( 'Bible / language:', 'estudobiblico-biblia-digital' ); ?></strong><br>
			<span><?php echo esc_html( $active_label ); ?></span>
		</p>
		<p class="description"><?php echo wp_kses_post( __( 'This widget always uses the active Bible set in <strong>Settings &gt; Bíblia Digital</strong> for this network site, so the random verse never mixes languages on the frontend.', 'estudobiblico-biblia-digital' ) ); ?></p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'book_seq' ) ); ?>"><?php esc_html_e( 'Book:', 'estudobiblico-biblia-digital' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'book_seq' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'book_seq' ) ); ?>">
				<option value="0" <?php selected( 0, $book_seq ); ?>><?php esc_html_e( 'All books', 'estudobiblico-biblia-digital' ); ?></option>
				<?php foreach ( $books as $book ) : ?>
					<option value="<?php echo esc_attr( (int) $book->livro_seq ); ?>" <?php selected( (int) $book->livro_seq, $book_seq ); ?>>
						<?php echo esc_html( $book->livro_desc ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>

		<p>
			<input id="<?php echo esc_attr( $this->get_field_id( 'show_reference' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_reference' ) ); ?>" type="checkbox" value="1" <?php checked( $show_reference ); ?>>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_reference' ) ); ?>"><?php esc_html_e( 'Show the Bible reference', 'estudobiblico-biblia-digital' ); ?></label>
		</p>

		<p>
			<input id="<?php echo esc_attr( $this->get_field_id( 'link_reference' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'link_reference' ) ); ?>" type="checkbox" value="1" <?php checked( $link_reference ); ?>>
			<label for="<?php echo esc_attr( $this->get_field_id( 'link_reference' ) ); ?>"><?php esc_html_e( 'Link the reference to the verse page', 'estudobiblico-biblia-digital' ); ?></label>
		</p>

		<p>
			<input id="<?php echo esc_attr( $this->get_field_id( 'show_button' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_button' ) ); ?>" type="checkbox" value="1" <?php checked( $show_button ); ?>>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_button' ) ); ?>"><?php esc_html_e( 'Show a link after the verse', 'estudobiblico-biblia-digital' ); ?></label>
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'button_text' ) ); ?>"><?php esc_html_e( 'Link text:', 'estudobiblico-biblia-digital' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'button_text' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'button_text' ) ); ?>" type="text" value="<?php echo esc_attr( $button_text ); ?>">
			<span class="description"><?php esc_html_e( 'E.g.: Read the chapter, Ler o capítulo, Leer el capítulo.', 'estudobiblico-biblia-digital' ); ?></span>
		</p>
		<?php
	}
}
