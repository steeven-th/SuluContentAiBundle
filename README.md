# SuluContentAiBundle

AI-powered content assistant for **Sulu CMS 3.x**. It brings generative AI to page and
article content, powered by the [`symfony/ai`](https://github.com/symfony/ai) component
(multi-provider: **OpenAI**, **Mistral**, **Anthropic**).

> ⚠️ **Work in progress.** This bundle is under active development. The public API and
> configuration are not stable yet.

## Features

- **In-admin AI assistant:** a toolbar button on the page/article edit form opens a chat
  panel. It can **generate** content (filling the document's blocks via structured output
  constrained to the project's own block schema) and **transform** existing content in place
  (translate, rephrase, shorten…) with natural-language targeting (e.g. *"shorten the intro"*).
  It always writes in the page locale, and the page title is preserved unless explicitly
  allowed. *(MVP)*
- **Automated SEO metadata:** a *"Generate SEO tags"* button on the SEO tab fills the title,
  description and keywords from the page content, in the page locale. *(MVP)*
- **Media metadata translation:** a *"Translate metadata"* button on the media form translates
  the metadata (title, description, copyright, credits) from a chosen source locale into the
  current one. *(MVP)*
- **Media metadata from vision:** a *"Generate metadata"* button on the media form analyzes the
  image with a vision model (Pixtral by default) to produce a title and an alt-text, in the
  current locale — with an option to improve the existing metadata. *(MVP)*
- **AI experts & predefined prompts:** admin screens (under *Settings*) to manage reusable AI
  experts (personas / brand voice) and predefined prompts. *(MVP)*
- **Per-field writing assistant:** an AI button overlaid on each text field opens a stateless
  *"Writing Assistant"* panel (selected text + an expert + a predefined prompt + a free
  instruction) that rewrites just that field, in its locale. *(MVP)*
- **Provider configuration in the admin:** manage AI providers (API key, models) from the admin
  under *Settings → AI Assistant*. Keys are **encrypted at rest**; if none is configured, the
  bundle falls back to the `.env` configuration. *(MVP)*
- **Telegram bot:** create page/article drafts remotely from text and media. *(planned,
  second lot)*

## Requirements

- PHP `^8.2`
- Sulu `^3.0`
- Symfony `^7.0`
- `symfony/ai-*` `~0.10.0`

## Installation

```bash
composer require itech-world/sulu-content-ai-bundle
```

Register the bundle if Symfony Flex does not do it automatically:

```php
// config/bundles.php
return [
    // ...
    ItechWorld\SuluContentAiBundle\ItechWorldSuluContentAiBundle::class => ['all' => true],
];
```

Configuration (AI providers, API keys, models) is documented in a later phase — see the
`config/packages/` setup and the `doc/` directory once available.

## Conventions

| Item | Value |
|------|-------|
| PHP namespace | `ItechWorld\SuluContentAiBundle` |
| Config prefix | `itech_world_sulu_content_ai` |
| Service IDs | `itech_world.sulu_content_ai.*` |
| Admin config key | `iw_sulu_content_ai` |
| Security context | `sulu.iw_sulu_content_ai.*` |

## License

MIT
