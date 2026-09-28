/**
 * Cahiers de Delphine — page d'optin newsletter.
 *
 * - Form d'inscription : POST vers /wp-json/180c/v1/newsletter/subscribe
 *   avec nonce wp_rest (window._180c).
 * - Reveal IntersectionObserver : pose .is-visible sur les sections au scroll
 *   (skip si prefers-reduced-motion).
 *
 * Chargé en dynamic import depuis main.js dès que [data-component="cdd-optin"]
 * est présent dans le DOM.
 */

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * Récupère un nonce frais via GET /newsletter/nonce (action
 * `180c_newsletter_public`) juste avant le POST. Robustesse cache : un nonce
 * imprimé dans une page servie depuis un cache serait périmé. En cas d'échec
 * réseau (ou réponse invalide), renvoie une chaîne vide — la route web vérifie
 * le nonce de façon souple (absent → laissé passer) : on ne bloque jamais
 * l'inscription sur un échec de récupération du nonce. Modèle : newsletter.js.
 *
 * @param {string} restUrl Base REST (window._180c.restUrl).
 * @returns {Promise<string>}
 */
async function fetchFreshNonce( restUrl ) {
	try {
		const res  = await fetch( restUrl + 'newsletter/nonce', {
			headers: { Accept: 'application/json' },
			cache:   'no-store',
		} );
		const data = res.ok ? await res.json() : null;
		if ( data && typeof data.nonce === 'string' && data.nonce !== '' ) {
			return data.nonce;
		}
	} catch ( _ ) {
		// Réseau indisponible : POST sans nonce (vérification souple côté serveur).
	}
	return '';
}

/**
 * Initialise le module : form + reveal au scroll.
 */
export function init() {
	const root = document.querySelector( '[data-component="cdd-optin"]' );
	if ( ! root ) {
		return;
	}

	initReveal( root );

	const form = root.querySelector( '[data-cdd-form]' );
	if ( ! form ) {
		return;
	}

	const emailInput   = form.querySelector( '[data-cdd-email]' );
	const submitBtn    = form.querySelector( '[data-cdd-submit]' );
	const submitLabel  = form.querySelector( '[data-cdd-submit-label]' );
	const feedback     = form.querySelector( '[data-cdd-feedback]' );

	if ( ! emailInput || ! submitBtn || ! feedback ) {
		return;
	}

	const initialSubmitLabel = submitLabel ? submitLabel.textContent : '';

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();

		const email = ( emailInput.value || '' ).trim();

		if ( ! EMAIL_RE.test( email ) ) {
			setState( form, feedback, 'error', 'Merci de saisir une adresse email valide.' );
			emailInput.focus();
			return;
		}

		const cfg = window._180c || {};
		// Seul `restUrl` est requis : le POST n'utilise plus l'en-tête
		// `X-WP-Nonce` (source d'un 403 `rest_cookie_invalid_nonce` au niveau du
		// cœur REST quand le nonce inline d'une page cachée est périmé). Le nonce
		// passe désormais par le body (`nl_nonce`), récupéré frais avant l'envoi.
		if ( ! cfg.restUrl ) {
			setState( form, feedback, 'error', 'Service indisponible. Réessayez plus tard.' );
			return;
		}

		setState( form, feedback, 'loading', 'Envoi en cours…' );
		if ( submitLabel ) {
			submitLabel.textContent = '…';
		}
		submitBtn.disabled = true;

		try {
			// Nonce FRAIS récupéré juste avant le POST (le nonce inline d'une page
			// cachée serait périmé). Échec réseau → chaîne vide : la route web
			// vérifie le nonce de façon SOUPLE (absent → laissé passer), l'envoi
			// n'est jamais bloqué par un échec de récupération du nonce.
			const nlNonce = await fetchFreshNonce( cfg.restUrl );

			const res = await fetch( cfg.restUrl + 'newsletter/subscribe', {
				method:  'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				// `source` : résolu côté serveur en tags src-plt:web / src-loc:cahiers.
				// `_gotcha` : honeypot (vide chez un humain ; rempli = bot → « ok »
				// serveur sans appel Mailchimp), lu tolérant à l'absence du champ.
				// `nl_nonce` : nonce frais (action `180c_newsletter_public`) dans le
				// body — remplace l'en-tête `X-WP-Nonce` (cf. 403 latent).
				body: JSON.stringify( {
					email,
					list: 'free',
					source: 'web-cahiers',
					_gotcha: form.querySelector( '[name="_gotcha"]' )?.value ?? '',
					nl_nonce: nlNonce,
				} ),
				credentials: 'same-origin',
			} );

			if ( res.ok ) {
				setState(
					form,
					feedback,
					'success',
					'Merci ! Vérifiez votre boîte mail pour confirmer votre inscription.'
				);
				form.reset();
			} else {
				let msg = 'Une erreur est survenue. Réessayez dans un instant.';
				try {
					const data = await res.json();
					if ( data && data.message ) {
						msg = data.message;
					}
				} catch ( _ ) { /* JSON parse error : on garde msg par défaut */ }
				setState( form, feedback, 'error', msg );
			}
		} catch ( _ ) {
			setState( form, feedback, 'error', 'Connexion impossible. Vérifiez votre réseau.' );
		} finally {
			submitBtn.disabled = false;
			if ( submitLabel ) {
				submitLabel.textContent = initialSubmitLabel;
			}
		}
	} );
}

/**
 * Pose les classes d'état sur le form + écrit le message dans la zone aria-live.
 *
 * @param {HTMLFormElement} form
 * @param {HTMLElement}     feedback
 * @param {'idle'|'loading'|'success'|'error'} state
 * @param {string}          message
 */
function setState( form, feedback, state, message ) {
	form.classList.remove( 'is-loading', 'is-success', 'is-error' );
	if ( 'loading' === state ) {
		form.classList.add( 'is-loading' );
	} else if ( 'success' === state ) {
		form.classList.add( 'is-success' );
	} else if ( 'error' === state ) {
		form.classList.add( 'is-error' );
	}
	feedback.textContent = message || '';
}

/**
 * Reveal au scroll via IntersectionObserver — pose .is-visible sur chaque
 * section .cdd-optin__hero|__signup|__subscribe|__rail dès qu'elle entre
 * dans le viewport. Pose d'abord .is-reveal-ready sur le root pour activer
 * les règles CSS de transition (sans cette classe : opacity 1 d'emblée,
 * comportement no-JS / no-IO friendly).
 *
 * Skip complet si prefers-reduced-motion ou IntersectionObserver absent.
 *
 * @param {HTMLElement} root
 */
function initReveal( root ) {
	const prefersReducedMotion =
		window.matchMedia &&
		window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	if ( prefersReducedMotion || typeof IntersectionObserver === 'undefined' ) {
		return;
	}

	const sections = root.querySelectorAll(
		'.cdd-optin__hero, .cdd-optin__signup, .cdd-optin__subscribe, .cdd-optin__rail'
	);

	if ( ! sections.length ) {
		return;
	}

	root.classList.add( 'is-reveal-ready' );

	const observer = new IntersectionObserver(
		( entries, obs ) => {
			entries.forEach( ( entry ) => {
				if ( entry.isIntersecting ) {
					entry.target.classList.add( 'is-visible' );
					obs.unobserve( entry.target );
				}
			} );
		},
		{
			rootMargin: '0px 0px -10% 0px',
			threshold:  0.05,
		}
	);

	sections.forEach( ( s ) => observer.observe( s ) );
}
