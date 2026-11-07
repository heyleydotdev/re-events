import path from 'node:path';
import js from '@eslint/js';
import { defineConfig, includeIgnoreFile } from 'eslint/config';
import reactHooks from 'eslint-plugin-react-hooks';
import reactRefresh from 'eslint-plugin-react-refresh';
import globals from 'globals';
import tseslint from 'typescript-eslint';

export default defineConfig([
	includeIgnoreFile(path.join(import.meta.dirname, '.gitignore'), {
		gitignoreResolution: true,
	}),
	{
		files: ['**/*.{ts,tsx}'],
		extends: [js.configs.recommended, tseslint.configs.recommended],
		languageOptions: {
			parserOptions: {
				projectService: true,
				tsconfigRootDir: import.meta.dirname,
			},
		},
		rules: {
			'@typescript-eslint/no-unused-vars': [
				'error',
				{ argsIgnorePattern: '^_', varsIgnorePattern: '^_' },
			],
			'@typescript-eslint/consistent-type-imports': [
				'warn',
				{ prefer: 'type-imports', fixStyle: 'separate-type-imports' },
			],
			'@typescript-eslint/no-non-null-assertion': 'error',
			'@typescript-eslint/no-misused-promises': [
				'error',
				{ checksVoidReturn: { attributes: false } },
			],
			'@typescript-eslint/no-unnecessary-condition': [
				'error',
				{ allowConstantLoopConditions: true },
			],
		},
	},
	{
		files: ['assets/src/admin/**/*.{ts,tsx}'],
		extends: [reactHooks.configs.flat.recommended, reactRefresh.configs.vite],
		languageOptions: {
			globals: globals.browser,
		},
		rules: {
			'@typescript-eslint/array-type': ['error', { default: 'generic', readonly: 'generic' }],
			'@typescript-eslint/prefer-as-const': 'error',
			'react-refresh/only-export-components': [
				'error',
				{
					allowConstantExport: true,
					allowCompoundComponents: true,
					allowExportNames: ['buttonVariants', 'badgeVariants', 'useBootstrap'],
				},
			],
		},
	},
	{
		files: ['**/*.js'],
		extends: [js.configs.recommended],
		languageOptions: {
			globals: globals.node,
		},
	},
]);
