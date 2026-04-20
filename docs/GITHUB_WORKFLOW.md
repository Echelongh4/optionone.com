# GitHub Workflow

## First-Time Setup

```powershell
git init
git branch -M main
git remote add origin <your-github-repo-url>
git add .
git commit -m "Initial commit"
git push -u origin main
```

## Normal Daily Workflow

```powershell
git status
git add .
git commit -m "Describe your change"
git push
```

## Recommended Rules

- Commit small focused changes.
- Push after each working feature or fix.
- Keep `.env` out of Git.
- Keep runtime files in `storage/` out of Git.
- Run `composer install` locally after pulling if dependencies changed.
