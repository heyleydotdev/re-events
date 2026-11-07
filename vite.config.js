import { relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import babel from '@rolldown/plugin-babel';
import { defineConfig } from 'vite';

const phpFullReload = () => ({
	name: 'php-full-reload',
	configureServer(server) {
		const root = fileURLToPath(new URL('.', import.meta.url));

		// Watch PHP even though it is outside Vite's module graph.
		server.watcher.add([`${root}src`, `${root}re-events.php`, `${root}uninstall.php`]);

		const reload = (file) => {
			const source = relative(root, file);

			if (
				!file.endsWith('.php') ||
				(!source.startsWith('src') && source !== 're-events.php' && source !== 'uninstall.php')
			) {
				return;
			}

			console.log(`[re-events] PHP changed: ${file}`);
			server.ws.send({ type: 'full-reload' });
		};

		server.watcher.on('change', reload);
		server.watcher.on('add', reload);
		server.watcher.on('unlink', reload);

		console.log('[re-events] PHP full-reload watcher ready');
	},
});

const selfAcceptingHmr = () => ({
	name: 'self-accepting-hmr',
	hotUpdate(ctx) {
		// Tailwind's @source makes Vite treat every TSX file as a CSS dependency
		// too, and that second module is not self-accepting. Ignore it so React
		// Fast Refresh handles the update instead of a full reload.
		const modules = ctx.modules.filter((module) => module.isSelfAccepting);

		return modules.length > 0 ? modules : undefined;
	},
});

export default defineConfig({
	base: './',
	resolve: {
		alias: {
			'@': fileURLToPath(new URL('assets/src/admin', import.meta.url)),
		},
	},
	server: {
		host: '127.0.0.1',
		port: 5173,
		strictPort: true,
		origin: 'http://127.0.0.1:5173',
	},
	plugins: [
		react(),
		babel({
			presets: [reactCompilerPreset()],
		}),
		selfAcceptingHmr(),
		phpFullReload(),
	],
	build: {
		outDir: 'assets/build',
		emptyOutDir: true,
		manifest: true,
		rolldownOptions: {
			input: {
				admin: fileURLToPath(new URL('assets/src/admin/index.tsx', import.meta.url)),
				'admin-css': fileURLToPath(new URL('assets/src/admin/app.css', import.meta.url)),
				'admin-fonts': fileURLToPath(
					new URL('node_modules/@fontsource-variable/geist/wght.css', import.meta.url),
				),
			},
			output: {
				format: 'es',
				entryFileNames: 'assets/[name]-[hash].js',
				chunkFileNames: 'assets/[name]-[hash].js',
				assetFileNames: 'assets/[name]-[hash][extname]',
			},
		},
	},
});
