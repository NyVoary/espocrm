# Troubleshooting — EspoCRM Docker

Problèmes rencontrés lors de la mise en place et leurs solutions.

---

## Problèmes résolus

### 1. `502 Bad Gateway` au démarrage

**Symptôme** : nginx répond 502 dès le lancement.

**Cause** : PHP-FPM essaie d'écrire ses logs dans `/proc/self/fd/2` (stderr), qui n'est pas accessible sur Docker Desktop Windows.

**Fix** : Dans le `Dockerfile`, on patche les fichiers de config PHP-FPM au moment du build :
```dockerfile
RUN for f in /usr/local/etc/php-fpm.conf /usr/local/etc/php-fpm.d/docker.conf; do \
        [ -f "$f" ] && sed -i 's|/proc/self/fd/2|/tmp/php-fpm.log|g' "$f" || true; \
    done
```

---

### 2. `Failed to open stream: vendor/autoload.php`

**Symptôme** : PHP retourne une fatal error sur `bootstrap.php:33`.

**Cause** : Le dossier `vendor/` existait sur l'hôte (créé vide ou partiellement) donc l'entrypoint sautait `composer install`, mais `autoload.php` était absent.

**Fix** : L'entrypoint vérifie maintenant `vendor/autoload.php` et non le dossier `vendor/` :
```sh
if [ ! -f "$WORKDIR/vendor/autoload.php" ]; then
    composer install ...
fi
```

**Fix manuel si ça se reproduit** :
```bash
# Supprimer le vendor incomplet puis relancer
Remove-Item -Recurse -Force D:\GitHub\espocrm\vendor
docker-compose restart app
```

---

### 3. `composer install` échoue — `git was not found`

**Symptôme** : Composer plante avec `git was not found in your PATH`.

**Cause** : Une dépendance de `composer.json` est une version `dev` qui se télécharge via git (`laminas/laminas-ldap: 2.20.x-dev`). `git` n'était pas installé dans l'image Alpine.

**Fix** : Ajouter `git` dans le `Dockerfile` :
```dockerfile
RUN apk add --no-cache \
    ...
    git \
    ...
```

---

### 4. `Permission denied` sur `data/logs/`

**Symptôme** : L'API retourne une page d'erreur PHP avec `file_put_contents(...data/logs/...): Permission denied`.

**Cause** : Le dossier `data/` est créé par root (entrypoint) après le démarrage, mais PHP-FPM tourne en `www-data`.

**Fix immédiat** (sans relancer les containers) :
```bash
docker-compose exec app chown -R www-data:www-data /var/www/html/data
```

**Fix permanent** : Dans l'entrypoint, le `chown data/` est maintenant exécuté **avant** le check `SKIP_INSTALL`, donc à chaque démarrage de n'importe quel container.

---

### 5. "Bad server response" — PHP servi en texte brut

**Symptôme** : La SPA affiche "Bad server response" et la console montre que la réponse est du code PHP source, pas du JSON.

**Cause** : Dans nginx, quand on forwardait les requêtes `/api/v1/` vers PHP-FPM sans spécifier `SCRIPT_NAME`, nginx envoyait l'URL complète (`/api/v1/Settings`) comme `SCRIPT_NAME`. Slim utilisait cette valeur pour calculer son base path et ne trouvait aucune route → 404 → nginx reservait le fichier en statique.

**Fix** : Forcer `SCRIPT_NAME` dans la config nginx :
```nginx
location ~ ^/api/v1/ {
    fastcgi_pass app:9000;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root/api/v1/index.php;
    fastcgi_param SCRIPT_NAME     /api/v1/index.php;
    ...
}
```

---

## Commandes utiles pour diagnostiquer

```bash
# Voir les logs de tous les services
docker-compose logs -f

# Logs d'un service spécifique
docker-compose logs -f app
docker-compose logs -f nginx

# Vérifier que les 4 containers tournent
docker-compose ps

# Vérifier les logs PHP-FPM
docker-compose exec app cat //tmp/php-fpm.log

# Tester que PHP s'exécute bien
# (crée un fichier test, appelle-le, puis supprime-le)
echo '<?php echo json_encode(["ok" => true]);' > public/test.php
Invoke-WebRequest -Uri "http://localhost/test.php" -UseBasicParsing
Remove-Item public/test.php

# Tester l'API avec credentials
$headers = @{ Authorization = "Basic " + [Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes("admin:MOTDEPASSE")) }
Invoke-WebRequest -Uri "http://localhost/api/v1/Settings" -Headers $headers -UseBasicParsing | Select-Object StatusCode

# Accéder au shell du container PHP
docker-compose exec app sh

# Vider le cache Slim si les routes semblent cassées
docker-compose exec app rm -f /var/www/html/data/cache/application/slim-routes.php

# Recharger la config nginx sans redémarrer
docker-compose exec nginx nginx -s reload

# Vérifier la config nginx
docker-compose exec nginx nginx -t
```

---

## Reset complet

Si tout est cassé et tu veux repartir de zéro :

```bash
# Arrêter et supprimer containers + volumes (PERD les données EspoCRM et la BDD)
docker-compose down -v

# Supprimer les dossiers générés sur l'hôte
Remove-Item -Recurse -Force D:\GitHub\espocrm\vendor
Remove-Item -Recurse -Force D:\GitHub\espocrm\node_modules

# Repartir proprement
docker-compose up --build
```

Puis relancer l'installeur sur **http://localhost/install/**.
