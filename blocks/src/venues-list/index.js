import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	const { limit, showDescription } = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Venues', 're-events' ) }>
					<TextControl
						label={ __( 'Number of venues', 're-events' ) }
						type="number"
						min={ 1 }
						value={ String( limit ) }
						onChange={ ( value ) =>
							setAttributes( {
								limit: Math.max(
									1,
									parseInt( value, 10 ) || 1
								),
							} )
						}
					/>
					<ToggleControl
						label={ __( 'Show description', 're-events' ) }
						checked={ showDescription }
						onChange={ ( value ) =>
							setAttributes( { showDescription: value } )
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
