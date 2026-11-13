import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	const { limit, showHeading, dateFormat, timeFormat } = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Bookings', 're-events' ) }>
					<RangeControl
						label={ __( 'Bookings shown', 're-events' ) }
						min={ 1 }
						max={ 100 }
						value={ limit }
						onChange={ ( value ) =>
							setAttributes( { limit: value || 20 } )
						}
					/>
					<ToggleControl
						label={ __( 'Show heading', 're-events' ) }
						checked={ showHeading }
						onChange={ ( value ) =>
							setAttributes( { showHeading: value } )
						}
					/>
					<TextControl
						label={ __( 'Date format', 're-events' ) }
						value={ dateFormat }
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
						help={ __(
							'Leave empty to use the site format.',
							're-events'
						) }
						onChange={ ( value ) =>
							setAttributes( { timeFormat: value } )
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
