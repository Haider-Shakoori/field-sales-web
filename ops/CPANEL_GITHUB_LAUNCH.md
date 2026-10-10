# FieldPulse: GitHub Actions launch on existing cPanel

The production application remains on **cPanel** at
`/home/businessos/fieldpulse.businessos.af`, served by
`https://fieldpulse.businessos.af`. **Do not migrate to the VPS.**

## GitHub-only code checks and a manually initiated cPanel deployment

1. Require `web-ci` and `web-css` to pass on `main`.
2. Confirm the cPanel user can log in by SSH and execute the existing
   `bash ops/scripts/deploy-cpanel.sh main` script. Its existing production
   configuration, Composer, PHP CLI, backups, Git remotes, scheduler and
   queue/monitoring cron jobs must already be functioning.
3. Set four **GitHub Actions secrets** (not checked into source):
   - `CPANEL_SSH_HOST`: the **cPanel server host**, not the VPS.
   - `CPANEL_SSH_USER`: the account with access to the existing deployment path.
   - `CPANEL_SSH_PRIVATE_KEY`: an SSH private key authorized for that cPanel account.
   - `CPANEL_SSH_KNOWN_HOSTS`: the cPanel server's verified SSH host key line.
     Verify the host fingerprint through the hosting provider or an independently
     trusted channel; do not blindly trust `ssh-keyscan` output.
4. In GitHub Actions, run **Deploy FieldPulse to existing cPanel** on `main`
   and type `DEPLOY`. This workflow is manual-only and targets the existing
   cPanel checkout; it does not copy the application to the VPS.
5. Confirm the post-deployment step verifies `/up`, `/ready` and the
   server's compiled CSS against the checked-out release.
6. The separate **FieldPulse cPanel production smoke** workflow checks the
   live site on main pushes, on demand and daily.

If deployment fails after maintenance mode begins, the existing cPanel script
intentionally leaves the site in maintenance mode; use the cPanel account to
resolve the issue and restore service safely. Do not force it back up with a
failed migration. The script creates a pre-migration database backup, and it
refuses to deploy when tracked host files have changed.

This pipeline does not manage Android production signing keys or iOS signing,
and it does not turn automated CI evidence into physical-device UAT.
Use the mobile repository release checklist for those separate gates.
