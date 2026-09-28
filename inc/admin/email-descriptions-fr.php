<?php
/**
 * Descriptions vulgarisées en français des e-mails WooCommerce.
 *
 * Remplace les descriptions techniques (souvent en anglais) des classes WC_Email
 * par un libellé clair pour l'outil « Outils → Test e-mails ». Indexé par `id`
 * d'e-mail. Le catalogue couvre TOUS les e-mails (actifs ou non) : ainsi la
 * description reste correcte si un e-mail est réactivé plus tard.
 *
 * Convention de rédaction : « Email client : … » / « Email admin : … ».
 *
 * @package 180c
 * @return array<string,string>
 */

defined( 'ABSPATH' ) || exit;

return array(

	// === 180°C (thème) ===
	'180c_gift_donor'                                  => 'Email client : confirme au donateur son achat d’un abonnement offert en cadeau.',
	'180c_gift_recipient'                              => 'Email client : annonce au bénéficiaire son abonnement cadeau et l’invite à se connecter.',
	'180c_subscription_cancelled'                      => 'Email client : confirme à l’abonné la résiliation de son abonnement.',

	// === WooCommerce — commandes ===
	'new_order'                                        => 'Email admin : une nouvelle commande vient d’être passée.',
	'cancelled_order'                                  => 'Email admin : une commande a été annulée.',
	'failed_order'                                     => 'Email admin : le paiement d’une commande a échoué.',
	'customer_processing_order'                        => 'Email client : sa commande est bien reçue et en cours de préparation.',
	'customer_completed_order'                         => 'Email client : sa commande est terminée (expédiée / disponible).',
	'customer_on_hold_order'                           => 'Email client : sa commande est en attente (paiement à confirmer).',
	'customer_cancelled_order'                         => 'Email client : sa commande a été annulée.',
	'customer_failed_order'                            => 'Email client : le paiement de sa commande a échoué.',
	'customer_refunded_order'                          => 'Email client : sa commande a été remboursée.',
	'customer_invoice'                                 => 'Email client : facture / détails de commande, envoyés manuellement depuis l’admin.',
	'customer_note'                                    => 'Email client : une note a été ajoutée à sa commande.',
	'customer_pos_completed_order'                     => 'Email client : reçu d’un achat réglé en boutique (caisse).',
	'customer_pos_refunded_order'                      => 'Email client : remboursement d’un achat réglé en boutique (caisse).',

	// === WooCommerce — compte client ===
	'customer_new_account'                             => 'Email client : bienvenue, son compte vient d’être créé.',
	'customer_reset_password'                          => 'Email client : lien pour réinitialiser son mot de passe.',

	// === WooCommerce Subscriptions ===
	'new_renewal_order'                                => 'Email admin : une commande de renouvellement d’abonnement a été créée.',
	'new_switch_order'                                 => 'Email admin : un client a changé de formule d’abonnement.',
	'cancelled_subscription'                           => 'Email admin : un abonnement a été annulé.',
	'expired_subscription'                             => 'Email admin : un abonnement a expiré.',
	'suspended_subscription'                           => 'Email admin : un abonnement a été suspendu (mis en pause).',
	'reactivated_subscription'                         => 'Email admin : un abonnement suspendu a été réactivé.',
	'payment_retry'                                    => 'Email admin : une relance de paiement de renouvellement est programmée après un échec.',
	'customer_processing_renewal_order'                => 'Email client : le prélèvement de renouvellement de son abonnement est en cours.',
	'customer_completed_renewal_order'                 => 'Email client : son abonnement a bien été renouvelé.',
	'customer_on_hold_renewal_order'                   => 'Email client : sa commande de renouvellement est en attente de paiement.',
	'customer_completed_switch_order'                  => 'Email client : son changement de formule d’abonnement est terminé.',
	'customer_renewal_invoice'                         => 'Email client : facture à régler pour renouveler son abonnement.',
	'customer_payment_retry'                           => 'Email client : nouvelle tentative de paiement après l’échec d’un renouvellement.',
	'customer_notification_auto_renewal'               => 'Email client : son abonnement sera renouvelé automatiquement très bientôt.',
	'customer_notification_manual_renewal'             => 'Email client : son abonnement doit être renouvelé manuellement.',
	'customer_notification_subscription_expiry'        => 'Email client : son abonnement arrive à expiration.',
	'customer_notification_auto_trial_expiry'          => 'Email client : sa période d’essai gratuit se termine (passage au paiement automatique).',
	'customer_notification_manual_trial_expiry'        => 'Email client : sa période d’essai gratuit se termine (paiement manuel requis).',

	// === WooCommerce Subscriptions — Gifting (abonnements offerts) ===
	'WCSG_Email_Customer_New_Account'                  => 'Email client : compte créé pour le bénéficiaire d’un abonnement offert.',
	'recipient_completed_order'                        => 'Email client : le bénéficiaire reçoit la confirmation de son abonnement digital offert.',
	'recipient_completed_renewal_order'                => 'Email client : le bénéficiaire est informé du renouvellement de son abonnement offert.',
	'gift_recipient_processing_renewal_order'          => 'Email client : reçu de renouvellement envoyé au bénéficiaire d’un abonnement offert.',

	// === WooCommerce Memberships (accès recettes) ===
	'WC_Memberships_User_Membership_Activated_Email'   => 'Email client : son accès aux recettes en ligne est désormais actif.',
	'WC_Memberships_User_Membership_Ending_Soon_Email' => 'Email client : son accès aux recettes en ligne se termine bientôt.',
	'WC_Memberships_User_Membership_Ended_Email'       => 'Email client : son accès aux recettes en ligne a expiré.',
	'WC_Memberships_User_Membership_Renewal_Reminder_Email' => 'Email client : rappel pour renouveler son accès aux recettes.',
	'WC_Memberships_User_Membership_Note_Email'        => 'Email client : une note a été ajoutée à son adhésion.',

	// === WooPayments ===
	'new_receipt'                                      => 'Email client : reçu d’un paiement encaissé via lecteur de carte (WooPayments).',
	'wcpay_post_kyc_activation'                        => 'Email admin : rappel pour réaliser sa première vente avec WooPayments.',

	// === Stripe ===
	'failed_renewal_authentication'                    => 'Email client : authentification 3-D Secure requise pour renouveler son abonnement.',
	'failed_preorder_sca_authentication'               => 'Email client : authentification requise pour finaliser le paiement d’une pré-commande.',
	'failed_authentication_requested'                  => 'Email admin : le paiement automatique a échoué, le client doit authentifier le paiement.',
	'wc_stripe_failed_refund_admin'                    => 'Email admin : un remboursement Stripe a échoué.',
	'wc_stripe_failed_refund_customer'                 => 'Email client : son remboursement a échoué.',

	// === WooCommerce — divers admin ===
	'admin_payment_gateway_enabled'                    => 'Email admin : une passerelle de paiement vient d’être activée.',
);
