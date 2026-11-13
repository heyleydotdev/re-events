import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	const {
		heading,
		emptyLabel,
		limit,
		dateFormat,
		timeFormat,
		lookupHeading,
		lookupButtonLabel,
		referenceLabel,
		emailLabel,
		cancelLabel,
		reactivateLabel,
		freeLabel,
		className,
		wrapperClass,
	} = attributes;

	const text = ( key, label, value, help ) => (
		<TextControl
			label={ label }
			value={ value }
			help={ help }
			onChange={ ( next ) => setAttributes( { [ key ]: next } ) }
		/>
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Bookings', 're-events' ) }>
					{ text(
						'heading',
						__( 'Heading', 're-events' ),
						heading,
						__( 'Leave empty to hide the heading.', 're-events' )
					) }
					<RangeControl
						label={ __( 'Bookings shown', 're-events' ) }
						min={ 1 }
						max={ 100 }
						value={ limit }
						onChange={ ( value ) =>
							setAttributes( { limit: value || 20 } )
						}
					/>
					{ text(
						'emptyLabel',
						__( 'Empty message', 're-events' ),
						emptyLabel
					) }
					{ text(
						'dateFormat',
						__( 'Date format', 're-events' ),
						dateFormat,
						__( 'Leave empty to use the site format.', 're-events' )
					) }
					{ text(
						'timeFormat',
						__( 'Time format', 're-events' ),
						timeFormat,
						__( 'Leave empty to use the site format.', 're-events' )
					) }
				</PanelBody>
				<PanelBody
					title={ __( 'Labels', 're-events' ) }
					initialOpen={ false }
				>
					{ text(
						'lookupHeading',
						__( 'Lookup heading', 're-events' ),
						lookupHeading
					) }
					{ text(
						'lookupButtonLabel',
						__( 'Lookup button', 're-events' ),
						lookupButtonLabel
					) }
					{ text(
						'referenceLabel',
						__( 'Reference label', 're-events' ),
						referenceLabel
					) }
					{ text(
						'emailLabel',
						__( 'Email label', 're-events' ),
						emailLabel
					) }
					{ text(
						'cancelLabel',
						__( 'Cancel label', 're-events' ),
						cancelLabel
					) }
					{ text(
						'reactivateLabel',
						__( 'Restore label', 're-events' ),
						reactivateLabel
					) }
					{ text(
						'freeLabel',
						__( 'Free label', 're-events' ),
						freeLabel
					) }
				</PanelBody>
				<PanelBody
					title={ __( 'Advanced', 're-events' ) }
					initialOpen={ false }
				>
					{ text(
						'className',
						__( 'CSS class', 're-events' ),
						className
					) }
					{ text(
						'wrapperClass',
						__( 'Wrapper CSS class', 're-events' ),
						wrapperClass
					) }
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
				<ServerSideRender
					block={ metadata.name }
					attributes={ attributes }
				/>
			</div>
		</>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
