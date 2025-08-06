# PolyTrans FAQ & Troubleshooting

This guide covers frequently asked questions and solutions to common issues with the PolyTrans plugin.

## Frequently Asked Questions

### General Questions

#### Q: What is PolyTrans?
**A:** PolyTrans is a WordPress plugin that automates multilingual content translation with support for various translation providers (Google Translate, OpenAI) and advanced workflow management for post-processing translated content.

#### Q: Do I need special hosting for PolyTrans?
**A:** No special hosting required. PolyTrans works on any WordPress hosting that meets the basic requirements (WordPress 5.0+, PHP 7.4+). However, for heavy translation workloads, ensure adequate server resources.

#### Q: Can I use PolyTrans with existing multilingual plugins?
**A:** Yes! PolyTrans integrates seamlessly with Polylang and works alongside SEO plugins like Yoast SEO and RankMath. Without Polylang, some features are limited but core translation functionality works.

#### Q: Is PolyTrans free?
**A:** PolyTrans itself is a proprietary plugin. Translation provider costs vary:
- **Google Translate**: Free (uses public API)
- **OpenAI**: Requires paid OpenAI API credits

### Installation & Setup

#### Q: I activated the plugin but don't see any new menus. What's wrong?
**A:** Check these common issues:
1. **User Permissions**: Ensure your user has `manage_options` capability
2. **Plugin Conflicts**: Temporarily deactivate other plugins to test
3. **Cache**: Clear any caching plugins
4. **JavaScript Errors**: Check browser console for errors

#### Q: How do I know if my server meets the requirements?
**A:** Check your WordPress **Tools → Site Health** page. For PolyTrans specifically:
```php
// Add to a test PHP file to check extensions
echo "JSON: " . (extension_loaded('json') ? '✅' : '❌') . "\n";
echo "cURL: " . (extension_loaded('curl') ? '✅' : '❌') . "\n";
echo "mbstring: " . (extension_loaded('mbstring') ? '✅' : '❌') . "\n";
echo "OpenSSL: " . (extension_loaded('openssl') ? '✅' : '❌') . "\n";
```

#### Q: Can I use PolyTrans on WordPress multisite?
**A:** PolyTrans is not currently tested or officially supported on WordPress multisite networks. Single-site installations only.

### Translation Provider Setup

#### Q: Google Translate vs OpenAI - which should I choose?
**A:** Choose based on your needs:

**Google Translate (Recommended for beginners):**
- ✅ No setup required
- ✅ Free to use
- ✅ Fast translation
- ❌ Basic quality
- ❌ Less customization

**OpenAI (Advanced users):**
- ✅ Higher quality translations
- ✅ Customizable with prompts
- ✅ Context-aware translation
- ❌ Requires API key setup
- ❌ Costs money per translation

#### Q: My OpenAI API key isn't working. What should I check?
**A:** Common OpenAI API issues:
1. **API Key Format**: Should start with `sk-`
2. **Account Status**: Ensure your OpenAI account has credits
3. **API Permissions**: Verify API key has proper permissions
4. **Rate Limits**: Check if you're hitting rate limits
5. **Network**: Ensure server can reach OpenAI API (HTTPS outbound)

Test your API key:
```bash
curl https://api.openai.com/v1/models \
  -H "Authorization: Bearer YOUR_API_KEY"
```

#### Q: How do I get better translation quality?
**A:** Improve translation quality by:
1. **Use OpenAI**: Generally higher quality than Google Translate
2. **Configure Assistants**: Create specialized assistants for different content types
3. **Add Context**: Use workflows to provide more context to AI
4. **Enable Review**: Have humans review translations before publishing
5. **Customize Prompts**: Fine-tune AI prompts for your content style

### Usage Questions

#### Q: How do I translate a single post?
**A:** To translate a single post:
1. Edit the post you want to translate
2. Scroll to "Translation Scheduler" meta box
3. Select "Regional" scope
4. Choose target language(s)
5. Click "Translate"
6. Monitor progress in **PolyTrans → Translation Logs**

#### Q: Can I bulk translate multiple posts at once?
**A:** Currently, PolyTrans translates posts individually through the post edit interface. For bulk operations:
1. Use WordPress bulk edit to quickly access multiple posts
2. Use workflows to standardize post-processing
3. Consider scripting for large-scale operations (developer feature)

#### Q: What happens if a translation fails?
**A:** When translation fails:
1. **Error Logging**: Failure details are logged in Translation Logs
2. **Retry Logic**: Some failures are automatically retried
3. **Notifications**: You may receive email notifications of failures
4. **Manual Retry**: You can retry failed translations manually

Check logs for specific error messages and solutions.

#### Q: How do I review translations before they go live?
**A:** Enable the review workflow:
1. In "Translation Scheduler", check "Enable Review"
2. Translated posts will be saved as drafts or pending
3. Reviewers receive email notifications
4. Review and publish when ready
5. Original author receives publication notification

### Workflow Questions

#### Q: What are workflows and do I need them?
**A:** Workflows are optional post-processing steps that enhance translated content:
- **SEO optimization**: Improve meta descriptions, titles
- **Internal linking**: Add relevant internal links
- **Content enhancement**: Improve readability, add CTAs
- **Custom processing**: Any automated content modification

You don't need workflows for basic translation, but they significantly improve content quality.

#### Q: How do I create my first workflow?
**A:** Start with a simple workflow:
1. Go to **PolyTrans → Workflows**
2. Click "Add New Workflow"
3. Name it (e.g., "SEO Enhancement")
4. Add an AI Assistant step
5. Configure it to improve meta descriptions
6. Set output actions to update Yoast SEO fields
7. Test on a sample post
8. Enable if results look good

#### Q: Can workflows break my content?
**A:** Workflows have safety measures:
- **Test Mode**: Preview changes before applying
- **Backup**: WordPress revisions preserve original content
- **Logging**: All changes are logged
- **User Attribution**: Changes are properly attributed

Always test workflows thoroughly before enabling them.

## Troubleshooting Common Issues

### Translation Issues

#### ❌ "Translation failed: API authentication error"
**Cause:** Invalid or expired API credentials  
**Solution:**
1. Check API key format and validity
2. Verify account status and credits (OpenAI)
3. Test API connection outside WordPress
4. Re-enter API key in settings

#### ❌ "Translation failed: Rate limit exceeded"
**Cause:** Too many API requests in short time  
**Solution:**
1. Wait and retry (rate limits reset over time)
2. Reduce concurrent translations
3. Upgrade API plan if consistently hitting limits
4. Implement delays between translations

#### ❌ "No target languages configured"
**Cause:** Languages not set up in settings  
**Solution:**
1. Go to **Settings → Translation Settings**
2. Configure allowed source and target languages
3. Save settings
4. Retry translation

#### ❌ "Post creation failed"
**Cause:** Various content or permission issues  
**Solution:**
1. Check user permissions for post creation
2. Verify post content is valid
3. Check for required custom fields
4. Review error logs for specific details

### Interface Issues

#### ❌ Translation meta boxes not appearing
**Cause:** Plugin not loaded or user permissions  
**Solution:**
1. Verify plugin is active
2. Check user has `edit_posts` capability
3. Try deactivating other plugins temporarily
4. Clear cache if using caching plugins

#### ❌ "Settings not saving"
**Cause:** Form submission or permission issues  
**Solution:**
1. Check for JavaScript errors in browser console
2. Verify user has `manage_options` capability
3. Try disabling other plugins temporarily
4. Check server error logs

#### ❌ Workflow interface slow or unresponsive
**Cause:** Resource constraints or conflicts  
**Solution:**
1. Check server resources (CPU, memory)
2. Reduce number of workflow steps temporarily
3. Clear browser cache
4. Test with default WordPress theme

### Performance Issues

#### ❌ Translation taking very long
**Cause:** API delays or server resource constraints  
**Solution:**
1. Check Translation Logs for bottlenecks
2. Monitor server resources during translation
3. Consider translating fewer languages at once
4. Check network connectivity to API providers

#### ❌ WordPress admin slow after activation
**Cause:** Plugin resource usage or conflicts  
**Solution:**
1. Check server resources (memory, CPU)
2. Disable non-essential plugins temporarily
3. Check for plugin conflicts
4. Consider server upgrade if consistently slow

### Configuration Issues

#### ❌ Languages not appearing in dropdown
**Cause:** Language configuration missing  
**Solution:**
1. Configure languages in **Settings → Translation Settings**
2. Ensure Polylang is installed and configured (recommended)
3. Check for proper language code format (en, es, fr, etc.)

#### ❌ Workflows not executing
**Cause:** Workflow configuration or trigger issues  
**Solution:**
1. Verify workflow is active
2. Check trigger conditions
3. Test workflow manually
4. Review workflow logs for errors
5. Ensure OpenAI API is configured (for AI steps)

### Email Notification Issues

#### ❌ Not receiving notification emails
**Cause:** WordPress email configuration  
**Solution:**
1. Test WordPress email functionality
2. Check spam/junk folders
3. Verify email addresses in user profiles
4. Consider SMTP plugin for reliable email delivery

#### ❌ Emails going to spam
**Cause:** Email delivery reputation  
**Solution:**
1. Install SMTP plugin with proper authentication
2. Configure SPF/DKIM records for your domain
3. Use reputable email service (not default PHP mail)

## Advanced Troubleshooting

### Enable Debug Logging

Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('POLYTRANS_DEBUG', true);
```

Check logs at:
- WordPress: `/wp-content/debug.log`
- PolyTrans: Database table `polytrans_logs`

### Test API Connectivity

Create a test file to check API access:
```php
<?php
// Test Google Translate API
$response = wp_remote_get('https://translate.googleapis.com/');
var_dump($response);

// Test OpenAI API
$response = wp_remote_post('https://api.openai.com/v1/models', [
    'headers' => ['Authorization' => 'Bearer YOUR_API_KEY']
]);
var_dump($response);
?>
```

### Check Database Tables

Verify PolyTrans database tables exist:
```sql
SHOW TABLES LIKE '%polytrans%';
DESCRIBE polytrans_logs;
```

### Plugin Conflict Testing

Test for plugin conflicts:
1. Deactivate all other plugins
2. Test PolyTrans functionality
3. Reactivate plugins one by one
4. Identify conflicting plugin

## Performance Optimization

### Server Optimization
- **PHP Memory**: Increase to 512MB for large translations
- **Execution Time**: Set to 300+ seconds for complex workflows
- **Max Input Vars**: Increase to 3000+ for complex forms

### Translation Optimization
- **Batch Translations**: Translate to multiple languages at once
- **Queue Management**: Monitor translation queue size
- **API Usage**: Optimize API calls to reduce costs

### Workflow Optimization
- **Step Efficiency**: Keep workflow steps focused and efficient
- **Test Thoroughly**: Test all workflows before enabling
- **Monitor Performance**: Track workflow execution times

## Getting Additional Help

### Before Contacting Support

1. **Check this FAQ**: Many issues are covered here
2. **Review Logs**: Check both WordPress and PolyTrans logs
3. **Test Isolation**: Disable other plugins to test
4. **Document Issue**: Note exact error messages and steps to reproduce

### Useful Information for Support

When contacting support, include:
- WordPress version
- PHP version
- PolyTrans version
- Active plugins list
- Exact error messages
- Steps to reproduce issue
- Server environment details

### Self-Help Resources

- **[Installation Guide](INSTALLATION.md)**: Installation help
- **[Interface Guide](INTERFACE.md)**: Using the interface
- **[API Documentation](../../API-DOCUMENTATION.md)**: Technical details
- **[Architecture Overview](../../ARCHITECTURE.md)**: How it works

---

*Still having issues? Document the exact problem and error messages, then contact support with details.*
