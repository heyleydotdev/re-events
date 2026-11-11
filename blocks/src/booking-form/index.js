import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	const { event, buttonLabel, successMessage } = attributes;

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
