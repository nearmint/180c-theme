<?php
/**
 * Admin — Moteur de prévisualisation des e-mails WooCommerce.
 *
 * Rend le HTML de n'importe quel e-mail WC (core, Subscriptions, Memberships,
 * Gifting, Payments, Stripe, et les e-mails custom 180c) avec les données réelles
 * de l'utilisateur connecté (sa commande / abonnement / adhésion la plus récente).
 *
 * Méthode de rendu : on assigne l'objet métier à l'instance d'e-mail puis on appelle
 * `get_content_html()` + `style_inline()`. On NE passe PAS par `WC_Email::trigger()`
 * (qui enverrait réellement et *bail* sur les gates `is_enabled()` / statut de commande).
 * Aucun e-mail n'est envoyé : `pre_wp_mail` est court-circuité en ceinture-bretelles
 * pendant toute la génération (utile pour les e-mails cadeau qui exigent un état
 * interne protégé et passent donc par `trigger()`).
 *
 * Lecture seule. Réservé aux `manage_options`. Chargé uniquement en admin.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prévisualiseur d'e-mails WooCommerce.
 */
class _180C_Email_Tester {

	/**
	 * Liste les e-mails WC ACTIVÉS uniquement (les désactivés sont masqués de l'outil).
	 *
	 * @return array<string,array{title:string,description:string,class:string,customer:bool,enabled:bool,source:string}>
	 */
	public static function get_all_wc_emails(): array {
		$emails = array();

		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return $emails;
		}

		$descriptions = self::get_fr_descriptions();

		foreach ( WC()->mailer()->get_emails() as $email ) {
			$enabled = method_exists( $email, 'is_enabled' ) ? (bool) $email->is_enabled() : (bool) $email->enabled;

			// Filtre : on n'expose que les e-mails activés dans WooCommerce.
			if ( ! $enabled ) {
				continue;
			}

			$emails[ $email->id ] = array(
				'title'       => (string) $email->get_title(),
				'description' => $descriptions[ $email->id ] ?? (string) $email->get_description(),
				'class'       => get_class( $email ),
				'customer'    => method_exists( $email, 'is_customer_email' ) ? (bool) $email->is_customer_email() : false,
				'enabled'     => true,
				'source'      => self::source_label( get_class( $email ) ),
			);
		}

		uasort(
			$emails,
			static function ( $a, $b ) {
				return strcmp( $a['source'] . $a['title'], $b['source'] . $b['title'] );
			}
		);

		return $emails;
	}

	/**
	 * Charge (une seule fois) le mapping des descriptions FR vulgarisées.
	 *
	 * @return array<string,string>
	 */
	private static function get_fr_descriptions(): array {
		static $descriptions = null;
		if ( null === $descriptions ) {
			$file         = __DIR__ . '/email-descriptions-fr.php';
			$descriptions = is_readable( $file ) ? (array) include $file : array();
		}
		return $descriptions;
	}

	/**
	 * Récupère une instance d'e-mail par son `id`.
	 *
	 * `WC()->mailer()->get_emails()` est indexé par nom de classe, pas par `id` :
	 * on parcourt donc la liste et on matche sur `$email->id`.
	 *
	 * @param string $email_id ID de l'e-mail recherché.
	 * @return WC_Email|null
	 */
	private static function get_email_by_id( string $email_id ) {
		foreach ( WC()->mailer()->get_emails() as $email ) {
			if ( $email->id === $email_id ) {
				return $email;
			}
		}
		return null;
	}

	/**
	 * Étiquette d'origine (plugin / thème) déduite du namespace de classe.
	 *
	 * @param string $class Nom de classe complet de l'e-mail.
	 * @return string
	 */
	private static function source_label( string $class ): string {
		if ( str_starts_with( $class, '_180C_' ) ) {
			return '180°C (thème)';
		}
		if ( str_starts_with( $class, 'WCSG_' ) ) {
			return 'Subscriptions Gifting';
		}
		if ( str_starts_with( $class, 'WCS_' ) ) {
			return 'Subscriptions';
		}
		if ( str_starts_with( $class, 'WC_Memberships' ) ) {
			return 'Memberships';
		}
		if ( str_starts_with( $class, 'WC_Payments' ) ) {
			return 'Payments';
		}
		if ( str_starts_with( $class, 'WC_Stripe' ) ) {
			return 'Stripe';
		}
		return 'WooCommerce';
	}

	/**
	 * Rassemble les données de test pour un utilisateur (objets réels les plus récents).
	 *
	 * @param int $user_id ID utilisateur (0 = utilisateur courant).
	 * @return array{
	 *     user_id:int,
	 *     user:?WP_User,
	 *     email:string,
	 *     first_name:string,
	 *     last_name:string,
	 *     orders:array<int,WC_Order>,
	 *     subscriptions:array<int,WC_Subscription>,
	 *     memberships:array<int,object>
	 * }
	 */
	public static function get_test_data_for_user( int $user_id = 0 ): array {
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();
		if ( ! $user_id ) {
			return array();
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return array();
		}

		$orders = function_exists( 'wc_get_orders' )
			? wc_get_orders(
				array(
					'customer' => $user_id,
					'limit'    => 15,
					'orderby'  => 'date',
					'order'    => 'DESC',
					'type'     => 'shop_order',
				)
			)
			: array();

		$subscriptions = function_exists( 'wcs_get_users_subscriptions' )
			? array_values( wcs_get_users_subscriptions( $user_id ) )
			: array();

		$memberships = function_exists( 'wc_memberships_get_user_memberships' )
			? array_values( wc_memberships_get_user_memberships( $user_id ) )
			: array();

		return array(
			'user_id'       => $user_id,
			'user'          => $user,
			'email'         => $user->user_email,
			'first_name'    => $user->first_name ? $user->first_name : $user->user_login,
			'last_name'     => (string) $user->last_name,
			'orders'        => is_array( $orders ) ? $orders : array(),
			'subscriptions' => $subscriptions,
			'memberships'   => $memberships,
		);
	}

	/**
	 * Classe un e-mail dans une famille d'objet métier.
	 *
	 * @param WC_Email $email Instance d'e-mail.
	 * @return string Une de : gift|user|membership|subscription|order.
	 */
	private static function classify( $email ): string {
		$key = strtolower( $email->id . ' ' . get_class( $email ) );

		if ( false !== strpos( $key, 'gift' ) ) {
			return 'gift';
		}
		if ( false !== strpos( $key, 'reset_password' ) || false !== strpos( $key, 'new_account' ) ) {
			return 'user';
		}
		if ( false !== strpos( $key, 'membership' ) ) {
			return 'membership';
		}
		// Abonnement : les e-mails « subscription » ou « notification », sauf ceux
		// rattachés à une commande de renouvellement (qui attendent un WC_Order).
		if ( false !== strpos( $key, 'notification' ) ) {
			return 'subscription';
		}
		if ( false !== strpos( $key, 'subscription' ) && false === strpos( $key, 'renewal' ) ) {
			return 'subscription';
		}
		// Tout le reste (commande, facture, renouvellement, switch, retry…) = commande.
		return 'order';
	}

	/**
	 * Sélectionne l'objet métier à passer au gabarit.
	 *
	 * @param string $kind      Famille (cf. classify()).
	 * @param array  $data      Données de test.
	 * @param int    $object_id ID d'objet forcé (0 = premier dispo).
	 * @return array{object:mixed,label:string,error:string}
	 */
	private static function resolve_object( string $kind, array $data, int $object_id = 0 ): array {
		switch ( $kind ) {
			case 'user':
				return array(
					'object' => $data['user'],
					'label'  => sprintf( 'Compte #%d — %s', $data['user_id'], $data['email'] ),
					'error'  => '',
				);

			case 'membership':
				if ( empty( $data['memberships'] ) ) {
					return array(
						'object' => null,
						'label'  => '',
						'error'  => 'Aucune adhésion (membership) trouvée pour ce compte.',
					);
				}
				$obj = self::pick_by_id( $data['memberships'], $object_id, 'get_id' );
				return array(
					'object' => $obj,
					'label'  => sprintf( 'Adhésion #%d — %s', $obj->get_id(), $obj->get_plan() ? $obj->get_plan()->get_name() : '' ),
					'error'  => '',
				);

			case 'subscription':
				if ( empty( $data['subscriptions'] ) ) {
					return array(
						'object' => null,
						'label'  => '',
						'error'  => 'Aucun abonnement trouvé pour ce compte.',
					);
				}
				$obj = self::pick_by_id( $data['subscriptions'], $object_id, 'get_id' );
				return array(
					'object' => $obj,
					'label'  => sprintf( 'Abonnement #%d — statut %s', $obj->get_id(), $obj->get_status() ),
					'error'  => '',
				);

			case 'order':
				if ( empty( $data['orders'] ) ) {
					return array(
						'object' => null,
						'label'  => '',
						'error'  => 'Aucune commande trouvée pour ce compte.',
					);
				}
				$obj = self::pick_by_id( $data['orders'], $object_id, 'get_id' );
				return array(
					'object' => $obj,
					'label'  => sprintf( 'Commande #%d — statut %s', $obj->get_id(), $obj->get_status() ),
					'error'  => '',
				);

			case 'gift':
			default:
				return array(
					'object' => null,
					'label'  => '',
					'error'  => '',
				);
		}
	}

	/**
	 * Cherche un objet par ID dans une liste, sinon retourne le premier.
	 *
	 * @param array  $list      Liste d'objets WC.
	 * @param int    $id        ID recherché (0 = premier).
	 * @param string $id_method Méthode renvoyant l'ID.
	 * @return mixed
	 */
	private static function pick_by_id( array $list, int $id, string $id_method ) {
		if ( $id > 0 ) {
			foreach ( $list as $item ) {
				if ( (int) $item->{$id_method}() === $id ) {
					return $item;
				}
			}
		}
		return reset( $list );
	}

	/**
	 * Liste les objets disponibles pour la famille d'un e-mail (pour le sélecteur UI).
	 *
	 * @param string $email_id ID de l'e-mail WC.
	 * @param array  $data     Données de test.
	 * @return array{kind:string,items:array<int,array{id:int,label:string}>}
	 */
	public static function get_objects_for_email( string $email_id, array $data ): array {
		$email = self::get_email_by_id( $email_id );
		if ( ! $email ) {
			return array(
				'kind'  => '',
				'items' => array(),
			);
		}
		$kind  = self::classify( $email );
		$items = array();

		if ( 'order' === $kind ) {
			foreach ( $data['orders'] as $o ) {
				$items[] = array(
					'id'    => $o->get_id(),
					'label' => sprintf( '#%d — %s — %s', $o->get_id(), $o->get_status(), $o->get_date_created() ? $o->get_date_created()->date_i18n( 'd/m/Y' ) : '' ),
				);
			}
		} elseif ( 'subscription' === $kind ) {
			foreach ( $data['subscriptions'] as $s ) {
				$items[] = array(
					'id'    => $s->get_id(),
					'label' => sprintf( '#%d — %s', $s->get_id(), $s->get_status() ),
				);
			}
		} elseif ( 'membership' === $kind ) {
			foreach ( $data['memberships'] as $m ) {
				$items[] = array(
					'id'    => $m->get_id(),
					'label' => sprintf( '#%d — %s', $m->get_id(), $m->get_plan() ? $m->get_plan()->get_name() : '' ),
				);
			}
		}

		return array(
			'kind'  => $kind,
			'items' => $items,
		);
	}

	/**
	 * Génère le HTML de prévisualisation d'un e-mail.
	 *
	 * @param string $email_id  ID de l'e-mail WC.
	 * @param int    $user_id   ID utilisateur testé (0 = courant).
	 * @param int    $object_id ID d'objet forcé (0 = premier dispo).
	 * @return array{ok:bool,html:string,subject:string,recipient:string,kind:string,object_label:string,message:string}
	 */
	public static function generate_email_preview( string $email_id, int $user_id = 0, int $object_id = 0 ): array {
		$fail = static function ( string $msg ): array {
			return array(
				'ok'           => false,
				'html'         => '',
				'subject'      => '',
				'recipient'    => '',
				'kind'         => '',
				'object_label' => '',
				'message'      => $msg,
			);
		};

		$data = self::get_test_data_for_user( $user_id );
		if ( empty( $data ) ) {
			return $fail( 'Impossible de récupérer les données de test pour cet utilisateur.' );
		}

		$email = self::get_email_by_id( $email_id );
		if ( ! $email ) {
			return $fail( 'E-mail introuvable : ' . $email_id );
		}

		$kind = self::classify( $email );

		// Ceinture-bretelles : aucun envoi pendant la génération (utile au cas gift).
		add_filter( 'pre_wp_mail', '__return_false', PHP_INT_MAX );
		add_filter( 'woocommerce_email_attachments', '__return_empty_array', PHP_INT_MAX );

		$result = $fail( 'Erreur inconnue.' );

		try {
			if ( 'gift' === $kind ) {
				$result = self::render_gift( $email, $data );
			} else {
				$resolved = self::resolve_object( $kind, $data, $object_id );
				if ( '' !== $resolved['error'] ) {
					$result = $fail( $resolved['error'] );
				} else {
					$result = self::render_standard( $email, $kind, $resolved['object'], $data, $resolved['label'] );
				}
			}
		} catch ( \Throwable $e ) {
			// Certains e-mails (changement d'abonnement, relance de paiement, cadeau
			// destinataire) exigent un objet runtime spécifique absent des données de
			// test génériques. On dégrade proprement avec le détail technique.
			$result = $fail(
				'Cet e-mail ne peut pas être prévisualisé avec les données de test disponibles : '
				. 'il requiert un objet spécifique (changement d’abonnement, relance de paiement, ou contexte cadeau). '
				. 'Détail : ' . $e->getMessage()
			);
		} finally {
			remove_filter( 'pre_wp_mail', '__return_false', PHP_INT_MAX );
			remove_filter( 'woocommerce_email_attachments', '__return_empty_array', PHP_INT_MAX );
		}

		return $result;
	}

	/**
	 * Rendu standard : assigne l'objet et capture le HTML inliné.
	 *
	 * @param WC_Email $email  Instance d'e-mail.
	 * @param string   $kind   Famille.
	 * @param mixed    $obj    Objet métier (WC_Order / WC_Subscription / membership / WP_User).
	 * @param array    $data   Données de test.
	 * @param string   $label  Libellé de l'objet utilisé.
	 * @return array
	 */
	private static function render_standard( $email, string $kind, $obj, array $data, string $label ): array {
		$email->setup_locale();

		if ( 'user' === $kind ) {
			// E-mails de credentials : on stube les propriétés lues par le gabarit.
			$email->object       = $obj;
			$email->user_login   = $obj->user_login;
			$email->user_email   = $obj->user_email;
			$email->user_pass    = '';
			$email->set_password = false;
			$email->reset_key    = 'PREVIEW-RESET-KEY';
			$email->user_id      = $data['user_id'];
		} else {
			$email->object = $obj;

			if ( 'order' === $kind || 'subscription' === $kind ) {
				$date = method_exists( $obj, 'get_date_created' ) ? $obj->get_date_created() : null;
				if ( $date ) {
					$email->placeholders['{order_date}'] = wc_format_datetime( $date );
				}
				if ( method_exists( $obj, 'get_order_number' ) ) {
					$email->placeholders['{order_number}'] = $obj->get_order_number();
				}
			}
			if ( 'subscription' === $kind ) {
				$email->placeholders['{subscription_id}'] = $obj->get_id();
			}
		}

		// Relance de paiement (WCS) : get_content_html() lit $email->retry (un
		// WCS_Retry), normalement posé par trigger() — que le préviewer n'appelle
		// jamais. Sans stub, le gabarit fait get_time() sur null → TypeError.
		if ( 'customer_payment_retry' === $email->id || 'payment_retry' === $email->id ) {
			if ( empty( $email->retry ) ) {
				$email->retry = new class() {
					/**
					 * Timestamp de relance simulé (renouvellement dans 7 jours).
					 *
					 * @return int
					 */
					public function get_time(): int {
						return time() + ( 7 * DAY_IN_SECONDS );
					}
				};
			}
		}

		$html = $email->get_content_html();
		$html = $email->style_inline( $html );

		$subject   = self::safe_call( $email, 'get_subject' );
		$recipient = self::safe_call( $email, 'get_recipient' );

		$email->restore_locale();

		if ( ! $html || strlen( trim( wp_strip_all_tags( $html ) ) ) < 5 ) {
			return array(
				'ok'           => false,
				'html'         => '',
				'subject'      => $subject,
				'recipient'    => $recipient,
				'kind'         => $kind,
				'object_label' => $label,
				'message'      => 'Le gabarit a rendu un contenu vide.',
			);
		}

		return array(
			'ok'           => true,
			'html'         => $html,
			'subject'      => $subject,
			'recipient'    => $recipient,
			'kind'         => $kind,
			'object_label' => $label,
			'message'      => '',
		);
	}

	/**
	 * Rendu des e-mails cadeau 180c : ils exigent un état interne protégé,
	 * alimenté par leur propre `trigger()`. L'envoi est neutralisé par le killswitch.
	 * On rejoue ensuite `get_content_html()` une fois l'état posé.
	 *
	 * @param WC_Email $email Instance d'e-mail cadeau.
	 * @param array    $data  Données de test.
	 * @return array
	 */
	private static function render_gift( $email, array $data ): array {
		if ( ! function_exists( '_180c_gift_table' ) || ! function_exists( '_180c_gift_get_row' ) ) {
			return array(
				'ok'           => false,
				'html'         => '',
				'subject'      => '',
				'recipient'    => '',
				'kind'         => 'gift',
				'object_label' => '',
				'message'      => 'Module cadeau indisponible.',
			);
		}

		// Cherche une ligne cadeau réelle (la plus récente), en lecture seule.
		global $wpdb;
		$gift_id = 0;
		$table   = _180c_gift_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$gift_id = (int) $wpdb->get_var( "SELECT id FROM `{$table}` ORDER BY id DESC LIMIT 1" );
		}

		if ( ! $gift_id ) {
			return array(
				'ok'           => false,
				'html'         => '',
				'subject'      => '',
				'recipient'    => '',
				'kind'         => 'gift',
				'object_label' => '',
				'message'      => 'Aucune commande cadeau en base pour générer un aperçu. Passez une commande cadeau de test pour activer cet aperçu.',
			);
		}

		$email->setup_locale();
		// trigger() pose gift_row / gift_context puis appelle send() (intercepté).
		$email->trigger( $gift_id, array() );
		$html      = $email->style_inline( $email->get_content_html() );
		$subject   = self::safe_call( $email, 'get_subject' );
		$recipient = self::safe_call( $email, 'get_recipient' );
		$email->restore_locale();

		if ( ! $html || strlen( trim( wp_strip_all_tags( $html ) ) ) < 5 ) {
			return array(
				'ok'           => false,
				'html'         => '',
				'subject'      => $subject,
				'recipient'    => $recipient,
				'kind'         => 'gift',
				'object_label' => 'Cadeau #' . $gift_id,
				'message'      => 'Le gabarit cadeau a rendu un contenu vide.',
			);
		}

		return array(
			'ok'           => true,
			'html'         => $html,
			'subject'      => $subject,
			'recipient'    => $recipient,
			'kind'         => 'gift',
			'object_label' => 'Cadeau #' . $gift_id,
			'message'      => '',
		);
	}

	/**
	 * Appel défensif d'un getter d'e-mail (subject / recipient).
	 *
	 * @param WC_Email $email  Instance.
	 * @param string   $method Nom de méthode.
	 * @return string
	 */
	private static function safe_call( $email, string $method ): string {
		try {
			return method_exists( $email, $method ) ? (string) $email->{$method}() : '';
		} catch ( \Throwable $e ) {
			return '';
		}
	}
}
