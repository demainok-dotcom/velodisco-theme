<?php
/**
 * VeloDisco — bloc dynamique « velodisco/contact ».
 *
 * Formulaire de contact (Nom, Email, Sujet, Message + Envoyer) fidèle au Figma
 * « Page Contact - Ordi » : labels colorés par catégorie, champs 459px centrés.
 * Traitement côté serveur via wp_mail() vers l'e-mail admin du site, avec
 * protection anti-spam (nonce WordPress + champ honeypot invisible).
 *
 * @package VeloDisco
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Traite la soumission du formulaire de contact.
 *
 * @return array{sent:bool, errors:array, values:array} État après traitement.
 */
function velodisco_handle_contact_submission() {
	$state = array(
		'sent'   => false,
		'errors' => array(),
		'values' => array( 'nom' => '', 'email' => '', 'sujet' => '', 'message' => '' ),
	);

	// Pas une soumission de ce formulaire → on ne fait rien.
	if ( empty( $_POST['vd_contact_submit'] ) ) {
		return $state;
	}

	// 1) Nonce (anti-CSRF + filtre les bots basiques).
	if ( empty( $_POST['vd_contact_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vd_contact_nonce'] ) ), 'vd_contact' ) ) {
		$state['errors'][] = 'Session expirée, merci de renvoyer le formulaire.';
		return $state;
	}

	// 2) Honeypot : champ caché « vd_website » qui doit rester vide (les bots le remplissent).
	if ( ! empty( $_POST['vd_website'] ) ) {
		// On fait comme si c'était envoyé, sans rien faire (on ne donne pas d'indice au bot).
		$state['sent'] = true;
		return $state;
	}

	// 3) Récupération + nettoyage.
	$nom     = isset( $_POST['vd_nom'] ) ? sanitize_text_field( wp_unslash( $_POST['vd_nom'] ) ) : '';
	$email   = isset( $_POST['vd_email'] ) ? sanitize_email( wp_unslash( $_POST['vd_email'] ) ) : '';
	$sujet   = isset( $_POST['vd_sujet'] ) ? sanitize_text_field( wp_unslash( $_POST['vd_sujet'] ) ) : '';
	$message = isset( $_POST['vd_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['vd_message'] ) ) : '';

	$state['values'] = compact( 'nom', 'email', 'sujet', 'message' );

	// 4) Validation.
	if ( '' === $nom ) {
		$state['errors'][] = 'Merci d\'indiquer votre nom.';
	}
	if ( '' === $email || ! is_email( $email ) ) {
		$state['errors'][] = 'Merci d\'indiquer une adresse e-mail valide.';
	}
	if ( '' === $message ) {
		$state['errors'][] = 'Merci d\'écrire votre message.';
	}
	if ( $state['errors'] ) {
		return $state;
	}

	// 5) Envoi via wp_mail vers l'e-mail admin du site.
	$to       = get_option( 'admin_email' );
	$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
	$subject  = sprintf( '[%s] Contact : %s', $blogname, ( '' !== $sujet ? $sujet : 'sans sujet' ) );

	$body  = "Nouveau message depuis le formulaire de contact de {$blogname}.\n\n";
	$body .= "Nom : {$nom}\n";
	$body .= "E-mail : {$email}\n";
	$body .= "Sujet : " . ( '' !== $sujet ? $sujet : '(non précisé)' ) . "\n\n";
	$body .= "Message :\n{$message}\n";

	// On répond à l'expéditeur (Reply-To) sans usurper le From (préserve SPF/DKIM du domaine).
	$headers = array(
		'Reply-To: ' . $nom . ' <' . $email . '>',
		'Content-Type: text/plain; charset=UTF-8',
	);

	$ok = wp_mail( $to, $subject, $body, $headers );

	if ( $ok ) {
		$state['sent']   = true;
		$state['values'] = array( 'nom' => '', 'email' => '', 'sujet' => '', 'message' => '' );
	} else {
		$state['errors'][] = 'L\'envoi a échoué. Réessayez plus tard ou écrivez-nous directement.';
	}

	return $state;
}

/**
 * Rend le bloc velodisco/contact (titre géré par le gabarit, ce bloc = formulaire).
 */
function velodisco_render_contact( $attrs = array(), $content = '' ) {
	$state  = velodisco_handle_contact_submission();
	$v      = $state['values'];
	$nonce  = wp_create_nonce( 'vd_contact' );
	$action = esc_url( get_permalink() ? get_permalink() : home_url( add_query_arg( null, null ) ) );

	ob_start();
	?>
	<section class="vd-contact__wrap">

		<?php if ( $state['sent'] ) : ?>

			<div class="vd-contact__confirm" role="status">
				<svg class="vd-check" viewBox="0 0 120 120" fill="none" aria-hidden="true">
					<defs>
						<linearGradient id="vd-check-grad" x1="0" y1="0" x2="1" y2="0">
							<stop offset="0" stop-color="#F24E1E"/>
							<stop offset="0.34" stop-color="#12A66D"/>
							<stop offset="0.68" stop-color="#11A6E2"/>
							<stop offset="1" stop-color="#DD9D06"/>
						</linearGradient>
					</defs>
					<circle class="vd-check__circle" cx="60" cy="60" r="54" pathLength="1" stroke="currentColor" stroke-width="3"/>
					<path class="vd-check__tick" d="M38 61 L53 75 L84 44" pathLength="1" stroke="url(#vd-check-grad)" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
				<p class="vd-search__empty-sub">Votre message a bien été envoyé</p>
				<a class="vd-search__empty-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">Retour à l'accueil</a>
			</div>

		<?php else : ?>

			<?php if ( ! empty( $state['errors'] ) ) : ?>
				<div class="vd-contact__notice vd-contact__notice--err" role="alert">
					<?php foreach ( $state['errors'] as $err ) : ?>
						<p><?php echo esc_html( $err ); ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<form class="vd-contact__form" method="post" action="<?php echo $action; // phpcs:ignore WordPress.Security.EscapeOutput ?>" novalidate>

			<p class="vd-field">
				<label class="vd-field__label vd-c-velos" for="vd-nom">Nom</label>
				<input class="vd-field__input" type="text" id="vd-nom" name="vd_nom" value="<?php echo esc_attr( $v['nom'] ); ?>" required>
			</p>

			<p class="vd-field">
				<label class="vd-field__label vd-c-accessoires" for="vd-email">Email</label>
				<input class="vd-field__input" type="email" id="vd-email" name="vd_email" value="<?php echo esc_attr( $v['email'] ); ?>" required>
			</p>

			<p class="vd-field">
				<label class="vd-field__label vd-c-societe" for="vd-sujet">Sujet</label>
				<input class="vd-field__input" type="text" id="vd-sujet" name="vd_sujet" value="<?php echo esc_attr( $v['sujet'] ); ?>">
			</p>

			<p class="vd-field">
				<label class="vd-field__label vd-c-composants" for="vd-message">Message</label>
				<textarea class="vd-field__input vd-field__textarea" id="vd-message" name="vd_message" rows="5" required><?php echo esc_textarea( $v['message'] ); ?></textarea>
			</p>

			<?php // Honeypot anti-spam : caché aux humains, rempli par les bots. ?>
			<div class="vd-hp" aria-hidden="true">
				<label for="vd-website">Ne pas remplir</label>
				<input type="text" id="vd-website" name="vd_website" tabindex="-1" autocomplete="off">
			</div>

			<input type="hidden" name="vd_contact_nonce" value="<?php echo esc_attr( $nonce ); ?>">
			<button class="vd-contact__btn" type="submit" name="vd_contact_submit" value="1">Envoyer</button>
		</form>

		<?php endif; ?>

	</section>
	<?php
	return ob_get_clean();
}

/** Enregistre le bloc dynamique velodisco/contact. */
function velodisco_register_contact_block() {
	register_block_type( 'velodisco/contact', array(
		'api_version'     => 3,
		'render_callback' => 'velodisco_render_contact',
	) );
}
add_action( 'init', 'velodisco_register_contact_block' );
