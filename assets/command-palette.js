( function ( wp, config ) {
	'use strict';

	if ( ! wp?.domReady ) {
		return;
	}

	wp.domReady( function () {
		const routes = config?.menuRoutes;
		if ( routes?.managerUrl ) {
			const mediaLink = document.querySelector( '#menu-media > a' );
			if ( mediaLink ) {
				mediaLink.href = routes.managerUrl;
			}
		}

		if ( routes?.uploadUrl ) {
			document.querySelectorAll( '#adminmenu a[href]' ).forEach( function ( link ) {
				const url = new URL( link.href, window.location.href );
				if ( /\/media-new\.php$/.test( url.pathname ) ) {
					link.href = routes.uploadUrl;
				}
			} );
		}

		if ( ! wp?.data || ! wp?.commands?.store || ! Array.isArray( config?.commands ) ) {
			return;
		}

		window.setTimeout( function () {
			const { registerCommand } = wp.data.dispatch( wp.commands.store );

			config.commands.forEach( function ( command ) {
				registerCommand( {
					name: command.name,
					label: command.label,
					searchLabel: command.searchLabel,
					keywords: command.keywords,
					category: 'view',
					callback: function ( { close } ) {
						close();
						window.location.assign( command.url );
					},
				} );
			} );
		}, 0 );
	} );
}( window.wp, window.uplinkMbeCommandPalette ) );
