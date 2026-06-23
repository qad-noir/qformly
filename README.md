# Qformly

Qformly turns questionnaire documents into editable, shareable Google Forms. Upload a `.txt` or `.docx` questionnaire, review the extracted sections and questions, correct anything the parser missed, then generate a Google Form or use the built-in mock generator locally.

The project was inspired by the repetitive work of rebuilding academic surveys, research instruments, and feedback questionnaires in Google Forms by hand. Qformly keeps people in control of the final form while taking care of the slow first draft.

## What it does

- Authenticated questionnaire projects with private document uploads
- TXT and DOCX text extraction
- Predictable rule-based parsing of sections, numbered questions, lettered choices, checkboxes, Likert prompts, and open-text questions
- An editable Livewire questionnaire editor for sections, questions, types, required flags, and choices
- Mock Google Form generation for local development
- Optional real Google OAuth and Google Forms API generation
- Generated-form history with respondent and edit links
- Per-user project, connection, and generated-form authorization

Qformly currently uses deterministic parsing rules, not AI or an LLM.

## Technology

- PHP 8.3+
- Laravel 13
- Laravel Jetstream and Fortify authentication
- Livewire 3 class components (no Volt)
- Blade and Tailwind CSS
- MySQL-compatible Laravel migrations and Eloquent
- Laravel filesystem storage and queues
- Google API PHP Client for OAuth and Google Forms API access
- PHPUnit feature and unit tests

## Requirements

- PHP 8.3 or later, with the `zip` extension enabled for DOCX extraction
- Composer
- Node.js and npm
- MySQL, MariaDB, or another Laravel-supported database

## Installation

```bash
git clone git clone https://github.com/qad-noir/qformly.git qformly
cd qformly
composer install
copy .env.example .env
php artisan key:generate
npm install
```

On macOS or Linux, use this instead of `copy`:

```bash
cp .env.example .env
```

Configure your database in `.env`, then run:

```bash
php artisan migrate
php artisan db:seed
npm run build
php artisan serve
```

Open the URL shown by Laravel, register an account, and create a questionnaire project. The optional demo seeder creates a local sample user at `test@example.com` with password `password`.

## Local development

Run the Laravel server, queue worker, logs, and Vite development server together:

```bash
composer run dev
```

Or run the parts independently:

```bash
php artisan serve
php artisan queue:listen
npm run dev
```

## Questionnaire workflow

1. Sign in and choose **New Questionnaire**.
2. Enter a project title and upload a `.txt` or `.docx` file (up to 5 MB).
3. Qformly stores the upload privately, extracts text, and builds an editable draft.
4. Review sections, question types, options, and required flags in the editor.
5. Use **Reparse original** if an existing project was created before a parser improvement. This replaces the current parsed sections, questions, and options with a fresh parse of the saved source text.
6. Generate a mock or real Google Form.

PDF uploads are intentionally not supported in the current MVP; the app shows a friendly message instead.

## Google Forms modes

### Mock mode (default)

Mock mode needs no Google credentials and is the safest way to develop locally:

```dotenv
GOOGLE_FORMS_MOCK=true
```

Generated records receive safe placeholder respondent and edit links. No Google account is contacted.

### Real Google OAuth and Forms API mode

To generate actual Google Forms, create an OAuth **Web application** in Google Cloud and enable:

- Google Forms API
- Google Drive API

Configure the OAuth consent screen, create credentials, and add the redirect URI exactly as it appears in `.env`:

```dotenv
GOOGLE_CLIENT_ID="your OAuth web client ID"
GOOGLE_CLIENT_SECRET="your OAuth web client secret"
GOOGLE_REDIRECT_URI="http://127.0.0.1:8000/google/callback"
GOOGLE_FORMS_MOCK=false
GOOGLE_SCOPES="openid,email,profile,https://www.googleapis.com/auth/forms.body,https://www.googleapis.com/auth/drive.file"
```

After changing environment values, run:

```bash
php artisan optimize:clear
```

Then open a questionnaire editor, select **Connect Google Account**, complete consent, and generate the form. Qformly stores OAuth access and refresh tokens using Laravel encrypted casts and never displays them in the UI.

> **Testing note:** If Google connection fails during testing, sign out of Google first or use an Incognito window, then connect again with an approved test-user account.

For a Google OAuth application in testing mode, the Google account must be listed as an approved test user in the Google Cloud consent-screen configuration.

## Google Forms mapping

| Qformly type | Google Forms type |
| --- | --- |
| `short_text` | Short text |
| `paragraph` | Paragraph |
| `multiple_choice` | Multiple choice / radio |
| `checkboxes` | Checkboxes |
| `dropdown` | Dropdown |
| `likert` | Multiple choice using the project’s Likert options |

Qformly preserves section order, question numbering, option order, help text, and required settings. New Google Forms are published using the Forms API where the connected account and Workspace policy permit it.

## Testing and verification

```bash
php artisan optimize:clear
php artisan migrate
php artisan route:list
php artisan test
npm run build
```

The test suite covers parsing, inline DOCX checkbox choices, uploads, ownership checks, mock form generation, Google OAuth route protection, OAuth state validation, and friendly real-mode failures when credentials or a Google connection are missing.

## Security notes

- Questionnaire uploads are stored on Laravel’s private local disk by default.
- Questionnaire projects, Google connections, and generated forms are scoped to their owner.
- OAuth state is bound to both the session and initiating user.
- Google access and refresh tokens are encrypted at rest and hidden from model serialization.
- Do not commit `.env` files, OAuth client secrets, access tokens, or refresh tokens.

## Current MVP limits

- PDF extraction is not yet implemented.
- Parsing is intentionally rule-based and should always be reviewed before generation.
- Google API behavior can be constrained by the connected account’s Google Workspace policies.

## License

Qformly is built on Laravel, which is licensed under the MIT license.

