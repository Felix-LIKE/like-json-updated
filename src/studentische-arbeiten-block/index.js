/**
 * LIKE-Json: Studentische Arbeiten block (edit).
 *
 * Dynamic block – renders server-side. The editor shows a preview using
 * ServerSideRender so editors see the actual PHP output.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { registerBlockType } from '@wordpress/blocks';
import ServerSideRender from '@wordpress/server-side-render';

import './style.scss';

const TASKS = [
	{ label: __( 'Bachelorarbeiten', 'like-json' ), value: 'bachelorarbeiten' },
	{ label: __( 'Masterarbeiten', 'like-json' ), value: 'masterarbeiten' },
	{
		label: __( 'Forschungspraktika', 'like-json' ),
		value: 'forschungspraktika',
	},
	{ label: __( 'Projektarbeiten', 'like-json' ), value: 'projektarbeiten' },
	{ label: __( 'Sonstige Arbeiten', 'like-json' ), value: 'sonstiges' },
	{ label: __( 'Alle Arbeiten', 'like-json' ), value: 'arbeiten-alle' },
];

const FORMATS = [
	{ label: __( 'Liste', 'like-json' ), value: 'liste' },
	{ label: __( 'Akkordeon', 'like-json' ), value: 'accordion' },
];

const RESSOURCES = [
	{ label: __( 'Link', 'like-json' ), value: 'link' },
	{ label: __( 'Base64 eingebettet', 'like-json' ), value: 'base64' },
];

/**
 * Block edit component.
 *
 * @param {Object}                                props               Block props.
 * @param {Object}                                props.attributes    Block attributes.
 * @param {(key: string, value: unknown) => void} props.setAttributes Set attributes callback.
 */
function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody
					title={ __( 'Einstellungen', 'like-json' ) }
					initialOpen
				>
					<SelectControl
						label={ __( 'Aufgabe', 'like-json' ) }
						value={ attributes.task }
						options={ TASKS }
						onChange={ ( value ) =>
							setAttributes( { task: value } )
						}
					/>
					<SelectControl
						label={ __( 'Darstellung', 'like-json' ) }
						value={ attributes.format }
						options={ FORMATS }
						onChange={ ( value ) =>
							setAttributes( { format: value } )
						}
					/>
					<SelectControl
						label={ __( 'PDF-Ressourcen', 'like-json' ) }
						value={ attributes.ressources }
						options={ RESSOURCES }
						onChange={ ( value ) =>
							setAttributes( { ressources: value } )
						}
					/>
					<TextControl
						label={ __( 'ID (optional)', 'like-json' ) }
						value={ attributes.id }
						onChange={ ( value ) => setAttributes( { id: value } ) }
					/>
					<TextControl
						label={ __( 'Betreuer (optional)', 'like-json' ) }
						value={ attributes.advisor }
						onChange={ ( value ) =>
							setAttributes( { advisor: value } )
						}
					/>
					<TextControl
						label={ __( 'Status (optional)', 'like-json' ) }
						value={ attributes.status }
						onChange={ ( value ) =>
							setAttributes( { status: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender
				block="like-json/studentische-arbeiten"
				attributes={ attributes }
			/>
		</div>
	);
}

registerBlockType( 'like-json/studentische-arbeiten', {
	edit: Edit,
	save: () => null, // Dynamic block, server-side rendered.
} );
