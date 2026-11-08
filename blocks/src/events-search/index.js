import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	const {
		limit,
		showKeyword,
		showCategory,
		showVenue,
		showDates,
		showUpcoming,
		showResults,
	} = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Search fields', 're-events' ) }>
					<TextControl
						label={ __( 'Number of results', 're-events' ) }
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
						label={ __( 'Show keyword', 're-events' ) }
						checked={ showKeyword }
						onChange={ ( value ) =>
							setAttributes( { showKeyword: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show category', 're-events' ) }
						checked={ showCategory }
						onChange={ ( value ) =>
							setAttributes( { showCategory: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show venue', 're-events' ) }
						checked={ showVenue }
						onChange={ ( value ) =>
							setAttributes( { showVenue: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show dates', 're-events' ) }
						checked={ showDates }
						onChange={ ( value ) =>
							setAttributes( { showDates: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show upcoming toggle', 're-events' ) }
						checked={ showUpcoming }
						onChange={ ( value ) =>
							setAttributes( { showUpcoming: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show results', 're-events' ) }
						checked={ showResults }
						onChange={ ( value ) =>
							setAttributes( { showResults: value } )
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
