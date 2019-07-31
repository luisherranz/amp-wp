/**
 * External dependencies
 */
import PropTypes from 'prop-types';

/**
 * WordPress dependencies
 */
import { RichText } from '@wordpress/block-editor';

const blockAttributes = {
	content: {
		type: 'string',
		source: 'html',
		selector: 'pre',
		default: '',
	},
	textAlign: {
		type: 'string',
	}
};

const saveV120 = ( { attributes } ) => {
	const { textAlign, content } = attributes;

	return (
		<RichText.Content
			tagName="pre"
			style={ { textAlign } }
			value={ content }
		/>
	);
};

saveV120.propTypes = {
	attributes: PropTypes.shape( {
		textAlign: PropTypes.string,
		content: PropTypes.string,
	} ).isRequired,
};

const deprecated = [
	{
		attributes: {
			...blockAttributes,
			deprecated: {
				default: '1.2.0',
			},
		},

		save: saveV120,

		migrate: ( attributes ) => {
			return {
				...omit( attributes, [ 'deprecated', 'anchor' ] ),
			};
		},
	},
];

export default deprecated;
