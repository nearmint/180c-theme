import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import path from 'node:path';
import fs from 'node:fs';

/**
 * Plugin « hot file » — pont entre le serveur de dev Vite et PHP.
 *
 * En `vite` (dev), on écrit `dist/hot` contenant l'origine du serveur de dev.
 * `inc/enqueue.php` détecte ce fichier : s'il existe, le thème charge les assets
 * depuis le serveur de dev (HMR), sinon il lit `dist/.vite/manifest.json` (prod).
 * Le fichier est supprimé à l'arrêt du serveur pour retomber proprement en prod.
 *
 * `apply: 'serve'` garantit que ce plugin ne tourne jamais pendant `vite build`.
 */
function hotFile() {
  const hotPath = path.resolve(__dirname, 'dist/hot');
  const clean = () => {
    try {
      fs.rmSync(hotPath, { force: true });
    } catch {
      // dist/hot déjà absent : rien à faire.
    }
  };

  return {
    name: '180c-hot-file',
    apply: 'serve',
    configureServer(server) {
      const write = () => {
        const origin =
          server.config.server.origin ||
          `http://localhost:${server.config.server.port ?? 5173}`;
        fs.mkdirSync(path.dirname(hotPath), { recursive: true });
        fs.writeFileSync(hotPath, origin);
      };

      if (server.httpServer) {
        server.httpServer.once('listening', write);
      } else {
        write();
      }

      server.httpServer?.once('close', clean);
      process.once('exit', clean);
      process.once('SIGINT', () => {
        clean();
        process.exit();
      });
      process.once('SIGTERM', () => {
        clean();
        process.exit();
      });
    },
  };
}

export default defineConfig({
  // WordPress theme : la base URL absolue dépend de l'install (varie selon dev,
  // prod, multisite). En mettant base="" Vite émet des URLs relatives dans le
  // CSS bundlé (ex. url(./assets/Oswald-...ttf)) qui se résolvent correctement
  // depuis la position du fichier CSS lui-même. Sans ça, Vite générait des
  // /assets/... absolus → 404 (les fonts ne se chargeaient jamais).
  base: '',
  plugins: [tailwindcss(), hotFile()],
  build: {
    manifest: true,
    outDir: 'dist',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        main: path.resolve(__dirname, 'src/js/main.js'),
        // Entrée CSS pure : bundle « commerce » chargé conditionnellement
        // (boutique, fiche produit, panier, commande, Mon Compte). Le sortir
        // de main.css allège d'autant la seule feuille render-blocking du
        // thème. Voir l'en-tête de src/css/commerce.css.
        commerce: path.resolve(__dirname, 'src/css/commerce.css'),
        admin: path.resolve(__dirname, 'src/js/admin/index.js'),
        'design-system': path.resolve(__dirname, 'src/js/design-system.js'),
      },
      output: {
        assetFileNames: 'assets/[name]-[hash][extname]',
        chunkFileNames: 'assets/[name]-[hash].js',
        entryFileNames: 'assets/[name]-[hash].js',
      },
    },
  },
  server: {
    port: 5173,
    strictPort: true,
    origin: 'http://localhost:5173',
    // CORS : autorise le site WordPress (Local) à charger les modules depuis le
    // serveur de dev Vite (origines différentes).
    cors: true,
  },
});
