# Docker Setup — EspoCRM

Documentation de la dockerisation du projet. Explique chaque choix.

---

## Architecture globale

```
┌─────────────────────────────────────────────────────┐
│                   Machine hôte :80                  │
└───────────────────────┬─────────────────────────────┘
                        │ HTTP
                        ▼
              ┌─────────────────┐
              │  nginx          │  Sert les fichiers statiques
              │  (port 80)      │  Proxy PHP vers app:9000
              └────────┬────────┘
                       │ FastCGI (port 9000)
                       ▼
              ┌─────────────────┐
              │  app            │  PHP-FPM 8.3
              │  (php-fpm)      │  Exécute le code PHP
              └────────┬────────┘
                       │ MySQL (port 3306)
                       ▼
              ┌─────────────────┐
              │  db             │  MySQL 8.0
              │  (MySQL)        │  Stockage persistant
              └─────────────────┘

              ┌─────────────────┐
              │  cron           │  Même image que app
              │                 │  Exécute cron.php chaque minute
              └─────────────────┘
```

---

## Fichiers créés et leur rôle

### `Dockerfile`

**Rôle** : Définit l'image PHP-FPM qui fait tourner EspoCRM.

**Pourquoi PHP 8.3-FPM Alpine ?**
- `php:8.3-fpm` : version requise par EspoCRM (composer.json : `>=8.3.0 <8.6.0`)
- `fpm` : PHP-FPM est le mode standard pour PHP derrière nginx (plus performant qu'Apache mod_php)
- `alpine` : image de base ultra-légère (~5 Mo vs ~150 Mo pour debian)

**Pourquoi Node.js dans la même image ?**
- EspoCRM a un build frontend (Grunt → compile LESS en CSS, bundle le JS)
- `client/lib/` et `client/css/` sont dans `.gitignore` → doivent être générés
- Plutôt qu'une image séparée, Node est inclus pour simplifier l'entrypoint

**Extensions PHP installées et pourquoi :**
| Extension | Raison |
|---|---|
| `pdo_mysql` | Connexion à MySQL (requis par composer.json) |
| `zip` | Import/export d'extensions EspoCRM |
| `gd` | Manipulation d'images (avatars, captcha) |
| `mbstring` | Chaînes multi-octets (UTF-8, internationalisation) |
| `xml` + `dom` | Parsing XML (imports, emails) |
| `exif` | Lecture des métadonnées d'images uploadées |
| `opcache` | Cache de bytecode PHP → performances x2-5 |
| `intl` | Internationalisation (dates, nombres, langues) |
| `bcmath` | Calculs financiers précis |
| `pcntl` + `posix` | Gestion des processus (daemon, jobs en parallèle) |

---

### `docker-compose.yml`

**Rôle** : Orchestre les 4 services et leurs connexions.

#### Service `nginx`
```yaml
image: nginx:1.25-alpine        # Pas besoin de builder une image custom
ports: "80:80"                  # Exposé sur la machine hôte
volumes:
  - .:/var/www/html:ro          # Lit les fichiers du projet (read-only = sécurité)
  - ./docker/nginx/default.conf # Config nginx personnalisée
```
> **Pourquoi nginx et pas Apache ?**
> Apache est supporté mais nginx est plus performant pour servir des fichiers statiques
> et consomme moins de mémoire. EspoCRM recommande nginx en production.

#### Service `app`
```yaml
build: .                        # Construit depuis le Dockerfile local
volumes:
  - .:/var/www/html             # Bind mount : le code source de la machine hôte
  - espo_data:/var/www/html/data  # Volume nommé persistant pour les données
```
> **Pourquoi un bind mount (`.:/var/www/html`) et pas COPY dans le Dockerfile ?**
> Un bind mount monte directement le dossier de la machine dans le container.
> Avantage : modifier le code source → l'app voit les changements sans rebuild.
> Le volume `espo_data` prend le dessus sur `data/` du bind mount → données persistantes.

#### Service `cron`
```yaml
command: ["sh", "-c", "while true; do php cron.php; sleep 60; done"]
environment:
  SKIP_INSTALL: "true"          # Ne relance pas composer install au démarrage
```
> **Pourquoi `while true; sleep 60` et pas `crond` ?**
> Plus simple dans Docker. `crond` d'Alpine nécessite une configuration supplémentaire.
> EspoCRM demande juste que `cron.php` soit exécuté toutes les minutes.
>
> **Pourquoi `SKIP_INSTALL=true` ?**
> `app` et `cron` partagent le même bind mount `.:/var/www/html`.
> Quand `cron` démarre, `vendor/` existe déjà (créé par `app`).
> Sans ce flag, les deux lanceraient `composer install` en parallèle → conflit.

#### Service `db`
```yaml
image: mysql:8.0                # Version requise par EspoCRM
healthcheck:                    # Attend que MySQL soit prêt avant de démarrer app
  test: mysqladmin ping
volumes:
  - db_data:/var/lib/mysql      # Données persistantes entre restarts
```
> **Pourquoi `condition: service_healthy` dans `app.depends_on.db` ?**
> MySQL prend quelques secondes à démarrer. Sans healthcheck, PHP essaierait de se connecter
> avant que MySQL soit prêt → erreur de connexion au lancement.

---

### `docker/nginx/default.conf`

**Rôle** : Traduit les règles `.htaccess` Apache d'EspoCRM en config nginx.

**Points clés :**

```nginx
root /var/www/html/public;
```
> EspoCRM recommande `public/` comme document root (sécurité : `application/`, `vendor/`
> ne sont pas accessibles depuis le web).

```nginx
location /client {
    alias /var/www/html/client;
}
```
> `client/` (JS/CSS) est EN DEHORS du document root `public/`.
> L'alias nginx permet d'y accéder via `/client/...` dans le navigateur.
> C'est l'équivalent de `Alias /client/ /var/www/html/client/` en Apache.

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```
> Routes toutes les URLs inconnues vers `public/index.php` (le router d'EspoCRM).
> C'est l'équivalent du `RewriteRule` dans `.htaccess`.

```nginx
fastcgi_param HTTP_ESPO_CGI_AUTH $http_authorization;
```
> EspoCRM utilise l'header `Authorization` pour l'API REST.
> Certaines configs PHP-FPM ne transmettent pas cet header → on le passe manuellement.

---

### `docker/entrypoint.sh`

**Rôle** : Script exécuté au démarrage du container `app` pour préparer l'environnement.

**Flux d'exécution :**
```
1. Copie php.ini dans le bon dossier PHP
2. Si SKIP_INSTALL=true → démarre directement (pour le service cron)
3. Si vendor/ absent → composer install (installe les dépendances PHP)
4. Si client/lib/ absent → npm ci + npm run build-frontend (compile JS/CSS)
5. Crée les dossiers data/ nécessaires
6. Fixe les permissions (www-data = user PHP-FPM)
7. Lance php-fpm avec su-exec (pour éviter de tourner en root)
```

> **Pourquoi `su-exec` et pas `sudo` ?**
> `sudo` n'est pas installé dans Alpine par défaut. `su-exec` est l'équivalent léger
> pour Alpine : change l'utilisateur courant sans fork de processus supplémentaire.

> **Pourquoi vérifier si `vendor/` existe avant `composer install` ?**
> Les deux containers (`app` et nginx) montent le même bind mount.
> Si tu arrêtes et relances les containers, `vendor/` est déjà là → pas besoin de réinstaller.

---

### `docker/php/php.ini`

**Rôle** : Surcharge les valeurs PHP par défaut pour EspoCRM.

| Paramètre | Valeur | Raison |
|---|---|---|
| `upload_max_filesize` | 50M | EspoCRM permet d'uploader des fichiers (pièces jointes) |
| `post_max_size` | 50M | Doit être ≥ upload_max_filesize |
| `memory_limit` | 256M | PHP par défaut = 128M, insuffisant pour les imports/exports |
| `max_execution_time` | 180s | Les imports de données peuvent être lents |
| `opcache.*` | activé | Cache le bytecode PHP compilé → performances |

---

### `.env.example` → `.env`

**Rôle** : Variables d'environnement injectées dans les containers au démarrage.

```env
HTTP_PORT=80          → Port exposé sur la machine hôte (changer si 80 est pris)
DB_NAME=espocrm       → Nom de la base MySQL (utilisé par docker-compose ET l'installeur)
DB_USER=espocrm       → User MySQL
DB_PASSWORD=...       → Mot de passe MySQL
DB_ROOT_PASSWORD=...  → Mot de passe root MySQL (pour le healthcheck)
```

> **Important** : Ces valeurs sont utilisées automatiquement par docker-compose pour créer
> la base de données MySQL. Il faut entrer les **mêmes valeurs** dans l'installeur web
> (`http://localhost/install/`) quand il demande les credentials de base de données.

---

### `.dockerignore`

**Rôle** : Indique à Docker quels fichiers NE PAS copier dans le contexte de build.

```
node_modules/    → Plusieurs centaines de Mo, régénérés dans le container
vendor/          → Idem
build/           → Artefact de packaging, inutile pour faire tourner l'app
data/logs...     → Données runtime, ne doivent pas être dans l'image
.git/            → Historique git, inutile dans l'image (réduit la taille)
tests/           → Ne font pas partie de l'image de production
```

> Sans `.dockerignore`, Docker copierait **tout** le projet dans le contexte de build,
> y compris `node_modules/` (potentiellement 500 Mo+), ce qui ralentirait énormément
> le `docker build`.

---

## Volumes Docker — ce qui persiste

```
espo_data → /var/www/html/data/
```
Contient : config.php (créée par l'installeur), cache, uploads, logs.
**Si tu supprimes ce volume, tu perds la configuration EspoCRM.**

```
db_data → /var/lib/mysql
```
Contient : toute la base de données MySQL.
**Si tu supprimes ce volume, tu perds toutes les données.**

---

## Commandes utiles

```bash
# Premier démarrage (build + lancement)
docker-compose up --build

# Démarrage normal (sans rebuild)
docker-compose up -d

# Voir les logs en temps réel
docker-compose logs -f

# Voir les logs d'un service spécifique
docker-compose logs -f app

# Accéder au shell du container PHP
docker-compose exec app sh

# Accéder à MySQL
docker-compose exec db mysql -u espocrm -pespocrm_pass espocrm

# Arrêter les containers (données préservées)
docker-compose down

# Arrêter ET supprimer les volumes (RESET COMPLET)
docker-compose down -v
```
