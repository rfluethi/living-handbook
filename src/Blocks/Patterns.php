<?php
/**
 * Start patterns for new handbook pages, one per page type (E-0080).
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Blocks;

use LivingHandbook\PostType\Handbook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seven block patterns, the outline of each page type the plugin seeds.
 *
 * Each is registered for the handbook post type with core/post-content in its
 * block types, which makes it a start pattern: the block editor offers them
 * when a new, empty handbook page is opened. Choosing one fills the page; it
 * does not set the page type, which stays the field in the sidebar, so there
 * is one place that decides the type.
 *
 * They hold the outline and nothing else: headings, collapsible sections with
 * a title of the form "Category: what it is about", and empty paragraphs and
 * list items whose placeholder says what goes there. No sample text, so an
 * unfilled section publishes as nothing rather than as a sentence nobody
 * meant. How to write the page is the business of the writing guide, not of
 * the pattern.
 *
 * The markup is built with core's own serializer, so the attributes are
 * encoded as the editor writes them and the blocks open without the "invalid
 * content" warning; PatternsTest holds the markup against the block parser.
 */
final class Patterns {

	/**
	 * Pattern category.
	 */
	public const CATEGORY = 'living-handbook';

	/**
	 * Registers the patterns on init.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( self::class, 'register_patterns' ) );
	}

	/**
	 * Registers the category and the seven patterns.
	 *
	 * @return void
	 */
	public static function register_patterns(): void {
		if ( ! function_exists( 'register_block_pattern' ) ) {
			return;
		}

		register_block_pattern_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Living Handbook', 'living-handbook' ),
				'description' => __( 'Outlines for handbook pages, one per page type.', 'living-handbook' ),
			)
		);

		foreach ( self::all() as $slug => $pattern ) {
			register_block_pattern(
				self::CATEGORY . '/' . $slug,
				array(
					'title'       => $pattern['title'],
					'description' => $pattern['description'],
					'content'     => $pattern['content'],
					'categories'  => array( self::CATEGORY ),
					'keywords'    => array( 'handbook', 'living handbook' ),
					'blockTypes'  => array( 'core/post-content' ),
					'postTypes'   => array( Handbook::POST_TYPE ),
				)
			);
		}
	}

	/**
	 * The seven patterns, slug to title, description and markup.
	 *
	 * @return array<string, array{title:string, description:string, content:string}>
	 */
	public static function all(): array {
		$related = self::heading( __( 'Related pages', 'living-handbook' ) )
			. self::bullets( array( __( 'Link to a related page', 'living-handbook' ) ) );

		return array(
			'guide'         => array(
				'title'       => __( 'Guide', 'living-handbook' ),
				'description' => __( 'A task, step by step: steps and result in view, concept, prerequisites and pitfalls folded away.', 'living-handbook' ),
				'content'     => self::paragraph( __( 'What this guide achieves, and for whom, in one to three sentences.', 'living-handbook' ) )
					. self::details( __( 'Concept: what it is about', 'living-handbook' ), __( 'How the task fits into the whole. Leave out if not needed.', 'living-handbook' ) )
					. self::details( __( 'Prerequisites: what you need', 'living-handbook' ), __( 'What must be done first, which access is needed.', 'living-handbook' ) )
					. self::heading( __( 'Steps', 'living-handbook' ) )
					. self::steps( array( __( 'First step, as an instruction', 'living-handbook' ), __( 'Next step', 'living-handbook' ), __( 'Next step', 'living-handbook' ) ) )
					. self::heading( __( 'Result', 'living-handbook' ) )
					. self::paragraph( __( 'How you can tell the task is done.', 'living-handbook' ) )
					. self::details( __( 'Pitfalls: what it is about', 'living-handbook' ), __( 'What goes wrong, and what newcomers should know.', 'living-handbook' ) )
					. $related,
			),
			'process'       => array(
				'title'       => __( 'Process description', 'living-handbook' ),
				'description' => __( 'A process across several roles: trigger, roles, sequence and result.', 'living-handbook' ),
				'content'     => self::paragraph( __( 'Which process this is, when it starts and what it leads to.', 'living-handbook' ) )
					. self::heading( __( 'Trigger', 'living-handbook' ) )
					. self::paragraph( __( 'What starts this process.', 'living-handbook' ) )
					. self::heading( __( 'Roles involved', 'living-handbook' ) )
					. self::bullets( array( __( 'Role: its task in this process', 'living-handbook' ) ) )
					. self::heading( __( 'Sequence', 'living-handbook' ) )
					. self::steps( array( __( 'Step: which role does what', 'living-handbook' ), __( 'Step: which role does what', 'living-handbook' ) ) )
					. self::heading( __( 'Result', 'living-handbook' ) )
					. self::paragraph( __( 'What exists at the end.', 'living-handbook' ) )
					. self::details( __( 'Background: why the process works this way', 'living-handbook' ), __( 'The reasons behind the sequence. Leave out if not needed.', 'living-handbook' ) )
					. $related,
			),
			'tool'          => array(
				'title'       => __( 'Tool overview', 'living-handbook' ),
				'description' => __( 'One tool as the team uses it: who, access, conventions and documentation.', 'living-handbook' ),
				'content'     => self::paragraph( __( 'What the team uses this tool for.', 'living-handbook' ) )
					. self::details( __( 'Background: what the tool is', 'living-handbook' ), __( 'The tool in one sentence, and where you meet it day to day.', 'living-handbook' ) )
					. self::heading( __( 'Who uses it', 'living-handbook' ) )
					. self::paragraph( __( 'Which roles need access.', 'living-handbook' ) )
					. self::heading( _x( 'Access', 'heading in the tool overview pattern: how to get access to the tool', 'living-handbook' ) )
					. self::paragraph( __( 'How to get access, and which role grants it.', 'living-handbook' ) )
					. self::heading( __( 'Our conventions', 'living-handbook' ) )
					. self::bullets( array( __( 'Naming, structure, labels', 'living-handbook' ), __( 'What is allowed and what is not', 'living-handbook' ) ) )
					. self::heading( __( 'Official documentation', 'living-handbook' ) )
					. self::bullets( array( __( 'Link to the vendor documentation', 'living-handbook' ) ) )
					. self::heading( __( 'Guides for this tool', 'living-handbook' ) )
					. self::bullets( array( __( 'Link to a guide in this handbook', 'living-handbook' ) ) ),
			),
			'role'          => array(
				'title'       => __( 'Role / Organisation', 'living-handbook' ),
				'description' => __( 'A role or a unit: responsibilities, authority and interfaces.', 'living-handbook' ),
				'content'     => self::paragraph( __( 'What this role covers, in one or two sentences.', 'living-handbook' ) )
					. self::details( __( 'Background: where you meet this role', 'living-handbook' ), __( 'How this role shows up in daily work.', 'living-handbook' ) )
					. self::heading( __( 'Responsibilities', 'living-handbook' ) )
					. self::bullets( array( __( 'A responsibility', 'living-handbook' ) ) )
					. self::heading( __( 'Authority', 'living-handbook' ) )
					. self::bullets( array( __( 'What this role may decide', 'living-handbook' ) ) )
					. self::heading( __( 'Interfaces', 'living-handbook' ) )
					. self::bullets( array( __( 'Works with: other roles', 'living-handbook' ), __( 'Reports to: a role', 'living-handbook' ) ) ),
			),
			'concept'       => array(
				'title'       => __( 'Background / Concept', 'living-handbook' ),
				'description' => __( 'An explanation: what it is about, why it is done this way, and what that means for the work.', 'living-handbook' ),
				'content'     => self::paragraph( __( 'Which question this page answers.', 'living-handbook' ) )
					. self::heading( __( 'What it is about', 'living-handbook' ) )
					. self::paragraph( __( 'The explanation.', 'living-handbook' ) )
					. self::heading( __( 'Why we do it this way', 'living-handbook' ) )
					. self::paragraph( __( 'The reasons.', 'living-handbook' ) )
					. self::heading( __( 'What it means for our work', 'living-handbook' ) )
					. self::paragraph( __( 'The concrete consequences.', 'living-handbook' ) )
					. $related,
			),
			'faq'           => array(
				'title'       => __( 'FAQ', 'living-handbook' ),
				'description' => __( 'Frequent questions, each folded away, each answer pointing to the page that holds the details.', 'living-handbook' ),
				'content'     => self::paragraph( __( 'Frequent questions about this area. Each answer points to the page that holds the details.', 'living-handbook' ) )
					. self::details( __( 'A question, in the words people ask it', 'living-handbook' ), __( 'A short answer in one to three sentences, and a link to the page with the details.', 'living-handbook' ) )
					. self::details( __( 'Another question', 'living-handbook' ), __( 'A short answer, and a link.', 'living-handbook' ) )
					. $related,
			),
			'area-overview' => array(
				'title'       => __( 'Area overview', 'living-handbook' ),
				'description' => __( 'The start of an area: what it covers and which page answers what.', 'living-handbook' ),
				'content'     => self::paragraph( __( 'What this area covers, and for whom.', 'living-handbook' ) )
					. self::bullets( array( __( 'Link to a page: what you know or can do afterwards', 'living-handbook' ), __( 'Link to a page: what you know or can do afterwards', 'living-handbook' ) ) ),
			),
		);
	}

	/**
	 * A level-two heading.
	 *
	 * @param string $text Heading.
	 * @return string
	 */
	private static function heading( string $text ): string {
		return get_comment_delimited_block_content( 'heading', array(), '<h2 class="wp-block-heading">' . esc_html( $text ) . '</h2>' ) . "\n\n";
	}

	/**
	 * An empty paragraph with a placeholder.
	 *
	 * @param string $placeholder What goes there.
	 * @return string
	 */
	private static function paragraph( string $placeholder ): string {
		return get_comment_delimited_block_content( 'paragraph', array( 'placeholder' => $placeholder ), '<p></p>' ) . "\n\n";
	}

	/**
	 * A collapsible section, closed, with one empty paragraph.
	 *
	 * @param string $summary     Its title.
	 * @param string $placeholder What goes inside.
	 * @return string
	 */
	private static function details( string $summary, string $placeholder ): string {
		$inner = '<details class="wp-block-details"><summary>' . esc_html( $summary ) . '</summary>'
			. get_comment_delimited_block_content( 'paragraph', array( 'placeholder' => $placeholder ), '<p></p>' )
			. '</details>';

		return get_comment_delimited_block_content( 'details', array(), $inner ) . "\n\n";
	}

	/**
	 * A numbered list of empty items.
	 *
	 * @param array<int, string> $placeholders One per item.
	 * @return string
	 */
	private static function steps( array $placeholders ): string {
		return self::list_block( $placeholders, true );
	}

	/**
	 * A bulleted list of empty items.
	 *
	 * @param array<int, string> $placeholders One per item.
	 * @return string
	 */
	private static function bullets( array $placeholders ): string {
		return self::list_block( $placeholders, false );
	}

	/**
	 * A list of empty items with placeholders.
	 *
	 * @param array<int, string> $placeholders One per item.
	 * @param bool               $ordered      Numbered or not.
	 * @return string
	 */
	private static function list_block( array $placeholders, bool $ordered ): string {
		$items = '';
		foreach ( $placeholders as $placeholder ) {
			$items .= get_comment_delimited_block_content( 'list-item', array( 'placeholder' => $placeholder ), '<li></li>' );
		}
		$tag = $ordered ? 'ol' : 'ul';

		return get_comment_delimited_block_content(
			'list',
			$ordered ? array( 'ordered' => true ) : array(),
			'<' . $tag . ' class="wp-block-list">' . $items . '</' . $tag . '>'
		) . "\n\n";
	}
}
