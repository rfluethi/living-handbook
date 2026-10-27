/**
 * Editor registration of the GitHub source note block.
 *
 * Metadata comes from blocks/git-source-note/block.json; only edit and save
 * live here. The block renders on the server, and only on pages that are kept
 * in a GitHub repository, so save() returns null.
 */

( function () {
	'use strict';

	const { blocks, blockEditor, element, components, i18n } = window.wp;
	const el = element.createElement;
	const { useBlockProps } = blockEditor;
	const { __ } = i18n;
	const defaultLabel = __(
		'This page is maintained on GitHub and updated automatically.',
		'living-handbook'
	);

	/**
	 * Renders the block in the editor: a field for the note text, styled like
	 * a WordPress info notice so it reads as a note rather than content.
	 *
	 * Named as a component, because it calls the useBlockProps() hook.
	 *
	 * @param {Object} props Block edit props.
	 * @return {Object} The element.
	 */
	function SourceNoteEdit( props ) {
		return el(
			'div',
			useBlockProps( {
				style: {
					padding: '.6em .9em',
					borderInlineStart: '4px solid #2271b1',
					background: '#f0f6fc',
				},
			} ),
			el( components.TextControl, {
				label: __(
					'Note text (shown only on GitHub pages)',
					'living-handbook'
				),
				value: props.attributes.label || '',
				placeholder: defaultLabel,
				onChange( next ) {
					props.setAttributes( { label: next } );
				},
			} )
		);
	}

	blocks.registerBlockType( 'living-handbook/git-source-note', {
		edit: SourceNoteEdit,
		save() {
			return null;
		},
	} );
} )();
