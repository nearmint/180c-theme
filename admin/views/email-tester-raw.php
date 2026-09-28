<?php
/**
 * Vue autonome — Aperçu d'un e-mail (nouvel onglet).
 *
 * Document HTML complet rendu par _180c_email_tester_maybe_render_raw() avant
 * tout chrome admin : barre d'info fine en haut + e-mail isolé dans une iframe.
 *
 * Variables fournies :
 *
 * @var string $email_id        ID de l'e-mail.
 * @var int    $tested_user     ID utilisateur testé.
 * @var array  $preview         Résultat de generate_email_preview().
 * @var array  $preview_objects {kind, items[]} pour le sélecteur d'objet.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$current_oid = isset( $_GET['oid'] ) ? absint( $_GET['oid'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$list_url    = add_query_arg(
	array(
		'page' => _180C_EMAIL_TESTER_SLUG,
		'uid'  => $tested_user,
	),
	admin_url( 'tools.php' )
);
$page_title = $preview['subject'] ? $preview['subject'] : $email_id;

nocache_headers();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex,nofollow" />
	<title><?php echo esc_html( $page_title ); ?> — <?php esc_html_e( 'Aperçu e-mail 180°C', '180c' ); ?></title>
	<style>
		html, body { height: 100%; margin: 0; }
		body { display: flex; flex-direction: column; background: #f0f0f1; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
		.etr-bar { flex: 0 0 auto; display: flex; flex-wrap: wrap; align-items: center; gap: 6px 18px; padding: 10px 18px; background: #1d2327; color: #f0f0f1; font-size: 13px; }
		.etr-bar strong { color: #fff; }
		.etr-bar code { background: rgba(255,255,255,.12); padding: 1px 6px; border-radius: 3px; font-size: 12px; }
		.etr-bar a { color: #FFAE3A; text-decoration: none; }
		.etr-bar a:hover { text-decoration: underline; }
		.etr-meta { color: #c3c4c7; }
		.etr-switch { display: inline-flex; align-items: center; gap: 6px; margin-left: auto; }
		.etr-switch select { max-width: 320px; }
		.etr-frame { flex: 1 1 auto; width: 100%; border: 0; background: #fff; }
		.etr-msg { flex: 1 1 auto; display: flex; align-items: center; justify-content: center; padding: 40px; text-align: center; color: #50575e; }
		.etr-msg p { max-width: 60ch; font-size: 15px; line-height: 1.5; }
	</style>
</head>
<body>
	<div class="etr-bar">
		<a href="<?php echo esc_url( $list_url ); ?>">&larr; <?php esc_html_e( 'Liste', '180c' ); ?></a>
		<span><strong><?php echo esc_html( $email_id ); ?></strong></span>
		<?php if ( $preview['ok'] ) : ?>
			<span class="etr-meta"><?php esc_html_e( 'Sujet :', '180c' ); ?> <?php echo esc_html( $preview['subject'] ); ?></span>
			<span class="etr-meta">
				<?php esc_html_e( 'Objet :', '180c' ); ?>
				<?php echo $preview['object_label'] ? esc_html( $preview['object_label'] ) : '—'; ?>
			</span>

			<?php if ( count( $preview_objects['items'] ) > 1 ) : ?>
				<form method="get" class="etr-switch">
					<input type="hidden" name="page" value="<?php echo esc_attr( _180C_EMAIL_TESTER_SLUG ); ?>" />
					<input type="hidden" name="uid" value="<?php echo esc_attr( $tested_user ); ?>" />
					<input type="hidden" name="preview" value="<?php echo esc_attr( $email_id ); ?>" />
					<?php wp_nonce_field( 'preview_email', '_180c_nonce' ); ?>
					<label for="etr-oid"><?php esc_html_e( 'Changer d’objet :', '180c' ); ?></label>
					<select name="oid" id="etr-oid" onchange="this.form.submit()">
						<?php foreach ( $preview_objects['items'] as $item ) : ?>
							<option value="<?php echo esc_attr( $item['id'] ); ?>" <?php selected( $item['id'], $current_oid ); ?>>
								<?php echo esc_html( $item['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</form>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<?php if ( $preview['ok'] ) : ?>
		<iframe
			class="etr-frame"
			title="<?php echo esc_attr( $preview['subject'] ); ?>"
			sandbox="allow-same-origin"
			srcdoc="<?php echo esc_attr( $preview['html'] ); ?>"></iframe>
	<?php else : ?>
		<div class="etr-msg">
			<p><?php echo esc_html( $preview['message'] ); ?></p>
		</div>
	<?php endif; ?>
</body>
</html>
