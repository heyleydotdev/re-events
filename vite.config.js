import { relative } from 'node:path';
import { fileURLToPath } from 'node:url';
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

export default defineConfig({
	base: './',
	server: {
		host: '127.0.0.1',
		port: 5173,
		strictPort: true,
		origin: 'http://127.0.0.1:5173',
	},
	plugins: [phpFullReload()],
	build: {
		outDir: 'assets/build',
		emptyOutDir: true,
		manifest: true,
		rollupOptions: {
			input: {
				admin: fileURLToPath(new URL('assets/src/admin/index.ts', import.meta.url)),
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
