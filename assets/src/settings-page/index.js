/**
 * External dependencies
 */
import PropTypes from 'prop-types';
import {
	CURRENT_THEME,
	OPTIONS_REST_ENDPOINT,
	READER_THEMES_REST_ENDPOINT,
	UPDATES_NONCE,
} from 'amp-settings';

/**
 * WordPress dependencies
 */
import domReady from '@wordpress/dom-ready';
import { render, useContext } from '@wordpress/element';

/**
 * Internal dependencies
 */
import '../css/variables.css';
import '../css/elements.css';
import '../css/core-components.css';
import './style.css';
import { OptionsContextProvider, Options } from '../components/options-context-provider';
import { ReaderThemesContextProvider } from '../components/reader-themes-context-provider';
import { SiteSettingsProvider } from '../components/site-settings-provider';
import { TemplateModes } from './template-modes';
import { SupportedTemplates } from './supported-templates';
import { MobileRedirection } from './mobile-redirection';
import { ReaderThemes } from './reader-themes';
import { SettingsFooter } from './settings-footer';
import { PluginSuppression } from './plugin-suppression';

const { ajaxurl: wpAjaxUrl } = global;

/**
 * Context providers for the settings page.
 *
 * @param {Object} props Component props.
 * @param {any} props.children Context consumers.
 */
function Providers( { children } ) {
	return (
		<SiteSettingsProvider>
			<OptionsContextProvider optionsRestEndpoint={ OPTIONS_REST_ENDPOINT } populateDefaultValues={ true }>
				<ReaderThemesContextProvider
					currentTheme={ CURRENT_THEME }
					readerThemesEndpoint={ READER_THEMES_REST_ENDPOINT }
					updatesNonce={ UPDATES_NONCE }
					wpAjaxUrl={ wpAjaxUrl }
				>
					{ children }
				</ReaderThemesContextProvider>
			</OptionsContextProvider>
		</SiteSettingsProvider>
	);
}
Providers.propTypes = {
	children: PropTypes.any,
};

/**
 * Settings page application root.
 */
function Root() {
	const { fetchingOptions } = useContext( Options );

	if ( false !== fetchingOptions ) {
		return null;
	}

	return (
		<>
			<TemplateModes />
			<ReaderThemes />
			<SupportedTemplates />
			<MobileRedirection />
			<PluginSuppression />
			<SettingsFooter />
		</>
	);
}

domReady( () => {
	const root = document.getElementById( 'amp-settings-root' );

	if ( root ) {
		render( (
			<Providers>
				<Root optionsRestEndpoint={ OPTIONS_REST_ENDPOINT } />
			</Providers>
		), root );
	}
} );
