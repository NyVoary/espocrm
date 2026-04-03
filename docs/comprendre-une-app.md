# Comment comprendre une application open-source inconnue

Méthodologie générale — applicable à n'importe quel projet (PHP, Node, Python, Java…)

---

## Étape 1 — Le README (vue d'ensemble, 5 min)

C'est le point d'entrée obligatoire. Tu cherches :

| Question | Où le trouver |
|---|---|
| Quel problème résout l'app ? | Titre + description |
| Quelle stack technique ? | Section "Requirements" / "Prerequisites" |
| Comment l'installer ? | Section "Installation" |
| Quelles commandes existent ? | Section "Usage" / "Commands" |
| Y a-t-il une doc externe ? | Liens vers docs.xxx.com |

> **Dans EspoCRM** : le README indique PHP 8.3+, MySQL 8+, et donne 4 méthodes d'installation (manuelle, script, Docker, Traefik).

---

## Étape 2 — Les fichiers de dépendances (le "ADN" de l'app)

Ces fichiers te disent **ce que l'app fait tourner** sans lire une seule ligne de code métier.

### `package.json` (Node.js / frontend)
```
"scripts" → les commandes disponibles (npm run build, npm run dev...)
"dependencies" → librairies utilisées au runtime
"devDependencies" → librairies utilisées uniquement pour le build
"engines" → version Node/npm requise
```

### `composer.json` (PHP)
```
"require" → dépendances PHP runtime (framework, BDD, email...)
"require-dev" → dépendances dev uniquement (tests, analyse...)
"scripts" → commandes PHP disponibles
"require.php" → version PHP requise
```

### Exemples d'autres écosystèmes
| Fichier | Écosystème |
|---|---|
| `requirements.txt` / `pyproject.toml` | Python |
| `pom.xml` / `build.gradle` | Java |
| `go.mod` | Go |
| `Cargo.toml` | Rust |
| `Gemfile` | Ruby |

> **Dans EspoCRM** : `composer.json` révèle le framework Slim 4 (API REST), Doctrine DBAL (BDD),
> Ratchet (WebSocket), Symfony Mailer. `package.json` révèle Backbone.js, jQuery, Grunt (build).

---

## Étape 3 — Le `.gitignore` (ce qui est généré vs ce qui est source)

Le `.gitignore` te dit **ce qui n'est PAS dans le repo** et doit être généré/installé.
C'est crucial pour comprendre ce que tu dois faire avant de lancer l'app.

```
vendor/          → doit être généré par `composer install`
node_modules/    → doit être généré par `npm install`
client/lib/*     → doit être généré par le build frontend (grunt/webpack...)
data/config.php  → créé lors de l'installation initiale
build/           → artefact de packaging, pas nécessaire pour faire tourner l'app
```

> **Règle** : tout ce qui est dans `.gitignore` et qui n'est pas du contenu utilisateur
> (uploads, logs) doit être **regénéré** avant de lancer l'app.

---

## Étape 4 — La structure des dossiers (la carte du territoire)

Avant de lire du code, comprends **ce que chaque dossier contient**.
La convention varie par langage mais il y a des patterns universels :

| Dossier typique | Ce qu'il contient |
|---|---|
| `src/` ou `app/` ou `application/` | Code source métier (contrôleurs, modèles, services) |
| `public/` ou `web/` ou `static/` | Fichiers accessibles depuis le web (CSS, JS, index.php) |
| `vendor/` | Dépendances PHP (Composer) — NE PAS modifier |
| `node_modules/` | Dépendances JS (npm) — NE PAS modifier |
| `tests/` | Tests unitaires et d'intégration |
| `config/` | Fichiers de configuration de l'app |
| `data/` | Données runtime (uploads, cache, logs, config générée) |
| `docs/` | Documentation |
| `bin/` | Scripts CLI exécutables |
| `migrations/` | Scripts de migration de base de données |

> **Dans EspoCRM** :
> - `application/` → tout le code PHP métier (namespace `Espo\`)
> - `public/` → document root web (index.php de l'API, install wizard)
> - `client/` → frontend JS/CSS (Backbone.js SPA)
> - `data/` → config générée, cache, uploads (tout est dans `.gitignore`)
> - `custom/` → extensions utilisateur

---

## Étape 5 — Le point d'entrée (comment l'app démarre)

C'est le fichier que le serveur web appelle en premier. Il te montre comment l'app s'initialise.

| Contexte | Point d'entrée typique |
|---|---|
| PHP web | `public/index.php` ou `index.php` |
| Node.js | `index.js`, `server.js`, `app.js` |
| Python Flask/Django | `app.py`, `manage.py`, `wsgi.py` |
| Java Spring | `src/main/java/.../Application.java` |

Lis ce fichier pour comprendre :
- Quel framework est chargé ?
- Comment le routage est configuré ?
- Y a-t-il un conteneur DI (injection de dépendances) ?

---

## Étape 6 — Les fichiers de build (comment l'app est compilée)

Si l'app a un frontend ou une étape de compilation, il y a un **fichier de build** :

| Fichier | Outil |
|---|---|
| `Gruntfile.js` | Grunt (tâches JS classiques) |
| `webpack.config.js` | Webpack (bundler moderne) |
| `vite.config.js` / `vite.config.ts` | Vite (bundler rapide) |
| `Makefile` | Make (multi-langage) |
| `Dockerfile` | Docker |
| `.github/workflows/*.yml` | CI/CD GitHub Actions |

Lis la section des **scripts/tâches** pour comprendre :
- Comment générer les assets frontend ?
- Comment packager l'app pour la production ?
- Quelles étapes de build existent (dev, prod, test) ?

> **Dans EspoCRM** : `Gruntfile.js` compile LESS → CSS, bundle les JS (Rollup),
> copie les libs. La commande `grunt internal` construit uniquement les assets frontend en place.

---

## Étape 7 — La configuration (comment l'app se connecte au monde)

Cherche les fichiers `.env.example`, `config.example.php`, `config.yml.dist`…
Ils te montrent **toutes les variables configurables** de l'app :
- Connexion base de données
- Clés API
- URLs, ports
- Feature flags

> **Règle** : copie toujours le fichier `.example` en `.env` (ou équivalent) et remplis-le
> avant de lancer l'app.

---

## Résumé — Ordre de lecture recommandé

```
1. README.md                    → Vue d'ensemble (5 min)
2. package.json + composer.json → Stack technique (5 min)
3. .gitignore                   → Ce qui doit être généré (2 min)
4. Structure des dossiers       → Carte du code (5 min)
5. public/index.php (ou équiv.) → Point d'entrée (10 min)
6. Gruntfile.js / webpack...    → Build (5 min)
7. .env.example                 → Configuration (5 min)
```

**Temps total** : ~35 min pour avoir une bonne compréhension d'une app inconnue
avant de lire une seule ligne de code métier.
