/**
 * Admin — écran d'édition d'une notification push (CPT 180c_notification).
 *
 * Deux responsabilités :
 *   1. Champs conditionnels + recherche de cible (recette / produit) via
 *      l'endpoint admin-ajax dédié `180c_notif_target`.
 *   2. (Phase 5) Aperçu live d'une notification iOS sous le panneau « Contenu ».
 *
 * Aucune dépendance : vanilla JS, servi tel quel (pas de build, pas de Vite).
 */
( function () {
	'use strict';

	var cfg = window._180cNotif || {};

	var typeRadios = document.querySelectorAll( 'input[name="_180c_notif_target_type"]' );
	var search     = document.getElementById( '_180c_notif_target_search' );
	var list       = document.getElementById( '_180c_notif_target_list' );
	var hidden     = document.getElementById( '_180c_notif_target_id' );
	var body       = document.getElementById( '_180c_notif_body' );
	var counter    = document.getElementById( '_180c_notif_body_count' );

	if ( ! typeRadios.length || ! search ) {
		return;
	}

	/** Index des derniers résultats de recherche, par identifiant. */
	var itemsById = {};

	/** Retourne le type de cible actuellement sélectionné. */
	function currentType() {
		for ( var i = 0; i < typeRadios.length; i++ ) {
			if ( typeRadios[ i ].checked ) {
				return typeRadios[ i ].value;
			}
		}
		return 'recipe';
	}

	/** Extrait l'identifiant « (#123) » saisi et met à jour le champ caché. */
	function resolveId() {
		var m = search.value.match( /#(\d+)\)?\s*$/ );
		hidden.value = m ? m[ 1 ] : '';
		return hidden.value;
	}

	/** Appel à l'endpoint admin-ajax dédié. */
	function request( params ) {
		var data = new URLSearchParams();
		data.append( 'action', '180c_notif_target' );
		data.append( 'nonce', cfg.nonce || '' );
		Object.keys( params ).forEach( function ( k ) {
			data.append( k, params[ k ] );
		} );

		return fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: data.toString()
		} ).then( function ( r ) {
			return r.ok ? r.json() : null;
		} ).catch( function () {
			return null;
		} );
	}

	var timer = null;
	function runSearch() {
		var q = search.value.trim();
		if ( q.length < 2 ) {
			list.innerHTML = '';
			return;
		}
		clearTimeout( timer );
		timer = setTimeout( function () {
			request( { type: currentType(), q: q } ).then( function ( res ) {
				list.innerHTML = '';
				if ( ! res || ! res.success || ! res.data || ! res.data.results ) {
					return;
				}
				res.data.results.forEach( function ( item ) {
					itemsById[ item.id ] = item;
					var opt = document.createElement( 'option' );
					opt.value = item.title + ' (#' + item.id + ')';
					list.appendChild( opt );
				} );
				onSelectionChange();
			} );
		}, 250 );
	}

	/** Réinitialise la sélection quand on change de type de contenu. */
	function onTypeChange() {
		search.value = '';
		hidden.value = '';
		list.innerHTML = '';
		itemsById = {};
		onSelectionChange();
	}

	// Point d'extension Phase 5 : mise à jour de l'aperçu. Défini plus bas si
	// le panneau d'aperçu est présent, sinon no-op.
	function onSelectionChange() {
		if ( typeof window._180cNotifPreviewUpdate === 'function' ) {
			window._180cNotifPreviewUpdate();
		}
	}

	for ( var i = 0; i < typeRadios.length; i++ ) {
		typeRadios[ i ].addEventListener( 'change', onTypeChange );
	}

	search.addEventListener( 'input', function () {
		resolveId();
		runSearch();
		onSelectionChange();
	} );
	search.addEventListener( 'change', function () {
		resolveId();
		onSelectionChange();
	} );

	if ( body && counter ) {
		var max = cfg.bodyMax || 178;
		body.addEventListener( 'input', function () {
			counter.textContent = body.value.length;
			counter.style.color = body.value.length > max ? '#b32d2e' : '';
			onSelectionChange();
		} );
	}

	// Expose quelques utilitaires pour l'extension Aperçu (ci-dessous).
	window._180cNotifApi = {
		cfg: cfg,
		currentType: currentType,
		resolveId: resolveId,
		request: request,
		getItem: function ( id ) { return itemsById[ id ] || null; }
	};
} )();

/**
 * Aperçu live — reproduction d'une notification iOS mise à jour à la frappe
 * (titre, corps) et au changement de sélection (image dérivée).
 */
( function () {
	'use strict';

	var api = window._180cNotifApi;
	if ( ! api ) {
		return;
	}

	var cfg     = api.cfg || {};
	var preview = document.getElementById( '_180c_notif_preview' );
	if ( ! preview ) {
		return;
	}

	var titleInput = document.getElementById( 'title' ); // Champ titre natif WP.
	var bodyInput  = document.getElementById( '_180c_notif_body' );
	var hidden     = document.getElementById( '_180c_notif_target_id' );

	var elTitle = document.getElementById( '_180c_notif_preview_title' );
	var elBody  = document.getElementById( '_180c_notif_preview_body' );
	var elThumb = document.getElementById( '_180c_notif_preview_thumb' );
	var elImage = document.getElementById( '_180c_notif_preview_image' );

	// Identifiant de la cible dont l'image est actuellement affichée : évite de
	// refetcher l'image à chaque frappe.
	var lastImageId = hidden ? ( hidden.value || '' ) : '';

	function setImage( url ) {
		if ( url ) {
			elImage.src = url;
			elThumb.style.display = '';
		} else {
			elImage.removeAttribute( 'src' );
			elThumb.style.display = 'none';
		}
	}

	function refreshImage() {
		var id = hidden ? ( hidden.value || '' ) : '';
		if ( id === lastImageId ) {
			return;
		}
		lastImageId = id;

		if ( ! id ) {
			setImage( '' );
			return;
		}

		var cached = api.getItem( id );
		if ( cached ) {
			setImage( cached.image_url );
			return;
		}

		api.request( { type: api.currentType(), id: id } ).then( function ( res ) {
			if ( res && res.success && res.data && res.data.item ) {
				setImage( res.data.item.image_url );
			} else {
				setImage( '' );
			}
		} );
	}

	function updatePreview() {
		var t = titleInput && titleInput.value ? titleInput.value.trim() : '';
		elTitle.textContent = t || ( cfg.previewTitle || '' );

		var b = bodyInput && bodyInput.value ? bodyInput.value : '';
		elBody.textContent = b.trim() ? b : ( cfg.previewBody || '' );

		refreshImage();
	}

	// Point d'extension appelé par le module principal (corps, sélection, type).
	window._180cNotifPreviewUpdate = updatePreview;

	if ( titleInput ) {
		titleInput.addEventListener( 'input', updatePreview );
	}

	updatePreview();
} )();
