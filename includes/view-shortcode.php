<?php
/**
 * Frontend shortcode view.
 *
 * @package BibliaDigitalWP
 *
 * @var BDWP70_Plugin $this
 * @var array $atts
 * @var array $state
 * @var array $bdwp70_books
 * @var array $bdwp70_versions
 * @var array $results
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bdwp70_current_url        = $this->maybe_add_bible_version_arg_to_url( home_url( user_trailingslashit( $this->seo_base() ) ), (int) $state['bible_id'] );
$bdwp70_selected_book      = (int) $state['book'];
$bdwp70_selected_book_name = $this->book_name_from_seq( $bdwp70_books, $bdwp70_selected_book, '' );
$bdwp70_chapter_url        = ( $bdwp70_selected_book && ! empty( $state['chapter'] ) ) ? $this->chapter_url( $bdwp70_selected_book, (int) $state['chapter'], $bdwp70_books, (int) $state['bible_id'] ) : $bdwp70_current_url;
$bdwp70_chapter_counts     = $this->get_chapter_counts( (int) $state['bible_id'] );
$bdwp70_title_image_url    = $this->title_image_url();
$bdwp70_hero_logo_url      = apply_filters( 'bdwp70_hero_logo_url', BDWP70_URL . 'assets/images/logo-estudo-biblico.webp' );
$bdwp70_hero_bible_url     = BDWP70_URL . 'assets/images/open-bible-hero.svg';
$bdwp70_old_books          = array();
$bdwp70_new_books          = array();
$bdwp70_root_class         = 'bdwp70 bdwp70--mode-' . sanitize_html_class( (string) $state['mode'] );
$bdwp70_version_label      = $this->get_bible_version_label( (int) $state['bible_id'] );
$bdwp70_quick_cards        = $this->quick_cards();

foreach ( $bdwp70_books as $bdwp70_book ) {
	if ( (int) $bdwp70_book->livro_seq <= 39 ) {
		$bdwp70_old_books[] = $bdwp70_book;
	} else {
		$bdwp70_new_books[] = $bdwp70_book;
	}
}

$bdwp70_previous_chapter_url = '';
$bdwp70_next_chapter_url     = '';
if ( 'reader' === $state['mode'] && $bdwp70_selected_book && ! empty( $state['chapter'] ) ) {
	$bdwp70_current_chapter = (int) $state['chapter'];
	$bdwp70_chapters        = isset( $bdwp70_chapter_counts[ $bdwp70_selected_book ] ) ? (int) $bdwp70_chapter_counts[ $bdwp70_selected_book ] : 0;
	if ( $bdwp70_current_chapter > 1 ) {
		$bdwp70_previous_chapter_url = $this->chapter_url( $bdwp70_selected_book, $bdwp70_current_chapter - 1, $bdwp70_books, (int) $state['bible_id'] );
	}
	if ( $bdwp70_chapters && $bdwp70_current_chapter < $bdwp70_chapters ) {
		$bdwp70_next_chapter_url = $this->chapter_url( $bdwp70_selected_book, $bdwp70_current_chapter + 1, $bdwp70_books, (int) $state['bible_id'] );
	}
}

// Atributos de navegação entre capítulos (setas do teclado em frontend.js).
$bdwp70_nav_attrs = '';
if ( $bdwp70_previous_chapter_url ) {
	$bdwp70_nav_attrs .= ' data-bdwp70-prev="' . esc_url( $bdwp70_previous_chapter_url ) . '"';
}
if ( $bdwp70_next_chapter_url ) {
	$bdwp70_nav_attrs .= ' data-bdwp70-next="' . esc_url( $bdwp70_next_chapter_url ) . '"';
}
?>
<div class="<?php echo esc_attr( $bdwp70_root_class ); ?>" data-bdwp70<?php echo $bdwp70_nav_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- URLs escapadas com esc_url() acima. ?>>
	<?php echo $this->render_breadcrumbs( $state, $bdwp70_books ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<?php if ( 'books' !== $state['mode'] ) : ?>
		<?php if ( $bdwp70_title_image_url ) : ?>
			<div class="bdwp70__title-image">
				<img src="<?php echo esc_url( $bdwp70_title_image_url ); ?>" alt="<?php echo esc_attr( $atts['title'] ); ?>" loading="lazy">
			</div>
		<?php endif; ?>
		<h1 class="bdwp70__title"><?php echo esc_html( $atts['title'] ); ?></h1>
	<?php endif; ?>

	<?php if ( 'books' === $state['mode'] ) : ?>
		<section class="bdwp70__home-hero bdwp70__home-hero--brand" aria-labelledby="bdwp70-home-title">
			<div class="bdwp70__hero-visual" aria-hidden="true">
				<img src="<?php echo esc_url( $bdwp70_hero_bible_url ); ?>" alt="" loading="lazy">
			</div>
			<div class="bdwp70__home-hero-content">
				<div class="bdwp70__hero-brand">
					<img src="<?php echo esc_url( $bdwp70_hero_logo_url ); ?>" alt="<?php echo esc_attr__( 'Estudo Bíblico', 'estudobiblico-biblia-digital' ); ?>" loading="lazy">
				</div>
				<span class="bdwp70__hero-eyebrow"><?php esc_html_e( '✥ The Word that transforms ✥', 'estudobiblico-biblia-digital' ); ?></span>
				<h1 id="bdwp70-home-title" class="bdwp70__hero-title"><?php echo esc_html( $atts['title'] ); ?></h1>
				<p><?php esc_html_e( 'Read, study, and meditate on the Word of God wherever you are. Free and complete access to the Holy Bible.', 'estudobiblico-biblia-digital' ); ?></p>
				<div class="bdwp70__hero-actions">
					<a class="bdwp70__hero-button" href="#bdwp70-livros"><?php esc_html_e( '📖 Read now', 'estudobiblico-biblia-digital' ); ?></a>
				</div>
				<?php echo $this->render_version_switcher( (int) $state['bible_id'], $bdwp70_current_url, 'hero' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</section>

		<section class="bdwp70__books-home" id="bdwp70-livros" aria-labelledby="bdwp70-books-title">
			<div class="bdwp70__books-headline">
				<div>
					<h2 id="bdwp70-books-title"><?php esc_html_e( 'Online Bible', 'estudobiblico-biblia-digital' ); ?></h2>
					<p><?php esc_html_e( 'Browse all the books of the Holy Bible quickly and in order.', 'estudobiblico-biblia-digital' ); ?></p>
				</div>
			</div>

			<?php if ( ! empty( $bdwp70_quick_cards ) ) : ?>
				<div class="bdwp70__quick-cards" id="bdwp70-como-usar" aria-label="<?php esc_attr_e( 'Bíblia Digital quick links', 'estudobiblico-biblia-digital' ); ?>">
					<?php foreach ( $bdwp70_quick_cards as $bdwp70_card ) : ?>
						<a class="bdwp70__quick-card bdwp70__quick-card--<?php echo esc_attr( sanitize_html_class( $bdwp70_card['style'] ) ); ?>" href="<?php echo esc_url( $bdwp70_card['url'] ); ?>">
							<span class="bdwp70__quick-icon" aria-hidden="true"><?php echo esc_html( $bdwp70_card['icon'] ); ?></span>
							<span><strong><?php echo esc_html( $bdwp70_card['title'] ); ?></strong><small><?php echo esc_html( $bdwp70_card['description'] ); ?></small></span>
							<span class="bdwp70__quick-arrow" aria-hidden="true">›</span>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="bdwp70__testaments bdwp70__testaments--cards">
				<section class="bdwp70__testament-card" aria-labelledby="bdwp70-old-testament">
					<header class="bdwp70__testament-header">
						<span class="bdwp70__testament-icon" aria-hidden="true">📖</span>
						<h2 id="bdwp70-old-testament"><?php esc_html_e( 'Old Testament', 'estudobiblico-biblia-digital' ); ?></h2>
						<a href="#bdwp70-old-testament"><?php esc_html_e( 'View all', 'estudobiblico-biblia-digital' ); ?></a>
					</header>
					<ul class="bdwp70__book-list bdwp70__book-list--old">
						<?php foreach ( $bdwp70_old_books as $bdwp70_book ) : ?>
							<?php $bdwp70_book_url = $this->maybe_add_bible_version_arg_to_url( home_url( user_trailingslashit( $this->seo_base() . '/' . $this->book_slug_from_seq( (int) $bdwp70_book->livro_seq, $bdwp70_books ) ) ), (int) $state['bible_id'] ); ?>
							<li><a href="<?php echo esc_url( $bdwp70_book_url ); ?>"><span aria-hidden="true">›</span><?php echo esc_html( $bdwp70_book->livro_desc ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</section>

				<section class="bdwp70__testament-card" aria-labelledby="bdwp70-new-testament">
					<header class="bdwp70__testament-header">
						<span class="bdwp70__testament-icon" aria-hidden="true">✝</span>
						<h2 id="bdwp70-new-testament"><?php esc_html_e( 'New Testament', 'estudobiblico-biblia-digital' ); ?></h2>
						<a href="#bdwp70-new-testament"><?php esc_html_e( 'View all', 'estudobiblico-biblia-digital' ); ?></a>
					</header>
					<ul class="bdwp70__book-list bdwp70__book-list--new">
						<?php foreach ( $bdwp70_new_books as $bdwp70_book ) : ?>
							<?php $bdwp70_book_url = $this->maybe_add_bible_version_arg_to_url( home_url( user_trailingslashit( $this->seo_base() . '/' . $this->book_slug_from_seq( (int) $bdwp70_book->livro_seq, $bdwp70_books ) ) ), (int) $state['bible_id'] ); ?>
							<li><a href="<?php echo esc_url( $bdwp70_book_url ); ?>"><span aria-hidden="true">›</span><?php echo esc_html( $bdwp70_book->livro_desc ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</section>
			</div>
		</section>
		<div class="bdwp70__books-version-panel" id="bdwp70-versao-biblia" aria-label="<?php esc_attr_e( 'Bible translation choice', 'estudobiblico-biblia-digital' ); ?>">
			<span class="bdwp70__books-version-panel-text"><?php esc_html_e( 'Choose the Bible translation to continue reading in this version:', 'estudobiblico-biblia-digital' ); ?></span>
			<?php echo $this->render_version_switcher( (int) $state['bible_id'], $bdwp70_current_url, 'books-footer' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	<?php elseif ( 'book' === $state['mode'] && $bdwp70_selected_book_name ) : ?>
		<div class="bdwp70__book-page">
			<h2><?php echo esc_html( $bdwp70_selected_book_name ); ?></h2>
			<p><?php esc_html_e( 'Select the chapter you want to read.', 'estudobiblico-biblia-digital' ); ?></p>
			<div class="bdwp70__chapter-grid">
				<?php $bdwp70_chapters = isset( $bdwp70_chapter_counts[ $bdwp70_selected_book ] ) ? (int) $bdwp70_chapter_counts[ $bdwp70_selected_book ] : 0; ?>
				<?php for ( $bdwp70_i = 1; $bdwp70_i <= $bdwp70_chapters; $bdwp70_i++ ) : ?>
					<a href="<?php echo esc_url( $this->chapter_url( $bdwp70_selected_book, $bdwp70_i, $bdwp70_books, (int) $state['bible_id'] ) ); ?>"><?php echo esc_html( (string) $bdwp70_i ); ?></a>
				<?php endfor; ?>
			</div>
			<div class="bdwp70__summary bdwp70__summary--after-chapters" aria-live="polite">
				<?php echo $this->render_version_switcher( (int) $state['bible_id'], $bdwp70_current_url, 'chapter-list' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<p><a class="bdwp70__clear" href="<?php echo esc_url( $bdwp70_current_url ); ?>"><?php esc_html_e( 'Back to books', 'estudobiblico-biblia-digital' ); ?></a></p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $results['items'] ) ) : ?>
		<?php if ( 'chapter' === $results['mode'] && $bdwp70_selected_book_name ) : ?>
			<div class="bdwp70__reader-layout bdwp70__reader-layout--single">
				<article class="bdwp70__reader-main">
					<header class="bdwp70__chapter-heading">
						<h2><a href="<?php echo esc_url( $bdwp70_chapter_url ); ?>"><?php echo esc_html( $bdwp70_selected_book_name . ' ' . (int) $state['chapter'] ); ?></a></h2>
						<nav class="bdwp70__chapter-nav bdwp70__chapter-nav--top" aria-label="<?php esc_attr_e( 'Chapter navigation', 'estudobiblico-biblia-digital' ); ?>">
							<?php
							if ( $bdwp70_previous_chapter_url ) :
								?>
								<a href="<?php echo esc_url( $bdwp70_previous_chapter_url ); ?>"><?php esc_html_e( '← Previous chapter', 'estudobiblico-biblia-digital' ); ?></a><?php endif; ?>
							<?php echo $this->render_chapter_picker( $state, $bdwp70_books, $bdwp70_chapter_counts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php
							if ( $bdwp70_next_chapter_url ) :
								?>
								<a href="<?php echo esc_url( $bdwp70_next_chapter_url ); ?>"><?php esc_html_e( 'Next chapter →', 'estudobiblico-biblia-digital' ); ?></a><?php endif; ?>
						</nav>
						<?php $bdwp70_reading_options_id = 'bdwp70-reading-options-' . (int) $state['book'] . '-' . (int) $state['chapter']; ?>
						<div class="bdwp70__reading-tools bdwp70__reading-panel" aria-label="<?php esc_attr_e( 'Reading settings', 'estudobiblico-biblia-digital' ); ?>">
							<button type="button" class="bdwp70__reading-trigger" data-bdwp70-panel-toggle aria-expanded="false" aria-controls="<?php echo esc_attr( $bdwp70_reading_options_id ); ?>">
								<span>Aa</span>
								<span class="bdwp70__reading-trigger-icon" aria-hidden="true">☷</span>
							</button>
							<div id="<?php echo esc_attr( $bdwp70_reading_options_id ); ?>" class="bdwp70__reading-options" data-bdwp70-panel hidden>
								<header class="bdwp70__reading-options-head">
									<span class="bdwp70__reading-options-icon" aria-hidden="true">☷</span>
									<strong><?php esc_html_e( 'Reading options', 'estudobiblico-biblia-digital' ); ?></strong>
								</header>

								<section class="bdwp70__reading-option-group" aria-labelledby="bdwp70-font-size-label">
									<span id="bdwp70-font-size-label" class="bdwp70__reading-option-label"><?php esc_html_e( 'Font size', 'estudobiblico-biblia-digital' ); ?></span>
									<div class="bdwp70__reading-size-row">
										<button type="button" class="bdwp70__reading-round" data-bdwp70-font="decrease" aria-label="<?php esc_attr_e( 'Decrease font size', 'estudobiblico-biblia-digital' ); ?>">A−</button>
										<span class="bdwp70__reading-size-status" data-bdwp70-font-label data-label-small="<?php esc_attr_e( 'Small', 'estudobiblico-biblia-digital' ); ?>" data-label-medium="<?php esc_attr_e( 'Medium', 'estudobiblico-biblia-digital' ); ?>" data-label-large="<?php esc_attr_e( 'Large', 'estudobiblico-biblia-digital' ); ?>"><strong>Aa</strong><small><?php esc_html_e( 'Medium', 'estudobiblico-biblia-digital' ); ?></small></span>
										<button type="button" class="bdwp70__reading-round" data-bdwp70-font="increase" aria-label="<?php esc_attr_e( 'Increase font size', 'estudobiblico-biblia-digital' ); ?>">A+</button>
									</div>
								</section>

								<section class="bdwp70__reading-option-group" aria-labelledby="bdwp70-format-label">
									<span id="bdwp70-format-label" class="bdwp70__reading-option-label"><?php esc_html_e( 'Text layout', 'estudobiblico-biblia-digital' ); ?></span>
									<div class="bdwp70__reading-choice-grid" role="group" aria-label="<?php esc_attr_e( 'Bible text layout', 'estudobiblico-biblia-digital' ); ?>">
										<button type="button" data-bdwp70-format="verse" class="is-active"><span aria-hidden="true">☷</span><?php esc_html_e( 'Verse by verse', 'estudobiblico-biblia-digital' ); ?></button>
										<button type="button" data-bdwp70-format="continuous"><span aria-hidden="true">☰</span><?php esc_html_e( 'Continuous', 'estudobiblico-biblia-digital' ); ?></button>
									</div>
								</section>

								<section class="bdwp70__reading-option-group" aria-labelledby="bdwp70-font-family-label">
									<span id="bdwp70-font-family-label" class="bdwp70__reading-option-label"><?php esc_html_e( 'Reading font', 'estudobiblico-biblia-digital' ); ?></span>
									<div class="bdwp70__reading-choice-grid" role="group" aria-label="<?php esc_attr_e( 'Font for Bible reading', 'estudobiblico-biblia-digital' ); ?>">
										<button type="button" data-bdwp70-font-family="default" class="is-active"><strong>Aa</strong><?php esc_html_e( 'Default', 'estudobiblico-biblia-digital' ); ?></button>
										<button type="button" data-bdwp70-font-family="lexend"><strong>Aa</strong><?php esc_html_e( 'Lexend', 'estudobiblico-biblia-digital' ); ?></button>
									</div>
								</section>

								<section class="bdwp70__reading-option-group" aria-labelledby="bdwp70-bg-label">
									<span id="bdwp70-bg-label" class="bdwp70__reading-option-label"><?php esc_html_e( 'Reading background', 'estudobiblico-biblia-digital' ); ?></span>
									<div class="bdwp70__reading-bg-row" role="group" aria-label="<?php esc_attr_e( 'Reading background color', 'estudobiblico-biblia-digital' ); ?>">
										<button type="button" data-bdwp70-bg="light"><?php esc_html_e( 'Light', 'estudobiblico-biblia-digital' ); ?></button>
										<button type="button" data-bdwp70-bg="sepia"><?php esc_html_e( 'Sepia', 'estudobiblico-biblia-digital' ); ?></button>
										<button type="button" data-bdwp70-bg="soft"><?php esc_html_e( 'Soft', 'estudobiblico-biblia-digital' ); ?></button>
										<button type="button" data-bdwp70-bg="dark"><?php esc_html_e( 'Dark', 'estudobiblico-biblia-digital' ); ?></button>
									</div>
								</section>
							</div>
						</div>
					</header>

					<div class="bdwp70__sticky-bar" data-bdwp70-sticky-bar>
						<span class="bdwp70__sticky-ref">
							<strong><?php echo esc_html( $bdwp70_selected_book_name ); ?></strong>
							<span><?php echo esc_html( (string) (int) $state['chapter'] ); ?></span>
						</span>
						<span class="bdwp70__sticky-nav">
							<?php if ( $bdwp70_previous_chapter_url ) : ?>
								<a href="<?php echo esc_url( $bdwp70_previous_chapter_url ); ?>" rel="prev" aria-label="<?php esc_attr_e( 'Previous chapter', 'estudobiblico-biblia-digital' ); ?>"><span aria-hidden="true">←</span></a>
							<?php endif; ?>
							<?php if ( $bdwp70_next_chapter_url ) : ?>
								<a href="<?php echo esc_url( $bdwp70_next_chapter_url ); ?>" rel="next" aria-label="<?php esc_attr_e( 'Next chapter', 'estudobiblico-biblia-digital' ); ?>"><span aria-hidden="true">→</span></a>
							<?php endif; ?>
						</span>
					</div>

					<div class="bdwp70__verses bdwp70__verses--chapter">
						<?php foreach ( $results['items'] as $bdwp70_verse ) : ?>
							<?php
							$bdwp70_book_name   = $this->book_name_from_seq( $bdwp70_books, (int) $bdwp70_verse->livroseq, (string) $bdwp70_verse->livro );
							$bdwp70_verse_link  = $this->verse_url( (int) $bdwp70_verse->livroseq, (int) $bdwp70_verse->capitulo, (int) $bdwp70_verse->versiculo, $bdwp70_books, (int) $state['bible_id'] );
							$bdwp70_is_selected = ! empty( $state['verse'] ) && (int) $state['verse'] === (int) $bdwp70_verse->versiculo && (int) $state['chapter'] === (int) $bdwp70_verse->capitulo && (int) $state['book'] === (int) $bdwp70_verse->livroseq;
							?>
							<p class="bdwp70__verse-line <?php echo $bdwp70_is_selected ? 'is-selected' : ''; ?>" id="<?php echo esc_attr( sanitize_title( $bdwp70_book_name ) . '-' . (int) $bdwp70_verse->capitulo . '-' . (int) $bdwp70_verse->versiculo ); ?>" data-bdwp70-verse-url="<?php echo esc_url( $bdwp70_verse_link ); ?>">
								<a class="bdwp70__verse-number" href="<?php echo esc_url( $bdwp70_verse_link ); ?>" aria-label="<?php echo esc_attr( $bdwp70_book_name . ' ' . (int) $bdwp70_verse->capitulo . ':' . (int) $bdwp70_verse->versiculo ); ?>"><?php echo esc_html( (int) $bdwp70_verse->versiculo ); ?></a>
								<span><?php echo esc_html( trim( (string) $bdwp70_verse->palavra ) ); ?></span>
							</p>
						<?php endforeach; ?>
					</div>

					<nav class="bdwp70__chapter-nav bdwp70__chapter-nav--bottom" aria-label="<?php esc_attr_e( 'Chapter navigation in the footer', 'estudobiblico-biblia-digital' ); ?>">
						<?php
						if ( $bdwp70_previous_chapter_url ) :
							?>
							<a href="<?php echo esc_url( $bdwp70_previous_chapter_url ); ?>"><?php esc_html_e( '← Previous chapter', 'estudobiblico-biblia-digital' ); ?></a><?php endif; ?>
						<a href="<?php echo esc_url( $this->maybe_add_bible_version_arg_to_url( home_url( user_trailingslashit( $this->seo_base() . '/' . $this->book_slug_from_seq( $bdwp70_selected_book, $bdwp70_books ) ) ), (int) $state['bible_id'] ) ); ?>"><?php /* translators: %s: Bible book name. */ echo esc_html( sprintf( __( 'Chapters of %s', 'estudobiblico-biblia-digital' ), $bdwp70_selected_book_name ) ); ?></a>
						<?php
						if ( $bdwp70_next_chapter_url ) :
							?>
							<a href="<?php echo esc_url( $bdwp70_next_chapter_url ); ?>"><?php esc_html_e( 'Next chapter →', 'estudobiblico-biblia-digital' ); ?></a><?php endif; ?>
					</nav>
				</article>
			</div>
		<?php else : ?>
			<?php echo $this->render_search_results_list( $results, $state, $bdwp70_books ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endif; ?>
		<?php /* 1.1.61 no-results fallback for primary shortcode search mode. */ ?>
	<?php elseif ( 'search' === (string) $state['mode'] && '' !== trim( (string) $state['search'] ) ) : ?>
		<?php echo $this->render_search_results_list( $results, $state, $bdwp70_books ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php endif; ?>

	<?php if ( (int) $results['total_pages'] > 1 ) : ?>
		<nav class="bdwp70__pagination" aria-label="<?php esc_attr_e( 'Bíblia Digital pagination', 'estudobiblico-biblia-digital' ); ?>">
			<?php
			$bdwp70_base_args    = array(
				'bdwp_bible_id'      => $state['bible_id'],
				'bdwp_livro'         => $state['book'],
				'bdwp_pesquisa'      => $state['search'],
				'bdwp_exata'         => $state['exact'],
				'bdwp_match'         => $state['match'],
				'bdwp_search_submit' => 1,
			);
			$bdwp70_current_page = (int) $state['paged'];
			for ( $bdwp70_i = 1; $bdwp70_i <= (int) $results['total_pages']; $bdwp70_i++ ) :
				if ( $bdwp70_i > 2 && $bdwp70_i < $bdwp70_current_page - 2 ) {
					if ( 3 === $bdwp70_i ) {
						echo '<span class="bdwp70__dots">…</span>';
					}
					continue;
				}
				if ( $bdwp70_i < (int) $results['total_pages'] - 1 && $bdwp70_i > $bdwp70_current_page + 2 ) {
					if ( $bdwp70_i === $bdwp70_current_page + 3 ) {
						echo '<span class="bdwp70__dots">…</span>';
					}
					continue;
				}
				$bdwp70_url = add_query_arg( array_merge( $bdwp70_base_args, array( 'bdwp_paged' => $bdwp70_i ) ), $bdwp70_current_url );
				?>
				<a class="bdwp70__page <?php echo $bdwp70_i === $bdwp70_current_page ? 'is-current' : ''; ?>" href="<?php echo esc_url( $bdwp70_url ); ?>">
					<?php echo esc_html( (string) $bdwp70_i ); ?>
				</a>
			<?php endfor; ?>
		</nav>
	<?php endif; ?>

	<?php if ( class_exists( 'BDWP70_Plugin' ) && (int) get_option( BDWP70_Plugin::OPTION_CREDIT, 0 ) ) : ?>
		<div class="bdwp70__footer">
			<?php echo wp_kses_post( __( 'Bíblia Digital Online by <a href="https://estudobiblico.org/" target="_blank" rel="noopener noreferrer">Estudo Bíblico</a>.', 'estudobiblico-biblia-digital' ) ); ?>
		</div>
	<?php endif; ?>
</div>

