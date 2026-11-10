<?php
/**
 * Template Name: Style guide
 *
 * Scout theme kit: the /style-guide/ page every Scout theme ships (Client
 * Design Playbook deliverable 7). It draws itself from the theme's DESIGN.md
 * through Scout Core's scout_design(), so it can never drift from the tokens:
 * change DESIGN.md, rebuild, and this page changes with it.
 *
 * Copy to the theme root as page-style-guide.php. WordPress uses it for the
 * page with the slug style-guide; the Template Name lets another page pick it.
 * Keep the page out of search with Site Sync ("noindex": true on its page
 * entry, Scout Core 1.11.0+) or the Hide from search box in its Scout SEO box.
 * Scout Core owns the robots tag (Site Standard rule 7), so the template sets none.
 *
 * Section order follows the playbook: tokens, feel, color, type, space and
 * motion, components, Scout features, voice and logo, accessibility. The look
 * is the site's own: everything here uses the --scout-* variables. A theme adds
 * its own samples to any section with the scout_style_guide_section action:
 *
 *   add_action( 'scout_style_guide_section', function ( $id ) {
 *       if ( 'components' === $id ) { get_template_part( 'parts/style-guide-photos' ); }
 *   } );
 *
 * Section ids: tokens, feel, color, type, space, components, features, voice, access.
 *
 * @package Scout_Theme_Kit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$scout_sg   = function_exists( 'scout_design' ) ? scout_design() : array();
$scout_all  = class_exists( 'Scout_Core_Design' ) ? Scout_Core_Design::all() : array( 'features' => array() );
$scout_cols = (array) ( $scout_sg['colors'] ?? array() );
$scout_bg   = (string) ( $scout_cols['bg'] ?? '#FFFFFF' );

/** Print a section's opening: number, label and heading. */
$scout_sg_head = static function ( string $num, string $label, string $title ): void {
	echo '<div class="scout-sg__head"><p class="scout-sg__label"><b>' . esc_html( $num ) . '</b> ' . esc_html( $label ) . '</p><h2>' . esc_html( $title ) . '</h2></div>';
};
$scout_sg_hook = static function ( string $id ): void {
	/**
	 * Add the theme's own samples to a style guide section.
	 *
	 * @param string $id tokens, feel, color, type, space, components, features, voice or access.
	 */
	do_action( 'scout_style_guide_section', $id );
};
$scout_ratio = static function ( string $a, string $b ): float {
	return function_exists( 'scout_design_contrast' ) ? scout_design_contrast( $a, $b ) : 0.0;
};

get_header();
?>
<main id="main" class="scout-sg">
<style>
.scout-sg{--sg-gap:var(--scout-space-stack,16px);background:var(--scout-bg);color:var(--scout-text);font-family:var(--scout-font-body,sans-serif)}
.scout-sg section{padding:var(--scout-space-section,72px) var(--scout-space-gutter,16px);border-top:1px solid var(--scout-line)}
.scout-sg section:nth-of-type(even){background:var(--scout-surface-2)}
.scout-sg .scout-sg__in{max-width:var(--scout-space-wrap,1200px);margin:0 auto;display:grid;gap:calc(var(--sg-gap) * 1.5)}
.scout-sg h1,.scout-sg h2,.scout-sg h3{font-family:var(--scout-font-heading,inherit);font-weight:var(--scout-weight-heading,600);margin:0}
.scout-sg h1{font-size:var(--scout-type-hero,clamp(38px,5.6vw,72px));line-height:1.05}
.scout-sg h2{font-size:var(--scout-type-h2,clamp(28px,3.4vw,44px));line-height:1.1}
.scout-sg h3{font-size:var(--scout-type-h3,20px);line-height:1.25}
.scout-sg p{margin:0;line-height:1.6}
.scout-sg__label{font-family:var(--scout-font-label,monospace);font-weight:var(--scout-weight-label,500);font-size:var(--scout-type-label,12px);text-transform:uppercase;letter-spacing:.12em;color:var(--scout-accent-text,var(--scout-text-2))}
.scout-sg__label b{color:var(--scout-text)}
.scout-sg__grid{display:grid;gap:var(--sg-gap);grid-template-columns:repeat(auto-fill,minmax(220px,1fr))}
.scout-sg__card{background:var(--scout-surface);border:1px solid var(--scout-line);border-radius:var(--scout-radius-card,12px);padding:var(--sg-gap);display:grid;gap:8px;align-content:start}
.scout-sg__swatch{height:88px;border-radius:var(--scout-radius-control,8px);border:1px solid var(--scout-line)}
.scout-sg__small{font-size:var(--scout-type-small,14px);color:var(--scout-text-2)}
.scout-sg__ok{color:var(--scout-good,var(--scout-text))}
.scout-sg__no{color:var(--scout-bad,var(--scout-text));font-weight:600}
.scout-sg table{width:100%;border-collapse:collapse;font-size:var(--scout-type-small,14px)}
.scout-sg th,.scout-sg td{text-align:left;padding:8px 10px;border-bottom:1px solid var(--scout-line);vertical-align:top}
.scout-sg code{font-family:var(--scout-font-label,monospace);font-size:.9em;overflow-wrap:anywhere}
.scout-sg__btn{display:inline-flex;align-items:center;min-height:48px;padding:0 24px;border-radius:var(--scout-radius-pill,980px);font:inherit;font-weight:500;text-decoration:none;cursor:pointer;border:1px solid transparent;transition:transform var(--scout-motion-base,.3s) var(--scout-motion-ease,ease),opacity var(--scout-motion-base,.3s) var(--scout-motion-ease,ease)}
.scout-sg__btn--fill{background:var(--scout-accent-fill);color:var(--scout-on-accent)}
.scout-sg__btn--line{border-color:var(--scout-accent);color:var(--scout-text);background:transparent}
.scout-sg__btn:hover{transform:translateY(-2px)}
.scout-sg a:focus-visible,.scout-sg button:focus-visible,.scout-sg input:focus-visible{outline:2px solid var(--scout-accent);outline-offset:2px}
.scout-sg__field{display:grid;gap:6px;max-width:420px}
.scout-sg__field input{min-height:48px;padding:0 14px;border:1px solid var(--scout-line);border-radius:var(--scout-radius-control,8px);background:var(--scout-surface);color:var(--scout-text);font:inherit}
.scout-sg__bar{height:12px;background:var(--scout-accent);border-radius:2px}
.scout-sg__night{background:var(--scout-night,#111);color:var(--scout-on-night,#fff);padding:var(--sg-gap);border-radius:var(--scout-radius-card,12px)}
.scout-sg__logo{max-height:72px;width:auto}
@media (prefers-reduced-motion:reduce){.scout-sg__btn{transition:none}.scout-sg__btn:hover{transform:none}}
</style>

<section aria-labelledby="scout-sg-title">
	<div class="scout-sg__in">
		<p class="scout-sg__label"><?php echo esc_html( sprintf( 'Style guide · design %s · not for search', (string) ( $scout_sg['design'] ?? '1' ) ) ); ?></p>
		<h1 id="scout-sg-title"><?php echo esc_html( '' !== (string) ( $scout_sg['name'] ?? '' ) ? (string) $scout_sg['name'] : get_bloginfo( 'name' ) ); ?></h1>
		<?php if ( ! $scout_sg ) : ?>
			<p><?php esc_html_e( 'Scout Core is not active, so there are no design tokens to show.', 'scout' ); ?></p>
		<?php endif; ?>
	</div>
</section>

<section id="tokens">
	<div class="scout-sg__in">
		<?php $scout_sg_head( '01', 'One source for tokens', 'Every color, font and corner on this page comes from the theme\'s DESIGN.md.' ); ?>
		<div class="scout-sg__grid">
			<div class="scout-sg__card"><p class="scout-sg__label">The source</p><b>DESIGN.md</b>
				<?php foreach ( (array) ( $scout_sg['sources'] ?? array() ) as $scout_src ) : ?>
					<code class="scout-sg__small"><?php echo esc_html( str_replace( ABSPATH, '', (string) $scout_src ) ); ?></code>
				<?php endforeach; ?>
			</div>
			<div class="scout-sg__card"><p class="scout-sg__label">The builder</p><b>design-build.php</b><span class="scout-sg__small">Checks contrast and writes theme.json and design-tokens.css.</span></div>
			<div class="scout-sg__card"><p class="scout-sg__label">The variables</p><b>--scout-*</b><span class="scout-sg__small">What the theme and every Scout plugin read.</span></div>
		</div>
		<?php $scout_sg_hook( 'tokens' ); ?>
	</div>
</section>

<section id="feel">
	<div class="scout-sg__in">
		<?php $scout_sg_head( '02', 'How the site should feel', 'Mode: ' . (string) ( $scout_sg['mode'] ?? 'light' ) . '.' ); ?>
		<?php $scout_sg_hook( 'feel' ); ?>
	</div>
</section>

<section id="color">
	<div class="scout-sg__in">
		<?php $scout_sg_head( '03', 'Color', 'Each color with its job and its contrast on the page.' ); ?>
		<div class="scout-sg__grid">
			<?php foreach ( $scout_cols as $scout_key => $scout_val ) : ?>
				<?php
				$scout_val   = (string) $scout_val;
				$scout_on    = in_array( $scout_key, array( 'on_night', 'on_night_2' ), true ) && ! empty( $scout_cols['night'] ) ? (string) $scout_cols['night'] : $scout_bg;
				$scout_r     = $scout_ratio( $scout_val, $scout_on );
				$scout_css_v = '--scout-' . str_replace( '_', '-', (string) $scout_key );
				?>
				<div class="scout-sg__card">
					<div class="scout-sg__swatch" style="background:var(<?php echo esc_attr( $scout_css_v ); ?>)"></div>
					<b><?php echo esc_html( (string) $scout_key ); ?></b>
					<code><?php echo esc_html( $scout_val ); ?> · <?php echo esc_html( $scout_css_v ); ?></code>
					<?php if ( $scout_r > 0 ) : ?>
						<span class="scout-sg__small"><?php echo esc_html( sprintf( '%s:1 on %s', number_format( $scout_r, 2 ), $scout_on === $scout_bg ? 'the page' : 'night' ) ); ?></span>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php $scout_sg_hook( 'color' ); ?>
	</div>
</section>

<section id="type">
	<div class="scout-sg__in">
		<?php $scout_sg_head( '04', 'Type', 'The three font roles and the type scale.' ); ?>
		<div class="scout-sg__grid">
			<?php foreach ( (array) ( $scout_sg['fonts'] ?? array() ) as $scout_role => $scout_font ) : ?>
				<div class="scout-sg__card">
					<p class="scout-sg__label"><?php echo esc_html( (string) $scout_role ); ?></p>
					<p style="font-family:var(--scout-font-<?php echo esc_attr( sanitize_key( (string) $scout_role ) ); ?>);font-weight:var(--scout-weight-<?php echo esc_attr( sanitize_key( (string) $scout_role ) ); ?>);font-size:24px">Aa Bb Cc 0123</p>
					<code><?php echo esc_html( trim( (string) ( $scout_font['family'] ?? '' ) . ' ' . (string) ( $scout_font['weight'] ?? '' ) . ' ' . (string) ( $scout_font['generic'] ?? '' ) ) ); ?></code>
				</div>
			<?php endforeach; ?>
		</div>
		<?php if ( ! empty( $scout_sg['type_scale'] ) ) : ?>
			<table>
				<thead><tr><th scope="col">Step</th><th scope="col">Size</th><th scope="col">Sample</th></tr></thead>
				<tbody>
				<?php foreach ( (array) $scout_sg['type_scale'] as $scout_step => $scout_size ) : ?>
					<tr>
						<td><code>--scout-type-<?php echo esc_html( str_replace( '_', '-', (string) $scout_step ) ); ?></code></td>
						<td><code><?php echo esc_html( (string) $scout_size ); ?></code></td>
						<td style="font-size:var(--scout-type-<?php echo esc_attr( str_replace( '_', '-', sanitize_key( (string) $scout_step ) ) ); ?>);line-height:1.15">The quick brown fox</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php $scout_sg_hook( 'type' ); ?>
	</div>
</section>

<section id="space">
	<div class="scout-sg__in">
		<?php $scout_sg_head( '05', 'Space, shape, depth, motion', 'The spacing scale, the corners and the timing.' ); ?>
		<table>
			<thead><tr><th scope="col">Token</th><th scope="col">Value</th><th scope="col">Sample</th></tr></thead>
			<tbody>
			<?php foreach ( (array) ( $scout_sg['space'] ?? array() ) as $scout_k => $scout_v ) : ?>
				<tr><td><code>--scout-space-<?php echo esc_html( str_replace( '_', '-', (string) $scout_k ) ); ?></code></td><td><code><?php echo esc_html( (string) $scout_v ); ?></code></td><td><div class="scout-sg__bar" style="width:min(100%, var(--scout-space-<?php echo esc_attr( str_replace( '_', '-', sanitize_key( (string) $scout_k ) ) ); ?>))"></div></td></tr>
			<?php endforeach; ?>
			<?php foreach ( (array) ( $scout_sg['radius'] ?? array() ) as $scout_k => $scout_v ) : ?>
				<tr><td><code>--scout-radius-<?php echo esc_html( (string) $scout_k ); ?></code></td><td><code><?php echo esc_html( (string) $scout_v ); ?></code></td><td><div style="width:72px;height:40px;background:var(--scout-surface);border:1px solid var(--scout-line);border-radius:var(--scout-radius-<?php echo esc_attr( sanitize_key( (string) $scout_k ) ); ?>)"></div></td></tr>
			<?php endforeach; ?>
			<?php foreach ( (array) ( $scout_sg['motion'] ?? array() ) as $scout_k => $scout_v ) : ?>
				<tr><td><code>--scout-motion-<?php echo esc_html( str_replace( '_', '-', (string) $scout_k ) ); ?></code></td><td><code><?php echo esc_html( (string) $scout_v ); ?></code></td><td class="scout-sg__small">Only transform and opacity animate.</td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php $scout_sg_hook( 'space' ); ?>
	</div>
</section>

<section id="components">
	<div class="scout-sg__in">
		<?php $scout_sg_head( '06', 'Components', 'Buttons, links and a form field, in the site\'s tokens.' ); ?>
		<div style="display:flex;flex-wrap:wrap;gap:var(--sg-gap);align-items:center">
			<a class="scout-sg__btn scout-sg__btn--fill" href="#components">Primary action</a>
			<a class="scout-sg__btn scout-sg__btn--line" href="#components">Second action</a>
			<a href="#components" style="color:var(--scout-accent-text,var(--scout-accent))">A text link</a>
		</div>
		<label class="scout-sg__field"><span class="scout-sg__label">A form field</span><input type="text" placeholder="Your name"></label>
		<?php $scout_sg_hook( 'components' ); ?>
	</div>
</section>

<section id="features">
	<div class="scout-sg__in">
		<?php $scout_sg_head( '07', 'Scout features', 'What each Scout plugin takes from DESIGN.md.' ); ?>
		<?php if ( empty( $scout_all['features'] ) ) : ?>
			<p class="scout-sg__small">DESIGN.md has no Feature sections, so every plugin uses the tokens above.</p>
		<?php endif; ?>
		<?php foreach ( (array) $scout_all['features'] as $scout_name => $scout_rows ) : ?>
			<?php $scout_rows = function_exists( 'scout_design_feature' ) ? scout_design_feature( (string) $scout_name ) : (array) $scout_rows; ?>
			<div class="scout-sg__card">
				<h3><?php echo esc_html( ucfirst( str_replace( '_', ' ', (string) $scout_name ) ) ); ?></h3>
				<table>
					<tbody>
					<?php foreach ( $scout_rows as $scout_k => $scout_v ) : ?>
						<tr><th scope="row"><code><?php echo esc_html( (string) $scout_k ); ?></code></th><td><?php echo esc_html( is_bool( $scout_v ) ? ( $scout_v ? 'yes' : 'no' ) : (string) $scout_v ); ?></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endforeach; ?>
		<?php $scout_sg_hook( 'features' ); ?>
	</div>
</section>

<section id="voice">
	<div class="scout-sg__in">
		<?php $scout_sg_head( '08 · 09', 'Voice, logo and the family', 'The logo and the mark.' ); ?>
		<div class="scout-sg__grid">
			<?php if ( ! empty( $scout_sg['logo_url'] ) ) : ?>
				<div class="scout-sg__card"><p class="scout-sg__label">Logo</p><img class="scout-sg__logo" src="<?php echo esc_url( (string) $scout_sg['logo_url'] ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"></div>
			<?php endif; ?>
			<?php if ( ! empty( $scout_sg['mark_url'] ) ) : ?>
				<div class="scout-sg__card"><p class="scout-sg__label">Mark</p><img class="scout-sg__logo" src="<?php echo esc_url( (string) $scout_sg['mark_url'] ); ?>" alt=""></div>
			<?php endif; ?>
			<?php if ( ! empty( $scout_cols['night'] ) ) : ?>
				<div class="scout-sg__night"><p>Text on night</p><p style="color:var(--scout-on-night-2,inherit)">Muted text on night</p></div>
			<?php endif; ?>
		</div>
		<?php $scout_sg_hook( 'voice' ); ?>
	</div>
</section>

<section id="access">
	<div class="scout-sg__in">
		<?php $scout_sg_head( '10 · 11', 'Accessibility and don\'t', 'The contrast pairs Scout checks on every build.' ); ?>
		<table>
			<thead><tr><th scope="col">Pair</th><th scope="col">Ratio</th><th scope="col">Needs</th><th scope="col">Result</th></tr></thead>
			<tbody>
			<?php foreach ( function_exists( 'scout_design_issues' ) ? scout_design_issues() : array() as $scout_i ) : ?>
				<tr>
					<td><?php echo esc_html( (string) $scout_i['pair'] ); ?></td>
					<td><?php echo esc_html( number_format( (float) $scout_i['ratio'], 2 ) ); ?>:1</td>
					<td><?php echo esc_html( (string) $scout_i['need'] ); ?>:1</td>
					<td class="<?php echo $scout_i['ok'] ? 'scout-sg__ok' : 'scout-sg__no'; ?>"><?php echo esc_html( $scout_i['ok'] ? 'Passes' : ( 'fail' === $scout_i['level'] ? 'Fails' : 'Below target' ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php $scout_sg_hook( 'access' ); ?>
	</div>
</section>
</main>
<?php
get_footer();
