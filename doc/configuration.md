# Configuration

`SuluContentAiBundle` runs on top of [`symfony/ai`](https://github.com/symfony/ai). The
AI providers ("platforms") and the agent are configured by **your project** in
`config/packages/ai.yaml` — the bundle does not prepend any AI configuration, so it stays
immune to `symfony/ai` pre-1.0 schema changes.

## 1. Provider bridges

The three provider bridges are pulled in automatically as dependencies of this bundle:

- `symfony/ai-open-ai-platform`
- `symfony/ai-anthropic-platform`
- `symfony/ai-mistral-platform`

## 2. API keys

Each bridge recipe adds its key placeholder to `.env`. Put the **real** values in
`.env.local` (never commit them):

```dotenv
# .env.local
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...
MISTRAL_API_KEY=...
```

Only the keys of the providers you actually use need a value.

## 3. `config/packages/ai.yaml`

Declare the platforms you use and an agent named **`content_ai`** — the bundle consumes
`ai.agent.content_ai` by convention:

```yaml
ai:
    platform:
        openai:
            api_key: '%env(OPENAI_API_KEY)%'
        anthropic:
            api_key: '%env(ANTHROPIC_API_KEY)%'
        mistral:
            api_key: '%env(MISTRAL_API_KEY)%'

    agent:
        content_ai:
            platform: 'ai.platform.openai'
            model: 'gpt-4o-mini'
            prompt: |
                You are a content assistant integrated into the Sulu CMS admin.
                You help editors write and structure page and article content.
```

You are free to point the `content_ai` agent to any platform/model, and to tune the system
prompt. Structured-output generation (block filling) does not require any extra setup — the
`symfony/ai` JSON-schema machinery is wired automatically.

## 4. Bundle configuration

The bundle exposes a minimal configuration under the `itech_world_sulu_content_ai` key:

```yaml
# config/packages/itech_world_sulu_content_ai.yaml
itech_world_sulu_content_ai:
    default_provider: openai        # openai | anthropic | mistral
    model: 'mistral-small-latest'   # model for content/SEO/translation (needs json_schema support)
    vision_model: 'pixtral-12b-latest' # vision-capable model for image metadata (needs image input)
```

> More options (per-task model mapping, quotas, etc.) are added in later phases.

## 5. Per-field AI button position

The per-field writing assistant overlays a small button in the top-right corner of
each text field (`right: 2px` by default). If another bundle also places a button
there (e.g. a translation button), shift the AI button aside by overriding the
`--iw-content-ai-field-button-right` CSS custom property — no bundle-to-bundle
coupling required.

The Sulu admin build has no CSS entry point by default (`assets/admin/` only
ships `app.js`), so add your custom CSS as follows:

**1.** Create a CSS file next to your admin entry, e.g. `assets/admin/custom.css`:

```css
/* Move the AI button left, e.g. to sit beside a translation button */
:root {
    --iw-content-ai-field-button-right: 34px;
}
```

**2.** Import it from `assets/admin/app.js` — the project entry point where you
already import the admin bundles:

```js
// assets/admin/app.js
import 'sulu-itech-world-sulu-content-ai-bundle';
// ... your other bundles ...
import './custom.css';
```

> The `sulu-itech-world-sulu-content-ai-bundle` import only resolves if the bundle
> is declared in `assets/admin/package.json`:
> `"sulu-itech-world-sulu-content-ai-bundle": "file:../../vendor/itech-world/sulu-content-ai-bundle/public/js"`
> — see step 5 of the installation in the [README](../README.md).

**3.** Rebuild the admin assets (`npm run build` in `assets/admin/`). Sulu's
webpack config already handles `.css` imports through `css-loader`, so nothing
else is required.

> The same approach lets you override any other admin CSS custom property exposed
> by this or other bundles.
