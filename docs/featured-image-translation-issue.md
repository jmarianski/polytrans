# Problem z tłumaczeniem Featured Image w Managed Assistant

## Zidentyfikowana przyczyna

Managed Assistant może niepoprawnie "tłumaczyć" featured image z powodu **niespójności między danymi wejściowymi, promptem a schematem wyjściowym**.

### 1. Dane wejściowe zawierają `featured_image`

W `BackgroundProcessor.php` (linia 508-514) dane przekazywane do tłumaczenia zawierają:

```php
$content_to_translate = [
    'title' => $post->post_title,
    'content' => $post->post_content,
    'excerpt' => $post->post_excerpt,
    'meta' => $meta,
    'featured_image' => $featured_image_data // Array z: id, alt, title, caption, description, filename
];
```

Gdzie `$featured_image_data` to obiekt zawierający:
- `id` - ID załącznika (liczba)
- `alt` - tekst alternatywny (do tłumaczenia)
- `title` - tytuł obrazu (do tłumaczenia)
- `caption` - podpis obrazu (do tłumaczenia)
- `description` - opis obrazu (do tłumaczenia)
- `filename` - nazwa pliku (NIE do tłumaczenia)

### 2. Prompt NIE zawiera `featured_image`

W `translation-user-message-full.twig` prompt zawiera tylko:

```twig
{
  "title": "{{ translated.title|escape('js') }}",
  "content": "{{ translated.content|escape('js') }}",
  "excerpt": "{{ translated.excerpt|escape('js') }}",
  "meta": {
    {% for key, value in translated.meta %}
      ...
    {% endfor %}
  }
}
```

**Brakuje pola `featured_image`!**

### 3. Expected Output Schema NIE zawiera `featured_image`

W `translation-schema-full.json` schema zawiera tylko:
- `title`
- `content`
- `excerpt`
- `meta` (z polami SEO)

**Brakuje definicji `featured_image`!**

### 4. Parser oczekuje `featured_image`

W `OpenAIProvider.php` (linia 359-365) parser ma hardcoded schema:

```php
$schema = [
    'title' => 'string',
    'content' => 'string',
    'excerpt' => 'string',
    'meta' => 'array',
    'featured_image' => 'array' // Parser oczekuje tego pola!
];
```

## Skutki problemu

1. **Asystent nie wie co zrobić z `featured_image`** - dane są dostępne w kontekście (`translated.featured_image`), ale prompt nie mówi mu o tym polu
2. **Asystent może próbować "zgadnąć"** - może dodać `featured_image` do odpowiedzi, ale:
   - Może przetłumaczyć `id` (błąd - powinno pozostać bez zmian)
   - Może przetłumaczyć `filename` (błąd - powinno pozostać bez zmian)
   - Może niepoprawnie przetłumaczyć tekstowe pola
3. **Asystent może zignorować pole** - wtedy parser może mieć problemy jeśli oczekuje `featured_image`
4. **Brak jasnych instrukcji** - asystent nie wie, które pola przetłumaczyć (alt, title, caption, description), a które zostawić bez zmian (id, filename)

## Rozwiązanie

### Opcja 1: Dodać `featured_image` do promptu z jasnymi instrukcjami (ZALECANE)

Zaktualizować `translation-user-message-full.twig`:

```twig
{
  "title": "{{ translated.title|escape('js') }}",
  "content": "{{ translated.content|escape('js') }}",
  "excerpt": "{{ translated.excerpt|escape('js') }}",
  "meta": {
    {% for key, value in translated.meta %}
      ...
    {% endfor %}
  }{% if translated.featured_image %},
  "featured_image": {
    "id": {{ translated.featured_image.id }},
    "alt": "{{ translated.featured_image.alt|escape('js') }}",
    "title": "{{ translated.featured_image.title|escape('js') }}",
    "caption": "{{ translated.featured_image.caption|escape('js') }}",
    "description": "{{ translated.featured_image.description|escape('js') }}",
    "filename": "{{ translated.featured_image.filename|escape('js') }}"
  }{% endif %}
}

Return the translated content in the same JSON structure. Translate only the values, keep all keys in English.

IMPORTANT: For featured_image:
- Translate ONLY: alt, title, caption, description (text fields)
- DO NOT translate: id (keep original number), filename (keep original filename)
```

### Opcja 2: Dodać `featured_image` do Expected Output Schema

Zaktualizować `translation-schema-full.json`:

```json
{
  "title": {...},
  "content": {...},
  "excerpt": {...},
  "meta": {...},
  "featured_image": {
    "id": {
      "type": "number",
      "target": "featured_image.id",
      "description": "Attachment ID - do not translate, keep original"
    },
    "alt": {
      "type": "string",
      "target": "featured_image.alt"
    },
    "title": {
      "type": "string",
      "target": "featured_image.title"
    },
    "caption": {
      "type": "string",
      "target": "featured_image.caption"
    },
    "description": {
      "type": "string",
      "target": "featured_image.description"
    },
    "filename": {
      "type": "string",
      "target": "featured_image.filename",
      "description": "Filename - do not translate, keep original"
    }
  }
}
```

### Opcja 3: Usunąć `featured_image` z danych wejściowych (jeśli nie jest potrzebne)

Jeśli featured image nie powinno być tłumaczone przez asystenta, można usunąć je z `$content_to_translate` w `BackgroundProcessor.php`.

## Rekomendacja

**Zalecam Opcję 1 + Opcja 2** - dodać `featured_image` zarówno do promptu jak i do schematu, z jasnymi instrukcjami które pola tłumaczyć, a które zostawić bez zmian.

## Rozwiązanie wdrożone

Zaktualizowano:
1. ✅ `translation-user-message-full.twig` - dodano `featured_image` do promptu z instrukcjami
2. ✅ `translation-schema-full.json` - dodano definicję `featured_image` w schemacie

Managed Assistant teraz działa tak samo jak zwykły asystent - otrzymuje pełny JSON z `featured_image` i wie, które pola tłumaczyć (alt, title, caption, description), a które zostawić bez zmian (id, filename).

