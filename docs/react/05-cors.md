# CORS — REACT

## Qu'est-ce que le CORS ?

CORS = Cross-Origin Resource Sharing.

Le navigateur bloque les requêtes vers un domaine différent du frontend.

```txt
Frontend : http://localhost:5173
Backend  : http://localhost:8000
→ Origines différentes → CORS bloqué par défaut
```

**L'erreur typique dans la console :**

```txt
Access to XMLHttpRequest at 'http://localhost:8000/api/users/'
from origin 'http://localhost:5173' has been blocked by CORS policy:
No 'Access-Control-Allow-Origin' header is present on the requested resource.
```

---

## Solution 1 — Proxy Vite (recommandé en dev, sans toucher au backend)

Dans `vite.config.js` :

```js
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        // rewrite: (path) => path.replace(/^\/api/, '')  // si le backend n'a pas /api
      }
    }
  }
})
```

Dans `api.js`, utiliser `/api` sans domaine :

```js
const api = axios.create({
  baseURL: '/api',   // ← pas http://localhost:8000
})
```

---

## Solution 2 — Configurer CORS côté backend

### Django (django-cors-headers)

```bash
pip install django-cors-headers
```

`settings.py` :

```python
INSTALLED_APPS = [
    ...
    'corsheaders',
]

MIDDLEWARE = [
    'corsheaders.middleware.CorsMiddleware',  # ← en premier !
    ...
]

# Autoriser uniquement le frontend dev
CORS_ALLOWED_ORIGINS = [
    "http://localhost:5173",
    "http://localhost:3000",
]

# Ou tout autoriser (dev uniquement !)
# CORS_ALLOW_ALL_ORIGINS = True
```

### Express.js (Node)

```bash
npm install cors
```

```js
const cors = require('cors')
app.use(cors({ origin: 'http://localhost:5173' }))
// ou
app.use(cors()) // tout autoriser
```

### Laravel

`config/cors.php` :

```php
'allowed_origins' => ['http://localhost:5173'],
// ou
'allowed_origins' => ['*'],
```

---

## Solution 3 — Header dans axios (ne règle PAS le CORS)

> Ajouter des headers dans axios ne résout PAS le CORS.
> Le CORS doit être configuré **côté serveur** ou via un proxy.

---

## Erreurs CORS fréquentes

| Symptôme                                   | Cause                                   | Solution                                                                       |
|--------------------------------------------|-----------------------------------------|--------------------------------------------------------------------------------|
| `No 'Access-Control-Allow-Origin'`         | Backend ne renvoie pas le header        | Configurer CORS backend                                                        |
| `CORS preflight` bloqué                    | Requête OPTIONS refusée                 | Autoriser OPTIONS côté backend                                                 |
| `Credentials` bloqués                      | Cookie/auth non autorisé                | Ajouter `withCredentials: true` axios + `CORS_ALLOW_CREDENTIALS = True` Django |
| Fonctionne sur Postman mais pas navigateur | Normal : Postman ne vérifie pas le CORS | Configurer le backend                                                          |

---

## CORS avec authentification (token dans cookie)

**axios :**

```js
const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  withCredentials: true,  // ← envoyer les cookies
})
```

**Django :**

```python
CORS_ALLOW_CREDENTIALS = True
CORS_ALLOWED_ORIGINS = ["http://localhost:5173"]
# Ne pas utiliser CORS_ALLOW_ALL_ORIGINS avec credentials !
```

---

## Checklist CORS

- [ ] Le backend envoie bien `Access-Control-Allow-Origin`
- [ ] L'origine frontend est bien dans la liste autorisée
- [ ] Si proxy Vite → `baseURL: '/api'` dans axios (sans domaine)
- [ ] Si credentials → `withCredentials: true` dans axios ET `CORS_ALLOW_CREDENTIALS = True` backend
