import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	const {
		event,
		ticket,
		occurrence,
		heading,
		dateFormat,
		timeFormat,
		showPrice,
		defaultQuantity,
		maxQuantity,
		hideSoldOut,
		buttonLabel,
		successMessage,
	} = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Booking form', 're-events' ) }>
					<TextControl
						label={ __( 'Event ID', 're-events' ) }
						type="number"
						min={ 0 }
						value={ String( event ) }
						help={ __(
							'Leave 0 to use the current event.',
							're-events'
						) }
						onChange={ ( value ) =>
							setAttributes( {
								event: Math.max(
									0,
									parseInt( value, 10 ) || 0
								),
							} )
						}
					/>
					<TextControl
						label={ __( 'Ticket ID', 're-events' ) }
						type="number"
						min={ 0 }
						help={ __(
							'Leave 0 for the first ticket.',
							're-events'
						) }
						value={ String( ticket ) }
						onChange={ ( value ) =>
							setAttributes( {
								ticket: Math.max(
									0,
									parseInt( value, 10 ) || 0
								),
							} )
						}
					/>
					<TextControl
						label={ __( 'Time ID', 're-events' ) }
						type="number"
						min={ 0 }
						help={ __(
							'Leave 0 for the first time.',
							're-events'
						) }
						value={ String( occurrence ) }
						onChange={ ( value ) =>
							setAttributes( {
								occurrence: Math.max(
									0,
									parseInt( value, 10 ) || 0
								),
							} )
						}
					/>
					<TextControl
						label={ __( 'Heading', 're-events' ) }
						value={ heading }
						onChange={ ( value ) =>
							setAttributes( { heading: value } )
						}
					/>
					<TextControl
						label={ __( 'Date format', 're-events' ) }
						value={ dateFormat }
						placeholder="F j, Y"
						help={ __(
							'Leave empty to use the site format.',
							're-events'
						) }
						onChange={ ( value ) =>
							setAttributes( { dateFormat: value } )
						}
					/>
					<TextControl
						label={ __( 'Time format', 're-events' ) }
						value={ timeFormat }
						placeholder="g:i a"
						help={ __(
							'Leave empty to use the site format.',
							're-events'
						) }
						onChange={ ( value ) =>
							setAttributes( { timeFormat: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show price', 're-events' ) }
						checked={ showPrice }
						onChange={ ( value ) =>
							setAttributes( { showPrice: value } )
						}
					/>
					<TextControl
						label={ __( 'Default seats', 're-events' ) }
						type="number"
						min={ 1 }
						value={ String( defaultQuantity ) }
						onChange={ ( value ) =>
							setAttributes( {
								defaultQuantity: Math.max(
									1,
									parseInt( value, 10 ) || 1
								),
							} )
						}
					/>
					<TextControl
						label={ __( 'Maximum seats', 're-events' ) }
						type="number"
						min={ 0 }
						help={ __( '0 uses the ticket limit.', 're-events' ) }
						value={ String( maxQuantity ) }
						onChange={ ( value ) =>
							setAttributes( {
								maxQuantity: Math.max(
									0,
									parseInt( value, 10 ) || 0
								),
							} )
						}
					/>
					<ToggleControl
						label={ __( 'Hide sold-out tickets', 're-events' ) }
						checked={ hideSoldOut }
						onChange={ ( value ) =>
							setAttributes( { hideSoldOut: value } )
						}
					/>
					<TextControl
						label={ __( 'Button label', 're-events' ) }
						value={ buttonLabel }
						placeholder={ __( 'Book', 're-events' ) }
						onChange={ ( value ) =>
							setAttributes( { buttonLabel: value } )
						}
					/>
					<TextControl
						label={ __( 'Success message', 're-events' ) }
						value={ successMessage }
						placeholder={ __( 'Booking confirmed.', 're-events' ) }
						onChange={ ( value ) =>
							setAttributes( { successMessage: value } )
						}
					/>
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
