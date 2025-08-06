# PolyTrans Configuration Guide

This comprehensive guide covers all configuration options for PolyTrans administrators.

## Overview

PolyTrans configuration is organized into several areas:
- **Translation Provider Settings**: Configure Google Translate or OpenAI
- **Language Configuration**: Set up source and target languages
- **Workflow Settings**: Configure post-processing workflows
- **Email Notifications**: Set up notification system
- **Security Settings**: Configure API access and restrictions
- **Performance Settings**: Optimize for your server environment

## Translation Provider Configuration

### Google Translate Provider

Google Translate is the simplest option requiring no API key:

#### Configuration Steps
1. Navigate to **Settings → Translation Settings**
2. Select "Google Translate" as your provider
3. Click "Save Changes"

#### Settings Available
- **Provider Selection**: Choose Google Translate
- **Translation Quality**: Standard Google Translate quality
- **Rate Limits**: Handled automatically by Google

#### Pros and Cons
✅ **Advantages:**
- No setup required
- Free to use
- Fast translation
- No API key management

❌ **Limitations:**
- Basic translation quality
- Limited customization
- No context awareness
- Generic translations

### OpenAI Provider (Advanced)

OpenAI provides higher quality, context-aware translations:

#### Prerequisites
1. **OpenAI Account**: Sign up at [OpenAI Platform](https://platform.openai.com/)
2. **API Key**: Generate an API key with proper permissions
3. **Credits**: Ensure your account has sufficient credits

#### Configuration Steps
1. Navigate to **Settings → Translation Settings**
2. Select "OpenAI" as provider
3. Enter your API key
4. Configure primary language
5. Set up assistant mappings
6. Test configuration

#### API Key Configuration
```
1. Get API Key from OpenAI Platform
2. Copy the key (starts with "sk-")
3. Paste in "OpenAI API Key" field
4. Click "Save Changes"
```

#### Assistant Mapping
Map language pairs to specific OpenAI assistants:

**Format:** `source_to_target`
**Examples:**
- `en_to_es` → `asst_abc123def456` (English to Spanish)
- `en_to_fr` → `asst_def456ghi789` (English to French)
- `es_to_en` → `asst_ghi789jkl012` (Spanish to English)

#### Creating OpenAI Assistants
1. Go to OpenAI Platform → Assistants
2. Create new assistant for each language pair
3. Configure assistant with translation-specific prompts:

```
You are a professional translator specializing in translating content from English to Spanish for a business website. 

Key Requirements:
- Maintain professional tone
- Preserve technical terms appropriately
- Keep HTML formatting intact
- Ensure cultural appropriateness for Spanish-speaking markets
- Maintain SEO-friendly language

When translating:
1. Translate the main content accurately
2. Adapt idioms and expressions culturally
3. Maintain the original structure and formatting
4. Keep proper nouns in original language unless commonly translated
5. Ensure the translation sounds natural to native speakers
```

#### Testing OpenAI Configuration
Use the built-in test interface:
1. Go to **Settings → Translation Settings**
2. Scroll to "Test Configuration" section
3. Enter test text
4. Select language pair
5. Click "Test Translation"
6. Review results

## Language Configuration

### Setting Up Languages

#### Polylang Integration (Recommended)
With Polylang installed:
1. Configure languages in **Languages → Languages**
2. PolyTrans automatically detects available languages
3. Languages appear in PolyTrans settings

#### Manual Configuration (Without Polylang)
Without Polylang, configure manually:
1. Go to **Settings → Translation Settings**
2. In "Language Configuration" section:
   - **Source Languages**: Comma-separated list (e.g., `en,es,fr`)
   - **Target Languages**: Comma-separated list (e.g., `es,fr,de,pl`)

#### Language Code Reference
Use standard ISO language codes:
- `en` - English
- `es` - Spanish  
- `fr` - French
- `de` - German
- `pl` - Polish
- `it` - Italian
- `pt` - Portuguese
- `nl` - Dutch
- `sv` - Swedish
- `da` - Danish
- `no` - Norwegian
- `fi` - Finnish

### Language-Specific Settings

For each target language, configure:

#### Post Status After Translation
- **`publish`**: Automatically publish translated posts
- **`draft`**: Save as draft (requires manual publishing)
- **`pending`**: Set to pending review
- **`same_as_source`**: Match source post status

**Recommendation:** Use `draft` or `pending` for quality control

#### Default Reviewer Assignment
- Assign default reviewers for each language
- Reviewers receive email notifications
- Must have `edit_posts` and `publish_posts` capabilities

#### Language-Specific Workflows
- Configure different workflows per language
- Useful for language-specific SEO optimization
- Cultural adaptation requirements

### Example Language Configuration

```
Source Languages: en, es
Target Languages: es, fr, de, pl

Language-Specific Settings:
┌─────────┬──────────────┬─────────────┬─────────────────┐
│ Language│ Post Status  │ Reviewer    │ Workflow        │
├─────────┼──────────────┼─────────────┼─────────────────┤
│ es      │ pending      │ maria.lopez │ seo-es-workflow │
│ fr      │ draft        │ jean.martin │ seo-fr-workflow │
│ de      │ pending      │ hans.muller │ seo-de-workflow │
│ pl      │ draft        │ anna.kowal  │ seo-pl-workflow │
└─────────┴──────────────┴─────────────┴─────────────────┘
```

## Workflow Configuration

### Understanding Workflows

Workflows are automated post-processing steps that enhance translated content:

#### Common Workflow Types
1. **SEO Enhancement**: Optimize meta descriptions, titles
2. **Internal Linking**: Add relevant internal links
3. **Content Formatting**: Improve readability, structure
4. **Cultural Adaptation**: Adapt content for target culture
5. **Call-to-Action Addition**: Add localized CTAs

### Creating Workflows

#### Basic Workflow Setup
1. Navigate to **PolyTrans → Workflows**
2. Click "Add New Workflow"
3. Configure basic information:
   - **Name**: Descriptive workflow name
   - **Description**: What the workflow does
   - **Status**: Active/Inactive

#### Adding Workflow Steps

##### AI Assistant Steps
For custom AI processing:
1. **Step Type**: Select "AI Assistant"
2. **Assistant Selection**: Choose configured OpenAI assistant
3. **Prompt Configuration**: Customize the AI prompt
4. **Input Data**: Select what data to send to AI
5. **Output Processing**: How to handle AI response

##### Predefined Steps
For common tasks:
1. **Step Type**: Select predefined step type
2. **Configuration**: Set step-specific options
3. **Parameters**: Configure step parameters

#### Output Actions
Configure what should be updated:
- **Post Title**: Update the post title
- **Post Content**: Update the main content
- **Post Excerpt**: Update the excerpt
- **Meta Fields**: Update custom meta fields
- **SEO Fields**: Update Yoast SEO or RankMath fields
- **Categories/Tags**: Update taxonomy terms

#### Workflow Triggers
Set when workflows execute:
- **After Translation**: Run immediately after translation
- **Before Publishing**: Run before post goes live
- **Manual Trigger**: Run only when manually triggered
- **Scheduled**: Run on a schedule

### Advanced Workflow Configuration

#### User Attribution
Assign workflow changes to specific users:
1. **Attribution User**: Select user for change attribution
2. **Permission Validation**: Ensure user has required permissions
3. **Revision History**: Changes appear with proper attribution

#### Conditional Execution
Configure workflows to run conditionally:
- **Post Type**: Only run for specific post types
- **Category**: Only run for specific categories
- **Language**: Run for specific target languages
- **Custom Fields**: Run based on custom field values

#### Error Handling
Configure how workflows handle errors:
- **Retry Logic**: Automatically retry failed steps
- **Fallback Actions**: What to do if step fails
- **Notification**: Notify administrators of failures

### Example Workflow: SEO Enhancement

```yaml
Name: "SEO Enhancement for Spanish Content"
Description: "Improves SEO elements for Spanish translations"

Steps:
1. AI Assistant Step:
   - Assistant: seo-spanish-assistant
   - Input: Post title, content, excerpt
   - Prompt: "Optimize this content for Spanish SEO"

2. Internal Linking Step:
   - Find related Spanish content
   - Add 2-3 relevant internal links
   - Use natural anchor text

Output Actions:
- Update post title (AI-optimized)
- Update meta description (AI-generated)
- Update content (with internal links)
- Update Yoast focus keyword

Triggers:
- After translation completion
- Before publishing
```

## Email Notification Configuration

### Notification Types

#### Review Notifications
Sent to reviewers when translation is ready:
- **Recipients**: Assigned reviewers
- **Trigger**: Translation completion (if review enabled)
- **Content**: Translation details, review links

#### Completion Notifications  
Sent to authors when translation is published:
- **Recipients**: Original post authors
- **Trigger**: Translation publishing
- **Content**: Publication confirmation, links to translated posts

#### Admin Notifications
Sent to administrators for important events:
- **Recipients**: Site administrators
- **Trigger**: Errors, workflow failures
- **Content**: Error details, resolution steps

### Email Configuration

#### SMTP Setup (Recommended)
For reliable email delivery:
1. Install SMTP plugin (e.g., WP Mail SMTP)
2. Configure with your email provider
3. Test email delivery
4. Update PolyTrans notification settings

#### Email Templates
Customize notification content:

##### Review Notification Template
```
Subject: Translation Ready for Review - {post_title}

Hello {reviewer_name},

A translation is ready for your review:

Post: {post_title}
Language: {target_language}
Review Link: {review_url}

Please review and publish when ready.

Best regards,
Translation Team
```

##### Completion Notification Template
```
Subject: Translation Published - {post_title}

Hello {author_name},

Your post has been translated and published:

Original: {original_url}
Translation: {translation_url}
Language: {target_language}

Thank you!
```

#### Notification Settings
Configure when to send notifications:
- **Enable/Disable**: Turn notifications on/off globally
- **Per-Language Settings**: Different settings per language
- **User Preferences**: Allow users to opt out
- **Frequency Limits**: Prevent notification spam

## Security Configuration

### API Access Control

#### Authentication Methods
Configure how external services authenticate:

1. **Bearer Token**: Standard authorization header
   ```
   Authorization: Bearer your-secret-token
   ```

2. **Custom Header**: Custom header authentication
   ```
   X-PolyTrans-Secret: your-secret-token
   ```

3. **POST Parameter**: Include secret in POST data
   ```
   {
     "secret": "your-secret-token",
     "data": "..."
   }
   ```

#### IP Restrictions
Limit API access by IP address:
1. **Allowed IPs**: Comma-separated list of allowed IPs
2. **IP Ranges**: Support for CIDR notation
3. **Dynamic IPs**: Consider using authentication instead

#### Security Best Practices
- **Strong Secrets**: Use long, random authentication tokens
- **HTTPS Only**: Require HTTPS for all API communication
- **Regular Rotation**: Rotate authentication tokens regularly
- **Monitoring**: Monitor API usage for suspicious activity

### User Permission Management

#### Required Capabilities
Ensure users have appropriate permissions:

**Translation Users:**
- `edit_posts` - Can schedule translations
- `edit_others_posts` - Can translate others' posts

**Reviewers:**
- `edit_posts` - Can edit translated content
- `publish_posts` - Can publish reviewed translations

**Administrators:**
- `manage_options` - Can configure plugin settings
- `edit_posts` - Can use all translation features

#### Permission Validation
PolyTrans validates permissions at multiple levels:
- **Admin Interface**: Check capabilities before showing options
- **AJAX Endpoints**: Validate permissions on every request
- **API Endpoints**: Authenticate and authorize API calls
- **Workflow Execution**: Verify user context permissions

## Performance Configuration

### Server Optimization

#### PHP Configuration
Optimize PHP settings for translation workloads:

```ini
; Increase memory for large translations
memory_limit = 512M

; Allow longer execution for complex workflows
max_execution_time = 300

; Handle large form submissions
max_input_vars = 3000
post_max_size = 50M
upload_max_filesize = 50M
```

#### WordPress Configuration
Optimize WordPress for PolyTrans:

```php
// wp-config.php optimizations

// Increase WordPress memory limit
define('WP_MEMORY_LIMIT', '512M');

// Enable object caching if available
define('WP_CACHE', true);

// Optimize database queries
define('WP_DEBUG', false); // Disable in production
```

### Translation Performance

#### Batch Processing
Configure batch sizes for optimal performance:
- **Concurrent Translations**: Limit simultaneous translations
- **Queue Processing**: Configure queue batch sizes
- **API Rate Limits**: Respect provider rate limits

#### Caching Strategy
Implement caching for better performance:
- **Translation Cache**: Cache completed translations
- **API Response Cache**: Cache API responses temporarily
- **Object Caching**: Use WordPress object caching

#### Resource Monitoring
Monitor resource usage:
- **Memory Usage**: Track PHP memory consumption
- **API Usage**: Monitor API call frequency and costs
- **Database Performance**: Optimize database queries

### Workflow Performance

#### Workflow Optimization
Optimize workflows for efficiency:
- **Step Efficiency**: Keep workflow steps focused
- **Parallel Processing**: Run independent steps in parallel
- **Resource Limits**: Set reasonable timeouts and limits

#### Background Processing
Configure background processing:
- **WordPress Cron**: Use WP-Cron for scheduled tasks
- **External Cron**: Use server cron for reliability
- **Queue Management**: Monitor and manage task queues

## Maintenance and Monitoring

### Regular Maintenance Tasks

#### Weekly Tasks
- Review translation logs for errors
- Check API usage and costs
- Monitor workflow performance
- Update language configurations if needed

#### Monthly Tasks
- Rotate authentication tokens
- Review and optimize workflows
- Check translation quality samples
- Update documentation

#### Quarterly Tasks
- Review server performance
- Optimize database tables
- Update API integrations
- Security audit

### Monitoring and Alerting

#### Key Metrics to Monitor
- **Translation Success Rate**: Percentage of successful translations
- **API Response Times**: Monitor provider performance
- **Error Rates**: Track translation and workflow errors
- **User Satisfaction**: Review feedback and quality

#### Setting Up Alerts
Configure alerts for critical issues:
- **Translation Failures**: Alert on repeated failures
- **API Errors**: Alert on authentication or rate limit issues
- **Performance Issues**: Alert on slow response times
- **Security Events**: Alert on unauthorized access attempts

### Backup and Recovery

#### Configuration Backup
Regularly backup PolyTrans configuration:
- **Settings Export**: Export plugin settings
- **Workflow Backup**: Export workflow configurations
- **Language Settings**: Document language configurations

#### Disaster Recovery
Plan for disaster recovery:
- **Restoration Process**: Document restoration steps
- **Configuration Recovery**: Keep configuration backups current
- **Data Recovery**: Plan for translation data recovery

## Advanced Configuration

### Multi-Server Setup

For high-volume environments, configure multi-server architecture:

#### Server Roles
- **Scheduler Server**: Manages translation requests
- **Translator Server**: Performs translation work
- **Receiver Server**: Processes completed translations

#### Configuration Steps
1. **Endpoint Configuration**: Set up communication endpoints
2. **Authentication**: Configure server-to-server authentication
3. **Load Balancing**: Distribute translation workload
4. **Monitoring**: Monitor cross-server communication

### Custom Provider Integration

For custom translation providers:

#### Provider Interface
Implement the translation provider interface:
- **Translation Method**: Core translation functionality
- **Settings Interface**: Provider-specific settings
- **Error Handling**: Handle provider-specific errors

#### Registration
Register custom providers:
```php
add_filter('polytrans_register_providers', function($providers) {
    $providers['custom'] = new Custom_Translation_Provider();
    return $providers;
});
```

### Integration with External Systems

#### Webhook Configuration
Set up webhooks for external integration:
- **Incoming Webhooks**: Receive external translation requests
- **Outgoing Webhooks**: Send translation updates to external systems
- **Authentication**: Secure webhook communication

#### API Extensions
Extend the API for custom integrations:
- **Custom Endpoints**: Add application-specific endpoints
- **Data Formats**: Support custom data formats
- **Authentication**: Implement custom authentication methods

---

*This configuration guide covers all major aspects of PolyTrans setup. For specific use cases or advanced customization, consult the [API Documentation](../../API-DOCUMENTATION.md) or contact support.*
