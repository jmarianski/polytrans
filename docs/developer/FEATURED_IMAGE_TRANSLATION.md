# Featured Image Translation Implementation

## Overview

This document describes the implementation of automatic featured image translation in the PolyTrans plugin. This feature ensures that when posts are translated, their featured images are also properly duplicated and translated, including all metadata (alt text, title, caption, description).

## Problem Statement

Previously, the PolyTrans plugin would:
- Copy the `_thumbnail_id` meta from the original post to the translated post
- Both posts would point to the **same image attachment**
- Image metadata (alt text, title, caption, description) remained in the original language
- No Polylang media translation relationships were established

## Solution

The implementation adds featured image translation through three key modifications:

### 1. Content Preparation (Translation Payload)

**File**: `/includes/core/class-background-processor.php`

Featured image metadata is now extracted and included in the translation payload:

```php
$featured_image_data = null;
if (has_post_thumbnail($post_id)) {
    $thumbnail_id = get_post_thumbnail_id($post_id);
    $attachment = get_post($thumbnail_id);
    
    if ($attachment) {
        $featured_image_data = [
            'id' => $thumbnail_id,
            'alt' => get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true),
            'title' => $attachment->post_title,
            'caption' => $attachment->post_excerpt,
            'description' => $attachment->post_content,
            'filename' => basename(get_attached_file($thumbnail_id))
        ];
    }
}

$content_to_translate = [
    'title' => $post->post_title,
    'content' => $post->post_content,
    'excerpt' => $post->post_excerpt,
    'meta' => json_decode(json_encode($meta), true),
    'featured_image' => $featured_image_data  // NEW!
];
```

### 2. Translation Processing

**No changes required** - Both Google and OpenAI providers use deep recursive translation, so they automatically translate the nested `featured_image` object.

### 3. Media Translation Manager

**File**: `/includes/receiver/managers/class-translation-media-manager.php` (NEW)

A new manager class handles the creation and setup of translated media attachments:

**Key responsibilities**:
- Check if a media translation already exists using Polylang API
- Create a duplicate attachment if needed
- Copy the physical image file
- Update attachment metadata with translated values
- Establish Polylang translation relationships
- Set the translated post's featured image to the new attachment

**Main method**: `setup_featured_image($new_post_id, $original_post_id, $target_language, $translated)`

### 4. Integration Points

**File**: `/includes/receiver/class-translation-coordinator.php`

The coordinator now calls the media manager after post creation:

```php
// Setup featured image translation
$media_manager = new PolyTrans_Translation_Media_Manager();
$media_manager->setup_featured_image($new_post_id, $original_post_id, $target_language, $translated);
```

**File**: `/includes/receiver/managers/class-translation-metadata-manager.php`

Modified to skip `_thumbnail_id` during metadata copying, since it's now handled by the media manager:

```php
private function should_skip_meta_key($key)
{
    $skip_keys = [
        '_polytrans_translation_status',
        '_edit_lock',
        '_edit_last',
        '_thumbnail_id',  // NEW - handled by media manager
    ];
    // ...
}
```

## How It Works

### Step-by-Step Flow

1. **Original post has featured image**
   - Image ID: 123
   - Alt text: "Beautiful sunset"
   - Title: "Sunset Photo"

2. **Translation preparation**
   - Featured image metadata extracted
   - Added to `$content_to_translate['featured_image']`

3. **Translation provider processes**
   - Returns translated metadata:
     - Alt text: "Hermoso atardecer" (Spanish)
     - Title: "Foto de Atardecer" (Spanish)

4. **Post creation**
   - New post created (ID: 456)
   - Metadata manager skips `_thumbnail_id`

5. **Media manager processes**
   - Checks if translation exists for image 123 in Spanish
   - If not exists:
     - Duplicates attachment (new ID: 789)
     - Copies physical file
     - Sets language to Spanish
     - Updates metadata with translated values
   - Links attachments: `pll_save_post_translations([en => 123, es => 789])`
   - Sets featured image: `set_post_thumbnail(456, 789)`

## Polylang Integration

The implementation leverages Polylang's media translation capabilities:

- `pll_get_post_translations($attachment_id)` - Get existing media translations
- `pll_set_post_language($attachment_id, $lang)` - Set attachment language
- `pll_save_post_translations($translations)` - Link media translations

**Benefits**:
- Media appears in Polylang's media translation interface
- Translation relationships are properly tracked
- Compatible with Polylang's media synchronization features

## Graceful Degradation

The implementation includes fallbacks for when Polylang is not available:

```php
if (!function_exists('pll_get_post_translations')) {
    // Log warning and skip media translation
    // Post will still be created successfully
    return;
}
```

## Configuration

No configuration required - the feature works automatically when:
- Post has a featured image
- Polylang plugin is active
- Translation is triggered normally

## File Structure

```
includes/
├── core/
│   └── class-background-processor.php          (Modified)
└── receiver/
    ├── class-translation-coordinator.php       (Modified)
    └── managers/
        ├── class-translation-media-manager.php (NEW)
        └── class-translation-metadata-manager.php (Modified)
```

## Testing

To test the feature:

1. Create a post with a featured image that has:
   - Alt text
   - Title
   - Caption
   - Description

2. Translate the post to another language

3. Verify the translated post:
   - Has its own featured image attachment
   - Image metadata is translated
   - Polylang shows translation relationship in media library

4. Check Polylang media library:
   - Both images should appear
   - Translation link should be visible

## Logging

The media manager logs its actions for debugging:

```php
PolyTrans_Logs_Manager::log("Creating translated media attachment", "info", [
    'original_attachment_id' => $original_attachment_id,
    'new_attachment_id' => $new_attachment_id,
    'target_language' => $target_language
]);
```

Check logs at: **PolyTrans → Logs** in WordPress admin.

## Future Enhancements

Potential improvements for future versions:

1. **Bulk media translation** - Translate all images in post content, not just featured
2. **Media reuse** - Option to reuse existing translated media instead of duplicating
3. **Image optimization** - Compress translated images automatically
4. **Alt text generation** - Use AI to generate contextually appropriate alt text
5. **CDN integration** - Sync translated media to CDN automatically

## Troubleshooting

### Featured image not translating

**Check**:
- Is Polylang active?
- Does the original post have a featured image?
- Check logs for media manager errors
- Verify file permissions in uploads directory

### Duplicate images created

This is expected behavior. Each language version gets its own attachment for proper translation tracking.

### Missing alt text in translation

**Check**:
- Was alt text present in the original?
- Check translation provider logs
- Verify the `featured_image` object was included in translation payload

## Related Documentation

- [API Documentation](API-DOCUMENTATION.md)
- [Architecture Overview](ARCHITECTURE.md)
- [Development Setup](DEVELOPMENT_SETUP.md)
- [Contributing Guide](CONTRIBUTING.md)

## Credits

Implemented as part of the PolyTrans continuous improvement initiative to provide comprehensive multilingual support including media assets.
