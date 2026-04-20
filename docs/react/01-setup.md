# SETUP REACT — Vite + Axios

## 1. Créer le projet

```bash
npm create vite@latest mon-projet -- --template react
cd mon-projet
npm install
```

## 2. Installer les dépendances

```bash
npm install axios
```

> Pour lire des fichiers Excel :
>
> ```bash
> npm install xlsx
> ```

## 3. Lancer le projet

```bash
npm run dev
# → http://localhost:5173
```

---

## 4. Structure des dossiers (orienté service)

Créer manuellement cette structure dans `src/` :

```txt
src/
├── services/
│   ├── api.js
│   └── userService.js
├── components/
│   └── UserList.jsx
├── pages/
│   └── UsersPage.jsx
└── App.jsx
```

```bash
mkdir src/services src/components src/pages
```

---

## 5. Configurer le proxy Vite (éviter CORS en dev)

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
      }
    }
  }
})
```

> Maintenant `/api/users/` pointe vers `http://localhost:8000/api/users/`

---

## 6. App.jsx minimal

```jsx
import { BrowserRouter, Routes, Route } from 'react-router-dom'
import UsersPage from './pages/UsersPage'

// npm install react-router-dom  ← si tu utilises le routing

function App() {
  return (
    <div>
      <h1>Mon Application</h1>
      <UsersPage />
    </div>
  )
}

export default App
```

---

## 7. main.jsx (ne pas modifier, juste vérifier)

```jsx
import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './App.jsx'
import './index.css'

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>
)
```

---

## Récap erreurs fréquentes au setup

| Erreur                    | Solution                                                      |
|---------------------------|---------------------------------------------------------------|
| `Module not found: axios` | `npm install axios`                                           |
| Port déjà utilisé         | Changer port dans `vite.config.js` : `server: { port: 3000 }` |
| Blank page                | Vérifier `index.html` → `<div id="root">` présent             |
| CORS error                | Voir `05-cors.md` ou configurer le proxy ci-dessus            |
