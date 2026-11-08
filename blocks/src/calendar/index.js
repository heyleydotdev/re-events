import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	const { showNav } = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Calendar', 're-events' ) }>
					<ToggleControl
						label={ __( 'Show month navigation', 're-events' ) }
						checked={ showNav }
						onChange={ ( value ) =>
							setAttributes( { showNav: value } )
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
