[![Built with TideCloak](https://tide.org/badges/built-with-tidecloak.svg)](https://tide.org/tidecloak?utm_source=github&utm_medium=readme&utm_campaign=unlocked)

## Project overview

_TrustGate lets an AI agent handle your paperwork while seeing only the documents you approve, for a limited time, with no single admin able to widen that access._

Built with [TideCloak](https://tide.org/tidecloak?utm_source=github&utm_medium=readme&utm_campaign=unlocked).

### 1. Vision & originality

TrustGate is one consent core with many playbooks. Documents sit in an encrypted vault. An AI agent proposes what a task needs; the owner approves a subset and a time limit, and can revoke anytime. Renting a home and visiting a clinic share the same core, each just a playbook.

### 2. Trust constraint insight

Delegating to an agent is all or nothing today. Operators can read everything, one admin can widen permissions, and "revoke" often just hides a button. Here the database holds ciphertext, grants cover chosen documents for a fixed time, revoking destroys the grant key mid-task, and admin changes need a quorum.

### 3. Value & impact

Lan, a student, asks an agent to apply for a flat. Instead of uploading her whole folder, she approves her ID and income proof for 15 minutes. Her medical summary is out of scope by rule. Operators stop holding unneeded data.

### 4. Why TideCloak matters

TideCloak provides login where passwords never leave the browser, plus the tokens and roles our Laravel API verifies on every request. Without it this is an ordinary app: the operator holds the keys and one admin can change roles. Our encryption and quorum are built to rely on TideCloak's distributed authority, not a master key.
### 5. Prototype & storytelling

Watch for: Lan approves a subset of documents and the Gemini-powered agent builds her application. In the "evil admin" tab, a database dump shows only ciphertext, self-granting a role stalls at 1/2 quorum, and a medical-record request is rejected. Revoking mid-run stops the agent. Demo video: [demo.mp4](./demo.mp4)

## Setup

### Prerequisites

- PHP 8.3 and Composer
- Node.js 18 or newer
- MySQL
- Docker (for TideCloak)
- A Gemini API key (only for the Gemini agent mode)

### 1. Start TideCloak

Follow [docs/TIDECLOAK_SETUP.md](./docs/TIDECLOAK_SETUP.md) to run TideCloak, activate it, create the realm roles `owner`, `admin`, `agent` and the demo users, and export `tidecloak.json` into `frontend/`.

### 2. Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env`: set the MySQL connection and the TideCloak variables (`TIDECLOAK_URL`, `TIDECLOAK_REALM`, `TIDECLOAK_CLIENT_ID`). For the agent, set `LLM_DRIVER=gemini` and `GEMINI_API_KEY` (never commit it), or use `LLM_DRIVER=mock`. Set `APP_DEMO=true` to enable the demo routes.

```bash
php artisan migrate --seed
php artisan serve
php artisan queue:work      # separate terminal
php artisan schedule:work   # separate terminal (grant expiry)
```

### 3. Frontend

```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

Open http://localhost:5173.

### 4. Demo accounts

| User | Role |
| --- | --- |
| `lan` | owner |
| `admin1`, `admin2`, `admin3` | admin (quorum of 2 of 3) |
| `trustgate-agent` | agent |

### 5. Try the demo

1. Log in as `lan` and open the vault (5 synthetic documents).
2. Choose the Rental playbook, give the agent a task, approve a subset with a time limit.
3. Watch the Agent Console build the application.
4. In the evil admin tab, try the database dump, self-grant and out-of-scope request.
5. Run a slow task, revoke mid-run, and verify the audit chain on the timeline.

## Known limitations

- During a grant, the server can read the approved documents.
- Revocation stops further reads but cannot recall data an agent already read and saved elsewhere.
- All data is synthetic. This is a prototype, not production software.
