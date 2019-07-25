/**
 * External dependencies
 */
import classnames from 'classnames';
import PropTypes from 'prop-types';
import { isEqual } from 'lodash';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Component } from '@wordpress/element';
import {
	RichText,
	BlockControls,
	AlignmentToolbar,
} from '@wordpress/block-editor';
import { select } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { maybeUpdateFontSize, maybeUpdateBlockDimensions } from '../../helpers';
import { getBackgroundColorWithOpacity } from '../../../common/helpers';
import './edit.css';
import { StoryBlockMover } from '../../components';

class TextBlockEdit extends Component {
	constructor() {
		super( ...arguments );

		this.state = {
			isEditing: false,
		};

		this.onReplace = this.onReplace.bind( this );
		this.toggleIsEditing = this.toggleIsEditing.bind( this );
	}

	componentDidMount() {
		maybeUpdateFontSize( this.props );
	}

	componentDidUpdate( prevProps, prevState ) {
		const { attributes, fontSize, isSelected } = this.props;
		const {
			height,
			width,
			content,
			ampFitText,
			ampFontFamily,
		} = attributes;

		// If the block was unselected, make sure that it's not editing anymore.
		if ( ! isSelected && prevProps.isSelected ) {
			this.toggleIsEditing( false );
		}

		const checkFontSize = ampFitText && (
			prevProps.attributes.ampFitText !== ampFitText ||
			prevProps.attributes.ampFontFamily !== ampFontFamily ||
			prevProps.attributes.width !== width ||
			prevProps.attributes.height !== height ||
			prevProps.attributes.content !== content
		);

		if ( checkFontSize ) {
			maybeUpdateFontSize( this.props );
		}

		const checkBlockDimensions = ! ampFitText && (
			! isEqual( prevProps.fontSize, fontSize ) ||
			prevProps.attributes.ampFitText !== ampFitText ||
			prevProps.attributes.ampFontFamily !== ampFontFamily ||
			prevProps.attributes.content !== content
		);

		if ( checkBlockDimensions ) {
			maybeUpdateBlockDimensions( this.props );
		}

		// If the state changed to editing, focus on the text.
		if ( this.state.isEditing && ! prevState.isEditing ) {
			const textInput = document.querySelector( '.is-selected .wp-block-amp-amp-story-text' );
			if ( textInput ) {
				// Create selection, collapse it in the end of the content.
				const range = document.createRange();
				range.selectNodeContents( textInput );
				range.collapse( false );
				const selection = window.getSelection();
				selection.removeAllRanges();
				selection.addRange( range );
			}
		}
	}

	onReplace( blocks ) {
		const { attributes, onReplace, name } = this.props;
		// Make sure that 'undefined' values aren't passed into onReplace.
		blocks = blocks.filter( ( block ) => 'undefined' !== typeof block );
		if ( ! blocks.length ) {
			return;
		}
		onReplace( blocks.map( ( block, index ) => (
			index === 0 && block.name === name ?
				{ ...block,
					attributes: {
						...attributes,
						...block.attributes,
					},
				} :
				block
		) ) );
	}

	toggleIsEditing( enable ) {
		if ( enable !== this.state.isEditing ) {
			this.setState( {
				isEditing: ! this.state.isEditing,
			} );
		}
	}

	render() {
		const { isEditing } = this.state;

		const {
			attributes,
			setAttributes,
			className,
			clientId,
			fontSize,
			isPartOfMultiSelection,
			isSelected,
			backgroundColor,
			customBackgroundColor,
			textColor,
			name,
		} = this.props;

		const {
			placeholder,
			content,
			align,
			ampFitText,
			autoFontSize,
			height,
			opacity,
		} = attributes;

		const userFontSize = fontSize && fontSize.size ? fontSize.size + 'px' : undefined;

		const { colors } = select( 'core/block-editor' ).getSettings();
		const appliedBackgroundColor = getBackgroundColorWithOpacity( colors, backgroundColor, customBackgroundColor, opacity );

		const wrapperStyle = { backgroundColor: appliedBackgroundColor };
		if ( ampFitText && content.length ) {
			wrapperStyle.lineHeight = height + 'px';
		}

		const styleClasses = [];
		let wrapperClass = 'wp-block-amp-story-text-wrapper';

		// We need to assign the block styles to the wrapper, too.
		if ( attributes.className && attributes.className.length ) {
			const classNames = attributes.className.split( ' ' );
			classNames.forEach( ( value ) => {
				if ( value.includes( 'is-style' ) ) {
					styleClasses.push( value );
				}
			} );
		}

		if ( styleClasses.length ) {
			wrapperClass += ' ' + styleClasses.join( ' ' );
		}

		const textWrapperClassName = 'wp-block-amp-story-text';
		const textClassNames = {
			'has-text-color': textColor.color,
			[ textColor.class ]: textColor.class,
			[ fontSize.class ]: ampFitText ? undefined : fontSize.class,
			'is-amp-fit-text': ampFitText,
		};
		const textStyle = {
			color: textColor.color,
			fontSize: ampFitText ? autoFontSize + 'px' : userFontSize,
			textAlign: align,
			position: ampFitText && content.length ? 'static' : undefined,
		};

		// StoryBlockMover is added here to the Text block since it depends on isEditing state.
		return (
			<>
				<BlockControls>
					<AlignmentToolbar
						value={ align }
						onChange={ ( value ) => setAttributes( { align: value } ) }
					/>
				</BlockControls>
				<div className={ classnames( wrapperClass, {
					'with-line-height': ampFitText,
					'is-empty-draggable-text': ! isEditing && ! content.length,
				} ) } style={ wrapperStyle } >
					{ isEditing &&
						<RichText
							wrapperClassName={ textWrapperClassName }
							tagName="p"
							// Ensure line breaks are normalised to HTML.
							value={ content }
							onChange={ ( nextContent ) => setAttributes( { content: nextContent } ) }
							// The 2 following lines are necessary for pasting to work.
							onReplace={ this.onReplace }
							onSplit={ () => {} }
							style={ textStyle }
							className={ classnames( className, textClassNames ) }
							placeholder={ placeholder || __( 'Write text…', 'amp' ) }
						/>
					}
					{ ! isEditing &&
						<StoryBlockMover
							clientId={ clientId }
							blockName={ name }
							blockElementId={ `block-${ clientId }` }
							isDraggable={ ! isPartOfMultiSelection }
							isMovable={ true }
						>
							<div
								role="textbox"
								tabIndex="-1"
								className="is-not-editing editor-rich-text block-editor-rich-text wp-block-amp-story-text"
								onClick={ () => {
									if ( isSelected ) {
										this.toggleIsEditing( true );
									}
								} }
								onMouseDown={ ( event ) => {
									// Prevent text selection on double click.
									if ( 1 < event.detail ) {
										event.preventDefault();
									}
								} }
							>
								<p
									className={ classnames( className + ' block-editor-rich-text__editable editor-rich-text__editable', textClassNames ) }
									style={ textStyle }
								>
									{ content.length ? content : (
										<p className="amp-text-placeholder">
											{ placeholder || __( 'Write text…', 'amp' ) }
										</p>
									) }
								</p>
							</div>
						</StoryBlockMover>
					}
				</div>
			</>
		);
	}
}

TextBlockEdit.propTypes = {
	attributes: PropTypes.shape( {
		width: PropTypes.number,
		height: PropTypes.number,
		placeholder: PropTypes.string,
		content: PropTypes.string,
		align: PropTypes.string,
		ampFitText: PropTypes.bool,
		autoFontSize: PropTypes.number,
		tagName: PropTypes.string,
		opacity: PropTypes.number,
		className: PropTypes.string,
		ampFontFamily: PropTypes.string,
	} ).isRequired,
	isSelected: PropTypes.bool.isRequired,
	clientId: PropTypes.string.isRequired,
	isPartOfMultiSelection: PropTypes.bool,
	onReplace: PropTypes.func.isRequired,
	name: PropTypes.string.isRequired,
	setAttributes: PropTypes.func.isRequired,
	className: PropTypes.string,
	fontSize: PropTypes.shape( {
		name: PropTypes.string,
		shortName: PropTypes.string,
		size: PropTypes.number,
		slug: PropTypes.string,
		class: PropTypes.string,
	} ).isRequired,
	backgroundColor: PropTypes.shape( {
		color: PropTypes.string,
		name: PropTypes.string,
		slug: PropTypes.string,
		class: PropTypes.string,
	} ).isRequired,
	customBackgroundColor: PropTypes.string,
	textColor: PropTypes.shape( {
		color: PropTypes.string,
		name: PropTypes.string,
		slug: PropTypes.string,
		class: PropTypes.string,
	} ).isRequired,
};

export default TextBlockEdit;
