<div align="center">
    <img width="150" src="./doc/images/logo.png" alt="Itech World logo">
</div>

<h1 align="center">Content AI Bundle</h1>
<h3 align="center">AI-powered content assistant for <a href="https://sulu.io" target="_blank">Sulu CMS 3.x</a></h3>

<p align="center">
    <a href="LICENSE" target="_blank">
        <img src="https://img.shields.io/badge/license-MIT-green" alt="License">
    </a>
    <a href="https://sulu.io/" target="_blank">
        <img src="https://img.shields.io/badge/sulu-%3E=3.0-cyan" alt="Sulu compatibility">
    </a>
    <img src="https://img.shields.io/badge/php-%3E=8.2-blue" alt="PHP">
</p>

<p align="center">
    Bring generative AI to your Sulu content, straight from the admin:<br>
    generate and rewrite page/article blocks, a per-field writing assistant, automated SEO metadata,<br>
    image metadata from vision, metadata translation, and reusable AI experts &amp; prompts.
</p>

<p align="center">
    <strong>Your own provider keys (OpenAI, Mistral, Anthropic), used directly — no SaaS in between.</strong>
</p>

---

> ⚠️ **Work in progress.** This bundle is under active development. The public API and configuration may still change before `1.0`.

## Requirements

* PHP >= 8.2 (with the bundled `sodium` extension, used to encrypt provider keys)
* Sulu CMS >= 3.0
* Symfony >= 7.0
* Doctrine ORM >= 3.0
* [`symfony/ai`](https://github.com/symfony/ai) platform bridges `~0.10` (OpenAI, Mistral and/or Anthropic)

## Features

* **In-admin AI assistant** — a toolbar button on the page/article form opens a chat panel that can **generate** content (filling the document's blocks via structured output constrained to *your* templates) and **transform** existing content in place (translate, rephrase, shorten…) with natural-language targeting.
* **Per-field writing assistant** — an AI button overlaid on each text field opens a stateless *Writing Assistant* (selected text + an expert + a predefined prompt + a free instruction) that rewrites just that field, in its locale.
* **Automated SEO metadata** — a *Generate SEO tags* button on the SEO tab fills title, description and keywords from the page content.
* **Media metadata** — on the media form, *Generate metadata* describes an image with a vision model (title + alt-text), and *Translate metadata* translates the media metadata from one locale to another.
* **AI experts & predefined prompts** — admin CRUD (under *Settings → AI Assistant*) to manage reusable experts (personas / brand voice) and predefined prompts, used by the writing assistant.
* **Provider configuration in the admin** — manage providers and API keys from the admin. **Keys are encrypted at rest**; the active provider is resolved dynamically, with a fallback to your `.env` configuration.
* **Multi-provider & locale-aware** — OpenAI, Mistral, Anthropic. Output always follows the content locale, and a shared style guidance avoids the usual "AI tells" (em dashes, emojis, clichés).

## Installation

### 1. Require the bundle

```bash
composer require itech-world/sulu-content-ai-bundle
```

### 2. Register the bundle

If Symfony Flex does not do it automatically:

```php
// config/bundles.php
return [
    // ...
    ItechWorld\SuluContentAiBundle\ItechWorldSuluContentAiBundle::class => ['all' => true],
];
```

### 3. Register the routes

```yaml
# config/routes.yaml
itech_world_sulu_content_ai:
    resource: '@ItechWorldSuluContentAiBundle/src/Controller/'
    type: attribute

itech_world_sulu_content_ai_admin_api:
    resource: '@ItechWorldSuluContentAiBundle/config/routing_admin_api.yaml'
    prefix: /admin/api
```

### 4. Configure the AI providers

The bundle consumes the `symfony/ai` platforms. Declare the providers you use and keep the keys in the environment:

```yaml
# config/packages/ai.yaml
ai:
    platform:
        openai:
            api_key: '%env(OPENAI_API_KEY)%'
        anthropic:
            api_key: '%env(ANTHROPIC_API_KEY)%'
        mistral:
            api_key: '%env(MISTRAL_API_KEY)%'
```

```dotenv
# .env.local
MISTRAL_API_KEY=your-key-here
```

> You can also configure providers **from the admin** (*Settings → AI Assistant → Providers*), where the key is stored encrypted. When a provider is configured there, it takes precedence over the `.env` fallback.

### 5. Register the admin assets

```js
// assets/admin/app.js
import 'sulu-itech-world-sulu-content-ai-bundle';
```

Then rebuild the admin assets (`npm run build` in `assets/admin/`).

### 6. Update the database schema

```bash
php bin/console doctrine:schema:update --force
# or generate and run a migration
```

### 7. Clear the cache

```bash
php bin/adminconsole cache:clear
php bin/console cache:clear
```

## Configuration

The bundle exposes a minimal configuration under the `itech_world_sulu_content_ai` key:

```yaml
# config/packages/itech_world_sulu_content_ai.yaml
itech_world_sulu_content_ai:
    default_provider: mistral            # openai | anthropic | mistral (env fallback)
    model: 'mistral-small-latest'        # text model (needs json_schema support)
    vision_model: 'pixtral-12b-latest'   # vision model for image metadata
    encryption_key: '%env(APP_SECRET)%'  # secret used to encrypt provider keys at rest
```

See [`doc/configuration.md`](doc/configuration.md) for the full setup (agent configuration, per-field button positioning, etc.).

## Usage

Everything happens from the Sulu admin:

* **Pages / articles** — the *AI Assistant* toolbar button (chat) and the per-field AI button on text fields.
* **SEO tab** — the *Generate SEO tags* button.
* **Media** — the *Generate metadata* (vision) and *Translate metadata* buttons.
* **Settings → AI Assistant** — manage **Experts**, **Prompts** and **Providers** (API keys, models).

> The generated content is inserted into the form as unsaved changes: review it, then save (or discard) as usual.

## Architecture

```
SuluContentAiBundle/
├── config/                 # Services, admin API routing, list/form metadata, image formats
├── src/
│   ├── Admin/              # Sulu admin integration (toolbar actions, settings views)
│   ├── Ai/                 # Generators, platform resolver, field mappers orchestration
│   ├── Controller/         # Back-office API (chat, SEO, media, per-field, providers)
│   ├── Entity/             # Conversations, experts, prompts, provider configs
│   ├── Repository/
│   ├── Schema/             # Template/block introspection → JSON Schema
│   └── Security/           # API key encryption
├── public/js/              # Admin React components (toolbar actions, field types)
├── translations/           # Admin translations (en, fr, de)
└── doc/                    # Documentation
```

## Available translations

* English
* French
* German

## 🐛 Bug and Idea

See the [open issues](https://github.com/steeven-th/SuluContentAiBundle/issues) for a list of proposed features (and known issues).

## 💰 Support me

You can buy me a coffee to support me — **this bundle is 100% free**.

[Buy me a coffee](https://www.buymeacoffee.com/steeven.th)

## 👨‍💻 Contact

<a href="https://steeven-th.dev"><img src="https://avatars.githubusercontent.com/u/82022828?s=96&v=4" width="50"></a>
<a href="https://x.com/ThomasSteeven2"><img src="./doc/images/x.webp" width="50" alt="x.com"></a>
<a href="https://www.linkedin.com/in/steeven-thomas-221b02b8/"><img src="./doc/images/linkedin.png" width="50" alt="Linkedin"></a>

## 📘&nbsp; License

This bundle is under the [MIT License](LICENSE).
