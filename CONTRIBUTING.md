# Guide de Contribution

Ce document décrit le workflow Git et les bonnes pratiques pour contribuer au projet.

## Objectifs
- Garder une histoire Git lisible et prévisible.
- Séparer clairement développement, travaux en cours et livrables finaux.
- Faciliter les revues de code et les intégrations continues.

## Branches
- `main`:
  - Réservée au livrable final et aux releases stables.
  - Pas de push direct. Fusion depuis `develop` uniquement quand le projet (ou une version) est prêt(e).
  - Recommandé: branche protégée côté GitHub (PR obligatoire, checks requis, pas de force-push).
- `develop`:
  - Branche par défaut pour l’intégration continue du développement.
  - Toutes les branches de travail partent de `develop` et sont fusionnées dans `develop` via PR.
  - Recommandé: branche protégée (PR + review + checks requis, pas de force-push).
- Branches de travail (depuis `develop`):
  - Existant: `diagrammes`, `charte-graphique`, `environnement-travail` (toutes basées sur `develop`).
  - Nommage recommandé: `feat/…`, `fix/…`, `docs/…`, `chore/…`, `refactor/…`, `test/…`, `ci/…`, `style/…`.
  - Exemples: `feat/us11-formulaire`, `docs/diagrammes`, `chore/charte-graphique`.

## Workflow standard
1. Synchroniser `develop`:
   ```bash
   git checkout develop
   git pull --ff-only
   ```
2. Créer une branche de travail depuis `develop`:
   ```bash
   git checkout -b feat/<nom-court>
   ```
3. Commiter avec un message explicite (Conventional Commits recommandé):
   - Exemples: `feat(frontend): page d’accueil US1`, `fix(api): corrige 500 sur POST /avis`.
4. Rebaser régulièrement sur `develop` (garder un historique linéaire):
   ```bash
   git fetch origin
   git rebase origin/develop
   ```
5. Pousser et définir l’upstream si nécessaire:
   ```bash
   git push -u origin feat/<nom-court>
   ```
6. Ouvrir une Pull Request vers `develop`:
   - Titre clair, description du contexte et des changements.
   - Lier l’issue/US si applicable.
   - Joindre captures/diagrammes si pertinent.
7. Revue & merge:
   - Tous les checks doivent passer (CI, tests, lint).
   - 1 review approuvée minimum.
   - Stratégie de merge recommandée: "Squash and merge" (historique propre). Alternativement "Rebase and merge" si convenu.

## Passage de `develop` vers `main` (release)
- Quand la version est prête et validée:
  1. S’assurer que `develop` est vert (tests/CI ok).
  2. Fusionner `develop` → `main` (fast-forward ou rebase+merge selon protections).
  3. Tagger la version (SemVer) et créer une release GitHub.

## Protections de branches (recommandé sur GitHub)
- `main`:
  - Interdire push direct et force-push.
  - PR obligatoire, 1+ review, checks requis.
  - Historique linéaire (no merge commit) conseillé.
- `develop`:
  - PR obligatoire, 1+ review, checks requis.
  - Interdire force-push.

## Messages de commit (Conventional Commits)
- Format: `type(scope): sujet`
- Types courants: `feat`, `fix`, `docs`, `chore`, `refactor`, `test`, `ci`, `style`.
- Exemples:
  - `feat(frontend): page d’accueil US1`
  - `fix(api): corrige 500 sur POST /avis`
  - `docs(diagrammes): ajoute séquence US9`

## Fichiers binaires et diagrammes
- Les images/diagrammes (PNG/PDF) peuvent être commité·es dans `diagrammes/`.
- Astuce: envisager Git LFS si les binaires deviennent nombreux/volumineux.

## Qualité, tests et style
- PHP: respecter PSR-12 autant que possible.
- Tests: si disponibles, les exécuter localement avant PR. Exemples (adapter selon votre setup):
  ```bash
  # Via Docker si présent
  docker compose run --rm php vendor/bin/phpunit
  # Ou localement
  vendor/bin/phpunit
  ```

## Check-list de Pull Request
- Base: `develop`.
- Rebase sur `origin/develop` effectué (pas de conflits).
- CI verte (tests/lint/build ok).
- Nom de branche et commits conformes.
- Description claire, issue liée si applicable.

## Divers
- Stash: utiliser pour mettre de côté des changements temporaires avant rebase/changement de branche; ne pas y laisser des travaux durables.
- Docs: si vous modifiez des décisions d’architecture, mettez à jour `README.md` ou ajoutez une note dans `docs/`.

Merci pour vos contributions !