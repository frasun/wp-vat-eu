/* eslint-disable import/no-extraneous-dependencies */
import globals from 'globals';
import wordpress from '@wordpress/eslint-plugin';

export default [
	...wordpress.configs.recommended,
	{
		languageOptions: {
			globals: {
				...globals.jquery,
				...globals.browser,
			},
		},
	},
];
