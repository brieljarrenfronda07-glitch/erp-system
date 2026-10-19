# Deploy Petcare to Render

The app uses PHP/Apache in Docker. `render.yaml` provisions one **paid Starter web service** and a **1 GB persistent disk**, keeping SQLite outside the public directory at `/var/data/clinic.sqlite`. Review the recurring cost in Render before confirming deployment. Free web services cannot provide durable SQLite storage.

1. Push the project source to your own GitHub repository. Do not upload `data/`, `.env` files, or credentials. Both `.gitignore` and `.dockerignore` exclude local clinic records.
2. In Render, choose **New → Blueprint**, connect that repository, and use the included `render.yaml`.
3. Set `ADMIN_EMAIL` and a unique `ADMIN_PASSWORD` of at least 12 characters in Render. Set `ADMIN_NAME` if desired. Keep passwords in Render's secret environment settings.
4. Review the Starter service and persistent disk cost, then deploy.
5. Open the exact HTTPS URL assigned by Render. Check `/health.php`, sign in with the configured admin credentials, create a staff account, and test saving a patient.

Production starts with an empty clinic and one administrator. Demo accounts and sample records are not seeded or displayed. Environment credentials initialize an empty database only; changing environment variables later does not reset existing passwords. Local XAMPP behavior is retained.

The deployment does not migrate local records. Back up the local database before arranging a separate migration. Take SQLite-consistent backups of production data and retain copies outside the service.

Local checks: `php -l api.php`, `php -l health.php`, `php -l index.php`, `node --check app.js`, `node --check printing.js`, and `node verify-print.cjs`.

If Docker is installed, build with `docker build -t petcare-clinic .`, then run it with a named volume mounted at `/var/data`, port 10000 published, and admin variables supplied through a local untracked environment file.
