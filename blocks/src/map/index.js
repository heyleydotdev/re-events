import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	const { limit, height } = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Map', 're-events' ) }>
					<TextControl
						label={ __( 'Maximum venues', 're-events' ) }
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
					<TextControl
						label={ __( 'Height (px)', 're-events' ) }
						type="number"
						min={ 200 }
						value={ String( height ) }
						onChange={ ( value ) =>
							setAttributes( {
								height: Math.max(
									200,
									parseInt( value, 10 ) || 200
								),
							} )
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
