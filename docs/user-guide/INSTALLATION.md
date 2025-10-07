# Installation

## Requirements

- WordPress 5.0+
- PHP 7.4+
- Polylang plugin (recommended)

## Install

1. Upload `polytrans/` to `/wp-content/plugins/`
2. Activate via **Plugins** menu
3. Go to **Settings → Translation Settings**
4. Configure your translation provider

## Quick Setup

### With Google Translate (Simple)
1. **Settings → Translation Settings**
2. Provider: Select "Google Translate"
3. Save Changes
4. Done!

### With OpenAI (Better Quality)
1. Get API key from [platform.openai.com](https://platform.openai.com/)
2. **Settings → Translation Settings**
3. Provider: Select "OpenAI"
4. Enter API key
5. Create OpenAI assistants for each language pair
6. Map assistants (e.g., `en_to_es` → `asst_abc123`)
7. Test configuration
8. Save

## Configure Languages

**With Polylang:**
- Languages auto-detected from Polylang

**Without Polylang:**
- **Settings → Translation Settings**
- Set source/target languages: `en,es,fr,de`

## First Translation

1. Edit any post
2. Scroll to "Translation Scheduler" meta box
3. Select target language(s)
4. Click "Translate"
5. Check **PolyTrans → Translation Logs** for progress

## Troubleshooting

**No meta box showing:**
- Ensure plugin is activated
- Check user has `manage_options` capability
- Clear cache

**Translation fails:**
- Check **PolyTrans → Translation Logs** for errors
- Verify API key (for OpenAI)
- Check language configuration

**Polylang not detected:**
- Ensure Polylang is activated
- Check Polylang languages are configured

See [FAQ.md](FAQ.md) for more help.
