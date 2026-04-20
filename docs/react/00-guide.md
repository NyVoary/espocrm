# GUIDE RAPIDE REACT — Référence Evaluation

## Ordre de travail

1. Setup projet → `01-setup.md`
2. Créer les services API → `02-services.md`
3. Faire le CRUD → `03-crud.md`
4. Import/Export fichiers → `04-fichiers.md`
5. Problèmes CORS → `05-cors.md`

---

## Checklist démarrage rapide

```bash
npm create vite@latest mon-projet -- --template react
cd mon-projet
npm install
npm install axios
npm run dev
```

## Structure projet (orienté service)

```txt
src/
├── services/
│   ├── api.js          ← instance axios globale
│   ├── userService.js  ← un service par entité
│   └── produitService.js
├── components/
│   ├── UserList.jsx
│   ├── UserForm.jsx
│   └── UserDetail.jsx
├── pages/
│   ├── UsersPage.jsx
│   └── ProduitsPage.jsx
└── App.jsx
```

## Commandes essentielles

```bash
npm run dev       # démarrer
npm install axios # ajouter axios
```

## URLs backend typiques

```txt
GET    /api/users/       → liste
POST   /api/users/       → créer
GET    /api/users/1/     → détail
PUT    /api/users/1/     → modifier complet
PATCH  /api/users/1/     → modifier partiel
DELETE /api/users/1/     → supprimer
```

## Rappel hooks utiles

```jsx
useState(valeurInitiale)    // state local
useEffect(() => {}, [])     // exécuter au montage
useEffect(() => {}, [dep])  // exécuter quand dep change
```
