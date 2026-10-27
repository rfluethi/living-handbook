/**
 * Stylelint configuration: the WordPress CSS coding standards.
 *
 * Two deliberate exceptions.
 *
 * The plugin names its classes in BEM style, block__element and
 * block--modifier (living-handbook-card__title, living-handbook-toc--mobile),
 * the same convention the block editor itself uses for its components. The WordPress preset only allows lowercase words
 * joined by single hyphens, so the pattern is widened to admit the two BEM
 * separators and nothing else: still lowercase, still no camelCase or
 * underscores of other kinds.
 *
 * no-descending-specificity is off. It compares selectors by the element they
 * end in, so `.living-handbook-toc__list a` is reported against
 * `.living-handbook-nav__top > a:hover` although the two never match the same
 * link. Within one component the stylesheet orders base rules before state
 * rules on purpose, and the shared focus rule comes last so that it wins (see
 * the comment there); moving rules to please this check would break both.
 */
export default {
	extends: [ '@wordpress/stylelint-config' ],
	ignoreFiles: [ 'node_modules/**', 'vendor/**' ],
	rules: {
		'no-descending-specificity': null,
		'selector-class-pattern': [
			'^[a-z][a-z0-9]*(-[a-z0-9]+)*(__[a-z0-9]+(-[a-z0-9]+)*)?(--[a-z0-9]+(-[a-z0-9]+)*)?$',
			{
				message:
					'Use lowercase words joined by hyphens, with BEM __element and --modifier parts (selector-class-pattern)',
			},
		],
	},
};
